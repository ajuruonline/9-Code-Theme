# 9Code Theme 14.1.6 — Display Ownership + Replace-Safe Quick Actions

## Per-post/page display settings
- Adds a **9Code Display** document settings panel for WordPress posts and pages.
- Adds **Presentation owner**: Automatic, 9Code Theme, or Plugin / custom presentation.
- Plugin/custom presentation suppresses Theme-owned title, date/author meta, category line, featured image, breadcrumbs, tags and previous/next navigation unless explicitly overridden.
- Individual Show / Hide / Theme default controls remain available for title, meta, category line, featured image, breadcrumbs, tags, navigation and comments.
- Adds per-item content width: Theme default, Reading, Wide, Full.
- Settings are registered post meta, REST/Gutenberg compatible, non-destructive and do not rewrite plugin data.

## Quick Actions
- Adds **9Code Theme** to the Theme-owned side Quick Actions launcher.
- Advances Quick Actions migration schema to 3 while preserving user customization after one migration.
- The in-launcher plugin ZIP installer now uses WordPress overwrite-package behavior, so uploading an already-installed plugin can replace the existing package instead of failing on the existing destination folder.
- Existing plugin activation is refreshed after replacement.

## Compatibility
- Edition remains 9.10.
- Suite remains exactly: 9Code Theme + 9Core + 9 Data Manager.
- Mason and 9Page remain discontinued and are not restored.
