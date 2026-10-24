# Changelog

## Open Scholar Theme 20.1.3
- Adds a final high-specificity no-blue lock for current menu rows, submenu focus states and current-menu arrows, including host-injected WordPress colour-scheme rules.
- Assigns distinct first-party application Dashicons server-side as a fallback before the enhanced SVG icon system runs.

## Open Scholar Theme 20.1.2
- Adds an explicit Control Centre `+` / `−` submenu control that reliably reveals Settings, Appearance, Tools, Plugins, Users and Updates on touch screens.
- Replaces repeated app symbols with contextual icons for lectures, courses, libraries, textbooks, publications, presentations, events, users and system controls.
- Uses clear `+` / `−` open-close controls in the Open Scholar toolbar and launcher, and a browser-style icon for View Site instead of a house glyph.

## Open Scholar Theme 20.1.1
- Adds direct Google Drive OAuth connection and automatic, server-side Drive uploads for completed backup packages; Drive-mounted-folder copies remain supported for hosts where that is preferred.
- Streams Drive uploads on cURL-enabled hosts to avoid loading full-site packages into PHP memory, and retains a small-package compatibility fallback.
- Shows the tracked Google Drive state for each archive and deletes the matching remote copy when an archive is deleted; a clear warning is shown if the remote copy cannot be removed.
- Adds a branded Control Centre for WordPress settings, appearance, tools, plugins, users and updates without returning those items to the app-focused sidebar.
- Replaces duplicated application icons, blue focus/active treatments and the stock toolbar treatment with distinct Open Scholar symbols and controls; makes the mobile application drawer reliably touch-scrollable.
- Makes Quick Actions configuration discoverable directly in its launcher with an Arrange action.

## Open Scholar Theme 20.1.0
- Adds immediate accessible backup preparation feedback, prevents duplicate submissions and opens collapsed required fields when validation fails.
- Replaces the stock dashboard with an Open Scholar campus workspace, searchable first-party app tiles and site/upload shortcuts.
- Curates the sidebar to 9/Open Scholar apps, keeping maintenance routes accessible through Quick Actions.
- Fixes menu icon/text overlap and overrides legacy black/blue interaction styles with the emerald application shell.
- Gives the launcher a matching dark emerald tile layout and consistent local SVG icons.

## Open Scholar Theme 20.0.1
- Restores the mobile launcher, plugin ZIP install/replace workflow and site preview shortcuts across administration, post editing and signed-in site views.
- Restores the full 9 Code workspace styling with Open Scholar green tokens, cards, tables, menus, inputs and editor chrome.
- Opens mobile Quick Actions as a bounded bottom panel and wraps settings tabs to avoid horizontal navigation.
- Consolidates extra Gutenberg toolbar actions, including Elementor and search, into the editor popup while keeping save/publish, meta fields and essential block controls accessible.

## Open Scholar Theme 20.0.0
- Recasts the theme as a lean host for independently released Open Scholar plugins; Data Manager, AI tools, builders, forms and custom blocks are no longer loaded or shipped by the theme.
- Keeps the stable `nine-code` install folder and legacy machine identifiers for safe in-place upgrades, while leaving application plugins active and their data untouched.
- Adds a small plugin discovery/capability contract and shared design-token API so current and future Open Scholar plugins can integrate without being absorbed into the theme.
- Adds mobile-first Open Scholar administration with closed-by-default, explained, alternating red/green sections.
- Keeps Jetpack active for its app and background services while suppressing promotions, dashboard/editor clutter and duplicate social metadata; its settings remain accessible.
- Adds lightweight meta description, Open Graph, Twitter/X card, robots and schema output, with per-post sharing fields and automatic handoff to recognized SEO plugins.
- Ports the proven 9 Import, Export & Backup Manager 2.2.0 Bluehost-hardened engine into the Ultra host layer: selective post/page/category/user backups, plugin packages and context, complete records, disaster database/wp-content snapshots, integrity validation, preview-first imports, archives, schedules, cPanel path import and hosting diagnostics.
- Keeps all Ultra Backup controls mobile-first and closed by default; an active standalone Nine Backup Manager remains authoritative to prevent duplicate schedules or jobs.
- Retires theme-owned 9 Blocks, AI designer/output, metadata workflow, builders, shortcodes, front-end editor and header/footer application modules from the release package.
- Builds `open-scholar-theme-20.0.0.zip` with a stable `nine-code` root.

