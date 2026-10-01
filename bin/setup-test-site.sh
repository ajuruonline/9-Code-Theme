#!/usr/bin/env bash
# Builds a throw-away WordPress (SQLite, no MySQL needed) with Nine Code installed.
# Usage: bin/setup-test-site.sh      -> ./.wp-test  (override with WP_TEST_DIR)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="${WP_TEST_DIR:-$ROOT/.wp-test}"
PORT="${WP_TEST_PORT:-8890}"
mkdir -p "$DIR"; cd "$DIR"

[ -f wp-cli.phar ] || curl -fsSLo wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
if [ ! -d wp ]; then
  curl -fsSLo wp.zip https://wordpress.org/latest.zip && unzip -q -o wp.zip && mv wordpress wp && rm wp.zip
  curl -fsSLo sqlite.zip https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
  unzip -q -o sqlite.zip -d wp/wp-content/plugins/ && rm sqlite.zip
  cp wp/wp-content/plugins/sqlite-database-integration/db.copy wp/wp-content/db.php
  sed -i "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$DIR/wp/wp-content/plugins/sqlite-database-integration#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" wp/wp-content/db.php
fi
cat > wp/wp-config.php <<PHP
<?php
define('DB_NAME','wp');define('DB_USER','');define('DB_PASSWORD','');define('DB_HOST','');define('DB_CHARSET','utf8');define('DB_COLLATE','');
define('DB_DIR', __DIR__ . '/wp-content/database/');
\$table_prefix='wp_';
define('WP_DEBUG', true); define('WP_DEBUG_LOG', __DIR__ . '/debug.log'); define('WP_DEBUG_DISPLAY', false);
define('WP_HTTP_BLOCK_EXTERNAL', true); define('AUTOMATIC_UPDATER_DISABLED', true); define('DISABLE_WP_CRON', true);
if(!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
PHP
mkdir -p wp/wp-content/mu-plugins
cp "$ROOT/tests/e2e/mu-test-env.php" wp/wp-content/mu-plugins/test-env.php
ln -sfn "$ROOT/theme/nine-code" wp/wp-content/themes/nine-code
ln -sfn "$ROOT/plugin/nine-code-data" wp/wp-content/plugins/nine-code-data
: > wp/debug.log

WP="php $DIR/wp-cli.phar --allow-root --path=$DIR/wp"
if ! $WP core is-installed 2>/dev/null; then
  $WP core install --url="http://localhost:$PORT" --title="Nine Code Test" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
fi
$WP option update siteurl "http://localhost:$PORT" >/dev/null
$WP option update home "http://localhost:$PORT" >/dev/null
$WP option update permalink_structure '/%postname%/' >/dev/null
$WP theme activate nine-code
$WP plugin activate nine-code-data
$WP rewrite flush >/dev/null
$WP term create category News --slug=news >/dev/null 2>&1 || true
if [ -z "$($WP post list --post_type=post --name=sample-post-1 --format=ids)" ]; then
  for i in 1 2 3; do
    $WP post create --post_title="Sample post $i" --post_name="sample-post-$i" --post_status=publish --post_category=news \
      --post_content="<!-- wp:paragraph --><p>Body of sample post $i with <strong>bold</strong> text.</p><!-- /wp:paragraph -->" --porcelain >/dev/null
  done
  $WP post meta add "$($WP post list --name=sample-post-1 --format=ids)" custom_field "hello meta" >/dev/null
fi
M=$($WP menu create "Main" --porcelain 2>/dev/null || $WP menu list --fields=term_id --format=ids | awk '{print $1}')
$WP menu item add-custom "$M" Home "http://localhost:$PORT/" >/dev/null 2>&1 || true
$WP menu location assign "$M" primary >/dev/null; $WP menu location assign "$M" footer >/dev/null
echo "Test site ready in $DIR  (start it with bin/serve-test-site.sh)"
