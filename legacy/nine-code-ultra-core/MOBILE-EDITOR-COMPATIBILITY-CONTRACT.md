# 9Code Mobile Editor Compatibility Contract v1.1

1. **Protected controls:** Save, Update, Publish and minimum WordPress editing controls must remain directly reachable at mobile/tablet widths.
2. **Drawer-first extras:** Third-party toolbar/admin-bar actions may be visually removed from the cramped header only after a usable proxy is placed in the Editor Tools drawer.
3. **Provider ownership:** The source button/link remains owned by its plugin; 9Core proxies the click/navigation and does not duplicate the underlying operation.
4. **Live-panel focus:** Post Content and meta boxes may be presented full-screen, but 9Core must operate on the existing live DOM rather than copy/rebuild plugin fields.
5. **Presentation-only hiding:** Mobile visibility switches may hide a panel from normal flow, but must never unregister the meta box, deactivate the plugin, delete metadata or bypass save callbacks.
6. **Per-user preference:** Individual panel visibility is stored per user; global Theme/Core settings only control the presentation defaults.
7. **No content repaint:** Admin skin rules must not alter published content CSS. Full-screen focus may enlarge the live editor surface only while editing.
8. **Popup safety:** WordPress media, ACF/Select2 and plugin modal layers must be able to render above a focused editor panel.
9. **One suite owner:** Theme Quick Actions and Data Manager admin-bar shortcuts yield on post editor screens so Core owns the editor-shell controls.
10. **Future compatibility:** Additional plugins do not need named integration; public toolbar controls and live `.postbox` surfaces are discovered generically.
11. **Recovery:** Disabling Editor Tools, Edit Panels or High Contrast restores native WordPress/plugin presentation without changing stored content.

## Presentation invariant

The editor-focus layer is never a second editor. It does not clone, serialize, rebuild or replace provider fields. A focused region is the original live WordPress/plugin DOM with temporary presentation classes only. Closing focus restores the normal layout and preserves native save semantics.
