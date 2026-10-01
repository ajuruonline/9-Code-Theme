# 9CodePress Conference Compatibility Contract

## Core rule

WordPress owns routing. The plugin that registers a custom post type, taxonomy, template, field group or application surface owns that object and its business logic. 9Code Theme/Core/Data may assist but must not silently redefine it.

## Public presentation

- External plugin templates and custom post types receive no 9Code Theme presentation stylesheet by default.
- Elementor-owned pages yield Theme presentation CSS.
- Embedded plugin components are protected from generic Theme form/table/widget/navigation selectors.
- A plugin can claim a full application surface through `ninecodepress_surface_context` and set `suppress_theme_quick_actions` when its own rail/dialog needs the space.
- The Theme launcher deliberately sits below provider modal/dialog layers.

## Core

- Core interoperability is descriptive/additive: it does not register foreign CPTs/taxonomies, ACF groups, Elementor locations or Frontend Admin routes.
- Core Builders are limited to `post` and `page` by default. Provider CPTs require explicit opt-in through `ncu_builder_post_types`.
- Core Admin Workspace yields on Elementor editor and ACF configuration screens. Other plugin applications can opt out with `ncu_admin_skin_foreign_app_screen`.
- Version migrations run on activation/admin requests, not ordinary public traffic.

## Data Manager

- Public/provider CPTs may be discovered for editing; internal non-public builder/configuration CPTs are excluded by default.
- Private/system provider meta is read-only unless the owner explicitly allows a key through `nine10_data_meta_editable`.
- Data-managed schema definitions register last and skip any CPT/taxonomy slug already registered by a provider. Providers can veto a pending Data registry definition through `nine10_data_registry_can_register`.
- Form Manager follows the same public-content boundary and allows explicit type control through `nine10_data_form_post_types`.
- Form attachment REST writes require permission to edit the specific object.

## Elementor / ACF / Frontend Admin

- No hard dependency is introduced. All provider APIs are capability-checked before use.
- Elementor remains presentation owner for its documents/templates.
- ACF remains canonical owner of ACF field definitions and values.
- Frontend Admin or another front-end editing product remains owner of its forms/actions; 9Core's own front-end editor is off unless explicitly enabled.

## Future plugin checklist

1. Prefix CPTs, taxonomies, functions/classes, REST routes and options.
2. Register content/data in the plugin, not the Theme.
3. Use normal WordPress template hierarchy or `template_include`.
4. For a full-screen application surface, implement `ninecodepress_surface_context`.
5. Never require 9Code Theme/Core/Data to load in order for the plugin's public data to remain valid.
6. Keep public requests read-only except for explicit user submissions.
7. Put upgrades/migrations on activation or admin maintenance paths.
