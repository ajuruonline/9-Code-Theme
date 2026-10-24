<?php
/**
 * REST API: ninecode-data/v1.
 *
 * Authentication is WordPress' own (cookie + X-WP-Nonce for the admin UI, application passwords for
 * scripts). Every route requires the Data Manager capability; object and field permissions are
 * enforced again inside NCD_Service for each record.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_REST {
	const NS = 'ninecode-data/v1';

	public static function register() {
		$e  = '(?P<provider>[a-z0-9_-]+)/(?P<entity>[a-z0-9_-]+)';
		$ok = array( __CLASS__, 'permission' );
		$routes = array(
			array( '/providers', 'GET', 'providers' ),
			array( '/entities/' . $e, 'GET', 'entity' ),
			array( '/records/' . $e, 'GET', 'list_records' ),
			array( '/records/' . $e, 'POST', 'create_record' ),
			array( '/records/' . $e . '/(?P<id>\d+)', 'GET', 'get_record' ),
			array( '/records/' . $e . '/(?P<id>\d+)', 'POST', 'save_record' ),
			array( '/records/' . $e . '/(?P<id>\d+)/trash', 'POST', 'trash_record' ),
			array( '/validate/' . $e, 'POST', 'validate' ),
			array( '/bulk/' . $e, 'POST', 'bulk' ),
			array( '/export/' . $e, 'POST', 'export' ),
			array( '/import/' . $e . '/stage', 'POST', 'stage' ),
			array( '/import/' . $e . '/preview', 'POST', 'preview' ),
			array( '/import/' . $e . '/apply', 'POST', 'apply' ),
			array( '/import/' . $e . '/discard', 'POST', 'discard' ),
			array( '/history', 'GET', 'history' ),
			array( '/history/(?P<id>\d+)', 'GET', 'history_item' ),
			array( '/history/(?P<id>\d+)/undo', 'POST', 'undo' ),
			array( '/lookup', 'GET', 'lookup' ),
		);
		foreach ( $routes as $r ) {
			register_rest_route( self::NS, $r[0], array( 'methods' => $r[1], 'callback' => array( __CLASS__, $r[2] ), 'permission_callback' => $ok ) );
		}
	}

	public static function permission() {
		if ( ! is_user_logged_in() ) { return new WP_Error( 'rest_forbidden', 'Log in to use Data Manager.', array( 'status' => 401 ) ); }
		return NCD_Service::can_use() ? true : new WP_Error( 'rest_forbidden', 'You do not have permission to use Data Manager.', array( 'status' => 403 ) );
	}

	private static function entity_from( WP_REST_Request $req ) {
		return NCD_Service::entity( (string) $req['provider'], (string) $req['entity'] );
	}

	private static function flags( WP_REST_Request $req ) {
		return array(
			'allow_status' => (bool) $req->get_param( 'allow_status' ),
			'allow_create' => (bool) $req->get_param( 'allow_create' ),
			'allow_trash'  => (bool) $req->get_param( 'allow_trash' ),
		);
	}

	private static function out( $data ) {
		return is_wp_error( $data ) ? $data : rest_ensure_response( $data );
	}

	public static function providers() {
		return self::out( NCD_Service::discovery_report() );
	}

	public static function entity( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		if ( ! NCD_Service::can( 'read', $e ) ) { return new WP_Error( 'ncd_forbidden', 'You cannot view this data type.', array( 'status' => 403 ) ); }
		$desc = NCD_Registry::describe_entity( $e );
		$desc['can_create'] = $e['allow_create'] && NCD_Service::can( 'create', $e );
		$desc['publish_field'] = NCD_Service::publish_config( $e )['field'];
		return self::out( $desc );
	}

	public static function list_records( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		return self::out( NCD_Service::list_records( $e, array(
			'search'     => (string) $req->get_param( 'search' ),
			'status'     => (string) $req->get_param( 'status' ),
			'orderby'    => (string) $req->get_param( 'orderby' ),
			'order'      => (string) $req->get_param( 'order' ),
			'page'       => (int) $req->get_param( 'page' ),
			'per_page'   => (int) ( $req->get_param( 'per_page' ) ?: 25 ),
			'filters'    => (array) $req->get_param( 'filters' ),
			'all_fields' => (bool) $req->get_param( 'all_fields' ),
		) ) );
	}

	public static function get_record( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		return is_wp_error( $e ) ? $e : self::out( NCD_Service::get_record( $e, (int) $req['id'] ) );
	}

	public static function save_record( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		$ctx = array_merge( self::flags( $req ), array( 'source' => (string) ( $req->get_param( 'source' ) ?: 'editor' ), 'expected_revision' => (string) $req->get_param( 'expected_revision' ) ) );
		if ( ! in_array( $ctx['source'], array( 'editor', 'api', 'mobile' ), true ) ) { $ctx['source'] = 'api'; }
		return self::out( NCD_Service::save( $e, (int) $req['id'], (array) $req->get_param( 'changes' ), $ctx ) );
	}

	public static function create_record( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		return self::out( NCD_Service::create( $e, (array) $req->get_param( 'values' ), array_merge( self::flags( $req ), array( 'source' => 'editor' ) ) ) );
	}

	public static function trash_record( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		return self::out( NCD_Service::trash( $e, (int) $req['id'], array_merge( self::flags( $req ), array( 'source' => 'editor' ) ) ) );
	}

	public static function validate( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		$id = (int) $req->get_param( 'id' );
		if ( $id && ! NCD_Service::can( 'edit', $e, $id ) ) { return new WP_Error( 'ncd_forbidden', 'You cannot edit this record.', array( 'status' => 403 ) ); }
		$v = NCD_Service::validate( $e, $id, (array) $req->get_param( 'changes' ), self::flags( $req ) );
		unset( $v['current'] );
		return self::out( $v );
	}

	public static function bulk( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		$changes = (array) $req->get_param( 'changes' );
		if ( ! $changes ) { return new WP_Error( 'ncd_bulk', 'Choose at least one field to change.', array( 'status' => 400 ) ); }
		return self::out( NCD_Service::bulk( $e, (array) $req->get_param( 'ids' ), $changes, array_merge( self::flags( $req ), array( 'source' => 'bulk' ) ) ) );
	}

	public static function export( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		$args = array(
			'ids'     => array_filter( array_map( 'absint', (array) $req->get_param( 'ids' ) ) ),
			'search'  => (string) $req->get_param( 'search' ),
			'status'  => (string) $req->get_param( 'status' ),
			'filters' => (array) $req->get_param( 'filters' ),
			'orderby' => 'id',
			'order'   => 'asc',
			'limit'   => (int) ( $req->get_param( 'limit' ) ?: NCD_Exchange::MAX_EXPORT ),
		);
		$records = NCD_Exchange::export_records( $e, $args );
		if ( is_wp_error( $records ) ) { return $records; }
		$format = sanitize_key( (string) ( $req->get_param( 'format' ) ?: 'json' ) );
		$base   = sanitize_file_name( $e['provider'] . '-' . $e['id'] . '-' . gmdate( 'Ymd-His' ) );
		if ( 'csv' === $format || 'xlsx' === $format ) {
			$flat = NCD_Exchange::flatten( $e, $records );
			if ( 'csv' === $format ) {
				return self::out( array( 'filename' => $base . '.csv', 'mime' => 'text/csv', 'encoding' => 'text', 'content' => NCD_Sheet::csv( $flat['headers'], $flat['rows'] ), 'count' => count( $records ) ) );
			}
			$bin = NCD_Sheet::xlsx( $flat['headers'], $flat['rows'], $flat['guide'] );
			if ( is_wp_error( $bin ) ) { return $bin; }
			return self::out( array( 'filename' => $base . '.xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'encoding' => 'base64', 'content' => base64_encode( $bin ), 'count' => count( $records ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary file transport over JSON.
		}
		$pkg = NCD_Exchange::package( $e, $records );
		return self::out( array( 'filename' => $base . '.ai.json', 'mime' => 'application/json', 'encoding' => 'text', 'content' => wp_json_encode( $pkg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'count' => count( $records ) ) );
	}

	public static function stage( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		$files = $req->get_file_params();
		if ( ! empty( $files['file']['tmp_name'] ) ) {
			$file = $files['file'];
			if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) { return new WP_Error( 'ncd_upload', 'The upload failed.', array( 'status' => 400 ) ); }
			$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
			$pkg = NCD_Exchange::parse( $file['tmp_name'], $ext, $e, true );
		} else {
			$raw = (string) $req->get_param( 'package' );
			if ( '' === trim( $raw ) ) { return new WP_Error( 'ncd_upload', 'Choose a file or paste a package.', array( 'status' => 400 ) ); }
			$pkg = NCD_Exchange::parse( $raw, 'json', $e, false );
		}
		if ( is_wp_error( $pkg ) ) { $pkg->add_data( array( 'status' => 400 ) ); return $pkg; }
		return self::out( NCD_Exchange::stage( $pkg ) );
	}

	private static function staged( WP_REST_Request $req ) {
		$e = self::entity_from( $req );
		if ( is_wp_error( $e ) ) { return array( $e, null ); }
		$pkg = NCD_Exchange::load_stage( (string) $req->get_param( 'token' ) );
		if ( is_wp_error( $pkg ) ) { return array( $pkg, null ); }
		if ( $pkg['provider'] !== $e['provider'] || $pkg['entity'] !== $e['id'] ) { return array( new WP_Error( 'ncd_scope', 'This import belongs to another data type.', array( 'status' => 400 ) ), null ); }
		return array( $e, $pkg );
	}

	public static function preview( WP_REST_Request $req ) {
		list( $e, $pkg ) = self::staged( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		return self::out( NCD_Exchange::preview( $e, $pkg, (int) $req->get_param( 'offset' ), (int) ( $req->get_param( 'limit' ) ?: 50 ), self::flags( $req ) ) );
	}

	public static function apply( WP_REST_Request $req ) {
		list( $e, $pkg ) = self::staged( $req );
		if ( is_wp_error( $e ) ) { return $e; }
		$selection = (array) $req->get_param( 'selection' );
		if ( count( $selection ) > 200 ) { return new WP_Error( 'ncd_batch', 'Apply at most 200 records per request.', array( 'status' => 400 ) ); }
		$opts = array_merge( self::flags( $req ), array( 'history_id' => (int) $req->get_param( 'history_id' ) ) );
		return self::out( NCD_Exchange::apply( $e, $pkg, $selection, $opts ) );
	}

	public static function discard( WP_REST_Request $req ) {
		NCD_Exchange::discard_stage( (string) $req->get_param( 'token' ) );
		return self::out( array( 'ok' => true ) );
	}

	public static function history( WP_REST_Request $req ) {
		return self::out( NCD_History::recent( array(
			'provider'  => (string) $req->get_param( 'provider' ),
			'entity'    => (string) $req->get_param( 'entity' ),
			'page'      => (int) $req->get_param( 'page' ),
			'all_users' => current_user_can( 'manage_options' ) && $req->get_param( 'all_users' ),
		) ) );
	}

	public static function history_item( WP_REST_Request $req ) {
		$h = NCD_History::get( (int) $req['id'] );
		if ( ! $h ) { return new WP_Error( 'ncd_history', 'Not found.', array( 'status' => 404 ) ); }
		if ( (int) $h['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'ncd_forbidden', 'Not allowed.', array( 'status' => 403 ) ); }
		return self::out( $h );
	}

	public static function undo( WP_REST_Request $req ) {
		return self::out( NCD_Service::undo( (int) $req['id'], (bool) $req->get_param( 'force' ) ) );
	}

	/** Search helper for relationship/media/user/term pickers. */
	public static function lookup( WP_REST_Request $req ) {
		$type   = sanitize_key( (string) $req->get_param( 'type' ) );
		$target = sanitize_key( (string) $req->get_param( 'target' ) );
		$search = sanitize_text_field( (string) $req->get_param( 'search' ) );
		$ids    = array_filter( array_map( 'absint', (array) $req->get_param( 'ids' ) ) );
		$out    = array();
		if ( 'user' === $type ) {
			if ( ! current_user_can( 'list_users' ) ) { return new WP_Error( 'ncd_forbidden', 'You cannot list users.', array( 'status' => 403 ) ); }
			$q = array( 'number' => 20, 'fields' => array( 'ID', 'display_name', 'user_email' ) );
			if ( $ids ) { $q['include'] = $ids; } elseif ( '' !== $search ) { $q['search'] = '*' . $search . '*'; }
			foreach ( get_users( $q ) as $u ) { $out[] = array( 'id' => (int) $u->ID, 'label' => $u->display_name, 'meta' => $u->user_email ); }
		} elseif ( 'term' === $type ) {
			if ( ! $target || ! taxonomy_exists( $target ) ) { return self::out( array() ); }
			$q = array( 'taxonomy' => $target, 'hide_empty' => false, 'number' => 30 );
			if ( $ids ) { $q['include'] = $ids; } elseif ( '' !== $search ) { $q['search'] = $search; }
			foreach ( (array) get_terms( $q ) as $t ) { if ( is_object( $t ) ) { $out[] = array( 'id' => (int) $t->term_id, 'label' => $t->name, 'meta' => $t->slug ); } }
		} else {
			$pt = 'media' === $type ? 'attachment' : ( $target && post_type_exists( $target ) ? $target : 'any' );
			$q  = array( 'post_type' => $pt, 'post_status' => 'attachment' === $pt ? 'inherit' : array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => 20, 'perm' => 'readable', 'suppress_filters' => false );
			if ( $ids ) { $q['post__in'] = $ids; $q['posts_per_page'] = count( $ids ); $q['orderby'] = 'post__in'; } elseif ( '' !== $search ) { $q['s'] = $search; }
			foreach ( get_posts( $q ) as $p ) {
				if ( ! current_user_can( 'read_post', $p->ID ) ) { continue; }
				$item = array( 'id' => (int) $p->ID, 'label' => $p->post_title ?: '#' . $p->ID, 'meta' => $p->post_type . ' · ' . $p->post_status );
				if ( 'attachment' === $p->post_type ) { $item['thumb'] = (string) wp_get_attachment_image_url( $p->ID, 'thumbnail' ); $item['meta'] = (string) get_post_mime_type( $p ); }
				$out[] = $item;
			}
		}
		return self::out( $out );
	}
}
