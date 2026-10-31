#!/usr/bin/env python3
"""Build the self-contained Open Scholar Secure Player app for WordPress and desktop.

Every input lives inside this folder and is verified against vendor/LOCK.json, so a fresh
checkout builds without environment variables or files outside the tree.
"""
import base64
import hashlib
import json
import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parent


def verify(lock):
    for relative, expected in lock.items():
        actual = hashlib.sha256((ROOT / relative).read_bytes()).hexdigest()
        if actual != expected:
            raise SystemExit(f'Checksum mismatch for {relative}: expected {expected}, got {actual}')


def build():
    lock = json.loads((ROOT / 'vendor' / 'LOCK.json').read_text(encoding='utf-8'))
    files = {**lock['pdfjs']['files'], **lock['cap']['files'], **lock['favicon']['files']}
    verify(files)

    source = ROOT / 'src'
    output = ROOT / 'dist'
    vendor = ROOT / 'vendor'
    cap = ROOT / lock['cap']['file']
    output.mkdir(exist_ok=True)

    cap_data = 'data:image/png;base64,' + base64.b64encode(cap.read_bytes()).decode()
    pdf = (vendor / 'pdf.mjs').read_text(encoding='utf-8')
    pdf = re.sub(r'^export \{[^\n]+\};\s*$', '', pdf, flags=re.M)
    pdf = re.sub(r'^//# sourceMappingURL=.*$', '', pdf, flags=re.M)
    pdf += '\nwindow.OSPDF={getDocument,GlobalWorkerOptions};\n'
    worker = base64.b64encode((vendor / 'pdf.worker.mjs').read_bytes()).decode()
    crypto = (source / 'crypto.mjs').read_text(encoding='utf-8').replace('export ', '')
    ledger = re.sub(r'^import .*$', '', (source / 'ledger.mjs').read_text(encoding='utf-8'), flags=re.M).replace('export ', '')
    app = re.sub(r'^import .*$', '', (source / 'app.js').read_text(encoding='utf-8'), flags=re.M)
    plain = re.sub(r'^import .*$', '', (source / 'plain.mjs').read_text(encoding='utf-8'), flags=re.M).replace('export ', '')
    application = crypto + '\n' + ledger + '\n' + plain + '\n' + app
    html = (source / 'index.html').read_text(encoding='utf-8')
    html = html.replace('/*__STYLE__*/', (source / 'style.css').read_text(encoding='utf-8'))
    html = html.replace('/*__CAP_IMAGE__*/', cap_data)
    html = html.replace('/*__PDF_WORKER__*/', 'window.OS_PDF_WORKER=' + json.dumps(worker) + ';')
    html = html.replace('/*__PDF_MODULE__*/', pdf.replace('</script', '<\\/script'))
    html = html.replace('/*__APPLICATION__*/', application.replace('</script', '<\\/script'))
    (output / 'index.html').write_text(html, encoding='utf-8')
    for name in ('sw.js', 'manifest.webmanifest', 'icon.svg', 'favicon.ico'):
        shutil.copyfile(source / name, output / name)
    shutil.copyfile(cap, output / 'icon-512.png')
    plugin_app = ROOT / 'wordpress' / 'secure-scholar' / 'app'
    plugin_app.mkdir(parents=True, exist_ok=True)
    for path in output.iterdir():
        if path.is_file():
            shutil.copyfile(path, plugin_app / path.name)
    print(f'Built Open Scholar Secure Player: {len(html.encode()):,} bytes')


if __name__ == '__main__':
    build()
