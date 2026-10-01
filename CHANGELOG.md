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

## Theme 16.1.0 / Plugin 10.1.0
**Nine Code Data 10.1.0**
- **Visual (WYSIWYG) Post Content editor** in the Post Editor: Add block popup for images (upload or media library), galleries,
  headings, lists, quotes, buttons, tables, video/audio/files, columns and a **Form** block; Code mode for raw markup.
- New **Form** block (also available in the native editor); renders any Form Manager form on the public site.
- Fixed: saving could overwrite edited content with stale "Gutenberg / 9 Elements" field values.
- Fixed: "Unsaved" badge was always visible.
- WordPress Plugin Check: 118 errors -> 0 (prepared SQL identifiers, escaping, file APIs, i18n, readme.txt, access guards).
- Plugin updater now requires `update_plugins` and respects `DISALLOW_FILE_MODS`.

**Nine Code theme 16.1.0**
- Fixed: page and front-page templates printed the title twice; title now aligns with the content column.
- Added: comments on posts/pages (respecting per-post "Comments" display setting), phone-safe side padding, comment form styling.
- Fixed: menu links inherited drawer styling (stray lines); skip link shadow.
- Proper 1200x900 theme screenshot; templates renamed to "Nine Code ...".
- Audit tooling: `bin/audit.sh`, `bin/plugin-check.sh`, `docs/AUDIT.md`.
