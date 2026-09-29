import json
import os
import tempfile
import unittest
import uuid
import contextlib
import io
from types import SimpleNamespace
from unittest import mock

import live_monitor
from api_client import ApiError


class ShouldUseMysqlTests(unittest.TestCase):
    """should_use_mysql() decides whether the agent attempts a database write.

    It reads the module-level DB_CONFIG at call time, and DB_CONFIG is built
    once at import, so each case patches it directly rather than the
    environment.
    """

    def _with_config(self, **overrides):
        config = {
            'host': '127.0.0.1',
            'port': 3306,
            'user': 'sentinel_user',
            'password': 'secret',
            'database': 'sentinel_proc',
        }
        config.update(overrides)
        return mock.patch.object(live_monitor, 'DB_CONFIG', config)

    def test_configured_connection_is_usable(self):
        with self._with_config():
            self.assertTrue(live_monitor.should_use_mysql())

    def test_missing_host_disables_the_database(self):
        with self._with_config(host=''):
            self.assertFalse(live_monitor.should_use_mysql())

    def test_missing_user_disables_the_database(self):
        with self._with_config(user=''):
            self.assertFalse(live_monitor.should_use_mysql())

    def test_missing_database_disables_the_database(self):
        with self._with_config(database=''):
            self.assertFalse(live_monitor.should_use_mysql())

    def test_empty_password_is_still_usable(self):
        # A local MySQL account may legitimately have no password.
        with self._with_config(password=''):
            self.assertTrue(live_monitor.should_use_mysql())

    def test_api_mode_disables_the_database_even_when_configured(self):
        # Remote laptops may carry leftover DB settings; API mode ignores them.
        with self._with_config(), mock.patch.object(live_monitor, 'TRANSPORT', 'api'):
            self.assertFalse(live_monitor.should_use_mysql())


class _FakeProcess:
    def __init__(self, info, username='CORP\\tester'):
        self.info = info
        self._username = username

    def username(self):
        return self._username


class _StubApiClient:
    def __init__(self, context):
        self.context = context
        self.context_calls = []
        self.posted = []

    def fetch_context(self, hashes):
        self.context_calls.append(list(hashes))
        return self.context

    def post_snapshot(self, payload):
        self.posted.append(payload)
        return {'ok': True, 'snapshot_id': 42, 'duplicate': False}


H_CHROME  = 'a' * 64
H_TRUSTED = 'b' * 64
H_BANNED  = 'c' * 64
H_KNOWN   = 'd' * 64

PATHS = {
    'C:/Program Files/chrome.exe': H_CHROME,
    'C:/apps/trusted.exe':          H_TRUSTED,
    'C:/temp/banned.exe':           H_BANNED,
    'C:/Windows/known.exe':        H_KNOWN,
}


