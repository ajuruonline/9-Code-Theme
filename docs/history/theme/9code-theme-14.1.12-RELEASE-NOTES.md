# 9Code Theme 14.1.12 — Universal CPT Host Compatibility

- Adds a generic WordPress/custom-post-type host compatibility layer. No plugin or CPT allow-list is required.
- Captures the final selected WordPress template without changing it.
- External plugin-selected templates become sovereign `plugin` host mode automatically.
- In plugin host mode, Theme Header/Footer and Theme presentation styles are suppressed; logged-in Quick Actions remain available.
- Aggressive Style Takeover is disabled for external plugin templates and for foreign CPT compatibility mode.
- Public custom post types that use the Theme's own single template receive a neutral full-width compatibility path instead of a narrow article reinterpretation.
- Plugins can explicitly opt into or override presentation through `ninecode_theme_host_mode`, `ninecodepress_surface_context`, `ninecode_theme_allow_style_takeover`, and `ninecode_theme_cpt_compatibility_full_width`.
- Existing post/page Theme presentation behavior is preserved.
