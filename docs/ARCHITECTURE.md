# Architecture

## Layout
```
theme/nine-code/          the theme (Text Domain: nine-code)
  functions.php           loads defaults, setup, Core module, then the public or admin runtime
  core/                   former 9Core plugin (bootstrap.php, inc/, assets/)
  inc/                    theme modules (display settings, header/footer engine, quick actions...)
  template-parts/ templates/ patterns/ blocks/
plugin/nine-code-data/    the plugin (Text Domain: nine-code-data)
  nine-code-data.php      header + old-plugin guard + constants only
  includes/bootstrap.php  everything else (kept separate: PHP hoists top-level declarations)
  modules/{post,category,data,ai}   bundled engines
```

## What goes where
| Belongs in the theme | Belongs in the plugin |
|---|---|
| Templates, template parts, patterns, block styles | Custom post types and taxonomies |
| Header/footer/nav, public CSS and display settings | Database tables, import/export, backups |
| Style Authority, branding, admin skin | Shortcodes and blocks used inside content |
| Editor *chrome* (hamburger/popover) | Post/category/form data tools and REST routes |

Rule of thumb: if removing the feature would make content disappear or break on switching themes, it is a plugin.

## Runtime split (performance)
Public requests load a deliberately small runtime (`inc/frontend-safe.php`, `inc/post-display-settings.php`,
and the Header/Footer engine only if a surface is switched on). wp-admin loads the full configuration modules.
Core's settings/branding/dark-mode modules load on every request, as the old plugin did.

## Cross-component contract
Theme and plugin talk only through filters/functions guarded by `function_exists`/`defined`:
`ninecodepress_component_contracts`, `ninecodepress_surface_context`, `nine10_data_theme_launcher_available`,
`NINE55_ULTRON_DATA_VERSION`. Either piece works without the other.

## Upgrade safety
`inc/migrate-legacy.php` (theme) and the guard in `nine-code-data.php` (plugin) retire the old plugins without
duplicate-declaration fatals in any activation order. Internal names (`ncu_*`, `nine10_*`, option keys, admin
page slugs) are intentionally unchanged so stored data keeps working.
