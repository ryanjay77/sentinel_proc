

import json
import psutil
import hashlib
import platform
import time
import requests
import mysql.connector
from mysql.connector import Error

import agent_config
from cpu_sampling import sample_processes

# ---------------------------------------------------------------------
# Configuration - credentials come from the environment or the gitignored
# monitoring_agent/.env (see .env.example), shared with live_monitor.py so
# both agents write to the same database.
# ---------------------------------------------------------------------
DB_CONFIG = agent_config.db_config()

VT_API_KEY = agent_config.vt_api_key()
VT_URL = "https://www.virustotal.com/api/v3/files/{hash}"

VT_SECONDS_BETWEEN_REQUESTS = 16

POINTS_FIRST_SEEN = 25
POINTS_HIGH_CPU = 20
POINTS_SUSPICIOUS_PATH = 25
POINTS_UNSIGNED = 30
POINTS_VT_CONFIRMED_MALICIOUS = 50 
POINTS_VT_SUSPICIOUS = 30            
CPU_THRESHOLD = 50.0
SUSPICIOUS_PATH_KEYWORDS = ["temp", "tmp", "downloads"]


def get_connection():
    if not DB_CONFIG.get('user'):
        raise Error(
            'No database configured — set SENTINEL_DB_USER and SENTINEL_DB_PASSWORD, '
            'or create monitoring_agent/.env (see .env.example).'
        )
    return mysql.connector.connect(**DB_CONFIG)

def check_virustotal(file_hash):

    headers = {"x-apikey": VT_API_KEY}
    url = VT_URL.format(hash=file_hash)

    try:
        response = requests.get(url, headers=headers, timeout=10)

        if response.status_code == 404:
            return None

        if response.status_code == 429:
            print("  VirusTotal rate limit hit - skipping this check.")
            return None

        response.raise_for_status()
        data = response.json()

        stats = data["data"]["attributes"]["last_analysis_stats"]
        malicious = stats.get("malicious", 0)
        suspicious = stats.get("suspicious", 0)
        total = sum(stats.values())

        return {"malicious": malicious + suspicious, "total": total}

    except requests.exceptions.RequestException as e:
        print(f"  VirusTotal request failed: {e}")
        return None
    except (KeyError, ValueError) as e:
        print(f"  VirusTotal response format unexpected: {e}")
        return None


def vt_points_and_reason(vt_result):
    if vt_result is None:
        return 0, None

    malicious = vt_result["malicious"]
    total = vt_result["total"]

    if malicious >= 5:
        return POINTS_VT_CONFIRMED_MALICIOUS, \
            f"VirusTotal: {malicious}/{total} engines flag as malicious (+{POINTS_VT_CONFIRMED_MALICIOUS})"
    elif malicious >= 1:
        return POINTS_VT_SUSPICIOUS, \
            f"VirusTotal: {malicious}/{total} engines flag as suspicious (+{POINTS_VT_SUSPICIOUS})"
    else:
        return 0, f"VirusTotal: clean (0/{total})"


# ---------------------------------------------------------------------
# Database helpers
# ---------------------------------------------------------------------

def get_seen_record(conn, file_hash):
    """Look up a hash's full record, including any cached VT result."""
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        "SELECT * FROM processes_seen WHERE file_hash = %s", (file_hash,)
    )
    result = cursor.fetchone()
    cursor.close()
    return result


def record_first_seen(conn, file_hash, name, path):
    cursor = conn.cursor()
    cursor.execute(
        """INSERT INTO processes_seen (file_hash, process_name, file_path)
           VALUES (%s, %s, %s)
           ON DUPLICATE KEY UPDATE process_name = process_name""",
        (file_hash, name, path),
    )
    conn.commit()
    cursor.close()


def save_vt_result(conn, file_hash, vt_result):
    """Cache the VirusTotal result so we never re-check this hash again."""
    cursor = conn.cursor()
    malicious = vt_result["malicious"] if vt_result else 0
    total = vt_result["total"] if vt_result else 0
    cursor.execute(
        """UPDATE processes_seen
           SET vt_checked_at = NOW(), vt_malicious_count = %s, vt_total_engines = %s
           WHERE file_hash = %s""",
        (malicious, total, file_hash),
    )
    conn.commit()
    cursor.close()


def insert_snapshot(conn):
    """Create the snapshot row this scan's alerts hang off, and return its id.

    The snapshot JSON blob is deliberately left NULL - the Laravel side
    (LiveSnapshotService) falls back to live_snapshot.json when it is empty,
    so this script does not have to duplicate the dashboard payload.
    """
    cursor = conn.cursor()
    cursor.execute(
        """INSERT INTO monitoring_snapshots
               (snapshot_timestamp, status, created_at, updated_at)
           VALUES (NOW(), 'normal', NOW(), NOW())"""
    )
    conn.commit()
    snapshot_id = cursor.lastrowid
    cursor.close()
    return snapshot_id


def set_snapshot_process_count(conn, snapshot_id, process_count):
    cursor = conn.cursor()
    cursor.execute(
        "UPDATE monitoring_snapshots SET process_count = %s WHERE id = %s",
        (process_count, snapshot_id),
    )
    conn.commit()
    cursor.close()


def record_alert(conn, snapshot_id, name, pid, path, score, level, reasons,
                 vt_detections=None):
    cursor = conn.cursor()
    message = f"{name} triggered risk scoring rules ({', '.join(reasons)})"
    details = json.dumps({
        "score": score,
        "reasons": reasons,
        "source": path or name,
        "pid": pid,
        "virustotal_detections": vt_detections,
    })
    cursor.execute(
        """INSERT INTO alerts (monitoring_snapshot_id, process_id, alert_type,
                                 severity, message, details, acknowledged)
           VALUES (%s, NULL, 'risk_score', %s, %s, %s, 0)""",
        (snapshot_id, level.lower(), message, details),
    )
    conn.commit()
    cursor.close()


