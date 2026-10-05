<?php
require __DIR__ . '/lib.php';
ncd_suite( 'records' );
ncd_as( 'admin' );
ncd_reboot();
$e  = ncd_entity( 'wordpress', 'type-post' );
$id = ncd_post( array( 'post_title' => 'Original', 'post_content' => '<p>Body</p>' ) );

$rec = NCD_Service::get_record( $e, $id );
ncd_t( ! is_wp_error( $rec ) && 'Original' === $rec['values']['post_title'], 'get_record reads values' );
ncd_t( $rec['access']['post_title']['writable'] && ! $rec['access']['meta:_edit_lock']['writable'] ?? true, 'access map marks writable vs protected' );
ncd_t( 12 === strlen( $rec['revision'] ), 'revision fingerprint present' );

// Validation is all-or-nothing for editor saves.
$r = NCD_Service::save( $e, $id, array( 'post_title' => 'New', 'meta:not_a_field' => 'x' ) );
ncd_t( ! $r['ok'] && isset( $r['errors']['meta:not_a_field'] ), 'unknown field rejected (mass assignment guard)' );
ncd_eq( ncd_fresh( $id )->post_title, 'Original', 'nothing written when any field is invalid' );

$r = NCD_Service::save( $e, $id, array( 'post_title' => 'New title' ) );
ncd_t( $r['ok'] && array( 'post_title' ) === $r['changed'] && $r['history_id'] > 0, 'valid save writes + records history' );
ncd_eq( ncd_fresh( $id )->post_title, 'New title', 'title stored' );

// Publishing gate.
$r = NCD_Service::save( $e, $id, array( 'post_status' => 'publish' ) );
ncd_t( ! $r['ok'] && isset( $r['errors']['post_status'] ), 'status change refused without allow_status' );
ncd_eq( ncd_fresh( $id )->post_status, 'draft', 'still draft' );
$r = NCD_Service::save( $e, $id, array( 'post_status' => 'publish' ), array( 'allow_status' => true ) );
ncd_t( $r['ok'], 'status change allowed with explicit allow_status' );
ncd_eq( ncd_fresh( $id )->post_status, 'publish', 'published' );
$r = NCD_Service::save( $e, $id, array( 'post_status' => 'not-a-status' ), array( 'allow_status' => true ) );
ncd_t( ! $r['ok'], 'unknown status rejected' );

// Conflict detection via expected_revision.
$rec = NCD_Service::get_record( $e, $id );
wp_update_post( array( 'ID' => $id, 'post_title' => 'Changed elsewhere' ) );
$r = NCD_Service::save( $e, $id, array( 'post_excerpt' => 'x' ), array( 'expected_revision' => $rec['revision'] ) );
ncd_t( is_wp_error( $r ) && 'ncd_conflict' === $r->get_error_code(), 'stale revision is a conflict' );

// Object-type verification: a page id under the post entity.
$page = ncd_post( array( 'post_type' => 'page' ) );
$r = NCD_Service::save( $e, $page, array( 'post_title' => 'hijack' ) );
ncd_t( is_wp_error( $r ) && 'ncd_not_found' === $r->get_error_code(), 'record of another type refused' );

// Sanitization through field types.
$r = NCD_Service::save( $e, $id, array( 'post_title' => '<script>alert(1)</script>Safe', 'comment_status' => 'evil' ) );
ncd_t( ! $r['ok'] && isset( $r['errors']['comment_status'] ), 'select outside its choices rejected' );
$r = NCD_Service::save( $e, $id, array( 'post_title' => '<script>alert(1)</script>Safe' ) );
ncd_t( false === strpos( ncd_fresh( $id )->post_title, '<script' ), 'title stripped of tags' );

// Create / trash need explicit permission.
$r = NCD_Service::create( $e, array( 'post_title' => 'Made' ) );
ncd_t( is_wp_error( $r ) && 'ncd_create_permission' === $r->get_error_code(), 'create refused without allow_create' );
$r = NCD_Service::create( $e, array( 'post_title' => 'Made', 'post_status' => 'publish' ), array( 'allow_create' => true ) );
ncd_t( ! $r['ok'] && isset( $r['errors']['post_status'] ), 'create cannot publish without allow_status' );
$r = NCD_Service::create( $e, array( 'post_title' => 'Made' ), array( 'allow_create' => true ) );
ncd_t( $r['ok'] && $r['id'] && 'draft' === get_post_status( $r['id'] ), 'create with permission makes a draft' );
$made = $r['id']; $made_hid = $r['history_id'];
$r = NCD_Service::trash( $e, $made );
ncd_t( is_wp_error( $r ), 'trash refused without allow_trash' );
$r = NCD_Service::trash( $e, $made, array( 'allow_trash' => true ) );
ncd_t( ! is_wp_error( $r ) && 'trash' === get_post_status( $made ), 'trash with permission' );
$u = NCD_Service::undo( $r['history_id'] );
ncd_t( ! is_wp_error( $u ) && 'draft' === get_post_status( $made ), 'undo trash restores previous status' );
$u = NCD_Service::undo( $made_hid );
ncd_t( ! is_wp_error( $u ) && 'trash' === get_post_status( $made ), 'undo create moves record to trash (never deletes)' );
ncd_t( null !== get_post( $made ), 'undone create still recoverable' );

