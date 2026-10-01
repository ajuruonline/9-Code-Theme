# 9Code Theme 14.1.11 — Responsive Brand Bounds + Blindspot Hardening

## Fixed
- Header fallback `9` now has explicit bounded font-size and line-height.
- Header logo/site icon is clamped to the actual available header height and a compact maximum.
- Mobile logo caps: 36px standard mobile, 32px narrow mobile.
- Legacy saved header dimensions are normalized at runtime, so old oversized values cannot bypass current limits.
- Logo width control now caps at 48px.
- Custom PNG/SVG header action icons are constrained to their action button and section-icon boxes.
- Header action rows scroll horizontally when overfilled instead of escaping the viewport.
- Header/legacy side-tab z-index values are normalized below WordPress admin bar and below Theme Quick Actions.
- Theme-native fallback brand marks receive the same containment rules.

## Preserved
- Theme title and post meta remain OFF by default.
- Theme Menu + Save + Launcher remain the only permanent logged-in public controls.
- Post Editor / Category Manager remain lazy-loaded from the launcher.
- 9Core 14.1.5 and 9 Data Manager 9.10.5 are unchanged.
