# 9 Category Manager v4.0.0 — Release Audit & Problems Fixed

## Release principle
9 Category Manager is the site-planning/infrastructure layer. It creates and organises content shells; 9 Post Manager remains the detailed population/editing layer.

## Problems found during the v4 build

### 1. Category-only thinking no longer matched the real site model
**Problem:** Real WordPress information architecture can be expressed through Categories, Tags, custom taxonomies, post types, authors, parent/child content, `menu_order`, and plugin-specific relationships. Treating only Categories as structure would force manual work elsewhere and make the planner incomplete.

**Fix:** The planner now auto-discovers native structural systems and exposes explicit relationship providers for non-native plugin relationships.

### 2. An editable post type is not necessarily safe for blank-shell creation
**Problem:** Plugins commonly expose technical `show_ui` post types such as orders, logs, templates or sync records. A generic planner must not create empty records merely because an administrator can edit them.

**Fix:** Structural management and shell creation are separate capabilities. Generic shell creation requires the mapped create capability and native Title support by default. Dedicated integrations can opt a specialised content type in via `ninecm_can_create_shell_for_post_type`.

### 3. Silent plugin/theme changes can destroy the planner's assumptions
**Problem:** A Course CPT, taxonomy attachment or relationship provider can disappear after a plugin update/deactivation. With a large site, the administrator may continue planning for days before noticing the structural loss.

**Fix / innovation:** Structure Drift Guard stores a small explicit baseline of registered infrastructure and reports critical/warning/informational drift. It stores no body content and never auto-mutates third-party data.

### 4. Gutenberg compatibility declaration was too low
**Problem:** The block declares Block API v3, which WordPress introduced in 6.3, while the earlier v4 working copy still claimed WordPress 6.2 compatibility.

**Fix:** `Requires at least` is now WordPress 6.3 in both plugin header and readme.

### 5. UI capability claims did not fully match unusual CPTs
**Problem:** Earlier v4 UI paths could still render or send Title/Excerpt controls even when a custom post type did not support those native fields.

**Fix:** Backend and front-end structure editors now render/send Title and Excerpt only when the selected post type supports them. The REST API rejects unsupported mutations as a second line of defence.

### 6. “Full infrastructure Blueprint” was narrower than its label
**Problem:** The earlier Blueprint primarily described the selected post type, not the registered site-wide infrastructure.

**Fix:** Blueprint JSON now embeds a whole-site structural snapshot and Drift Guard summary while retaining detailed selected-type planning data.

### 7. Featured image could be confused with content population
**Problem:** In this workflow, Featured Image is planning data. Treating it as evidence that 9 Post Manager had populated the page would make health reports inaccurate.

**Fix:** Featured image does not clear the planning-shell state. Body content, Elementor body data, recognised ACF values, explicit integration hooks or filters can complete the handoff.

### 8. Network retry could duplicate a shell
**Problem:** If a create request succeeded server-side but its response was lost, a second click could create another shell if the client generated a new request key.

**Fix:** Front-end creation retains the same idempotency key until a successful response; the server also stores a short-lived request→post mapping.

### 9. Structural Undo could overwrite unrelated later work
**Problem:** Restoring author, parent and order together when only one field had been bulk-changed could erase a legitimate later edit to another structural field.

**Fix:** Structural undo checkpoints are operation-specific and restore only the field touched by that bulk operation.

### 10. Whole-site structural intelligence can exceed an editor’s normal scope
**Problem:** A site-wide drift snapshot can include registered post types/taxonomies/providers that a limited editor is not authorised to manage. Exposing the raw baseline through the UI/export/AI layer would turn a useful diagnostic into an information-leak surface.

**Fix:** Drift Guard baseline inspection is administrator-only. Non-admin Blueprint and Abilities discovery is permission-scoped and the drift section is marked restricted.

### 11. Native-field capability enforcement needed a second line of defence
**Problem:** Earlier v4 client code could still send `menu_order=0` on content types that did not expose ordering, and a crafted request could attempt Author/Featured Image changes even when those supports were absent.

**Fix:** Both clients now send only supported native fields, and the REST API independently rejects unsupported Author, Featured Image and Menu Order mutations. Featured-image application/removal is verified and rolls the rest of the plan back if it fails.

### 12. WordPress 7.1 changed core post list-table row markup
**Problem:** Plugins that implicitly target old checkbox/title cell markup can break their admin CSS/JS.

**Fix:** Static compatibility audit confirmed 9 Category Manager's custom admin does not rely on the affected `th.check-column`, title-cell or row-action assumptions.

## Scale controls retained
- server-side taxonomy/content paging and search
- bounded bulk builders
- retry-safe shell creation
- taxonomy archive/protection/manual order
- allocation and structural undo checkpoints
- large-directory transient size guard
- public query safety ceiling with visible truncation
- stale term conflict detection
- capability-specific taxonomy writes
- object-level post editing checks
- spreadsheet formula neutralisation in CSV
- sparse term metadata
- no direct custom SQL or custom database tables

## Integration boundary
Unknown Course → Module, Course → Lesson, Publication → Profile or similar relationships are never guessed from arbitrary meta. Integrations must explicitly register a provider. This protects third-party plugin data models.

## Remaining production-only tests
A local static/regression audit cannot reproduce the exact combination of hosting, WordPress database size, theme, Elementor/Pro, ACF, 9 Post Manager and third-party LMS/custom plugins. Before mass creation, perform a short staging smoke run covering one of each native/custom structure actually used on the target site.
