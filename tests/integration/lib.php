<?php
/**
 * Minimal assertion helpers for integration suites run with `wp eval-file`.
 * Each suite prints PASS/FAIL lines and a final "<suite>: N passed, M failed" line that run.sh totals.
 */
if ( ! function_exists( 'ncd_t' ) ) {
	$GLOBALS['ncd_t'] = array( 'pass' => 0, 'fail' => 0, 'suite' => 'suite' );
	function ncd_suite( $name ) { $GLOBALS['ncd_t'] = array( 'pass' => 0, 'fail' => 0, 'suite' => $name ); }
	function ncd_t( $cond, $msg, $detail = null ) {
		if ( $cond ) { $GLOBALS['ncd_t']['pass']++; echo "PASS {$msg}\n"; return true; }
		$GLOBALS['ncd_t']['fail']++;
		echo "FAIL {$msg}" . ( null !== $detail ? ' :: ' . ( is_scalar( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' ) . "\n";
		return false;
	}
	function ncd_eq( $a, $b, $msg ) { return ncd_t( $a === $b, $msg, array( 'got' => $a, 'want' => $b ) ); }
	function ncd_done() {
		$t = $GLOBALS['ncd_t'];
		echo "\n{$t['suite']}: {$t['pass']} passed, {$t['fail']} failed\n";
	}
	function ncd_as( $login_or_id ) {
		$u = is_numeric( $login_or_id ) ? get_user_by( 'id', $login_or_id ) : get_user_by( 'login', $login_or_id );
		wp_set_current_user( $u ? $u->ID : 0 );
		return $u;
	}
	function ncd_user( $login, $role ) {
		$u = get_user_by( 'login', $login );
		if ( ! $u ) { $id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.test', 'role' => $role ) ); $u = get_user_by( 'id', $id ); }
		else { $u->set_role( $role ); }
		return $u;
	}
	function ncd_rest( $method, $path, array $params = array(), $json = null ) {
		$r = new WP_REST_Request( $method, $path );
		foreach ( $params as $k => $v ) { $r->set_param( $k, $v ); }
		if ( null !== $json ) { $r->set_header( 'content-type', 'application/json' ); $r->set_body( wp_json_encode( $json ) ); }
		$res = rest_do_request( $r );
		return array( $res->get_status(), $res->get_data() );
	}
	/** Rebuild the registry (after registering fixtures or changing roles). */
	function ncd_reboot() { NCD_Registry::reset(); NCD_Registry::providers(); }
	function ncd_entity( $p, $e ) { return NCD_Registry::entity( $p, $e ); }
	function ncd_post( array $args = array() ) {
		return (int) wp_insert_post( array_merge( array( 'post_title' => 'Fixture ' . wp_generate_password( 6, false ), 'post_status' => 'draft', 'post_type' => 'post', 'post_author' => 1 ), $args ) );
	}
	function ncd_fresh( $id ) { clean_post_cache( $id ); wp_cache_flush(); return get_post( $id ); }
}
