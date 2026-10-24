#!/usr/bin/env bash
# Runs the official WordPress Plugin Check against Data Manager (needs the test site: bin/setup-test-site.sh).
# Data Manager ships inside the theme, and Plugin Check's CLI only accepts installed plugins, so the bundled
# code is copied into a temporary plugin folder with a header-only main file. The checked code is the code the
# theme ships. Fails on any ERROR except plugin_updater_detected (a deliberate, capability-gated admin feature
# that only matters for wordpress.org hosting; this code is distributed privately).
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; DIR="${WP_TEST_DIR:-$ROOT/.wp-test}"
WP="php $DIR/wp-cli.phar --allow-root --path=$DIR/wp"
if ! $WP plugin is-installed plugin-check 2>/dev/null; then
  curl -fsSLo "$DIR/pc.zip" https://downloads.wordpress.org/plugin/plugin-check.latest-stable.zip
  unzip -q -o "$DIR/pc.zip" -d "$DIR/wp/wp-content/plugins/" && rm "$DIR/pc.zip"
fi
$WP plugin activate plugin-check >/dev/null 2>&1

# The folder name is the plugin slug, and Plugin Check expects the text domain to match it: nine-code-data.
STAGE="$DIR/wp/wp-content/plugins/nine-code-data"
DM="$ROOT/theme/nine-code/data-manager"
version=$(grep -m1 "define( 'NINE55_ULTRON_DATA_VERSION'" "$DM/bootstrap.php" | sed "s/.*, '\(.*\)' ).*/\1/")
rm -rf "$STAGE" && mkdir -p "$STAGE" && cp -R "$DM/." "$STAGE/"
printf '%s\n' '<?php' '/**' ' * Plugin Name: Nine Code Data' ' * Description: Data Manager bundled with the Nine Code theme (header-only copy for Plugin Check).' " * Version: $version" ' * Requires at least: 6.6' ' * Requires PHP: 7.4' ' * License: GPL-2.0-or-later' ' * Text Domain: nine-code-data' ' */' > "$STAGE/nine-code-data.php"

$WP plugin check nine-code-data --format=json --ignore-warnings > "$DIR/pc.json" 2>/dev/null
rm -rf "$STAGE"
python3 "$ROOT/bin/pc-summary.py" "$DIR/pc.json"
n=$(python3 - "$DIR/pc.json" <<'PY'
import json,re,sys
raw=open(sys.argv[1]).read(); n=0
for b in re.split(r'^FILE: ',raw,flags=re.M)[1:]:
    _,_,rest=b.partition('\n')
    for r in json.loads(rest.strip() or '[]'):
        if r['type']=='ERROR' and r['code']!='plugin_updater_detected': n+=1
print(n)
PY
)
[ "$n" = 0 ] && echo "PLUGIN CHECK OK" || { echo "PLUGIN CHECK: $n errors"; exit 1; }
