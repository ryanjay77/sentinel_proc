"""Configuration shared by the SentinelProc agent scripts.

Credentials are read from the environment, falling back to
monitoring_agent/.env. That file is gitignored, so the agent can be pushed or
submitted without shipping a working database password — while the
non-interactive launch used by the dashboard's Refresh button
(MonitoringController::refresh writes a .bat that passes no environment of its
own) still finds its settings.
"""

import os

ENV_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), '.env')

# Non-secret connection details keep a default. The credentials deliberately
# have none, so an unconfigured checkout degrades to "no database" instead of
# silently trying a guessable account.
DB_DEFAULTS = {
    'host': '127.0.0.1',
    'port': '3306',
    'database': 'sentinel_proc',
    'user': '',
    'password': '',
}


def load_env_file(path=ENV_PATH):
    """Copy KEY=VALUE lines from `path` into os.environ.

    Existing environment variables win, so a value set by whoever launched the
    agent is never overridden by the file.
    """
    try:
        with open(path, encoding='utf-8') as handle:
            lines = handle.readlines()
    except OSError:
        return

    for line in lines:
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        key, _, value = line.partition('=')
        key = key.strip()
        if key:
            os.environ.setdefault(key, value.strip().strip('"').strip("'"))


load_env_file()


def db_config():
    return {
        'host':     os.getenv('SENTINEL_DB_HOST', DB_DEFAULTS['host']),
        'port':     int(os.getenv('SENTINEL_DB_PORT', DB_DEFAULTS['port'])),
        'user':     os.getenv('SENTINEL_DB_USER', DB_DEFAULTS['user']),
        'password': os.getenv('SENTINEL_DB_PASSWORD', DB_DEFAULTS['password']),
        'database': os.getenv('SENTINEL_DB_NAME', DB_DEFAULTS['database']),
    }


def vt_api_key():
    return os.getenv('VT_API_KEY', '')


def transport():
    """'local' (default) writes straight to MySQL; 'api' sends everything
    over the authenticated HTTPS API so remote laptops never touch the
    database directly."""
    return os.getenv('SENTINEL_TRANSPORT', 'local').strip().lower()


def api_url():
    return os.getenv('SENTINEL_API_URL', '').strip().rstrip('/')


def api_token():
    return os.getenv('SENTINEL_API_TOKEN', '').strip()


def api_allow_http():
    return os.getenv('SENTINEL_ALLOW_HTTP', '').strip().lower() in {'1', 'true', 'yes', 'on'}
