# 9Core 14.2.0 — Conference Update

- Adds a neutral interoperability policy for Conference/Proceeding/Open Scholar, Elementor, ACF and front-end admin tools.
- Core does not register or rewrite foreign CPTs, taxonomies, metadata, navigation or templates.
- Core Builder metadata, meta boxes, editor tools, content transforms and REST routes are post/page only by default; provider CPTs require explicit opt-in.
- Core/Data schema registries register last and never pre-empt provider-owned CPT/taxonomy slugs.
- Upgrade maintenance, including the legacy Data fallback, runs on admin_init rather than public plugins_loaded.
- Admin Workspace yields automatically on Elementor editor and ACF configuration screens, with a generic provider opt-out filter for other admin applications.
- Removes discontinued Mason/9Page diagnostic control-plane logic from the active runtime.
- Diagnostics report interoperability policy/capabilities without loading foreign plugins.
