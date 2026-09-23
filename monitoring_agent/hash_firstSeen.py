

import psutil
import hashlib
import json
import os
import time

# This file acts as our "memory" of every hash we've ever seen.
# Later, this becomes a MySQL table - but the concept is identical.
SEEN_HASHES_FILE = "seen_hashes.json"


def load_seen_hashes():
    """Load the record of previously-seen file hashes from disk."""
    if os.path.exists(SEEN_HASHES_FILE):
        with open(SEEN_HASHES_FILE, "r") as f:
            return json.load(f)
    return {}  # empty record if this is the very first run


def save_seen_hashes(seen_hashes):
    """Persist the updated record of seen hashes back to disk."""
    with open(SEEN_HASHES_FILE, "w") as f:
        json.dump(seen_hashes, f, indent=2)


def hash_file(filepath):
    """
    Compute the SHA-256 hash of a file, reading it in small chunks so
    even large executables don't get loaded fully into memory at once.
    Returns None if the file can't be read (permissions, deleted, etc.)
    """
    try:
        sha256 = hashlib.sha256()
        with open(filepath, "rb") as f:
            for chunk in iter(lambda: f.read(8192), b""):
                sha256.update(chunk)
        return sha256.hexdigest()
    except (FileNotFoundError, PermissionError, OSError):
        return None


def scan_processes():
    """
    Walk through every running process, hash its executable, and check
    whether that hash has been seen before on this machine.
    """
    seen_hashes = load_seen_hashes()
    new_hashes_this_run = {}

    print(f"{'PID':<8}{'NAME':<25}{'STATUS':<14}{'HASH (first 12 chars)'}")
    print("-" * 80)

    for proc in psutil.process_iter(['pid', 'name', 'exe']):
        try:
            info = proc.info
            pid = info['pid']
            name = info['name'] or "Unknown"
            path = info['exe']

            if not path:
                # Some processes (kernel threads, protected system
                # processes) have no accessible file path - skip them.
                continue

            file_hash = hash_file(path)
            if file_hash is None:
                continue

            if file_hash in seen_hashes:
                status = "Known"
            else:
                status = "FIRST-SEEN"
                new_hashes_this_run[file_hash] = {
                    "name": name,
                    "path": path,
                    "first_seen": time.strftime("%Y-%m-%d %H:%M:%S"),
                }

            print(f"{pid:<8}{name:<25}{status:<14}{file_hash[:12]}")

        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue

    # Merge this run's new hashes into our permanent record and save.
    if new_hashes_this_run:
        seen_hashes.update(new_hashes_this_run)
        save_seen_hashes(seen_hashes)
        print(f"\n{len(new_hashes_this_run)} new process(es) recorded as seen for the first time.")
    else:
        print("\nNo new processes this run - everything matched a known hash.")


if __name__ == "__main__":
    print("SentinelProc - First-Seen Process Scan\n")
    scan_processes()