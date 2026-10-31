# HANDOFF — Open Scholar Secure Player

Read this first. It says what exists, what was verified, what was not, and exactly what to do next.
Last updated: 31 October 2026 (end of the first working session).

## 1. What this project is
Open Scholar Secure Player is the next edition of **Secure Scholar v1.0.0** (the original handoff source is `Open_Scholar_Secure_Player_Codex_Handoff_2026-10-31.zip`, kept unchanged). It is a self-contained browser app (also packaged as a WordPress plugin app and a Windows WebView2 window) for:
- opening ordinary PDFs, videos, pictures and saved web lessons with no password (new in 1.1), and
- protecting files with `.scholar` (encrypted content) + `.scholarpass` (signed access/password file) and opening protected files.

Product principles from the owner: mobile-first, simple for Nigerian learners on low data, the tutorial built into the product, white background, dark headings, Open Scholar green, the official cap, "Powered by OpenScholar.ng".

## 2. Where everything lives
| Thing | Location |
|---|---|
| Working copy (this session) | `9-Code-Theme` repo, folder `open-scholar-secure-player/`, branch `claude/loving-maxwell-ny63r3` |
| Second copy for the owner's new repo | `ajuruonline/Secure-Scholar`, branch `open-scholar-secure-player` (main is untouched) |
| Google Drive folder | `$$--Secure Scholar` (starred; orange colour was set but could not be verified via API) — https://drive.google.com/drive/folders/11TY9qQB434UeN5_4oD2HzOj1SskGlT0q |
| Windows EXE build | GitHub Actions run 41246118270 on `claude/loving-maxwell-ny63r3` — artifact `OpenScholarSecurePlayer-Windows` (≈14 MB, contains `OpenScholarSecurePlayer.exe` and `.sha256`) |

Commits on `claude/loving-maxwell-ny63r3` (newest first): `5037240` Windows build on Python 3.12 + pywebview 6.2.1; `ad698e2`/`8a47478` Windows workflow + self-test; `6bbb5b2` LF line endings; `b0d2b4f` official cap and favicon; `761c199` 1.1 slice (normal opening, home, settings help); `2d86f09` Sprint 0 baseline.

## 3. How to build and test (every command used so far)
```bash
cd open-scholar-secure-player
python3 build.py                       # rebuilds dist/ and the WordPress copy; refuses on checksum mismatch
node --test tests/crypto.test.mjs tests/validate_build.mjs tests/plain.test.mjs   # 19 tests
node --check src/app.js && node --check src/plain.mjs
python3 -m py_compile desktop/secure_scholar.py
php -l wordpress/secure-scholar/secure-scholar.php
# Browser tests (need Chromium; Playwright is pre-installed in the sandbox):
(cd dist && python3 -m http.server 8765 --bind 127.0.0.1) &
SECURE_SCHOLAR_URL=http://127.0.0.1:8765/ node tests/ui_flow.mjs      # protect -> download -> open
SECURE_SCHOLAR_URL=http://127.0.0.1:8765/ node tests/plain_flow.mjs   # ordinary open, 320–1280 px
```
Inputs are verified by `vendor/LOCK.json` (PDF.js 5.6.205 and the official cap / favicon). **Never edit `vendor/` or `assets/` by hand.** Change `src/` and run `build.py`.
Windows: `desktop/Build_Secure_Scholar_EXE.ps1` (run on Windows, Python 3.12). It runs `build.py`, makes the EXE with PyInstaller and runs `OpenScholarSecurePlayer.exe --self-test`.

## 4. Verified (with evidence)
- `build.py` reproduces the shipped `dist/index.html` byte-for-byte from in-tree inputs (Sprint 0).
- 19/19 Node tests pass.
- Protected flow (protect → download → open) passes in Chromium at 390 px.
- Ordinary open passes at 320, 360, 390, 430, 768 and 1280 px: no horizontal scroll, PDF page navigation, resume to page 2, My reading list, unsupported-type message, picture and saved web lesson opening.
- The Windows EXE build succeeds on `windows-latest` (run 41246118270), including the self-test step.
- Self-test logic verified on Linux with a stub window library (serves the app, favicon is valid ICO, source files are not served).

## 5. NOT verified — do not claim these
- The EXE has **not been run on a Windows machine**: no WebView2 window test, no Office conversion test, no capture-exclusion test.
- The EXE is **not code-signed** and is a development build, not a public release.
- No real Android/iPhone/PWA testing. No WordPress staging test. The WordPress PHP plugin still says "Secure Scholar" (only the visible app was renamed).
- Security: the v1 trust model is weak (no pinned publisher key by default; the access secret travels inside `.scholarpass`; local counters can be reset). Nothing here fixes this yet.
- Drive: only `BASELINE.md` and `CHANGELOG.md` were uploaded. The full source, screenshots and EXE are not in Drive (too large for the connector) — they are in the GitHub repos.
- The orange folder colour in Drive could not be read back.

## 6. Known issues (open)
1. PDF.js warns that the PDF standard font (Helvetica) falls back to a system font. Fix: embed PDF.js standard font data.
2. Text selection is disabled on the whole body (`user-select:none` in `style.css`), which also blocks reading text in ordinary files. Allow selection for ordinary files.
3. The PDF viewer has no zoom-to-fit, search, outline, bookmarks or notes yet.
4. Video in ordinary files has no speed control, captions or chapters yet.
5. The banner image the owner supplied (`images/2.png`, "Insert, watch and learn only") is **not** used. It contains phone numbers and flash-drive steps that may not match the app. Ask the owner before using it.
6. The `Nine Code` CI on `claude/loving-maxwell-ny63r3` (root `.github/workflows/ci.yml`) fails on every push. This was already failing before this work and is unrelated to Open Scholar. Do not assume it reflects this project.
7. Windows workflow trigger: it runs on pushes to `claude/loving-maxwell-ny63r3` and on `open-scholar-secure-player-v*` tags. The workflow file must stay in the repo root `.github/workflows/`.
8. `.gitattributes` pins LF line endings. Without it the Windows checkout breaks the SHA-256 checks.

## 7. Next steps, in order
1. **Ask the owner** whether the banner (`2.png`) should appear on the home screen, and check the Drive folder colour.
2. Finish Sprint 1 viewer: fix the standard font (issue 1), allow text selection in ordinary files (issue 2), add zoom-to-fit, search and outline for PDFs, and video speed and chapters.
3. Sprint 2 security (do this before any real release): pin the publisher public key, stop shipping the access secret inside `.scholarpass` when a PIN is set, make the publisher key backup and recovery explicit, and add tests for each.
4. WordPress: rename the plugin (keep the `secure-scholar` slug, routes and shortcodes), then test the plugin on a staging Open Scholar site.
5. Windows: run `OpenScholarSecurePlayer.exe` on a clean Windows 10/11 machine; test video, PDF, Office conversion with LibreOffice; then decide on code signing.
6. Upload the built EXE and the source zip to Drive, and tag a release (`open-scholar-secure-player-v1.1.0`) once steps 2–5 are verified.

## 8. Rules for the next developer or AI
- Work on a branch; commit small; keep `dist/` in sync by running `build.py` (CI and the plugin both depend on it).
- Do not change `.scholar`/`.scholarpass` formats, option names or routes without a migration and a fixture test. v1 files must keep opening.
- Add a test with every fix. Report exact pass/fail counts, not "should work".
- Do not claim an EXE, device, security or signing result unless it was actually run and checked.
- Do not create a pull request unless the owner asks.
- Commit trailers: `Co-Authored-By: Claude <noreply@anthropic.com>` and the session link. Do not put model names in commit messages.
