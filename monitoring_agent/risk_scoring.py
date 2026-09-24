

import psutil
import hashlib
import json
import os
import platform
import time

from cpu_sampling import sample_processes

SEEN_HASHES_FILE = "seen_hashes.json"

POINTS_FIRST_SEEN = 25
POINTS_HIGH_CPU = 20
POINTS_SUSPICIOUS_PATH = 25
POINTS_UNSIGNED = 30

CPU_THRESHOLD = 50.0  # percent

SUSPICIOUS_PATH_KEYWORDS = {"temp", "tmp", "downloads"}


def _path_segments(path_text):
    normalized = (path_text or "").lower().replace("\\", "/")
    return [seg for seg in normalized.split("/") if seg]

def load_seen_hashes():
    if os.path.exists(SEEN_HASHES_FILE):
        with open(SEEN_HASHES_FILE, "r") as f:
            return json.load(f)
    return {}

def save_seen_hashes(seen_hashes):
    with open(SEEN_HASHES_FILE, "w") as f:
        json.dump(seen_hashes, f, indent=2)

def hash_file(filepath):
    try:
        sha256 = hashlib.sha256()
        with open(filepath, "rb") as f:
            for chunk in iter(lambda: f.read(8192), b""):
                sha256.update(chunk)
        return sha256.hexdigest()
    except (FileNotFoundError, PermissionError, OSError):
        return None

def is_suspicious_path(filepath, cmdline=None):
    """True when the executable path or any command-line argument names a
    staging folder (Temp, tmp, Downloads).

    Keywords are compared against whole path segments, matching live_monitor.py
    and virus_total.py, so a folder called "templates" or "attempts" is not
    mistaken for a Temp directory.
    """
    if any(keyword in _path_segments(filepath) for keyword in SUSPICIOUS_PATH_KEYWORDS):
        return True

    if cmdline:
        for arg in cmdline:
            if any(keyword in _path_segments(arg) for keyword in SUSPICIOUS_PATH_KEYWORDS):
                return True

    return False

def is_unsigned(filepath):

    if platform.system() != "Windows":
        return None  

    try:
        import win32api
        import win32con

        win32api.GetFileVersionInfo(filepath, "\\")
        return False  
    except ImportError:
        return None  
    except Exception:
        return True  


def calculate_risk_score(name, filepath, cpu_percent, first_seen, cmdline=None):
    """
    Add up points for every red flag this process triggers.
    Returns (score, list_of_reasons) so we can always explain the result.
    """
    score = 0
    reasons = []

    if first_seen:
        score += POINTS_FIRST_SEEN
        reasons.append(f"first-seen (+{POINTS_FIRST_SEEN})")

    if cpu_percent > CPU_THRESHOLD:
        score += POINTS_HIGH_CPU
        reasons.append(f"high CPU {cpu_percent:.0f}% (+{POINTS_HIGH_CPU})")

    if is_suspicious_path(filepath, cmdline):
        score += POINTS_SUSPICIOUS_PATH
        reasons.append(f"suspicious path (+{POINTS_SUSPICIOUS_PATH})")

    unsigned = is_unsigned(filepath)
    if unsigned is True:
        score += POINTS_UNSIGNED
        reasons.append(f"unsigned executable (+{POINTS_UNSIGNED})")

    return score, reasons


def risk_level(score):
    if score >= 50:
        return "HIGH"
    elif score >= 25:
        return "MEDIUM"
    else:
        return "LOW"


def scan_processes():
    seen_hashes = load_seen_hashes()
    new_hashes_this_run = {}
    flagged = []

    print(f"{'PID':<8}{'NAME':<22}{'SCORE':<8}{'LEVEL':<8}{'REASONS'}")
    print("-" * 100)

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

            first_seen = file_hash not in seen_hashes
            if first_seen:
                new_hashes_this_run[file_hash] = {
                    "name": name,
                    "path": path,
                    "first_seen": time.strftime("%Y-%m-%d %H:%M:%S"),
                }

            score, reasons = calculate_risk_score(name, path, cpu, first_seen, cmdline)
            level = risk_level(score)
            reason_text = ", ".join(reasons) if reasons else "no red flags"

            print(f"{pid:<8}{name:<22}{score:<8}{level:<8}{reason_text}")

            if level in ("HIGH", "MEDIUM"):
                flagged.append((pid, name, score, level, reasons))

        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue

    if new_hashes_this_run:
        seen_hashes.update(new_hashes_this_run)
        save_seen_hashes(seen_hashes)

    print(f"\n{len(flagged)} process(es) flagged as MEDIUM or HIGH risk this run.")
    return flagged


if __name__ == "__main__":
    print("SentinelProc - Risk-Scored Process Scan\n")
    scan_processes()