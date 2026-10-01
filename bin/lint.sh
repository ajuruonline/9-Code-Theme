#!/usr/bin/env bash
# Static gates: PHP/JS syntax, JSON validity, merge-hazard guards.
set -uo pipefail
cd "$(dirname "$0")/.."
fail=0
echo "== PHP syntax"; n=0
while IFS= read -r -d '' f; do n=$((n+1)); php -l "$f" >/dev/null 2>/tmp/lint.err || { echo "FAIL $f"; cat /tmp/lint.err; fail=1; }; done < <(find theme plugin tests -name '*.php' -print0)
echo "$n files"
echo "== JS syntax"; n=0
while IFS= read -r -d '' f; do n=$((n+1)); node --check "$f" 2>/tmp/lint.err || { echo "FAIL $f"; cat /tmp/lint.err; fail=1; }; done < <(find theme plugin \( -name '*.js' -o -name '*.mjs' \) -print0; find tests -name '*.mjs' -print0)
echo "$n files"
echo "== JSON"; n=0
while IFS= read -r -d '' f; do n=$((n+1)); python3 -c "import json,sys; json.load(open(sys.argv[1]))" "$f" 2>/dev/null || { echo "FAIL $f"; fail=1; }; done < <(find theme plugin -name '*.json' -print0)
echo "$n files"
echo "== No full-screen editor layer (position:fixed + inset:0) in post-editor code"
if grep -rnE "inset:\s*0" theme/nine-code/core/assets/css/editor-workspace.css theme/nine-code/core/assets/css/editor-tools.css theme/nine-code/core/assets/js/editor-tools.js theme/nine-code/core/assets/js/editor-workspace.js plugin/nine-code-data/modules/post/assets/native-editor-recovery.js 2>/dev/null | grep -v "display:none\|display: none" | grep -i "fixed" ; then echo "FAIL: fixed full-viewport editor layer"; fail=1; fi
echo "== Plugin-only APIs must not appear in the theme"
if grep -rnE "register_(de)?activation_hook|plugin_dir_(url|path)|plugins_url|plugin_basename|load_plugin_textdomain" theme --include=*.php | grep -v "migrate-legacy" ; then echo "FAIL: plugin-only API in theme"; fail=1; fi
echo "== Theme/plugin versions agree with headers"
tv=$(grep -m1 '^Version:' theme/nine-code/style.css | awk '{print $2}'); cv=$(grep -m1 "define( 'NCU_THEME_VERSION'" theme/nine-code/functions.php | sed "s/.*, '\(.*\)' ).*/\1/")
pv=$(grep -m1 ' \* Version:' plugin/nine-code-data/nine-code-data.php | awk '{print $3}'); pc=$(grep -m1 "define( 'NINE55_ULTRON_DATA_VERSION'" plugin/nine-code-data/nine-code-data.php | sed "s/.*, '\(.*\)' ).*/\1/")
[ "$tv" = "$cv" ] || { echo "FAIL theme version header $tv != constant $cv"; fail=1; }
[ "$pv" = "$pc" ] || { echo "FAIL plugin version header $pv != constant $pc"; fail=1; }
echo "theme $tv, plugin $pv"
[ $fail -eq 0 ] && echo "LINT OK" || { echo "LINT FAILED"; exit 1; }
