import unittest
from unittest import mock

import psutil

import cpu_sampling


class FakeProc:
    """Stand-in for psutil.Process that replays fixed cpu_percent() readings."""

    def __init__(self, pid, name='proc', readings=(0.0, 73.5)):
        self.info = {'pid': pid, 'name': name, 'cpu_percent': 0.0}
        self._readings = list(readings)
        self.calls = 0

    def cpu_percent(self, interval=None):
        value = self._readings[min(self.calls, len(self._readings) - 1)]
        self.calls += 1
        return value


class SampleProcessesTests(unittest.TestCase):
    def test_second_reading_replaces_the_zero_first_sample(self):
        proc = FakeProc(pid=1234)

        with mock.patch.object(cpu_sampling.psutil, 'process_iter', return_value=[proc]), \
                mock.patch.object(cpu_sampling.time, 'sleep') as sleeper:
            sampled = cpu_sampling.sample_processes(['pid', 'name', 'cpu_percent'])

        sleeper.assert_called_once_with(cpu_sampling.SAMPLE_INTERVAL_SECS)
        self.assertEqual(proc.calls, 2)
        self.assertEqual(sampled[0].info['cpu_percent'], 73.5)

    def test_system_idle_process_is_excluded(self):
        idle = FakeProc(pid=0, name='System Idle Process')
        busy = FakeProc(pid=99, name='busy')

        with mock.patch.object(cpu_sampling.psutil, 'process_iter', return_value=[idle, busy]), \
                mock.patch.object(cpu_sampling.time, 'sleep'):
            sampled = cpu_sampling.sample_processes(['pid', 'name'])

        self.assertEqual([proc.info['pid'] for proc in sampled], [99])

    def test_process_that_dies_mid_scan_is_reported_as_idle_not_raised(self):
        dead = FakeProc(pid=7)
        dead.cpu_percent = mock.Mock(side_effect=psutil.NoSuchProcess(7))

        with mock.patch.object(cpu_sampling.psutil, 'process_iter', return_value=[dead]), \
                mock.patch.object(cpu_sampling.time, 'sleep'):
            sampled = cpu_sampling.sample_processes(['pid'])

        self.assertEqual(sampled[0].info['cpu_percent'], 0.0)


if __name__ == '__main__':
    unittest.main()
