@echo off
REM SentinelProc — Cloudflare Quick Tunnel launcher
REM Copy the printed https://...trycloudflare.com URL for the agent configuration.
REM The URL changes each time the tunnel restarts.
REM Stop with Ctrl+C when the demo is over.

echo.
echo Starting Cloudflare Quick Tunnel for http://localhost:8000 ...
echo Copy the https:// URL printed below into your agent configuration.
echo Press Ctrl+C to stop the tunnel.
echo.

cloudflared tunnel --url http://localhost:8000

echo.
echo Tunnel stopped. Remember to revoke demo tokens and restore APP_URL.
echo.