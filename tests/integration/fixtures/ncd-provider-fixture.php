<?php
/**
 * Test-only provider exercising the full contract: post entity with provider-owned validation,
 * sanitization, permission, export/import transforms, publish callback, and a custom-table entity
 * with list/read/write/create/trash/transaction callbacks.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ncdfx_table() { global $wpdb; return $wpdb->prefix . 'ncdfx_rooms'; }
function ncdfx_install() {
	global $wpdb;
	$wpdb->query( 'CREATE TABLE IF NOT EXISTS ' . ncdfx_table() . ' (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(190) NOT NULL DEFAULT \'\', capacity int(11) NOT NULL DEFAULT 0, secret_code varchar(64) NOT NULL DEFAULT \'\', trashed tinyint(1) NOT NULL DEFAULT 0, PRIMARY KEY  (id))' ); // phpcs:ignore
}
add_action( 'init', function () {
	register_post_type( 'ncdfx_talk', array( 'label' => 'Talks', 'show_ui' => true, 'supports' => array( 'title' ) ) );
	ncdfx_install();
} );

$GLOBALS['ncdfx_log'] = array();
add_action( 'ninecode_data_register_providers', function () {
	ninecode_data_register_provider( 'fixture-app', array(
		'label'   => 'Fixture App',
		'version' => '2.0.0',
		'owner'   => 'fixture-app',
		'migrations' => array( '1.0.0' => 'Initial contract' ),
		'entities' => array(
			'talk' => array(
				'label' => 'Talks', 'singular' => 'Talk', 'kind' => 'post', 'object_type' => 'ncdfx_talk',
				'statuses' => array( 'draft', 'publish' ),
				'publish' => array( 'field' => 'post_status', 'values' => array( 'publish' ) ),
				'publish_callback' => function ( $id, $status, $context ) {
					$GLOBALS['ncdfx_log'][] = 'publish:' . $id . ':' . $status;
					if ( 'publish' === $status && '' === (string) get_post_meta( $id, '_ncdfx_room', true ) ) { return new WP_Error( 'room', 'A talk needs a room before it can be published' ); }
					wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
					return true;
				},
				'fields' => array(
					'post_title'  => array( 'label' => 'Title', 'type' => 'text', 'storage' => 'post_field', 'required' => true ),
					'post_status' => array( 'label' => 'Status', 'type' => 'status', 'storage' => 'post_field' ),
					'room'        => array( 'label' => 'Room', 'type' => 'integer', 'storage' => 'meta', 'source' => '_ncdfx_room', 'help' => 'Room id' ),
					'level'       => array( 'label' => 'Level', 'type' => 'text', 'storage' => 'meta', 'source' => '_ncdfx_level',
						'sanitize_callback' => function ( $v ) { return strtolower( trim( (string) $v ) ); },
						'validate_callback' => function ( $v ) { return in_array( $v, array( '', 'intro', 'advanced' ), true ) ? '' : 'Level must be intro or advanced'; } ),
					'slot'        => array( 'label' => 'Slot', 'type' => 'text', 'storage' => 'meta', 'source' => '_ncdfx_slot', 'protected' => true, 'reason' => 'Scheduled by the programme builder' ),
					'speakers'    => array( 'label' => 'Speakers', 'type' => 'json', 'storage' => 'callback',
						'read_callback'  => function ( $id ) { return json_decode( (string) get_post_meta( $id, '_ncdfx_speakers', true ), true ) ?: array(); },
						'write_callback' => function ( $id, $v ) { $GLOBALS['ncdfx_log'][] = 'speakers:' . $id; update_post_meta( $id, '_ncdfx_speakers', wp_slash( wp_json_encode( $v ) ) ); return true; } ),
				),
				'validate_callback' => function ( $id, $clean ) {
					return ( isset( $clean['level'] ) && 'advanced' === $clean['level'] && isset( $clean['post_title'] ) && strlen( $clean['post_title'] ) < 5 ) ? array( 'post_title' => 'Advanced talks need a descriptive title' ) : array();
				},
				'permission_callback' => function ( $action, $id ) {
					if ( $id && get_post_meta( $id, '_ncdfx_locked', true ) ) { return 'read' === $action; }
					return current_user_can( 'edit_posts' );
				},
				'export_transform' => function ( $values ) { $values['room'] = $values['room'] ? 'R' . $values['room'] : ''; return $values; },
				'import_transform' => function ( $fields ) { if ( isset( $fields['room'] ) ) { $fields['room'] = (int) ltrim( (string) $fields['room'], 'R' ); } return $fields; },
			),
			'room' => array(
				'label' => 'Rooms', 'singular' => 'Room', 'kind' => 'custom', 'allow_create' => true, 'allow_trash' => true,
				'columns' => array( 'name', 'capacity' ),
				'fields' => array(
					'name'        => array( 'label' => 'Name', 'type' => 'text', 'storage' => 'callback', 'required' => true ),
					'capacity'    => array( 'label' => 'Capacity', 'type' => 'integer', 'storage' => 'callback', 'min' => 0 ),
					'secret_code' => array( 'label' => 'Door code', 'type' => 'text', 'storage' => 'callback', 'secret' => true, 'protected' => true ),
				),
				'list_callback' => function ( $args ) {
					global $wpdb;
					$t = ncdfx_table(); $per = (int) $args['per_page']; $off = ( (int) $args['page'] - 1 ) * $per;
					$where = 'trashed = 0'; if ( ! empty( $args['ids'] ) ) { $where .= ' AND id IN (' . implode( ',', array_map( 'intval', $args['ids'] ) ) . ')'; }
					if ( '' !== (string) ( $args['search'] ?? '' ) ) { $where .= $wpdb->prepare( ' AND name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' ); }
					return array( 'ids' => $wpdb->get_col( "SELECT id FROM {$t} WHERE {$where} ORDER BY id LIMIT {$per} OFFSET {$off}" ), 'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$where}" ) ); // phpcs:ignore
				},
				'read_callback' => function ( $id ) {
					global $wpdb;
					$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ncdfx_table() . ' WHERE id = %d AND trashed = 0', $id ), ARRAY_A ); // phpcs:ignore
					return $row ? array( 'name' => $row['name'], 'capacity' => (int) $row['capacity'], 'secret_code' => $row['secret_code'] ) : null;
				},
				'write_callback' => function ( $id, $values ) {
					global $wpdb;
					foreach ( $values as $k => $v ) {
						if ( 'capacity' === $k && $v > 500 ) { return new WP_Error( 'cap', 'Capacity above 500 needs the venue manager' ); }
						$wpdb->update( ncdfx_table(), array( $k => $v ), array( 'id' => $id ) ); // phpcs:ignore
					}
					return true;
				},
				'create_callback' => function ( $values ) { global $wpdb; $wpdb->insert( ncdfx_table(), array( 'name' => (string) ( $values['name'] ?? 'Room' ) ) ); return (int) $wpdb->insert_id; }, // phpcs:ignore
				'trash_callback'  => function ( $id ) { global $wpdb; $wpdb->update( ncdfx_table(), array( 'trashed' => 1 ), array( 'id' => $id ) ); return true; }, // phpcs:ignore
				'label_callback'  => function ( $id ) { global $wpdb; return array( 'label' => (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . ncdfx_table() . ' WHERE id = %d', $id ) ) ); }, // phpcs:ignore
				'permission_callback' => function () { return current_user_can( 'manage_options' ); },
				'transaction_callback' => function ( callable $work ) {
					global $wpdb;
					$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore
					$ok = $work();
					$wpdb->query( $ok ? 'COMMIT' : 'ROLLBACK' ); // phpcs:ignore
					$GLOBALS['ncdfx_log'][] = $ok ? 'commit' : 'rollback';
					return $ok;
				},
			),
		),
	) );
} );
