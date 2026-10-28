"""Fail-closed source contract for disposable CF7 REST acceptance wiring.

Actual CF7 REST behavior is proven only by the WordPress CI step, not here.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class Cf7RestDispatchContract(unittest.TestCase):
    def test_pinned_disposable_site_guard(self):
        src = (ROOT / 'tests/e2e/header-cf7-rest.php').read_text()
        self.assertIn("'/.wp-test/wp/'", src)
        self.assertIn("WPCF7_VERSION !== '6.2.3'", src)

    def test_five_cases_run_as_separate_cli_processes(self):
        shell = (ROOT / 'bin/test-cf7-header.sh').read_text()
        self.assertIn('for case in valid invalid missing_unit guest_management subscriber_management; do', shell)
        self.assertIn('CF7_REST_CASE="$case"', shell)
        self.assertIn('tests/e2e/header-cf7-rest.php', shell)

    def test_mail_never_delivered_and_cleanup(self):
        src = (ROOT / 'tests/e2e/header-cf7-rest.php').read_text()
        for token in ('wpcf7_skip_mail', 'pre_wp_mail', 'CF7_REST_NO_OUTBOUND_MAIL',
                      'CF7_REST_TEMP_FORM_REMOVED', 'wp_delete_post'):
            self.assertIn(token, src)

    def test_public_feedback_and_protected_management_are_distinct(self):
        src = (ROOT / 'tests/e2e/header-cf7-rest.php').read_text()
        for token in ('CF7_REST_GUEST_VALID_ACCEPTED',
                      'CF7_REST_INVALID_EMAIL_REJECTED',
                      'CF7_REST_MISSING_UNIT_REJECTED',
                      'CF7_REST_GUEST_MANAGEMENT_DENIED'):
            self.assertIn(token, src)
        self.assertIn('HTTP/browser/AJAX NOT_RUN', src)
        # CF7 v6.2.3 uses rest_authorization_required_code(): anonymous = 401.
        self.assertIn("$response->get_status() === 401", src)

    def test_authenticated_subscriber_management_denied_and_cleaned(self):
        # Authenticated users lacking CF7 management capabilities must receive
        # 403 (not the guest's 401). Disposable user must be removed afterwards.
        src = (ROOT / 'tests/e2e/header-cf7-rest.php').read_text()
        shell = (ROOT / 'bin/test-cf7-header.sh').read_text()
        self.assertIn('subscriber_management', shell)
        self.assertIn('subscriber_management', src)
        self.assertIn("'role' => 'subscriber'", src)
        self.assertIn("$response->get_status() === 403", src)
        self.assertIn('CF7_REST_SUBSCRIBER_MANAGEMENT_DENIED', src)
        self.assertIn('CF7_REST_TEMP_USER_REMOVED', src)
        self.assertIn('wp_delete_user', src)


if __name__ == '__main__':
    unittest.main()
