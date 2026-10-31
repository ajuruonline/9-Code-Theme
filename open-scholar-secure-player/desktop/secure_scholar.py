#!/usr/bin/env python3
"""Secure Scholar Windows host: own WebView2 window, local-only server and Office preparation."""
from __future__ import annotations
import base64
import ctypes
import mimetypes
import os
import shutil
import subprocess
import sys
import tempfile
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlsplit

import webview

APP_NAME = 'Secure Scholar'
ROOT = Path(getattr(sys, '_MEIPASS', Path(__file__).resolve().parents[1]))
DIST = ROOT / 'dist'

class StaticHandler(BaseHTTPRequestHandler):
    allowed = {'index.html', 'sw.js', 'manifest.webmanifest', 'icon.svg', 'icon-512.png'}
    def log_message(self, *_args):
        pass
    def do_GET(self):
        name = unquote(urlsplit(self.path).path).lstrip('/') or 'index.html'
        if name not in self.allowed:
            self.send_error(404)
            return
        path = DIST / name
        if not path.is_file():
            self.send_error(404)
            return
        data = path.read_bytes()
        mime = mimetypes.guess_type(name)[0] or 'application/octet-stream'
        self.send_response(200)
        self.send_header('Content-Type', mime)
        self.send_header('Content-Length', str(len(data)))
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.send_header('Referrer-Policy', 'no-referrer')
        self.send_header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
        self.end_headers()
        self.wfile.write(data)

class DesktopApi:
    def __init__(self):
        self.window = None
    def convert_office(self):
        if not self.window:
            return None
        files = self.window.create_file_dialog(
            webview.OPEN_DIALOG,
            allow_multiple=False,
            file_types=('Documents and slides (*.docx;*.pptx;*.odt;*.odp;*.rtf;*.txt)',)
        )
        if not files:
            return None
        source = Path(files[0])
        program = shutil.which('soffice') or shutil.which('libreoffice')
        if not program:
            for candidate in (Path(r'C:\Program Files\LibreOffice\program\soffice.exe'), Path(r'C:\Program Files (x86)\LibreOffice\program\soffice.exe')):
                if candidate.exists():
                    program = str(candidate)
                    break
        if not program:
            raise RuntimeError('Install LibreOffice, or save the document as PDF first.')
        with tempfile.TemporaryDirectory(prefix='secure-scholar-office-') as temporary:
            output = Path(temporary)
            profile = output / 'profile'
            result = subprocess.run(
                [program, '-env:UserInstallation=' + profile.as_uri(), '--headless', '--convert-to', 'pdf', '--outdir', str(output), str(source)],
                capture_output=True, timeout=180, check=False
            )
            pdf = output / (source.stem + '.pdf')
            if result.returncode or not pdf.exists():
                raise RuntimeError('The document could not be prepared. Save it as PDF and try again.')
            return {'name': pdf.name, 'original': source.name, 'data': base64.b64encode(pdf.read_bytes()).decode('ascii')}

def request_capture_exclusion():
    """Best effort Windows 10+ exclusion from common capture APIs."""
    if sys.platform != 'win32':
        return
    user32 = ctypes.windll.user32
    kernel32 = ctypes.windll.kernel32
    process_id = os.getpid()
    handles = []
    callback_type = ctypes.WINFUNCTYPE(ctypes.c_bool, ctypes.c_void_p, ctypes.c_void_p)
    def visit(hwnd, _lparam):
        pid = ctypes.c_ulong()
        user32.GetWindowThreadProcessId(hwnd, ctypes.byref(pid))
        if pid.value == process_id and user32.IsWindowVisible(hwnd):
            handles.append(hwnd)
        return True
    user32.EnumWindows(callback_type(visit), 0)
    for handle in handles:
        # WDA_EXCLUDEFROMCAPTURE. Unsupported Windows versions fail safely.
        user32.SetWindowDisplayAffinity(handle, 0x00000011)
    kernel32.SetLastError(0)

def main():
    if not (DIST / 'index.html').is_file():
        raise SystemExit('Secure Scholar application files are missing. Reinstall the complete package.')
    server = ThreadingHTTPServer(('127.0.0.1', 0), StaticHandler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    api = DesktopApi()
    url = f'http://127.0.0.1:{server.server_port}/index.html'
    window = webview.create_window(APP_NAME, url, js_api=api, width=1180, height=780, min_size=(420, 640), confirm_close=True, background_color='#f3f7f3')
    api.window = window
    window.events.loaded += request_capture_exclusion
    storage = Path(os.environ.get('LOCALAPPDATA', Path.home())) / 'SecureScholar'
    storage.mkdir(parents=True, exist_ok=True)
    try:
        webview.start(gui='edgechromium' if sys.platform == 'win32' else None, debug=False, private_mode=False, storage_path=str(storage))
    finally:
        server.shutdown()
        server.server_close()
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
