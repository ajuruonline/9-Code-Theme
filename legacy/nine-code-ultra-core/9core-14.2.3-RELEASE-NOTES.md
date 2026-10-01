# 9Core 14.2.3 — Full Screen Editor Visibility Fix

- Forces Full Screen/Edit Panels ON once for existing installations upgrading from earlier builds.
- Uses Gutenberg main content surface for Post Content so title + editor can occupy the viewport.
- Detects compact editors using effective visual viewport and coarse-pointer fallback, covering tablet desktop-mode cases.
- Bottom-right control now says **Full Screen**; active mode changes it to **Close**.
- Panel rows explicitly identify FULL SCREEN title/content and meta-box editing.
- No plugin field, meta box, save callback or post data ownership changes.
