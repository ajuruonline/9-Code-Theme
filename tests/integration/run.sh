#!/usr/bin/env bash
# Runs every integration suite against the disposable test site and prints exact totals.
# Usage: tests/integration/run.sh [suite ...]
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DIR="${WP_TEST_DIR:-$ROOT/.wp-test}"
WP=(php -d memory_limit=1G "$DIR/wp-cli.phar" --allow-root "--path=$DIR/wp")
SUITES=("$@")
[ ${#SUITES[@]} -eq 0 ] && SUITES=(discovery records acf exchange security providers compat adapters)
pass=0; fail=0; broken=()
for s in "${SUITES[@]}"; do
  f="$ROOT/tests/integration/$s.php"
  [ -f "$f" ] || { echo "missing suite $s"; broken+=("$s"); continue; }
  out="$("${WP[@]}" eval-file "$f" --user=admin 2>&1 | grep -v 'wp_update_\|wp_version_check\|WordPress.org')"
  echo "$out" | grep -E '^(PASS|FAIL|SKIP)|: [0-9]+ passed' 
  echo "$out" | grep -E 'Fatal|Warning:|Notice:|Deprecated:|Error:' | grep -v '^PASS\|^FAIL' | head -20
  line="$(echo "$out" | grep -E ': [0-9]+ passed, [0-9]+ failed' | tail -1)"
  if [ -z "$line" ]; then echo "SUITE $s did not finish"; echo "$out" | tail -20; broken+=("$s"); continue; fi
  p="$(echo "$line" | sed -E 's/.*: ([0-9]+) passed.*/\1/')"; q="$(echo "$line" | sed -E 's/.*, ([0-9]+) failed.*/\1/')"
  pass=$((pass+p)); fail=$((fail+q))
done
echo
echo "integration total: $pass passed, $fail failed${broken:+, ${#broken[@]} suite(s) broken: ${broken[*]}}"
[ "$fail" -eq 0 ] && [ ${#broken[@]} -eq 0 ]
