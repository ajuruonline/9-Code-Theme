<?php
require __DIR__ . '/lib.php';
ncd_suite( 'security' );
ncd_as( 'admin' );
ncd_reboot();
$e = ncd_entity( 'wordpress', 'type-post' );
$editor = ncd_user( 'ncd_editor', 'editor' );
$author = ncd_user( 'ncd_author', 'author' );
$sub    = ncd_user( 'ncd_sub', 'subscriber' );
$admin_post  = ncd_post( array( 'post_title' => 'Admin post', 'post_status' => 'publish', 'post_author' => 1 ) );
$author_post = ncd_post( array( 'post_title' => 'Author post', 'post_status' => 'draft', 'post_author' => $author->ID ) );

// Access to Data Manager itself.
foreach ( array( $editor, $author, $sub ) as $u ) {
	ncd_as( $u->ID );
	ncd_t( ! NCD_Service::can_use(), "{$u->user_login} has no Data Manager access by default" );
	list( $s ) = ncd_rest( 'GET', '/ninecode-data/v1/providers' );
	ncd_eq( $s, 403, "REST providers forbidden for {$u->user_login}" );
	list( $s ) = ncd_rest( 'GET', '/ninecode/v1/data/schema' );
	ncd_eq( $s, 403, "legacy REST schema forbidden for {$u->user_login}" );
}
wp_set_current_user( 0 );
list( $s ) = ncd_rest( 'GET', '/ninecode-data/v1/providers' );
ncd_eq( $s, 401, 'REST requires login' );
list( $s ) = ncd_rest( 'POST', '/ninecode-data/v1/records/wordpress/type-post/' . $admin_post, array( 'changes' => array( 'post_title' => 'x' ) ) );
ncd_t( in_array( $s, array( 401, 403 ), true ) && 'Admin post' === get_post( $admin_post )->post_title, 'anonymous write refused' );

