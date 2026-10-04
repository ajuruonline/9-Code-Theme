<?php
/**
 * Data Manager operations: list, read, validate, save, create, trash, bulk edit, undo.
 *
 * Every mutation:
 *  - requires the Data Manager capability AND the object capability (or the provider's permission callback);
 *  - validates and sanitizes through the owning provider (field/entity callbacks) before falling back
 *    to generic type rules;
 *  - refuses protected/secret fields, unknown fields and fields of other object types (no mass assignment);
 *  - takes a short scope lock on the object, snapshots the fields it touches, rolls back on failure;
 *  - records an audit history entry that can be undone.
 *
 * Status changes (publish/unpublish/schedule), creating and trashing records always require an
 * explicit permission flag in the context, so neither imports nor AI packages can do them silently.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Service {
	const CAP = 'manage_ninecode_data';

	/* ------------------------------------------------------------ access */

	public static function can_use() {
		return current_user_can( self::CAP ) || current_user_can( 'manage_options' );
	}

	/**
	 * @param string $action read|edit|create|trash|publish
	 */
	public static function can( $action, array $entity, $id = 0 ) {
		if ( ! self::can_use() ) { return false; }
		$id = (int) $id;
		if ( isset( $entity['callbacks']['permission'] ) ) {
			return (bool) call_user_func( $entity['callbacks']['permission'], $action, $id, $entity );
		}
		$caps = $entity['capabilities'];
		if ( isset( $caps[ $action ] ) && is_string( $caps[ $action ] ) ) { return $id ? current_user_can( $caps[ $action ], $id ) : current_user_can( $caps[ $action ] ); }
		switch ( $entity['kind'] ) {
			case 'post':
				$pto = get_post_type_object( $entity['object_type'] );
				if ( ! $pto ) { return false; }
				if ( 'create' === $action ) { return current_user_can( $pto->cap->create_posts ); }
				if ( ! $id ) { return 'publish' === $action ? current_user_can( $pto->cap->publish_posts ) : current_user_can( $pto->cap->edit_posts ); }
				if ( 'trash' === $action ) { return current_user_can( 'delete_post', $id ); }
				if ( 'publish' === $action ) { return current_user_can( 'publish_post', $id ) || current_user_can( $pto->cap->publish_posts ); }
				return current_user_can( 'edit_post', $id );
			case 'term':
				$tax = get_taxonomy( $entity['object_type'] );
				if ( ! $tax ) { return false; }
				if ( 'create' === $action || ! $id ) { return current_user_can( $tax->cap->edit_terms ); }
				return current_user_can( 'edit_term', $id );
			case 'user':
				if ( 'read' === $action && ! $id ) { return current_user_can( 'list_users' ); }
				return $id ? current_user_can( 'edit_user', $id ) : current_user_can( 'list_users' );
		}
		return current_user_can( 'manage_options' );
	}

	/** Is this field writable by the current user on this object? @return array{0:bool,1:string} */
	public static function field_access( array $entity, array $field, $id = 0 ) {
		if ( ! empty( $field['protected'] ) || empty( $field['writable'] ) ) { return array( false, $field['reason'] ?: 'Read-only' ); }
		if ( $id && ! self::can( 'edit', $entity, $id ) ) { return array( false, 'You cannot edit this record' ); }
		if ( $id && 'meta' === $field['storage'] && in_array( $field['origin'], array( 'discovered-meta', 'registered-meta', 'core' ), true ) && 'custom' !== $entity['kind']
			&& ! NCD_Policy::can_write_meta( NCD_Store::meta_type( $entity ), $id, $field['source'] ) ) {
			return array( false, 'WordPress does not allow editing this key for your account' );
		}
		if ( 'post' === $entity['kind'] && 'post_field' === $field['storage'] && 'post_author' === $field['source'] ) {
			$pto = get_post_type_object( $entity['object_type'] );
			if ( $pto && ! current_user_can( $pto->cap->edit_others_posts ) ) { return array( false, 'Changing the author needs permission to edit others\' items' ); }
		}
		return array( true, '' );
	}

	/** Field that controls publication for this entity, with the values that mean "public". */
	public static function publish_config( array $entity ) {
		$cfg = array_merge( array( 'field' => 'post' === $entity['kind'] ? 'post_status' : '', 'values' => array( 'publish', 'future' ) ), $entity['publish'] );
		return isset( $entity['fields'][ $cfg['field'] ] ) ? $cfg : array( 'field' => '', 'values' => array() );
	}

	/* ------------------------------------------------------------ reading */

	public static function entity( $provider, $entity ) {
		$e = NCD_Registry::entity( $provider, $entity );
		return $e ?: new WP_Error( 'ncd_unknown_entity', 'Unknown data type ' . $provider . '.' . $entity . '.', array( 'status' => 404 ) );
	}

	public static function list_records( array $entity, array $args = array() ) {
		if ( ! self::can( 'read', $entity ) ) { return new WP_Error( 'ncd_forbidden', 'You cannot view this data type.', array( 'status' => 403 ) ); }
		$res     = NCD_Store::query( $entity, $args );
		$columns = $entity['columns'] ?: array_slice( array_keys( $entity['fields'] ), 0, 4 );
		$all     = ! empty( $args['all_fields'] );
		$rows    = array();
		foreach ( $res['ids'] as $id ) {
			if ( 'post' === $entity['kind'] && ! self::can( 'read', $entity, $id ) ) { continue; }
			$values = $all ? NCD_Store::read( $entity, $id ) : array();
			if ( ! $all ) {
				foreach ( $columns as $c ) { if ( isset( $entity['fields'][ $c ] ) ) { $values[ $c ] = NCD_Policy::redact( $entity['fields'][ $c ], NCD_Store::read_field( $entity, $entity['fields'][ $c ], $id ) ); } }
			}
			$rows[] = array( 'summary' => NCD_Store::summary( $entity, $id ), 'values' => $values );
		}
		return array( 'rows' => $rows, 'total' => $res['total'], 'page' => max( 1, (int) ( $args['page'] ?? 1 ) ), 'per_page' => max( 1, min( 200, (int) ( $args['per_page'] ?? 25 ) ) ), 'columns' => $columns );
	}

	public static function get_record( array $entity, $id ) {
		$id = (int) $id;
		if ( ! NCD_Store::exists( $entity, $id ) ) { return new WP_Error( 'ncd_not_found', 'Record #' . $id . ' does not exist for this data type.', array( 'status' => 404 ) ); }
		if ( ! self::can( 'read', $entity, $id ) ) { return new WP_Error( 'ncd_forbidden', 'You cannot view this record.', array( 'status' => 403 ) ); }
		$values = NCD_Store::read( $entity, $id );
		$access = array();
		foreach ( $entity['fields'] as $k => $f ) {
			list( $ok, $why ) = self::field_access( $entity, $f, $id );
			$access[ $k ] = array( 'writable' => $ok, 'reason' => $why );
		}
		return array(
			'summary'     => NCD_Store::summary( $entity, $id ),
			'values'      => $values,
			'access'      => $access,
			'revision'    => self::fingerprint( $values ),
			'can_publish' => self::can( 'publish', $entity, $id ),
			'can_trash'   => $entity['allow_trash'] && self::can( 'trash', $entity, $id ),
		);
	}

	public static function fingerprint( array $values ) { return NCD_Types::hash( $values ); }

	/* ------------------------------------------------------------ validation */

	/**
	 * Validate proposed changes for one record (or a new record when $id is 0).
	 *
	 * @return array{clean:array,errors:array,unchanged:array,current:array}
	 */
	public static function validate( array $entity, $id, array $changes, array $context = array() ) {
		$id      = (int) $id;
		$clean   = array();
		$errors  = array();
		$current = $id ? NCD_Store::read( $entity, $id ) : array();
		$unchanged = array();
		$publish = self::publish_config( $entity );

		foreach ( $changes as $key => $raw ) {
			$key = (string) $key;
			if ( ! isset( $entity['fields'][ $key ] ) ) { $errors[ $key ] = 'Unknown field for this data type'; continue; }
			$field = $entity['fields'][ $key ];
			list( $ok, $why ) = $id ? self::field_access( $entity, $field, $id ) : array( empty( $field['protected'] ) && ! empty( $field['writable'] ), $field['reason'] ?: 'Read-only' );
			if ( ! $ok ) {
				if ( $id && NCD_Types::same( $raw, $current[ $key ] ?? null ) ) { $unchanged[] = $key; continue; } // re-sent unchanged read-only value: harmless
				$errors[ $key ] = $why;
				continue;
			}
			list( $value, $err ) = NCD_Types::sanitize( $field, $raw );
			if ( '' !== $err ) { $errors[ $key ] = $field['label'] . ' ' . $err; continue; }
			if ( $field['validate'] ) {
				$msg = call_user_func( $field['validate'], $value, $field, $id, $context );
				if ( is_string( $msg ) && '' !== $msg ) { $errors[ $key ] = $msg; continue; }
				if ( is_wp_error( $msg ) ) { $errors[ $key ] = $msg->get_error_message(); continue; }
			}
			if ( $field['required'] && ( '' === $value || array() === $value || null === $value || 0 === $value ) ) { $errors[ $key ] = $field['label'] . ' is required'; continue; }
			if ( $id && array_key_exists( $key, $current ) && NCD_Types::same( $value, $current[ $key ] ) ) { $unchanged[] = $key; continue; }
			$clean[ $key ] = $value;
		}

		// Publishing / status control is never implicit.
		if ( $publish['field'] && array_key_exists( $publish['field'], $clean ) ) {
			$new = (string) $clean[ $publish['field'] ];
			if ( empty( $context['allow_status'] ) ) {
				$errors[ $publish['field'] ] = 'Status changes (publish, unpublish, schedule) need the explicit "Allow status changes" permission';
				unset( $clean[ $publish['field'] ] );
			} elseif ( in_array( $new, (array) $publish['values'], true ) && ! self::can( 'publish', $entity, $id ) ) {
				$errors[ $publish['field'] ] = 'You do not have permission to publish this item';
				unset( $clean[ $publish['field'] ] );
			} elseif ( ! empty( $entity['statuses'] ) && 'post' === $entity['kind'] && ! in_array( $new, $entity['statuses'], true ) ) {
				$errors[ $publish['field'] ] = 'Unsupported status "' . $new . '"';
				unset( $clean[ $publish['field'] ] );
			}
		}

		if ( isset( $entity['callbacks']['validate'] ) && ( $clean || ! $id ) ) {
			$extra = call_user_func( $entity['callbacks']['validate'], $id, $clean, $context );
			foreach ( (array) $extra as $k => $msg ) {
				if ( '' === (string) $msg ) { continue; }
				$errors[ $k ] = (string) $msg;
				unset( $clean[ $k ] );
			}
		}
		return array( 'clean' => $clean, 'errors' => $errors, 'unchanged' => $unchanged, 'current' => $current );
	}

	/* ------------------------------------------------------------ writing */

	private static function lock( array $entity, $id ) {
		$name = 'ncd_lock_' . md5( $entity['provider'] . '.' . $entity['id'] . '.' . (int) $id );
		if ( add_option( $name, time(), '', false ) ) { return $name; }
		if ( (int) get_option( $name ) < time() - 60 ) { update_option( $name, time(), false ); return $name; } // stale lock
		return false;
	}

	private static function unlock( $name ) { if ( $name ) { delete_option( $name ); } }

	/**
	 * Write validated values for one record via the provider (or built-in storage), with rollback.
	 *
	 * @return array{ok:bool,changed:array,errors:array,warnings:array}
	 */
	public static function apply_values( array $entity, $id, array $clean, array $before, array $context ) {
		$result = array( 'ok' => true, 'changed' => array(), 'errors' => array(), 'warnings' => array() );
		if ( ! $clean ) { return $result; }
		$work = static function () use ( $entity, $id, $clean, $context, &$result ) {
			if ( isset( $entity['callbacks']['write'] ) ) {
				$r = call_user_func( $entity['callbacks']['write'], (int) $id, $clean, $context );
				if ( is_wp_error( $r ) ) { $result['ok'] = false; $result['errors']['_record'] = $r->get_error_message(); return false; }
				if ( is_array( $r ) ) {
					$result['warnings'] = array_values( (array) ( $r['warnings'] ?? array() ) );
					foreach ( (array) ( $r['errors'] ?? array() ) as $k => $m ) { $result['errors'][ $k ] = (string) $m; }
					if ( $result['errors'] ) { $result['ok'] = false; }
				}
				$result['changed'] = array_values( array_diff( array_keys( $clean ), array_keys( $result['errors'] ) ) );
				return $result['ok'];
			}
			foreach ( $clean as $k => $v ) {
				$r = NCD_Store::write_field( $entity, $entity['fields'][ $k ], $id, $v, $context );
				if ( is_wp_error( $r ) ) { $result['ok'] = false; $result['errors'][ $k ] = $r->get_error_message(); return false; }
				$result['changed'][] = $k;
			}
			return true;
		};
		if ( isset( $entity['callbacks']['transaction'] ) ) {
			call_user_func( $entity['callbacks']['transaction'], $work );
		} else {
			$work();
			if ( ! $result['ok'] && $result['changed'] && empty( $entity['callbacks']['write'] ) ) {
				// Compensating rollback of the fields already written in this record.
				foreach ( $result['changed'] as $k ) { NCD_Store::write_field( $entity, $entity['fields'][ $k ], $id, $before[ $k ] ?? '', array( 'source' => 'rollback' ) ); }
				$result['warnings'][] = 'The change was rolled back because ' . implode( '; ', $result['errors'] );
				$result['changed'] = array();
			}
		}
		if ( 'post' === $entity['kind'] && $result['changed'] ) { clean_post_cache( (int) $id ); }
		return $result;
	}

	/**
	 * Save changes to one existing record.
	 *
	 * @param array $context source, allow_status, expected_revision.
	 */
	public static function save( array $entity, $id, array $changes, array $context = array() ) {
		$id = (int) $id;
		if ( ! NCD_Store::exists( $entity, $id ) ) { return new WP_Error( 'ncd_not_found', 'Record #' . $id . ' does not exist for this data type.', array( 'status' => 404 ) ); }
		if ( ! self::can( 'edit', $entity, $id ) ) { return new WP_Error( 'ncd_forbidden', 'You cannot edit this record.', array( 'status' => 403 ) ); }
		$lock = self::lock( $entity, $id );
		if ( ! $lock ) { return new WP_Error( 'ncd_locked', 'This record is being saved by another operation. Try again in a moment.', array( 'status' => 409 ) ); }
		try {
			$v = self::validate( $entity, $id, $changes, $context );
			if ( ! empty( $context['expected_revision'] ) && $context['expected_revision'] !== self::fingerprint( $v['current'] ) ) {
				return new WP_Error( 'ncd_conflict', 'This record changed since you opened it. Reload to see the latest values.', array( 'status' => 409 ) );
			}
			if ( $v['errors'] && empty( $context['partial'] ) ) {
				return array( 'ok' => false, 'id' => $id, 'changed' => array(), 'errors' => $v['errors'], 'warnings' => array(), 'history_id' => 0 );
			}
			$before = array_intersect_key( $v['current'], $v['clean'] );
			$r      = self::apply_values( $entity, $id, $v['clean'], $before, $context );
			$after  = $r['changed'] ? array_intersect_key( NCD_Store::read( $entity, $id ), array_flip( $r['changed'] ) ) : array();
			$hid    = 0;
			if ( $r['changed'] && empty( $context['no_history'] ) ) {
				$hid = NCD_History::add( $entity['provider'], $entity['id'], 'edit', $context['source'] ?? 'editor', sprintf( 'Edited %s (%s)', NCD_Store::summary( $entity, $id )['label'], implode( ', ', $r['changed'] ) ),
					array( array( 'id' => $id, 'action' => 'edit', 'before' => array_intersect_key( $before, $after ), 'after' => $after ) ) );
			}
			do_action( 'ninecode_data_record_saved', $entity, $id, $r['changed'], $context );
			return array( 'ok' => $r['ok'] && ! $v['errors'], 'id' => $id, 'changed' => $r['changed'], 'errors' => $v['errors'] + $r['errors'], 'warnings' => $r['warnings'], 'history_id' => $hid, 'before' => $before, 'after' => $after );
		} finally {
			self::unlock( $lock );
		}
	}

	/** Create a record (explicit permission required in $context['allow_create']). */
	public static function create( array $entity, array $values, array $context = array() ) {
		if ( empty( $context['allow_create'] ) ) { return new WP_Error( 'ncd_create_permission', 'Creating records needs the explicit "Allow creating records" permission.', array( 'status' => 400 ) ); }
		if ( ! $entity['allow_create'] || ! self::can( 'create', $entity ) ) { return new WP_Error( 'ncd_forbidden', 'You cannot create records of this type.', array( 'status' => 403 ) ); }
		$v = self::validate( $entity, 0, $values, $context );
		if ( $v['errors'] ) { return array( 'ok' => false, 'id' => 0, 'changed' => array(), 'errors' => $v['errors'], 'warnings' => array(), 'history_id' => 0 ); }
		$id = NCD_Store::create( $entity, $v['clean'] );
		if ( is_wp_error( $id ) ) { return $id; }
		$id   = (int) $id;
		$rest = $v['clean'];
		$r    = self::apply_values( $entity, $id, $rest, array(), $context );
		$hid  = empty( $context['no_history'] ) ? NCD_History::add( $entity['provider'], $entity['id'], 'create', $context['source'] ?? 'editor', 'Created ' . NCD_Store::summary( $entity, $id )['label'],
			array( array( 'id' => $id, 'action' => 'create', 'before' => array(), 'after' => array_intersect_key( NCD_Store::read( $entity, $id ), $rest ) ) ) ) : 0;
		do_action( 'ninecode_data_record_created', $entity, $id, $context );
		return array( 'ok' => $r['ok'], 'id' => $id, 'changed' => $r['changed'], 'errors' => $r['errors'], 'warnings' => $r['warnings'], 'history_id' => $hid );
	}

	/** Move a record to the trash (explicit permission required in $context['allow_trash']). */
	public static function trash( array $entity, $id, array $context = array() ) {
		$id = (int) $id;
		if ( empty( $context['allow_trash'] ) ) { return new WP_Error( 'ncd_trash_permission', 'Trashing records needs the explicit "Allow moving records to the trash" permission.', array( 'status' => 400 ) ); }
		if ( ! $entity['allow_trash'] || ! NCD_Store::exists( $entity, $id ) || ! self::can( 'trash', $entity, $id ) ) { return new WP_Error( 'ncd_forbidden', 'You cannot trash this record.', array( 'status' => 403 ) ); }
		$summary = NCD_Store::summary( $entity, $id );
		$r = NCD_Store::trash( $entity, $id );
		if ( is_wp_error( $r ) ) { return $r; }
		$hid = empty( $context['no_history'] ) ? NCD_History::add( $entity['provider'], $entity['id'], 'trash', $context['source'] ?? 'editor', 'Trashed ' . $summary['label'], array( array( 'id' => $id, 'action' => 'trash', 'before' => array( '_status' => $summary['status'] ), 'after' => array() ) ) ) : 0;
		return array( 'ok' => true, 'id' => $id, 'history_id' => $hid );
	}

	/**
	 * Apply the same changes to many records (validated per record). One history entry.
	 *
	 * @return array{ok:bool,results:array,history_id:int,changed_records:int}
	 */
	public static function bulk( array $entity, array $ids, array $changes, array $context = array() ) {
		$results = array();
		$records = array();
		$context = array_merge( $context, array( 'no_history' => true ) );
		foreach ( array_slice( array_unique( array_map( 'intval', $ids ) ), 0, 500 ) as $id ) {
			$r = self::save( $entity, $id, $changes, $context );
			if ( is_wp_error( $r ) ) { $results[] = array( 'id' => $id, 'ok' => false, 'errors' => array( '_record' => $r->get_error_message() ) ); continue; }
			$results[] = array( 'id' => $id, 'ok' => $r['ok'], 'changed' => $r['changed'], 'errors' => $r['errors'], 'warnings' => $r['warnings'] );
			if ( $r['changed'] ) { $records[] = array( 'id' => $id, 'action' => 'edit', 'before' => array_intersect_key( $r['before'], $r['after'] ), 'after' => $r['after'] ); }
		}
		$hid = $records ? NCD_History::add( $entity['provider'], $entity['id'], 'bulk', $context['source'] ?? 'bulk', sprintf( 'Bulk edit of %d record(s): %s', count( $records ), implode( ', ', array_keys( $changes ) ) ), $records ) : 0;
		return array( 'ok' => ! array_filter( $results, static function ( $r ) { return ! $r['ok']; } ), 'results' => $results, 'history_id' => $hid, 'changed_records' => count( $records ) );
	}

	/* ------------------------------------------------------------ undo */

	/**
	 * Undo a history entry. Fields changed again since then are reported as conflicts and skipped
	 * unless $force is true.
	 */
	public static function undo( $history_id, $force = false ) {
		$h = NCD_History::get( $history_id );
		if ( ! $h ) { return new WP_Error( 'ncd_history', 'History entry not found.', array( 'status' => 404 ) ); }
		if ( 'undone' === $h['status'] ) { return new WP_Error( 'ncd_history', 'This change was already undone.', array( 'status' => 409 ) ); }
		if ( (int) $h['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'ncd_forbidden', 'Only the person who made this change or an administrator can undo it.', array( 'status' => 403 ) ); }
		$entity = self::entity( $h['provider'], $h['entity'] );
		if ( is_wp_error( $entity ) ) { return $entity; }
		$report = array( 'restored' => array(), 'conflicts' => array(), 'errors' => array() );
		$records = array();
		$ctx = array( 'source' => 'undo', 'allow_status' => true, 'no_history' => true );
		foreach ( array_reverse( $h['records'] ) as $rec ) {
			$id = (int) $rec['id'];
			if ( 'create' === $rec['action'] ) {
				$r = NCD_Store::exists( $entity, $id ) ? NCD_Store::trash( $entity, $id ) : true;
				if ( is_wp_error( $r ) ) { $report['errors'][ $id ] = $r->get_error_message(); } else { $report['restored'][] = $id; $records[] = array( 'id' => $id, 'action' => 'trash', 'before' => array(), 'after' => array() ); }
				continue;
			}
			if ( 'trash' === $rec['action'] ) {
				$r = NCD_Store::untrash( $entity, $id );
				if ( ! is_wp_error( $r ) && 'post' === $entity['kind'] && ! empty( $rec['before']['_status'] ) ) { wp_update_post( array( 'ID' => $id, 'post_status' => sanitize_key( $rec['before']['_status'] ) ) ); }
				if ( is_wp_error( $r ) ) { $report['errors'][ $id ] = $r->get_error_message(); } else { $report['restored'][] = $id; }
				continue;
			}
			if ( ! NCD_Store::exists( $entity, $id ) ) { $report['errors'][ $id ] = 'Record no longer exists'; continue; }
			$current = NCD_Store::read( $entity, $id );
			$restore = array();
			foreach ( (array) $rec['before'] as $k => $old ) {
				if ( ! isset( $entity['fields'][ $k ] ) ) { continue; }
				if ( ! $force && array_key_exists( $k, (array) $rec['after'] ) && ! NCD_Types::same( $current[ $k ] ?? null, $rec['after'][ $k ] ) ) { $report['conflicts'][] = array( 'id' => $id, 'field' => $k ); continue; }
				$restore[ $k ] = $old;
			}
			if ( ! $restore ) { continue; }
			foreach ( array_keys( $restore ) as $k ) {
				list( $ok, $why ) = self::field_access( $entity, $entity['fields'][ $k ], $id );
				if ( ! $ok ) { $report['errors'][ $id . ':' . $k ] = $why; unset( $restore[ $k ] ); }
			}
			$r = self::apply_values( $entity, $id, $restore, array_intersect_key( $current, $restore ), $ctx );
			if ( $r['changed'] ) {
				$report['restored'][] = $id;
				$records[] = array( 'id' => $id, 'action' => 'edit', 'before' => array_intersect_key( $current, array_flip( $r['changed'] ) ), 'after' => array_intersect_key( $restore, array_flip( $r['changed'] ) ) );
			}
			foreach ( $r['errors'] as $k => $m ) { $report['errors'][ $id . ':' . $k ] = $m; }
		}
		$status = ( $report['conflicts'] || $report['errors'] ) ? 'partially-undone' : 'undone';
		NCD_History::set_status( $history_id, $status );
		$report['history_id'] = NCD_History::add( $h['provider'], $h['entity'], 'undo', 'undo', 'Undo of change #' . (int) $history_id, $records, (int) $history_id );
		$report['status'] = $status;
		return $report;
	}

	/* ------------------------------------------------------------ discovery report */

	public static function discovery_report() {
		$out = array();
		foreach ( NCD_Registry::providers() as $p ) {
			$entities = array();
			foreach ( $p['entities'] as $e ) {
				if ( ! self::can( 'read', $e ) ) { continue; }
				$counts = array( 'fields' => count( $e['fields'] ), 'writable' => 0, 'protected' => 0, 'secret' => 0, 'acf' => 0, 'discovered' => 0 );
				foreach ( $e['fields'] as $f ) {
					if ( $f['writable'] && empty( $f['protected'] ) ) { $counts['writable']++; } else { $counts['protected']++; }
					if ( $f['secret'] ) { $counts['secret']++; }
					if ( 'acf' === $f['origin'] ) { $counts['acf']++; }
					if ( 'discovered-meta' === $f['origin'] ) { $counts['discovered']++; }
				}
				$entities[] = array( 'id' => $e['id'], 'label' => $e['label'], 'kind' => $e['kind'], 'object_type' => $e['object_type'], 'counts' => $counts, 'notes' => $e['notes'] );
			}
			if ( $entities ) {
				$out[] = array( 'id' => $p['id'], 'label' => $p['label'], 'version' => $p['version'], 'contract' => $p['contract'], 'adapter' => $p['adapter'], 'description' => $p['description'], 'entities' => $entities );
			}
		}
		return array( 'providers' => $out, 'contract' => NCD_Registry::CONTRACT, 'errors' => current_user_can( 'manage_options' ) ? NCD_Registry::errors() : array() );
	}
}
