#!/usr/bin/env bash
# Runs the official WordPress Plugin Check against Nine Code Data (needs the test site: bin/setup-test-site.sh).
# Fails on any ERROR except plugin_updater_detected (a deliberate, capability-gated admin feature that
# only matters for wordpress.org hosting; this plugin is distributed privately).
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; DIR="${WP_TEST_DIR:-$ROOT/.wp-test}"
WP="php $DIR/wp-cli.phar --allow-root --path=$DIR/wp"
if ! $WP plugin is-installed plugin-check 2>/dev/null; then
  curl -fsSLo "$DIR/pc.zip" https://downloads.wordpress.org/plugin/plugin-check.latest-stable.zip
  unzip -q -o "$DIR/pc.zip" -d "$DIR/wp/wp-content/plugins/" && rm "$DIR/pc.zip"
fi
$WP plugin activate plugin-check >/dev/null 2>&1
$WP plugin check nine-code-data --format=json --ignore-warnings > "$DIR/pc.json" 2>/dev/null
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
