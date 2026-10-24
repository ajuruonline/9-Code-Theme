# Open Scholar Ultra Backup

Ultra Backup is the host-level recovery and portability subsystem in Open Scholar Theme 20. It is derived from the strongest existing implementation in the user's archive: **9 Import, Export & Backup Manager 2.2.0 — Bluehost/shared-host hardened**.

## Capabilities

- exact post, page, custom record, user, category/term, media, menu, ACF group and Elementor template selection;
- category branches, user-authored content and relationship dependencies;
- complete records and complete portable-site packages;
- installed plugin code plus declared options, tables, post types, taxonomies and dependencies;
- secret/credential redaction unless an administrator explicitly includes them;
- SHA-256 payload integrity maps and preview-first import;
- update, skip or duplicate conflict policies;
- plugin rollback packages and staged plugin import;
- source URL/database-prefix remapping for migrations;
- database SQL and `wp-content` disaster snapshots;
- downloadable archives, scheduled profiles and optional mounted/synchronised Drive folders;
- browser upload or restricted cPanel/server-path import for large packages;
- Bluehost/shared-host segmented execution and real filesystem/ZIP/database diagnostics.

## Safety boundary

Normal restore uses the package preview and selective importer. A disaster snapshot's raw SQL and `wp-content` archive are emergency recovery artifacts; they are deliberately not executed casually by the selective importer. Use the hosting recovery tool or a qualified administrator for a destructive whole-database replacement.

The standalone Nine Backup Manager receives priority when active, so the theme never starts a second scheduler or registers duplicate handlers.

## Plugin integration

Open Scholar plugins should register durable assets through `nine_backup_plugin_registry`. See `theme/nine-code/core/ultra-backup/NINE-PLUGIN-INTEGRATION.md`. A plugin remains the owner of validation and business semantics; Ultra Backup only packages and restores declared portable state.
