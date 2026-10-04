<?php
/**
 * Data exchange: export (JSON AI package, CSV, XLSX), staged import, dry-run preview with per-field
 * conflict detection, and selective, batched application with one undoable history entry.
 *
 * Nothing in a package can create, trash, publish or unpublish records unless the operator ticks the
 * matching permission in the preview; those actions are always listed in the preview first.
 * Data is never sent to an external service: packages are files the operator downloads.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Exchange {
	const FORMAT      = 'ninecode-data-package';
	const VERSION     = 2;
	const MAX_EXPORT  = 5000;
	const MAX_RECORDS = 20000;
	const MAX_BYTES   = 52428800; // 50 MB

	/* ================================================================ export */

	/**
	 * Collect records for export.
	 *
	 * @param array $args ids[] or search/status/filters; limit.
	 */
	public static function export_records( array $entity, array $args ) {
		if ( ! NCD_Service::can( 'read', $entity ) ) { return new WP_Error( 'ncd_forbidden', 'You cannot export this data type.', array( 'status' => 403 ) ); }
		$limit   = max( 1, min( self::MAX_EXPORT, (int) ( $args['limit'] ?? self::MAX_EXPORT ) ) );
		$records = array();
		$page    = 1;
		$args    = array_merge( $args, array( 'per_page' => 100 ) );
		do {
			$res = NCD_Store::query( $entity, array_merge( $args, array( 'page' => $page ) ) );
			foreach ( $res['ids'] as $id ) {
				if ( count( $records ) >= $limit ) { break 2; }
				if ( ! NCD_Service::can( 'read', $entity, $id ) ) { continue; }
				$records[] = self::export_record( $entity, $id );
			}
			$page++;
		} while ( $res['ids'] && ! empty( $res['total'] ) && ( $page - 1 ) * 100 < $res['total'] && empty( $args['ids'] ) );
		return $records;
	}

	public static function export_record( array $entity, $id ) {
		$values   = NCD_Store::read( $entity, $id );
		if ( isset( $entity['callbacks']['export'] ) ) { $values = (array) call_user_func( $entity['callbacks']['export'], $values, (int) $id, $entity ); }
		$fields   = array();
		$readonly = array();
		$base     = array();
		$links    = array();
		foreach ( $entity['fields'] as $k => $f ) {
			if ( ! array_key_exists( $k, $values ) || $f['secret'] ) { continue; }
			$v = $values[ $k ];
			if ( $f['writable'] && empty( $f['protected'] ) ) { $fields[ $k ] = $v; $base[ $k ] = NCD_Types::hash( $v ); }
			else { $readonly[ $k ] = $v; }
			$l = self::link_info( $f, $v );
			if ( $l ) { $links[ $k ] = $l; }
		}
		$summary = NCD_Store::summary( $entity, $id );
		$out = array(
			'_ref'   => array( 'id' => (int) $id, 'revision' => NCD_Service::fingerprint( $values ), 'base' => $base ),
			'_label' => $summary['label'],
			'fields' => $fields,
		);
		if ( $readonly ) { $out['readonly'] = $readonly; }
		if ( $links ) { $out['_links'] = $links; }
		return $out;
	}

	/** Human/AI-readable context for media and relationship ids (ignored on import). */
	private static function link_info( array $f, $v ) {
		$ids = in_array( $f['type'], array( 'media', 'post', 'user', 'term' ), true ) ? array( $v ) : ( in_array( $f['type'], array( 'media_list', 'post_list', 'user_list', 'term_list' ), true ) ? (array) $v : array() );
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) { return null; }
		$out = array();
		foreach ( array_slice( $ids, 0, 50 ) as $id ) {
			if ( in_array( $f['type'], array( 'media', 'media_list' ), true ) ) { $out[] = array( 'id' => $id, 'url' => (string) wp_get_attachment_url( $id ), 'title' => get_the_title( $id ) ); }
			elseif ( in_array( $f['type'], array( 'post', 'post_list' ), true ) ) { $out[] = array( 'id' => $id, 'title' => get_the_title( $id ), 'type' => get_post_type( $id ) ); }
			elseif ( in_array( $f['type'], array( 'user', 'user_list' ), true ) ) { $u = get_userdata( $id ); $out[] = array( 'id' => $id, 'name' => $u ? $u->display_name : '' ); }
			else { $t = get_term( $id ); $out[] = array( 'id' => $id, 'name' => ( $t && ! is_wp_error( $t ) ) ? $t->name : '' ); }
		}
		return $out;
	}

	public static function instructions( array $entity ) {
		return array(
			'This is a Nine Code Data package for "' . $entity['label'] . '" (' . $entity['provider'] . '.' . $entity['id'] . ') exported from ' . home_url( '/' ) . '.',
			'Edit only the values inside each record\'s "fields" object. Keep every "_ref" object exactly as it is: it identifies the record and detects conflicts.',
			'"readonly", "_label" and "_links" are context only. Changes there are ignored.',
			'Follow the field types in "schema.fields": select fields accept only the listed choices; *_list fields are arrays of ids; group/repeater/flexible fields keep their nested structure; dates use YYYY-MM-DD.',
			'Do not add, remove or reorder records unless you were explicitly asked to. To propose a new record, add one without "_ref" and with "_action": "create". To propose removing a record, set "_action": "trash" on it. The site operator must approve both.',
			'Do not change status/publication fields unless explicitly asked. Publishing changes need the operator\'s explicit approval.',
			'Return the complete package as valid JSON with the same "format", "provider" and "entity" values.',
		);
	}

	public static function package( array $entity, array $records ) {
		$schema = array();
		foreach ( $entity['fields'] as $k => $f ) {
			if ( $f['secret'] ) { continue; }
			$schema[ $k ] = NCD_Registry::describe_field( $f );
		}
		return array(
			'format'          => self::FORMAT,
			'package_version' => self::VERSION,
			'contract'        => NCD_Registry::CONTRACT,
			'generated_at'    => gmdate( 'c' ),
			'site'            => home_url( '/' ),
			'provider'        => $entity['provider'],
			'entity'          => $entity['id'],
			'entity_label'    => $entity['label'],
			'instructions'    => self::instructions( $entity ),
			'approval_required_for' => array( 'create' => 'records without _ref / _action:create', 'trash' => '_action:trash', 'status' => 'changes to the publication/status field' ),
			'schema'          => array( 'fields' => $schema ),
			'records'         => $records,
		);
	}

	/** Columns and rows for CSV/XLSX: _id, _action, _revision, _label, then writable fields. */
	public static function flatten( array $entity, array $records ) {
		$keys = array();
		foreach ( $entity['fields'] as $k => $f ) { if ( $f['writable'] && empty( $f['protected'] ) && ! $f['secret'] ) { $keys[] = $k; } }
		$headers = array_merge( array( '_id', '_action', '_revision', '_label' ), $keys );
		$rows    = array();
		foreach ( $records as $r ) {
			$row = array( '_id' => $r['_ref']['id'], '_action' => '', '_revision' => $r['_ref']['revision'], '_label' => $r['_label'] );
			foreach ( $keys as $k ) { $row[ $k ] = self::cell( $entity['fields'][ $k ], $r['fields'][ $k ] ?? '' ); }
			$rows[] = $row;
		}
		$guide = array();
		foreach ( $keys as $k ) {
			$f = $entity['fields'][ $k ];
			$guide[] = array( 'column' => $k, 'label' => $f['label'], 'type' => $f['type'], 'allowed values' => $f['choices'] ? implode( ' | ', array_keys( $f['choices'] ) ) : '', 'help' => $f['help'], 'required' => $f['required'] ? 'yes' : '' );
		}
		array_unshift( $guide, array( 'column' => '_id / _action / _revision', 'label' => 'Do not change', 'type' => 'control', 'allowed values' => '_action: blank (update), create, trash', 'help' => 'Leave _id and _revision as exported. New rows: leave _id empty and set _action to create (needs approval).', 'required' => '' ) );
		return array( 'headers' => $headers, 'rows' => $rows, 'guide' => $guide );
	}

	private static function cell( array $f, $v ) {
		if ( in_array( $f['type'], array( 'media_list', 'post_list', 'user_list', 'term_list', 'multiselect' ), true ) && is_array( $v ) ) { return implode( ', ', array_map( 'strval', $v ) ); }
		if ( is_array( $v ) || is_object( $v ) ) { return (string) wp_json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
		if ( is_bool( $v ) ) { return $v ? '1' : '0'; }
		return (string) $v;
	}

	/* ================================================================ import: parse + stage */

	/** Parse an uploaded/pasted file into a normalized package for $entity. */
	public static function parse( $raw_or_path, $ext, array $entity, $is_path = true ) {
		$ext = strtolower( (string) $ext );
		if ( $is_path && filesize( $raw_or_path ) > self::MAX_BYTES ) { return new WP_Error( 'ncd_too_large', 'The file is larger than 50 MB. Split it into smaller files.' ); }
		if ( 'json' === $ext ) {
			$raw = $is_path ? file_get_contents( $raw_or_path ) : (string) $raw_or_path; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- uploaded temp file.
			$pkg = json_decode( (string) $raw, true );
			if ( ! is_array( $pkg ) ) { return new WP_Error( 'ncd_json', 'The package is not valid JSON (' . json_last_error_msg() . ').' ); }
			return self::normalize_package( $pkg, $entity );
		}
		if ( 'csv' === $ext || 'xlsx' === $ext ) {
			if ( ! $is_path ) { return new WP_Error( 'ncd_format', 'Spreadsheets must be uploaded as files.' ); }
			$sheet = 'csv' === $ext ? NCD_Sheet::read_csv( $raw_or_path ) : NCD_Sheet::read_xlsx( $raw_or_path );
			if ( is_wp_error( $sheet ) ) { return $sheet; }
			return self::sheet_to_package( $sheet, $entity );
		}
		return new WP_Error( 'ncd_format', 'Unsupported file type. Use .json, .csv or .xlsx.' );
	}

	public static function normalize_package( array $pkg, array $entity ) {
		$format = (string) ( $pkg['format'] ?? '' );
		if ( 'ninecode-universal-data-package' === $format ) { $pkg = self::upgrade_draft_package( $pkg, $entity ); $format = self::FORMAT; }
		if ( self::FORMAT !== $format ) { return new WP_Error( 'ncd_format', 'This is not a Nine Code Data package (missing "format": "' . self::FORMAT . '").' ); }
		if ( ! empty( $pkg['provider'] ) && ( $pkg['provider'] !== $entity['provider'] || ( $pkg['entity'] ?? '' ) !== $entity['id'] ) ) {
			return new WP_Error( 'ncd_scope', 'This package belongs to ' . $pkg['provider'] . '.' . ( $pkg['entity'] ?? '?' ) . ', not ' . $entity['provider'] . '.' . $entity['id'] . '. Open the matching data type to import it.' );
		}
		if ( ! isset( $pkg['records'] ) || ! is_array( $pkg['records'] ) ) { return new WP_Error( 'ncd_records', 'The package has no "records" list.' ); }
		if ( count( $pkg['records'] ) > self::MAX_RECORDS ) { return new WP_Error( 'ncd_records', 'Packages are limited to ' . self::MAX_RECORDS . ' records. Split the file.' ); }
		$records = array();
		foreach ( array_values( $pkg['records'] ) as $i => $r ) {
			if ( ! is_array( $r ) || ! isset( $r['fields'] ) || ! is_array( $r['fields'] ) ) { return new WP_Error( 'ncd_record', 'Record ' . ( $i + 1 ) . ' has no "fields" object.' ); }
			$ref = isset( $r['_ref'] ) && is_array( $r['_ref'] ) ? $r['_ref'] : array();
			$records[] = array(
				'id'       => absint( $ref['id'] ?? 0 ),
				'revision' => (string) ( $ref['revision'] ?? '' ),
				'base'     => isset( $ref['base'] ) && is_array( $ref['base'] ) ? $ref['base'] : array(),
				'action'   => self::action( $r['_action'] ?? '', absint( $ref['id'] ?? 0 ) ),
				'label'    => (string) ( $r['_label'] ?? '' ),
				'fields'   => $r['fields'],
			);
		}
		return array( 'provider' => $entity['provider'], 'entity' => $entity['id'], 'source' => 'json', 'records' => $records );
	}

	private static function action( $raw, $id ) {
		$a = sanitize_key( (string) $raw );
		if ( in_array( $a, array( 'create', 'trash', 'skip' ), true ) ) { return $a; }
		return $id ? 'update' : 'create';
	}

	private static function sheet_to_package( array $sheet, array $entity ) {
		if ( ! in_array( '_id', $sheet['headers'], true ) ) { return new WP_Error( 'ncd_sheet', 'The first row must contain the exported column keys, including _id.' ); }
		$unknown = array_diff( array_filter( $sheet['headers'] ), array( '_id', '_action', '_revision', '_label' ), array_keys( $entity['fields'] ) );
		$records = array();
		foreach ( $sheet['rows'] as $row ) {
			$id = absint( $row['_id'] ?? 0 );
			$fields = array_diff_key( $row, array_flip( array( '_id', '_action', '_revision', '_label' ) ) );
			$records[] = array( 'id' => $id, 'revision' => (string) ( $row['_revision'] ?? '' ), 'base' => array(), 'action' => self::action( $row['_action'] ?? '', $id ), 'label' => (string) ( $row['_label'] ?? '' ), 'fields' => $fields );
		}
		return array( 'provider' => $entity['provider'], 'entity' => $entity['id'], 'source' => 'sheet', 'records' => $records, 'warnings' => $unknown ? array( 'Unknown columns ignored: ' . implode( ', ', $unknown ) ) : array() );
	}

	/** 11.0-draft "universal" packages (title/content/meta/acf/taxonomies) -> current field keys. */
	private static function upgrade_draft_package( array $pkg, array $entity ) {
		$records = array();
		foreach ( (array) ( $pkg['records'] ?? array() ) as $r ) {
			$f = array();
			foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status', 'slug' => 'post_name' ) as $old => $new ) { if ( isset( $r[ $old ] ) ) { $f[ $new ] = $r[ $old ]; } }
			foreach ( (array) ( $r['meta'] ?? array() ) as $k => $v ) { $f[ 'meta:' . $k ] = $v; }
			foreach ( (array) ( $r['acf'] ?? array() ) as $k => $v ) { $f[ 'acf:' . $k ] = $v; }
			foreach ( (array) ( $r['taxonomies'] ?? array() ) as $tax => $slugs ) { $f[ 'tax_' . $tax ] = $slugs; }
			$records[] = array( '_ref' => array( 'id' => absint( $r['id'] ?? 0 ) ), 'fields' => array_intersect_key( $f, $entity['fields'] ) );
		}
		return array( 'format' => self::FORMAT, 'records' => $records );
	}

	private static function staging_dir() {
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'ncd-staging';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- protect staging dir.
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- protect staging dir.
		}
		return $dir;
	}

	/** Store a parsed package server-side so it can be previewed/applied in batches. */
	public static function stage( array $package ) {
		$dir = self::staging_dir();
		foreach ( (array) glob( $dir . '/*.json' ) as $old ) { if ( filemtime( $old ) < time() - DAY_IN_SECONDS ) { wp_delete_file( $old ); } }
		$token = wp_generate_password( 32, false, false );
		file_put_contents( $dir . '/' . $token . '.json', wp_json_encode( $package ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- private staging file.
		set_transient( 'ncd_stage_' . $token, array( 'user' => get_current_user_id(), 'provider' => $package['provider'], 'entity' => $package['entity'], 'count' => count( $package['records'] ) ), DAY_IN_SECONDS );
		return array( 'token' => $token, 'count' => count( $package['records'] ), 'warnings' => $package['warnings'] ?? array() );
	}

	public static function load_stage( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
		$meta  = get_transient( 'ncd_stage_' . $token );
		if ( ! $token || ! is_array( $meta ) ) { return new WP_Error( 'ncd_stage', 'This import has expired. Upload the file again.', array( 'status' => 410 ) ); }
		if ( (int) $meta['user'] !== get_current_user_id() ) { return new WP_Error( 'ncd_forbidden', 'This import belongs to another user.', array( 'status' => 403 ) ); }
		$path = self::staging_dir() . '/' . $token . '.json';
		$pkg  = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- private staging file.
		return is_array( $pkg ) ? $pkg : new WP_Error( 'ncd_stage', 'The staged import could not be read.', array( 'status' => 410 ) );
	}

	public static function discard_stage( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
		delete_transient( 'ncd_stage_' . $token );
		wp_delete_file( self::staging_dir() . '/' . $token . '.json' );
	}

	/* ================================================================ preview */

	/**
	 * Dry run for records [offset, offset+limit). Nothing is written.
	 *
	 * @param array $options allow_create, allow_trash, allow_status.
	 */
	public static function preview( array $entity, array $package, $offset, $limit, array $options ) {
		$rows    = array();
		$records = array_slice( $package['records'], (int) $offset, max( 1, min( 200, (int) $limit ) ), true );
		foreach ( $records as $index => $rec ) { $rows[] = self::plan_record( $entity, (int) $index, $rec, $options ); }
		return array( 'rows' => $rows, 'offset' => (int) $offset, 'total' => count( $package['records'] ) );
	}

	/** Field-by-field plan for one staged record. */
	public static function plan_record( array $entity, $index, array $rec, array $options ) {
		$ctx    = array( 'source' => 'import', 'allow_status' => ! empty( $options['allow_status'] ) );
		$row    = array( 'index' => $index, 'id' => $rec['id'], 'label' => $rec['label'], 'action' => $rec['action'], 'blocked' => '', 'fields' => array(), 'changes' => 0, 'errors' => 0, 'conflicts' => 0 );
		$fields = $rec['fields'];
		if ( isset( $entity['callbacks']['import'] ) ) { $fields = (array) call_user_func( $entity['callbacks']['import'], $fields, $rec['id'], $entity ); }

		if ( 'skip' === $rec['action'] ) { $row['blocked'] = 'Marked _action: skip'; return $row; }
		if ( 'trash' === $rec['action'] ) {
			if ( ! $rec['id'] || ! NCD_Store::exists( $entity, $rec['id'] ) ) { $row['blocked'] = 'Record #' . $rec['id'] . ' does not exist in ' . $entity['label']; $row['errors']++; return $row; }
			$row['label'] = NCD_Store::summary( $entity, $rec['id'] )['label'];
			if ( ! $entity['allow_trash'] || ! NCD_Service::can( 'trash', $entity, $rec['id'] ) ) { $row['blocked'] = 'You cannot trash this record'; $row['errors']++; }
			elseif ( empty( $options['allow_trash'] ) ) { $row['blocked'] = 'Needs "Allow moving records to the trash"'; }
			return $row;
		}
		if ( 'create' === $rec['action'] ) {
			if ( ! $entity['allow_create'] || ! NCD_Service::can( 'create', $entity ) ) { $row['blocked'] = 'You cannot create records of this type'; $row['errors']++; return $row; }
			if ( empty( $options['allow_create'] ) ) { $row['blocked'] = 'Needs "Allow creating records"'; }
			$v = NCD_Service::validate( $entity, 0, $fields, $ctx );
			foreach ( $fields as $k => $after ) {
				$f = $entity['fields'][ $k ] ?? null;
				$status = isset( $v['errors'][ $k ] ) ? 'invalid' : 'change';
				$row['fields'][] = array( 'key' => $k, 'label' => $f ? $f['label'] : $k, 'before' => '', 'after' => self::short( $v['clean'][ $k ] ?? $after ), 'status' => $status, 'message' => $v['errors'][ $k ] ?? '' );
				'invalid' === $status ? $row['errors']++ : $row['changes']++;
			}
			return $row;
		}
		// update
		if ( ! $rec['id'] || ! NCD_Store::exists( $entity, $rec['id'] ) ) { $row['blocked'] = 'Record #' . $rec['id'] . ' does not exist in ' . $entity['label'] . ' (wrong data type or deleted)'; $row['errors']++; return $row; }
		if ( ! NCD_Service::can( 'edit', $entity, $rec['id'] ) ) { $row['blocked'] = 'You cannot edit this record'; $row['errors']++; return $row; }
		$row['label'] = NCD_Store::summary( $entity, $rec['id'] )['label'];
		$v        = NCD_Service::validate( $entity, $rec['id'], $fields, $ctx );
		$current  = $v['current'];
		$record_changed = $rec['revision'] && ! $rec['base'] && $rec['revision'] !== NCD_Service::fingerprint( $current );
		$publish  = NCD_Service::publish_config( $entity );
		foreach ( $fields as $k => $after ) {
			$f = $entity['fields'][ $k ] ?? null;
			$item = array( 'key' => $k, 'label' => $f ? $f['label'] : $k, 'before' => self::short( $current[ $k ] ?? '' ), 'after' => self::short( $v['clean'][ $k ] ?? $after ), 'status' => 'unchanged', 'message' => '' );
			if ( in_array( $k, $v['unchanged'], true ) ) { continue; }
			if ( isset( $v['errors'][ $k ] ) ) {
				$item['status']  = ( $f && ( $f['protected'] || ! $f['writable'] ) ) ? 'protected' : ( $k === $publish['field'] && empty( $options['allow_status'] ) ? 'status' : ( ! $f ? 'unknown' : 'invalid' ) );
				$item['message'] = $v['errors'][ $k ];
				'status' === $item['status'] ? $row['changes']++ : $row['errors']++;
			} elseif ( array_key_exists( $k, $v['clean'] ) ) {
				$item['status'] = 'change';
				$conflict = ( isset( $rec['base'][ $k ] ) && $rec['base'][ $k ] !== NCD_Types::hash( $current[ $k ] ?? null ) ) || $record_changed;
				if ( $conflict ) { $item['status'] = 'conflict'; $item['message'] = 'Changed on the site since this file was exported. Tick it to overwrite.'; $row['conflicts']++; }
				else { $row['changes']++; }
			} else { continue; }
			$row['fields'][] = $item;
		}
		if ( ! $row['fields'] ) { $row['action'] = 'unchanged'; }
		return $row;
	}

	private static function short( $v ) {
		if ( is_array( $v ) || is_object( $v ) ) { $v = wp_json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
		$v = (string) $v;
		return strlen( $v ) > 600 ? substr( $v, 0, 600 ) . '…' : $v;
	}

	/* ================================================================ apply */

	/**
	 * Apply selected changes for a batch of staged records.
	 *
	 * @param array $selection index => true (all valid, non-conflicting changes) | string[] field keys (explicit, includes conflicts).
	 * @param array $options   allow_create, allow_trash, allow_status, history_id (append to an import already started).
	 */
	public static function apply( array $entity, array $package, array $selection, array $options ) {
		$ctx     = array( 'source' => 'import', 'allow_status' => ! empty( $options['allow_status'] ), 'allow_create' => ! empty( $options['allow_create'] ), 'allow_trash' => ! empty( $options['allow_trash'] ), 'no_history' => true, 'partial' => true );
		$results = array();
		$history = array();
		foreach ( $selection as $index => $pick ) {
			$index = (int) $index;
			if ( ! isset( $package['records'][ $index ] ) ) { continue; }
			$rec  = $package['records'][ $index ];
			$plan = self::plan_record( $entity, $index, $rec, $options );
			if ( $plan['blocked'] ) { $results[] = array( 'index' => $index, 'ok' => false, 'message' => $plan['blocked'] ); continue; }
			$fields = $rec['fields'];
			if ( isset( $entity['callbacks']['import'] ) ) { $fields = (array) call_user_func( $entity['callbacks']['import'], $fields, $rec['id'], $entity ); }
			$allowed = array();
			foreach ( $plan['fields'] as $pf ) {
				if ( true === $pick || '1' === $pick || 'all' === $pick ) { if ( 'change' === $pf['status'] ) { $allowed[] = $pf['key']; } }
				elseif ( is_array( $pick ) && in_array( $pf['key'], $pick, true ) && in_array( $pf['status'], array( 'change', 'conflict' ), true ) ) { $allowed[] = $pf['key']; }
			}
			$skipped = array();
			foreach ( $plan['fields'] as $pf ) {
				if ( ! in_array( $pf['key'], $allowed, true ) ) { $skipped[ $pf['key'] ] = $pf['message'] ? $pf['message'] : 'Not selected'; }
			}
			if ( 'trash' === $rec['action'] ) {
				$prev_status = NCD_Store::summary( $entity, $rec['id'] )['status'];
				$r = NCD_Service::trash( $entity, $rec['id'], $ctx );
				if ( is_wp_error( $r ) ) { $results[] = array( 'index' => $index, 'ok' => false, 'message' => $r->get_error_message() ); continue; }
				$history[] = array( 'id' => $rec['id'], 'action' => 'trash', 'before' => array( '_status' => $prev_status ), 'after' => array() );
				$results[] = array( 'index' => $index, 'ok' => true, 'id' => $rec['id'], 'action' => 'trash' );
				continue;
			}
			$changes = array_intersect_key( $fields, array_flip( $allowed ) );
			if ( ! $changes ) { $results[] = array( 'index' => $index, 'ok' => true, 'id' => $rec['id'], 'action' => 'none', 'message' => 'Nothing selected', 'skipped' => $skipped ); continue; }
			if ( 'create' === $rec['action'] ) {
				$r = NCD_Service::create( $entity, $changes, $ctx );
				if ( is_wp_error( $r ) ) { $results[] = array( 'index' => $index, 'ok' => false, 'message' => $r->get_error_message() ); continue; }
				if ( $r['id'] ) { $history[] = array( 'id' => $r['id'], 'action' => 'create', 'before' => array(), 'after' => array() ); }
				$results[] = array( 'index' => $index, 'ok' => $r['ok'], 'id' => $r['id'], 'action' => 'create', 'errors' => $r['errors'], 'warnings' => $r['warnings'], 'skipped' => $skipped );
				continue;
			}
			$r = NCD_Service::save( $entity, $rec['id'], $changes, $ctx );
			if ( is_wp_error( $r ) ) { $results[] = array( 'index' => $index, 'ok' => false, 'message' => $r->get_error_message() ); continue; }
			if ( $r['changed'] ) { $history[] = array( 'id' => $rec['id'], 'action' => 'edit', 'before' => array_intersect_key( $r['before'], $r['after'] ), 'after' => $r['after'] ); }
			$results[] = array( 'index' => $index, 'ok' => $r['ok'], 'id' => $rec['id'], 'action' => 'update', 'changed' => $r['changed'], 'errors' => $r['errors'], 'warnings' => $r['warnings'], 'skipped' => $skipped );
		}
		$hid = absint( $options['history_id'] ?? 0 );
		if ( $history ) {
			$prev = $hid ? NCD_History::get( $hid ) : null;
			$same = $prev && 'import' === $prev['operation'] && $prev['provider'] === $entity['provider'] && $prev['entity'] === $entity['id'];
			if ( ! $same || ! NCD_History::append( $hid, $history ) ) { $hid = NCD_History::add( $entity['provider'], $entity['id'], 'import', $options['source'] ?? 'import', 'Import into ' . $entity['label'], $history ); }
		}
		return array( 'results' => $results, 'history_id' => $hid, 'applied' => count( $history ) );
	}
}
