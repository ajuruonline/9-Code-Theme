# Nine Plugin Portability Integration Contract

`9 Import, Export & Backup Manager` can automatically inspect many normal WordPress plugin patterns, but every first-party Nine plugin should explicitly declare its portable assets when possible. This makes exports deterministic and avoids depending on source-code heuristics.

## Registry filter

Use `nine_backup_plugin_registry` and key the entry by the plugin basename or by its slug.

```php
add_filter( 'nine_backup_plugin_registry', function ( $registry ) {
    $registry['nine-example/nine-example.php'] = array(
        'option_names'    => array(
            'nine_example_settings',
            'nine_example_layout',
        ),
        'option_prefixes' => array(
            'nine_example_',
        ),
        'table_names'     => array(),
        'table_suffixes'  => array(
            'nine_example_records',
        ),
        'post_types'      => array(
            'nine_example',
        ),
        'taxonomies'      => array(
            'nine_example_group',
        ),
    );

    return $registry;
} );
```

### Fields

- `option_names`: exact WordPress option names owned by the plugin.
- `option_prefixes`: prefixes used to discover a family of plugin options.
- `table_names`: exact prefixed table names. Prefer `table_suffixes` for portability where possible.
- `table_suffixes`: names appended to the current `$wpdb->prefix`.
- `post_types`: plugin-owned custom post types that should be eligible for record inclusion.
- `taxonomies`: plugin-owned taxonomies that should be eligible for record inclusion.

## Sensitive settings

The manager redacts option names containing credential-like patterns by default. Extend those patterns when a plugin uses a custom secret name:

```php
add_filter( 'nine_backup_sensitive_option_patterns', function ( $patterns ) {
    $patterns[] = 'nine_example_credential';
    return $patterns;
} );
```

Do not deliberately rename secrets to evade redaction. If a controlled migration really requires secrets, the administrator can explicitly enable **Include sensitive settings** for that package.

## Context remapping

Standard URL replacements happen automatically. A plugin with IDs, paths or relationships that require special migration logic can customize individual settings:

```php
add_filter( 'nine_backup_remap_plugin_option', function ( $value, $option_name, $plugin_file, $bundle ) {
    if ( 'nine_example_settings' === $option_name && is_array( $value ) ) {
        // Perform plugin-specific migration here.
    }
    return $value;
}, 10, 4 );
```

Custom-table rows can be adjusted after the generic site-context remap and before insertion:

```php
add_filter( 'nine_backup_remap_plugin_table_row', function ( $row, $source_table, $destination_table, $plugin_file, $maps, $bundle ) {
    // $maps can contain source-to-destination record ID mappings.
    return $row;
}, 10, 6 );
```

For non-plugin custom tables handled by the general table importer, use `nine_backup_remap_table_row`.

## Import lifecycle hooks

Use these around plugin code replacement when necessary:

```php
add_action( 'nine_backup_before_plugin_import', function ( $record, $strategy, $activation_policy ) {
    // Optional preparation before files are replaced.
}, 10, 3 );

add_action( 'nine_backup_after_plugin_import', function ( $record, $strategy, $activation_policy ) {
    // Optional post-install/update housekeeping.
}, 10, 3 );
```

Do not use these hooks to silently activate unrelated plugins or modify records outside the imported plugin's ownership.

## Plugin dependency declaration

Where supported by the deployed WordPress version, use the normal plugin header:

```text
Requires Plugins: dependency-slug, another-dependency
```

When **Include declared plugin dependencies** is enabled, Backup Manager attempts to include installed dependencies recursively. The import preflight reports missing dependencies and compatibility information.

## Recommended baseline for all Nine plugins

Every Nine plugin that stores durable data should define:

1. its plugin registry entry;
2. all durable options/settings it owns;
3. every custom DB table suffix;
4. owned custom post types/taxonomies;
5. any additional sensitive-option patterns;
6. remapping hooks where stored values contain IDs/paths that cannot be migrated generically.

This keeps the Nine suite portable across client sites without forcing each plugin to invent a separate backup format.
