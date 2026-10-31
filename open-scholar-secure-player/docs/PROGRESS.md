# Progress log and task board — Open Scholar Secure Player

Keep this file current. Every session adds a dated entry at the top and updates the task board.
Read `HANDOFF.md` for the full picture; this file is the running record.

## Deliverables (v1.1.0)
| Output | How it is built | Status |
|---|---|---|
| WordPress plugin ZIP `Open_Scholar_Secure_Player_Plugin_v1.1.0.zip` | `python3 bin/package.py` | Built and checksummed. Not yet installed on a WordPress staging site. |
| Windows EXE `OpenScholarSecurePlayer.exe` | GitHub Actions `open-scholar-windows-exe.yml` (windows-latest, Python 3.12) | Built and self-tested by CI (run 41246118270). **Not run on a real Windows machine. Not signed.** |
| Development pack `Open_Scholar_Secure_Player_Development_Pack_v1.1.0.zip` | `python3 bin/package.py` | Built. Full source, tests, vendor locks, docs. |

## Task board
| # | Task | Status | Evidence / where |
|---|---|---|---|
| T0 | Clean reproducible build, pinned PDF.js and cap | Done | `vendor/LOCK.json`; `build.py` reproduces shipped `dist/index.html` byte-for-byte |
| T1 | Rename visible product to Open Scholar Secure Player | Done | `src/index.html`, plugin header, Windows host |
| T2 | Ordinary file opening (no password) with the same viewer | Done (PDF, video, picture, HTML) | `tests/plain_flow.mjs` at 320–1280 px |
| T3 | Self-explanatory home and settings (tutorial built in) | Done for protected settings and home | `src/index.html` hints and "How it works" |
| T4 | Official cap and favicon | Done | `assets/original-cap.png`, `src/favicon.ico` |
| T5 | Windows EXE built on CI | Done | run 41246118270, artifact checked |
| T6 | Windows EXE runs on a clean Windows 10/11 machine | **Open** | needs a Windows tester |
| T7 | PDF standard-font fallback warning | Open | embed PDF.js standard font data |
| T8 | Allow text selection in ordinary files | Open | `style.css` body `user-select:none` |
| T9 | PDF zoom-to-fit, search, outline, bookmarks | Open | viewer |
| T10 | Video speed, captions, chapters | Open | viewer |
| T11 | Publisher key pinning; stop shipping access secret inside `.scholarpass` when PIN set | Open (security, required before release) | `src/crypto.mjs`, `FORMAT.md`, `SECURITY.md` |
| T12 | Publisher key backup and recovery UX | Open | `src/app.js` owner backup |
| T13 | WordPress staging test on Open Scholar site (current theme, cache, PWA) | Open | needs staging access |
| T14 | Decide whether to use the owner's banner image | Open | owner decision (`images/2.png`) |
| T15 | Code signing for the EXE | Open | needs a certificate |
| T16 | Fix the root Nine Code CI failing on every push | Open, unrelated to this app | `.github/workflows/ci.yml` |

## Log
### 31 Oct 2026 — session 1 (this record)
- Built Sprint 0 and the 1.1 slice (T0–T5 above). Commits on `claude/loving-maxwell-ny63r3` in `9-Code-Theme`, mirrored to branch `open-scholar-secure-player` in `ajuruonline/secure-scholar`.
- Windows build fixes: LF line endings (checksum failed on CRLF); pinned Python 3.12 and pywebview 6.2.1 (runner default Python was too new for pywebview 5.4).
- Plugin bumped to 1.1.0 with new name; slug, routes, shortcodes and options unchanged. `tests/validate_build.mjs` updated.
- Added `bin/package.py` to create the three outputs.
- Owner Drive folder `$$--Secure Scholar` created, starred, coloured orange (colour not readable via API).
