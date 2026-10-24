#!/usr/bin/env bash
# Rehearses the move from the legacy three-package site to the lean Open Scholar host.
# The current theme may retire old 9Core, but must leave the standalone Data Manager active and intact.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export WP_TEST_DIR="${WP_TEST_DIR:-$ROOT/.wp-test-upgrade}" WP_TEST_PORT="${WP_TEST_PORT:-8892}"
DIR="$WP_TEST_DIR"; PORT="$WP_TEST_PORT"; BASE="http://localhost:$PORT"
WP="php $DIR/wp-cli.phar --allow-root --path=$DIR/wp"
fail=0; check() { if eval "$2"; then echo "PASS $1"; else echo "FAIL $1"; fail=1; fi; }
front() { curl -s -o /dev/null -w '%{http_code}' "$BASE/"; }

"$ROOT/bin/setup-test-site.sh" >/dev/null 2>&1
$WP theme activate twentytwentyfive >/dev/null
rm -rf "$DIR/legacy"; mkdir -p "$DIR/legacy"
git -C "$ROOT" archive ed3fda3 legacy | tar -x -C "$DIR/legacy"
LEGACY="$DIR/legacy/legacy"; CONTENT="$DIR/wp/wp-content"
ln -sfn "$LEGACY/9code-13-theme" "$CONTENT/themes/9code-13-theme"
ln -sfn "$LEGACY/nine-code-ultra-core" "$CONTENT/plugins/nine-code-ultra-core"
ln -sfn "$LEGACY/nine10-data-edition" "$CONTENT/plugins/nine10-data-edition"
$WP theme activate 9code-13-theme >/dev/null
$WP plugin activate nine-code-ultra-core nine10-data-edition >/dev/null

# Move the old data layer to its last standalone release before changing themes.
git -C "$ROOT" archive "${PLUGIN_17_REF:-6e6708b}" plugin/nine-code-data | tar -x -C "$DIR/legacy"
ln -sfn "$DIR/legacy/plugin/nine-code-data" "$CONTENT/plugins/nine-code-data"
$WP plugin deactivate nine10-data-edition >/dev/null
$WP plugin activate nine-code-data >/dev/null

"$ROOT/bin/serve-test-site.sh" >/dev/null
: > "$DIR/wp/debug.log"
OLDMENU=$($WP menu list --fields=term_id --format=ids | awk '{print $1}')
$WP menu location assign "$OLDMENU" primary >/dev/null
$WP option update ncu_settings "$($WP option get ncu_settings --format=json | sed 's/"client_brand_name":""/"client_brand_name":"Keep Me"/')" --format=json >/dev/null
FORM=$($WP post create --post_type=nine10_form_def --post_title="Legacy form" --post_status=publish --porcelain)
DM_VERSION=$($WP option get nine55_ultron_data_version)
check "legacy install serves" '[ "$(front)" = 200 ]'

echo "-- activate the lean Open Scholar theme"
$WP theme activate nine-code >/dev/null
$WP eval 'wp_set_current_user(1); do_action("admin_init");' >/dev/null
check "site serves after theme switch" '[ "$(front)" = 200 ]'
ACTIVE=$($WP plugin list --status=active --field=name)
check "obsolete 9Core plugin deactivated" '! grep -qx nine-code-ultra-core <<<"$ACTIVE"'
check "standalone Data Manager remains active" 'grep -qx nine-code-data <<<"$ACTIVE"'
check "Open Scholar theme active under stable slug" '[ "$($WP theme list --status=active --field=name)" = nine-code ]'
check "Data Manager version was not changed by theme" '[ "$($WP option get nine55_ultron_data_version)" = "$DM_VERSION" ]'
check "Data Manager executes from plugin" '[[ "$($WP eval '\''echo NINE55_ULTRON_DATA_FILE;'\'' 2>/dev/null)" == *"plugins/nine-code-data"* ]]'
check "primary menu assignment preserved" '[ "$($WP eval "echo (int) (get_nav_menu_locations()[\"primary\"] ?? 0);")" = "$OLDMENU" ]'
check "host settings preserved" '[ "$($WP eval "echo ncu_get_settings()[\"client_brand_name\"];")" = "Keep Me" ]'
check "plugin-owned form remains intact" '[ "$($WP post get "$FORM" --field=post_title)" = "Legacy form" ]'
check "no PHP fatals logged" '! grep -E "PHP Fatal|Cannot redeclare" "$DIR/wp/debug.log" >/dev/null'

[ $fail -eq 0 ] && echo "UPGRADE OK" || { echo "UPGRADE FAILED"; grep -E "Fatal" "$DIR/wp/debug.log" | head -5; exit 1; }
