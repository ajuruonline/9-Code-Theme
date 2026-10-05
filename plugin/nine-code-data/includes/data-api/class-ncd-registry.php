<?php
/**
 * Nine Code Data provider contract (version 1) and registry.
 *
 * A provider is the plugin that OWNS a piece of data. It declares what it stores and supplies the
 * callbacks that validate, sanitize, read and write it. Data Manager owns discovery, editing
 * workflows, bulk operations, exchange (JSON/CSV/XLSX/AI), history and recovery, and never
 * reimplements a provider's business rules.
 *
 * Register from any plugin (safe even when Nine Code Data is not installed):
 *
 *     add_action( 'ninecode_data_register_providers', function () {
 *         ninecode_data_register_provider( 'my-app', array( ...see docs/DATA-PROVIDER-CONTRACT.md... ) );
 *     } );
 *
 * Every registered post type, taxonomy, user meta and ACF field is ALSO discovered generically
 * (NCD_Generic_Provider), so a plugin is visible before it adds a dedicated adapter.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Registry {
	const CONTRACT = 1;

	/** Field types understood by the workspace UI and the exchange layer. */
	const FIELD_TYPES = array(
		'text', 'textarea', 'html', 'number', 'integer', 'email', 'url', 'date', 'datetime', 'year',
		'select', 'multiselect', 'checkbox', 'boolean', 'json', 'media', 'media_list', 'post', 'post_list',
		'user', 'user_list', 'term', 'term_list', 'status', 'group', 'repeater', 'flexible', 'readonly',
	);

	/** Entity kinds with built-in storage helpers. 'custom' entities must supply all callbacks. */
	const KINDS = array( 'post', 'term', 'user', 'custom' );

	private static $providers = array();
	private static $booted    = false;
	private static $errors    = array();

	/** Collect providers once, after every plugin registered its post types/taxonomies. */
	public static function boot() {
		if ( self::$booted ) { return; }
		self::$booted = true;
		do_action( 'ninecode_data_register_providers' );
		self::import_legacy_declarations();
		NCD_Generic_Provider::register();
		do_action( 'ninecode_data_providers_registered', self::$providers );
	}

	/**
	 * Register a provider. Returns true or a WP_Error describing the first contract violation.
	 *
	 * @param string $id   Provider slug (sanitize_key).
	 * @param array  $args See docs/DATA-PROVIDER-CONTRACT.md.
	 */
	public static function register( $id, array $args ) {
		$id = sanitize_key( $id );
		if ( '' === $id ) { return self::fail( 'ncd_provider_id', 'A provider id is required.' ); }
		$contract = isset( $args['contract'] ) ? (int) $args['contract'] : self::CONTRACT;
		if ( $contract > self::CONTRACT ) {
			return self::fail( 'ncd_contract', sprintf( 'Provider %s needs data contract %d; this Nine Code Data supports %d.', $id, $contract, self::CONTRACT ) );
		}
		$provider = array(
			'id'          => $id,
			'label'       => isset( $args['label'] ) ? (string) $args['label'] : $id,
			'version'     => isset( $args['version'] ) ? (string) $args['version'] : '',
			'contract'    => $contract,
			'owner'       => isset( $args['owner'] ) ? (string) $args['owner'] : $id,
			'description' => isset( $args['description'] ) ? (string) $args['description'] : '',
			'adapter'     => isset( $args['adapter'] ) ? (string) $args['adapter'] : 'dedicated',
			'migrations'  => isset( $args['migrations'] ) && is_array( $args['migrations'] ) ? $args['migrations'] : array(),
			'entities'    => array(),
		);
		foreach ( (array) ( $args['entities'] ?? array() ) as $entity_id => $entity ) {
			$normalized = self::normalize_entity( $provider, (string) $entity_id, (array) $entity );
			if ( is_wp_error( $normalized ) ) { return self::fail( $normalized->get_error_code(), $normalized->get_error_message() ); }
			$provider['entities'][ $normalized['id'] ] = $normalized;
		}
		if ( ! $provider['entities'] ) { return self::fail( 'ncd_entities', sprintf( 'Provider %s declares no entities.', $id ) ); }
		self::$providers[ $id ] = $provider;
		return true;
	}

	private static function fail( $code, $message ) {
		self::$errors[] = $message;
		return new WP_Error( $code, $message );
	}

	public static function errors() { return self::$errors; }

	private static function normalize_entity( array $provider, $entity_id, array $e ) {
		$entity_id = sanitize_key( $entity_id );
		$kind      = isset( $e['kind'] ) ? sanitize_key( $e['kind'] ) : 'post';
		if ( '' === $entity_id ) { return new WP_Error( 'ncd_entity_id', 'Entity ids must be non-empty slugs.' ); }
		if ( ! in_array( $kind, self::KINDS, true ) ) { return new WP_Error( 'ncd_entity_kind', sprintf( 'Entity %s has unknown kind %s.', $entity_id, $kind ) ); }
		$object_type = isset( $e['object_type'] ) ? (string) $e['object_type'] : '';
		if ( 'post' === $kind && ( '' === $object_type || ! post_type_exists( $object_type ) ) ) {
			return new WP_Error( 'ncd_entity_object', sprintf( 'Entity %s.%s: post type "%s" is not registered.', $provider['id'], $entity_id, $object_type ) );
		}
		if ( 'term' === $kind && ( '' === $object_type || ! taxonomy_exists( $object_type ) ) ) {
			return new WP_Error( 'ncd_entity_object', sprintf( 'Entity %s.%s: taxonomy "%s" is not registered.', $provider['id'], $entity_id, $object_type ) );
		}
		if ( 'custom' === $kind ) {
			foreach ( array( 'list_callback', 'read_callback' ) as $cb ) {
				if ( empty( $e[ $cb ] ) || ! is_callable( $e[ $cb ] ) ) {
					return new WP_Error( 'ncd_entity_callbacks', sprintf( 'Custom entity %s.%s must supply a callable %s.', $provider['id'], $entity_id, $cb ) );
				}
			}
		}
		foreach ( array( 'read_callback', 'write_callback', 'validate_callback', 'list_callback', 'count_callback', 'create_callback', 'trash_callback', 'permission_callback', 'export_transform', 'import_transform', 'publish_callback', 'label_callback', 'transaction_callback' ) as $cb ) {
			if ( isset( $e[ $cb ] ) && ! is_callable( $e[ $cb ] ) ) {
				return new WP_Error( 'ncd_entity_callable', sprintf( 'Entity %s.%s: %s is not callable.', $provider['id'], $entity_id, $cb ) );
			}
		}
		$fields = array();
		foreach ( (array) ( $e['fields'] ?? array() ) as $key => $field ) {
			$problem = self::check_field( (string) $key, (array) $field );
			if ( $problem ) { return new WP_Error( 'ncd_field', sprintf( 'Entity %s.%s: %s', $provider['id'], $entity_id, $problem ) ); }
			$f = self::normalize_field( (string) $key, (array) $field );
			// A custom entity without a write callback cannot persist fields that have no write callback of their own.
			if ( 'custom' === $kind && empty( $e['write_callback'] ) && empty( $f['write'] ) && $f['writable'] ) {
				$f['writable'] = false;
				$f['reason']   = $f['reason'] ? $f['reason'] : 'The owning plugin does not accept edits to this field';
			}
			$fields[ $f['key'] ] = $f;
		}
		foreach ( (array) ( $e['protected_fields'] ?? array() ) as $pk ) {
			if ( isset( $fields[ $pk ] ) ) { $fields[ $pk ]['protected'] = true; $fields[ $pk ]['writable'] = false; }
		}
		return array(
			'id'           => $entity_id,
			'provider'     => $provider['id'],
			'label'        => isset( $e['label'] ) ? (string) $e['label'] : $entity_id,
			'singular'     => isset( $e['singular'] ) ? (string) $e['singular'] : ( $e['label'] ?? $entity_id ),
			'kind'         => $kind,
			'object_type'  => $object_type,
			'description'  => isset( $e['description'] ) ? (string) $e['description'] : '',
			'fields'       => $fields,
			'statuses'     => isset( $e['statuses'] ) ? array_values( (array) $e['statuses'] ) : ( 'post' === $kind ? array( 'publish', 'future', 'draft', 'pending', 'private' ) : array() ),
			'publish'      => isset( $e['publish'] ) && is_array( $e['publish'] ) ? $e['publish'] : array(),
			'relationships'=> isset( $e['relationships'] ) && is_array( $e['relationships'] ) ? $e['relationships'] : array(),
			'capabilities' => isset( $e['capabilities'] ) && is_array( $e['capabilities'] ) ? $e['capabilities'] : array(),
			'allow_create' => ! empty( $e['allow_create'] ) || isset( $e['create_callback'] ) || ( 'post' === $kind && ! array_key_exists( 'allow_create', $e ) ),
			'allow_trash'  => ! empty( $e['allow_trash'] ) || isset( $e['trash_callback'] ) || ( 'post' === $kind && ! array_key_exists( 'allow_trash', $e ) ),
			'search_fields'=> isset( $e['search_fields'] ) ? (array) $e['search_fields'] : array(),
			'columns'      => isset( $e['columns'] ) ? array_values( (array) $e['columns'] ) : array(),
			'notes'        => isset( $e['notes'] ) ? (array) $e['notes'] : array(),
			'callbacks'    => array_filter( array(
				'read'        => $e['read_callback'] ?? null,
				'write'       => $e['write_callback'] ?? null,
				'validate'    => $e['validate_callback'] ?? null,
				'list'        => $e['list_callback'] ?? null,
				'count'       => $e['count_callback'] ?? null,
				'create'      => $e['create_callback'] ?? null,
				'trash'       => $e['trash_callback'] ?? null,
				'permission'  => $e['permission_callback'] ?? null,
				'export'      => $e['export_transform'] ?? null,
				'import'      => $e['import_transform'] ?? null,
				'publish'     => $e['publish_callback'] ?? null,
				'label'       => $e['label_callback'] ?? null,
				'transaction' => $e['transaction_callback'] ?? null,
			) ),
		);
	}

	const STORAGES = array( 'post_field', 'meta', 'taxonomy', 'acf', 'thumbnail', 'user_field', 'term_field', 'callback', 'none' );

	/** Contract check for one raw field definition (recursive). Returns '' when valid. */
	private static function check_field( $key, array $f ) {
		if ( '' === $key ) { return 'field keys must be non-empty'; }
		if ( isset( $f['type'] ) && ! in_array( sanitize_key( $f['type'] ), self::FIELD_TYPES, true ) ) { return sprintf( 'field "%s" has unknown type "%s"', $key, $f['type'] ); }
		if ( isset( $f['storage'] ) && ! in_array( sanitize_key( $f['storage'] ), self::STORAGES, true ) ) { return sprintf( 'field "%s" has unknown storage "%s"', $key, $f['storage'] ); }
		foreach ( array( 'validate_callback', 'sanitize_callback', 'read_callback', 'write_callback' ) as $cb ) {
			if ( isset( $f[ $cb ] ) && ! is_callable( $f[ $cb ] ) ) { return sprintf( 'field "%s": %s is not callable', $key, $cb ); }
		}
		foreach ( (array) ( $f['sub_fields'] ?? array() ) as $sk => $sf ) {
			$p = self::check_field( (string) ( $sf['key'] ?? $sk ), (array) $sf );
			if ( $p ) { return $p; }
		}
		foreach ( (array) ( $f['layouts'] ?? array() ) as $layout ) {
			foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $sk => $sf ) {
				$p = self::check_field( (string) ( $sf['key'] ?? $sk ), (array) $sf );
				if ( $p ) { return $p; }
			}
		}
		return '';
	}

	/** Normalize one field definition (also used recursively for group/repeater sub-fields). */
	public static function normalize_field( $key, array $f ) {
		$type = isset( $f['type'] ) ? sanitize_key( $f['type'] ) : 'text';
		if ( ! in_array( $type, self::FIELD_TYPES, true ) ) { $type = 'text'; }
		$storage = isset( $f['storage'] ) ? sanitize_key( $f['storage'] ) : 'meta';
		$protected = ! empty( $f['protected'] );
		$out = array(
			'key'         => $key,
			'label'       => isset( $f['label'] ) ? (string) $f['label'] : ucwords( str_replace( array( '_', '-' ), ' ', ltrim( $key, '_' ) ) ),
			'type'        => $type,
			'storage'     => $storage, // post_field|meta|taxonomy|acf|thumbnail|user_field|term_field|callback|none
			'source'      => isset( $f['source'] ) ? (string) $f['source'] : $key, // meta key / post field / taxonomy / ACF key
			'group'       => isset( $f['group'] ) ? (string) $f['group'] : 'fields',
			'help'        => isset( $f['help'] ) ? (string) $f['help'] : '',
			'example'     => $f['example'] ?? '',
			'required'    => ! empty( $f['required'] ),
			'choices'     => isset( $f['choices'] ) && is_array( $f['choices'] ) ? $f['choices'] : array(),
			'protected'   => $protected,
			'writable'    => ! $protected && ( ! isset( $f['writable'] ) || (bool) $f['writable'] ) && 'readonly' !== $type,
			'secret'      => ! empty( $f['secret'] ),
			'reason'      => isset( $f['reason'] ) ? (string) $f['reason'] : '',
			'applies_to'  => isset( $f['applies_to'] ) ? array_values( (array) $f['applies_to'] ) : array(),
			'target'      => isset( $f['target'] ) ? (string) $f['target'] : '', // post type / taxonomy / role for relationship fields
			'single'      => ! isset( $f['single'] ) || (bool) $f['single'], // meta stored as one row (true) or one row per value
			'multiple'    => ! empty( $f['multiple'] ) || in_array( $type, array( 'multiselect', 'media_list', 'post_list', 'user_list', 'term_list', 'repeater', 'flexible' ), true ),
			'origin'      => isset( $f['origin'] ) ? (string) $f['origin'] : 'provider',
			'sub_fields'  => array(),
			'layouts'     => array(),
			'min'         => $f['min'] ?? null,
			'max'         => $f['max'] ?? null,
			'validate'    => isset( $f['validate_callback'] ) && is_callable( $f['validate_callback'] ) ? $f['validate_callback'] : null,
			'sanitize'    => isset( $f['sanitize_callback'] ) && is_callable( $f['sanitize_callback'] ) ? $f['sanitize_callback'] : null,
			'read'        => isset( $f['read_callback'] ) && is_callable( $f['read_callback'] ) ? $f['read_callback'] : null,
			'write'       => isset( $f['write_callback'] ) && is_callable( $f['write_callback'] ) ? $f['write_callback'] : null,
		);
		foreach ( (array) ( $f['sub_fields'] ?? array() ) as $sk => $sf ) {
			$sf  = (array) $sf;
			$sub = self::normalize_field( (string) ( $sf['key'] ?? $sk ), $sf );
			$out['sub_fields'][ $sub['key'] ] = $sub;
		}
		foreach ( (array) ( $f['layouts'] ?? array() ) as $lk => $layout ) {
			$layout = (array) $layout;
			$name   = (string) ( $layout['name'] ?? $lk );
			$subs   = array();
			foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $sk => $sf ) { $sf = (array) $sf; $sub = self::normalize_field( (string) ( $sf['key'] ?? $sk ), $sf ); $subs[ $sub['key'] ] = $sub; }
			$out['layouts'][ $name ] = array( 'name' => $name, 'label' => (string) ( $layout['label'] ?? $name ), 'sub_fields' => $subs );
		}
		return $out;
	}

	/**
	 * Providers that only declared themselves through the 11.0-draft filter
	 * `ninecode_data_manager_providers` (array with post_types) become generic-backed providers.
	 */
	private static function import_legacy_declarations() {
		$legacy = apply_filters( 'ninecode_data_manager_providers', array() );
		foreach ( (array) $legacy as $id => $decl ) {
			$id = sanitize_key( is_string( $id ) ? $id : ( $decl['id'] ?? '' ) );
			if ( '' === $id || isset( self::$providers[ $id ] ) || empty( $decl['post_types'] ) ) { continue; }
			$entities = array();
			foreach ( (array) $decl['post_types'] as $pt ) {
				if ( ! post_type_exists( $pt ) ) { continue; }
				$entities[ $pt ] = NCD_Generic_Provider::post_entity_definition( $pt );
			}
			if ( $entities ) {
				self::register( $id, array(
					'label'    => (string) ( $decl['label'] ?? $id ),
					'version'  => (string) ( $decl['contract_version'] ?? '' ),
					'owner'    => (string) ( $decl['owner'] ?? $id ),
					'adapter'  => 'declared',
					'entities' => $entities,
				) );
			}
		}
	}

	public static function providers() { self::boot(); return self::$providers; }

	public static function provider( $id ) { self::boot(); return self::$providers[ sanitize_key( $id ) ] ?? null; }

	public static function entity( $provider_id, $entity_id ) {
		$p = self::provider( $provider_id );
		return $p['entities'][ sanitize_key( $entity_id ) ] ?? null;
	}

	/** Post types / taxonomies already claimed by a non-generic provider. */
	public static function claimed_objects( $kind ) {
		$out = array();
		foreach ( self::$providers as $p ) {
			if ( 'wordpress' === $p['id'] ) { continue; }
			foreach ( $p['entities'] as $e ) { if ( $e['kind'] === $kind && $e['object_type'] ) { $out[ $e['object_type'] ] = $p['id'] . '.' . $e['id']; } }
		}
		return $out;
	}

	/** For tests: forget everything so a fresh boot can run. */
	public static function reset() { self::$providers = array(); self::$booted = false; self::$errors = array(); }

	/** Public, JSON-safe description of a provider/entity (no callbacks). */
	public static function describe_entity( array $e ) {
		$fields = array();
		foreach ( $e['fields'] as $k => $f ) { $fields[ $k ] = self::describe_field( $f ); }
		return array(
			'id' => $e['id'], 'provider' => $e['provider'], 'label' => $e['label'], 'singular' => $e['singular'],
			'kind' => $e['kind'], 'object_type' => $e['object_type'], 'description' => $e['description'],
			'statuses' => $e['statuses'], 'allow_create' => $e['allow_create'], 'allow_trash' => $e['allow_trash'],
			'relationships' => $e['relationships'], 'columns' => $e['columns'], 'notes' => $e['notes'],
			'publish' => array_diff_key( $e['publish'], array( 'callback' => 1 ) ),
			'fields' => $fields,
		);
	}

	public static function describe_field( array $f ) {
		$out = array_diff_key( $f, array( 'validate' => 1, 'sanitize' => 1, 'read' => 1, 'write' => 1 ) );
		foreach ( $out['sub_fields'] as $k => $sf ) { $out['sub_fields'][ $k ] = self::describe_field( $sf ); }
		foreach ( $out['layouts'] as $k => $l ) { foreach ( $l['sub_fields'] as $sk => $sf ) { $out['layouts'][ $k ]['sub_fields'][ $sk ] = self::describe_field( $sf ); } }
		return $out;
	}
}
