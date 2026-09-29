@echo off
REM SentinelProc — One-click agent launcher for remote laptops
REM Double-click this file to start monitoring.

setlocal

REM ── 1. Check Python is installed ──────────────────────────────────────────
python --version >nul 2>&1
if errorlevel 1 (
    echo.
    echo [ERROR] Python is not installed.
    echo Download and install Python from https://python.org
    echo Make sure to check "Add Python to PATH" during installation.
    echo.
    pause
    exit /b 1
)

REM ── 2. Install dependencies ───────────────────────────────────────────────
echo [1/3] Installing dependencies...
python -m pip install -r requirements.txt
if errorlevel 1 (
    echo.
    echo [ERROR] Failed to install dependencies.
    echo Check your internet connection and try again.
    echo.
    pause
    exit /b 1
)

REM ── 3. Create .env if missing ─────────────────────────────────────────────
if not exist .env (
    echo.
    echo [2/3] Creating .env file...
    echo.
    echo Paste your unique token (shown once when minted):
    set /p API_TOKEN="SENTINEL_API_TOKEN=(
    echo.
    echo SENTINEL_TRANSPORT=api> .env
    echo SENTINEL_API_URL=https://sentinelproc-production-f000.up.railway.app>> .env
    echo SENTINEL_API_TOKEN=%API_TOKEN%>> .env
    echo.
    echo [.env created. Edit it if your server URL or token changes.]
    echo.
) else (
    echo [2/3] .env already exists.
)

REM ── 4. Run the agent ──────────────────────────────────────────────────────
echo [3/3] Starting agent...
echo.
python live_monitor.py

pause
