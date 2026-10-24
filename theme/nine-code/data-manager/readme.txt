=== Nine Code Data Manager ===
Contributors: 9igeria
Tags: editor, data, forms, categories, backup
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 18.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal data workspace for Nine Code sites: every post type, taxonomy, user, ACF field and app, with AI-ready export/import, preview, History and undo.

== Description ==

Data Manager is built into the Nine Code theme. Installing or updating the theme installs it too, so there is no separate plugin to manage. Your posts, forms, responses and shortcodes stay in your site's database; the screens that edit them are available while the Nine Code theme is active.

* **Data Workspace** - discover every data type (WordPress, ACF, Open Scholar, Conference.lat, Nine Code apps), search, edit on phone or desktop, bulk edit, export JSON (AI package), XLSX or CSV, preview imports field by field, apply selectively, and undo from History. Protected and secret data stays locked; publishing, creating and trashing always need explicit permission.
* **Post Editor** - edit post content in a visual block editor (images, galleries, headings, lists, buttons, tables, video, forms), post metadata, taxonomies and plugin fields from one mobile-friendly screen.
* **Form block** - place any Form Manager form in content with the standard Add block popup.
* **Category Manager** - structure categories and assign content in bulk with undo.
* **Post Creator** - create posts and pages from structured AI/JSON packages.
* **Form Manager** - build forms, collect responses, download CSV.
* **Data Backup** - scoped backups and restore.

Ships inside the Nine Code theme. Its version always matches the theme's.

== Installation ==

1. Install or update the Nine Code theme. Data Manager is included and active.
2. Open **Data Manager** in the admin menu.

Upgrading from the separate "Nine Code Data" plugin or the older "9 Data Manager" plugin: once the Nine Code theme is active, the old plugin is deactivated automatically on the next admin page. Your data is not changed. You can then delete the old plugin.

== Changelog ==

= 18.0.0 =
* Data Manager is bundled with the Nine Code theme and loads on every request. The separate Nine Code Data plugin is retired and deactivated automatically.
* The theme and Data Manager share one version number.
* Option names, meta keys, post types, taxonomies, REST routes, hooks and filters are unchanged. History table is created on the first admin request after an update.

= 17.1.0 =
* All Data Manager and suite settings sections now start closed, alternate red/green visual groups, stack for mobile use and show explanatory help where available.
* Added a shared settings UI contract and opt-in API for future Nine Code plugins.

= 17.0.0 =
* Quick-action launcher is centred on the right edge (theme 17.0.0 ships the same change).
* Comments on every post type are an editable data type; status changes go through WordPress moderation and need the explicit permission.
* Forms: question definitions are editable with the Form Manager's own rules; visitor responses are listed read-only.
* Post types without an admin screen are discovered; WordPress internals stay hidden.

= 11.0.0 =
* New Data Workspace (9 Data Manager > Data Workspace): mobile-first discovery, records, editor (ACF groups, repeaters, flexible content, galleries, relationships), bulk edit, History and undo.
* Provider contract v1 (`ninecode_data_register_providers`): plugins keep ownership of validation, permissions and writes; generic discovery for everything else.
* AI round trip: JSON package with schema and instructions, XLSX and CSV; staged, batched imports with field-level preview, conflict detection, selective apply and explicit create/trash/publish permissions. Nothing is sent to external AI services.
* Security: capability and per-record checks, protected structural keys, secret redaction, mass-assignment guard, formula-injection guard, scope locks, audit history.
* Bundled adapters for Teaching Player, LectureBoard, Live Lecture and Workshop progress.
* The 11.0-draft Universal Data routes and page remain as a safe compatibility layer (imports are preview-only).

= 10.1.0 =
* Renamed from 9 Data Manager; text domain is now nine-code-data.
* New visual (WYSIWYG) Post Content editor with Add block popup and Form block.
* Fixed: saving could overwrite edited content with stale block-field values.
* Fixed: "Unsaved" badge always visible.
* Security and coding-standards pass: prepared SQL identifiers, escaped output, WordPress file APIs.
