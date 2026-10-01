#!/usr/bin/env bash
# Starts (or restarts) the PHP dev server for the test site in the background.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="${WP_TEST_DIR:-$ROOT/.wp-test}"; PORT="${WP_TEST_PORT:-8890}"
[ -f "$DIR/server.pid" ] && kill "$(cat "$DIR/server.pid")" 2>/dev/null || true
cd "$DIR/wp"
nohup php -d memory_limit=512M -S "localhost:$PORT" -t "$DIR/wp" "$ROOT/tests/e2e/router.php" > "$DIR/server.log" 2>&1 &
echo $! > "$DIR/server.pid"
for _ in $(seq 1 30); do curl -fs -o /dev/null "http://localhost:$PORT/" && { echo "Serving http://localhost:$PORT"; exit 0; }; sleep 0.5; done
echo "server did not start; see $DIR/server.log" >&2; exit 1
