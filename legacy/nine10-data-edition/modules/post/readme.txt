=== 9 Post Manager ===
Contributors: Studio 9
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 4.0.0

WordPress-native, mobile-first front-end post workspace. Edit the complete post, create lightweight native site fields, manage post/plugin options, replace media, use 9CF AI and make bite-sized post/category backups without requiring ACF.



== Version 4.0.0 ==
* Radical ACF-free architecture reset. WordPress core posts, blocks, taxonomies, registered/unregistered post meta and lightweight 9PM Site Fields are the normal data model. ACF is loaded only as an optional legacy adapter when ACF is actually active.
* Front-end 9P opens a true full-screen editing workspace above the theme/plugin UI, with a persistent bottom Close control and isolated mobile editing surface.
* The Update/Publish command dock is fixed at the bottom of the workspace and keeps Update pinned at thumb reach even when the rest of the action row scrolls. Phone Draft Recovery remains active for interruption protection.
* New Site Fields lets administrators add simple text, long/rich text, URL, email, number, boolean, choice, image and file fields per post type. Values are registered WordPress post meta; there is no parallel content database.
* Post Options / Plugins exposes native date/schedule, parent, menu order, template, format, password, comments/pings, sticky state and discoverable per-post plugin/sidebar/drawer meta. Private plugin settings are human-editable but excluded from AI unless the owning plugin supplies a semantic 9CF provider.
* Media workspace shows the Featured Image plus images used inside post content and supports direct replacement with an undo snapshot.
* New `.9post.zip` native backup engine exports one post or all posts in a category/term. Backups can be data-only or include WordPress media and are deliberately independent of themes/plugins/global options.
* Category backup is automatically split into 100-post parts so large categories remain bite-sized for Google Drive; every part can be restored independently.
* Media backups preserve originals plus WordPress generated-size URLs. Restore sideloads media through WordPress, regenerates image sizes, rewrites original/thumbnail/srcset URLs and block image IDs, and relinks featured images.
* Restore recreates missing taxonomy hierarchy, remaps cross-post links inside category backups, restores native Site Field schemas when authorised, and respects registered plugin meta permissions/sanitizers.
* Backup manifests include SHA-256 checksums and duplicate-media detection. ZipArchive is used when available with a WordPress PclZip fallback.
* Provider hooks allow a plugin to add/restore post-specific data stored outside post meta without 9PM guessing at arbitrary database tables.
* 9CF v2 AI export/import, field-level stale/conflict protection, .9pm specialist designs, Category Manager sister workflow and legacy JSON/Markdown/CSV tools remain available.
* Current boundary: data stored by a plugin only in custom SQL tables or remote services cannot be discovered safely unless that plugin exposes a 9PM/9CF provider hook.


== Version 3.1.0 ==
* Current-reality hardening for WordPress 7.0/Gutenberg-first production. 9CF schema moves to v2 while keeping v1 imports compatible.
* Adds per-field baseline hashes, contract IDs, current WordPress/revision/content metadata and field-level validation so AI imports can distinguish safe changes from conflicts.
* Safe AI Apply now skips protected settings/taxonomies, invalid values and fields changed since export instead of overwriting them; warnings identify field codes.
* Gutenberg blocks that move can be remapped only when a stable block identity (anchor or named block) plus the exported baseline value match uniquely; unanchored/ambiguous moves remain blocked rather than guessed.
* Registered plugin meta now respects WordPress edit_post_meta capability/auth rules and carries registered REST/type validation metadata. Tested against current 9 Embed v1.4.0 and 9 Embed Square v1.3.0 assignment fields.
* Adds provider validation/sanitisation hooks (`npm9_9cf_validate_field`, `npm9_9cf_sanitize_field_value`) while retaining existing provider read/write hooks.
* Adds compact per-post 9CF AI import audit history and links recovery snapshots to contract IDs.
* Adds a 4 MB bounded 9CF import limit and fixes string boolean handling (`false`, `off`, `no`, `0`) so registered boolean fields cannot accidentally flip ON.
* Treats Custom HTML/shortcode/freeform as protected AI content under the current WordPress block-role model; user can still edit deliberately in Gutenberg/Advanced.
* Adds direct Open in Gutenberg action and automatic vertical lift for the admin-only 9C/9P sister dock when the current Nine Action Menu occupies bottom-centre.
* Preserves all v3.0.0 9CF, .9pm, ACF bridge, Category Manager, phone-draft, snapshot, media and design functionality.

