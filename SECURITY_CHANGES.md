# SentinelProc — Security Changes Record

**Project:** SentinelProc — A Real-Time Process Monitoring and Access Control System with Risk-Based Threat Detection
**Components:** Python monitoring agent (`monitoring_agent/`) + Laravel 12 web dashboard (`sentinel_proc-web/`)
**Scope of work:** High and Critical severity findings only. Medium and Low items that were deliberately left open are listed at the end so nothing is over-claimed.
**Verification basis:** Every item below was re-read in the actual source on 2026-09-23. Nothing is listed from memory alone. Where a control is *not* claimed, it is absent from this document.

---

## 1. API authorization — token ability + role gating

**What was wrong.** `POST /api/monitoring/snapshot` was protected only by `auth:sanctum`. Any authenticated principal holding a Sanctum token — including a token belonging to a Viewer or Analyst, or any ordinary personal token — could write telemetry into the database. A read token could post fabricated process data. The read endpoints carried no role checks at all, so any authenticated token could pull telemetry that the web UI restricts to Admin and Analyst.

**What changed.**
- The write route now requires a token granted the specific ability: `->middleware('ability:monitoring:write')`.
- The three read routes were moved inside `Route::middleware('role:admin,analyst')`, mirroring the web RBAC instead of trusting a token alone.
- The middleware aliases `abilities` and `ability` were registered so Sanctum's ability classes resolve from route files.
- `app:generate-monitoring-token` issues the agent's token scoped to `['monitoring:write']` only — not `['*']`.

**Files.** `sentinel_proc-web/routes/api.php`; `sentinel_proc-web/bootstrap/app.php:19-20` (aliases); `sentinel_proc-web/app/Console/Commands/GenerateMonitoringToken.php:42` (`createToken($tokenName, ['monitoring:write'])`).

**Verification.** `routes/api.php` read directly. `bootstrap/app.php` confirms `'ability' => CheckForAnyAbility::class`. Then executed end to end against the running application after item 13 supplied the missing table: no token → **401**; a valid token carrying `monitoring:write` → **201** with a `snapshot_id`; a valid token carrying only an unrelated ability → **403 `Invalid ability provided`**; the agent's token on a read route → **403** from the role gate. The gates are enforced, not merely declared.

---

## 2. Nested agent payload validation

**What was wrong.** The `snapshot` field was validated only as `required|json`. The decoded JSON was then written row-by-row into `processes` and `alerts` without any inspection of its contents — every field went in as-is, with no type, length, format, or enum constraint. Anything holding the write token could inject arbitrary strings and unbounded lengths (data poisoning), which then render into the Blade tables (stored XSS).

**What changed.** Two layers:
1. `StoreMonitoringSnapshotRequest` validates the envelope: `snapshot => required|json`, `snapshot_timestamp => date_format:Y-m-d H:i:s`, `process_count => integer|min:0`, `cpu_usage`/`disk_usage => numeric|between:0,100`, `memory_usage => numeric|min:0`, `status => in:normal,warning,critical`, with custom messages.
2. `MonitoringApiController::storeSnapshot` re-validates the **decoded** structure with `Validator::make` before any row is created:
   - `processes.*.hash` must match `^[a-fA-F0-9]{64}$`
   - `risk_level` and `severity` are restricted to `low,medium,high,critical`
   - every string has a length cap (`name` 255, `path` 1000, `message` 1000, `details` 5000)
   - `pid`, `cpu_percent`, `memory_mb` must be non-negative
   - a payload that does not decode to an array is rejected with 422

The controller also stopped leaking internals: on failure it returns a generic `Internal error` unless `app.debug` is on, while logging the real exception server-side.

**Files.** `sentinel_proc-web/app/Http/Requests/StoreMonitoringSnapshotRequest.php`; `sentinel_proc-web/app/Http/Controllers/Api/MonitoringApiController.php:18-135`.

**Verification.** Both files read directly. `StoreMonitoringSnapshotRequest::authorize()` returns `$this->user() !== null` as a defence-in-depth check behind the route middleware.

**Deliberately not claimed.** `alerts.*.details` is constrained as a string of max 5000; if the agent ever sends it as a JSON object, that field will be rejected rather than coerced. Worth knowing at demo time.

---

## 3. `.env` write path — directive injection hardening

