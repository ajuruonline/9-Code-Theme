"""Regression: a pinned third-party plugin must be byte-verified before install."""
import hashlib
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
VERIFY = ROOT / 'bin' / 'verify-archive-sha256.sh'
INSTALL = ROOT / 'bin' / 'test-cf7-header.sh'
EXPECTED = '4766cbc9b433128e43a8af9af8d97b3a7106273e743a19cd645bff5ed2141261'

class PinnedArchiveTests(unittest.TestCase):
    def run_verifier(self, data, expected):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / 'fixture.zip'
            path.write_bytes(data)
            return subprocess.run(['bash', str(VERIFY), str(path), expected],
                                  capture_output=True, text=True, timeout=10)

    def test_valid_archive_hash_accepted(self):
        data = b'isolated regression fixture (not real plugin)'
        result = self.run_verifier(data, hashlib.sha256(data).hexdigest())
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('ARCHIVE_SHA256_VERIFIED=', result.stdout)

    def test_changed_archive_hash_rejected(self):
        data = b'isolated regression fixture (not real plugin)'
        result = self.run_verifier(data + b'tampered', hashlib.sha256(data).hexdigest())
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('ARCHIVE_SHA256_VERIFIED=', result.stdout)
        self.assertIn('ARCHIVE_SHA256_MISMATCH', result.stderr)

    def test_invalid_digest_rejected(self):
        result = self.run_verifier(b'fixture', 'not-a-sha256')
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('ARCHIVE_SHA256_INVALID_EXPECTED', result.stderr)

    def test_production_installer_enforces_pinned_digest_before_unzip(self):
        source = INSTALL.read_text(encoding='utf-8')
        self.assertIn(EXPECTED, source)
        self.assertRegex(source, r'(?m)^\s*bash\s+"\$ROOT/bin/verify-archive-sha256\.sh"')
        verify_at = source.index('bash "$ROOT/bin/verify-archive-sha256.sh"')
        self.assertLess(verify_at, source.index('unzip -tqq'))
        self.assertLess(verify_at, source.index('plugin activate contact-form-7'))
        self.assertNotIn('CF7_EXPECTED_SHA256:-', source)

if __name__ == '__main__':
    unittest.main()
