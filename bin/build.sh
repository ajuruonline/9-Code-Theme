#!/usr/bin/env bash
# Builds the installable zip into dist/. Data Manager is inside the theme (theme/nine-code/data-manager),
# so the theme zip is the only package. The folder name inside the zip is the slug WordPress
# installs to, so uploading a newer zip replaces the previous version in place.
set -euo pipefail
cd "$(dirname "$0")/.."
bin/lint.sh >/dev/null
tv=$(grep -m1 '^Version:' theme/nine-code/style.css | awk '{print $2}')
rm -rf dist && mkdir -p dist
( cd theme  && zip -qr "../dist/nine-code-theme-$tv.zip" nine-code  -x '*.DS_Store' )
( cd dist && sha256sum *.zip > SHA256SUMS.txt && cat SHA256SUMS.txt && ls -la )
