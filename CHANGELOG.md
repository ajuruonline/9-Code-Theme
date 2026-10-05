# Changelog

## Unreleased

## Plugin 11.0.0 — Universal Data Manager
- **Data Workspace**: one mobile-first admin for every data type: WordPress core, custom post types, taxonomies, users, ACF/SCF fields (group, repeater, flexible content, gallery, file, relationship, post object, user, taxonomy), registered and discovered meta, and every Nine Code app.
- **Provider contract v1** (`docs/DATA-PROVIDER-CONTRACT.md`): identity/version/migrations, entities, fields, storage, validation/sanitization, read/write/permission/publish/transaction callbacks, export/import transforms, protected and secret fields. Open Scholar 4.6.0 and Conference.lat 1.7.0 ship dedicated providers. Teaching Player, LectureBoard, Live Lecture and Workshop progress use bundled adapters.
- **AI export/import**: JSON package (records + schema + instructions + stable `_ref` ids and revision hashes), XLSX (with Guide sheet) and CSV; staged imports with field-level preview and before/after comparison, conflict detection, selective apply, batching (200 per request), explicit create/trash/status permissions, one History entry per import, undo and downloadable error reports.
- **Security**: `manage_ninecode_data` plus per-record WordPress/provider capabilities, REST nonce auth, object-type verification, mass-assignment guard, structural/secret key protection, kses for users without `unfiltered_html`, CSV formula guard, exit-guarded staging files, scope locks, compensating rollback, audit history table `{prefix}ncd_history`.
- **Compatibility**: `ninecode/v1/data/*` routes, the `ninecode-universal-data` page, admin-post actions and draft filters are preserved; the draft import route is now preview-only and the draft's unrestricted meta import was removed. No option names, meta keys, post types, taxonomies or hooks changed.
- **Tests**: integration suites (`tests/integration/run.sh`: 349 checks) and the Data Workspace end-to-end test (`tests/e2e/data-workspace.mjs`: phone and desktop).
- Posts and Pages now keep only Save/Save Draft and Publish/Update visible in the editor top bar; every other editor/plugin action, including Edit with Elementor, is routed through the existing hamburger menu on desktop and mobile.

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
