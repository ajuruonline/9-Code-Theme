<?php
require __DIR__ . '/lib.php';
ncd_suite( 'discovery' );
ncd_as( 'admin' );

register_post_type( 'ncd_book', array( 'label' => 'Books', 'show_ui' => true, 'supports' => array( 'title', 'editor', 'custom-fields' ) ) );
register_taxonomy( 'ncd_genre', 'ncd_book', array( 'label' => 'Genres', 'show_ui' => true ) );
register_post_type( 'ncd_hidden', array( 'label' => 'Hidden', 'public' => false, 'show_ui' => false ) );
register_post_meta( 'ncd_book', 'isbn', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
register_post_meta( 'ncd_book', '_owned_rest', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => function () { return current_user_can( 'edit_posts' ); } ) );
register_post_meta( 'ncd_book', '_owned_norest', array( 'type' => 'string', 'single' => true, 'auth_callback' => '__return_true' ) );
$b = ncd_post( array( 'post_type' => 'ncd_book' ) );
update_post_meta( $b, 'unregistered_note', 'hi' );
update_post_meta( $b, '_private_state', 'x' );
update_post_meta( $b, 'stripe_api_key', 'sk_live_123' );
update_post_meta( $b, '_wp_page_template', 'default' );
delete_transient( 'ncd_meta_keys_' . md5( 'post|ncd_book|' . wp_cache_get_last_changed( 'posts' ) . wp_cache_get_last_changed( 'terms' ) . wp_cache_get_last_changed( 'users' ) ) );
ncd_reboot();

$p = NCD_Registry::provider( 'wordpress' );
ncd_t( $p && 'generic' === $p['adapter'], 'generic provider registered' );
ncd_t( isset( $p['entities']['type-ncd_book'] ), 'custom post type discovered' );
ncd_t( isset( $p['entities']['tax-ncd_genre'] ), 'custom taxonomy discovered' );
ncd_t( ! isset( $p['entities']['type-ncd_hidden'] ), 'non-UI post type skipped' );
ncd_t( ! isset( $p['entities']['type-revision'] ) && ! isset( $p['entities']['type-acf-field-group'] ), 'internal post types skipped' );
ncd_t( isset( $p['entities']['users'] ), 'users entity discovered' );

$e = $p['entities']['type-ncd_book'];
$f = $e['fields'];
ncd_t( isset( $f['meta:isbn'] ) && 'registered-meta' === $f['meta:isbn']['origin'] && ! $f['meta:isbn']['protected'], 'registered meta discovered and editable' );
ncd_t( isset( $f['meta:unregistered_note'] ) && 'discovered-meta' === $f['meta:unregistered_note']['origin'], 'unregistered meta discovered from data' );
ncd_t( $f['meta:_private_state']['protected'], 'underscore meta is protected by default' );
ncd_t( $f['meta:stripe_api_key']['secret'] && $f['meta:stripe_api_key']['protected'], 'secret-looking meta is secret + protected' );
ncd_t( $f['meta:_wp_page_template']['protected'], 'structural WordPress key protected' );
ncd_t( ! $f['meta:_owned_rest']['protected'], 'private key registered with auth_callback + show_in_rest is editable' );
ncd_t( $f['meta:_owned_norest']['protected'], 'private key without show_in_rest stays protected' );
ncd_t( isset( $f['tax_ncd_genre'] ) && 'term_list' === $f['tax_ncd_genre']['type'], 'taxonomy field attached to post type' );
ncd_t( isset( $f['post_title'], $f['post_content'], $f['post_status'] ), 'core post fields present' );

$r = NCD_Store::read( $e, $b );
ncd_eq( $r['meta:stripe_api_key'], NCD_Policy::REDACTED, 'secret value redacted on read' );

// Users: roles + capabilities never writable.
$u = $p['entities']['users']['fields'];
ncd_t( $u['roles']['protected'], 'user roles protected' );
foreach ( $u as $k => $uf ) {
	if ( preg_match( '/capabilities|user_level|session_tokens/', $k ) ) { ncd_t( $uf['protected'], "user key {$k} protected" ); }
}
ncd_t( ! isset( $u['user_pass'] ) || $u['user_pass']['protected'], 'user_pass not exposed as writable' );

