"""Source-contract regression for genuine CF7 browser/HTTP acceptance.

These tests assert wiring only. Actual browser/network behavior requires GitHub
Actions WordPress + Playwright execution; do not report source checks as E2E PASS.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class Cf7BrowserGateContract(unittest.TestCase):
    def test_browser_runner_is_invoked_while_real_plugin_active(self):
        sh = (ROOT / "bin/test-cf7-header.sh").read_text()
        self.assertIn("tests/e2e/cf7-browser.mjs", sh)
        self.assertIn("CF7_BROWSER_PAGE_ID", sh)
        self.assertIn("CF7_BROWSER_FORM_ID", sh)
        self.assertLess(sh.index("plugin activate contact-form-7"), sh.index("tests/e2e/cf7-browser.mjs"))

    def test_browser_uses_live_http_and_both_feedback_outcomes(self):
        js = (ROOT / "tests/e2e/cf7-browser.mjs").read_text()
        self.assertIn("chromium.launch", js)
        self.assertIn("page.goto(", js)
        self.assertIn("waitForResponse(", js)
        self.assertIn("validation_failed", js)
        self.assertIn("mail_sent", js)
        self.assertIn("CF7_BROWSER_HTTP_AJAX_PASS", js)
        self.assertNotIn("page.setContent(", js)

    def test_http_mail_suppression_is_server_side(self):
        sh = (ROOT / "bin/test-cf7-header.sh").read_text()
        self.assertIn("wpcf7_skip_mail", sh)
        self.assertIn("pre_wp_mail", sh)
        self.assertIn("mu-plugins", sh)

    def test_disposable_cleanup_and_ci_wiring(self):
        sh = (ROOT / "bin/test-cf7-header.sh").read_text()
        workflow = (ROOT / ".github/workflows/ci.yml").read_text()
        self.assertIn("wp post delete", sh)
        self.assertIn("CF7_BROWSER_FIXTURE_CLEANUP", sh)
        self.assertIn("CF7 browser/HTTP source-contract regression", workflow)


if __name__ == "__main__":
    unittest.main()
