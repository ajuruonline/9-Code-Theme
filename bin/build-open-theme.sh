#!/usr/bin/env bash
# Draft-only Open Theme installer. Intentionally separate from the existing
# 9 Code Alpha build; never overwrite or relabel that historical package.
set -euo pipefail
cd "$(dirname "$0")/.."
source_dir="theme-upload/open-theme-green-edition"
test -f "$source_dir/style.css"
test -f "$source_dir/functions.php"
test -f "$source_dir/index.php"
test -f "$source_dir/assets/css/main.css"
grep -Eq '^Theme Name:[[:space:]]*Open Theme[[:space:]]*$' "$source_dir/style.css"
version="$(sed -nE 's/^Version:[[:space:]]*([0-9]+(\.[0-9]+)+)[[:space:]]*$/\1/p' "$source_dir/style.css" | head -n 1)"
test -n "$version" || { echo "Open Theme style.css has no valid version" >&2; exit 2; }
if find "$source_dir" -type l -print -quit | grep -q .; then
  echo "Refusing to package theme symlinks" >&2
  exit 2
fi
mkdir -p dist
archive="open-theme-$version.zip"
rm -f "dist/$archive" "dist/SHA256SUMS-open-theme.txt"
(
  cd theme-upload
  zip -qr "../dist/$archive" open-theme-green-edition \
    -x '*.DS_Store' '*/.git/*' '*/.github/*' '*/.env' '*/.env.*' '*/node_modules/*'
)
(
  cd dist
  unzip -tqq "$archive"
  sha256sum "$archive" > SHA256SUMS-open-theme.txt
  cat SHA256SUMS-open-theme.txt
)
