# Open Theme 2.0.0 (launch)

Renamed from "Open Theme Green Edition" to **Open Theme**. Version 1.0 was the pre-launch build; 2.0.0 is the launch version.
The folder and slug stay `nine-code`, so it installs over 1.x in place and keeps settings and records.

## Since 1.0.x

- Quick Actions: All Posts and All Pages, a list for every taxonomy, grouped sections with search, up to 60 actions (1.0.1).
- Exports never ask for a password and never include passwords or API keys (1.0.0).
- Edit button on the side rail for the current page, post, category, tag or author (1.0.0).
- Visual layer `assets/css/open-theme.css` (1.0.0).
- Fixed: a false "separate backup plugin owns the engine" warning, and a JavaScript error on Data & Recovery (1.0.0).

Internal upgrade schema continues from the 20.x series (`NCU_CORE_VERSION` 20.2.7), so upgrade routines still run.

Verification: docs/ULTRON-RABBIT-2.0.0.md.

## 2.0.1 — Post editor

- The editor's top bar keeps only Back, Add block, Undo, Redo, Settings, Save and View. Everything else
  (Elementor, third-party plugin buttons, Preview, Document overview, Options) moves into one branded
  editor menu, opened by the site icon at the top right. It opens to half the screen and closes when pressed again.
- Meta boxes are accordions. All start closed, and each has a one-line plain-language explanation.
- Form-style cards for the meta boxes (rounded, bordered, system font).

