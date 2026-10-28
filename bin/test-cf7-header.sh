#!/usr/bin/env bash
# Real, pinned Contact Form 7 smoke test on the disposable WordPress CI site.
# No production install, no outbound mail, no real user submissions.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="${WP_TEST_DIR:-$ROOT/.wp-test}"
WP=(php "$DIR/wp-cli.phar" --allow-root "--path=$DIR/wp")
if [[ ! -f "$DIR/wp-config.php" && ! -f "$DIR/wp/wp-config.php" ]]; then
  echo "CF7_TEST_REFUSED: disposable WordPress site is missing." >&2
  exit 2
fi
TMP="$(mktemp -d)"
# These IDs are created only on the disposable .wp-test WordPress instance.
CF7_BROWSER_FORM_ID=""
CF7_BROWSER_PAGE_ID=""
CF7_BROWSER_MAIL_GUARD="$DIR/wp/wp-content/mu-plugins/n9eh-cf7-browser-no-mail.php"
CF7_BROWSER_SETTINGS_BACKUP="$TMP/n9lh-settings.json"
export CF7_BROWSER_SETTINGS_BACKUP
cleanup() {
  local previous_status=$?
  trap - EXIT
  local cleanup_ok=1
  if [[ "$CF7_BROWSER_PAGE_ID" =~ ^[0-9]+$ ]]; then
    "${WP[@]}" post delete "$CF7_BROWSER_PAGE_ID" --force >/dev/null 2>&1 || cleanup_ok=0
  fi
  if [[ "$CF7_BROWSER_FORM_ID" =~ ^[0-9]+$ ]]; then
    "${WP[@]}" post delete "$CF7_BROWSER_FORM_ID" --force >/dev/null 2>&1 || cleanup_ok=0
  fi
  if [[ -f "$CF7_BROWSER_SETTINGS_BACKUP" ]]; then
    "${WP[@]}" eval-file "$ROOT/tests/e2e/cf7-popup-restore.php" || cleanup_ok=0
  fi
  rm -f "$CF7_BROWSER_MAIL_GUARD" || cleanup_ok=0
  "${WP[@]}" plugin deactivate contact-form-7 >/dev/null 2>&1 || cleanup_ok=0
  rm -rf "$TMP"
  if (( cleanup_ok == 1 )); then
    echo "CF7_BROWSER_FIXTURE_CLEANUP=YES"
  else
    echo "CF7_BROWSER_FIXTURE_CLEANUP=NO" >&2
    previous_status=1
  fi
  exit "$previous_status"
}
trap cleanup EXIT
curl -fsSL --retry 2 --connect-timeout 15 \
  -o "$TMP/contact-form-7.6.2.3.zip" \
  https://downloads.wordpress.org/plugin/contact-form-7.6.2.3.zip
# Pin actual bytes, not merely the version string or ZIP container validity.
# SHA-256 independently observed in both 27 Oct 2026 CI workflows.
CF7_EXPECTED_SHA256="4766cbc9b433128e43a8af9af8d97b3a7106273e743a19cd645bff5ed2141261"
bash "$ROOT/bin/verify-archive-sha256.sh" "$TMP/contact-form-7.6.2.3.zip" "$CF7_EXPECTED_SHA256"
unzip -tqq "$TMP/contact-form-7.6.2.3.zip"
echo "CF7_ARCHIVE_SHA256=$(sha256sum "$TMP/contact-form-7.6.2.3.zip" | awk '{print $1}')"
unzip -q -o "$TMP/contact-form-7.6.2.3.zip" -d "$DIR/wp/wp-content/plugins/"
"${WP[@]}" plugin activate contact-form-7 >/dev/null
"${WP[@]}" eval-file "$ROOT/tests/e2e/header-cf7-compat.php"
# Each REST case is a separate PHP process: CF7 uses a submission singleton.
# This is in-process REST dispatch, not a browser/HTTP/AJAX certification.
for case in valid invalid missing_unit guest_management subscriber_management; do
  CF7_REST_CASE="$case" "${WP[@]}" eval-file "$ROOT/tests/e2e/header-cf7-rest.php"
