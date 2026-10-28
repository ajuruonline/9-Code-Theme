"""Evidence integrity: CF7 _wpcf7 is a form identifier, not a security nonce.

These are source-contract checks only; actual WordPress/REST execution is separate.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'tests' / 'e2e' / 'header-cf7-compat.php'


class CF7FormIdEvidenceTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = SOURCE.read_text(encoding='utf-8')

    def test_uses_form_id_evidence_label(self):
        self.assertIn("'CF7_FORM_ID_AND_SUBMIT_PRESERVED'", self.source)

    def test_does_not_call_form_id_a_nonce(self):
        self.assertNotIn("'CF7_NONCE_AND_SUBMIT_PRESERVED'", self.source)

    def test_binds_hidden_form_id_to_created_fixture(self):
        self.assertIn("preg_match('/<input", self.source)
        self.assertIn("(int) $id", self.source)
        self.assertIn("$form_id_input[0]", self.source)

    def test_excludes_rest_authorization_from_claimed_coverage(self):
        self.assertIn('CF7_FORM_ID_ONLY', self.source)
        self.assertIn('REST/AJAX/authorization/backend remain NOT_RUN', self.source)


if __name__ == '__main__':
    unittest.main()