**What was wrong.** The settings page writes administrator-supplied values into `.env`. Two problems:
- A value containing a newline would have appended an arbitrary new directive — e.g. pasting a "VT key" of `abc\nDB_PASSWORD=x` would rewrite the database password.
- A naive `preg_replace` with the value as the replacement string interprets `$1` / `\1` in the value as a backreference, so certain keys could corrupt the file contents in unexpected ways.

**What changed.**
- `updateVtKey` validates `nullable|string|max:100|regex:/^[A-Za-z0-9]*$/` — letters and digits only, with an explicit error message.
- `updateAlertEmail` validates `nullable|email|max:255`.
- `setEnvValue()` strips `\r` and `\n` from the value before writing, so no value can introduce a second directive regardless of validation drift.
- The rewrite uses `preg_replace_callback(..., fn () => $line, ..., 1)` — the replacement is returned literally, so `$` and `\` in a value cannot be interpreted as backreferences.
- Substitution is limited to the first match; an absent key is appended.
- `config:clear` runs after the write so the new value takes effect without a manual step.
- Both actions write a `SystemAuditLog` record (`update_vt_key`, `update_alert_email`).

**Files.** `sentinel_proc-web/app/Http/Controllers/SettingsController.php`.

**Verification.** File read directly; the `\r\n` strip, the callback form, and the `1` match limit are all present in the current source.

---

## 4. Login brute-force rate limiting

**What was wrong.** `POST /login` had no throttle. Password guessing was unlimited — the highest-value endpoint in the app to leave open, and the one an assessor will try first.

**What changed.** A named limiter `login` registered in the service provider, keyed on the lowercased submitted email plus the client IP, limited to **5 attempts per minute**. The route applies it with `->middleware('throttle:login')`.

Keying on email **and** IP means an attacker cannot rotate through usernames from one host to reset the counter, and cannot lock out a legitimate user by hammering their address from elsewhere without also consuming their own IP budget.

**Files.** `sentinel_proc-web/app/Providers/AppServiceProvider.php:26-31`; `sentinel_proc-web/routes/web.php` (login route).

**Verification.** Both files read directly.

---

## 5. HTTP security headers and Content-Security-Policy

**What was wrong.** Responses carried no security headers. The app could be framed by another origin (clickjacking — meaningful here, because it has state-changing POST forms such as acknowledge and delete), and content type could be sniffed.

**What changed.** A `SecurityHeaders` middleware appended globally:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`
- `Content-Security-Policy` with `default-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'none'`
- `Strict-Transport-Security` set **only when the request is already HTTPS** — sending HSTS over plain HTTP is meaningless and can break a local demo on a browser that has previously cached it.

**Important tradeoff, documented on purpose.** The CSP keeps `'unsafe-inline'` in `script-src`/`style-src` and allows the Tailwind and Chart.js CDN hosts. The Blade views render inline `<script>` blocks and inline `confirm()` handlers, so removing `'unsafe-inline'` would break the dashboard outright; the CDN hosts are required because Tailwind and Chart.js are loaded from them. This is the deliberate application of the rule "do not blindly add headers that break the application." Clickjacking is still blocked by `frame-ancestors 'none'`, which does not depend on `'unsafe-inline'`.

This was not theoretical: when the dashboard chart was introduced, `cdn.jsdelivr.net` had to be added to `script-src` because the new CDN was being blocked — the exact failure mode the rule warns about.

**Files.** `sentinel_proc-web/app/Http/Middleware/SecurityHeaders.php`; `sentinel_proc-web/bootstrap/app.php:15` (`$middleware->append(...)`).

**Verification.** Both files read directly.

---

## 6. Hardcoded database credentials removed from the agent

**What was wrong.** `live_monitor.py` and `virus_total.py` contained a working MySQL username and password in source. For a project that is submitted as a repository and demonstrated live, that is a real credential disclosure, not a theoretical one.

**The constraint that made this non-trivial.** The dashboard's Refresh button does not pass an environment to the agent. `MonitoringController::refresh()` generates a `.bat` file and launches it with `popen('cmd /C start /B ...')` — no environment, no arguments. A naive "read credentials from the environment only" change would have left the Refresh button unable to reach the database, and the live demo would have silently stopped updating.

