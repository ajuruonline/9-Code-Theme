=== 9 Category Manager ===
Contributors: 9igeria
Tags: site structure, information architecture, categories, tags, taxonomy, post types, authors, pages, gutenberg, elementor, planning
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv2 or later

A mobile-first WordPress site-infrastructure planner plus the text-only 9 Post of Contents directory.

== Product boundary ==
9 Category Manager is the planning and allocation layer. It creates content shells and manages structural relationships. 9 Post Manager remains the population layer for body content, ACF/custom fields, detailed media and final page/post editing.

Planning data may include:
* title
* excerpt / planning summary
* featured image where the post type supports thumbnails
* author where the post type supports authors
* native post parent/child relationship for hierarchical post types
* native menu_order where available
* Categories, Tags and any compatible show_ui taxonomy, including custom hierarchical and flat taxonomies
* explicitly registered plugin relationships through the ninecm_relationship_providers API

9 Category Manager never guesses unknown plugin meta keys.

== Version 4 highlights ==
* Site Infrastructure Map discovers editable post types such as Pages, Posts, Courses, Modules, Lessons, Publications or other registered custom types.
* Structure Drift Guard can save a registered-infrastructure baseline and flag missing/rewired post types, taxonomy attachments, native supports or explicit relationship-provider keys after plugin/theme changes.
* Safe shell-creation gate separates structurally manageable content types from generic shell-creatable types, preventing accidental blank orders/logs/templates/technical records; integrations can opt specialised types in deliberately.
* Discovers every compatible visible taxonomy: Categories, Tags and custom hierarchical/flat systems.
* Full-screen front-end Structure Planner for authorised editors; the backend remains the full control room.
* Current-item and new-shell planning: title, excerpt, supported featured image, supported author, parent/order, all attached taxonomies and registered relationship providers.
* Pages can use standard Categories and Tags, both switchable in Settings.
* Native Page excerpt support is enabled by default so planning summaries are available to compatible WordPress tools.
* Backend content index has content-type, title-search, status and relationship-presence filters.
* Bulk taxonomy relationship Add / Remove / Replace with a 30-minute undo checkpoint.
* Bulk structural allocation: assign Author, native Parent and sequential menu_order to selected content with a separate 30-minute undo checkpoint.
* High-scale term manager with server-side paging/search, hierarchy paths, archive, protection and manual term order.
* Bulk hierarchy builder for hierarchical taxonomies and one-term-per-line bulk builder for Tags/flat taxonomies.
* Bulk planning-shell builder with bounded retryable batches.
* Health Audit and architecture score for large structures.
* Excel-compatible CSV exports and full infrastructure Blueprint JSON, including a whole-site registered-infrastructure snapshot and Drift Guard status summary.
* Read-only WordPress Abilities bridge when the host WordPress version provides the Abilities API.
* Gutenberg and Elementor 9 Post of Contents support Categories, Tags and compatible public custom taxonomies/content types.

== Front-end planner ==
The floating 9 launcher opens a full-screen mobile-first workspace with:
* Current item
* Create shell
* Site map
* Quick Terms manager
* Launcher position

The front-end is intentionally condensed. Archive/protection/manual term ordering, large bulk operations, health diagnostics and exports stay in the backend control room.

Launcher corner, X/Y offset and size can be set globally and overridden per administrator/device so it can coexist with 9 Post Manager.

== External relationship provider API ==
Course -> Module, Lesson -> Course, Publication -> Author Profile, Product -> Vendor or similar relationships may be stored differently by different plugins. 9 Category Manager does not infer arbitrary metadata.

Integrations register explicit providers with:

    add_filter( 'ninecm_relationship_providers', function( $providers, $post_type, $post_id ) {
        // Add a provider containing key, label, type, get_callback,
        // update_callback and optional options/validation/permission callbacks.
        return $providers;
    }, 10, 3 );

Provider controls then appear automatically in the infrastructure map and structure editor.

== 9 Post Manager integration ==
Planning shells remain marked as shells when they only contain planning data, including a featured image. Body content, Elementor body/layout data and recognised ACF values can mark a shell populated.

A population tool can explicitly complete the handoff with:

    do_action( 'ninecm_content_populated', $post_id );

Additional integration filters:
* ninecm_shell_is_populated
* ninecm_meta_is_population_signal
* ninecm_meta_marks_shell_populated

