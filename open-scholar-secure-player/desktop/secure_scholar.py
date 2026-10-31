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
import traceback
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlsplit

import webview

APP_NAME = 'Open Scholar Secure Player'
ROOT = Path(getattr(sys, '_MEIPASS', Path(__file__).resolve().parents[1]))
DIST = ROOT / 'dist'

class StaticHandler(BaseHTTPRequestHandler):
    allowed = {'index.html', 'sw.js', 'manifest.webmanifest', 'icon.svg', 'icon-512.png', 'favicon.ico'}
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

WEBVIEW2_CLIENT = '{F3017226-FE2A-4295-8BDF-00C3A9A7E4C5}'
WEBVIEW2_LINK = 'https://developer.microsoft.com/microsoft-edge/webview2/'

def log_directory():
    base = Path(os.environ.get('LOCALAPPDATA', Path.home()))
    folder = base / 'OpenScholarSecurePlayer'
    folder.mkdir(parents=True, exist_ok=True)
    return folder

def show_message(text):
    """Windowed builds have no console, so errors must be shown in a dialog."""
    if sys.platform == 'win32':
        ctypes.windll.user32.MessageBoxW(0, text, APP_NAME, 0x10)

def report_fatal(reason, error=None):
    log = log_directory() / 'error.log'
    details = traceback.format_exc() if error is not None else ''
    with log.open('a', encoding='utf-8') as handle:
        handle.write(f"[{__import__('datetime').datetime.now().isoformat()}] {reason}\n{details}\n")
    show_message(f"{APP_NAME} could not start.\n\n{reason}\n\nDetails were saved to:\n{log}")

def webview2_installed():
    """The window uses Microsoft WebView2. Check the runtime is present before starting."""
    if sys.platform != 'win32':
        return True
    import winreg
    keys = [
        (winreg.HKEY_LOCAL_MACHINE, rf'SOFTWARE\WOW6432Node\Microsoft\EdgeUpdate\Clients\{WEBVIEW2_CLIENT}'),
        (winreg.HKEY_LOCAL_MACHINE, rf'SOFTWARE\Microsoft\EdgeUpdate\Clients\{WEBVIEW2_CLIENT}'),
        (winreg.HKEY_CURRENT_USER, rf'SOFTWARE\Microsoft\EdgeUpdate\Clients\{WEBVIEW2_CLIENT}'),
    ]
    for hive, path in keys:
        try:
            with winreg.OpenKey(hive, path):
                return True
        except OSError:
            continue
    return False

def self_test():
    """Start the local server without a window and check the bundled app. Used by the release build."""
    from urllib.request import urlopen
    server = ThreadingHTTPServer(('127.0.0.1', 0), StaticHandler)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    try:
        base = f'http://127.0.0.1:{server.server_port}/'
        page = urlopen(base + 'index.html', timeout=10).read().decode('utf-8')
        assert 'Open Scholar Secure Player' in page, 'product name missing from bundled app'
        assert 'id="plain-file"' in page, 'ordinary file opening missing from bundled app'
        icon = urlopen(base + 'favicon.ico', timeout=10).read()
        assert icon[:4] == b'\x00\x00\x01\x00', 'favicon is not an ICO file'
        for blocked in ('../secure_scholar.py', 'build.py'):
            try:
                urlopen(base + blocked, timeout=10)
                raise AssertionError(f'{blocked} must not be served')
            except Exception as error:
                if isinstance(error, AssertionError):
                    raise
        print('self-test passed')
        return 0
    finally:
        server.shutdown()
        server.server_close()

def main():
    if '--self-test' in sys.argv:
        return self_test()
    if not (DIST / 'index.html').is_file():
        raise SystemExit('Open Scholar Secure Player application files are missing. Reinstall the complete package.')
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
    if '--self-test' in sys.argv:
        raise SystemExit(main())
    if not webview2_installed():
        report_fatal(f'The Microsoft WebView2 Runtime is not installed. Install it from {WEBVIEW2_LINK} and open this app again.')
        raise SystemExit(1)
    try:
        raise SystemExit(main())
    except SystemExit:
        raise
    except BaseException as error:
        report_fatal(f'Unexpected error: {error}', error)
        raise SystemExit(1)