done

# Actual browser -> HTTP multipart -> real CF7 REST response, not WP-CLI rest_do_request.
# Refuse non-local/non-disposable sites. Never use this fixture on production.
if [[ "$DIR" != */.wp-test || ! -f "$DIR/wp/wp-content/mu-plugins/test-env.php" ]]; then
  echo "CF7_BROWSER_REFUSED: missing disposable .wp-test marker" >&2
  exit 2
fi
CF7_BROWSER_BASE_URL=$("${WP[@]}" option get home)
case "$CF7_BROWSER_BASE_URL" in
  http://localhost:*|http://127.0.0.1:*) ;;
  *) echo "CF7_BROWSER_REFUSED: site is not local" >&2; exit 2 ;;
esac
cat > "$CF7_BROWSER_MAIL_GUARD" <<'PHP'
<?php
// Disposable local-only guard: suppress every outgoing mail attempt.
if (!defined('ABSPATH') || strpos(wp_normalize_path(ABSPATH), '/.wp-test/wp/') === false
    || !in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    return;
}
add_filter('wpcf7_skip_mail', '__return_true', PHP_INT_MAX);
add_filter('wpcf7_skip_spam_check', '__return_true', PHP_INT_MAX);
add_filter('pre_wp_mail', static function ($preempt) { return true; }, PHP_INT_MAX);
// Browser client accepts this syntactically valid address; the real CF7
// server validator rejects it so HTTP validation is tested deterministically.
add_filter('wpcf7_validate_email*', static function ($result, $tag) {
    $value = isset($_POST['your-email']) ? sanitize_email(wp_unslash($_POST['your-email'])) : '';
    if ($tag->name === 'your-email' && $value === 'reject@example.test') {
        $result->invalidate($tag, 'Disposable server-side email rejection.');
    }
    return $result;
}, 20, 2);
PHP
CF7_BROWSER_FORM_ID=$("${WP[@]}" post create --post_type=wpcf7_contact_form --post_status=publish \
  --post_title="Disposable 9EH CF7 browser form" --porcelain)
if [[ ! "$CF7_BROWSER_FORM_ID" =~ ^[0-9]+$ ]]; then
  echo "CF7_BROWSER_FAILED: could not create temporary form" >&2; exit 1
fi
"${WP[@]}" post meta update "$CF7_BROWSER_FORM_ID" _form \
  '[text* your-name] [email* your-email] [textarea your-message] [submit "Send"]' >/dev/null
CF7_BROWSER_PAGE_ID=$("${WP[@]}" post create --post_type=page --post_status=publish \
  --post_title="Disposable 9EH CF7 browser page" \
  --post_content="[contact-form-7 id=\"$CF7_BROWSER_FORM_ID\"]" --porcelain)
if [[ ! "$CF7_BROWSER_PAGE_ID" =~ ^[0-9]+$ ]]; then
  echo "CF7_BROWSER_FAILED: could not create temporary page" >&2; exit 1
fi
export CF7_BROWSER_FORM_ID CF7_BROWSER_PAGE_ID CF7_BROWSER_BASE_URL
echo "CF7_BROWSER_MAIL_SUPPRESSION_GUARD=INSTALLED"
node "$ROOT/tests/e2e/cf7-browser.mjs"
# Isolate popup integration from the native-page shortcode: never render the
# same CF7 form twice on the page and mistake duplicate-instance behavior for
# an Alpha popup regression. The disposable page is deleted in the EXIT trap.
"${WP[@]}" post update "$CF7_BROWSER_PAGE_ID" --post_content="Disposable Alpha popup integration only" >/dev/null
# Exercise actual Alpha popup with temporary settings, restored in EXIT trap.
"${WP[@]}" eval-file "$ROOT/tests/e2e/cf7-popup-config.php"
node "$ROOT/tests/e2e/cf7-popup-browser.mjs"
