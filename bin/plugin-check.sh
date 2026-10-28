#!/usr/bin/env bash
# Runs the official WordPress Plugin Check against Nine Code Data (needs the test site: bin/setup-test-site.sh).
# Blocks on unexpected ERROR; explicitly reports plugin_updater_detected as a documented
# private-distribution exception, never as a clean WordPress.org Plugin Check.
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
counts=$(python3 - "$DIR/pc.json" <<'PY'
import json, re, sys
raw = open(sys.argv[1]).read()
blocked = 0
documented = 0
for block in re.split(r'^FILE: ', raw, flags=re.M)[1:]:
    _, _, rest = block.partition('\n')
    for finding in json.loads(rest.strip() or '[]'):
        if finding.get('type') != 'ERROR':
            continue
        if finding.get('code') == 'plugin_updater_detected':
            documented += 1
        else:
            blocked += 1
print(blocked, documented)
PY
) || { echo "PLUGIN CHECK TOOL FAILURE: invalid report" >&2; exit 2; }
read -r blocked documented <<< "$counts"
if [ "$blocked" -gt 0 ]; then
  echo "PLUGIN CHECK FAILED: $blocked unexpected error(s); $documented documented exception(s)"
  exit 1
elif [ "$documented" -gt 0 ]; then
  echo "PLUGIN CHECK ADVISORY: $documented documented exception(s) (plugin_updater_detected; private distribution only)"
else
  echo "PLUGIN CHECK CLEAN"
fi