// Grant the Data Manager capability; WordPress capabilities still decide per record.
$editor->add_cap( 'manage_ninecode_data' );
$author->add_cap( 'manage_ninecode_data' );
ncd_as( $author->ID ); ncd_reboot(); $e = ncd_entity( 'wordpress', 'type-post' );
ncd_t( NCD_Service::can_use(), 'author with capability can open Data Manager' );
$r = NCD_Service::save( $e, $admin_post, array( 'post_title' => 'Hijacked' ) );
ncd_t( is_wp_error( $r ) && 'Admin post' === ncd_fresh( $admin_post )->post_title, 'author cannot edit others\' posts' );
$r = NCD_Service::save( $e, $author_post, array( 'post_title' => 'Mine' ) );
ncd_t( ! is_wp_error( $r ) && $r['ok'], 'author can edit own post' );
$r = NCD_Service::save( $e, $author_post, array( 'post_author' => 1 ) );
ncd_t( ! $r['ok'], 'author cannot reassign authorship' );
$r = NCD_Service::save( $e, $author_post, array( 'post_content' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>' ) );
$c = ncd_fresh( $author_post )->post_content;
ncd_t( false === stripos( $c, '<script' ) && false === stripos( $c, 'onerror' ), 'stored XSS stripped for users without unfiltered_html' );
ncd_t( ! NCD_Service::can( 'read', ncd_entity( 'wordpress', 'users' ) ), 'author cannot list users' );
list( $s ) = ncd_rest( 'GET', '/ninecode-data/v1/records/wordpress/users' );
ncd_eq( $s, 403, 'REST users list forbidden for author' );
list( $s ) = ncd_rest( 'GET', '/ninecode-data/v1/lookup', array( 'type' => 'user', 'search' => 'adm' ) );
ncd_eq( $s, 403, 'user lookup forbidden for author' );
$recs = NCD_Exchange::export_records( $e, array() );
ncd_t( ! in_array( $admin_post, wp_list_pluck( wp_list_pluck( $recs, '_ref' ), 'id' ), true ), 'author export excludes records they cannot edit' );
// Author's own user record: may edit profile fields but never roles/caps.
$ue = ncd_entity( 'wordpress', 'users' );
$r = NCD_Service::save( $ue, $author->ID, array( 'meta:wp_capabilities' => array( 'administrator' => true ) ) );
ncd_t( ! $r['ok'] && ! user_can( $author->ID, 'manage_options' ), 'privilege escalation via capabilities meta blocked' );
$r = NCD_Service::save( $ue, $author->ID, array( 'roles' => 'administrator' ) );
ncd_t( ! $r['ok'] && ! user_can( $author->ID, 'manage_options' ), 'privilege escalation via roles blocked' );
$r = NCD_Service::save( $ue, 1, array( 'user_email' => 'attacker@example.test' ) );
ncd_t( ( is_wp_error( $r ) || ! $r['ok'] ) && 'attacker@example.test' !== get_userdata( 1 )->user_email, 'author cannot edit another user' );

// Publishing needs the WordPress capability too, not only the toggle.
$contrib = ncd_user( 'ncd_contrib', 'contributor' ); $contrib->add_cap( 'manage_ninecode_data' );
$cpost = ncd_post( array( 'post_author' => $contrib->ID, 'post_status' => 'draft' ) );
ncd_as( $contrib->ID ); ncd_reboot(); $e = ncd_entity( 'wordpress', 'type-post' );
$r = NCD_Service::save( $e, $cpost, array( 'post_status' => 'publish' ), array( 'allow_status' => true ) );
ncd_t( ( is_wp_error( $r ) || ! $r['ok'] ) && 'publish' !== get_post_status( $cpost ), 'contributor cannot publish even with allow_status' );

// Structural keys and arbitrary meta.
ncd_as( 'admin' ); ncd_reboot(); $e = ncd_entity( 'wordpress', 'type-post' );
foreach ( array( 'meta:_wp_page_template', 'meta:_edit_lock', 'meta:_thumbnail_id', 'meta:_wp_attached_file', 'meta:anything_new' ) as $k ) {
	$r = NCD_Service::save( $e, $admin_post, array( $k => 'x' ) );
	ncd_t( ! $r['ok'], "admin cannot write {$k} directly" );
}
// Registered meta with a denying auth_callback.
register_post_meta( 'post', '_ncd_owner_only', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => function () { return false; } ) );
update_post_meta( $admin_post, '_ncd_owner_only', 'v' );
ncd_reboot(); $e = ncd_entity( 'wordpress', 'type-post' );
$r = NCD_Service::save( $e, $admin_post, array( 'meta:_ncd_owner_only' => 'changed' ) );
ncd_t( ! $r['ok'] && 'v' === get_post_meta( $admin_post, '_ncd_owner_only', true ), 'owner auth_callback denial is respected' );

// Legacy draft import route never writes.
list( $s, $d ) = ncd_rest( 'POST', '/ninecode/v1/data/import', array(), array( 'format' => 'ninecode-universal-data-package', 'scope' => array( 'post_type' => 'post' ), 'records' => array( array( 'id' => $admin_post, 'post_type' => 'post', 'title' => 'Legacy write', 'meta' => array( 'evil' => 1 ) ) ) ) );
ncd_t( 200 === $s && ! empty( $d['preview_only'] ) && 'Admin post' === ncd_fresh( $admin_post )->post_title && '' === get_post_meta( $admin_post, 'evil', true ), 'legacy import route is preview-only' );

// REST input coercion: id in URL must belong to the entity; unknown entity 404.
$page = ncd_post( array( 'post_type' => 'page' ) );
list( $s ) = ncd_rest( 'POST', '/ninecode-data/v1/records/wordpress/type-post/' . $page, array( 'changes' => array( 'post_title' => 'x' ) ) );
ncd_eq( $s, 404, 'REST rejects id of another object type' );
list( $s ) = ncd_rest( 'GET', '/ninecode-data/v1/entities/wordpress/type-nope' );
ncd_eq( $s, 404, 'unknown entity 404' );
list( $s ) = ncd_rest( 'POST', '/ninecode-data/v1/import/wordpress/type-post/apply', array( 'token' => 'nope', 'selection' => array( 0 => true ) ) );
ncd_t( $s >= 400, 'apply with unknown token refused' );
list( $s ) = ncd_rest( 'POST', '/ninecode-data/v1/import/wordpress/type-post/apply', array( 'token' => 'x', 'selection' => array_fill( 0, 201, true ) ) );
ncd_t( $s >= 400, 'apply batches capped at 200' );

// Undo permissions: only the author of the change or an admin.
$r = NCD_Service::save( $e, $admin_post, array( 'post_excerpt' => 'by admin' ) );
ncd_as( $editor->ID );
$u = NCD_Service::undo( $r['history_id'] );
ncd_t( is_wp_error( $u ) && 'by admin' === ncd_fresh( $admin_post )->post_excerpt, 'editor cannot undo an admin\'s change' );
list( $s ) = ncd_rest( 'GET', '/ninecode-data/v1/history/' . $r['history_id'] );
ncd_eq( $s, 403, 'editor cannot read an admin\'s history entry' );
$hist = NCD_History::recent( array() );
ncd_t( ! in_array( (string) $r['history_id'], wp_list_pluck( $hist, 'id' ), true ), 'history list is scoped to the current user' );

// Staged files are not web-served as data.
ncd_as( 'admin' );
$st = NCD_Exchange::stage( array( 'provider' => 'wordpress', 'entity' => 'type-post', 'records' => array() ) );
$url = wp_upload_dir()['baseurl'] . '/ncd-staging/' . $st['token'] . '.php';
$resp = wp_remote_get( $url, array( 'timeout' => 5 ) );
ncd_t( is_wp_error( $resp ) || false === strpos( wp_remote_retrieve_body( $resp ), 'records' ), 'staged file not readable over HTTP' );
NCD_Exchange::discard_stage( $st['token'] );

$editor->remove_cap( 'manage_ninecode_data' ); $author->remove_cap( 'manage_ninecode_data' ); $contrib->remove_cap( 'manage_ninecode_data' );
foreach ( array( $admin_post, $author_post, $cpost, $page ) as $x ) { wp_delete_post( $x, true ); }
ncd_done();
