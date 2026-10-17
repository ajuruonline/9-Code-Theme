<?php
/**
 * Audit history and recovery snapshots.
 *
 * Every mutation made through Data Manager (editor save, bulk edit, import, undo, REST) records the
 * before/after values of exactly the fields it touched, so it can be reviewed and undone.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_History {
	const DB_VERSION = '1';
	const KEEP       = 1000;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ncd_history';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			provider varchar(64) NOT NULL DEFAULT '',
			entity varchar(64) NOT NULL DEFAULT '',
			operation varchar(20) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT '',
			summary text NOT NULL,
			records longtext NOT NULL,
			record_count int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'applied',
			undo_of bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY provider_entity (provider, entity)
		) {$charset};" );
		update_option( 'ncd_history_db_version', self::DB_VERSION, false );
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== get_option( 'ncd_history_db_version' ) ) { self::install(); }
	}

	/**
	 * @param array $records list of array( id, action (edit|create|trash), before (field=>value), after (field=>value) ).
	 * @return int history id
	 */
	public static function add( $provider, $entity, $operation, $source, $summary, array $records, $undo_of = 0, $status = 'applied' ) {
		global $wpdb;
		self::maybe_install();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned audit table.
		$wpdb->insert( self::table(), array(
			'created_at'   => current_time( 'mysql', true ),
			'user_id'      => get_current_user_id(),
			'provider'     => substr( (string) $provider, 0, 64 ),
			'entity'       => substr( (string) $entity, 0, 64 ),
			'operation'    => substr( (string) $operation, 0, 20 ),
			'source'       => substr( (string) $source, 0, 20 ),
			'summary'      => (string) $summary,
			'records'      => (string) wp_json_encode( array_values( $records ) ),
			'record_count' => count( $records ),
			'status'       => $status,
			'undo_of'      => (int) $undo_of,
		) );
		$id = (int) $wpdb->insert_id;
		self::prune();
		do_action( 'ninecode_data_history_added', $id, $provider, $entity, $operation, $records );
		return $id;
	}

	private static function prune() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned audit table.
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
		if ( $count > self::KEEP + 50 ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i ORDER BY id ASC LIMIT %d', self::table(), $count - self::KEEP ) );
		}
		// phpcs:enable
	}

	public static function get( $id ) {
		global $wpdb;
		self::maybe_install();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned audit table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), (int) $id ), ARRAY_A );
		if ( ! $row ) { return null; }
		$row['records'] = json_decode( (string) $row['records'], true ) ?: array();
		return $row;
	}

	public static function recent( array $args = array() ) {
		global $wpdb;
		self::maybe_install();
		$limit  = max( 1, min( 200, (int) ( $args['per_page'] ?? 50 ) ) );
		$offset = max( 0, ( (int) ( $args['page'] ?? 1 ) - 1 ) * $limit );
		$where  = '1=1';
		$params = array( self::table() );
		if ( ! empty( $args['provider'] ) ) { $where .= ' AND provider = %s'; $params[] = sanitize_key( $args['provider'] ); }
		if ( ! empty( $args['entity'] ) ) { $where .= ' AND entity = %s'; $params[] = sanitize_key( $args['entity'] ); }
		if ( empty( $args['all_users'] ) ) { $where .= ' AND user_id = %d'; $params[] = get_current_user_id(); }
		$params[] = $limit;
		$params[] = $offset;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $where holds fixed fragments only.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at, user_id, provider, entity, operation, source, summary, record_count, status, undo_of FROM %i WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $params ), ARRAY_A );
		foreach ( (array) $rows as &$r ) {
			$u = get_userdata( (int) $r['user_id'] );
			$r['user'] = $u ? $u->display_name : '#' . $r['user_id'];
		}
		return (array) $rows;
	}

	/** Append records to an existing entry (used by batched imports so one import = one undo). */
	public static function append( $id, array $records, $summary = '' ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row || (int) $row['user_id'] !== get_current_user_id() ) { return false; }
		$all = array_merge( $row['records'], array_values( $records ) );
		$data = array( 'records' => (string) wp_json_encode( $all ), 'record_count' => count( $all ) );
		if ( '' !== $summary ) { $data['summary'] = $summary; }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned audit table.
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
		return true;
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned audit table.
		$wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => (int) $id ) );
	}
}
