# Ultron Rabbit — Open Scholar Theme 20.2.5 QA Audit

Scope: navigation visual state, capability-based app discovery, portable record transfer, server snapshots, Google Drive backup integration and import package security.

## Automated results (isolated development environment)

| Check | Result | Method |
|---|---|---|
| Dashboard icon distinct from Top Bar | PASS | PHP markup contract |
| Nav opens and expands current nested submenu | PASS | Chromium browser 1440×900, 390×844, 320×640 |
| Current nested entry scrolls into visible menu area | PASS | Browser geometry, scrollTop and viewport checks |
| Navigation top 10% / 90% total height | PASS | Browser panel bounds |
| No horizontal overflow at tested widths | PASS | Browser `document.documentElement.scrollWidth` |
| All Export portable preset | PASS | PHP action mock: full_portability / no Drive upload |
| Users-only export | PASS | PHP action mock: users preset |
| Media export includes files | PASS | PHP action mock: include_media_files |
| Server SQL/site snapshot route | PASS | PHP action mock: disaster_snapshot, server redirect |
| Explicit Google Drive snapshot route | PASS (mocked) | PHP action mock: connected Drive, upload success |
| Disconnected Google Drive rejection | PASS | PHP action mock: redirects to error |
| ZIP portable encoding/inspection | PASS | PHP pure-code ZIP creator, Python ZIP reader/CRC check |
| ZIP path traversal rejection | PASS | PHP reflection negative-path tests |
| CSS parse | PASS | tinycss2 stylesheets |
| PHP syntax | PASS | `php -l` on all theme PHP files |
| JS syntax | PASS | `node --check` on all theme JS files |
| WordPress full record import against live MySQL | NOT TESTED | Requires a disposable WordPress staging installation |
| Real Google Drive OAuth and large-file upload | NOT TESTED | Requires institution-approved OAuth/account connection |
| Server disaster recovery on real hosting | NOT TESTED | Requires isolated staging and hosting administrator |
| Compatibility with all installed university plugins | NOT TESTED | Requires exact deployed plugin versions |

## Risk register / release controls

1. **High (deployment):** Full SQL restore is not performed in the theme. Use the backup ZIP in staging and restore through supervised database/file operations. Avoid automatic destructive recovery.
2. **High (security):** Archive ZIPs may contain student/author personal data and plaintext SQL. Keep archives outside public download access; verify Apache, Nginx or IIS blocks public requests to `wp-content/uploads/nine-backup-manager/`. Apache `.htaccess` rules alone do not secure Nginx.
3. **Medium (scale):** One-shot All Export and database+files snapshots may exceed shared hosting resource/time limits. Use the existing safe-mode advanced exporter and staged restore process on large installations.
4. **Medium (compatibility):** The theme relinquishes backup engine ownership when a standalone older `Nine_Backup_Manager` is active. Check the on-screen warning rather than assuming both versions are active.
5. **Medium (portability):** Some plugins' custom data require explicit ownership declarations. Import preview may skip unknown CPTs/taxonomies, opaque third-party settings or installations missing dependencies.
6. **Medium (service):** Google Drive needs configured OAuth client, callback authorization, folder permissions and a successful upload/download/restore drill. Mock tests do not prove live connectivity.
7. **Low (UX):** The active submenu auto-scroll follows WordPress current markers; unusual plugins that supply no correct marker use a page-route fallback and should be reviewed in staging.

## Staging acceptance procedure

1. Install the update on a cloned site with current plugins; retain a safe pre-upgrade backup.
2. Open Dashboard, Export & Import, Backup & Restore and a nested plugin submenu on desktop and mobile.
3. Export two posts, two users and media, inspect ZIP manifest + reports, then import into a clean staging site and compare IDs, media and metadata.
4. Run All Records and All Export, comparing counts to WordPress admin records. Verify role assignments and password reset behaviour.
5. Generate a server database/site snapshot; verify `disaster/database.sql`, `disaster/wp-content.zip`, sizes and archive checksum.
6. Configure Google OAuth and upload a snapshot; verify presence in Drive, then test retrieval under a second authorised admin account.
7. Perform an isolated restore drill; record exact recovery procedure. Do not import SQL directly into production.
8. Inspect server logs for PHP fatal errors, storage growth and backup folder web exposure.
