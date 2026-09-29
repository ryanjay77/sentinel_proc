"""Advisory Windows warning dialogs for medium/high risk processes."""

import ctypes
import platform
import threading


_notified_hashes = set()
_notified_lock = threading.Lock()


def _show_message(title: str, message: str) -> None:
    ctypes.windll.user32.MessageBoxW(None, message, title, 0x30)


def _warning_for(process: dict) -> tuple[str, str]:
    name = process.get('name') or 'Unknown program'
    file_hash = process['hash']

    if process['risk_level'] == 'high':
        return (
            'SentinelProc - HIGH risk warning',
            f"HIGH risk detected for {name}. Do not open this file again. "
            'Save your work, run a full antivirus scan, and report this to '
            f"your administrator.\n\nSHA-256: {file_hash}",
        )

    return (
        'SentinelProc - MEDIUM risk warning',
        f"Please check whether you started {name} yourself. If not, close "
        'it and tell your administrator. This is an advisory warning.\n\n'
        f"SHA-256: {file_hash}",
    )


def notify_risky_process(process: dict) -> bool:
    """Schedule one advisory dialog per medium/high file hash per run."""
    if platform.system() != 'Windows':
        return False

    severity = str(process.get('risk_level', '')).lower()
    file_hash = process.get('hash')
    if severity not in {'medium', 'high'} or not file_hash:
        return False

    unique_hash = str(file_hash).lower()
    with _notified_lock:
        if unique_hash in _notified_hashes:
            return False
        _notified_hashes.add(unique_hash)

    title, message = _warning_for({**process, 'risk_level': severity, 'hash': unique_hash})
    try:
        threading.Thread(
            target=_show_message,
            args=(title, message),
            name='sentinelproc-risk-warning',
            daemon=False,
        ).start()
    except RuntimeError:
        with _notified_lock:
            _notified_hashes.discard(unique_hash)
        return False

    return True


def notify_risky_processes(processes: list[dict]) -> None:
    for process in processes:
        notify_risky_process(process)