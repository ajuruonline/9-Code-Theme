#!/usr/bin/env python3
"""Build the release outputs for Open Scholar Secure Player.

Produces, in release/:
  Open_Scholar_Secure_Player_Plugin_v<VERSION>.zip            WordPress plugin (install via Plugins > Add New > Upload)
  Open_Scholar_Secure_Player_Development_Pack_v<VERSION>.zip  full source, tests, vendor locks and handoff docs
  SHA256SUMS.txt                                              checksums of both ZIPs
The Windows EXE is NOT built here. It is produced on a Windows runner by
.github/workflows/open-scholar-windows-exe.yml (see docs/RELEASE.md).
"""
import hashlib
import subprocess
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '1.1.0'
OUT = ROOT / 'release'
EXCLUDE_DIRS = {'release', '.git', '__pycache__', '.build-venv', 'build', 'node_modules'}
EXCLUDE_FILES = {'.DS_Store'}

def files_under(base):
    for path in sorted(base.rglob('*')):
        rel = path.relative_to(base)
        if not path.is_file() or set(rel.parts) & EXCLUDE_DIRS or path.name in EXCLUDE_FILES:
            continue
        if rel.parts[:2] == ('desktop', 'dist'):
            continue
        yield path, rel

def write_zip(target, pairs):
    with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED) as archive:
        for path, arcname in pairs:
            archive.write(path, arcname.as_posix())

def sha256(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def main():
    subprocess.run([sys.executable, str(ROOT / 'build.py')], check=True)
    OUT.mkdir(exist_ok=True)
    plugin = OUT / f'Open_Scholar_Secure_Player_Plugin_v{VERSION}.zip'
    pack = OUT / f'Open_Scholar_Secure_Player_Development_Pack_v{VERSION}.zip'
    plugin_base = ROOT / 'wordpress' / 'secure-scholar'
    # WordPress requires the folder name to be the plugin slug.
    write_zip(plugin, [(p, Path('secure-scholar') / r) for p, r in files_under(plugin_base)])
    write_zip(pack, [(p, Path('Open_Scholar_Secure_Player_Development_Pack') / r) for p, r in files_under(ROOT)])
    sums = OUT / 'SHA256SUMS.txt'
    sums.write_text(''.join(f'{sha256(p)}  {p.name}\n' for p in (plugin, pack)), encoding='utf-8')
    for p in (plugin, pack, sums):
        print(f'{p.relative_to(ROOT)}  {p.stat().st_size:,} bytes')

if __name__ == '__main__':
    main()