== Version 3.0.0 ==
* Introduces 9CF (Nine Content Fields), a vendor-neutral numbered AI field contract that inventories WordPress core fields, Gutenberg/9 Elements blocks, taxonomies, ACF, public/registered plugin meta and plugin-provided semantic fields.
* 9CF exports every field with a stable ID plus human progress codes F001, F002, etc.; numbering is never used as the database key.
* Adds blank AI Form and Current 9CF downloads in .9cf / JSON / Markdown formats and safe Filled 9CF preview/import with mismatch, stale-post and block-structure warnings.
* Gutenberg / 9 Elements becomes the primary day-to-day tab. The mobile editor is split into Post, Gutenberg / 9 Elements, ACF Bridge, Categories & Meta, Plugin Fields and Advanced tabs.
* 9CF detects empty registered block attributes and empty registered post-meta fields, not only data that is already populated.
* Adds 9CF provider filters so Nine and third-party plugins can expose semantic fields stored outside ordinary block attributes/meta.
* Keeps ACF fully supported but repositions it as an optional bridge/provider rather than the required storage model.
* Optional 9CF Bridge Mirror publishes deterministic ninecf_* custom-meta keys for Elementor/custom-field consumers without requiring ACF.
* Optional ACF Bridge JSON maps the same mirror keys into ACF so Elementor ACF Dynamic Tags can be used when desired; image/audio/video suggestions are type-aware for 9 Elements media.
* Front-end admin panel now prioritises Download 9CF AI Form, Download Current 9CF and Import Filled 9CF; legacy JSON/Markdown tools remain available behind a secondary disclosure.
* Preserves .9pm Block Editor/9 Elements design files, linked-design hardening, experimental per-post Elementor route, Category Manager sister integration, snapshots, phone drafts and all v2.0.1 production fixes.

== Version 2.0.0 ==
* Added portable `.9pm` AI design packages above the ACF data layer.
* Production Block Editor route compiles ACF bindings into real Gutenberg containers plus the installed `nine/elements` block.
* Supports 9 Elements Heading, Paragraph, Text, Image, Audio, Video, List, Icon List and Tabs, plus 9PM card/grid/columns/hero composition, galleries, buttons, separators and spacers.
* ACF contracts auto-match by field key then field name; unresolved required aliases can be manually mapped in Post Manager before applying.
* Linked designs rebuild from current ACF values when content is saved through 9 Post Manager; Detach Design preserves the generated blocks while stopping automatic rebuilds.
* Added an experimental Elementor tab that accepts standard Elementor exported JSON or `engine: elementor` `.9pm` packages and applies them to the selected post only, never global Theme Builder conditions.
* Experimental Elementor packages support portable `{{acf:alias}}` placeholders and linked/detached re-sync behaviour.
* Added downloadable Markdown AI authoring guides for Block Editor and Elementor, a schema reference, example `.9pm`, and per-post ACF Design Brief export.
* 9PM design recipes now travel inside the normal JSON/Markdown post backup package.
* Based on the newer 1.2.0 author-assignment release and retains Category Manager sister integration, Instant ACF Render, Quick Deploy, snapshots, phone-draft recovery and media fixes.

== Version 1.2.0 ==
* Added native post-author assignment in the mobile editor.
* Author selector is populated from WordPress author-capable users and displays the 9User professional title when available.
* Author changes use native post_author, so 9User profiles, archives, galleries and author-aware Nine plugins update automatically without duplicate author fields.
* Author assignment respects WordPress edit_others_posts capabilities.

== Version 1.1.0 ==
* Sister-program integration with 9 Category Manager.
* Bottom-centre front-end dock becomes two coordinated controls when both plugins are active: 9C for structure and 9P for content.
* Added Categories / Structure handoff from the backend editor and frontend toolbox.
* Opening one sister popup automatically closes the other to avoid mobile overlap.
* Preserves the full v1.0 Instant ACF Render and AI deployment system.

== Version 1.0.0 ==
* Moves the admin-only frontend 9PM launcher to bottom-center.
* Adds ACF → Page Render with 10 fixed rapid-deployment styles.
* Per-post render controls expose colours only; structural style remains preset-driven.
* Imports and exports reusable 99 Go Style (.99gostyle) packages without renaming ACF fields.
* Quick Deploy: import ACF JSON, allocate it to a post type, create a post/page, assign a style and view.
* Basic renderer uses ACF tabs/groups as information sections and handles common text, media, gallery, relationship, taxonomy and nested data.
* 9PM yields automatically when 99 ACF Go Builder already has an allocated goal for the post.
* Retains mobile editor, ACF tabs, AI JSON/Markdown workflow, CSV field reports, snapshots, phone-draft recovery and media fixes.

== Operating principle ==
9 Post Manager is the WordPress-native content cockpit for one post at a time. WordPress core fields, Gutenberg/9 Elements content, taxonomies, native Site Fields and post-owned plugin meta remain the source of truth. 9CF is the AI exchange contract, `.9post.zip` is the bite-sized post/category backup contract, and `.9pm` remains an optional specialist presentation contract. ACF is legacy compatibility only; Elementor remains optional specialist design.

== 2.0.1 pre-production hardening ==
* Preserves current assignments in taxonomies larger than 500 terms.
* Refuses stale phone saves when the server copy changed after loading.
* Preview-safe View links for unpublished content.
* Taxonomy permission/error preflight, stronger front-end media validation, recoverable presentation changes, and safer Quick Deploy ordering.
* Adds Instant Render loop fallback and [nine_post_manager_render] escape-hatch shortcode for unusual templates.
* Linked .9pm designs now detect manual Gutenberg/Elementor edits before automatic rebuild; prepend/append designs keep a stable base so repeated ACF saves do not duplicate generated content.

