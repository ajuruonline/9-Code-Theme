# 9Core 15.0.2 - Editor Overlay Removal

## Goal
Keep WordPress Post Content and post metadata directly editable at all times. 9CODE may compact auxiliary controls, but it must never cover the native editor with a full-screen shell.

## Changes
- Replaces the viewport-sized Editor Tools shell/backdrop with a compact non-modal hamburger popover.
- Removes body scroll locking from Editor Tools.
- Hard-cleans legacy focus/full-screen classes and stale overlay DOM on every post editor load.
- Forces legacy Edit Panels/backdrop/full-screen nodes hidden through CSS.
- Disables the 9CODE Command Palette on post editor screens to eliminate a second possible full-viewport layer.
- Keeps Save/Update/Publish visible.
- Keeps Post Content and provider meta boxes in native WordPress flow.
- Upgrade migration turns old focus/hide settings off and turns high-contrast editor repaint off once.

## Ownership
WordPress owns post content and normal editor saving. Provider plugins own their metadata and save callbacks. 9Core owns only compact editor chrome/shortcuts.
