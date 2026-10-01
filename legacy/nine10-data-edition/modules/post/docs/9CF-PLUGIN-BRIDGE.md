# 9CF Plugin / Widget Bridge

9CF already discovers Gutenberg blocks, 9 Elements, public post meta and REST-visible registered post meta. A plugin should register a semantic bridge only when its data is not represented cleanly by those mechanisms.

## Register semantic fields

Use the `npm9_9cf_fields` filter. Each field should provide a stable ID, label and current value.

```php
add_filter( 'npm9_9cf_fields', function( $fields, $post_id, $mode ) {
    $fields[] = [
        'id'       => 'my_plugin:summary',
        'section'  => 'My Plugin',
        'label'    => 'Summary',
        'type'     => 'textarea',
        'value'    => get_post_meta( $post_id, '_my_summary', true ),
        'editable' => true,
        'ai_fill'  => true,
        'hint'     => 'Short public summary.',
        'source'   => [ 'meta_key' => '_my_summary' ],
    ];
    return $fields;
}, 10, 3 );
```

## Apply a semantic field

If ordinary `source.meta_key` is sufficient, 9CF can write it automatically. For a custom storage API use `npm9_9cf_apply_field` and return a non-null value when handled.

```php
add_filter( 'npm9_9cf_apply_field', function( $handled, $post_id, $field, $value ) {
    if ( 'plugin:my_plugin_summary' !== $field['id'] ) {
        return $handled;
    }
    my_plugin_save_summary( $post_id, $value );
    return true;
}, 10, 4 );
```

## ACF / Elementor bridge

9CF can optionally mirror every editable field into deterministic `ninecf_*` post-meta keys. Elementor can use those as Custom Fields without ACF. If ACF Dynamic Tags are preferred, 9PM can download an ACF field-group JSON whose names are those same mirror keys. The original content remains owned by WordPress/Gutenberg/plugin storage; the ACF fields are a presentation bridge, not a replacement database.

## Third-party static blocks with HTML-sourced attributes

WordPress block attributes can be stored in the block delimiter or derived from saved HTML. 9CF can generically and safely write delimiter attributes. For source-backed attributes (`source: html`, `text`, `attribute`, `query`) the plugin should register a semantic 9CF provider unless 9 Post Manager already has a dedicated core-block handler. This prevents a generic importer from writing the right value into the wrong serialization location.


## v3.1 provider validation hooks

Providers may additionally use:

- `npm9_9cf_validate_field` — return `WP_Error` to reject an incoming value for a provider field.
- `npm9_9cf_sanitize_field_value` — return the provider-normalised value before 9CF writes it.

Existing `npm9_9cf_fields` and `npm9_9cf_apply_field` integrations remain compatible. Registered post meta should prefer `register_post_meta()` with `show_in_rest`, `sanitize_callback`, and appropriate authorization; 9CF v3.1 also checks `edit_post_meta` before enabling writes.
