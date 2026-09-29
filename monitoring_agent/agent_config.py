"""Configuration shared by the SentinelProc agent.

Supports both:
- Local mode: direct MySQL connection
- API mode: authenticated HTTPS connection to the SentinelProc backend

When packaged with PyInstaller, the .env file is loaded from the same
directory as SentinelProcAgent.exe rather than the temporary _MEI folder.
"""

import os
import sys


def _get_agent_directory():
    """Return the real directory containing the agent.

    Normal Python:
        Uses the directory containing this .py file.

    PyInstaller EXE:
        Uses the directory containing SentinelProcAgent.exe.
        This is important because PyInstaller extracts the application
        into a temporary _MEI folder at runtime.
    """

    if getattr(sys, "frozen", False):
        return os.path.dirname(os.path.abspath(sys.executable))

    return os.path.dirname(os.path.abspath(__file__))


AGENT_DIRECTORY = _get_agent_directory()

ENV_PATH = os.path.join(AGENT_DIRECTORY, ".env")


DB_DEFAULTS = {
    "host": "127.0.0.1",
    "port": "3306",
    "database": "sentinel_proc",
    "user": "",
    "password": "",
}


def load_env_file(path=ENV_PATH):
    """Load KEY=VALUE settings from the .env file.

    Existing environment variables are preserved and take priority
    over values found in the .env file.
    """

    try:
        with open(path, encoding="utf-8") as handle:
            lines = handle.readlines()
    except OSError:
        return

    for line in lines:
        line = line.strip()

        if not line:
            continue

        if line.startswith("#"):
            continue

        if "=" not in line:
            continue

        key, _, value = line.partition("=")

        key = key.strip()

        if not key:
            continue

        value = value.strip().strip('"').strip("'")

        os.environ.setdefault(key, value)


# Load .env immediately when this module is imported.
load_env_file()


def db_config():
    """Return MySQL database configuration."""

    return {
        "host": os.getenv(
            "SENTINEL_DB_HOST",
            DB_DEFAULTS["host"]
        ),

        "port": int(
            os.getenv(
                "SENTINEL_DB_PORT",
                DB_DEFAULTS["port"]
            )
        ),

        "user": os.getenv(
            "SENTINEL_DB_USER",
            DB_DEFAULTS["user"]
        ),

        "password": os.getenv(
            "SENTINEL_DB_PASSWORD",
            DB_DEFAULTS["password"]
        ),

        "database": os.getenv(
            "SENTINEL_DB_NAME",
            DB_DEFAULTS["database"]
        ),
    }


def vt_api_key():
    """Return the VirusTotal API key, if configured."""

    return os.getenv("VT_API_KEY", "")


def transport():
    """Return the configured transport mode.

    Supported values:
        local
        api

    API mode is recommended for remote laptops because the laptop
    communicates with the SentinelProc backend instead of connecting
    directly to the database.
    """

    return os.getenv(
        "SENTINEL_TRANSPORT",
        "local"
    ).strip().lower()


def api_url():
    """Return the SentinelProc backend API URL."""

    return os.getenv(
        "SENTINEL_API_URL",
        ""
    ).strip().rstrip("/")


def api_token():
    """Return the unique SentinelProc API token."""

    return os.getenv(
        "SENTINEL_API_TOKEN",
        ""
    ).strip()


def api_allow_http():
    """Return whether HTTP API connections are explicitly allowed.

    HTTPS should be used for the Railway production backend.
    """

    return os.getenv(
        "SENTINEL_ALLOW_HTTP",
        ""
    ).strip().lower() in {
        "1",
        "true",
        "yes",
        "on",
    }