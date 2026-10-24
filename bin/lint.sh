#!/usr/bin/env bash
# Static gates: PHP/JS syntax, JSON validity, merge-hazard guards.
set -uo pipefail
cd "$(dirname "$0")/.."
fail=0
echo "== PHP syntax"; n=$(find theme tests -name '*.php' | wc -l)
if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do php -l "$f" >/dev/null 2>/tmp/lint.err || { echo "FAIL $f"; cat /tmp/lint.err; fail=1; }; done < <(find theme tests -name '*.php' -print0)
elif command -v docker >/dev/null 2>&1; then
  docker run --rm -v "$PWD:/app" -w /app php:8.2-cli sh -lc 'find theme tests -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null' || { echo "FAIL PHP syntax in container"; fail=1; }
else
  echo "FAIL: PHP or Docker is required for PHP syntax checks"; fail=1
fi
echo "$n files"
echo "== JS syntax"; n=0
while IFS= read -r -d '' f; do n=$((n+1)); node --check "$f" 2>/tmp/lint.err || { echo "FAIL $f"; cat /tmp/lint.err; fail=1; }; done < <(find theme \( -name '*.js' -o -name '*.mjs' \) -print0; find tests -name '*.mjs' -print0)
echo "$n files"
echo "== JSON"; n=0
while IFS= read -r -d '' f; do n=$((n+1)); python3 -c "import json,sys; json.load(open(sys.argv[1]))" "$f" 2>/dev/null || { echo "FAIL $f"; fail=1; }; done < <(find theme -name '*.json' -print0)
echo "$n files"
echo "== No full-screen editor layer (position:fixed + inset:0) in post-editor code"
if grep -rnE "inset:\s*0" theme/nine-code/core/assets/css/editor-workspace.css theme/nine-code/core/assets/css/editor-tools.css theme/nine-code/core/assets/js/editor-tools.js theme/nine-code/core/assets/js/editor-workspace.js theme/nine-code/data-manager/modules/post/assets/native-editor-recovery.js 2>/dev/null | grep -v "display:none\|display: none" | grep -i "fixed" ; then echo "FAIL: fixed full-viewport editor layer"; fail=1; fi
echo "== Plugin-only APIs must not appear in shipped theme runtime"
if grep -rnE "register_(de)?activation_hook|plugin_dir_(url|path)|plugins_url|load_plugin_textdomain" theme/nine-code --include=*.php \
  --exclude-dir=data-manager | grep -v "migrate-legacy" ; then echo "FAIL: plugin-only API in theme runtime"; fail=1; fi
if grep -rn "plugin_basename" theme/nine-code --include=*.php --exclude-dir=data-manager | grep -v "migrate-legacy" | grep -v "core/ultra-backup/" ; then echo "FAIL: plugin_basename outside the backup interoperability engine"; fail=1; fi
echo "== Theme and host core versions agree"
tv=$(grep -m1 '^Version:' theme/nine-code/style.css | awk '{print $2}'); cv=$(grep -m1 "define( 'NCU_THEME_VERSION'" theme/nine-code/functions.php | sed "s/.*, '\(.*\)' ).*/\1/")
[ "$tv" = "$cv" ] || { echo "FAIL theme version header $tv != constant $cv"; fail=1; }
echo "theme host $tv"
echo "== Settings UI contract"
python3 tests/settings-ui-contract.py || fail=1
echo "== Open Scholar ownership contract"
python3 tests/open-scholar-contract.py || fail=1
[ $fail -eq 0 ] && echo "LINT OK" || { echo "LINT FAILED"; exit 1; }
