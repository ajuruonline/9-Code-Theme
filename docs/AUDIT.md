# Audit notes

How the code is checked, and what was reviewed by hand. Run everything with `npm test` and `bin/audit.sh`.

## Automated gates
| Gate | Command | Status at release |
|---|---|---|
| PHP/JS/JSON syntax, no full-screen editor layer, no plugin-only APIs in the theme | `bin/lint.sh` | clean |
| WordPress **Plugin Check** (official) on Nine Code Data | `bin/plugin-check.sh` | 0 errors (see exception) |
| PHP 7.4+ compatibility, deprecated WordPress APIs | `bin/audit.sh` | clean |
| Public site, admin crawl, post editor, visual editor, upgrade simulation | `npm test`, `bin/test-upgrade.sh` | all pass |

**Plugin Check exception:** `plugin_updater_detected` is the AI Manager's "Plugin Update" feature. It is
deliberate, requires `update_plugins`, honours `DISALLOW_FILE_MODS`, and only matters for wordpress.org hosting.

## Plugin hardening done in 10.1.0 (118 Plugin Check errors -> 0)
- SQL: table names bound with `%i`; `IN (...)` lists built from `%d` placeholders.
- File APIs: `wp_delete_file()` instead of `unlink()`; `copy()` after `is_uploaded_file()` instead of `move_uploaded_file()`;
  remaining raw stream calls (CSV/ZIP downloads, staged temp files) carry a justified `phpcs:ignore`.
- Output: form labels escaped at the point of output; `wpautop` output passed through `wp_kses_post`.
- i18n: translators comments, ordered placeholders, single text domain `nine-code-data`.
- Direct access guards, `readme.txt`, plugin-updater capability and file-mod checks.
- Real bug fixes found by the new editor tests: stale block fields overwrote edited content; permanent "Unsaved" badge.

## Advisory findings: independently unresolved at current release gate

The escaping/SQL scan is intentionally advisory, but **a successful CI step is not a clean security audit**. Earlier logs showed 61 PHPCS errors in seven files while the script truncated the report and printed `AUDIT OK`. The updated audit script emits full file/line/sniff diagnostics and distinguishes `AUDIT ADVISORY FINDINGS`, `AUDIT CLEAN`, and blocking scanner failures. All existing findings must be independently reconciled against the exact source revision before release; do not treat the historical false-positive explanation below as item-by-item verification.

## Reviewed by hand and left as is (static-analysis false positives) (historical assessment; requires revalidation)
- Theme `WordPress.Security.EscapeOutput` (61): helper methods and SVG builders that return already-escaped HTML, and
  admin-supplied custom HTML/embeds that are `wp_kses()`-filtered when saved (`class-n9f-settings.php`).
- `NonceVerification` warnings on `personal_options_update` / settings handlers: WordPress verifies the nonce before
  those hooks fire, or the handler calls `check_admin_referer()` first.
- Content/JSON payloads read from `$_POST` in the Post Editor are sanitized per field downstream
  (`sanitize_post_content()`, `sanitize_meta_value()`, ACF field sanitizers) and gated by `edit_post`.

## Not covered
Live hosting, caching/minification, third-party plugin combinations, browsers other than Chromium.
