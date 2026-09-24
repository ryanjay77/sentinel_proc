# SentinelProc — Live Demo Script

**Project:** A Real-Time Process Monitoring and Access Control System with Risk-Based Threat Detection
**Stack:** Python agent (psutil + VirusTotal) → MySQL → Laravel 12 + Blade/Tailwind UI
**URL:** http://127.0.0.1:8000
**Demo accounts:** `admin@sentinel.local` / `analyst@sentinel.local` / `viewer@sentinel.local` — password `password` (all three)

Every behaviour below was executed and confirmed against the running application. Anything not verified is marked **[not verified]**.

---

## 1. Pre-flight (do this 10 minutes before you present)

Run these in order. Do not skip the first one — MySQL must be up before the app.

```bash
# 1. Start XAMPP: Apache + MySQL (MariaDB has no Windows service here — start it from the XAMPP panel)

# 2. Apply any pending database migrations
cd C:\xampp\htdocs\sentinel_proc\sentinel_proc-web
php artisan migrate

# 3. Start the application
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000 and log in as `admin@sentinel.local` / `password`. If the dashboard shows
data, you are ready.

**Checklist before you start talking**

- [ ] Dashboard shows 4 KPI tiles, a risk chart, and a process table (not "no data" placeholders)
- [ ] `/alerts` lists alerts and one of them opens into a detail page
- [ ] `/reports` lists 3 reports and each one renders
- [ ] Keep a second browser window (or a private/incognito window) ready for the role-switching scene

**If you want a fresh scan before the demo** (optional, see §6 for the cost):
press the **Refresh Snapshot** button while logged in as admin or analyst.

---

## 2. What each layer does (30-second explanation)

| Layer | Role |
|---|---|
| Python agent (`monitoring_agent/`) | Enumerates running processes (psutil), hashes each executable, checks first-seen files against VirusTotal, classifies risk, writes a snapshot to MySQL / `live_snapshot.json` |
| MySQL | Stores snapshots, processes, alerts, first-seen hash registry, activity + audit logs |
| Laravel | Authentication, role-based access control, risk analytics, alert triage, reporting, CSV export |

---

## 3. Demo scenes (in order — each one is about 1–2 minutes)

### Scene 1 — Dashboard overview (admin)

Log in as `admin@sentinel.local`.

Show: total running processes, high/medium risk counts, the risk distribution chart, and the live
process table with per-process risk levels.

**Say:** the dashboard is driven by the newest valid snapshot — the agent's output flows straight
through to this view with no manual import step.

---

### Scene 2 — Access control (the core of the project)

This is the scene your professor most wants to see. Use three logins.

| | viewer | analyst | admin |
|---|---|---|---|
| Dashboard (`/`, `/dashboard`) | yes | yes | yes |
| Reports, Processes, Alerts, Risk detections, VirusTotal, Activity | **403** | yes | yes |
| Users, Settings, System log, Process rules/lists | **403** | **403** | yes |
| "Refresh Snapshot" button visible | no | yes | yes |

1. Log in as `viewer@sentinel.local`. The dashboard loads, but the **Refresh Snapshot** button is
   gone from the toolbar. Type `http://127.0.0.1:8000/users` in the address bar → **403 Forbidden**.
   **Say:** the restriction is enforced on the server, not just hidden in the UI.
2. Log out, log in as `analyst@sentinel.local`. Reports, Processes and Alerts now open; `/users`
   still returns **403**. **Say:** analysts get the operational pages but not user administration.
3. Log out, log in as `admin@sentinel.local`. Everything opens, including Users and Settings.

**Say:** the role is stored on the user record and checked by middleware on every route group, so
there is no way to reach a page by typing the URL.

---

### Scene 3 — Alert triage (analyst or admin)

Go to **Alerts** (`/alerts`).

1. Filter by severity. Point out that alerts carry a severity, a type, and the process they came from.
2. Open one alert → click **Acknowledge**. The button turns into "Acknowledged" and the alert is
   marked as handled.
