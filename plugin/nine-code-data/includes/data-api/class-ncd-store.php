<?php
/**
 * Built-in storage helpers for post, term and user entities.
 *
 * Providers can override any of this with entity callbacks (read/write/list/...) or field callbacks.
 * Custom-table entities ('custom' kind) must supply their own callbacks; this class never touches
 * a provider's tables.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Store {

	/* --------------------------------------------------------------- listing */

	/**
	 * @param array $args search, status, orderby, order, page, per_page, ids, filters (field => value).
	 * @return array{ids:int[],total:int}
	 */
	public static function query( array $entity, array $args ) {
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 25 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$order    = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';
		$search   = trim( (string) ( $args['search'] ?? '' ) );
		$ids      = array_filter( array_map( 'absint', (array) ( $args['ids'] ?? array() ) ) );

		if ( isset( $entity['callbacks']['list'] ) ) {
			$r = call_user_func( $entity['callbacks']['list'], array_merge( $args, array( 'per_page' => $per_page, 'page' => $page, 'order' => $order ) ), $entity );
			return array( 'ids' => array_values( array_map( 'intval', (array) ( $r['ids'] ?? array() ) ) ), 'total' => (int) ( $r['total'] ?? 0 ) );
		}

		switch ( $entity['kind'] ) {
			case 'post':
				$status  = sanitize_key( (string) ( $args['status'] ?? 'any' ) );
				$allowed = array_merge( $entity['statuses'], array( 'inherit' ) );
				$q = array(
					'post_type'              => $entity['object_type'],
					'post_status'            => ( 'any' === $status || '' === $status ) ? ( 'attachment' === $entity['object_type'] ? 'inherit' : $entity['statuses'] ) : ( in_array( $status, $allowed, true ) ? $status : 'publish' ),
					'posts_per_page'         => $per_page,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => self::post_orderby( (string) ( $args['orderby'] ?? 'modified' ) ),
					'order'                  => $order,
					'suppress_filters'       => false,
					'ignore_sticky_posts'    => true,
					'update_post_term_cache' => false,
				);
				if ( '' !== $search ) { $q['s'] = $search; }
				if ( $ids ) { $q['post__in'] = $ids; $q['posts_per_page'] = min( 500, count( $ids ) ); $q['paged'] = 1; }
				$meta_query = array();
				foreach ( (array) ( $args['filters'] ?? array() ) as $fkey => $fval ) {
					$f = $entity['fields'][ $fkey ] ?? null;
					if ( ! $f || '' === (string) $fval ) { continue; }
					if ( 'meta' === $f['storage'] && empty( $f['secret'] ) ) { $meta_query[] = array( 'key' => $f['source'], 'value' => (string) $fval, 'compare' => 'LIKE' ); }
					if ( 'taxonomy' === $f['storage'] ) { $q['tax_query'][] = array( 'taxonomy' => $f['source'], 'field' => 'term_id', 'terms' => array_map( 'absint', (array) $fval ) ); }
					if ( 'post_field' === $f['storage'] && 'post_author' === $f['source'] ) { $q['author'] = absint( $fval ); }
				}
				if ( $meta_query ) { $q['meta_query'] = $meta_query; }
				$q = apply_filters( 'ninecode_data_post_query_args', $q, $entity, $args );
				$wpq = new WP_Query( $q );
				return array( 'ids' => array_map( 'intval', $wpq->posts ), 'total' => (int) $wpq->found_posts );

			case 'term':
				$q = array( 'taxonomy' => $entity['object_type'], 'hide_empty' => false, 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page, 'orderby' => in_array( $args['orderby'] ?? '', array( 'name', 'slug', 'count', 'term_id' ), true ) ? $args['orderby'] : 'name', 'order' => $order, 'fields' => 'ids' );
				if ( '' !== $search ) { $q['search'] = $search; }
				if ( $ids ) { $q['include'] = $ids; $q['number'] = 0; $q['offset'] = 0; }
				$found = get_terms( $q );
				$count_q = $q; unset( $count_q['number'], $count_q['offset'] ); $count_q['fields'] = 'count';
				return array( 'ids' => is_wp_error( $found ) ? array() : array_map( 'intval', $found ), 'total' => (int) ( is_wp_error( $found ) ? 0 : get_terms( $count_q ) ) );

			case 'user':
				$q = array( 'number' => $per_page, 'paged' => $page, 'fields' => 'ID', 'orderby' => in_array( $args['orderby'] ?? '', array( 'display_name', 'registered', 'login', 'email', 'ID' ), true ) ? $args['orderby'] : 'display_name', 'order' => $order, 'count_total' => true );
				if ( '' !== $search ) { $q['search'] = '*' . $search . '*'; $q['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' ); }
				if ( $ids ) { $q['include'] = $ids; $q['number'] = count( $ids ); $q['paged'] = 1; }
				if ( ! empty( $entity['capabilities']['role__in'] ) ) { $q['role__in'] = (array) $entity['capabilities']['role__in']; }
				$q = apply_filters( 'ninecode_data_user_query_args', $q, $entity, $args );
				$uq = new WP_User_Query( $q );
				return array( 'ids' => array_map( 'intval', (array) $uq->get_results() ), 'total' => (int) $uq->get_total() );
		}
		return array( 'ids' => array(), 'total' => 0 );
	}

	private static function post_orderby( $orderby ) {
		$map = array( 'title' => 'title', 'date' => 'date', 'modified' => 'modified', 'id' => 'ID', 'menu_order' => 'menu_order', 'author' => 'author', 'status' => 'post_status' );
		return $map[ $orderby ] ?? 'modified';
	}

	/* --------------------------------------------------------------- object info */

	public static function exists( array $entity, $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) { return false; }
		switch ( $entity['kind'] ) {
			case 'post': $p = get_post( $id ); return $p && $p->post_type === $entity['object_type'] && 'auto-draft' !== $p->post_status;
			case 'term': $t = get_term( $id, $entity['object_type'] ); return $t && ! is_wp_error( $t );
			case 'user': return (bool) get_userdata( $id );
		}
		if ( isset( $entity['callbacks']['read'] ) ) { return null !== call_user_func( $entity['callbacks']['read'], $id, $entity ); }
		return false;
	}

	/** Summary used in lists and history: label, status, modified, links. */
	public static function summary( array $entity, $id ) {
		$id  = (int) $id;
		$out = array( 'id' => $id, 'label' => '#' . $id, 'status' => '', 'modified' => '', 'edit_url' => '', 'view_url' => '' );
		switch ( $entity['kind'] ) {
			case 'post':
				$p = get_post( $id );
				if ( $p ) {
					$out['label']    = '' !== $p->post_title ? $p->post_title : '(no title)';
					$out['status']   = $p->post_status;
					$out['modified'] = $p->post_modified_gmt;
					$out['edit_url'] = (string) get_edit_post_link( $id, 'raw' );
					$out['view_url'] = in_array( $p->post_status, array( 'publish', 'private' ), true ) ? (string) get_permalink( $id ) : '';
				}
				break;
			case 'term':
				$t = get_term( $id, $entity['object_type'] );
				if ( $t && ! is_wp_error( $t ) ) { $out['label'] = $t->name; $out['status'] = $t->count . ' items'; $out['edit_url'] = (string) get_edit_term_link( $t ); $out['view_url'] = (string) get_term_link( $t ); }
				break;
			case 'user':
				$u = get_userdata( $id );
				if ( $u ) { $out['label'] = $u->display_name; $out['status'] = implode( ', ', $u->roles ); $out['edit_url'] = (string) get_edit_user_link( $id ); }
				break;
		}
		if ( isset( $entity['callbacks']['label'] ) ) { $out = array_merge( $out, (array) call_user_func( $entity['callbacks']['label'], $id, $entity ) ); }
		return $out;
	}

	/* --------------------------------------------------------------- reading */

	public static function read( array $entity, $id ) {
		if ( isset( $entity['callbacks']['read'] ) ) {
			$values = call_user_func( $entity['callbacks']['read'], (int) $id, $entity );
			$values = is_array( $values ) ? $values : array();
			// Fields not supplied by the provider callback fall back to built-in storage.
			foreach ( $entity['fields'] as $k => $f ) {
				if ( ! array_key_exists( $k, $values ) && 'custom' !== $entity['kind'] && 'callback' !== $f['storage'] ) { $values[ $k ] = self::read_field( $entity, $f, $id ); }
			}
		} else {
			$values = array();
			foreach ( $entity['fields'] as $k => $f ) { $values[ $k ] = self::read_field( $entity, $f, $id ); }
		}
		foreach ( $entity['fields'] as $k => $f ) {
			if ( array_key_exists( $k, $values ) ) { $values[ $k ] = NCD_Policy::redact( $f, $values[ $k ] ); }
		}
		return $values;
	}

	public static function read_field( array $entity, array $f, $id ) {
		$id = (int) $id;
		if ( $f['read'] ) { return call_user_func( $f['read'], $id, $f, $entity ); }
		$src = $f['source'];
		switch ( $f['storage'] ) {
			case 'post_field':
				$p = get_post( $id );
				if ( ! $p ) { return ''; }
				if ( 'page_template' === $src ) { return (string) get_page_template_slug( $id ); }
				return isset( $p->$src ) ? $p->$src : '';
			case 'thumbnail':
				return (int) get_post_thumbnail_id( $id );
			case 'taxonomy':
				$terms = wp_get_object_terms( $id, $src, array( 'fields' => 'ids' ) );
				return is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
			case 'meta':
				$type = self::meta_type( $entity );
				if ( ! $f['single'] ) { return array_values( (array) get_metadata( $type, $id, $src, false ) ); }
				return get_metadata( $type, $id, $src, true );
			case 'acf':
				if ( ! function_exists( 'get_field' ) ) { return null; }
				$acf_id = self::acf_object_id( $entity, $id );
				return self::acf_keys_to_names( $f, get_field( $src, $acf_id, false ) );
			case 'user_field':
				$u = get_userdata( $id );
				return $u ? ( 'roles' === $src ? implode( ', ', $u->roles ) : (string) $u->$src ) : '';
			case 'term_field':
				$t = get_term( $id, $entity['object_type'] );
				return ( $t && ! is_wp_error( $t ) ) ? $t->$src : '';
		}
		return null;
	}

	public static function meta_type( array $entity ) {
		return array( 'post' => 'post', 'term' => 'term', 'user' => 'user' )[ $entity['kind'] ] ?? 'post';
	}

	public static function acf_object_id( array $entity, $id ) {
		if ( 'term' === $entity['kind'] ) { return $entity['object_type'] . '_' . (int) $id; }
		if ( 'user' === $entity['kind'] ) { return 'user_' . (int) $id; }
		return (int) $id;
	}

	/** ACF returns nested rows keyed by sub-field keys (field_xxx); expose them by name. */
	private static function acf_keys_to_names( array $f, $value ) {
		if ( ! is_array( $value ) || ( ! $f['sub_fields'] && ! $f['layouts'] ) ) { return $value; }
		$map_row = static function ( $row, $subs ) use ( &$map_row ) {
			if ( ! is_array( $row ) ) { return $row; }
			$out = array();
			foreach ( $row as $k => $v ) {
				$name = $k;
				foreach ( $subs as $sub ) { if ( ( $sub['source'] ?? '' ) === $k ) { $name = $sub['key']; $v = self::acf_keys_to_names( $sub, $v ); break; } }
				$out[ $name ] = $v;
			}
			return $out;
		};
		if ( 'group' === $f['type'] ) { return $map_row( $value, $f['sub_fields'] ); }
		if ( 'repeater' === $f['type'] ) { return array_values( array_map( static function ( $r ) use ( $map_row, $f ) { return $map_row( $r, $f['sub_fields'] ); }, $value ) ); }
		if ( 'flexible' === $f['type'] ) {
			return array_values( array_map( static function ( $r ) use ( $map_row, $f ) {
				$layout = is_array( $r ) ? (string) ( $r['acf_fc_layout'] ?? '' ) : '';
				$subs   = $f['layouts'][ $layout ]['sub_fields'] ?? array();
				return array( 'acf_fc_layout' => $layout ) + $map_row( array_diff_key( (array) $r, array( 'acf_fc_layout' => 1 ) ), $subs );
			}, $value ) );
		}
		return $value;
	}

	/** Inverse of acf_keys_to_names for writing (names -> field keys). */
	private static function acf_names_to_keys( array $f, $value ) {
		if ( ! is_array( $value ) ) { return $value; }
		$map_row = static function ( $row, $subs ) {
			$out = array();
			foreach ( (array) $row as $name => $v ) {
				if ( 'acf_fc_layout' === $name ) { $out[ $name ] = $v; continue; }
				$sub = $subs[ $name ] ?? null;
				$out[ $sub ? $sub['source'] : $name ] = $sub ? self::acf_names_to_keys( $sub, $v ) : $v;
			}
			return $out;
		};
		if ( 'group' === $f['type'] ) { return $map_row( $value, $f['sub_fields'] ); }
		if ( 'repeater' === $f['type'] ) { return array_values( array_map( static function ( $r ) use ( $map_row, $f ) { return $map_row( $r, $f['sub_fields'] ); }, $value ) ); }
		if ( 'flexible' === $f['type'] ) {
			return array_values( array_map( static function ( $r ) use ( $map_row, $f ) { return $map_row( $r, $f['layouts'][ $r['acf_fc_layout'] ?? '' ]['sub_fields'] ?? array() ); }, $value ) );
		}
		return $value;
	}

	/* --------------------------------------------------------------- writing */

	/**
	 * Write one already-validated field value with the built-in storage.
	 *
	 * @return true|WP_Error
	 */
	public static function write_field( array $entity, array $f, $id, $value, array $context = array() ) {
		$id = (int) $id;
		if ( $f['write'] ) {
			$r = call_user_func( $f['write'], $id, $value, $f, $entity, $context );
			return is_wp_error( $r ) ? $r : true;
		}
		$src = $f['source'];
		switch ( $f['storage'] ) {
			case 'post_field':
				$allowed = array( 'post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status', 'post_date', 'post_author', 'post_parent', 'menu_order', 'comment_status', 'ping_status', 'post_password', 'page_template' );
				if ( ! in_array( $src, $allowed, true ) ) { return new WP_Error( 'ncd_field', 'Post field ' . $src . ' cannot be edited.' ); }
				$update = array( 'ID' => $id, $src => $value );
				if ( 'post_date' === $src ) { $update['post_date_gmt'] = get_gmt_from_date( $value ); $update['edit_date'] = true; }
				if ( 'page_template' === $src ) { $update = array( 'ID' => $id, 'page_template' => '' === $value ? 'default' : $value ); }
				$r = wp_update_post( wp_slash( $update ), true );
				return is_wp_error( $r ) ? $r : true;
			case 'thumbnail':
				if ( $value ) { return set_post_thumbnail( $id, (int) $value ) ? true : ( (int) get_post_thumbnail_id( $id ) === (int) $value ? true : new WP_Error( 'ncd_thumb', 'Could not set the featured image.' ) ); }
				delete_post_thumbnail( $id );
				return true;
			case 'taxonomy':
				if ( ! current_user_can( get_taxonomy( $src )->cap->assign_terms ?? 'edit_posts' ) ) { return new WP_Error( 'ncd_cap', 'You cannot assign ' . $src . ' terms.' ); }
				$r = wp_set_object_terms( $id, array_map( 'intval', (array) $value ), $src, false );
				return is_wp_error( $r ) ? $r : true;
			case 'meta':
				$type = self::meta_type( $entity );
				if ( ! $f['single'] ) {
					delete_metadata( $type, $id, $src );
					foreach ( (array) $value as $item ) { add_metadata( $type, $id, $src, wp_slash( $item ) ); }
					return true;
				}
				if ( '' === $value || null === $value || array() === $value ) { delete_metadata( $type, $id, $src ); return true; }
				update_metadata( $type, $id, $src, wp_slash( $value ) );
				return true;
			case 'acf':
				if ( ! function_exists( 'update_field' ) ) { return new WP_Error( 'ncd_acf', 'ACF is not active.' ); }
				$ok = update_field( $src, self::acf_names_to_keys( $f, $value ), self::acf_object_id( $entity, $id ) );
				return false === $ok && ! NCD_Types::same( self::read_field( $entity, $f, $id ), $value ) ? new WP_Error( 'ncd_acf', 'ACF refused the value for ' . $f['label'] . '.' ) : true;
			case 'user_field':
				if ( ! in_array( $src, array( 'display_name', 'user_email', 'user_url', 'first_name', 'last_name', 'nickname', 'description' ), true ) ) { return new WP_Error( 'ncd_field', 'User field ' . $src . ' cannot be edited here.' ); }
				$r = wp_update_user( array( 'ID' => $id, $src => $value ) );
				return is_wp_error( $r ) ? $r : true;
			case 'term_field':
				if ( ! in_array( $src, array( 'name', 'slug', 'description', 'parent' ), true ) ) { return new WP_Error( 'ncd_field', 'Term field ' . $src . ' cannot be edited.' ); }
				$r = wp_update_term( $id, $entity['object_type'], array( $src => $value ) );
				return is_wp_error( $r ) ? $r : true;
		}
		return new WP_Error( 'ncd_storage', 'No writer for ' . $f['label'] . ' (' . $f['storage'] . ').' );
	}

	/** Create an empty object; field values are written afterwards. */
	public static function create( array $entity, array $values ) {
		if ( isset( $entity['callbacks']['create'] ) ) { return call_user_func( $entity['callbacks']['create'], $values, $entity ); }
		switch ( $entity['kind'] ) {
			case 'post':
				$title = isset( $values['post_title'] ) ? sanitize_text_field( (string) $values['post_title'] ) : '';
				return wp_insert_post( array( 'post_type' => $entity['object_type'], 'post_status' => 'draft', 'post_title' => '' !== $title ? $title : 'Untitled' ), true );
			case 'term':
				$name = isset( $values['name'] ) ? sanitize_text_field( (string) $values['name'] ) : '';
				if ( '' === $name ) { return new WP_Error( 'ncd_create', 'A name is required to create a term.' ); }
				$r = wp_insert_term( $name, $entity['object_type'] );
				return is_wp_error( $r ) ? $r : (int) $r['term_id'];
		}
		return new WP_Error( 'ncd_create', 'This entity does not support creating records from Data Manager.' );
	}

	public static function trash( array $entity, $id ) {
		if ( isset( $entity['callbacks']['trash'] ) ) { return call_user_func( $entity['callbacks']['trash'], (int) $id, $entity ); }
		if ( 'post' === $entity['kind'] ) { return wp_trash_post( (int) $id ) ? true : new WP_Error( 'ncd_trash', 'Could not move #' . $id . ' to the trash.' ); }
		return new WP_Error( 'ncd_trash', 'This entity does not support trashing from Data Manager.' );
	}

	public static function untrash( array $entity, $id ) {
		if ( 'post' === $entity['kind'] ) { $r = wp_untrash_post( (int) $id ); if ( $r ) { wp_update_post( array( 'ID' => (int) $id, 'post_status' => 'draft' ) ); } return $r ? true : new WP_Error( 'ncd_untrash', 'Could not restore #' . $id . '.' ); }
		return new WP_Error( 'ncd_untrash', 'This entity cannot be restored automatically.' );
	}
}
