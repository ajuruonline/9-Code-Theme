#!/usr/bin/env bash
# Draft-only deterministic Open Theme installer. Intentionally separate from
# the existing 9 Code Alpha build; never overwrite or relabel that package.
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
# zip(1) copies checkout mtimes and produces different SHA-256 digests for
# identical source across independent Actions runs. Normalize entry ordering,
# DOS timestamps, permissions, extras and compression in Python stdlib.
python3 - "$source_dir" "dist/$archive" <<'PY'
from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED, ZIP_STORED
import sys

source = Path(sys.argv[1])
target = Path(sys.argv[2])
paths = sorted([source, *source.rglob('*')],
               key=lambda p: p.relative_to(source.parent).as_posix())
with ZipFile(target, 'w', compression=ZIP_DEFLATED, compresslevel=9) as z:
    for item in paths:
        if item.is_symlink():
            raise SystemExit('Refusing to package theme symlinks')
        parts = item.relative_to(source.parent).parts
        if any(part in {'.git', '.github', 'node_modules', '.DS_Store'}
               or part == '.env' or part.startswith('.env.') for part in parts):
            continue
        is_dir = item.is_dir()
        name = item.relative_to(source.parent).as_posix() + ('/' if is_dir else '')
        info = ZipInfo(name, (1980, 1, 1, 0, 0, 0))
        info.create_system = 3
        info.external_attr = ((0o40755 if is_dir else 0o100644) << 16)
        if is_dir:
            info.external_attr |= 0x10
            info.compress_type = ZIP_STORED
            z.writestr(info, b'')
        else:
            info.compress_type = ZIP_DEFLATED
            z.writestr(info, item.read_bytes(), compress_type=ZIP_DEFLATED,
                       compresslevel=9)
PY
(
  cd dist
  unzip -tqq "$archive"
  sha256sum "$archive" > SHA256SUMS-open-theme.txt
  cat SHA256SUMS-open-theme.txt
)
