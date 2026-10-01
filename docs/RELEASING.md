# Releasing

1. Bump the version in **one** place per piece (lint fails if header and constant disagree):
   - Theme: `Version:` in `theme/nine-code/style.css` and `NCU_THEME_VERSION` in `functions.php`
   - Plugin: `Version:` and `NINE55_ULTRON_DATA_VERSION` in `plugin/nine-code-data/nine-code-data.php`
2. Add a line to `CHANGELOG.md`.
3. `npm test && bin/test-upgrade.sh && bin/build.sh`
4. Upload `dist/*.zip`. The zip root folder is the install slug, so uploading replaces the old version in place.

Theme and plugin version independently; changing one never requires re-uploading the other.
