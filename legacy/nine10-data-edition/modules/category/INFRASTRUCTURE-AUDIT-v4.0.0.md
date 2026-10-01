# 9 Category Manager v4.0.0 — Infrastructure Architecture Audit

## Release objective
Version 4 turns 9 Category Manager into the site-planning and allocation control layer while preserving its role boundary with 9 Post Manager.

The plugin must answer four questions efficiently:
1. What structural systems exist on this WordPress site?
2. What shell/content items exist inside them?
3. How are those items allocated to taxonomies, authors, parents, ordering and explicit plugin relationships?
4. Can the administrator create/restructure those relationships without opening every WordPress edit screen?

## Data model
### Automatically discovered native mechanisms
The planner discovers registered WordPress post types and visible taxonomies at runtime. It uses native WordPress fields/APIs for:
- post type
- title
- excerpt/planning summary
- featured image where thumbnail support exists
- author where author support exists
- post_parent for hierarchical content types
- menu_order where the post type is hierarchical or exposes page attributes
- Category, Tag and custom taxonomy term relationships

No parallel category table or proprietary post relationship database is created.

### External/plugin-specific relationships
A Course -> Module or similar relationship may be implemented by another plugin using custom meta, taxonomy terms, a custom table or an internal API. Guessing that storage is unsafe.

Version 4 therefore exposes `ninecm_relationship_providers`. Integrators provide explicit get/update/validation/permission callbacks and optional searchable options. These providers become visible in the same planner without 9 Category Manager owning the external plugin's data model.

## Planning / population boundary
9 Category Manager owns infrastructure planning only:
- title
- excerpt
- featured image
- author
- parent/order
- taxonomy/tag allocations
- registered relationship-provider values

It does not edit body content, arbitrary ACF/custom fields or detailed page media. Those remain the responsibility of 9 Post Manager and other content tools.

A shell with title + excerpt + featured image + allocations remains a planning shell. Featured image is not treated as proof that detailed population occurred.

## Front-end architecture
The authorised front-end launcher opens a true full-screen mobile-first planner. This avoids collisions with the page being viewed and gives enough working area for complex infrastructure without recreating wp-admin.

Front-end scope:
- current item structure
- new shell creation
- condensed site infrastructure map
- quick term/tag creation/search
- local launcher placement

Backend-only heavy controls:
- large term management
- archive/protection/manual term ordering
- bulk builders
- bulk allocations
- health audit
- exports/import-state restoration

## Structure Drift Guard — innovation pass
The control room can save a small baseline of the registered site architecture: visible content types, native planning supports, taxonomy attachments and explicit relationship-provider identities. It stores no page body content. A later plugin/theme update or deactivation is compared against that baseline and classified as critical, warning or informational drift.

This is intended to surface infrastructure loss before an administrator continues planning against an incomplete site. Accepting the current structure as a new baseline is always an explicit administrator action.

## Safe shell-creation boundary
A registered post type can be structurally manageable without being safe for generic blank-shell creation. Orders, logs, template records and synchronisation objects are common examples. Version 4 therefore requires the mapped create capability plus native Title support before enabling generic shell creation. Specialised integrations may deliberately override this through `ninecm_can_create_shell_for_post_type`.

## Backend control room
### Site Infrastructure Map
For each editable post type, the control room reports:
- native planning capabilities
- hierarchical state
- attached taxonomies/tags
- explicit relationship providers
- shell-creation permission

### Content Infrastructure Index
The item list is intentionally decoupled from the selected taxonomy. A Course or Module remains visible even when Category is not attached to that post type. Selected-taxonomy bulk allocation is disabled when incompatible, while Edit Structure remains available.

Filters include:
- content type
- title search
- status
- whether the selected taxonomy has a relationship

### Bulk structure operations
Selected content may receive:
- one author
- one native parent/top-level reset
- sequential menu_order

The latest structural operation has a 30-minute per-user/per-post-type undo snapshot separate from taxonomy-allocation undo.

## Taxonomy / tag scale strategy
Hierarchical taxonomies support path-based creation in bounded batches.
Flat taxonomies such as Tags support one-term-per-line bulk creation.
Term state remains sparse: default order/protected/archive values do not create unnecessary metadata rows.
Archived hierarchy state is inherited by descendants rather than copied onto every descendant.

## Safety model
- REST nonce protection.
- Post-type capability checks.
- Object-level `edit_post` checks on existing items.
- Taxonomy-specific manage/edit/delete/assign capability checks.
- Parent-cycle prevention.
- Protected-term move/delete prevention.
- Archived-branch allocation enforcement.
- Stale term IDs return conflicts instead of silently changing the intended allocation.
- Multi-field planning saves validate before mutation and attempt rollback if a later relationship/provider operation fails.
- Bulk operations are bounded to prevent one request from becoming an unbounded server job.
- Creation uses retry/idempotency keys to reduce duplicate shells after network interruption.

## Portability / AI
Planning CSV includes infrastructure fields and a machine-readable representation of all attached taxonomies and relationship providers.
Blueprint JSON describes the whole registered site-infrastructure snapshot, Drift Guard status, selected post-type capabilities, taxonomy systems, terms and planning items without exporting body content/private arbitrary custom fields.

Where the WordPress host exposes the Abilities API, Version 4 registers read-only infrastructure discovery abilities. Destructive writes are deliberately not exposed as AI abilities.

## Deliberate exclusions
Version 4 does not:
- guess Course/Module/Lesson meta keys
- automatically merge taxonomies
- automatically synchronise post_parent with taxonomy parentage
- mutate arbitrary ACF/custom fields
- expose destructive AI abilities
- replace WordPress menu/nav-menu management
- turn 9 Post of Contents into an image/card listing tool

These exclusions prevent the planning layer from becoming a second Post Manager or from corrupting data owned by other plugins.

## Required production smoke sequence
1. Upgrade an existing v3 installation and confirm settings migration.
2. As an administrator, save the Structure Drift Guard baseline; confirm it reports No drift on reload.
3. Confirm a technical/editable CPT without native Title support remains structurally visible where authorised but is not offered for generic shell creation.
4. Confirm Pages expose Categories/Tags/Excerpt according to settings.
5. Confirm at least one custom post type appears in Site Infrastructure.
6. Create one draft shell with title/excerpt/featured image and confirm `_ninecm_shell` remains set.
7. Populate body/ACF/Elementor data and confirm shell state can be cleared.
8. Assign Category + Tag + one custom taxonomy where available.
9. Test bulk taxonomy allocation and Undo.
10. Test supported bulk author/parent/order and Undo.
11. Render 9 Post of Contents in Gutenberg and Elementor.
12. Test the front-end full-screen planner on a small mobile viewport.
13. Disable/re-enable a non-production test plugin that registers a CPT/taxonomy and confirm Drift Guard reports and then resolves the change as expected.

## Current compatibility baseline
- Gutenberg Block API v3 requires WordPress 6.3+, so Version 4 declares WordPress 6.3 as its minimum.
- Elementor integration uses the current `elementor/widgets/register` hook and widget dependency methods rather than legacy registration.
- The custom administration UI does not rely on WordPress core list-table title/checkbox markup that changed in WordPress 7.1.
- Abilities integration remains feature-detected and read-only; the main plugin continues to work without the Abilities API on WordPress 6.3–6.8.
