# 9Core 14.2.2 — Mobile Editor Focus Update

## Full-screen Edit Panels
- Adds one compact bottom-right **Edit Panels** control on mobile/tablet post editors.
- **Post Content** opens the live title + content workspace full-screen.
- Every live WordPress/plugin meta box is listed and can open full-screen without being cloned or reimplemented.
- While a panel is full-screen the same bottom-right control becomes **Close**; closing restores the original editor and scroll position.

## Mobile panel visibility
- Third-party/plugin meta boxes are hidden from the normal compact editor flow by default when the master setting is enabled.
- Hidden panels remain available from Edit Panels.
- Every meta box has a per-user visibility switch; choices affect presentation only and do not disable plugins or delete metadata.
- The Theme 9Code Display screen exposes master switches for full-screen panels and default third-party panel hiding.

## Compatibility
- WordPress save/update/publish remains protected.
- ACF/Rank Math/other plugin fields keep their original DOM, callbacks and form submission behavior.
- Elementor editor mode remains excluded.
- Media modal, Select2 and ACF popup layers remain above a full-screen editor panel.
- Gutenberg iframe canvas is supported through its live container.
