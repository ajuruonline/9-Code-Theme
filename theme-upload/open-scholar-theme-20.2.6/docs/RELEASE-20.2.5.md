# Open Scholar Theme 20.2.5 — Navigation & Data Recovery Separation

**Brand identity:** Open Scholar green `#14532D` unchanged. **Status:** packaged for deployment verification.

## Navigation

- The right launcher retains **Dashboard first**, with the four-tile `dashicons-dashboard` symbol.
- **Top Bar** now uses the clearly different `dashicons-admin-settings` gear symbol.
- The left Open Scholar menu starts at 10% of viewport height and extends to the bottom (90% high), with an independently scrollable menu body.
- On every open, WordPress's current submenu markers take precedence over top-level parent markers. The active submenu expands and scrolls into view. The current item gets a green active-state indicator; the underlying edit page does not jump.
- Menu entries, capabilities, routes, post types and plugin submenus remain owned by WordPress.

## Distinct data products

**Export & Import (portable data transfer)** has a separate first-class admin menu / Dashboard app (`osch-export-import`):
- Direct ZIP downloads: posts/pages/CPTs, users/profiles, media, terms, menus, site settings, **All Records**, **All Export** (supported portable data and plugin bundles).
- Every direct transfer requires `manage_options`, a valid action-specific nonce and password reauthentication. Secrets, account passwords and active sessions are not intentionally exported.
- Downloaded ZIPs are not encrypted. The package includes structured JSON and generated CSV/XLSX reference reports where applicable. Download copies are removed from temporary server archives on completion/shutdown.
- All Import accepts only compatible 9 Backup/Open Scholar packages. Packages are inspected and shown for preview before record conflict handling. Plugins may need to be installed/staged first; unsupported CPTs/taxonomies may be skipped.
- WordPress WXR XML import/export continues to use native WordPress Tools.

**Backup & Restore** has its own first-class admin menu / Dashboard app (`osch-backup-restore`):
- Server: an explicit **database.sql + wp-content ZIP disaster snapshot**, or a portable site package.
- Google Drive: an explicit database/site snapshot action after OAuth connection; supports configured automatic upload and scheduled profiles. The Google Drive response is checked; an uploaded marker cannot be invented. A failed Drive upload still leaves the server copy (subject to hosting availability).
- The backup archive list, downloads, schedules, settings and host diagnostics remain available.
- A disaster snapshot is **not** fed into the selective importer as raw SQL. A deliberate full server/database restoration remains an administrator task.

## Ultron Rabbit hardening

- Reject traversal, absolute paths, drive names, filesystem symlinks and malformed entries before ZIP extraction; add 50,000-entry and 20 GiB expanded-size limits.
- Keep the newest created archive during retention pruning, including when filesystem timestamps tie.
- Temporary direct exports are removed on PHP shutdown (including interrupted transfers when shutdown handlers are run).
- Existing standalone `Nine_Backup_Manager` installations continue to take precedence over the theme copy to prevent duplicate jobs and menus. Older standalone versions may not include the new theme workflows until updated or deactivated.

## Deployment caveats

- **External services were not connected in the development test environment.** An authorised administrator must verify Google OAuth and a real Drive upload on the target institution site.
- Hosting-specific capacity, WordPress post types, plugin ownership registries, web server access rules (especially Nginx), and large-database recovery require production/staging validation.
- For a database snapshot restore, use a staging server first. WordPress core binaries, `wp-config.php`, web server configuration and third-party service credentials may require separate steps.
