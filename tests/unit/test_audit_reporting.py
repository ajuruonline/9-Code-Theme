"""Regression for PHPCS advisory evidence visibility and tool-failure separation."""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class AuditReportingTests(unittest.TestCase):
    def run_case(self, mode):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / 'bin').mkdir()
            (root / 'vendor' / 'bin').mkdir(parents=True)
            shutil.copy2(ROOT / 'bin' / 'audit.sh', root / 'bin' / 'audit.sh')
            stub = root / 'vendor' / 'bin' / 'phpcs'
            stub.write_text('''#!/bin/sh
case "$*" in
  *--config-set*) exit 0 ;;
  *PHPCompatibility.FunctionUse*)
    if [ "$AUDIT_TEST_MODE" = compat_failure ]; then echo 'FOUND 1 ERROR'; exit 1; fi
    exit 0 ;;
  *WordPress.Security.EscapeOutput*)
    if [ "$AUDIT_TEST_MODE" = tool_failure ]; then echo 'Unexpected tool exception'; exit 2; fi
    if [ "$AUDIT_TEST_MODE" = clean ]; then exit 0; fi
    echo 'FILE: theme/example.php'
    echo 'FOUND 1 ERROR AFFECTING 1 LINE'
    echo ' 12 | ERROR | Unescaped output (WordPress.Security.EscapeOutput.OutputNotEscaped)'
    echo 'line 4 detail'
    echo 'line 5 detail'
    echo 'line 6 detail'
    echo 'line 7 detail'
    echo 'line 8 detail'
    echo 'line 9 detail'
    exit 1 ;;
esac
exit 99
''')
            stub.chmod(0o755)
            env = dict(os.environ, AUDIT_TEST_MODE=mode)
            return subprocess.run(['bash', str(root / 'bin' / 'audit.sh')], cwd=root,
                                  capture_output=True, text=True, env=env)

    def test_advisory_preserves_file_line_and_sniff(self):
        p = self.run_case('advisory')
        self.assertEqual(p.returncode, 0, p.stdout + p.stderr)
        for token in ('theme/example.php', '12 | ERROR', 'WordPress.Security.EscapeOutput', 'AUDIT ADVISORY FINDINGS'):
            self.assertIn(token, p.stdout)
        self.assertNotIn('AUDIT OK', p.stdout)

    def test_clean_is_distinct_from_advisory(self):
        p = self.run_case('clean')
        self.assertEqual(p.returncode, 0, p.stdout + p.stderr)
        self.assertIn('AUDIT CLEAN', p.stdout)
        self.assertNotIn('AUDIT ADVISORY FINDINGS', p.stdout)

    def test_tool_failure_blocks_ci(self):
        p = self.run_case('tool_failure')
        self.assertNotEqual(p.returncode, 0)
        self.assertIn('AUDIT TOOL FAILURE', p.stderr)

    def test_compatibility_failure_blocks_ci(self):
        p = self.run_case('compat_failure')
        self.assertNotEqual(p.returncode, 0)
        self.assertIn('AUDIT FAILED', p.stdout)


if __name__ == '__main__':
    unittest.main()
