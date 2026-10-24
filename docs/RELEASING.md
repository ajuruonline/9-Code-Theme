# Releasing

1. Bump the version in **all four** places. Theme and Data Manager share one version number, and lint fails if they differ:
   - `Version:` in `theme/nine-code/style.css`
   - `NCU_THEME_VERSION` in `theme/nine-code/functions.php`
   - `NINE55_ULTRON_DATA_VERSION` in `theme/nine-code/data-manager/bootstrap.php`
   - `Stable tag:` in `theme/nine-code/data-manager/readme.txt`
   Also update the changelog in `theme/nine-code/data-manager/readme.txt` and `docs/DATA-MANAGER-APP-INVENTORY.md`.
2. Add a section to `CHANGELOG.md`.
3. `npm test && bin/test-upgrade.sh && bin/build.sh`
4. Upload `dist/nine-code-theme-*.zip`. The zip root folder is the install slug, so uploading replaces the old version in place.

There is one package. Installing or updating the theme installs Data Manager too.