// Policy classification table.
$cases = array(
	array( '_edit_lock', true, false ), array( '_thumbnail_id', true, false ), array( 'wp_capabilities', true, false ),
	array( 'mailchimp_webhook', true, true ), array( '_gm_host_password_hash', true, true ), array( 'color', false, false ),
	array( '_elementor_data', true, false ), array( '_yoast_wpseo_title', true, false ), array( 'otp_seed', true, true ),
);
foreach ( $cases as $c ) {
	$cl = NCD_Policy::classify( $c[0], 'post' );
	ncd_t( $cl['protected'] === $c[1] && $cl['secret'] === $c[2], "classify {$c[0]}", $cl );
}
add_filter( 'ninecode_data_manager_writable_meta', $grant = function ( $ok, $key ) { return '_private_state' === $key ? true : $ok; }, 10, 2 );
ncd_t( ! NCD_Policy::classify( '_private_state' )['protected'], 'legacy writable_meta filter can authorize a private key' );
ncd_t( NCD_Policy::classify( '_edit_lock' )['protected'], 'legacy filter cannot unlock structural keys' );
remove_filter( 'ninecode_data_manager_writable_meta', $grant, 10 );

// Contract: registration validation.
$err = NCD_Registry::register( 'future', array( 'contract' => 99, 'entities' => array( 'x' => array( 'kind' => 'post', 'object_type' => 'post' ) ) ) );
ncd_t( is_wp_error( $err ) && 'ncd_contract' === $err->get_error_code(), 'newer contract version rejected' );
$err = NCD_Registry::register( 'empty', array( 'entities' => array() ) );
ncd_t( is_wp_error( $err ), 'provider without entities rejected' );
$err = NCD_Registry::register( 'badkind', array( 'entities' => array( 'x' => array( 'kind' => 'spaceship' ) ) ) );
ncd_t( is_wp_error( $err ), 'unknown entity kind rejected' );
$err = NCD_Registry::register( 'nocb', array( 'entities' => array( 'x' => array( 'kind' => 'custom', 'fields' => array( 'a' => array( 'type' => 'text' ) ) ) ) ) );
ncd_t( is_wp_error( $err ), 'custom entity without read/write/list callbacks rejected' );
$err = NCD_Registry::register( 'badtype', array( 'entities' => array( 'x' => array( 'kind' => 'post', 'object_type' => 'post', 'fields' => array( 'a' => array( 'type' => 'hologram' ) ) ) ) ) );
ncd_t( is_wp_error( $err ), 'unknown field type rejected' );

// Provider claim removes the type from generic discovery.
add_action( 'ninecode_data_register_providers', $claim = function () {
	ninecode_data_register_provider( 'books-app', array( 'label' => 'Books app', 'version' => '1.2.3', 'entities' => array( 'book' => array( 'kind' => 'post', 'object_type' => 'ncd_book', 'fields' => array( 'post_title' => array( 'label' => 'Title', 'type' => 'text', 'storage' => 'post_field' ) ) ) ) ) );
} );
ncd_reboot();
ncd_t( NCD_Registry::entity( 'books-app', 'book' ) !== null, 'provider registered through ninecode_data_register_providers' );
ncd_t( ! isset( NCD_Registry::provider( 'wordpress' )['entities']['type-ncd_book'] ), 'claimed post type is not duplicated by generic discovery' );
remove_action( 'ninecode_data_register_providers', $claim );

// Legacy declaration (draft filter) → generic-backed provider.
add_filter( 'ninecode_data_manager_providers', $legacy = function ( $p ) { $p['legacy-app'] = array( 'label' => 'Legacy app', 'post_types' => array( 'ncd_book' ) ); return $p; } );
ncd_reboot();
$lp = NCD_Registry::provider( 'legacy-app' );
ncd_t( $lp && 'declared' === $lp['adapter'] && isset( $lp['entities']['ncd_book'] ), 'legacy ninecode_data_manager_providers declaration imported' );
remove_filter( 'ninecode_data_manager_providers', $legacy );

ncd_eq( ninecode_data_contract_version(), NCD_Registry::CONTRACT, 'contract version helper' );
$rep = NCD_Service::discovery_report();
ncd_t( ! empty( $rep['providers'] ), 'discovery report lists providers' );

wp_delete_post( $b, true );
ncd_reboot();
ncd_done();
