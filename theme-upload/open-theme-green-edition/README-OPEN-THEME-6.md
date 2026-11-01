# Open Theme 6.0.0 — Open Navigation exclusive ownership

Open Theme renders no site-level header or footer itself. Open Navigation (9stagram/Open Navigator) exclusively manages those areas. The WordPress `wp_head`, `wp_body_open`, and `wp_footer` lifecycle hooks remain intact for accessibility and plugin compatibility.

This release adds Elementor Pro/HFE suppression hooks and targeted CSS/DOM fallbacks for commonly used external site chrome. It does not remove article headers, headings, or admin screens. External plugins with custom markup may require further integration.

**Installation:** Appearance → Themes → Add New → Upload Theme. Back up existing theme, activate Open Theme 6, clear caches, verify with Open Navigation on and off.

**Recovery:** Use hosting File Manager to replace `inc/exclusive-navigation.php` or restore the previous theme.
