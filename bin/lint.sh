#!/usr/bin/env bash
# Static gates: PHP/JS syntax, JSON validity, merge-hazard guards.
set -uo pipefail
cd "$(dirname "$0")/.."
fail=0
echo "== PHP syntax"; n=0
while IFS= read -r -d '' f; do n=$((n+1)); php -l "$f" >/dev/null 2>/tmp/lint.err || { echo "FAIL $f"; cat /tmp/lint.err; fail=1; }; done < <(find theme tests -name '*.php' -print0)
echo "$n files"
echo "== JS syntax"; n=0
while IFS= read -r -d '' f; do n=$((n+1)); node --check "$f" 2>/tmp/lint.err || { echo "FAIL $f"; cat /tmp/lint.err; fail=1; }; done < <(find theme \( -name '*.js' -o -name '*.mjs' \) -print0; find tests -name '*.mjs' -print0)
echo "$n files"
echo "== JSON"; n=0
while IFS= read -r -d '' f; do n=$((n+1)); python3 -c "import json,sys; json.load(open(sys.argv[1]))" "$f" 2>/dev/null || { echo "FAIL $f"; fail=1; }; done < <(find theme -name '*.json' -print0)
echo "$n files"
echo "== No full-screen editor layer (position:fixed + inset:0) in post-editor code"
if grep -rnE "inset:\s*0" theme/nine-code/core/assets/css/editor-workspace.css theme/nine-code/core/assets/css/editor-tools.css theme/nine-code/core/assets/js/editor-tools.js theme/nine-code/core/assets/js/editor-workspace.js theme/nine-code/data-manager/modules/post/assets/native-editor-recovery.js 2>/dev/null | grep -v "display:none\|display: none" | grep -i "fixed" ; then echo "FAIL: fixed full-viewport editor layer"; fail=1; fi
echo "== Plugin-only APIs must not appear in the theme (Data Manager is bundled, so it is covered too)"
# plugin_basename() is the one exception, allowed only in the AI Manager: it updates other plugins,
# so it has to map their file paths. Activation hooks, plugin URLs and textdomain loading never belong here.
if grep -rnE "register_(de)?activation_hook|plugin_dir_(url|path)|plugins_url|load_plugin_textdomain" theme --include=*.php | grep -v "migrate-legacy" ; then echo "FAIL: plugin-only API in theme"; fail=1; fi
if grep -rn "plugin_basename" theme --include=*.php | grep -v "migrate-legacy" | grep -v "theme/nine-code/data-manager/modules/ai/" ; then echo "FAIL: plugin_basename outside the AI Manager"; fail=1; fi
echo "== Theme and Data Manager versions agree"
tv=$(grep -m1 '^Version:' theme/nine-code/style.css | awk '{print $2}'); cv=$(grep -m1 "define( 'NCU_THEME_VERSION'" theme/nine-code/functions.php | sed "s/.*, '\(.*\)' ).*/\1/")
dv=$(grep -m1 "define( 'NINE55_ULTRON_DATA_VERSION'" theme/nine-code/data-manager/bootstrap.php | sed "s/.*, '\(.*\)' ).*/\1/"); dr=$(grep -m1 '^Stable tag:' theme/nine-code/data-manager/readme.txt | awk '{print $3}')
[ "$tv" = "$cv" ] || { echo "FAIL theme version header $tv != constant $cv"; fail=1; }
[ "$tv" = "$dv" ] || { echo "FAIL theme version $tv != Data Manager constant $dv"; fail=1; }
[ "$tv" = "$dr" ] || { echo "FAIL theme version $tv != Data Manager readme stable tag $dr"; fail=1; }
echo "theme and Data Manager $tv"
echo "== Settings UI contract"
python3 tests/settings-ui-contract.py || fail=1
[ $fail -eq 0 ] && echo "LINT OK" || { echo "LINT FAILED"; exit 1; }
