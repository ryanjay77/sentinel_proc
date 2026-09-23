import os
import unittest
from unittest import mock

import live_monitor


class LiveMonitorDbConfigTests(unittest.TestCase):
    def setUp(self):
        self.original = {
            'SENTINEL_DB_HOST': os.environ.get('SENTINEL_DB_HOST'),
            'SENTINEL_DB_USER': os.environ.get('SENTINEL_DB_USER'),
            'SENTINEL_DB_PASSWORD': os.environ.get('SENTINEL_DB_PASSWORD'),
            'SENTINEL_DB_NAME': os.environ.get('SENTINEL_DB_NAME'),
            'SENTINEL_DB_PORT': os.environ.get('SENTINEL_DB_PORT'),
        }

    def tearDown(self):
        for key, value in self.original.items():
            if value is None:
                os.environ.pop(key, None)
            else:
                os.environ[key] = value

    def test_should_use_mysql_when_db_settings_are_present(self):
        os.environ['SENTINEL_DB_HOST'] = 'localhost'
        os.environ['SENTINEL_DB_USER'] = 'sentinel_agent'
        os.environ['SENTINEL_DB_PASSWORD'] = 'secret'
        os.environ['SENTINEL_DB_NAME'] = 'sentinel_proc'
        os.environ['SENTINEL_DB_PORT'] = '3306'

        self.assertTrue(live_monitor.should_use_mysql())

    def test_should_use_mysql_when_db_settings_are_missing(self):
        for key in [
            'SENTINEL_DB_HOST',
            'SENTINEL_DB_USER',
            'SENTINEL_DB_PASSWORD',
            'SENTINEL_DB_NAME',
            'SENTINEL_DB_PORT',
        ]:
            os.environ.pop(key, None)

        with mock.patch.object(
            live_monitor,
            'DB_CONFIG',
            {'host': '', 'user': '', 'password': '', 'database': '', 'port': 3306},
        ):
            self.assertFalse(live_monitor.should_use_mysql())


if __name__ == '__main__':
    unittest.main()
