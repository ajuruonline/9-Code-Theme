# Sprint 0 baseline — Open Scholar Secure Player (from Secure Scholar v1.0.0)

Source: handoff `Open_Scholar_Secure_Player_Codex_Handoff_2026-10-31.zip` → `source/Secure_Scholar_v1/`.
Original archives in the handoff were not modified; this tree is the development copy.

## Build reproducibility (fixed)
- Blocker: `build.py` required `../vendor/pdf.mjs`, `../brand.png`, `../original-cap.png` outside the tree, and
  `CODEX_PRIMARY_RUNTIME_NODE_MODULES` for PDF.js.
- Fix: PDF.js is vendored in `vendor/` from npm `pdfjs-dist@5.6.205` (`legacy/build`, Apache-2.0, licence in
  `vendor/PDFJS-LICENSE.txt`). The npm build was checked against the PDF.js already embedded in the shipped
  `dist/index.html` (full module match). The cap is `assets/original-cap.png`, byte-identical to the cap embedded
  in the shipped build (`dist/icon-512.png`). It is recovered from the shipped build, not from an official brand
  master file; replace it with the official master if one is supplied.
- `brand.png` was only checked for existence and never used, so the gate was removed.
- `vendor/LOCK.json` holds SHA-256 hashes for every build input; `build.py` refuses to build on mismatch.
- Result: `python3 build.py` regenerates `dist/index.html` **byte-identical** to the shipped file, and the
  WordPress `app/` copy is identical to `dist/`.

## Checks at baseline (this environment)
| Check | Command | Result |
|---|---|---|
| Node unit + build tests | `node --test tests/crypto.test.mjs tests/validate_build.mjs` | 16 pass, 0 fail |
| Browser protect→download→open flow (390px) | `SECURE_SCHOLAR_URL=http://127.0.0.1:8765/ node tests/ui_flow.mjs` | passed (Chromium) |
| JS syntax | `node --check src/{crypto.mjs,ledger.mjs,app.js}` | pass |
| Python syntax | `python3 -m py_compile desktop/secure_scholar.py` | pass |
| PHP syntax | `php -l wordpress/secure-scholar/secure-scholar.php` | no syntax errors |

## Not verified
- Windows EXE (none built; no Windows runner used).
- Real Android/iPhone devices, WordPress staging, current Open Scholar theme.
- Online licence authority (not present in v1).
- No v2 `.osb`/`.oslicense` code exists in the handoff; none is claimed.
