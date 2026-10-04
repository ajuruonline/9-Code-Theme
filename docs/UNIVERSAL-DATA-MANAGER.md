# Universal Data Manager contract

Nine Code Data is the suite data owner. Core, Manager, Data Manager and app
plugins remain separate owners, but every registered WordPress object is
discoverable from Data Manager.

## What is discovered

- registered post types and their supports;
- registered taxonomies and assignments;
- registered post meta;
- ACF field groups and fields, when ACF is active;
- provider schemas added through `ninecode_data_manager_providers`;
- provider field catalogs added through `ninecode_data_manager_schema`.

The Universal Data workspace is available under **Nine Code Data → Universal
Data**. It exports a complete JSON package, supports AI/spreadsheet round trips,
previews imports, creates a recovery snapshot, and only permits publishing
changes when the operator explicitly enables it and has the WordPress capability.

## Provider integration

An app can advertise itself without taking ownership away from its own plugin:

```php
add_filter( 'ninecode_data_manager_providers', function ( $providers ) {
    $providers['my-app'] = array(
        'id'          => 'my-app',
        'label'       => 'My App',
        'status'      => 'active',
        'owner'       => 'my-app',
        'post_types'  => array( 'my_record' ),
        'data_manager'=> 'ninecode-universal',
        'contract_version' => '1.0.0',
    );
    return $providers;
} );

add_filter( 'ninecode_data_manager_schema', function ( $schema ) {
    // Add provider_field_catalog['my-app'] or enrich post_types['my_record'].
    return $schema;
} );
```

The provider remains responsible for validation, rendering, and special storage
rules. Data Manager handles discovery, bulk editing, export/import, preview,
backup, and operator permissions.
