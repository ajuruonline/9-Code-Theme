# Architecture

## Product boundary

Open Scholar Theme is a host, not an application bundle.

| Theme owns | Plugins own |
|---|---|
| Branding, design tokens and login/admin presentation | Data Manager and provider-field editing |
| Public template shell with no theme header/footer | Header/footer shell and Instagram navigation |
| Mobile-first, collapsed settings conventions | Conference, teacher and other app workflows |
| Plugin registration and capability discovery contract | AI import/export, builders, forms, blocks and CPTs |
| SEO/Open Graph/schema fallback | Dedicated SEO implementation when installed |
| Jetpack visibility policy | Jetpack services and settings |
| Ultra Backup coordination and disaster snapshots | Plugin-specific data semantics and validation |

The stable install directory is `theme/nine-code/`. Internal `ncu_*` identifiers remain for upgrade compatibility; visible product language is Open Scholar.

## Runtime

`functions.php` loads standard theme setup, migration compatibility, the small host core, and a public-safe stylesheet layer. `core/bootstrap.php` loads only settings, branding, Open Scholar presentation, notices, integration contracts, Jetpack hygiene, SEO and Ultra Backup.

Ultra Backup is a host-infrastructure exception. It is based on the existing Nine Backup 2.2.0 Bluehost-hardened engine and protects the installation as a whole. Its plugin registry lets each plugin declare its durable options, tables, post types and taxonomies without transferring ownership of those records to the theme.

Legacy application modules remain in source history for safe comparison and migration work, but the build excludes them. In particular, the release ZIP contains no bundled Data Manager, AI manager, builders, custom 9 Blocks, theme header/footer engine or legacy front-end editor.

## Plugin integration contract

Plugins register themselves through the `open_scholar_theme_integrations` filter. Each entry can expose an ID, label, description, version, URL and capabilities. The workspace app library lists authorized first-party menus. The sidebar is curated to 9/Open Scholar apps; maintenance routes stay available through the launcher. See `docs/APP-WORKSPACE.md` for custom app identification.

Plugins can consume `open_scholar_theme_design_tokens()` or the retained `ninecode_theme_get_design_tokens()` adapter. The theme fires `open_scholar_theme_ready` after setup and advertises `open-scholar-plugin-host` theme support.

## Ownership and compatibility

The theme never deactivates Data Manager or application plugins. The one retained automatic migration concerns the obsolete 9Core plugin, whose genuinely host-level responsibilities moved into the theme in earlier releases. Stored content, field values, options and plugin registrations are not deleted.

SEO output is a fallback. It stops when a recognized dedicated SEO plugin is active, and can be controlled with the `open_scholar_theme_seo_provider_active` filter. Jetpack modules are not deactivated; promotional and duplicate editor/dashboard surfaces are suppressed while its settings remain reachable from Open Scholar.
