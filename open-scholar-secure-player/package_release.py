#!/usr/bin/env python3
"""Create the Secure Scholar v1 WordPress, Windows build-kit and source releases."""
import shutil
import subprocess
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
OUTPUT = ROOT / 'release'
VERSION = '1.0.0'

def add_tree(archive, source, prefix, excluded=()):
    for path in sorted(source.rglob('*')):
        relative = path.relative_to(source)
        if not path.is_file() or any(part in excluded for part in relative.parts):
            continue
        archive.write(path, str(Path(prefix) / relative))

def main():
    subprocess.run(['python3', str(ROOT / 'build.py')], check=True)
    OUTPUT.mkdir(exist_ok=True)
    plugin = OUTPUT / f'Secure_Scholar_Plugin_v{VERSION}.zip'
    with zipfile.ZipFile(plugin, 'w', zipfile.ZIP_DEFLATED) as archive:
        add_tree(archive, ROOT / 'wordpress' / 'secure-scholar', 'secure-scholar')
    windows = OUTPUT / f'Secure_Scholar_Windows_EXE_Build_Kit_v{VERSION}.zip'
    with zipfile.ZipFile(windows, 'w', zipfile.ZIP_DEFLATED) as archive:
        add_tree(archive, ROOT / 'desktop', 'Secure_Scholar_Windows_v1/desktop', excluded=('.venv', '.build-venv', '__pycache__', 'dist', 'build'))
        add_tree(archive, ROOT / 'dist', 'Secure_Scholar_Windows_v1/dist')
        for name in ('README.md', 'FORMAT.md', 'SECURITY.md', 'OPEN_SCHOLAR_STANDARD.md', 'DEVELOPMENT.md', 'VALIDATION.md', 'CHANGELOG.md'):
            archive.write(ROOT / name, f'Secure_Scholar_Windows_v1/{name}')
    code = OUTPUT / f'Secure_Scholar_Development_Code_Pack_v{VERSION}.zip'
    with zipfile.ZipFile(code, 'w', zipfile.ZIP_DEFLATED) as archive:
        add_tree(archive, ROOT, 'Secure_Scholar_v1', excluded=('release', '__pycache__', '.venv', '.build-venv', 'node_modules'))
    for path in (plugin, windows, code):
        print(path, path.stat().st_size)

if __name__ == '__main__':
    main()
