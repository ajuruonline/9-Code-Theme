=== Nine Code Data ===
Contributors: 9igeria
Tags: editor, data, forms, categories, backup
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 11.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal data workspace for Nine Code sites: every post type, taxonomy, user, ACF field and app, with AI-ready export/import, preview, History and undo.

== Description ==

Nine Code Data keeps your content tools in a plugin, so your posts, forms, responses and shortcodes keep working even if you change themes.

* **Data Workspace** - discover every data type (WordPress, ACF, Open Scholar, Conference.lat, Nine Code apps), search, edit on phone or desktop, bulk edit, export JSON (AI package), XLSX or CSV, preview imports field by field, apply selectively, and undo from History. Protected and secret data stays locked; publishing, creating and trashing always need explicit permission.
* **Post Editor** - edit post content in a visual block editor (images, galleries, headings, lists, buttons, tables, video, forms), post metadata, taxonomies and plugin fields from one mobile-friendly screen.
* **Form block** - place any Form Manager form in content with the standard Add block popup.
* **Category Manager** - structure categories and assign content in bulk with undo.
* **Post Creator** - create posts and pages from structured AI/JSON packages.
* **Form Manager** - build forms, collect responses, download CSV.
* **Data Backup** - scoped backups and restore.

Works with the Nine Code theme; the theme also works without this plugin.

== Installation ==

1. Upload the plugin zip under Plugins > Add New > Upload Plugin and activate it.
2. Open **9 Data Manager** in the admin menu.

Upgrading from "9 Data Manager": activating Nine Code Data retires the old plugin automatically. Your data is not changed.

== Changelog ==

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