Not yet done: a full form-style redesign of the block canvas itself, and a branded version of Gutenberg's
own ⋮ options panel (it opens from the menu, but keeps Gutenberg's styling).

## 2.0.2 — Compact editor on phones

- Less spacing in the editor header, title and meta boxes on phones. Move-up and move-down arrows are hidden on phones.
- Meta box headers are filled green with white text. Each section has its own colour (five tones) so sections are easy to tell apart.
- The one-line explanation sits under each section title.

## 2.0.3 — Side rail minimise and sheet

- The side rail has a minimise button. Minimised, it becomes one button at the lower right. Tapping it opens a half-screen sheet with every rail action, labelled, which scrolls. The maximise button returns to the side rail.
- The rail's icons and the minimise, maximise and menu buttons use Font Awesome (bundled in `assets/vendor/fontawesome`, licence in `LICENSE.txt` there).
- Tested at 390px: expanded, minimised and sheet states, with no JavaScript errors. The sheet is opaque and its labels are visible.

Not yet done: form-field outlines, numbered sections, double-underlined section headings, a "where you are" header on settings screens, Scholar profile quick access, and the post list design.

## 2.0.5 — Performance and compact phone controls

- Side rail: the quick-action sync runs every 4 seconds and only while the tab is visible (was every 1.2 seconds). The rail background is solid, without a blur filter, which is costly on phones.
- Post editor: the watcher ignores changes inside the block canvas and waits 300 ms before rebuilding the menu and accordions.
- Phones: text fields are 36px tall and buttons size to their text (34px), instead of 44px full-size controls. Paragraph fields keep their full height.
- **Optimise Site** (Open Scholar menu). "Optimise whole site" applies Balanced and removes expired cache entries. Safe, Balanced and Full are available by hand, and "Restore WordPress defaults" turns everything off. Each level sets revisions, autosave, heartbeat, oEmbed discovery and REST head link, and always removes unused emoji scripts and the generator, RSD and shortlink head tags. Nothing is deleted except expired transients, which WordPress rebuilds on demand.

## 2.0.6 — Records, automatic optimisation, editor and menu fixes

**Records export and import** (Export & Import, top of the page)
- Choose any post type, Users & profiles, Categories & tags and Comments, then download Excel (.xlsx, one sheet each), CSV (one file each, zipped when there are several), JSON, or the Open Scholar package (.zip, full copy with relationships).
- Import any of those files back. A preview shows what will be added, updated or skipped before anything changes. Modes: update and add, only add new, only update existing.
- Before writing, a JSON safety copy of the affected records is saved and offered for download.
- Matching: posts and terms by slug, users by login or email, comments by post, date and author. An id is used only when it points at the same record, so files move safely between sites.
- Spreadsheet-friendly: files re-saved by Excel, LibreOffice or Google Sheets import correctly; phone numbers, leading zeros and decimals such as 2.10 stay as typed; a file with only some columns changes only those columns.
- Never exported or imported: passwords, sessions, capabilities and secret-looking keys. Importing never changes your own role.
- Tested by `tests/open-theme/records-roundtrip.php` (160 checks across Excel, CSV, JSON and Excel-style workbooks).

**Optimise Site is automatic**
- Sites that never chose a level run Automatic: the Balanced settings plus daily maintenance (expired cache entries removed). Nothing to set up.
- Advanced controls set each item yourself: emoji, head tidy, embed script, admin icons for visitors, public heartbeat, oEmbed and REST links, self-pingbacks, revisions kept, autosave and editor heartbeat intervals, daily maintenance and an optional weekly database tidy.
- Site health lists always-loaded settings size, expired cache, revisions, object cache, page cache, PHP version and active plugins, with plain advice. Maintenance history shows the last 10 runs.
- Revisions are never deleted; only the number kept for new saves is limited.

**Pop-up menu**
- Tapping a row opens its submenu (+ / −). A link button at the right opens the page itself. Both buttons respond on phones, where WordPress previously swallowed the tap.

**Post editor meta boxes**
- Tapping the title opens the box (it used to open and close in the same tap). The site icon sits in each header and titles are double-underlined.
- Compact fields, bold dark-green labels, italic descriptions, double-underlined section headings inside boxes.

**Speed and layout**
- Font Awesome (254 KB) replaced by a 2 KB icon sheet with the five icons the theme uses.
- Admin styles and scripts are versioned with the theme version, so browsers pick up changes after an update (2.0.5's phone sizing was cached on some sites).
- Phones: page content keeps clear of the open side rail; the "Who sees what" table says it scrolls sideways and keeps its first column in view.

## 3.0.0 — Speed and error fixes

Speed only: no new features, no content or setting is deleted.

**Speed (measured on the test site, median of three loads, before → after)**
- Dashboard: DOM ready 426 → 228 ms; page weight 4.8 MB → 1.5 MB.
- Quick Actions: DOM ready 623 → 346 ms.
- Dashboard & Menus: DOM ready 626 → 494 ms.
- Posts list: DOM ready 334 → 210 ms.
- Post editor is unchanged (~1.1 s): most of that is WordPress's own block editor.
- Public front page: DOM ready about the same. Its weight is mostly the archive and 9Stagram plugin stylesheets, which are needed there.

**What changed**
- The Ctrl+K command palette (WordPress core search) is no longer loaded on admin screens, with its Secure Custom Fields add-on. This is the largest saving, about 2.4 MB per admin page. It is a setting under Optimise Site › Advanced ("Keep the Ctrl+K command palette on admin screens"); turn it back on there if you use it.
- Dashicons are removed for visitors only when no stylesheet on the page needs them. Plugins that use dashicon icons keep them.

**Error checks**
- 20 admin and public page loads at phone and desktop widths: no JavaScript errors.
- Ten key pages: no broken resources (no 4xx or 5xx responses).
- The PHP log shows no errors from theme code during these checks. Remaining log entries come from WordPress.org update checks, which the sandbox cannot reach, and from a third-party Secure Custom Fields notice.
- The public suite (`tests/e2e/public.mjs`) was written for the older Nine Code theme's markup. Three of its checks (header, footer and mobile menu toggle classes) don't apply to Open Theme; the suite needs updating for this theme.

## 3.0.1 — Icons restored

- Fixed: icons showed as plain dots. Version 3.0.0 replaced Font Awesome with a small sheet of five icons, so any other Font Awesome icon drew as a filled square. Font Awesome 6.5.2 is back in full.
- Icons no longer hold up the page. The stylesheet loads without blocking the first paint, and the icon font downloads only when an icon is drawn.
- Checked on the test site: every Font Awesome icon on the home page, a post, the dashboard, Dashboard & Menus, Export & Import, the post editor and Optimise Site has a glyph, with no JavaScript errors.

## 4.0.0 — Speed, without changing how the theme works

Nothing is removed and no screen changes. Speed comes from not loading code a screen never uses.

- Dashboard & Menus: page weight 3.2 MB → 1.7 MB (the custom-field stylesheet and scripts, and the command palette, are no longer loaded there).
- Dashboard: 4.8 MB → 1.5 MB, the same reductions plus the editor components the dashboard doesn't need.
- Secure Custom Fields' global styles and scripts are skipped on Open Theme's own screens. They still load on post, page and field-group screens. Setting: Optimise Site › Advanced › "Skip custom-field styles on Open Theme screens".
- Command palette (Ctrl+K) off by default, as in 3.0.0 (Advanced › "Keep the Ctrl+K command palette on admin screens").
- Font Awesome restored in full (3.0.1), loaded without blocking the page.

Checked: 20 admin and public page loads with no JavaScript errors; every Font Awesome icon on the key screens draws its glyph; no broken resources.

Not changed, on purpose:
- The public home page's archive and 9Stagram stylesheets (about 330 KB) are needed on that page.
- The post editor's own WordPress block editor is most of its cost and cannot be trimmed safely.
- Font Awesome is not subset, because that would bring back the missing icons from 3.0.0.

## 5.0.0 — Feels like an app, not WordPress

**Menu order** (popup and sidebar)
1. Dashboard
2. Content: Posts, Pages, Media, Comments, then the Open Scholar apps
3. Core: Appearance, Plugins, Users, Tools, Settings, Open Scholar host pages, Export & Import, Backup & Restore
4. Other plugins, under a collapsed **Advanced** heading at the bottom
A custom order saved on Dashboard & Menus still wins. An order of 500 (the untouched default) follows the tiers.

**People who cannot manage the site** (authors, contributors, editors)
- See Dashboard, Posts, Pages, Media, Comments, their Profile, and any app that has content screens.
- Plugins, Themes, Settings, Users (except their own profile), Tools and the Open Scholar host pages are hidden, and opening them by address goes back to the Dashboard. Permissions are not changed.

**Wording**
- The word "WordPress" is now "Advanced" in admin and login text (text only, so links keep working), and "Howdy" is now "Hello".
- Removed: the WordPress logo, version and "thank you" footer, the generator tag, and the core update notice. The login page shows the site's name.
- The editor's "Meta Boxes" bar is now "Details".
- The address still contains wp-admin and wp-content, because plugins depend on those paths.

**Profile and user forms**
- Settings about the software (syntax highlighting, colour scheme, keyboard shortcuts, toolbar, infinite scrolling, language, browser sessions and application passwords) are in one collapsed **Advanced settings** section.
- Sections use double-underlined headings and rounded cards. Name, contact, biography, password and plugin profile fields stay where they were.

**Post editor**
- Top bar with a soft shadow, pill-shaped Save and Publish, a round editor menu button, and "Details" for the meta boxes.

**Checks**
- Admin account: all menus in the new order. Author account: 403 on Plugins, Themes, Settings, Users and the host page; Tools redirects; profile and content screens open.
- 20 admin and public loads with no JavaScript errors. Editor regression tests pass.
- Admin time to first byte rose by about 20–30 ms, from the wording pass. The fast path skips pages with none of the words.

Not changed, and why:
- The block editor's canvas is WordPress's own editor. Restyling it further is possible but needs a full custom editor.
- Paths like /wp-admin/ cannot be renamed without breaking plugins.

## 5.1.0 — Phone lists and the editor's settings icon

- Meta box headers in the editor show a settings (sliders) icon instead of the site icon.
- Posts, pages, media and comments on phones: each item is a card; status filters are pills; the row actions are pills; the search field is full width.
- Add New is a floating button at the bottom right on the posts and media screens. Screen Options and Help are hidden on phones.
- Function is unchanged: the same actions, bulk tools and links are there.
Checked: 20 admin and public loads with no JavaScript errors; editor regression tests pass.

## 5.2.0 — Logo, scholar links, Advanced dashboard tools

- Logo: the Open Scholar graduation cap is used in the dashboard header, the menu header and the editor menu.
- Dashboard: "Welcome to your digital office." Quick links: View your site, View my scholar profile, View scholars (for people who can edit users).
- Dashboard apps: Dashboard, then Open Scholar apps and content, then core tools, then other installed apps. Export & Import, Backup & Restore, and any app named Export, Import, Backup, Restore, Navigator or Exhibit are in a collapsed Advanced section.
- Profile: Open Scholar fields and core personal details stay visible; other plugins' profile sections move into Advanced.
- Side rail: the redundant menu button is removed (it stays only as the restore button for a minimised rail).
- Menu on phones opens as a bottom sheet from the launcher.

## 5.3.0 — Post filters, rounded menu sheet, lighter page start

- Posts list: a green filter bar at the top with white controls to filter by category, tag and author. It sends the same parameters WordPress already uses, so results are unchanged.
- Menu sheet on phones: a rounded card floating above the launcher. The square-corner rule for bordered boxes still applies everywhere else; the menu is marked to keep its radius.
- Speed: the square-corner script now starts its first scan in idle time, a few elements per frame, so it no longer holds up the first paint. Hover and focus only check the element under the pointer.
- Measured script time per screen: public front page about 30 ms; posts list about 60 ms; dashboard about 90 ms. The block editor's own script (about 570 ms) is WordPress core and is unchanged.

## 5.4.0 — Back and forward in the menu

- The menu header has back and forward chevrons beside the close button. They step through the pages you have visited, using the browser's history.

## 5.5.0 — Compact phone lists

- Posts list: a single green "Filter posts" bar that opens on tap, with Format, Category, Tag and Author. Format covers every WordPress post format. Filters that are on open the bar automatically.
- Posts cards on phones: less padding and smaller row actions, so more posts fit on screen.
- Add Post is a centred text button, with no icon, clear of the menu handle.
- The launcher is the logo alone, with no green box.
- Phone spacing is reduced for panels, settings forms, app tiles and the table toolbar.
