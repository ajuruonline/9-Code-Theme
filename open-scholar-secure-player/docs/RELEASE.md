# Release guide — Open Scholar Secure Player

## Outputs for each release
1. **WordPress plugin ZIP** — `release/Open_Scholar_Secure_Player_Plugin_v<V>.zip`. Folder inside the ZIP is `secure-scholar/` (WordPress requires the slug). Install: WordPress → Plugins → Add New → Upload Plugin → activate.
2. **Windows EXE** — `release/OpenScholarSecurePlayer.exe` plus `.sha256`. Built on Windows only:
   - Push to `claude/loving-maxwell-ny63r3` (or any branch in the workflow trigger), or push a tag `open-scholar-secure-player-v<V>`.
   - Download the artifact `OpenScholarSecurePlayer-Windows` from the workflow run page.
   - Verify with `Get-FileHash OpenScholarSecurePlayer.exe -Algorithm SHA256` against the `.sha256` file.
3. **Development pack ZIP** — `release/Open_Scholar_Secure_Player_Development_Pack_v<V>.zip` (source, tests, vendor locks, docs, this file).

## Build steps
```bash
cd open-scholar-secure-player
python3 bin/package.py          # rebuilds dist/, writes both ZIPs and SHA256SUMS.txt into release/
```
Before packaging run the tests in `HANDOFF.md` section 3. A release is not valid if any test fails.

## Before calling a release "done"
- All tests pass (record exact counts).
- EXE run on a clean Windows 10/11 machine: opens window, opens a PDF, plays a video, protects and opens a file.
- Plugin installed on a WordPress staging site with the current Open Scholar theme; `/secure-scholar/` loads; owner route requires sign-in.
- Security items T11–T12 in `docs/PROGRESS.md` are done, or the release notes say they are not.
- Version numbers match in `package.py`, the plugin header, `CHANGELOG.md` and `src/index.html` footer.
