# 9CF AI Workflow

9CF (Nine Content Fields) is the portable content contract used by 9 Post Manager v3. It is not a second database and it does not require Advanced Custom Fields.

## Daily mobile workflow

1. Open a post/page/CPT in 9 Post Manager, from wp-admin or the front-end 9P button.
2. Tap **9CF / Export**.
3. Download **Numbered 9CF AI Form**.
4. Upload the `.9cf` file to ChatGPT, Gemini, Claude or another AI together with the goal for the page.
5. Ask the AI to fill `field.value` only and to report progress using the field codes (`F001`, `F002`, ...).
6. Review/correct the AI output if necessary.
7. Save/download the completed JSON as `.9cf` (or `.json`).
8. In 9 Post Manager choose **Import Filled 9CF**.
9. Review the **safe** change count plus any protected/conflicted/invalid field codes, then apply. 9PM creates a recovery snapshot first and records the contract in the AI import history.
10. Use the tabbed mobile editor for corrections rather than starting the whole post manually.

## What 9CF can inventory

- WordPress post title, excerpt, slug, status, author and featured image.
- Gutenberg block content and editable registered block attributes.
- 9 Elements (`nine/elements`) content, lists, tabs, media references and relevant settings.
- WordPress categories and other UI taxonomies.
- Existing ACF fields when ACF is installed (ACF Bridge provider).
- Public post meta and registered REST-visible plugin meta, including registered fields that are still empty.
- Semantic fields registered by another Nine/plugin integration through the 9CF provider filters.

## Numbering rule

Every exported field gets a sequential human code such as `F001`. The code is for progress tracking and pen-and-paper workflows. The importer does **not** use the number as the database key. It uses the stable `field.id` and source path, so adding/removing another field does not cause F-number changes to write into the wrong place.

## Gutenberg-first rule

Gutenberg/9 Elements is the primary daily content surface. ACF remains supported, but it is an optional provider/bridge rather than the 9CF storage model. Elementor remains a specialist design route.

## Media rule

An AI must never invent WordPress attachment IDs. It can preserve an existing ID/URL/filename or state that a media field still needs a real image/file. Actual new uploads should be done through WordPress Media Library.


## Schema v2 safe-apply rule

A filled 9CF file may be older than the live post. 9PM therefore compares each incoming field with its exported `baseline_hash`. Safe fields can still apply even when another field is stale. Fields changed on the site after export, protected settings (`ai_fill=false`), invalid values, unknown fields and ambiguous moved blocks are skipped rather than overwriting newer work.

For the safest workflow, export a fresh schema-v2 9CF file before each AI editing session. Legacy schema-v1 imports remain accepted but cannot provide field-level baseline conflict protection.
