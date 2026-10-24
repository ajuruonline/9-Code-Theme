# Releasing

1. Bump both theme version declarations:
   - `Version:` in `theme/nine-code/style.css`
   - `NCU_THEME_VERSION` in `theme/nine-code/functions.php`
2. Add a section to `CHANGELOG.md` and update compatibility documentation when the host contract changes.
3. Run `bin/lint.sh` and `bin/build.sh`.
4. Inspect the ZIP and verify that it contains neither `data-manager/` nor retired application modules.
5. Upload `dist/open-scholar-theme-*.zip`. Its root remains `nine-code`, the stable install slug, so an upload replaces the prior theme in place.

Data Manager and other Open Scholar applications have independent plugin releases and versions. Do not couple their versions to the theme.
