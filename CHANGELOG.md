# Changelog

## Theme 16.0.0 / Plugin 10.0.0 — Nine Code baseline
- One product named **Nine Code**: theme (public site + former 9Core) and plugin (former 9 Data Manager).
- Removed 9Core's duplicate legacy data engine (the plugin owns data).
- Safe in-place upgrade from the old three-package install (`bin/test-upgrade.sh`).
- Fixed: Header & Footer settings page ran out of memory (recursive settings migration).
- Fixed: undefined `$icon` and null page titles raising PHP warnings/deprecations in wp-admin.
- Fixed: Header & Footer engine never rendered on the public site; per-post title/meta overrides were never applied.
- Added: native header/navigation/footer and post titles; accessible mobile menu.
- Added: real test suite (public, admin crawl, editor acceptance incl. save-to-database, upgrade), lint, build, CI.
- Unified text domains: `nine-code`, `nine-code-data`. Internal slugs/options/meta keys unchanged.
