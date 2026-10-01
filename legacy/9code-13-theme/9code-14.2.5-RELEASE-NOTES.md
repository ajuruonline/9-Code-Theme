# 9Code Theme 14.2.5 — Conference Update

- Theme presentation CSS yields automatically to external plugin templates, every custom post type by default, and Elementor-owned content.
- Global/generic CSS selectors were removed or scoped so Frontend Admin and Special/Elements embedded inside normal content keep their own component styling.
- Fullscreen/plugin surfaces can suppress the logged-in Theme launcher through the shared surface contract.
- Theme launcher/modal z-index now yields to provider-owned dialogs rather than claiming the highest layer.
- Design tokens and isolated logged-in tools remain available without global style takeover.
- Compatibility is ownership-based, not hard-coded to Conference/Open Scholar/9Link/9stagram slugs.
- Safe public runtime and native WordPress Page/Post rendering remain unchanged.