// Undo of an edit, with conflict protection.
$id2 = ncd_post( array( 'post_title' => 'A' ) );
$s = NCD_Service::save( $e, $id2, array( 'post_title' => 'B', 'post_excerpt' => 'E1' ) );
wp_update_post( array( 'ID' => $id2, 'post_excerpt' => 'E2 (later edit)' ) );
$u = NCD_Service::undo( $s['history_id'] );
ncd_eq( ncd_fresh( $id2 )->post_title, 'A', 'undo restored untouched field' );
ncd_eq( ncd_fresh( $id2 )->post_excerpt, 'E2 (later edit)', 'undo skipped field changed later' );
ncd_t( 1 === count( $u['conflicts'] ) && 'partially-undone' === $u['status'], 'conflict reported' );
$u2 = NCD_Service::undo( $s['history_id'], true );
ncd_eq( ncd_fresh( $id2 )->post_excerpt, '', 'forced undo overwrites' );
$u3 = NCD_Service::undo( $s['history_id'] );
ncd_t( is_wp_error( $u3 ), 'cannot undo twice' );

// Bulk edit: one history entry, per-record validation.
$ids = array( ncd_post(), ncd_post(), $page );
$b = NCD_Service::bulk( $e, $ids, array( 'post_excerpt' => 'Bulk' ) );
ncd_eq( $b['changed_records'], 2, 'bulk changed valid records only' );
ncd_t( ! $b['ok'] && ! $b['results'][2]['ok'], 'bulk reports the wrong-type record' );
$h = NCD_History::get( $b['history_id'] );
ncd_t( 'bulk' === $h['operation'] && 2 === count( $h['records'] ), 'bulk is one history entry' );
NCD_Service::undo( $b['history_id'] );
ncd_eq( ncd_fresh( $ids[0] )->post_excerpt, '', 'bulk undo restores all' );
$b = NCD_Service::bulk( $e, array( $ids[0] ), array( 'post_status' => 'publish' ) );
ncd_t( 0 === $b['changed_records'], 'bulk status change needs allow_status' );

// Term + user entities.
$te = ncd_entity( 'wordpress', 'tax-category' );
$term = wp_insert_term( 'NCD Cat ' . wp_generate_password( 4, false ), 'category' );
$r = NCD_Service::save( $te, $term['term_id'], array( 'description' => 'Described' ) );
ncd_t( $r['ok'] && 'Described' === get_term( $term['term_id'] )->description, 'term field saved' );
$ue = ncd_entity( 'wordpress', 'users' );
$r = NCD_Service::save( $ue, 1, array( 'roles' => 'subscriber' ) );
ncd_t( ! $r['ok'] && user_can( 1, 'manage_options' ), 'user roles cannot be changed' );
$r = NCD_Service::save( $ue, 1, array( 'meta:wp_capabilities' => array( 'subscriber' => true ) ) );
ncd_t( ! $r['ok'] && user_can( 1, 'manage_options' ), 'capabilities meta cannot be written' );
$r = NCD_Service::save( $ue, 1, array( 'user_pass' => 'hacked' ) );
ncd_t( ! $r['ok'], 'user_pass cannot be written' );

// Scope lock: a concurrent save is refused.
add_option( 'ncd_lock_' . md5( 'wordpress.type-post.' . $id2 ), time(), '', false );
$r = NCD_Service::save( $e, $id2, array( 'post_title' => 'locked?' ) );
ncd_t( is_wp_error( $r ) && 'ncd_locked' === $r->get_error_code(), 'record lock prevents concurrent writes' );
delete_option( 'ncd_lock_' . md5( 'wordpress.type-post.' . $id2 ) );

// Listing, search, status filter, paging.
NCD_Service::save( $e, $id, array( 'post_title' => 'Zebracorn unique' ) );
$l = NCD_Service::list_records( $e, array( 'search' => 'Zebracorn', 'per_page' => 5 ) );
ncd_t( ! is_wp_error( $l ) && $l['total'] >= 1, 'search finds record' );
$l = NCD_Service::list_records( $e, array( 'status' => 'trash' ) );
ncd_t( in_array( $made, wp_list_pluck( wp_list_pluck( $l['rows'], 'summary' ), 'id' ), true ), 'status filter includes trash when asked' );
$l = NCD_Service::list_records( $e, array( 'per_page' => 1, 'page' => 2 ) );
ncd_t( 1 === count( $l['rows'] ) && 2 === $l['page'], 'pagination' );

foreach ( array_merge( array( $id, $id2, $page, $made ), $ids ) as $x ) { wp_delete_post( $x, true ); }
wp_delete_term( $term['term_id'], 'category' );
ncd_done();