3. Go back to the list and confirm the alert now shows as acknowledged.

**Say:** acknowledgement is a write operation with its own permission check — the requesting user
must have the analyst or admin role.

---

### Scene 4 — Risk detection and first-seen tracking

Go to **Risk Detections** (`/risk-detections`) and **VirusTotal** (`/virus-total`).

Show: the risk timeline (detected / first-seen / risk-change events) and the registry of tracked
executable hashes, including which ones have been checked against VirusTotal and whether any came
back flagged.

**Say:** a file is only sent to VirusTotal the **first time** its hash is seen — repeat scans are
cheap, and there is a 16-second gap between lookups to respect the API rate limit.

---

### Scene 5 — Reports and CSV export

Go to **Reports** (`/reports`). Open each of the three: *Daily Process Audit*, *Risk Summary*,
*Threat Intelligence*. Then press **Export** on one.

**Say:** export streams the same data as a CSV and writes an entry to the system audit log —
report generation is itself an auditable action.

---

### Scene 6 — The API ingest path (optional, impressive, takes 2 minutes)

Only do this if you want to show that the agent talks to the app through an authenticated API
rather than writing to the database directly.

```bash
cd C:\xampp\htdocs\sentinel_proc\sentinel_proc-web

# 1. Mint a token for the monitoring agent
php artisan app:generate-monitoring-token
#    → prints:  1|xxxxxxxx...   (store it; it is shown only once)
```

Then, in a second terminal (replace `TOKEN` with the value printed above):

```bash
# 2. Unauthenticated request → rejected
curl -i -X POST http://127.0.0.1:8000/api/monitoring/snapshot \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"snapshot\":\"{\\\"processes\\\":[]}\"}"
#    → HTTP 401 Unauthenticated

# 3. Authenticated request → accepted
curl -i -X POST http://127.0.0.1:8000/api/monitoring/snapshot \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d "{\"snapshot\":\"{\\\"processes\\\":[{\\\"pid\\\":123,\\\"name\\\":\\\"demo.exe\\\",\\\"risk_level\\\":\\\"low\\\"}]}\"}"
#    → HTTP 201 {"ok":true,"message":"Snapshot stored successfully","snapshot_id":N}
```

**Say:** the token is scoped to a single ability (`monitoring:write`). A token without that ability
is rejected with **403 Invalid ability provided**, and the agent's token cannot read the
history endpoints at all — those require an analyst or admin **user session**.

**Cleanup:** if you create a demo snapshot during the presentation, it stays in the database.
It will not disturb the dashboard (the dashboard only accepts snapshots that contain stats,
processes *and* alerts). Delete it afterwards if you want the database pristine:

```bash
php artisan tinker --execute="DB::table('processes')->where('monitoring_snapshot_id', N)->delete(); DB::table('alerts')->where('monitoring_snapshot_id', N)->delete(); DB::table('monitoring_snapshots')->where('id', N)->delete();"
```

---

### Scene 7 — Security controls (only if your professor asks)

| Control | How to show it |
|---|---|
| Login brute-force throttling | Try a wrong password 6 times → the 6th returns **429 Too Many Requests**. Limit is 5 per email+IP per minute. |
| CSRF protection | Every POST/PUT/DELETE form carries a token; the acknowledge action sends it in a header |
| Password hashing | `php artisan tinker --execute="echo App\Models\User::find(1)->password;"` → bcrypt hash, never plaintext |
| SQL injection safety | Eloquent/query-builder bindings throughout; no string-concatenated SQL in the app |
| XSS safety | Blade escapes output by default (`{{ }}`), raw `{!! !!}` is not used for user data |
| Security headers | `curl -I http://127.0.0.1:8000/login` → `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy` |
| Audit trail | `/activity/system` (admin) lists logins, report exports, refreshes, acknowledgements |

---

## 4. Traps — read this before you click anything

