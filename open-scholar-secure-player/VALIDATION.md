# Secure Scholar v1 validation - 31 October 2026

## Passed in this build environment

- Self-contained application build completed: 4,328,079-byte HTML application.
- JavaScript syntax checks passed for crypto, local ledger and application modules.
- Python Windows-host source compiled successfully.
- 16 Node tests passed with zero failures.
- All three release ZIP archives passed full CRC/integrity checks.
- WordPress and Windows/desktop packages were verified to contain the exact same application build.
- The build contains the simple workflow controls, unique HTML IDs and required WordPress/Open Scholar standard entry points.

The cryptographic tests cover multi-chunk AES-256-GCM round trip, independent password file, optional PIN, signed-rule tampering, publisher pinning, file binding, ciphertext corruption, plaintext and truncation rejection, supported HTML packaging, expiry, future start, installation binding, clock rollback, invalid policy rejection and context-bound key wrapping.

## Environment limits

- A real browser user-interface run could not start because this execution environment blocks Chromium's required operating-system socket. The complete Playwright owner-to-recipient test is included in `tests/ui_flow.mjs` for staging or CI.
- PHP CLI was not installed, so the WordPress file could not be passed through `php -l` here. Its required entry points and generated package contents were checked, but live activation/routing remains a staging requirement.
- No Windows runner was available. The Windows host source and EXE build recipe are included, but `SecureScholar.exe` was not produced or run here.
- No live OpenScholar.ng staging site, Android phone, iPhone or Windows 10/11 device was changed or tested.

## Required release acceptance

1. Activate the plugin on an Open Scholar staging site and refresh permalinks.
2. Verify the public and signed-in owner routes through the existing Open Scholar phone shortcut.
3. Run `node tests/ui_flow.mjs` in a normal browser-capable CI environment.
4. Test PDF, MP4, HTML, expiry, credits, optional PIN and installation binding on Android and iPhone.
5. Build the EXE on Windows, test direct Office preparation, test offline restart and verify capture-exclusion behaviour with common Windows capture tools.
6. Code-sign the Windows executable before public distribution.
