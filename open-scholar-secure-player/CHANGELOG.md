# Changelog

## 1.1.0 — Open Scholar Secure Player (in progress)
- Sprint 0: clean-room build. PDF.js 5.6.205 and the cap are vendored in-tree with SHA-256 locks; `build.py` reproduces the shipped `dist/index.html` byte-for-byte.
- Windows host: window and product name are Open Scholar Secure Player; serves favicon.ico; `--self-test` checks the bundled app without a window. The release build runs it before publishing.
- Official OpenScholar cap (512 px PNG) and OpenScholar.ico are now used as the header logo and browser favicon, replacing the cap recovered from the v1.0.0 build. Checksums are in vendor/LOCK.json.
- Renamed the visible product to Open Scholar Secure Player (protected file formats and routes unchanged).
- New home: Open a file (no password), Open a protected file, Protect a file, My reading, plus a "How it works" guide.
- Normal file open for PDF, MP4/WebM, PNG/JPG and saved web pages, using the same viewer. No protections are applied to ordinary files, and nothing is uploaded.
- Your place (PDF page, video time) is saved on this device; My reading lists recent files and can forget them.
- Friendly message for unsupported types.
- Every protected-file setting now has a plain-language explanation; Advanced settings are labelled optional.
- Tests: `tests/plain.test.mjs` (unit), `tests/plain_flow.mjs` (browser, 320–1280px, no horizontal scroll), `tests/ui_flow.mjs` (protect → download → open).

## 1.0.0 - 31 October 2026

- Introduced Secure Scholar and its independent v1 format.
- Replaced the technical two-panel interface with two simple actions.
- Added `.scholar` content and `.scholarpass` password files.
- Added automatic local publisher identity and optional private PIN.
- Added expiry, opening credits, session time, installation binding and seeking restriction.
- Added PDF, video, image and self-contained HTML viewing.
- Added recipient watermark, anti-copy/print/drag controls and static-web curtain.
- Added WordPress delivery through the existing Open Scholar phone shortcut.
- Added Windows WebView2 host, Office preparation and capture-exclusion request.
- Added Windows EXE build script and GitHub Actions workflow.
