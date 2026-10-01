#!/usr/bin/env bash
# Simulates upgrading a live site from the old 3-package install
# (9Code 15 Theme + 9Core 15 + 9 Data Manager, from git commit ed3fda3) to Nine Code.
# Asserts: no fatals in any intermediate state, old plugins retired, menus + data intact.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export WP_TEST_DIR="${WP_TEST_DIR:-$ROOT/.wp-test-upgrade}" WP_TEST_PORT="${WP_TEST_PORT:-8892}"
DIR="$WP_TEST_DIR"; PORT="$WP_TEST_PORT"; BASE="http://localhost:$PORT"
WP="php $DIR/wp-cli.phar --allow-root --path=$DIR/wp"
fail=0; check() { if eval "$2"; then echo "PASS $1"; else echo "FAIL $1"; fail=1; fi; }
front() { curl -s -o /dev/null -w '%{http_code}' "$BASE/"; }

"$ROOT/bin/setup-test-site.sh" >/dev/null 2>&1
$WP theme activate twentytwentyfive >/dev/null; $WP plugin deactivate nine-code-data >/dev/null
rm -rf "$DIR/legacy"; mkdir -p "$DIR/legacy"
git -C "$ROOT" archive ed3fda3 legacy | tar -x -C "$DIR/legacy"
L="$DIR/legacy/legacy"; C="$DIR/wp/wp-content"
ln -sfn "$L/9code-13-theme" "$C/themes/9code-13-theme"
ln -sfn "$L/nine-code-ultra-core" "$C/plugins/nine-code-ultra-core"
ln -sfn "$L/nine10-data-edition" "$C/plugins/nine10-data-edition"
$WP theme activate 9code-13-theme >/dev/null; $WP plugin activate nine-code-ultra-core nine10-data-edition >/dev/null
"$ROOT/bin/serve-test-site.sh" >/dev/null
: > "$DIR/wp/debug.log"
OLDMENU=$($WP menu list --fields=term_id --format=ids | awk '{print $1}')
$WP menu location assign "$OLDMENU" primary >/dev/null
$WP option update ncu_settings "$($WP option get ncu_settings --format=json | sed 's/"client_brand_name":""/"client_brand_name":"Keep Me"/')" --format=json >/dev/null
FORM=$($WP post create --post_type=nine10_form_def --post_title="Legacy form" --post_status=publish --porcelain)
check "legacy install serves (200)" '[ "$(front)" = 200 ]'

echo "-- step 1: upload + activate Nine Code Data while the old plugins are still active"
$WP plugin activate nine-code-data >/dev/null 2>&1 || true
check "site still up after activating new plugin (200)" '[ "$(front)" = 200 ]'
echo "-- step 2: activate the Nine Code theme"
$WP theme activate nine-code >/dev/null 2>&1 || true
curl -s -o /dev/null "$BASE/wp-admin/" ; $WP eval 'wp_set_current_user(1); do_action("admin_init");' >/dev/null 2>&1 || true
check "site up after theme switch (200)" '[ "$(front)" = 200 ]'
ACTIVE=$($WP plugin list --status=active --field=name)
check "old 9Core plugin deactivated" '! grep -qx nine-code-ultra-core <<<"$ACTIVE"'
check "old 9 Data Manager plugin deactivated" '! grep -qx nine10-data-edition <<<"$ACTIVE"'
check "Nine Code Data active" 'grep -qx nine-code-data <<<"$ACTIVE"'
check "Nine Code theme active" '[ "$($WP theme list --status=active --field=name)" = nine-code ]'
check "primary menu location carried over" '[ "$($WP eval "echo (int) (get_nav_menu_locations()[\"primary\"] ?? 0);")" = "$OLDMENU" ]'
check "Core settings preserved (client_brand_name)" '[ "$($WP eval "echo ncu_get_settings()[\"client_brand_name\"];")" = "Keep Me" ]'
check "legacy form post type + data intact" '[ "$($WP post get "$FORM" --field=post_title)" = "Legacy form" ]'
check "Post Editor admin URL does not fatal (500)" '[ "$(curl -s -o /dev/null -w %{http_code} -b /dev/null "$BASE/wp-admin/admin.php?page=nine-post-manager")" != 500 ]'
check "no PHP fatals logged" '! grep -E "PHP Fatal|Cannot redeclare" "$DIR/wp/debug.log" >/dev/null'
$WP plugin delete nine-code-ultra-core nine10-data-edition >/dev/null 2>&1 || true
check "site up after deleting old plugins (200)" '[ "$(front)" = 200 ]'
[ $fail -eq 0 ] && echo "UPGRADE OK" || { echo "UPGRADE FAILED"; grep -E "Fatal" "$DIR/wp/debug.log" | head -5; exit 1; }
