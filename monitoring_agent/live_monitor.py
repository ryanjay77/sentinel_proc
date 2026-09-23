
import json
import os
import hashlib
import platform
import time
import psutil
import requests
from datetime import datetime, timezone

# ── Optional MySQL driver ─────────────────────────────────────────────
try:
    import mysql.connector
    _MYSQL_AVAILABLE = True
except ImportError:
    _MYSQL_AVAILABLE = False

# ── Paths & config ────────────────────────────────────────────────────
OUTPUT_PATH = os.path.join(os.path.dirname(__file__), 'live_snapshot.json')

DB_CONFIG = {
    'host':     os.getenv('SENTINEL_DB_HOST',     '127.0.0.1'),
    'port':     int(os.getenv('SENTINEL_DB_PORT', '3306')),
    'user':     os.getenv('SENTINEL_DB_USER',     'sentinel_user'),
    'password': os.getenv('SENTINEL_DB_PASSWORD', 'sentinel_pass'),
    'database': os.getenv('SENTINEL_DB_NAME',     'sentinel_proc'),
}

# VirusTotal — read from env only; never hardcode keys in source
VT_API_KEY           = os.getenv('VT_API_KEY', '')
VT_URL               = 'https://www.virustotal.com/api/v3/files/{}'
VT_RATE_LIMIT_SECS   = 16   # free tier: 4 req/min

SUSPICIOUS_KEYWORDS = {'temp', 'tmp', 'downloads', 'appdata/local/temp'}

# ── Helpers ───────────────────────────────────────────────────────────

def _now_utc() -> str:
    return datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')


def hash_file(path: str):
    """SHA-256 of an executable file. Returns None on any read error."""
    try:
        h = hashlib.sha256()
        with open(path, 'rb') as f:
            for chunk in iter(lambda: f.read(8192), b''):
                h.update(chunk)
        return h.hexdigest()
    except (FileNotFoundError, PermissionError, OSError):
        return None


def is_suspicious_path(path: str) -> bool:
    if not path:
        return False
    segments = set(path.lower().replace('\\', '/').split('/'))
    return bool(segments & SUSPICIOUS_KEYWORDS)


def is_unsigned(path: str):
    """Returns True/False/None. None means 'not checked' (non-Windows)."""
    if platform.system() != 'Windows' or not path:
        return None
    try:
        import win32api
        win32api.GetFileVersionInfo(path, '\\')
        return False
    except ImportError:
        return None
    except Exception:
        return True


def check_virustotal(file_hash: str):
    """Query VT for a hash. Returns {'malicious': int, 'total': int} or None."""
    if not file_hash or not VT_API_KEY:
        return None
    try:
        r = requests.get(
            VT_URL.format(file_hash),
            headers={'x-apikey': VT_API_KEY},
            timeout=10,
        )
        if r.status_code in (404, 429):
            return None
        r.raise_for_status()
        stats = r.json()['data']['attributes']['last_analysis_stats']
        return {
            'malicious': stats.get('malicious', 0) + stats.get('suspicious', 0),
            'total':     sum(stats.values()),
        }
    except Exception:
        return None


def load_process_lists(conn) -> dict:
    """
    Load whitelist and blacklist from the DB into memory once per scan.
    Returns {'whitelist': [...], 'blacklist': [...]}
    Each entry: {'match_by': 'name'|'hash'|'path', 'value': str}
    """
    result = {'whitelist': [], 'blacklist': []}
    if not conn:
        return result
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT type, match_by, value FROM process_lists")
        for row in cur.fetchall():
            result[row['type']].append({'match_by': row['match_by'], 'value': row['value']})
        cur.close()
    except Exception:
        pass
    return result


def check_list(rules: list, name: str, file_hash: str | None, path: str | None) -> bool:
    """Return True if this process matches any rule in the provided list."""
    name_lower = name.lower()
    path_lower = (path or '').lower()
    for rule in rules:
        mb, val = rule['match_by'], rule['value']
        if mb == 'name' and val == name_lower:
            return True
        if mb == 'hash' and file_hash and val == file_hash:
            return True
        if mb == 'path' and val == path_lower:
            return True
    return False


