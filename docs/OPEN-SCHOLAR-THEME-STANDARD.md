# Open Scholar Theme standard

Open Scholar Theme is the shared host, presentation and administration layer for the Open Scholar application family. Version 20.1.4 uses the Open Scholar design contract evaluated on 24 October 2026.

## Identity and palette

- Product name: **Open Scholar Theme**; application label: **Open Scholar**.
- Accent: `#14532d`; dark shell: `#123d2a`; page: `#f2f5f3`; card: `#ffffff`.
- Text: `#191919`; muted: `#5e5e5e`; border: `#e0dfdc`; soft green: `#e5f2eb`.
- Cards use an 8px radius, and mobile controls are at least 44px high.
- Settings sections start closed, include a plain-language explanation, stack to one column on phones, and alternate red/green markers so long screens remain navigable.

## Ownership boundaries

- The theme supplies design tokens, templates, Open Scholar administration, branding, host-level SEO fallback and plugin integration contracts.
- Data Manager, AI import/export, fields, app records, forms, builders and blocks belong to independently released Open Scholar plugins and are not bundled into the theme.
- The theme supplies no public header or footer. It still calls `wp_body_open()`, `wp_head()`, and `wp_footer()` so the installed Open Scholar shell/9stagram plugin can render safely.
- Open Scholar plugins own their records, validation, permissions, rendering, and business logic. The theme styles shared surfaces but does not copy or rewrite plugin data.
- Elementor owns Elementor documents and editor surfaces. Open Scholar styling yields where Elementor owns the template.
- WordPress remains the unmodified open-source runtime. Open Scholar changes visible branding through supported hooks and styles; core files, APIs, updates, and security mechanisms remain intact.

## Data Manager compatibility

Data Manager discovers registered post types, taxonomies, ACF/SCF fields, safe registered/discovered metadata, and provider integrations. Open Scholar, Conference.lat, teaching, forms, comments, users, and future apps can expose precise schemas through the existing provider contract. Protected or secret fields remain locked unless the provider explicitly grants write access.

Every AI/data exchange follows export → edit → validate → preview → explicit apply → history/undo. The UI must never publish, create, trash, or change status without the permission and approval required by the provider contract.

Future Open Scholar plugin pages should follow the mobile-first settings convention: explanatory labels, touch-sized controls, and all tabs/sections closed until the user opens them. Plugins may register their identity and capabilities through `open_scholar_theme_integrations` and consume `open_scholar_theme_design_tokens()`.

## Upgrade rule

The folder `nine-code`, text domains, option names, post meta, post types, taxonomies, REST routes, capabilities, hooks, and legacy component IDs are compatibility APIs. Do not rename them without an explicit migration. Visible branding may say Open Scholar; stable machine identifiers prevent duplicate installs and data loss.
