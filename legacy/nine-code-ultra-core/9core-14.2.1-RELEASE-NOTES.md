# 9Core 14.2.1 — Mobile Editor Update

## Editor Tools drawer
On WordPress block-editor post screens at widths up to 1180px, nonessential header and admin-bar actions are represented in one right-side **Editor Tools** drawer. Save/Update/Publish, undo/redo, inserter, settings and core options stay protected. The original third-party controls remain in the DOM and are triggered by proxy actions, preserving plugin ownership.

Known Elementor, Rank Math, Yoast/AIOSEO, Jetpack, ACF and front-end-admin labels are classified as plugin tools, while the capture rule remains generic for future toolbar additions.

## 9Code editor skin
The post editor receives stronger panel headings, field borders, active tabs, labels, focus states and publish controls without styling `.editor-styles-wrapper` or changing public content appearance. Classic-editor title/meta-box chrome also gets a bounded high-contrast treatment.

## Clutter/performance cleanup
- Core command-palette admin-bar node is suppressed on post editor screens.
- Core's second Styles PluginSidebar icon is not registered on mobile/tablet.
- Admin Workspace context bar stays out of post editors.
- Editor assets load only on post editing screens; drawer JavaScript loads only for Gutenberg/block-editor requests.