class CollectSnapshotApiTests(unittest.TestCase):
    """The remote-laptop scan path: one context call, local scoring, one POST."""

    def _scan_processes(self):
        return iter([
            _FakeProcess({'pid': 101, 'name': 'chrome.exe', 'exe': 'C:/Program Files/chrome.exe',
                          'cpu_percent': 5.0, 'memory_info': SimpleNamespace(rss=100 * 1024 * 1024)}),
            _FakeProcess({'pid': 102, 'name': 'known.exe', 'exe': 'C:/Windows/known.exe',
                          'cpu_percent': 1.0, 'memory_info': SimpleNamespace(rss=50 * 1024 * 1024)}),
            _FakeProcess({'pid': 103, 'name': 'trusted.exe', 'exe': 'C:/apps/trusted.exe',
                          'cpu_percent': 0.5, 'memory_info': SimpleNamespace(rss=30 * 1024 * 1024)}),
            _FakeProcess({'pid': 104, 'name': 'banned.exe', 'exe': 'C:/temp/banned.exe',
                          'cpu_percent': 0.1, 'memory_info': SimpleNamespace(rss=10 * 1024 * 1024)}),
        ])

    def _context(self):
        return {
            'known_hashes': [H_KNOWN],
            'vt_cache': {},
            'process_lists': {
                'whitelist': [{'match_by': 'name', 'value': 'trusted.exe'}],
                'blacklist': [{'match_by': 'name', 'value': 'banned.exe'}],
            },
        }

    def _run_scan(self, stub, processes=None, vt_api_key=''):
        tmp = tempfile.mkdtemp()
        output = os.path.join(tmp, 'live_snapshot.json')
        patches = [
            mock.patch.object(live_monitor, 'TRANSPORT', 'api'),
            mock.patch.object(live_monitor, 'HOSTNAME', 'test-laptop'),
            mock.patch.object(live_monitor, 'OUTPUT_PATH', output),
            mock.patch.object(live_monitor, 'VT_API_KEY', vt_api_key),
            mock.patch.object(live_monitor, 'ApiClient', lambda: stub),
            mock.patch.object(live_monitor, 'sample_processes',
                              return_value=processes if processes is not None else self._scan_processes()),
            mock.patch.object(live_monitor, 'hash_file', lambda path: PATHS.get(path)),
            mock.patch.object(live_monitor, 'is_unsigned', lambda path: None),
            mock.patch.object(live_monitor, 'db_connect'),
            mock.patch.object(live_monitor, 'notify_risky_processes'),
        ]
        for patch in patches:
            patch.start()
            self.addCleanup(patch.stop)
        self._output = output
        return live_monitor.collect_snapshot_api()

    def test_dispatch_selects_the_api_path(self):
        with mock.patch.object(live_monitor, 'TRANSPORT', 'api'), \
                mock.patch.object(live_monitor, 'collect_snapshot_api') as api_scan:
            live_monitor.collect_snapshot()
        api_scan.assert_called_once()

    def test_batches_scores_and_posts_the_whole_scan(self):
        stub = _StubApiClient(self._context())
        snapshot = self._run_scan(stub)

        # One context call for the whole scan, with the unique hashes.
        self.assertEqual(len(stub.context_calls), 1)
        self.assertEqual(stub.context_calls[0], sorted(PATHS.values()))

        # The dashboard snapshot keeps the local-mode JSON shape.
        for key in ('stats', 'processes', 'alerts', 'generated_at'):
            self.assertIn(key, snapshot)
        with open(self._output, encoding='utf-8') as f:
            self.assertEqual(json.load(f), snapshot)

        # One snapshot POST carrying every scored process.
        self.assertEqual(len(stub.posted), 1)
        payload = stub.posted[0]
        self.assertEqual(payload['process_count'], 4)
        self.assertEqual(payload['hostname'], 'test-laptop')
        self.assertEqual(json.loads(payload['snapshot'])['hostname'], 'test-laptop')
        uuid.UUID(payload['scan_uuid'])  # raises if not a valid UUID
        posted = {p['name']: p for p in payload['processes']}
        self.assertEqual(len(posted), 4)

        # first_seen comes from the server-provided known_hashes
        self.assertTrue(posted['chrome.exe']['first_seen'])
        self.assertFalse(posted['known.exe']['first_seen'])

        # list overrides applied during local scoring
        self.assertEqual((posted['trusted.exe']['risk_level'], posted['trusted.exe']['score'],
                          posted['trusted.exe']['reasons']), ('low', 0, ['whitelisted']))
        self.assertEqual((posted['banned.exe']['risk_level'], posted['banned.exe']['score'],
                          posted['banned.exe']['reasons']), ('high', 100, ['blacklisted']))

        # chrome.exe: first-seen only -> 25 points -> medium
        self.assertEqual((posted['chrome.exe']['risk_level'], posted['chrome.exe']['score']),
                         ('medium', 25))

        # the user field is carried in the payload's status column
        self.assertEqual(posted['chrome.exe']['status'], 'CORP\\tester')

    def test_uses_cached_vt_verdict_without_querying_virustotal(self):
        context = {
            'known_hashes': [],
            'vt_cache': {H_CHROME: {'malicious': 3, 'total': 70}},
            'process_lists': {'whitelist': [], 'blacklist': []},
        }
        stub = _StubApiClient(context)
        with mock.patch.object(live_monitor, 'check_virustotal') as vt_check:
            self._run_scan(stub, processes=iter([
                _FakeProcess({'pid': 101, 'name': 'chrome.exe', 'exe': 'C:/Program Files/chrome.exe',
                              'cpu_percent': 5.0, 'memory_info': SimpleNamespace(rss=1024)}),
            ]), vt_api_key='fake-key')
        vt_check.assert_not_called()

        posted = stub.posted[0]['processes'][0]
        # first-seen 25 + VT suspicious 30 = 55 -> high
        self.assertEqual((posted['risk_level'], posted['score']), ('high', 55))
        self.assertEqual(posted['virus_total_data'], {'malicious': 3, 'total': 70})
        self.assertIn('virustotal suspicious', posted['reasons'])


    def test_api_only_never_queries_virustotal_for_uncached_hashes(self):
        context = {
            'known_hashes': [],
            'vt_cache': {},
            'process_lists': {'whitelist': [], 'blacklist': []},
        }
        stub = _StubApiClient(context)
        with mock.patch.object(live_monitor, 'check_virustotal',
                               return_value={'malicious': 6, 'total': 70}) as vt_check:
            self._run_scan(stub, processes=iter([
                _FakeProcess({'pid': 101, 'name': 'chrome.exe', 'exe': 'C:/Program Files/chrome.exe',
                              'cpu_percent': 5.0, 'memory_info': SimpleNamespace(rss=1024)}),
            ]), vt_api_key='fake-key')
        vt_check.assert_not_called()

        posted = stub.posted[0]['processes'][0]
        self.assertEqual((posted['risk_level'], posted['score']), ('medium', 25))
        self.assertIsNone(posted['virus_total_data'])


class ApiAgentLoopTests(unittest.TestCase):
    def test_health_and_scan_failures_are_reported_without_stopping_retries(self):
        client = SimpleNamespace(
            base_url='https://sentinel.example.test',
            check_health=mock.Mock(side_effect=[
                ApiError('no internet'),
                None,
                None,
            ]),
        )
        output = io.StringIO()

        with mock.patch.object(live_monitor, 'ApiClient', return_value=client), \
                mock.patch.object(live_monitor, 'collect_snapshot_api', side_effect=[
                    ApiError('Token rejected (HTTP 401)'),
                    RuntimeError('temporary scan issue'),
                ]) as scan, \
                mock.patch.object(live_monitor.time, 'sleep') as sleep, \
                contextlib.redirect_stdout(output):
            live_monitor.run_api_agent(scan_interval=1, max_iterations=3)

        self.assertEqual(client.check_health.call_count, 3)
        self.assertEqual(scan.call_count, 2)
        self.assertEqual(sleep.call_count, 2)
        self.assertIn('Server not reachable', output.getvalue())
        self.assertIn('Server reachable', output.getvalue())
        self.assertIn('Token rejected (HTTP 401)', output.getvalue())
        self.assertIn('temporary scan issue', output.getvalue())


if __name__ == '__main__':
    unittest.main()
