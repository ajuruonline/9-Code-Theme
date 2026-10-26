# Open Scholar Theme 20.2.0 — Compact Navigation + Portable Data & Recovery

## 1. Navigation specification

- Preserves the original WordPress `#adminmenu` (its capability filtering and plugin menus), but presents it in a bottom-left popup.
- Desktop popup is approximately 25% of the viewport width (minimum 285px for readable labels), capped at 420px, max 70vh tall. Mobile uses a limited 78vw × 56dvh popup, not full-screen, with an independently scrollable list.
- Launcher remains visible **above** the popup and toggles both open and closed. Three-line hamburger transitions to close; popup also closes on X, outside click and Escape.
- The compact menu does **not** resize or translate the underlying full-width admin content.
- Open Scholar academic-cap SVG appears in the menu header. The launcher shows a WordPress Site Icon if configured, falling back to the Open Scholar cap. No hard-coded 9 logo remains in these components.
- The known named menu mappings retain distinguishable icons; unknown third-party plugin icons stay intact.

## 2. Three distinct recovery workflows

### A. Export & Import: portable data, immediately downloaded

Open **Open Scholar Data & Recovery → Export & Import**.

- **Download Posts ZIP**: posts, pages, custom post types and their structured data, as portable Open Scholar package.
- **Download Users ZIP**: WP users, roles, login names, emails, eligible profile metadata; passwords, active sessions, application credentials and recognizable authentication secrets are deliberately excluded.
- Both actions require a logged-in administrator with `manage_options`, valid nonce and password re-verification.
- These packages download directly and are deleted from the local archives after streaming. They do not automatically upload to Google or prune existing local backups.
- **Advanced selective export** retains search, single-record exports, media, user, term, custom plugin records, plugin ZIP bundles, etc.
- **Import ZIP and preview** accepts existing Open Scholar/9 Backup packages with manifest/integrity validation before import. This is **not** a generic ZIP importer for arbitrary services.
- For standard WordPress-to-WordPress interoperability, **Tools → Export / Import** use WordPress WXR XML, which is independent of the Open Scholar ZIP format.
- User imports create new password values (WordPress credentials must be reset). Password hashes are intentionally not portable through user ZIP export.

### B. WordPress Backup: local restore/recovery

Open **WordPress Backup** → **Create local backup**, then select:
- Full Portable Site to copy portable records, plugins and settings; or
- Disaster Snapshot for a database SQL + wp-content archive requiring an external/manual server restore.

The previously implemented shared-hosting safe mode, resumable backup parts, alternative admin-post manual continuation, host diagnostics, selective restore preview and import logs remain present. Backups appear in **Downloads**, protected by administrator role, nonce, and password confirmation. Do not rely only on a same-host backup.

### C. Google Drive Backup: off-site copy

Open **Google Drive Backup**. Link to **Settings** to configure Google OAuth Client ID/Secret and connect account with limited `drive.file` permission. Once connected, choose automatic upload for new backup packages or **Upload latest local backup to Drive**. Do not make the backup folder public. See Drive status in the archive listing.

Deleting a local archive now **does not** delete its remote Google Drive copy. To delete an off-site backup, use Google Drive's file-management controls. Remote uploads remain subject to Google OAuth, host outbound HTTPS availability and Drive quotas.

## 3. Security and recovery caveats

- A password-confirmation **download gate is not ZIP encryption**. Copy downloaded ZIPs only to access-controlled/encrypted storage or a personal private Google Drive. Do not send packages in public links or messages.
- Packages can include personal data (user emails, private/draft posts, metadata). The server-side ZIP storage uses randomized filenames, an empty index, `.htaccess` Apache protection and `web.config` IIS protection. For Nginx or non-Apache servers, server-level deny rules must be configured separately. Verify archive URLs cannot be fetched anonymously. This is vital before exporting sensitive data.
- A complete disaster SQL snapshot may include credentials or session-related records and must be protected as highly sensitive. The portable user export deliberately excludes login passwords and detectable auth secrets.
- Exports do not replace a tested complete-site disaster recovery plan. Download a copy, keep Google Drive backup disconnected from hosting administration, and test restoring on a staging WordPress site.
- An independent installed **Nine_Backup_Manager** plugin loads before the theme and overrides the built-in engine. A visible admin warning now explains this. To use this updated theme engine, retain backups and **deactivate, not delete**, the older standalone plugin. External plugins may need independent updates.

## 4. User acceptance checks

1. On desktop, with the popup closed, the entire administration page spans the viewport. Click the bottom-left hamburger; popup width ~25%; click the SAME hamburger to close.
2. On mobile, popup never occupies the whole viewport; search and inner scrolling work; the cap icon is visible in its header.
3. Open Export & Import. Export a small selection of posts and users using your admin password. Check downloaded archives. Test importing one on a non-production instance.
4. Open WordPress Backup → Diagnostics; run host self-test; then generate a small site-settings-only backup before broad exports. Use manual continuation if Bluehost blocks AJAX.
5. Connect Google Drive, upload a small local package, confirm file appears in **your private Drive**. Delete local copy if desired and check Drive copy remains.
6. Attempt `https://your-domain.example/wp-content/uploads/nine-backup-manager/archives/<filename>.zip` in an incognito browser tab. It must NOT download. Ask host to configure an Nginx deny rule if it does.

## 5. Validation scope

- PHP syntax checks, Node JS parser, 12 regression tests and responsive Chromium fixture tests (desktop/mobile, launcher re-close, search, focus/dismissal) were exercised locally.
- This package was **not** tested on the owner's authenticated Bluehost server, Google OAuth account or live WordPress database. Those require post-install acceptance checks.
