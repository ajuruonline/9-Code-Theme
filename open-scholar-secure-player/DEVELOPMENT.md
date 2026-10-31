# Secure Scholar v1 development

## Build and checks

```sh
python3 build.py
node --test tests/crypto.test.mjs tests/validate_build.mjs
node --check src/crypto.mjs
node --check src/ledger.mjs
node --check src/app.js
python3 -m py_compile desktop/secure_scholar.py
```

`build.py` embeds PDF.js, the Open Scholar cap, CSS and application code into one application file, then copies that exact build into the WordPress plugin. This is the online/offline compatibility invariant.

## Windows

Build on a Windows 10/11 runner using `desktop/Build_Secure_Scholar_EXE.ps1`, or dispatch `.github/workflows/build-windows.yml`. Do not distribute an unsigned or untested executable as a production release.

## Release priorities

1. Real Android, iPhone, Windows and WordPress staging acceptance.
2. Signed multi-resource course and offline-website bundles.
3. Optional server authority for global credits, authoritative time, revocation and renewal.
4. Windows code signing and installer packaging.
5. Native Android/iOS editions only if system-level controls justify them.
6. Large-video range decryption to avoid whole-file browser memory use.
7. Accessibility design for authorised selectable text and screen-reader access.