def score_process(path, cpu, first_seen, vt=None, unsigned=None):
    """
    Return (score, risk_level_str, reasons_list).
    risk_level uses lowercase 'high'/'medium'/'low' to match DB column.
    """
    score, reasons = 0, []
    if first_seen:
        score += 25; reasons.append('first-seen')
    if cpu > 50:
        score += 20; reasons.append('high cpu')
    if is_suspicious_path(path):
        score += 25; reasons.append('suspicious path')
    if unsigned is True:
        score += 30; reasons.append('unsigned executable')
    if vt:
        if vt['malicious'] >= 5:
            score += 50; reasons.append('virustotal malicious')
        elif vt['malicious'] >= 1:
            score += 30; reasons.append('virustotal suspicious')

    if score >= 50:
        level = 'high'
    elif score >= 25:
        level = 'medium'
    else:
        level = 'low'

    return score, level, reasons


# ── Database layer ────────────────────────────────────────────────────

def should_use_mysql() -> bool:
    """
    True when DB_CONFIG carries enough settings to attempt a connection.

    Reads the module-level DB_CONFIG at call time (not a copy taken at
    import) so callers and tests can patch live_monitor.DB_CONFIG.
    Password is deliberately not required, since a local MySQL account
    may legitimately have an empty password.
    """
    return bool(
        DB_CONFIG.get('host')
        and DB_CONFIG.get('user')
        and DB_CONFIG.get('database')
    )


def db_connect():
    if not _MYSQL_AVAILABLE:
        return None
    if not should_use_mysql():
        return None
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Exception as exc:
        print(f'[DB] Connection failed: {exc}')
        return None


def is_hash_known(conn, file_hash: str) -> bool:
    cur = conn.cursor()
    cur.execute('SELECT id FROM processes_seen WHERE file_hash = %s', (file_hash,))
    result = cur.fetchone()
    cur.close()
    return result is not None


def record_first_seen(conn, file_hash: str, name: str, path: str):
    cur = conn.cursor()
    cur.execute(
        '''INSERT INTO processes_seen (file_hash, process_name, file_path)
           VALUES (%s, %s, %s)
           ON DUPLICATE KEY UPDATE process_name = process_name''',
        (file_hash, name, path),
    )
    conn.commit()
    cur.close()


def update_vt_cache(conn, file_hash: str, vt: dict):
    cur = conn.cursor()
    cur.execute(
        '''UPDATE processes_seen
           SET vt_checked_at = NOW(), vt_malicious_count = %s, vt_total_engines = %s
           WHERE file_hash = %s''',
        (vt['malicious'], vt['total'], file_hash),
    )
    conn.commit()
    cur.close()


def insert_snapshot(conn, snapshot_json: str, process_count: int) -> int:
    """
    Insert a row into monitoring_snapshots.
    Returns the new row's id (int).
    """
    cur = conn.cursor()
    cur.execute(
        '''INSERT INTO monitoring_snapshots
               (snapshot, snapshot_timestamp, process_count, status)
           VALUES (%s, NOW(), %s, 'normal')''',
        (snapshot_json, process_count),
    )
    conn.commit()
    snapshot_id = cur.lastrowid
    cur.close()
    return snapshot_id


def insert_process(conn, snapshot_id: int, p: dict) -> int:
    """
    Insert one process row. Returns the new process id.

    Column mapping (schema → dict key):
      monitoring_snapshot_id  ← snapshot_id (int)
      pid                     ← p['pid']
      name                    ← p['name']
      path                    ← p['path']
      cpu_percent             ← p['cpu_percent']
      memory_mb               ← p['memory_mb']
      status                  ← p['user'] (username stored in status col)
      hash                    ← p['hash']
      first_seen              ← 1 if first-seen else 0
      risk_level              ← p['risk_level']  ('high'/'medium'/'low')
      virus_total_data        ← JSON string or NULL
    """
    vt_json = json.dumps(p['vt']) if p.get('vt') else None
    cur = conn.cursor()
    cur.execute(
        '''INSERT INTO processes
               (monitoring_snapshot_id, pid, name, path,
                cpu_percent, memory_mb, status, hash,
                first_seen, risk_level, virus_total_data)
           VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)''',
        (
            snapshot_id,
            p['pid'],
            p['name'],
            p.get('path') or None,
            p.get('cpu_percent'),
            p.get('memory_mb'),
            p.get('user', 'unknown'),
            p.get('hash') or None,
            1 if p.get('first_seen') else 0,
            p['risk_level'],
            vt_json,
        ),
    )
    conn.commit()
    process_id = cur.lastrowid
    cur.close()
    return process_id


