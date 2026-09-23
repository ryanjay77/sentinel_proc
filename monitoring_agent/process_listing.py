

import psutil
import time


def list_processes():
    """Print every running process with its PID, name, CPU%, memory%, and path."""

    print(f"{'PID':<8}{'NAME':<30}{'CPU%':<8}{'MEM%':<8}{'PATH'}")
    print("-" * 100)

    for proc in psutil.process_iter(['pid', 'name', 'cpu_percent',
                                       'memory_percent', 'exe']):
        try:
            info = proc.info
            pid = info['pid']
            name = info['name'] or "Unknown"
            cpu = info['cpu_percent'] or 0.0
            mem = info['memory_percent'] or 0.0
            path = info['exe'] or "N/A"

            print(f"{pid:<8}{name:<30}{cpu:<8.1f}{mem:<8.1f}{path}")

        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            # Some processes disappear or need admin rights to inspect -
            # just skip them instead of crashing the whole script.
            continue


if __name__ == "__main__":
    print("SentinelProc - Live Process Snapshot\n")
    list_processes()

    # CPU% needs two readings to be accurate (psutil quirk) - so we take
    # a second snapshot 2 seconds later to show real, non-zero values.
    print("\nTaking a second reading in 2 seconds for accurate CPU%...\n")
    time.sleep(2)
    list_processes()