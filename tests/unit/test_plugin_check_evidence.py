"""Regression: Plugin Check's approved private-updater exception is never labelled clean."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class PluginCheckEvidenceTests(unittest.TestCase):
    def run_case(self, rows):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / "bin").mkdir()
            (root / "site").mkdir()
            (root / "stub").mkdir()
            shutil.copy2(ROOT / "bin" / "plugin-check.sh", root / "bin" / "plugin-check.sh")
            shutil.copy2(ROOT / "bin" / "pc-summary.py", root / "bin" / "pc-summary.py")
            report = "" if rows is None else (
                "FILE: /plugin/nine-code-data/includes/updater.php\n"
                + json.dumps(rows) + "\n"
            )
            stub = root / "stub" / "php"
            stub.write_text(
                "#!/bin/sh\n"
                "case \" $* \" in\n"
                "  *\" plugin check nine-code-data \"*) printf '%s\\n' \"$MOCK_PLUGIN_REPORT\" ;;\n"
                "esac\n"
                "exit 0\n"
            )
            stub.chmod(0o755)
            env = os.environ.copy()
            env["PATH"] = str(root / "stub") + os.pathsep + env.get("PATH", "")
            env["WP_TEST_DIR"] = str(root / "site")
            env["MOCK_PLUGIN_REPORT"] = report
            return subprocess.run(
                ["bash", str(root / "bin" / "plugin-check.sh")],
                cwd=root, env=env, text=True, capture_output=True, timeout=20
            )

    def test_clean_report_is_explicitly_clean(self):
        result = self.run_case(None)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn("PLUGIN CHECK CLEAN", result.stdout)

    def test_allowed_updater_error_is_explicit_advisory(self):
        result = self.run_case([
            {"type": "ERROR", "code": "plugin_updater_detected",
             "line": 17, "message": "Private updater"}
        ])
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn("PLUGIN CHECK ADVISORY: 1 documented exception", result.stdout)
        self.assertNotIn("PLUGIN CHECK OK", result.stdout)

    def test_unexpected_error_blocks(self):
        result = self.run_case([
            {"type": "ERROR", "code": "unexpected_security_error",
             "line": 24, "message": "Needs review"}
        ])
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("PLUGIN CHECK FAILED: 1 unexpected error", result.stdout)

    def test_mixed_exception_and_unexpected_error_blocks(self):
        result = self.run_case([
            {"type": "ERROR", "code": "plugin_updater_detected",
             "line": 17, "message": "Private updater"},
            {"type": "ERROR", "code": "unexpected_security_error",
             "line": 24, "message": "Needs review"}
        ])
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("PLUGIN CHECK FAILED: 1 unexpected error", result.stdout)
        self.assertIn("1 documented exception", result.stdout)


if __name__ == "__main__":
    unittest.main()
