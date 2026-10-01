#!/usr/bin/env bash
# Builds installable zips into dist/. Folder names inside the zips are the slugs WordPress
# will install to, so uploading a newer zip replaces the previous version in place.
set -euo pipefail
cd "$(dirname "$0")/.."
bin/lint.sh >/dev/null
tv=$(grep -m1 '^Version:' theme/nine-code/style.css | awk '{print $2}')
pv=$(grep -m1 ' \* Version:' plugin/nine-code-data/nine-code-data.php | awk '{print $3}')
rm -rf dist && mkdir -p dist
( cd theme  && zip -qr "../dist/nine-code-theme-$tv.zip" nine-code  -x '*.DS_Store' )
( cd plugin && zip -qr "../dist/nine-code-data-plugin-$pv.zip" nine-code-data -x '*.DS_Store' )
( cd dist && sha256sum *.zip > SHA256SUMS.txt && cat SHA256SUMS.txt && ls -la )
