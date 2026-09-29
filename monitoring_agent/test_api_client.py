import unittest
from unittest import mock

import requests

import api_client
from api_client import ApiClient, ApiError


class FakeResponse:
    def __init__(self, status_code, body, text=''):
        self.status_code = status_code
        self._body = body
        self.text = text or str(body)

    def json(self):
        return self._body


class FakeSession:
    """Hands out scripted responses/exceptions and records every POST."""

    def __init__(self, *script):
        self.script = list(script)
        self.calls = []

    def post(self, url, json=None, headers=None, timeout=None):
        self.calls.append(
            {'method': 'POST', 'url': url, 'json': json, 'headers': headers, 'timeout': timeout}
        )
        step = self.script.pop(0)
        if isinstance(step, Exception):
            raise step
        return step

    def get(self, url, timeout=None):
        self.calls.append({'method': 'GET', 'url': url, 'timeout': timeout})
        step = self.script.pop(0)
        if isinstance(step, Exception):
            raise step
        return step


class ConstructorTests(unittest.TestCase):
    def test_raises_a_helpful_error_without_url_or_token(self):
        with mock.patch.object(api_client, 'api_url', return_value=''):
            with mock.patch.object(api_client, 'api_token', return_value=''):
                with self.assertRaises(ApiError) as ctx:
                    ApiClient()
        self.assertIn('SENTINEL_API_URL', str(ctx.exception))
        self.assertIn('SENTINEL_API_TOKEN', str(ctx.exception))

    def test_explicit_arguments_beat_the_environment(self):
        client = ApiClient(
            base_url='https://sentinel.example.test',
            token='tok-123',
            session=FakeSession(),
        )
        self.assertEqual(client.base_url, 'https://sentinel.example.test')
        self.assertEqual(client.token, 'tok-123')

    def test_trailing_slash_on_the_base_url_is_stripped(self):
        client = ApiClient(
            base_url='https://sentinel.example.test/',
            token='tok-123',
            session=FakeSession(),
        )
        self.assertEqual(client.base_url, 'https://sentinel.example.test')

    def test_http_requires_an_explicit_local_network_opt_in(self):
        with mock.patch.object(api_client, 'api_allow_http', return_value=False):
            with self.assertRaises(ApiError) as ctx:
                ApiClient('http://192.168.1.20:8000', 'tok-123', session=FakeSession())
        self.assertIn('SENTINEL_ALLOW_HTTP=true', str(ctx.exception))

        with mock.patch.object(api_client, 'api_allow_http', return_value=True):
            client = ApiClient('http://192.168.1.20:8000', 'tok-123', session=FakeSession())
        self.assertEqual(client.base_url, 'http://192.168.1.20:8000')


class PostTests(unittest.TestCase):
    def _client(self, *script):
        session = FakeSession(*script)
        client = ApiClient(
            base_url='https://sentinel.example.test',
            token='tok-123',
            session=session,
        )
        return client, session

    def _post(self, *script, method='fetch_context', payload=None):
        client, session = self._client(*script)
        with mock.patch.object(api_client.time, 'sleep') as sleep:
            if method == 'fetch_context':
                result = client.fetch_context(['a' * 64, 'b' * 64])
            else:
                result = client.post_snapshot(payload or {'scan_uuid': 'x'})
        return result, session, sleep

    def test_fetch_context_posts_hash_list_with_bearer_auth(self):
        result, session, _ = self._post(
            FakeResponse(200, {'known_hashes': []}),
        )

        self.assertEqual(result, {'known_hashes': []})
        self.assertEqual(len(session.calls), 1)
        call = session.calls[0]
        self.assertEqual(call['url'], 'https://sentinel.example.test/api/monitoring/context')
        self.assertEqual(call['json'], {'hashes': ['a' * 64, 'b' * 64]})
        self.assertEqual(call['headers']['Authorization'], 'Bearer tok-123')
        self.assertEqual(call['headers']['Accept'], 'application/json')
        self.assertEqual(call['timeout'], 30)

    def test_post_snapshot_sends_the_envelope_to_the_snapshot_route(self):
        payload = {'scan_uuid': 'abc', 'process_count': 2}
        result, session, _ = self._post(
            FakeResponse(201, {'ok': True, 'snapshot_id': 7}),
            method='post_snapshot',
            payload=payload,
        )

        self.assertEqual(result, {'ok': True, 'snapshot_id': 7})
        call = session.calls[0]
        self.assertEqual(call['url'], 'https://sentinel.example.test/api/monitoring/snapshot')
        self.assertEqual(call['json'], payload)

    def test_client_errors_are_raised_without_retrying(self):
        client, session = self._client(FakeResponse(401, {}, text='Unauthenticated.'))
        with mock.patch.object(api_client.time, 'sleep') as sleep:
            with self.assertRaises(ApiError) as ctx:
                client.post_snapshot({'scan_uuid': 'abc'})

        self.assertIn('Token rejected (HTTP 401)', str(ctx.exception))
        self.assertIn('SENTINEL_API_TOKEN', str(ctx.exception))
        self.assertEqual(len(session.calls), 1)
        sleep.assert_not_called()

    def test_server_errors_are_retried_until_success(self):
        result, session, sleep = self._post(
            FakeResponse(500, {}, text='boom'),
            FakeResponse(503, {}, text='overloaded'),
            FakeResponse(201, {'ok': True}),
        )

        self.assertEqual(result, {'ok': True})
        self.assertEqual(len(session.calls), 3)
        self.assertEqual([c.args[0] for c in sleep.call_args_list], [1, 2])

    def test_connection_errors_are_retried(self):
        result, session, sleep = self._post(
            requests.ConnectionError('refused'),
            FakeResponse(200, {'known_hashes': ['a' * 64]}),
        )

        self.assertEqual(result, {'known_hashes': ['a' * 64]})
        self.assertEqual(len(session.calls), 2)
        sleep.assert_called_once_with(1)

    def test_gives_up_after_max_retries(self):
        client, session = self._client(
            *([FakeResponse(500, {}, text='down')] * 4)
        )
        with mock.patch.object(api_client.time, 'sleep') as sleep:
            with self.assertRaises(ApiError) as ctx:
                client.fetch_context(['a' * 64])

        self.assertIn('4 attempts', str(ctx.exception))
        self.assertEqual(len(session.calls), 4)
        self.assertEqual(len(sleep.call_args_list), 3)

    def test_health_check_is_public_and_uses_the_health_endpoint(self):
        client, session = self._client(FakeResponse(200, {'ok': True}))

        client.check_health()

        self.assertEqual(session.calls, [{
            'method': 'GET',
            'url': 'https://sentinel.example.test/api/health',
            'timeout': 30,
        }])

    def test_health_check_reports_a_wrong_server_path(self):
        client, _ = self._client(FakeResponse(404, {}, text='Not found'))

        with self.assertRaises(ApiError) as ctx:
            client.check_health()

        self.assertIn('health endpoint was not found', str(ctx.exception))
        self.assertIn('SENTINEL_API_URL', str(ctx.exception))

    def test_health_check_explains_invalid_urls_and_network_failures(self):
        client, _ = self._client(requests.exceptions.InvalidURL('missing scheme'))
        with self.assertRaises(ApiError) as invalid_url:
            client.check_health()
        self.assertIn('not a valid URL', str(invalid_url.exception))

        client, _ = self._client(requests.ConnectionError('connection refused'))
        with self.assertRaises(ApiError) as offline:
            client.check_health()
        self.assertIn('internet connection', str(offline.exception))


if __name__ == '__main__':
    unittest.main()