def insert_activity_log(conn, snapshot_id: int, p: dict, reasons: list):
    """
    Write a row to activity_logs for every process the agent detects.
    event_type:
      first_seen  — hash never seen before
      vt_flagged  — VT returned detections
      risk_change — medium/high risk score
      detected    — normal process observed
    """
    if p.get('vt') and p['vt'].get('malicious', 0) > 0:
        event_type = 'vt_flagged'
    elif p.get('first_seen'):
        event_type = 'first_seen'
    elif p['risk_level'] in ('high', 'medium'):
        event_type = 'risk_change'
    else:
        event_type = 'detected'

    details = json.dumps({
        'reasons': reasons,
        'score':   p.get('score', 0),
        'user':    p.get('user', 'unknown'),
    })

    cur = conn.cursor()
    cur.execute(
        '''INSERT INTO activity_logs
               (monitoring_snapshot_id, process_name, pid,
                event_type, risk_level, risk_score, path, hash, details)
           VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)''',
        (
            snapshot_id,
            p['name'],
            p['pid'],
            event_type,
            p['risk_level'],
            p.get('score', 0),
            p.get('path') or None,
            p.get('hash') or None,
            details,
        ),
    )
    conn.commit()
    cur.close()


def insert_alert(conn, snapshot_id: int, process_id: int, p: dict, reasons: list):
    """
    Insert one alert row for a medium/high risk process.

    Column mapping:
      monitoring_snapshot_id ← snapshot_id
      process_id             ← process_id (bigint)
      alert_type             ← 'risk_score'
      severity               ← p['risk_level']  ('high'/'medium')
      message                ← human-readable string
      details                ← JSON with score + reasons
      acknowledged           ← 0
    """
    message = f"{p['name']} triggered risk scoring rules ({', '.join(reasons)})"
    details = json.dumps({
        'score':   p['score'],
        'reasons': reasons,
        'source':  p.get('path') or p['name'],
        'pid':     p['pid'],
    })
    cur = conn.cursor()
    cur.execute(
        '''INSERT INTO alerts
               (monitoring_snapshot_id, process_id, alert_type,
                severity, message, details, acknowledged)
           VALUES (%s, %s, 'risk_score', %s, %s, %s, 0)''',
        (snapshot_id, process_id, p['risk_level'], message, details),
    )
    conn.commit()
    cur.close()


# ── Core collection ───────────────────────────────────────────────────

