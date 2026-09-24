import unittest

import live_monitor
import risk_scoring
import virus_total

STAGING_PATHS = [
    r'C:\Users\a\AppData\Local\Temp\x.exe',
    r'C:\Users\a\AppData\Local\Tmp\x.exe',
    r'C:\Users\a\Downloads\x.exe',
]

BENIGN_PATHS = [
    r'C:\Program Files\App\app.exe',
    r'C:\tools\templates\x.exe',
    r'C:\work\attempts\x.exe',
]


class SuspiciousPathTests(unittest.TestCase):
    """All three agent modules must agree on what counts as a staging folder.

    They previously disagreed: live_monitor and virus_total compared whole path
    segments while risk_scoring searched for substrings, so a folder called
    "templates" or "attempts" scored points as a Temp directory.
    """

    def test_staging_directories_are_flagged_everywhere(self):
        for path in STAGING_PATHS:
            with self.subTest(path=path):
                self.assertTrue(live_monitor.is_suspicious_path(path))
                self.assertTrue(risk_scoring.is_suspicious_path(path))
                self.assertTrue(virus_total.is_suspicious_path(path))

    def test_folders_merely_containing_a_keyword_are_ignored_everywhere(self):
        for path in BENIGN_PATHS:
            with self.subTest(path=path):
                self.assertFalse(live_monitor.is_suspicious_path(path))
                self.assertFalse(risk_scoring.is_suspicious_path(path))
                self.assertFalse(virus_total.is_suspicious_path(path))

    def test_empty_path_is_not_flagged_everywhere(self):
        self.assertFalse(live_monitor.is_suspicious_path(''))
        self.assertFalse(risk_scoring.is_suspicious_path(''))
        self.assertFalse(virus_total.is_suspicious_path(''))

    def test_command_line_arguments_are_matched_by_segment(self):
        staging = [
            r'C:\Windows\System32\cmd.exe',
            '/c',
            r'C:\Users\a\AppData\Local\Temp\payload.exe',
        ]
        benign = [
            r'C:\Windows\System32\cmd.exe',
            '/c',
            r'C:\tools\templates\payload.exe',
        ]

        self.assertTrue(risk_scoring.is_suspicious_path(staging[0], staging))
        self.assertTrue(virus_total.is_suspicious_path(staging[0], staging))
        self.assertFalse(risk_scoring.is_suspicious_path(benign[0], benign))
        self.assertFalse(virus_total.is_suspicious_path(benign[0], benign))


if __name__ == '__main__':
    unittest.main()
