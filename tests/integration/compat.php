<?php
require __DIR__ . '/lib.php';
ncd_suite( 'compat' );
ncd_as( 'admin' );
ncd_reboot();
$routes = rest_get_server()->get_routes();
foreach ( array( '/ninecode/v1/data/schema', '/ninecode/v1/data/export/(?P<post_type>[a-zA-Z0-9_-]+)', '/ninecode/v1/data/import', '/ninecode-data/v1/providers', '/ninecode-data/v1/history' ) as $r ) {
	ncd_t( isset( $routes[ $r ] ), "route {$r} registered" );
}
list( $s, $d ) = ncd_rest( 'GET', '/ninecode/v1/data/schema' );
ncd_t( 200 === $s && 'ninecode-universal-data-schema' === $d['format'] && isset( $d['post_types']['post']['fields']['post_title'] ), 'legacy schema shape preserved' );
ncd_t( ! $d['post_types']['post']['fields']['meta:_edit_lock']['writable'] ?? true, 'legacy schema marks protected fields non-writable' );
$id = ncd_post( array( 'post_title' => 'Legacy export' ) );
list( $s, $d ) = ncd_rest( 'GET', '/ninecode/v1/data/export/post' );
ncd_t( 200 === $s && 'ninecode-data-package' === $d['format'] && $d['records'], 'legacy export route returns a v2 package' );
list( $s ) = ncd_rest( 'GET', '/ninecode/v1/data/export/nope' );
ncd_eq( $s, 404, 'legacy export of unknown type 404' );
$schema = apply_filters( 'ninecode_data_manager_schema', array() );
ncd_t( isset( $schema['providers']['wordpress'] ), 'ninecode_data_manager_schema filter still served' );
ncd_t( has_action( 'admin_post_ninecode_universal_export' ) && has_action( 'admin_post_ninecode_universal_import' ), 'legacy admin-post actions kept' );
ncd_t( class_exists( 'NineCode_Universal_Data_Manager' ) && method_exists( 'NineCode_Universal_Data_Manager', 'instance' ), 'legacy class kept' );
// Original Data Manager modules and options untouched.
ncd_t( class_exists( 'NineCode_ACF_Data_Engine' ), 'ACF Data Engine module still loads' );
ncd_t( false !== get_option( 'nine55_ultron_data_version', false ), 'plugin version option name preserved' );
ncd_t( get_role( 'administrator' )->has_cap( 'manage_ninecode_data' ) || current_user_can( 'manage_options' ), 'administrator can use Data Manager' );
// History table installed via upgrade routine, not only activation.
global $wpdb;
ncd_t( NCD_History::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', NCD_History::table() ) ), 'history table exists' );
delete_option( 'ncd_history_db_version' );
NCD_History::maybe_install();
ncd_eq( get_option( 'ncd_history_db_version' ), NCD_History::DB_VERSION, 'history schema migration is idempotent' );
// Public helper API.
ncd_t( function_exists( 'ninecode_data_register_provider' ) && function_exists( 'ninecode_data_contract_version' ), 'public helper functions available' );
wp_delete_post( $id, true );
ncd_done();
