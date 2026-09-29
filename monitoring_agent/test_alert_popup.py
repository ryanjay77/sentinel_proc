import unittest
from unittest import mock

import alert_popup


class AlertPopupTests(unittest.TestCase):
    def setUp(self):
        with alert_popup._notified_lock:
            alert_popup._notified_hashes.clear()

    def tearDown(self):
        with alert_popup._notified_lock:
            alert_popup._notified_hashes.clear()

    def test_non_windows_never_starts_a_popup(self):
        with mock.patch.object(alert_popup.platform, 'system', return_value='Linux'), \
                mock.patch.object(alert_popup.threading, 'Thread') as thread:
            self.assertFalse(alert_popup.notify_risky_process({
                'name': 'app.exe', 'hash': 'a' * 64, 'risk_level': 'high',
            }))
        thread.assert_not_called()

    def test_only_medium_and_high_hashes_get_one_background_popup(self):
        with mock.patch.object(alert_popup.platform, 'system', return_value='Windows'), \
                mock.patch.object(alert_popup.threading, 'Thread') as thread:
            self.assertFalse(alert_popup.notify_risky_process({
                'name': 'safe.exe', 'hash': 'a' * 64, 'risk_level': 'low',
            }))
            self.assertFalse(alert_popup.notify_risky_process({
                'name': 'no-hash.exe', 'hash': None, 'risk_level': 'high',
            }))
            self.assertTrue(alert_popup.notify_risky_process({
                'name': 'check.exe', 'hash': 'B' * 64, 'risk_level': 'medium',
            }))
            self.assertFalse(alert_popup.notify_risky_process({
                'name': 'same-file.exe', 'hash': 'b' * 64, 'risk_level': 'high',
            }))
            self.assertTrue(alert_popup.notify_risky_process({
                'name': 'danger.exe', 'hash': 'c' * 64, 'risk_level': 'high',
            }))

        self.assertEqual(thread.call_count, 2)
        medium_kwargs = thread.call_args_list[0].kwargs
        high_kwargs = thread.call_args_list[1].kwargs
        self.assertIs(medium_kwargs['target'], alert_popup._show_message)
        self.assertFalse(medium_kwargs['daemon'])
        self.assertIn('check whether you started', medium_kwargs['args'][1])
        self.assertNotIn('infected', medium_kwargs['args'][1].lower())
        self.assertIn('Do not open this file again', high_kwargs['args'][1])
        self.assertIn('Save your work', high_kwargs['args'][1])
        self.assertIn('full antivirus scan', high_kwargs['args'][1])
        self.assertIn('report this to your administrator', high_kwargs['args'][1])


if __name__ == '__main__':
    unittest.main()