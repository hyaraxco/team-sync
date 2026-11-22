import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('release', Path(__file__).with_name('release.py'))
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)
SHA = 'a' * 40


class ReleaseTest(unittest.TestCase):
    def runs(self):
        return [dict(path=f'.github/workflows/{name}-tests.yml', head_sha=SHA,
                     head_branch='main', event='push', run_number=1,
                     status='completed', conclusion='success') for name in ['be', 'fe']]

    def test_valid_explicit_release(self):
        release.validate('v0.20.0', SHA, ['v0.19.12', 'v99.0.0-rc.1'], self.runs())

    def test_invalid_versions_and_sha(self):
        for version in ['v01.0.0', 'v1.0', 'v1.0.0-rc.1', '$(touch bad)']:
            with self.subTest(version=version), self.assertRaises(ValueError):
                release.validate(version, SHA, [], self.runs())
        with self.assertRaises(ValueError):
            release.validate('v1.0.0', '--help', [], self.runs())

    def test_existing_or_lower_version(self):
        for version in ['v0.19.12', 'v0.19.11']:
            with self.subTest(version=version), self.assertRaises(ValueError):
                release.validate(version, SHA, ['v0.19.12'], self.runs())

    def test_missing_pending_failed_wrong_sha_or_pr_checks(self):
        cases = [[], self.runs()[:1]]
        for field, value in [('status', 'in_progress'), ('conclusion', 'failure'),
                             ('conclusion', 'skipped'), ('head_sha', 'b' * 40),
                             ('event', 'pull_request'), ('head_branch', 'other')]:
            runs = self.runs()
            runs[0][field] = value
            cases.append(runs)
        for runs in cases:
            with self.subTest(runs=runs), self.assertRaises(ValueError):
                release.validate('v1.0.0', SHA, [], runs)

    def test_new_failed_run_overrides_old_success(self):
        runs = self.runs()
        runs.append(dict(runs[0], run_number=2, conclusion='failure'))
        with self.assertRaises(ValueError):
            release.validate('v1.0.0', SHA, [], runs)


if __name__ == '__main__':
    unittest.main()