**What changed.**
- New `agent_config.py`: `db_config()` reads `SENTINEL_DB_*` from the environment; `vt_api_key()` reads `VT_API_KEY`; `load_env_file()` parses `monitoring_agent/.env` (relative to the module's own location, so it works regardless of the directory the agent is launched from) and uses `os.environ.setdefault`, so a real environment variable always wins over the file.
- `monitoring_agent/.env` holds the local values and is **gitignored**.
- `monitoring_agent/.env.example` is the committed template, with placeholder credentials only and the VirusTotal key commented out.
- Non-secret connection details (host, port, database name) keep defaults so an unconfigured checkout degrades to "no database" rather than crashing; the credentials deliberately have **no** default, so the agent cannot silently connect with a guessable account.

**Files.** `monitoring_agent/agent_config.py` (new); `monitoring_agent/.env` (new, gitignored); `monitoring_agent/.env.example` (new, tracked); `.gitignore` (new entry); `monitoring_agent/live_monitor.py`; `monitoring_agent/virus_total.py`.

**Verification.** `git grep` for the removed credential string over tracked files returns nothing (the literal is deliberately not repeated in this document). Config lookup confirmed working when the agent is launched from a different working directory. VirusTotal was deliberately left disabled in the new `.env` so no real API quota is consumed.

---

## 7. Agent schema bootstrap rewritten to match the Laravel schema

**What was wrong.** `monitoring_agent/schema.sql` had drifted from the Laravel migrations. It defined a legacy `alerts` shape and a `monitoring_snapshots` table missing `snapshot_timestamp`, `process_count`, and `status`. Two files claiming to define the same database means whichever one runs first wins, and the other one breaks — migration `000008` exists specifically to repair the damage.

**What changed.** The file was rewritten so that:
- Its header states the ownership contract explicitly: **Laravel is the authoritative owner of the database**; this file only bootstraps the tables the agent touches, so the agent can run before `php artisan migrate` has ever run.
- Every definition mirrors the final migrated state, with a table-by-table mapping back to the migration that owns it.
- All statements are `IF NOT EXISTS`, so running it against an already-migrated database is a no-op.
- Laravel-owned tables are deliberately **not** created here — they belong to `php artisan migrate`.
- A note in the header instructs that any migration change must be mirrored here.
- No credentials are present in the file.

**Files.** `monitoring_agent/schema.sql`.

**Verification.** Read directly; the table list matches migrations `000004`, `000005`, `000008`, `000009`, `000010`.

---

## 8. Migration idempotency against a pre-bootstrapped database

**What was wrong.** The migrations assumed an empty database. Because the agent can create its tables first (item 7), running `php artisan migrate` afterwards would collide on `CREATE TABLE` and fail partway through, leaving the schema in a half-applied state.

**What changed.** Each table creation is guarded by `Schema::hasTable()`:
- `000004` — `monitoring_snapshots`, `processes`, `alerts`
- `000005` — `processes_seen`
- `000007` — `processes`, `alerts`
- `000009` — `activity_logs`, `system_audit_logs`
- `000010` — `process_lists`

**Files.** `sentinel_proc-web/database/migrations/0001_01_01_0000{04,05,07,09,10}_*.php`.

**Verification.** `grep -n hasTable` across `database/migrations/` returns exactly the nine guards listed above.

---

## 9. Agent fails loudly instead of connecting with guessed credentials

**What was wrong.** With credentials hardcoded as fallbacks, a misconfigured agent would attempt a connection with guessed values and produce a confusing driver error — or, worse, succeed against the wrong account. There was no way to distinguish "database not configured" from "database unreachable".

**What changed.**
- `live_monitor.should_use_mysql()` requires host, user, and database to be set; when they are not, it prints an explicit instruction pointing at `monitoring_agent/.env` and `.env.example`, and continues with JSON output only. The password is deliberately **not** required, because a local MySQL may legitimately use an empty password.
- `db_connect()` prints the actual connection exception instead of failing silently.
- `virus_total.get_connection()` raises an error naming the missing configuration rather than falling through to a driver-level failure.

**Files.** `monitoring_agent/live_monitor.py`; `monitoring_agent/virus_total.py`.

**Verification.** Both files read directly; the configuration matrix is covered by tests (item 12).

---

## 10. Suspicious-path matching aligned across the three agent modules

**What was wrong.** `risk_scoring.py` decided whether an executable was staged in a Temp/Downloads folder with a **substring** test. `'temp' in r'c:\tools\templates\x.exe'.lower()` is `True`. So a legitimate application running from a folder called `templates` — or `attempts`, or `contemplation` — was scored as if it had been dropped in `Temp`, inflating its risk score and potentially raising an alert. Meanwhile `live_monitor.py` and `virus_total.py` already compared whole path segments, so **the same process received different risk verdicts depending on which module evaluated it**.

**What changed.** `risk_scoring.is_suspicious_path()` now splits the path on `/` and `\` and compares each keyword against whole segments, matching the other two modules. The docstring records why, so the substring form is not reintroduced. Command-line arguments are matched the same way.

**Files.** `monitoring_agent/risk_scoring.py`; behaviour aligned with `monitoring_agent/live_monitor.py` and `monitoring_agent/virus_total.py`.

**Verification.** `monitoring_agent/test_suspicious_path.py` asserts all three implementations agree — staging paths (`\Temp\`, `\Tmp\`, `\Downloads\`) are flagged everywhere, and benign paths (`\Program Files\`, `\templates\`, `\attempts\`) are flagged nowhere. The benign case failed against the old `risk_scoring` implementation and passes now.

---

## 11. CPU sampling correctness — a dead risk signal

**What was wrong.** This is a detection-correctness defect with direct security consequence: every CPU-based rule in the risk engine was inert.
- psutil's **first** `cpu_percent()` reading for a process is always `0.0` — there is no earlier sample to diff against. A single `process_iter()` pass therefore reported every process on the machine as idle, including a cryptominer pinning all cores.
- Separately, Windows reports the combined idle time of all cores as "System Idle Process" (pid 0). On a 4-core machine it reads around 400% and would top any ranking it appeared in.

**What changed.** New `cpu_sampling.py`:
- `sample_processes()` primes a counter on every process handle, waits `SAMPLE_INTERVAL_SECS` (0.5s), then re-reads into `proc.info['cpu_percent']` — so all existing callers keep using `proc.info['cpu_percent']` unchanged.
- `IDLE_PIDS = frozenset({0})` excludes the Windows System Idle Process.
- `read_cpu_percent()` returns `0.0` instead of raising for a process that exits mid-sample, so one dying process cannot abort a scan.

All three scanning entry points use the shared sampler — `live_monitor.py:378`, `risk_scoring.py:128`, `virus_total.py:265`.

**Files.** `monitoring_agent/cpu_sampling.py` (new); `monitoring_agent/live_monitor.py`; `monitoring_agent/virus_total.py`.

**Verification.** `monitoring_agent/test_cpu_sampling.py`, plus a direct measurement of the sampler on the demonstration machine: **279 processes sampled, 18 reporting non-zero CPU, highest reading 55.4%**. Before the fix every one of them read `0.0`. (Measured by calling `cpu_sampling.sample_processes()` alone — no database access and no VirusTotal lookups, so no real API quota was spent.)

---

## 12. Regression tests locking the above

**What was added.**
- `monitoring_agent/test_agent_config.py` — env-file parsing (quoted values, empty values, comments, blanks), environment-wins precedence, missing file does not raise, credentials have no guessed default.
- `monitoring_agent/test_cpu_sampling.py` — priming behaviour and idle-PID exclusion.
- `monitoring_agent/test_suspicious_path.py` — cross-module agreement between all three path checks.
- `monitoring_agent/test_live_monitor.py` — **rewritten**. The two existing tests set `SENTINEL_DB_*` environment variables *after* importing the module, which never affected the import-time `DB_CONFIG`. They were passing because the hardcoded defaults were truthy — they asserted nothing about configuration at all. They now patch `DB_CONFIG` directly and cover the full matrix: fully configured is usable; missing host, user, or database each disables the connection; an empty password is still usable.
- Laravel feature tests: `AuthAndReportsTest` (login places the user in their role; the reports page lists its reports; a Viewer is refused `/reports/{type}` and its export with 403; an Analyst can export and receives CSV) and `SentinelDashboardTest` (a guest is redirected to login; the dashboard renders snapshot data when a snapshot file is present; an Admin can use Refresh Snapshot and a Viewer is refused with 403; the analytics aggregates render with seeded rows).

**Result (re-run 2026-09-23, after all changes).** Python: **18 passed in 0.66s**. Laravel: **11 passed, 35 assertions, in 4.26s**. Both suites green. Re-run again after items 13–15: Python **18 passed, 6 subtests**; Laravel **11 passed, 35 assertions**. Still green.

**Files.** `monitoring_agent/test_*.py`; `sentinel_proc-web/tests/`.

---

## 13. The API authorization above was unenforceable — the Sanctum table did not exist

**What was wrong.** Items 1 and 2 added token abilities and role gates to `routes/api.php`, but the
`personal_access_tokens` table had never been created: Sanctum's migration was absent from
`database/migrations/`. `auth:sanctum` could therefore never authenticate anything. Every API
request was rejected as unauthenticated, so the agent could not deliver a single snapshot, and none
of the authorization logic in item 1 could be reached at runtime. The route file *looked* correct
and would pass any code review — this was only findable by running it.

**What changed.** Added Sanctum's canonical migration at
`database/migrations/0001_01_01_000003_create_personal_access_tokens_table.php` (the empty slot in
the existing `0001_01_01_00000{0..2}` sequence) and applied it. Additive only — one new empty table;
no existing table was touched. `config/sanctum.php` remains unpublished on purpose: the package
merges its own defaults, so publishing is not required for tokens to work.

**Files.** `sentinel_proc-web/database/migrations/0001_01_01_000003_create_personal_access_tokens_table.php` (new).

**Verification.** `php artisan app:generate-monitoring-token` prints a usable token; the full
request matrix in item 1 then behaves as specified. The table was left empty afterwards (the
verification token and its agent user were removed), so run the command above when the token is
actually needed.

---

## 14. `alerts.updated_at` was missing — acknowledge and alert ingest returned 500

**What was wrong.** Migration `000008` rebuilt the `alerts` table with a `created_at` column but no
`updated_at`, while the `Alert` model keeps Eloquent's default timestamps. Every write through the
model emitted `updated_at` and died on `Unknown column 'alerts.updated_at'`. Two live paths were
broken, both demo-visible:

- `POST /api/monitoring/snapshot` — any snapshot containing an alert returned **500** after having
  already written the snapshot and its process rows (see item 15).
- `POST /alerts/{id}/acknowledge` — the **Acknowledge** button on the alert detail page returned
  **500**. The alert could never be marked as handled.

**What changed.** New migration `2026_09_23_000000_add_updated_at_to_alerts_table.php` adds
`updated_at TIMESTAMP NULL` to `alerts`, guarded by `hasTable`/`hasColumn` like the other
migrations. Nullable, so the 96 existing rows and the agent's raw inserts (which never set the
column) remain valid. `monitoring_agent/schema.sql` was updated to mirror it, per the ownership
contract in item 7.

**Files.** `sentinel_proc-web/database/migrations/2026_09_23_000000_add_updated_at_to_alerts_table.php` (new); `monitoring_agent/schema.sql`.

**Verification.** Acknowledge re-tested against the running application: previously **500**, now
**200** `{"ok":true,"message":"Alert acknowledged"}` with `acknowledged=1` and a populated
`updated_at` in the database. API ingest re-tested after both repairs: **201** with the alert row
written.

---

## 15. Snapshot ingest was not atomic — a mid-way failure left partial rows

**What was wrong.** `storeSnapshot()` created the snapshot, then its process rows, then its alert
rows, each as an independent insert. When any child insert failed, the snapshot row was already
committed: the database kept a snapshot with missing children and the client received a 500. This
was not hypothetical — it is exactly what item 14 produced during verification. It is also
reachable independently: the nested validation allows `path` up to 1000 characters while the
`processes.path` column is `varchar(512)`, so a 600-character path passes validation and fails at
the database.

**What changed.** The snapshot and all of its child rows are now written inside a single
`DB::transaction()` closure, so a failure anywhere in the sequence rolls the whole ingest back.

**Files.** `sentinel_proc-web/app/Http/Controllers/Api/MonitoringApiController.php`.

**Verification.** A payload with a deliberately over-long 600-character path was posted: the
response was **500** (validation still lets it through — see the known gaps) and the row counts
before and after were **identical** (3 snapshots / 551 processes both times). No orphan rows.
Confirmed separately that this cannot disturb the demo dashboard: `LiveSnapshotService::isValidSnapshot()`
requires non-empty `stats`, `processes` *and* `alerts`, and the transaction rollback was verified
against the live database.

---

## Verified in place — examined and left unchanged
These controls already existed and were confirmed in the source. They are listed so that a reviewer knows they were checked, not assumed:

- `EnsureUserHasRole` middleware aborts with 403 on a role mismatch and redirects unauthenticated users to login. `routes/web.php` divides capabilities into `role:admin,analyst` (reports, refresh, processes, activity, risk detections, VirusTotal, alerts including acknowledge) and `role:admin` (alert deletion, user management, system audit log, settings, process rules, whitelist/blacklist).
- `AuthController::login` regenerates the session ID on success; `logout` invalidates the session and regenerates the CSRF token; both write a `SystemAuditLog` record.
- `User` hides `password` and `remember_token` from serialisation and casts `password` as `hashed`.
- `UserController` blocks self-demotion and self-deletion, validates role with `Rule::enum(UserRole::class)`, and enforces `Password::min(8)->letters()->numbers()` with confirmation.
- `MonitoringApiController` logs the real exception server-side and returns a generic message unless `app.debug` is set.

---

## Known gaps deliberately left open

High and Critical findings were the agreed scope. The following were identified and intentionally not changed; they are recorded here so the record is honest rather than selective:

| Item | Nature | Why it was left |
|---|---|---|
| `role` present in `User::$fillable` | A future mass-assignment call site could set a role | All current writes go through validated controller paths; the model-level guard is missing but no reachable path exploits it. Medium severity. |
| `processes.path` validated to 1000 chars, column is `varchar(512)` | A long path passes validation and fails at the database | Now rolls back cleanly (item 15) instead of corrupting, but it returns 500 where 422 is correct. Truncating the rule to 512 is a one-line fix left for the next pass. |
| No automated test covers the API routes | Items 13 and 14 were both invisible to the suite | The two defects were found by running the app, not by the tests. The suite covers the web UI and the agent, not the token flow. |
| Agent issues its DB calls inside the scan loop | Throughput, not a vulnerability | Performance, not security. |
| `load_process_lists` swallows exceptions silently | A failed whitelist/blacklist load is invisible | Availability/diagnosability, not a security boundary. |
| No Subresource Integrity on the Tailwind and Chart.js CDN tags | A compromised CDN could serve modified script | Requires pinned versions; the CDNs are already allow-listed in the CSP. |
| Settings page displays DB host/name/user and mail host | Infrastructure disclosure to **Admin** users only | Admin is a trusted role; the page exists to serve that purpose. |
| `.env` retains the demo DB password and `APP_DEBUG=true` | Debug error pages are verbose | Retained deliberately so the live defence demonstration keeps working. |

---

## Summary of files touched

| Area | Files |
|---|---|
| Routing / authorization | `sentinel_proc-web/routes/api.php`, `routes/web.php`, `bootstrap/app.php` |
| Middleware | `app/Http/Middleware/SecurityHeaders.php` |
| Requests / validation | `app/Http/Requests/StoreMonitoringSnapshotRequest.php` |
| Controllers | `app/Http/Controllers/Api/MonitoringApiController.php`, `app/Http/Controllers/SettingsController.php` |
| Providers | `app/Providers/AppServiceProvider.php` |
| Console | `app/Console/Commands/GenerateMonitoringToken.php` |
| Migrations | `database/migrations/0001_01_01_0000{04,05,07,09,10}_*.php`, `0001_01_01_000003_create_personal_access_tokens_table.php` (new), `2026_09_23_000000_add_updated_at_to_alerts_table.php` (new) |
| Agent | `monitoring_agent/agent_config.py` (new), `cpu_sampling.py` (new), `live_monitor.py`, `virus_total.py`, `risk_scoring.py`, `schema.sql` |
| Agent config | `monitoring_agent/.env` (new, gitignored), `monitoring_agent/.env.example` (new), `.gitignore` |
| Tests | `monitoring_agent/test_agent_config.py`, `test_cpu_sampling.py`, `test_suspicious_path.py`, `test_live_monitor.py` |
