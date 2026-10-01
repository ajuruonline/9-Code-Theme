# 9 Post Manager — Web Studio Changelog

## v4.0.0 — 22 August 2026
- Rebased Post Manager around WordPress-native post data. ACF is now an optional legacy adapter, not a core dependency.
- Added full-screen front-end 9P workspace with bottom-centre sister launcher, Action Menu collision lift, persistent bottom Update dock and bottom Close control.
- Added lightweight Site Fields stored as registered WordPress post meta.
- Added Post Options / Plugins workspace for native post settings plus discoverable post-specific plugin/sidebar/drawer meta.
- Added direct featured/content-image replacement with recovery snapshot.
- Added `.9post.zip` post/category backup and restore, data-only or with media, 100-post category chunks, safe identity-based restore, taxonomy reconstruction, optional media sideload/relink, checksums and cross-part relationship repair.
- Hardened media restoration so arbitrary numeric plugin values cannot be mistaken for attachment IDs; actual media keys and Gutenberg media block attributes are remapped instead.
- `.9pm` designs now support `field_contract`, stable 9CF field IDs and `{{field:alias}}` placeholders without ACF; legacy `acf_contract` and `{{acf:alias}}` remain supported.
- Retains 9CF v2 Safe AI Apply, phone drafts, snapshots, Category Manager sister workflow and optional Elementor specialist route.

# 9 Post Manager v3.0.0 — 9CF / Gutenberg-first workflow (16 August 2026)

- Reframed Post Manager around Gutenberg-first daily editing and introduced 9CF as the portable provider-neutral AI field contract.
- Inventories WordPress core fields, Gutenberg/9 Elements blocks, taxonomies, ACF bridge fields, public/registered plugin meta and plugin semantic providers.
- Numbered F001/F002 field codes support AI progress reporting and pen-and-paper tracking while stable IDs/paths remain authoritative for import.
- Adds blank/current `.9cf` export, Markdown compatibility, filled-9CF preview/apply, structure/stale/target warnings and recovery snapshots.
- Adds mobile workspace tabs: Post, Gutenberg / 9 Elements, ACF Bridge, Categories & Meta, Plugin Fields and Advanced.
- Detects empty registered meta and empty registered Gutenberg attributes so exports include valid fillable fields before they have values.
- Adds provider filters (`npm9_9cf_fields`, `npm9_9cf_apply_field`) for non-meta/non-block Nine plugin data.
- Adds optional deterministic 9CF mirror meta and downloadable ACF bridge JSON for Elementor/ACF specialist workflows while keeping ACF non-mandatory.
- Front-end 9P popup prioritises 9CF AI form download/import; legacy JSON/Markdown remains secondary.
- Preserves the complete v2.0.1 `.9pm` design compiler, linked-output safeguards, experimental Elementor route and production hardening.

# 9 Post Manager v2.0.1 — Pre-production hardening (16 August 2026)

- Hardened the current v2.0.0 `.9pm` design-file branch before heavy mobile use; no v2 design capability is removed.
- Preserves every current taxonomy assignment even when a taxonomy exceeds the 500-term mobile window; exposes large-taxonomy warnings and assignment capabilities.
- Adds stale-edit refusal so an old phone form cannot overwrite a newer server edit.
- Uses preview-safe View URLs for draft/pending/private/scheduled content.
- Preflights taxonomy permissions and surfaces term-assignment failures instead of silently continuing.
- Hardens front-end media replacement by attachment type and ACF image/file field type.
- Makes Instant Render style changes recoverable through 9PM snapshots and adds loop/shortcode fallbacks for templates that bypass normal post content.
- Reorders Quick Deploy to validate/create safely before ACF schema import and removes a newly created shell if schema import fails.
- Protects linked .9pm outputs from accidental manual Gutenberg/Elementor overwrite by hashing the last generated output and blocking automatic rebuild when manual edits are detected.
- Fixes linked Block designs using prepend/append so repeated ACF rebuilds reuse a stable original base instead of duplicating generated content on every save.

# 9 Post Manager 2.0.0

- Introduced `.9pm` as the portable AI design contract between ACF data and the presentation engine.
- Block Editor / 9 Elements is the production route: ACF bindings compile into genuine Gutenberg Group/Columns/Button/Spacer structures plus the installed `nine/elements` dynamic block.
- Automatic ACF matching uses field keys first, then exact field names; unresolved required aliases expose a mobile mapping interface before application.
- Linked designs can rebuild after ACF updates; Detach Design preserves generated content for manual Gutenberg editing.
- Added experimental per-post Elementor application using Elementor JSON data stored only on the selected post/page. Standard Elementor exported JSON can be wrapped automatically; `.9pm` Elementor packages can use `{{acf:alias}}` placeholders.
- Added Markdown authoring guides, schema documentation, an example `.9pm` file, and current-post ACF Design Brief export for use with ChatGPT or other AI editors.
- Portable 9PM design recipes now travel with the existing post backup/import package.
- Based on the 1.2.0 Web Studio branch, retaining native author assignment and all earlier category/content sister-program features.

# 9 Post Manager 1.2.0

- Added 9User-aware native author assignment directly to the mobile post editor.
- Uses WordPress `post_author` as the shared relationship, preserving compatibility with the full Nine display/query stack.
- Shows the 9User profile title beside author names when the 9User API is available.
- Author changes are capability-checked using the post type’s `edit_others_posts` capability.

# 9 Post Manager v1.1.0 — Sister Program Update

- Paired with 9 Category Manager as Structure → Content workflow.
- Bottom-centre front-end dock coordinates 9C and 9P buttons.
- Added direct Category Manager handoff for the currently loaded post/page.
- Sister popups close each other to prevent mobile overlap.
- Retains v1.0.0 ACF → Page Render, Quick Deploy, backups, AI import/export, media tools, recovery and snapshots.

# 9 Post Manager – Mobile AI Import/Export 0.4.1

- Production finishing pass by 99 Web Studio.
- Preserved the existing WordPress install folder and main plugin file so upgrades replace prior builds.
- Added complete WordPress plugin metadata and GPL-2.0-or-later licence declaration.
- Added Nine Backup portability registration where plugin-owned durable settings or content types were statically detected.
- Confirmed that the source archive contained no unsafe traversal paths and no invalid packaged JSON.
- JavaScript syntax checked where JavaScript assets exist.

Source version: 0.4.0.

## 1.0.0 — 2026-08-15
- Major 9 Post Manager evolution: Instant ACF → Page Render and ACF JSON Quick Deploy.
- Front-end administrator launcher moved to bottom-center to avoid side navigation and editing tabs.
- Ten fixed rapid-deployment styles; per-post editing intentionally limited to colour overrides.
- Imports/exports compatible `99-go-style` v2 `.99gostyle` packages; bundled Lecturer Profile Pro compatibility preset.
- ACF JSON can be imported and allocated to a selected post type, then used to create a render-ready draft/page immediately.
- Render assignment can travel inside 9PM JSON/Markdown packages, including the selected reusable style package when required.
- Automatically yields to an allocated 99 ACF Go Builder goal to prevent duplicate rendering.
- New render/quick-deploy controls are collapsed by default to keep the mobile editor short.
- Retains all v0.4.1 portability metadata and existing v0.4.x editing/recovery/media functionality.
