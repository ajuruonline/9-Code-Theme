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
