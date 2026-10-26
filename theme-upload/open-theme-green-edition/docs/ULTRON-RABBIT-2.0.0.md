# Ultron Rabbit verification: Open Theme 2.0.0

Scope: source checks, headless Chromium on a test WordPress install at 1280px and 390px, and export
inspection. Full installation on a live site, a full-site import and Google Drive upload were not tested.

## Results

| Check | Result |
| --- | --- |
| PHP syntax (42 files, `php -l`) | 0 errors |
| JavaScript parse (7 files) | 0 errors |
| Theme name and version agree (style.css, functions.php) | Open Theme 2.0.0 |
| Public pages: front, sample page, post, archive, search | HTTP 200, no JS errors, no fatals |
| Public 404 page | HTTP 404 as designed |
| Admin: dashboard, Data & Recovery, Dashboard & Menus, Quick Actions, posts, post editor, theme settings | HTTP 200, no JS errors, no fatals |
| Admin: Data Workspace, Post Manager | HTTP 403 on the test site: these pages come from the separate Nine Code Data plugin, which is not installed there |
| Posts export | Valid ZIP, no password prompt |
| Users export | Valid ZIP, no password hashes |
| Edit button on a published page | Present, links to the page editor |
| Passwords in export forms | None |

Result: all checks that the theme owns pass. The 403 pages depend on the Nine Code Data plugin, which
this theme does not bundle.

## Known limits

- The Data Workspace and Post Manager need the Nine Code Data plugin, or the bundled Data Manager from the
  9 Code Alpha theme, to be present.
- The dashboard and menu list comes from the live admin menu; post types hidden from the menu do not appear there.
- Not tested: full import, Google Drive upload, and a live site.