def collect_snapshot():
    conn = db_connect()
    vt_checks = 0
    raw_processes = []   # full data per process (for DB writes)

    # Load whitelist/blacklist once at scan start
    process_lists = load_process_lists(conn)

    for proc in psutil.process_iter(['pid', 'name', 'exe', 'cpu_percent', 'memory_info']):
        try:
            info      = proc.info
            pid       = info['pid']
            name      = info['name'] or 'Unknown'
            path      = info.get('exe') or ''
            cpu       = float(info.get('cpu_percent') or 0.0)
            mem_bytes = (info.get('memory_info').rss if info.get('memory_info') else 0)
            memory_mb = mem_bytes / (1024 * 1024)

            try:
                user = proc.username()
            except Exception:
                user = 'unknown'

            # Hash + first-seen
            file_hash  = hash_file(path) if path else None
            first_seen = False

            if file_hash and conn:
                first_seen = not is_hash_known(conn, file_hash)
                if first_seen:
                    record_first_seen(conn, file_hash, name, path)
            elif file_hash:
                first_seen = True  # no DB, treat everything as first-seen

            # VirusTotal (only for first-seen, only if key is set)
            vt = None
            if first_seen and file_hash and VT_API_KEY:
                if vt_checks > 0:
                    time.sleep(VT_RATE_LIMIT_SECS)
                vt = check_virustotal(file_hash)
                vt_checks += 1
                if vt and conn:
                    update_vt_cache(conn, file_hash, vt)

            unsigned = is_unsigned(path) if path else None
            score, risk_level, reasons = score_process(path, cpu, first_seen, vt, unsigned)

            # ── Whitelist / Blacklist override ───────────────────────
            if check_list(process_lists['whitelist'], name, file_hash, path):
                risk_level = 'low'
                score      = 0
                reasons    = ['whitelisted']
            elif check_list(process_lists['blacklist'], name, file_hash, path):
                risk_level = 'high'
                score      = 100
                reasons    = ['blacklisted']

            raw_processes.append({
                'pid':        pid,
                'name':       name,
                'path':       path,
                'user':       user,
                'cpu_percent': round(cpu, 2),
                'memory_mb':   round(memory_mb, 2),
                'hash':       file_hash,
                'first_seen': first_seen,
                'risk_level': risk_level,   # 'high'/'medium'/'low'
                'score':      score,
                'reasons':    reasons,
                'vt':         vt,
            })
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue

    # ── Build summary stats ───────────────────────────────────────────
    total      = len(raw_processes)
    high_count = sum(1 for p in raw_processes if p['risk_level'] == 'high')
    med_count  = sum(1 for p in raw_processes if p['risk_level'] == 'medium')
    norm_count = sum(1 for p in raw_processes if p['risk_level'] == 'low')

    # ── Build the JSON snapshot (for live_snapshot.json + monitoring_snapshots.snapshot) ──
    # Sort: high first, then medium, then by CPU desc; cap at 8 for dashboard
    sorted_procs = sorted(
        raw_processes,
        key=lambda p: ({'high': 0, 'medium': 1, 'low': 2}[p['risk_level']], -p['cpu_percent']),
    )

    # Dashboard-friendly process list (top 8)
    dashboard_processes = [
        {
            'pid':    p['pid'],
            'name':   p['name'],
            'user':   p['user'],
            'cpu':    f"{p['cpu_percent']:.1f}%",
            'memory': f"{p['memory_mb']:.0f} MB",
            'status': {'high': 'High Risk', 'medium': 'Medium Risk', 'low': 'Normal'}[p['risk_level']],
        }
        for p in sorted_procs[:8]
    ]

    # Dashboard alerts (medium + high, cap at 4)
    alert_procs = [p for p in sorted_procs if p['risk_level'] in ('high', 'medium')]
    dashboard_alerts = [
        {
            'severity': p['risk_level'].upper(),
            'title':    f"{p['name']} triggered risk scoring rules",
            'time':     'just now',
            'source':   p['path'] or p['name'],
        }
        for p in alert_procs[:4]
    ] or [{
        'severity': 'LOW',
        'title':    'No active anomalies detected',
        'time':     'just now',
        'source':   'System baseline',
    }]

    stats = [
        {'label': 'Running Processes', 'value': str(total),      'trend': '',     'tone': 'primary'},
        {'label': 'High Risk',          'value': str(high_count), 'trend': '',     'tone': 'danger'},
        {'label': 'Medium Risk',        'value': str(med_count),  'trend': '',     'tone': 'warning'},
        {'label': 'Whitelisted',        'value': str(norm_count), 'trend': '',     'tone': 'success'},
    ]

    generated_at = datetime.now(timezone.utc).isoformat()

    snapshot = {
        'stats':        stats,
        'processes':    dashboard_processes,
        'alerts':       dashboard_alerts,
        'generated_at': generated_at,
    }

    # ── Write live_snapshot.json (fallback) ───────────────────────────
    with open(OUTPUT_PATH, 'w', encoding='utf-8') as f:
        json.dump(snapshot, f, indent=2)

    # ── Write to MySQL ────────────────────────────────────────────────
    if conn:
        try:
            snapshot_id = insert_snapshot(conn, json.dumps(snapshot), total)

            for p in raw_processes:
                process_db_id = insert_process(conn, snapshot_id, p)

                if p['risk_level'] in ('high', 'medium'):
                    insert_alert(conn, snapshot_id, process_db_id, p, p['reasons'])

                insert_activity_log(conn, snapshot_id, p, p['reasons'])

        except Exception as exc:
            print(f'[DB] Write failed: {exc}')
        finally:
            conn.close()

    return snapshot


# ── Entry point ───────────────────────────────────────────────────────

if __name__ == '__main__':
    print('SentinelProc — collecting snapshot...')
    snapshot = collect_snapshot()

    high  = next((s['value'] for s in snapshot['stats'] if s['label'] == 'High Risk'),  '0')
    med   = next((s['value'] for s in snapshot['stats'] if s['label'] == 'Medium Risk'), '0')
    total = next((s['value'] for s in snapshot['stats'] if s['label'] == 'Running Processes'), '0')

    print(json.dumps({
        'snapshot_written': OUTPUT_PATH,
        'total_processes':  total,
        'high_risk':        high,
        'medium_risk':      med,
        'alerts_generated': len([a for a in snapshot['alerts'] if a['severity'] != 'LOW']),
        'mysql':            _MYSQL_AVAILABLE,
        'vt_enabled':       bool(VT_API_KEY),
    }, indent=2))
