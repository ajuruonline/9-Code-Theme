#!/usr/bin/env python3
"""Build the two Open Scholar web apps:

  dist/index.html  Open Scholar Secure Player (viewer: opens ordinary and protected lessons)
  dist/maker.html  Open Scholar Secure File Maker (publisher tool: makes .scholar and .scholarpass files)

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


def strip_module(text):
    """Turn an ES module into plain script text that can share one scope with the other modules."""
    text = re.sub(r'^import .*$', '', text, flags=re.M)
    return text.replace('export ', '')


def verify(files):
    for relative, expected in files.items():
        actual = hashlib.sha256((ROOT / relative).read_bytes()).hexdigest()
        if actual != expected:
            raise SystemExit(f'Checksum mismatch for {relative}: expected {expected}, got {actual}')


def read(relative):
    return (ROOT / relative).read_text(encoding='utf-8')


def build():
    lock = json.loads((ROOT / 'vendor' / 'LOCK.json').read_text(encoding='utf-8'))
    verify({**lock['pdfjs']['files'], **lock['cap']['files'], **lock['favicon']['files']})

    src = ROOT / 'src'
    output = ROOT / 'dist'
    vendor = ROOT / 'vendor'
    output.mkdir(exist_ok=True)
    cap = ROOT / lock['cap']['file']
    cap_data = 'data:image/png;base64,' + base64.b64encode(cap.read_bytes()).decode()
    style = read('src/style.css')

    pdf = strip_module(read('vendor/pdf.mjs'))
    pdf = re.sub(r'^//# sourceMappingURL=.*$', '', pdf, flags=re.M)
    pdf += '\nwindow.OSPDF={getDocument,GlobalWorkerOptions};\n'
    worker = base64.b64encode((vendor / 'pdf.worker.mjs').read_bytes()).decode()

    shared = strip_module(read('src/crypto.mjs')) + '\n' + strip_module(read('src/ledger.mjs')) + '\n' + strip_module(read('src/plain.mjs'))
    common = strip_module(read('src/common.mjs'))

    player_js = shared + '\n' + common + '\n' + strip_module(read('src/player.mjs'))
    maker_js = strip_module(read('src/crypto.mjs')) + '\n' + strip_module(read('src/ledger.mjs')) + '\n' + common + '\n' + strip_module(read('src/maker.mjs'))

    player = read('src/player.html')
    player = player.replace('/*__STYLE__*/', style).replace('/*__CAP_IMAGE__*/', cap_data)
    player = player.replace('/*__PDF_WORKER__*/', 'window.OS_PDF_WORKER=' + json.dumps(worker) + ';')
    player = player.replace('/*__PDF_MODULE__*/', pdf.replace('</script', '<\\/script'))
    player = player.replace('/*__APPLICATION__*/', player_js.replace('</script', '<\\/script'))

    maker = read('src/maker.html')
    maker = maker.replace('/*__STYLE__*/', style).replace('/*__CAP_IMAGE__*/', cap_data)
    maker = maker.replace('/*__APPLICATION__*/', maker_js.replace('</script', '<\\/script'))

    (output / 'index.html').write_text(player, encoding='utf-8')
    (output / 'maker.html').write_text(maker, encoding='utf-8')
    for name in ('sw.js', 'manifest.webmanifest', 'icon.svg', 'favicon.ico', 'icon-192.png', 'apple-touch-icon.png'):
        shutil.copyfile(src / name, output / name)
    shutil.copyfile(cap, output / 'icon-512.png')

    plugin_app = ROOT / 'wordpress' / 'secure-scholar' / 'app'
    plugin_app.mkdir(parents=True, exist_ok=True)
    for path in output.iterdir():
        if path.is_file():
            shutil.copyfile(path, plugin_app / path.name)
    print(f'Built player: {len(player.encode()):,} bytes; maker: {len(maker.encode()):,} bytes')


if __name__ == '__main__':
    build()