== Post of Contents ==
Available as:
* Gutenberg block: 9 Post of Contents
* Elementor widget: 9 Post of Contents under 9 Widgets
* shortcode: [nine_post_of_contents]

The public directory remains text-first: taxonomy/category/tag labels and linked content titles, with optional hierarchy, dropdowns, search, filtering, numbering/bullets/icons, counts and style presets. It intentionally does not become an image-heavy listing widget.

Example:
[nine_post_of_contents taxonomy="category" depth="3" collapsible="1" marker="number" search="1" filter="1" post_types="page,post"]

== Security and scale ==
* WordPress REST nonces and object-level edit_post checks.
* Taxonomy-specific manage_terms/edit_terms/delete_terms/assign_terms capability checks.
* Post-type create/edit capability checks.
* Archived branches reject new allocations but preserve legitimate historical relationships until deliberately removed.
* Protected structural terms resist accidental move/delete.
* Bulk operations are bounded and retry-friendly.
* Generic shell creation is conservative: native title support + the mapped create capability are required by default; specialised integrations can opt in through ninecm_can_create_shell_for_post_type.
* Structure Drift Guard stores only registered structural identity, not post body content or arbitrary third-party data.
* No direct custom database tables or direct SQL are required.
* Large public directory HTML is not persisted as an oversized transient.
* Exports neutralise spreadsheet-formula-leading text.

== Changelog ==
= 4.0.0 =
* Added Structure Drift Guard: save/replace a baseline of registered post types, taxonomies, native supports/attachments and explicit relationship-provider identities; detect structural drift after plugin/theme changes.
* Added a conservative shell-creation safety gate so editable technical CPTs are inspectable/manageable without automatically becoming generic shell-creation targets.
* Corrected the minimum WordPress version to 6.3 because the bundled Gutenberg block uses Block API v3.
* Expanded Blueprint JSON with a whole-site infrastructure snapshot and Drift Guard summary.
* Corrected Title/Excerpt controls so unsupported native fields are not shown/sent for unusual custom post types.
* Expanded the product from category-only planning into a Site Infrastructure Planner while preserving the 9 Category Manager name and upgrade path.
* Added automatic discovery of editable registered post types and their native structural capabilities.
* Added Categories, Tags, hierarchical custom taxonomies and flat custom taxonomies as first-class planning systems.
* Added full-screen front-end planner with Current, Create Shell, Site Map, Terms and Launcher workspaces.
* Added title, planning excerpt, supported featured image, supported author, native parent/order and all compatible taxonomy allocations to the per-item structure editor.
* Added the ninecm_relationship_providers extension API for safe integration with Course/Module/Lesson and other plugin-specific relationships without guessing meta keys.
* Added Site Infrastructure Map to show content types, structural capabilities, taxonomies and registered relationship providers in one backend control room.
* Added Page Tags and native Page Excerpt support, both configurable.
* Added backend status and selected-taxonomy relationship-presence filters for large content inventories.
* Added bulk Author assignment, bulk native Parent assignment and sequential native menu_order operations with a separate 30-minute structural Undo.
* Added flat taxonomy/tag bulk term creation in addition to hierarchical Bulk Builder.
* Expanded planning CSV and Blueprint JSON to include author, parent, order, featured media, every attached taxonomy and registered relationship-provider values.
* Expanded the read-only Abilities integration to infrastructure discovery, taxonomy-term listing and planning-item inspection.
* Corrected shell lifecycle semantics: featured image is planning data and no longer marks a shell as populated.
* Added transactional validation/rollback around multi-field planning saves to reduce half-updated infrastructure records.
* Preserved Elementor and Gutenberg 9 Post of Contents while widening taxonomy/content-type discovery.

= 3.0.0 =
* Added Health Audit, branch archive/protection, manual term order, bulk shell planning, allocation Undo, infrastructure Blueprint export, state restoration and read-only Abilities integration.

= 2.2.0 =
* Heavy-use pre-audit hardening for hierarchy rendering, bulk batching, stale selections, retry-safe shell creation, mobile performance, CSV safety and permissions.

= 2.0.0 =
* Established the planning-layer / 9 Post Manager population-layer architecture and front-end planner.

= 1.0.0 =
* Initial release.
