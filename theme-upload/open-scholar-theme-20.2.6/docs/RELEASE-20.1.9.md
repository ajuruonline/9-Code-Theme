# Open Scholar Theme 20.1.9 — Popup Navigation & Ultra Backup Resilience

## Navigation
- Replaces the legacy WordPress left drawer with a modal application menu, launched from the bottom-left `9 Menu` control.
- All WordPress admin-menu URLs and capability filtering continue to come from the live WordPress `#adminmenu`; no separately hard-coded app list.
- Modal includes label/submenu search, independently expandable submenu groups, backdrop/Escape dismissal, restored focus, responsive single-column mobile and two-column desktop view.
- Underlying content uses full width while the popup is closed. Existing unrelated editor quick actions remain available; the duplicate legacy right-side menu toggle is hidden.
- Removes the reported blue focus styling and aligns iconography by top-level menu name. Unrecognised third-party plugin icons remain intact rather than receiving a misleading generic/lightbulb replacement.

## Ultra Backup
- Shows and pre-fills the required backup name; keeps it expanded.
- Offers a preselected, small site-settings-only backup to check ZIP generation before attempting a complete backup. For a full portable backup, select **Complete Portable Site** in Section 3.
- Includes a real admin-post form for manually processing one backup part without relying on blocked `admin-ajax.php` requests. Automatic work disables this button until it fails; manual mode stays manual after redirects.
- Adds an authenticated AJAX probe and human-readable guidance on blocked worker requests.
- Distinguishes partially failed backup runs from successful completion and preserves completed archives.
- Work continues to use the existing archive format, plugin settings, schedule and import APIs.

## Installation and checks
1. Save the current installed theme ZIP and verify any active backup plugin separately.
2. WordPress -> Appearance -> Themes -> Add New -> Upload Theme -> choose `open-scholar-theme-20.1.9.zip`. If prompted, replace the current Open Scholar theme.
3. In WordPress administration, tap **9 Menu** at bottom-left, use search, open Settings or Appearance, then close using X or Escape.
4. Open **Ultra Backup -> Create Backup**, leave the default small test selected and create the settings-only backup. Verify a ZIP is listed in Archives.
5. For broader coverage return and select other preset(s). If an AJAX worker fails, select **Continue next part manually** until each part completes; check Diagnostics for the precise server error.
6. The packaged backup engine is intentionally not loaded when an independent `Nine_Backup_Manager` plugin already owns that class. In this case that plugin must be updated separately.

## Testing scope
- PHP files passed `php -l` checks in the packaging environment.
- JavaScript files passed Node syntax checks.
- Local static release tests passed.
- Browser fixture tested mobile/desktop, launcher, modal, filter, Escape, and JavaScript error-free rendering.
- A live Bluehost/WordPress server was not available in the build environment; file-system permissions, server WAF rules, PHP limits and third-party plugin conflicts cannot be certified by local checks alone.