## Open Scholar Theme 19.0.0
- Rebrands the visible theme, login, profile, admin bar, dashboard, and administration as Open Scholar while preserving internal slugs, options, hooks, permissions, and stored data.
- Adopts the canonical Open Scholar palette and plugin compatibility boundary.
- Removes theme-owned public header/footer output and runtime loading; standard hooks remain for 9stagram/Instagram or another Open Scholar shell plugin.
- Makes registered plugin menus and dashboard content visible even when an older installation saved the former filtering options.
- Expands Data Manager navigation so data editing, bulk editing, fields, field groups, taxonomies, AI exchange, versions, backups, and health are directly discoverable.
- Keeps every settings section closed by default, mobile-first, explained, and alternately red/green for navigation.
- Builds one safe replacement package: `open-scholar-theme-19.0.0.zip`, containing the stable `nine-code` folder.

## Theme 18.0.0 (Data Manager bundled, one package)
- **One package.** Data Manager now lives inside the Nine Code theme (`theme/nine-code/data-manager`). Installing or updating the theme installs and activates it. The separate Nine Code Data plugin is no longer shipped.
- **Automatic retirement.** Once the theme is active, the old "9 Data Manager" plugin and the separate Nine Code Data plugin are deactivated on the next admin page. No data changes: option names, meta keys, post types, taxonomies, tables, REST routes, hooks and filters are the same.
- **Theme events instead of plugin hooks.** Activation has no plugin hook in a theme. Installing runs from `after_switch_theme` and from an `admin_init` check against the stored version, so the history table and roles are in place on the first admin request after an update.
- **Plugin-only APIs are gone** from the Data Manager code. `bin/lint.sh` now keeps activation hooks, `plugin_dir_*`, `plugins_url` and textdomain loading out of the theme. The only exception is `plugin_basename()` in the AI Manager, which updates other plugins.
- **One version.** Theme and Data Manager are both 18.0.0. `bin/lint.sh` checks the theme header, both constants and the Data Manager readme stable tag.
- **Waiting screens** in the Data Workspace and Post Editor show an animated graduation cap (tassel swinging, reduced motion respected) instead of plain loading text.
- **Build and tests:** `bin/build.sh` produces one zip, `nine-code-theme-18.0.0.zip`. `bin/test-upgrade.sh` rehearses the full path from the old 3-package install, through the separate 17.1.0 plugin, to the 18.0.0 theme.
- **Trade-off:** Data Manager's post types, shortcodes and blocks are registered only while Nine Code is the active theme. Data stays in the database, but those screens and shortcodes stop working under another theme.

## Theme 17.1.0 / Plugin 17.1.0
- Every suite settings/data-entry section starts closed; the user chooses what to open and no saved tab state reopens it.
- Sections, tabs and entry groups alternate subtle red/green navigation colours and stack into touch-friendly controls on phones.
- Menu & UI, AI Manager, Data Workspace, Category Manager and Post Manager now expose clearer section/field descriptions where available.
- Added `docs/SETTINGS-UI-CONTRACT.md`, shared assets and an opt-in filter for future plugins, plus a static regression gate in `bin/lint.sh`.
- Every collapsible panel in the Header & Footer and Style settings continues to start closed; the footer settings no longer reopens the last tab on load.

## Theme 17.0.0 / Plugin 17.0.0
- The quick-action launcher sits in the middle of the right edge on desktop, phones and the front end, instead of the bottom corner where it covered content.
- Versions of the theme and Nine Code Data are aligned at 17.0.0.

## Plugin 11.0.0 — Universal Data Manager
- **Every record type is visible**: post types without an admin screen are discovered too (read-only where their plugin protects them); WordPress internals stay hidden.
- **Comments** are a data type: edit author, email, website and text; approve, hold, spam or trash through WordPress' own moderation functions (status changes need the explicit permission).
- **Forms**: form questions are editable with the Form Manager's own rules (an empty form is refused); visitor responses are listed with their answers, read-only. The visitor IP stays a hash that is never shown.
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
