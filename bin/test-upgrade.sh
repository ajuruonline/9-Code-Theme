#!/usr/bin/env bash
# Simulates upgrading a live site through every earlier install to Nine Code 18:
#   1. the old 3-package install (9Code 15 Theme + 9Core 15 + 9 Data Manager, from git commit ed3fda3)
#   2. the separate "Nine Code Data" plugin 17.1.0 (from git commit 6e6708b, the last commit that shipped it)
#   3. the Nine Code theme 18.0.0, which bundles Data Manager
# Asserts: no fatals in any intermediate state, old plugins retired, Data Manager running from the theme,
# menus, settings and data intact.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export WP_TEST_DIR="${WP_TEST_DIR:-$ROOT/.wp-test-upgrade}" WP_TEST_PORT="${WP_TEST_PORT:-8892}"
DIR="$WP_TEST_DIR"; PORT="$WP_TEST_PORT"; BASE="http://localhost:$PORT"
WP="php $DIR/wp-cli.phar --allow-root --path=$DIR/wp"
fail=0; check() { if eval "$2"; then echo "PASS $1"; else echo "FAIL $1"; fail=1; fi; }
front() { curl -s -o /dev/null -w '%{http_code}' "$BASE/"; }

"$ROOT/bin/setup-test-site.sh" >/dev/null 2>&1
$WP theme activate twentytwentyfive >/dev/null; $WP plugin deactivate nine-code-data >/dev/null 2>&1 || true
rm -rf "$DIR/legacy"; mkdir -p "$DIR/legacy"
git -C "$ROOT" archive ed3fda3 legacy | tar -x -C "$DIR/legacy"
L="$DIR/legacy/legacy"; C="$DIR/wp/wp-content"
ln -sfn "$L/9code-13-theme" "$C/themes/9code-13-theme"
ln -sfn "$L/nine-code-ultra-core" "$C/plugins/nine-code-ultra-core"
ln -sfn "$L/nine10-data-edition" "$C/plugins/nine10-data-edition"
$WP theme activate 9code-13-theme >/dev/null; $WP plugin activate nine-code-ultra-core nine10-data-edition >/dev/null
# The separate Nine Code Data plugin as it shipped in 17.1.0. Nothing in the repository ships it any more.
git -C "$ROOT" archive "${PLUGIN_17_REF:-6e6708b}" plugin/nine-code-data | tar -x -C "$DIR/legacy"
ln -sfn "$DIR/legacy/plugin/nine-code-data" "$C/plugins/nine-code-data"
"$ROOT/bin/serve-test-site.sh" >/dev/null
: > "$DIR/wp/debug.log"
OLDMENU=$($WP menu list --fields=term_id --format=ids | awk '{print $1}')
$WP menu location assign "$OLDMENU" primary >/dev/null
$WP option update ncu_settings "$($WP option get ncu_settings --format=json | sed 's/"client_brand_name":""/"client_brand_name":"Keep Me"/')" --format=json >/dev/null
FORM=$($WP post create --post_type=nine10_form_def --post_title="Legacy form" --post_status=publish --porcelain)
check "legacy install serves (200)" '[ "$(front)" = 200 ]'

echo "-- step 1: activate the separate Nine Code Data 17.1.0 plugin while the old plugins are still active"
$WP plugin activate nine-code-data >/dev/null 2>&1 || true
check "site still up after activating the separate plugin (200)" '[ "$(front)" = 200 ]'
check "separate Nine Code Data 17.1.0 plugin is active before the switch" '[ "$($WP plugin list --status=active --field=name | grep -cx nine-code-data)" = 1 ]'
echo "-- step 2: activate the Nine Code 18 theme, which bundles Data Manager"
$WP theme activate nine-code >/dev/null 2>&1 || true
# The switch request still loads the plugin, so the bundled copy steps aside. The next admin request
# retires the plugins; the request after that runs the bundled Data Manager install.
$WP eval 'wp_set_current_user(1); do_action("admin_init");' >/dev/null 2>&1 || true
$WP eval 'wp_set_current_user(1); do_action("admin_init");' >/dev/null 2>&1 || true
check "site up after theme switch (200)" '[ "$(front)" = 200 ]'
ACTIVE=$($WP plugin list --status=active --field=name)
check "old 9Core plugin deactivated" '! grep -qx nine-code-ultra-core <<<"$ACTIVE"'
check "old 9 Data Manager plugin deactivated" '! grep -qx nine10-data-edition <<<"$ACTIVE"'
check "separate Nine Code Data plugin deactivated" '! grep -qx nine-code-data <<<"$ACTIVE"'
check "Nine Code theme active" '[ "$($WP theme list --status=active --field=name)" = nine-code ]'
DMFILE=$($WP eval 'echo NINE55_ULTRON_DATA_FILE;' 2>/dev/null)
check "Data Manager runs from the theme" 'grep -q "data-manager/bootstrap.php" <<<"$DMFILE"'
check "Data Manager is version 18.0.0 (installed by the theme)" '[ "$($WP option get nine55_ultron_data_version)" = 18.0.0 ]'
check "Data Manager history table exists" '[ "$($WP eval '"'"'global $wpdb; $t = NCD_History::table(); echo false === $wpdb->query("SELECT id FROM $t LIMIT 1") ? "no" : "yes";'"'"' 2>/dev/null)" = yes ]'
check "primary menu location carried over" '[ "$($WP eval "echo (int) (get_nav_menu_locations()[\"primary\"] ?? 0);")" = "$OLDMENU" ]'
check "Core settings preserved (client_brand_name)" '[ "$($WP eval "echo ncu_get_settings()[\"client_brand_name\"];")" = "Keep Me" ]'
check "legacy form post type + data intact" '[ "$($WP post get "$FORM" --field=post_title)" = "Legacy form" ]'
MENUS=$($WP eval 'wp_set_current_user(1); do_action("admin_menu"); global $_registered_pages; $k = array_keys((array) $_registered_pages); echo (in_array("toplevel_page_nine-post-manager", $k, true) ? "post:yes" : "post:no") . " " . (count(preg_grep("/nine-category-manager/", $k)) ? "category:yes" : "category:no");' 2>/dev/null | tail -1)
check "Post Editor admin page is registered for the admin" 'grep -q "post:yes" <<<"$MENUS"'
check "Category Manager admin page is registered for the admin" 'grep -q "category:yes" <<<"$MENUS"'
check "no PHP fatals logged" '! grep -E "PHP Fatal|Cannot redeclare" "$DIR/wp/debug.log" >/dev/null'
$WP plugin delete nine-code-ultra-core nine10-data-edition nine-code-data >/dev/null 2>&1 || true
check "site up after deleting old plugins (200)" '[ "$(front)" = 200 ]'
check "Data Manager still runs after the old plugins are gone" '[ "$($WP option get nine55_ultron_data_version)" = 18.0.0 ]'
[ $fail -eq 0 ] && echo "UPGRADE OK" || { echo "UPGRADE FAILED"; grep -E "Fatal" "$DIR/wp/debug.log" | head -5; exit 1; }
