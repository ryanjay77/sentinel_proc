import os
import tempfile
import unittest
from unittest import mock

import agent_config

CONNECTION_KEYS = [
    'SENTINEL_DB_HOST',
    'SENTINEL_DB_PORT',
    'SENTINEL_DB_USER',
    'SENTINEL_DB_PASSWORD',
    'SENTINEL_DB_NAME',
]


class DbConfigTests(unittest.TestCase):
    def test_reads_connection_settings_from_the_environment(self):
        env = {
            'SENTINEL_DB_HOST': 'db.example.test',
            'SENTINEL_DB_PORT': '3307',
            'SENTINEL_DB_USER': 'someone',
            'SENTINEL_DB_PASSWORD': 'hunter2',
            'SENTINEL_DB_NAME': 'somewhere',
        }

        with mock.patch.dict(os.environ, env):
            self.assertEqual(agent_config.db_config(), {
                'host': 'db.example.test',
                'port': 3307,
                'user': 'someone',
                'password': 'hunter2',
                'database': 'somewhere',
            })

    def test_credentials_have_no_guessed_default(self):
        with mock.patch.dict(os.environ, {}, clear=False):
            for key in CONNECTION_KEYS:
                os.environ.pop(key, None)

            config = agent_config.db_config()

        self.assertEqual(config['user'], '')
        self.assertEqual(config['password'], '')
        # Connection details that are not secret keep a default.
        self.assertEqual(config['host'], '127.0.0.1')
        self.assertEqual(config['port'], 3306)
        self.assertEqual(config['database'], 'sentinel_proc')


class VtApiKeyTests(unittest.TestCase):
    def test_key_is_empty_until_set(self):
        with mock.patch.dict(os.environ, {}, clear=False):
            os.environ.pop('VT_API_KEY', None)
            self.assertEqual(agent_config.vt_api_key(), '')

            os.environ['VT_API_KEY'] = 'abc123'
            self.assertEqual(agent_config.vt_api_key(), 'abc123')


class TransportTests(unittest.TestCase):
    def test_transport_defaults_to_local(self):
        with mock.patch.dict(os.environ, {}, clear=False):
            os.environ.pop('SENTINEL_TRANSPORT', None)
            self.assertEqual(agent_config.transport(), 'local')

    def test_transport_is_normalized(self):
        with mock.patch.dict(os.environ, {'SENTINEL_TRANSPORT': '  API '}):
            self.assertEqual(agent_config.transport(), 'api')

    def test_api_settings_default_to_empty(self):
        with mock.patch.dict(os.environ, {}, clear=False):
            for key in ('SENTINEL_API_URL', 'SENTINEL_API_TOKEN'):
                os.environ.pop(key, None)
            self.assertEqual(agent_config.api_url(), '')
            self.assertEqual(agent_config.api_token(), '')

    def test_api_url_strips_trailing_slashes(self):
        with mock.patch.dict(os.environ, {
            'SENTINEL_API_URL': 'https://sentinel.example.test///',
            'SENTINEL_API_TOKEN': '  tok-123  ',
        }):
            self.assertEqual(
                agent_config.api_url(), 'https://sentinel.example.test'
            )
            self.assertEqual(agent_config.api_token(), 'tok-123')

    def test_http_transport_is_disabled_unless_explicitly_allowed(self):
        with mock.patch.dict(os.environ, {}, clear=False):
            os.environ.pop('SENTINEL_ALLOW_HTTP', None)
            self.assertFalse(agent_config.api_allow_http())

        with mock.patch.dict(os.environ, {'SENTINEL_ALLOW_HTTP': ' true '}):
            self.assertTrue(agent_config.api_allow_http())


class LoadEnvFileTests(unittest.TestCase):
    def _write_env_file(self, contents):
        handle = tempfile.NamedTemporaryFile(
            'w', suffix='.env', delete=False, encoding='utf-8'
        )
        with handle:
            handle.write(contents)
        self.addCleanup(os.unlink, handle.name)
        return handle.name

    def _clean_env(self, *keys):
        for key in keys:
            os.environ.pop(key, None)
            self.addCleanup(os.environ.pop, key, None)

    def test_parses_values_and_skips_comments_and_blanks(self):
        path = self._write_env_file(
            '# a comment\n'
            '\n'
            'SENTINEL_TEST_ALPHA=one\n'
            'SENTINEL_TEST_BETA="two words"\n'
            'SENTINEL_TEST_GAMMA=\n'
            '# SENTINEL_TEST_DELTA=commented-out\n'
        )
        self._clean_env(
            'SENTINEL_TEST_ALPHA', 'SENTINEL_TEST_BETA',
            'SENTINEL_TEST_GAMMA', 'SENTINEL_TEST_DELTA',
        )

        agent_config.load_env_file(path)

        self.assertEqual(os.environ.get('SENTINEL_TEST_ALPHA'), 'one')
        self.assertEqual(os.environ.get('SENTINEL_TEST_BETA'), 'two words')
        self.assertEqual(os.environ.get('SENTINEL_TEST_GAMMA'), '')
        self.assertNotIn('SENTINEL_TEST_DELTA', os.environ)

    def test_existing_environment_variables_win(self):
        path = self._write_env_file('SENTINEL_TEST_WIN=from_file\n')

        with mock.patch.dict(os.environ, {'SENTINEL_TEST_WIN': 'from_env'}):
            agent_config.load_env_file(path)
            self.assertEqual(os.environ['SENTINEL_TEST_WIN'], 'from_env')

    def test_missing_file_is_not_an_error(self):
        missing = os.path.join(
            tempfile.gettempdir(), 'sentinel-no-such-env-file.env'
        )
        self.addCleanup(lambda: os.path.exists(missing) and os.unlink(missing))

        agent_config.load_env_file(missing)  # must not raise


if __name__ == '__main__':
    unittest.main()
