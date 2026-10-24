# Architecture

## Layout
```
theme/nine-code/          the theme and the whole package (Text Domain: nine-code); one zip, one version
  functions.php           loads defaults, setup, Core module, Data Manager, then the public or admin runtime
  core/                   former 9Core plugin (bootstrap.php, inc/, assets/)
  data-manager/           Data Manager, formerly the "Nine Code Data" plugin (Text Domain: nine-code-data)
    bootstrap.php         version constants, plus a guard for a still-active old plugin copy
    includes/bootstrap.php  everything else (kept separate: PHP hoists top-level declarations)
    modules/{post,category,data,ai}   bundled engines
  inc/                    theme modules (display settings, header/footer engine, quick actions...)
  template-parts/ templates/ patterns/ blocks/
```

## What goes where
| Belongs in the theme | Belongs in Data Manager (`data-manager/`) |
|---|---|
| Templates, template parts, patterns, block styles | Custom post types and taxonomies |
| Header/footer/nav, public CSS and display settings | Database tables, import/export, backups |
| Style Authority, branding, admin skin | Shortcodes and blocks used inside content |
| Editor *chrome* (hamburger/popover) | Post/category/form data tools and REST routes |

Data Manager is inside the theme so one install or update delivers both. The cost: its custom post types,
shortcodes and blocks are registered only while Nine Code is the active theme. Its data stays in the database,
but the tools and shortcodes stop working under another theme. Do not add plugin-only WordPress APIs (activation
hooks, `plugin_dir_*`, `plugins_url`) to the package: use theme events and `NINE55_ULTRON_DATA_URL` instead. The one
exception is `plugin_basename()` in the AI Manager, which updates other plugins. `bin/lint.sh` enforces both rules.

## Runtime split (performance)
Public requests load a deliberately small runtime (`inc/frontend-safe.php`, `inc/post-display-settings.php`,
and the Header/Footer engine only if a surface is switched on). wp-admin loads the full configuration modules.
Core's settings/branding/dark-mode modules load on every request, as the old plugin did.

## Cross-component contract
Theme modules talk to Data Manager only through filters/functions guarded by `function_exists`/`defined`:
`ninecodepress_component_contracts`, `ninecodepress_surface_context`, `nine10_data_theme_launcher_available`,
`NINE55_ULTRON_DATA_VERSION`. `defined( 'NINE55_ULTRON_DATA_VERSION' )` means "Data Manager is loaded".

## Upgrade safety
`inc/migrate-legacy.php` retires the old 9Core plugin and the old data plugins (`nine10-data-edition` and
`nine-code-data`) once the theme is active. `data-manager/bootstrap.php` steps aside while a plugin copy still
defines `NINE55_ULTRON_DATA_VERSION`, so two copies never load in one request. Activation has no plugin hook in a
theme, so the install routine runs from `after_switch_theme`, and from an `admin_init` check that compares the
stored version with `NINE55_ULTRON_DATA_VERSION`. `NCD_History::add()` also creates the history table on its first
write. Internal names (`ncu_*`, `nine10_*`, option keys, meta keys, post types, REST routes, admin page slugs) are
intentionally unchanged so stored data keeps working.
