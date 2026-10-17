<?php
require __DIR__ . '/lib.php';
ncd_suite( 'providers' );
ncd_as( 'admin' );
ncd_reboot();
if ( ! NCD_Registry::provider( 'fixture-app' ) ) { ncd_t( false, 'fixture provider registered', NCD_Registry::errors() ); ncd_done(); return; }
$p = NCD_Registry::provider( 'fixture-app' );
ncd_t( 'Fixture App' === $p['label'] && '2.0.0' === $p['version'] && 1 === $p['contract'] && isset( $p['migrations']['1.0.0'] ), 'identity, version, contract and migrations recorded' );
ncd_t( ! isset( NCD_Registry::provider( 'wordpress' )['entities']['type-ncdfx_talk'] ), 'provider-claimed post type not rediscovered generically' );
$e = ncd_entity( 'fixture-app', 'talk' );
$t = ncd_post( array( 'post_type' => 'ncdfx_talk', 'post_title' => 'Talk one' ) );

// Provider sanitize + validate callbacks.
$r = NCD_Service::save( $e, $t, array( 'level' => '  INTRO ' ) );
ncd_t( $r['ok'] && 'intro' === get_post_meta( $t, '_ncdfx_level', true ), 'field sanitize_callback applied' );
$r = NCD_Service::save( $e, $t, array( 'level' => 'expert' ) );
ncd_t( ! $r['ok'] && false !== strpos( $r['errors']['level'], 'intro or advanced' ), 'field validate_callback enforced' );
$r = NCD_Service::save( $e, $t, array( 'level' => 'advanced', 'post_title' => 'Hi' ) );
ncd_t( ! $r['ok'] && isset( $r['errors']['post_title'] ) && 'intro' === get_post_meta( $t, '_ncdfx_level', true ), 'entity validate_callback enforced (cross-field)' );

// Callback storage.
$GLOBALS['ncdfx_log'] = array();
$r = NCD_Service::save( $e, $t, array( 'speakers' => array( array( 'name' => 'Ada' ) ) ) );
ncd_t( $r['ok'] && in_array( 'speakers:' . $t, $GLOBALS['ncdfx_log'], true ), 'field write_callback used' );
ncd_eq( NCD_Store::read( $e, $t )['speakers'][0]['name'], 'Ada', 'field read_callback used' );

// Protected field + permission callback.
$r = NCD_Service::save( $e, $t, array( 'slot' => 'Mon 9:00' ) );
ncd_t( ! $r['ok'], 'provider-protected field read-only' );
update_post_meta( $t, '_ncdfx_locked', 1 );
$r = NCD_Service::save( $e, $t, array( 'post_title' => 'Locked edit' ) );
ncd_t( is_wp_error( $r ), 'permission_callback denies edits' );
ncd_t( NCD_Service::can( 'read', $e, $t ), 'permission_callback still allows read' );
delete_post_meta( $t, '_ncdfx_locked' );

// Publish callback owns publication.
$GLOBALS['ncdfx_log'] = array();
$r = NCD_Service::save( $e, $t, array( 'post_status' => 'publish' ), array( 'allow_status' => true ) );
ncd_t( ! $r['ok'] && 'draft' === get_post_status( $t ) && in_array( 'publish:' . $t . ':publish', $GLOBALS['ncdfx_log'], true ), 'publish_callback can refuse publication' );
$r = NCD_Service::save( $e, $t, array( 'room' => 3, 'post_status' => 'publish' ), array( 'allow_status' => true ) );
ncd_t( $r['ok'] && 'publish' === get_post_status( $t ), 'publish_callback publishes after other fields are written', $r );

// Export/import transforms.
$pkg = NCD_Exchange::package( $e, NCD_Exchange::export_records( $e, array( 'ids' => array( $t ) ) ) );
ncd_eq( $pkg['records'][0]['fields']['room'], 'R3', 'export_transform applied' );
$pkg['records'][0]['fields']['room'] = 'R7';
$n = NCD_Exchange::normalize_package( $pkg, $e );
NCD_Exchange::apply( $e, $n, array( 0 => true ), array() );
ncd_eq( (int) get_post_meta( $t, '_ncdfx_room', true ), 7, 'import_transform applied before validation' );

// Custom table entity: create, list, read, write, secret, transaction rollback, trash, undo.
$re = ncd_entity( 'fixture-app', 'room' );
$c = NCD_Service::create( $re, array( 'name' => 'Hall A', 'capacity' => 120 ), array( 'allow_create' => true ) );
ncd_t( $c['ok'] && $c['id'] > 0, 'custom entity create_callback', $c );
$rid = $c['id'];
$l = NCD_Service::list_records( $re, array( 'search' => 'Hall' ) );
ncd_t( 1 === $l['total'] && 'Hall A' === $l['rows'][0]['summary']['label'], 'custom list_callback + label_callback' );
global $wpdb;
$wpdb->update( ncdfx_table(), array( 'secret_code' => '4321' ), array( 'id' => $rid ) );
ncd_eq( NCD_Store::read( $re, $rid )['secret_code'], NCD_Policy::REDACTED, 'custom secret redacted' );
ncd_t( false === strpos( wp_json_encode( NCD_Exchange::export_records( $re, array( 'ids' => array( $rid ) ) ) ), '4321' ), 'custom secret never exported' );
$GLOBALS['ncdfx_log'] = array();
$r = NCD_Service::save( $re, $rid, array( 'name' => 'Hall B', 'capacity' => 900 ) );
ncd_t( ! $r['ok'] && in_array( 'rollback', $GLOBALS['ncdfx_log'], true ), 'transaction rolled back on provider error' );
ncd_eq( NCD_Store::read( $re, $rid )['name'], 'Hall A', 'no partial write after rollback' );
ncd_eq( $r['changed'], array(), 'rolled-back save reports no changed fields' );
$r = NCD_Service::save( $re, $rid, array( 'name' => 'Hall B', 'capacity' => 200 ) );
ncd_t( $r['ok'] && in_array( 'commit', $GLOBALS['ncdfx_log'], true ) && 'Hall B' === NCD_Store::read( $re, $rid )['name'], 'transaction committed on success' );
NCD_Service::undo( $r['history_id'] );
ncd_eq( NCD_Store::read( $re, $rid )['name'], 'Hall A', 'custom entity undo through write_callback' );
$tr = NCD_Service::trash( $re, $rid, array( 'allow_trash' => true ) );
ncd_t( ! is_wp_error( $tr ) && null === NCD_Store::read( $re, $rid ) || ! NCD_Store::exists( $re, $rid ), 'custom trash_callback' );
$ed = ncd_user( 'ncd_editor', 'editor' ); $ed->add_cap( 'manage_ninecode_data' );
ncd_as( $ed->ID );
ncd_t( ! NCD_Service::can( 'read', $re ), 'custom permission_callback restricts non-admins' );
$ed->remove_cap( 'manage_ninecode_data' );
ncd_as( 'admin' );

// REST surface for a provider entity.
list( $s, $d ) = ncd_rest( 'GET', '/ninecode-data/v1/entities/fixture-app/talk' );
ncd_t( 200 === $s && ! isset( $d['fields']['speakers']['read'] ) && isset( $d['fields']['slot'] ) && $d['fields']['slot']['protected'], 'entity description is JSON-safe and marks protected fields' );

wp_delete_post( $t, true );
$wpdb->query( 'DELETE FROM ' . ncdfx_table() ); // phpcs:ignore
ncd_done();
