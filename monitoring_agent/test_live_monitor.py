import unittest
from unittest import mock

import live_monitor


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


if __name__ == '__main__':
    unittest.main()
