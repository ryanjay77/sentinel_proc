"""HTTPS transport for the SentinelProc agent's API-only mode.

Used when SENTINEL_TRANSPORT=api: the agent fetches its scan context
(first-seen hashes, VirusTotal cache, allow/deny lists) and posts finished
snapshots through these calls, so a laptop running the agent never needs
database credentials or network access to MySQL.
"""

import time
from urllib.parse import urlparse

import requests

from agent_config import api_allow_http, api_token, api_url


class ApiError(Exception):
    """The API is unreachable or rejected a request after retries."""


class ApiClient:
    def __init__(self, base_url=None, token=None, timeout=30, max_retries=3, session=None):
        self.base_url = (base_url or api_url()).rstrip('/')
        self.token = token or api_token()
        self.timeout = timeout
        self.max_retries = max_retries
        self.session = session or requests.Session()
        if not self.base_url or not self.token:
            raise ApiError(
                'SENTINEL_API_URL and SENTINEL_API_TOKEN must be set for '
                'SENTINEL_TRANSPORT=api (see .env.example)'
            )
        if urlparse(self.base_url).scheme.lower() == 'http' and not api_allow_http():
            raise ApiError(
                'SENTINEL_API_URL must use HTTPS. Set SENTINEL_ALLOW_HTTP=true only '
                'for a trusted local network such as a phone hotspot.'
            )

    def _post(self, path, payload):
        """POST JSON with auth, retrying transient failures.

        Retrying is safe for both endpoints: the context call is read-only,
        and snapshot posts carry a scan_uuid so the server dedupes them.
        Client errors (401/403/422) are raised immediately — retrying those
        can never succeed.
        """
        url = self.base_url + path
        headers = {
            'Authorization': f'Bearer {self.token}',
            'Accept': 'application/json',
        }

        last_error = None
        for attempt in range(self.max_retries + 1):
            try:
                response = self.session.post(
                    url, json=payload, headers=headers, timeout=self.timeout,
                )
            except requests.RequestException as exc:
                last_error = exc
            else:
                if response.status_code in (200, 201):
                    return response.json()
                if response.status_code in (408, 429, 500, 502, 503, 504):
                    last_error = ApiError(f'HTTP {response.status_code} from {url}')
                else:
                    if response.status_code == 401:
                        raise ApiError(
                            'Token rejected (HTTP 401). Generate a fresh token for this laptop '
                            'and check SENTINEL_API_TOKEN.'
                        )
                    if response.status_code == 403:
                        raise ApiError(
                            'Token lacks the required SentinelProc API permission (HTTP 403). '
                            'Generate a monitoring agent token.'
                        )
                    raise ApiError(
                        f'API rejected the request (HTTP {response.status_code}): '
                        f'{response.text[:200]}'
                    )

            if attempt < self.max_retries:
                time.sleep(2 ** attempt)

        if isinstance(last_error, requests.RequestException):
            detail = self._friendly_request_error(last_error, url)
        else:
            detail = str(last_error)
        raise ApiError(f'Giving up on {url} after {self.max_retries + 1} attempts: {detail}')

    def check_health(self):
        """Check server reachability without sending the agent token."""
        url = self.base_url + '/api/health'
        try:
            response = self.session.get(url, timeout=self.timeout)
        except requests.RequestException as exc:
            raise ApiError(self._friendly_request_error(exc, url)) from exc

        if response.status_code == 200:
            return
        if response.status_code == 404:
            raise ApiError(
                f'SentinelProc health endpoint was not found at {url}. '
                'Check that SENTINEL_API_URL is the server root URL.'
            )
        raise ApiError(f'SentinelProc health check returned HTTP {response.status_code} from {url}.')

    def _friendly_request_error(self, error, url):
        if isinstance(error, (requests.exceptions.MissingSchema,
                              requests.exceptions.InvalidSchema,
                              requests.exceptions.InvalidURL)):
            return (
                f'SENTINEL_API_URL is not a valid URL ({self.base_url}). '
                'Use the complete https:// URL printed by cloudflared.'
            )
        if isinstance(error, requests.exceptions.SSLError):
            return (
                f'HTTPS certificate validation failed for {url}. Check the URL and system clock; '
                'certificate verification remains enabled.'
            )

        message = str(error).lower()
        if any(marker in message for marker in (
            'name or service not known', 'getaddrinfo failed', 'name resolution',
            'nodename nor servname',
        )):
            return (
                f'Could not resolve the server host for {url}. Check SENTINEL_API_URL '
                'for a typo and make sure this laptop has internet access.'
            )
        return (
            f'Cannot reach SentinelProc at {url}. Check this laptop\'s internet connection, '
            'that the tunnel is running, and that SENTINEL_API_URL is correct.'
        )

    def fetch_context(self, hashes):
        """Return the server-side scan context for the given file hashes.

        {'known_hashes': [...], 'vt_cache': {hash: {'malicious', 'total'}},
         'process_lists': {'whitelist': [...], 'blacklist': [...]}}
        """
        return self._post('/api/monitoring/context', {'hashes': list(hashes)})

    def post_snapshot(self, payload):
        """Send a finished snapshot envelope. Returns the decoded response."""
        return self._post('/api/monitoring/snapshot', payload)
