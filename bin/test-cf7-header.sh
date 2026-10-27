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
cleanup() {
  "${WP[@]}" plugin deactivate contact-form-7 >/dev/null 2>&1 || true
  rm -rf "$TMP"
}
trap cleanup EXIT
curl -fsSL --retry 2 --connect-timeout 15 \
  -o "$TMP/contact-form-7.6.2.3.zip" \
  https://downloads.wordpress.org/plugin/contact-form-7.6.2.3.zip
unzip -tqq "$TMP/contact-form-7.6.2.3.zip"
echo "CF7_ARCHIVE_SHA256=$(sha256sum "$TMP/contact-form-7.6.2.3.zip" | awk '{print $1}')"
unzip -q -o "$TMP/contact-form-7.6.2.3.zip" -d "$DIR/wp/wp-content/plugins/"
"${WP[@]}" plugin activate contact-form-7 >/dev/null
"${WP[@]}" eval-file "$ROOT/tests/e2e/header-cf7-compat.php"