1. **Do not press "Refresh Snapshot" unless you accept the cost.** It launches the real Python
   agent, which hashes every running process and sends brand-new executables to VirusTotal. It is
   rate-limited to one lookup per 16 seconds, and it consumes real VirusTotal API quota.
   Repeat scans are usually fast because known hashes are skipped.
2. **Login is throttled to 5 attempts per minute.** If you fumble the password 5 times live, the
   next attempt returns 429 — wait 60 seconds. Do not demo the throttle and then need to log in.
3. **`APP_DEBUG=true` is on.** Any error shows a full stack trace. Do not demonstrate error
   handling live; if a page errors, navigate away and continue.
4. **The dashboard is showing the stored snapshot, not a live stream.** The newest valid snapshot
   was collected earlier; that is what "Real-Time" means here — the agent refreshes on demand.
5. **Never demo the Settings page.** It exposes infrastructure configuration (DB host/port/name,
   mail settings, paths) by design and is admin-only — it is honest, but it is not the story.
6. **Do not clear `processes_seen`.** It is the first-seen registry: clearing it makes the next
   scan treat every running process as new and send ~175 hashes to VirusTotal at 16 s each.

---

## 5. Seeds and expected numbers (as of the last verification)

Use these to sanity-check that the demo database is intact.

| Table | Rows | Notes |
|---|---|---|
| `users` | 3 | admin / analyst / viewer |
| `monitoring_snapshots` | 2 | dashboard reads the newest valid one |
| `processes` | 550 | process rows across snapshots |
| `alerts` | 96 | 2 high, 94 medium |
| `activity_logs` | 550 | 442 detected, 105 first-seen, 3 risk-change |
| `processes_seen` | 175 | 68 checked against VirusTotal, 1 flagged |
| `system_audit_logs` | 56 | logins, exports, acknowledgements |
| `process_lists` / `process_rules` | 15 / 1 | allow/block lists |

If a count is far off, the data was modified by a scan — not a bug.

---

## 6. Questions your professor is likely to ask

**"Where does the risk score come from?"**
Path heuristics (writable/system directories, temp folders), process reputation from VirusTotal
(first-seen files only), and rule matching against the allow/block lists. Each process gets a risk
level of low / medium / high / critical and the alert severity mirrors it.

**"What stops a normal user from deleting an alert?"**
The delete route is inside the admin-only group; the acknowledge route is analyst+admin. Both are
enforced by middleware, and both write to the audit log.

**"Is the agent's API authenticated?"**
Yes — Sanctum personal access tokens. The token is bound to one ability (`monitoring:write`) and
can only POST snapshots. Reading history requires a role-checked user session.

**"How do you know a snapshot can't corrupt the database?"**
The nested payload is validated before it reaches the database (hash format, enums, length limits),
and the snapshot plus its processes and alerts are written inside a single database transaction —
if any child row fails, the whole ingest rolls back.

**"What would you improve next?"** (answer honestly — these are known and deliberate)
- Validate process paths against the column length (currently a 600-character path passes validation
  but is rejected by the database — the transaction now rolls it back cleanly, but a 422 would be
  better than a 500).
- Add SRI hashes to the CDN-loaded Tailwind/Chart.js scripts.
- Remove `role` from `User::$fillable` so a role can never be mass-assigned.
- Move the agent's per-process database writes out of the scan loop (batching).
- Replace silent `except Exception: pass` blocks in the agent's list loader with logging.

---

## 7. Recovery — if something breaks mid-demo

| Symptom | Fix |
|---|---|
| Dashboard says no data | MySQL is down. Start it in XAMPP and reload. |
| 429 on login | You hit the rate limit. Wait 60 seconds, or use a different account. |
| Page renders unstyled | The CDN for Tailwind did not load (no internet). Switch to a working network or present the pages you already loaded. |
| 500 on any page | You found a genuine bug — note the URL, navigate away, and check `sentinel_proc-web/storage/logs/laravel.log` afterwards. |
| Wrong-looking process list | Fine — the newest snapshot is being shown. Explain that the agent refreshes on demand. |
