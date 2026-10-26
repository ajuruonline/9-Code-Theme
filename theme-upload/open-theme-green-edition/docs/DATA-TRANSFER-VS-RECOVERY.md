# Open Scholar — Export/Import versus Backup/Restore

## Export & Import: records for reuse

Admin menu **Export & Import**. For an academic department moving books, articles, authors, library shelves, users or publications to a different Open Scholar institution, download one of the portable ZIP options and use **All Import → Upload & Preview** at the destination. Preview duplicate IDs, missing plugins and role mapping. For conventional WordPress XML, use Tools → Export / Tools → Import.

Direct transfer actions require administrator-password confirmation in addition to the WordPress login and anti-CSRF nonce. ZIP files are not password-encrypted. Exported user accounts omit the actual login passwords and active sessions; invite imported users to reset passwords. The direct ZIP is removed from the temporary server archive after it streams.

## Backup & Restore: recovering a failed installation

Admin menu **Backup & Restore**. **Server Backup** creates SQL + the `wp-content` file tree; **Google Drive Backup** uploads a new snapshot to a connected off-site Drive account. A distinct option stores portable records and plugins on the server. Backups can be scheduled through **Schedules** and managed in **Downloads**. The backup engine uses stored archives and a host diagnostics area.

Google Drive uses the administrator's own OAuth client/Drive account. An Upload action does not imply success until the server receives a successful Google API result. Offline server backup archives are not a substitute for an independent Drive copy.

**Full restore is different:** a SQL/wp-content snapshot needs a matching WordPress installation, configuration, safe site URL handling, and a supervised restore on server/staging. The selective ZIP record importer does not automatically execute database SQL.

## Data security and operations

Access requires administrator privileges. On Nginx, separately configure denial of public web requests to the private archive directory; .htaccess applies only to suitable Apache configurations. Store Google credentials safely and do not share backup archives publicly. Verify backup packages by downloading, listing ZIP contents and performing a real restore drill periodically.
