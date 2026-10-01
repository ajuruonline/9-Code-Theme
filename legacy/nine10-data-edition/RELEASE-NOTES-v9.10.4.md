# 9 Data Manager 9.10.4 — Unified Launcher + Form Categories

## Theme launcher ownership
- Post Editor and Category Manager no longer add separate public floating launchers when 9Code Theme Quick Actions is active.
- Their complete front-end workspaces remain available and open from the Theme launcher through the shared front-end action events.
- If the Theme launcher is absent, the previous Data Manager buttons remain available as a safe fallback.

## Forms everywhere data is edited
- Every editable Post, Page and show-ui CPT gets a **9 Data Form** selector plus placement control.
- Placement options are Manual/Selected Only, Before Content and After Content.
- Data Manager Post Editor enriches those registered fields into named dropdowns instead of raw form IDs.
- Shortcode placement remains available for exact layout positioning.

## Category Responses
- Forms may be assigned one or more response categories.
- Managed responses record their source post/page and inherit its WordPress categories plus the form's assigned categories.
- Form Manager now includes a dedicated **Category Responses** screen with Category + optional Form filters.
- Matching category responses can be downloaded as a combined CSV with source, form, categories and dynamic response columns.
- CSV exports retain spreadsheet-formula injection protection.

## Compatibility
- Legacy internal module class names/slugs remain in place for upgrade compatibility.
- User-facing labels remain Post Editor, Category Manager, Post Creator, Form Manager and Data Backup.
- Mason and 9Page remain discontinued and are not dependencies.