# ---------------------------------------------------------------------
# Detection logic (unchanged from Step 4/5)
# ---------------------------------------------------------------------

def hash_file(filepath):
    try:
        sha256 = hashlib.sha256()
        with open(filepath, "rb") as f:
            for chunk in iter(lambda: f.read(8192), b""):
                sha256.update(chunk)
        return sha256.hexdigest()
    except (FileNotFoundError, PermissionError, OSError):
        return None


def _path_segments(path_text):
    normalized = path_text.lower().replace("\\", "/")
    return [seg for seg in normalized.split("/") if seg]


def is_suspicious_path(filepath, cmdline=None):
    segments = _path_segments(filepath)
    if any(k in segments for k in SUSPICIOUS_PATH_KEYWORDS):
        return True
    if cmdline:
        for arg in cmdline:
            if any(k in _path_segments(arg) for k in SUSPICIOUS_PATH_KEYWORDS):
                return True
    return False


def is_unsigned(filepath):
    if platform.system() != "Windows":
        return None
    try:
        import win32api
        win32api.GetFileVersionInfo(filepath, "\\")
        return False
    except ImportError:
        return None
    except Exception:
        return True


def risk_level(score):
    if score >= 50:
        return "HIGH"
    elif score >= 25:
        return "MEDIUM"
    return "LOW"


# ---------------------------------------------------------------------
# Main scan loop
# ---------------------------------------------------------------------

def scan_processes():
    try:
        conn = get_connection()
    except Error as e:
        print(f"Could not connect to MySQL: {e}")
        return

    if VT_API_KEY == "PASTE_YOUR_VIRUSTOTAL_API_KEY_HERE":
        print("WARNING: VirusTotal API key not set - VT checks will be skipped.\n")

    flagged_count = 0
    new_count = 0
    vt_checks_this_run = 0
    scanned_count = 0

    try:
        snapshot_id = insert_snapshot(conn)
    except Error as e:
        print(f"Could not create monitoring snapshot: {e}")
        conn.close()
        return

    for proc in sample_processes(['pid', 'name', 'exe', 'cpu_percent', 'cmdline']):
        try:
            info = proc.info
            pid = info['pid']
            name = info['name'] or "Unknown"
            path = info['exe']
            cpu = info['cpu_percent'] or 0.0
            cmdline = info['cmdline'] or []

            if not path:
                continue

            file_hash = hash_file(path)
            if file_hash is None:
                continue

            scanned_count += 1

            existing = get_seen_record(conn, file_hash)
            first_seen = existing is None

            if first_seen:
                record_first_seen(conn, file_hash, name, path)
                new_count += 1
                existing = get_seen_record(conn, file_hash)

            score = 0
            reasons = []

            if first_seen:
                score += POINTS_FIRST_SEEN
                reasons.append(f"first-seen (+{POINTS_FIRST_SEEN})")
            if cpu > CPU_THRESHOLD:
                score += POINTS_HIGH_CPU
                reasons.append(f"high CPU {cpu:.0f}% (+{POINTS_HIGH_CPU})")
            if is_suspicious_path(path, cmdline):
                score += POINTS_SUSPICIOUS_PATH
                reasons.append(f"suspicious path (+{POINTS_SUSPICIOUS_PATH})")
            if is_unsigned(path) is True:
                score += POINTS_UNSIGNED
                reasons.append(f"unsigned executable (+{POINTS_UNSIGNED})")

            # --- VirusTotal check: only for first-seen, uncached hashes ---
            vt_detections_display = None
            already_checked = existing and existing.get("vt_checked_at") is not None

            if first_seen and not already_checked and VT_API_KEY != "PASTE_YOUR_VIRUSTOTAL_API_KEY_HERE":
                print(f"  Checking VirusTotal for {name}...")
                vt_result = check_virustotal(file_hash)
                save_vt_result(conn, file_hash, vt_result)
                vt_points, vt_reason = vt_points_and_reason(vt_result)
                if vt_reason:
                    reasons.append(vt_reason)
                score += vt_points
                if vt_result:
                    vt_detections_display = f"{vt_result['malicious']}/{vt_result['total']}"

                vt_checks_this_run += 1
                time.sleep(VT_SECONDS_BETWEEN_REQUESTS)

            elif already_checked and existing.get("vt_malicious_count") is not None:
                # Reuse the cached result instead of calling the API again
                cached = {"malicious": existing["vt_malicious_count"],
                          "total": existing["vt_total_engines"]}
                vt_points, vt_reason = vt_points_and_reason(cached)
                if vt_reason and vt_points > 0:
                    reasons.append(vt_reason)
                score += vt_points
                vt_detections_display = f"{cached['malicious']}/{cached['total']}"

            level = risk_level(score)

            if level in ("MEDIUM", "HIGH"):
                record_alert(conn, snapshot_id, name, pid, path, score, level,
                             reasons, vt_detections_display)
                flagged_count += 1
                print(f"[{level}] {name} (PID {pid}) - score {score} - "
                      f"{', '.join(reasons)}")

        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue

    set_snapshot_process_count(conn, snapshot_id, scanned_count)
    conn.close()
    print(f"\nScan complete: {scanned_count} process(es) scanned, "
          f"{new_count} new, "
          f"{vt_checks_this_run} VirusTotal check(s) made, "
          f"{flagged_count} alert(s) written.")


if __name__ == "__main__":
    print("SentinelProc - Full Scan (MySQL + VirusTotal)\n")
    scan_processes()