# Nine Code Data — provider contract v1

A *provider* is a plugin or app that owns some data and tells Data Manager how to show, validate and
write it. Data Manager never owns another plugin's business rules: it discovers, displays, diffs,
exports, imports, records history and undoes — but every value is cleaned, checked and written by the
owning provider's callbacks (or by WordPress' own APIs for generic data).

Without a provider, generic discovery still works (see "Generic discovery" below). A provider is needed
when the plugin keeps editable data in private (`_`-prefixed) keys, custom tables, or has rules that a
plain meta write would bypass (publishing rules, derived data, logs).

## Registering

```php
add_action( 'ninecode_data_register_providers', function () {
	if ( ! function_exists( 'ninecode_data_register_provider' ) ) { return; }
	ninecode_data_register_provider( 'my-app', array(
		'label'      => 'My App',
		'version'    => MY_APP_VERSION,     // shown in discovery; bump with your plugin
		'contract'   => 1,                  // optional; newer contracts are refused by older Data Manager
		'owner'      => 'my-app',
		'migrations' => array( '2.0.0' => 'Field "x" renamed to "y"' ), // human-readable history
		'entities'   => array( 'item' => array( /* entity, see below */ ) ),
	) );
} );
```

`ninecode_data_register_provider()` returns `true` or a `WP_Error` describing the first contract
violation; violations are also listed in the discovery report for administrators.
`ninecode_data_contract_version()` returns the contract the installed Data Manager speaks (1).

Registration happens lazily, after `init`, so post types and taxonomies are available.

## Entities

| Key | Meaning |
| --- | --- |
| `kind` | `post`, `term`, `user` or `custom` |
| `object_type` | post type / taxonomy (required for `post` and `term`) |
| `label`, `singular`, `description`, `notes[]` | shown in the Workspace |
| `fields` | field map (below) |
| `statuses` | allowed statuses (post kind) |
| `publish` | `array( 'field' => 'post_status', 'values' => array( 'publish' ) )` — which field publishes |
| `columns` | list columns |
| `relationships` | informational links between entities (exported in the AI schema) |
| `capabilities` | optional cap names per action (`read`, `edit`, `create`, `trash`, `publish`) |
| `allow_create`, `allow_trash` | default true for posts, false otherwise (true when the callback exists) |
| `protected_fields[]` | field keys forced read-only |

Callbacks (all optional for `post`/`term`/`user`; `custom` needs `list_callback` and `read_callback`):

| Callback | Signature | Notes |
| --- | --- | --- |
| `read_callback` | `( $id, $entity ) : array\|null` | values keyed by field key; `null` = record not found |
| `write_callback` | `( $id, $clean, $context ) : true\|WP_Error\|array( 'errors'=>[], 'warnings'=>[] )` | receives only validated, sanitized changes |
| `validate_callback` | `( $id, $clean, $context ) : array( field => message )` | cross-field rules; `$id` is 0 on create |
| `list_callback` | `( $args, $entity ) : array( 'ids'=>[], 'total'=>n )` | `$args`: search, status, page, per_page, order, ids, filters |
| `create_callback` | `( $values, $entity ) : int\|WP_Error` | must return the new id |
| `trash_callback` | `( $id, $entity ) : true\|WP_Error` | never hard-delete |
| `permission_callback` | `( $action, $id, $entity ) : bool` | overrides WordPress cap mapping |
| `publish_callback` | `( $id, $value, $context ) : true\|WP_Error` | owns publication; runs after other fields |
| `transaction_callback` | `( callable $work ) : bool` | run `$work()` in a transaction; roll back when it returns false |
| `export_transform` / `import_transform` | `( $values, $id, $entity ) : array` | representation changes for files |
| `label_callback` | `( $id, $entity ) : array( label, status, modified, edit_url, view_url )` | list/summary |

`$context` carries `source` (editor, mobile, bulk, import, undo, api), `allow_status`, `allow_create`,
`allow_trash`.

## Fields

| Key | Meaning |
| --- | --- |
| `type` | `text textarea html number integer email url date datetime year select multiselect checkbox boolean json media media_list post post_list user user_list term term_list status group repeater flexible readonly` |
| `storage` | `post_field meta taxonomy acf thumbnail user_field term_field callback none` |
| `source` | meta key / post field / taxonomy / ACF key |
| `label`, `group`, `help`, `example`, `required`, `choices`, `min`, `max`, `target` | display and validation |
| `protected`, `reason` | read-only, with the reason shown to users |
| `secret` | never shown, exported or written (implies protected) |
| `sub_fields`, `layouts` | group / repeater / flexible structure |
| `applies_to` | informational (e.g. record types that use the field) |
| `sanitize_callback` | `( $raw, $field ) : clean value\|WP_Error` |
| `validate_callback` | `( $clean, $field, $id, $context ) : '' \| message \| WP_Error` |
| `read_callback` / `write_callback` | per-field storage (`storage => callback`) |

Unknown types, storages or non-callable callbacks are refused at registration.

## What Data Manager guarantees to providers

* Only users with `manage_ninecode_data` (or `manage_options`) reach Data Manager; per record, the
  provider's `permission_callback` or WordPress' capability mapping decides.
* Writes contain only declared, writable, non-protected fields that passed the field sanitizer, the
  field validator and the entity validator — unknown keys are rejected (no mass assignment).
* Status/publication, creation and trashing need an explicit permission in the request (a toggle in the
  UI, `allow_status` / `allow_create` / `allow_trash` over REST) **and** the matching capability.
* Each record write holds a scope lock; a stale `expected_revision` is refused as a conflict.
* Without a transaction callback, a failing field write rolls back the fields already written for that
  record (compensating rollback).
* Every change is recorded in History (before/after per field) and can be undone; undo skips fields
  changed again later unless forced.

## Generic discovery

Without a provider, every post type with UI, every taxonomy with UI and users are listed, with core
fields, taxonomies, ACF field groups (incl. group, repeater, flexible content, gallery, file, relationship,
post object, user, taxonomy fields) and meta (registered keys plus keys found in the data).

Protection rules for generic meta:

* Secret-looking keys (`password`, `token`, `secret`, `api_key`, `_hash`, `nonce`, `webhook`, …) are
  secret: never shown or exported.
* Structural WordPress/plugin keys (`_edit_lock`, `_wp_*`, `_thumbnail_id`, capabilities, user level,
  page builder and SEO internals, ACF storage) are protected.
* Other `_`-prefixed keys are protected **unless** the owner registered them with `register_meta()`
  using both `show_in_rest` and an `auth_callback` — WordPress then routes every write through
  `edit_{type}_meta`, which runs the owner's `auth_callback` and `sanitize_callback`.
* Filters: `ninecode_data_classify_meta` (array, key, meta type) and the legacy
  `ninecode_data_manager_writable_meta` (may only unlock non-secret, non-structural private keys).

## Backward compatibility

* The 11.0-draft declaration filter `ninecode_data_manager_providers` (`post_types` list) still works:
  such declarations become generic-backed providers (adapter "declared").
* `ninecode_data_manager_schema` and the `ninecode/v1/data/*` REST routes are served by a compatibility
  layer; `POST ninecode/v1/data/import` is preview-only.
* Draft packages (`format: ninecode-universal-data-package`) are upgraded on import.
