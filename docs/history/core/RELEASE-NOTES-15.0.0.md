# 9Core 15.0.0 - Mobile Full-Screen Editor Recovery

Release date: 2026-09-28

## Fixed

The compact WordPress editor could present an opaque white full-screen surface while the real Post Content or metadata controls remained clipped underneath a transformed, contained or overflow-limited ancestor.

9Core 15.0.0 changes the focus model:

- Post Content and metadata use the original live WordPress/plugin DOM node; no cloned editor is created.
- A separate dim backdrop sits below the live focused panel.
- Focus mode does not force the live panel or its `.inside` content to a white background.
- Transformed, clipped, contained and overflow-limited ancestors are neutralized only while focus is active and are restored on close.
- The promoted live panel is validated after layout; if it cannot render usable geometry, focus mode cancels and the normal editor is restored.
- Provider-hidden/conditional meta boxes are excluded, while panels hidden only by 9Core's compact presentation remain available in Edit Panels.
- Closed meta boxes are opened temporarily in full-screen mode and their collapsed state is restored on close.
- Page scroll, target scroll and previous keyboard focus are restored.
- A full-screen Save button delegates to Gutenberg `core/editor.savePost()` or the native Classic Editor save/update control.
- WordPress media, ACF/Select2 and component modal layers retain higher z-index authority than the focused panel.

## Compatibility

This is an editor-presentation change. It does not rewrite post content, metadata, ACF values, Rank Math values, CPT ownership, save callbacks or Data Manager contracts. Internal plugin directory remains `nine-code-ultra-core`. Compatibility API and suite contract major remain 14.
