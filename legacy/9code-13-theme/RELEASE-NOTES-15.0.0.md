# 9Code Theme 15.0.0 - Mobile Full-Screen Editor

Release date: 2026-09-28

## Purpose

Theme 15.0.0 synchronizes the presentation shell with 9Core 15.0.0. On WordPress post-edit screens, the Theme yields editor-shell ownership to Core so only one compact mobile editor control system is active.

## Preserved contracts

- Internal theme directory remains `9code-13-theme`.
- Theme/Core compatibility API and suite contract major remain 14.
- Public rendering, Header/Footer, display controls, Quick Actions, provider-surface yielding and native WordPress content fallbacks are preserved.
- Specialist plugins continue to own their CPTs, templates, metadata and application surfaces.

## Mobile editor behavior

The Theme exposes the existing Mobile Editor Workspace settings bridge. Post Content and live metadata panels are focused by Core 15.0.0; the Theme does not create a competing overlay on post-edit screens.
