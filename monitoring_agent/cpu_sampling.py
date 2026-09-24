"""CPU sampling shared by the SentinelProc agent.

psutil's first cpu_percent() reading for a process is always 0.0 — there is no
earlier sample to diff against — so a single process_iter() pass reports every
process on the machine as idle. Every CPU threshold rule and every CPU column
stays dead until the counter is primed, allowed to accumulate, and read again.
"""

import time

import psutil

# How long primed counters accumulate before the real reading.
SAMPLE_INTERVAL_SECS = 0.5

# Windows reports the combined idle time of all cores as "System Idle Process"
# (pid 0). It does no work and has no executable, so it is never a CPU consumer —
# on a 4-core machine it reads ~400% and would top any ranking it appears in.
IDLE_PIDS = frozenset({0})


def read_cpu_percent(proc) -> float:
    """cpu_percent() that returns 0.0 instead of raising for a process that died."""
    try:
        return proc.cpu_percent(None)
    except psutil.Error:
        return 0.0


def sample_processes(attrs, interval=SAMPLE_INTERVAL_SECS):
    """psutil.process_iter() whose 'cpu_percent' entry holds a real reading.

    Primes every handle, waits `interval`, then re-reads into proc.info — so
    callers keep using `proc.info['cpu_percent']` exactly as before.
    """
    procs = []
    for proc in psutil.process_iter(attrs):
        if proc.info.get('pid') in IDLE_PIDS:
            continue
        proc.info['cpu_percent'] = read_cpu_percent(proc)
        procs.append(proc)

    time.sleep(interval)

    for proc in procs:
        proc.info['cpu_percent'] = read_cpu_percent(proc)

    return procs
