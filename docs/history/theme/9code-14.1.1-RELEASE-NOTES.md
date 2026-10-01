# 9Code 14 Theme — 14.1.1 Release Notes

## Plugin Assistance & Semantic Host Guard

- Adds a public Theme assistance contract and design-token accessor for 9Code-first plugins.
- Declares the non-negotiable host-ownership rule: Theme may style/assist plugin surfaces but must not reinterpret plugin navigation, data or business logic.
- Publishes additive component metadata through `ninecodepress_component_contracts`.
- Bundled starter 9Blocks now declare semantic roles and default to `host_navigation=false`.
- No Header/Footer, Popular Site, Skin, Quick Actions or plugin-data ownership was moved.

**NEEDS LIVE TEST:** plugin surfaces under strict style takeover, 9Page rail behavior, Elementor/Gutenberg coexistence.
