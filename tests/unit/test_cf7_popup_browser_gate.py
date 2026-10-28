"""Failing-first source contract for actual CF7 inside the Alpha header popup.

This only checks fixture wiring. It does not prove real Chromium execution.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class Cf7PopupBrowserContract(unittest.TestCase):
    def test_real_popup_browser_oracle_exists(self):
        js = (ROOT / "tests/e2e/cf7-popup-browser.mjs").read_text()
        for token in ("chromium.launch", "page.goto(", "data-n9lh-popup-open",
                      "data-n9lh-popup", "waitForResponse(", "validation_failed",
                      "mail_sent", "CF7_POPUP_BROWSER_HTTP_PASS"):
            self.assertIn(token, js)
        self.assertIn("width: 320", js)
        self.assertNotIn("page.setContent(", js)

    def test_disposable_header_configuration_is_bounded(self):
        php = (ROOT / "tests/e2e/cf7-popup-config.php").read_text()
        for token in ("/.wp-test/wp/", "localhost", "127.0.0.1",
                      "CF7_BROWSER_FORM_ID", "ELHF_H_Settings::OPTION",
                      "show_form_popup", "form_shortcode", "header_actions"):
            self.assertIn(token, php)

    def test_header_configuration_is_restored_even_on_failure(self):
        sh = (ROOT / "bin/test-cf7-header.sh").read_text()
        restore = (ROOT / "tests/e2e/cf7-popup-restore.php").read_text()
        for token in ("cf7-popup-config.php", "cf7-popup-browser.mjs",
                      "cf7-popup-restore.php", "CF7_BROWSER_SETTINGS_BACKUP"):
            self.assertIn(token, sh)
        self.assertIn("CF7_POPUP_SETTINGS_RESTORED=YES", restore)
        self.assertIn("CF7_BROWSER_FIXTURE_CLEANUP", sh)
        self.assertIn('post update "$CF7_BROWSER_PAGE_ID"', sh)
        self.assertLess(sh.index("tests/e2e/cf7-browser.mjs"), sh.index('post update "$CF7_BROWSER_PAGE_ID"'))
        self.assertLess(sh.index('post update "$CF7_BROWSER_PAGE_ID"'), sh.index("tests/e2e/cf7-popup-config.php"))
        popup = (ROOT / "tests/e2e/cf7-popup-browser.mjs").read_text()
        self.assertIn("CF7_POPUP_DUPLICATE_FORM_INSTANCE", popup)

    def test_ci_invokes_failing_first_contract(self):
        workflow = (ROOT / ".github/workflows/ci.yml").read_text()
        self.assertIn("test_cf7_popup_browser_gate.py", workflow)


if __name__ == "__main__":
    unittest.main()
