<?php
/**
 * Value validation/sanitization by field type.
 *
 * Used only when the owning provider did not supply its own sanitize/validate callback. Every
 * method returns array( $clean_value, $error_message ); an empty message means the value is valid.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Types {

	public static function sanitize( array $field, $value ) {
		if ( $field['sanitize'] ) {
			// Contract: return the clean value, or a WP_Error to reject the input.
			$r = call_user_func( $field['sanitize'], $value, $field );
			return is_wp_error( $r ) ? array( null, $r->get_error_message() ) : array( $r, '' );
		}
		$type = $field['type'];
		if ( null === $value ) { $value = ''; }
		switch ( $type ) {
			case 'text':
				return array( sanitize_text_field( self::scalar( $value ) ), '' );
			case 'textarea':
				return array( sanitize_textarea_field( self::scalar( $value ) ), '' );
			case 'html':
				$v = self::scalar( $value );
				return array( current_user_can( 'unfiltered_html' ) ? $v : wp_kses_post( $v ), '' );
			case 'number':
				$v = trim( self::scalar( $value ) );
				if ( '' === $v ) { return array( '', '' ); }
				return is_numeric( $v ) ? array( 0 + $v, '' ) : array( '', 'must be a number' );
			case 'integer':
				$v = trim( self::scalar( $value ) );
				if ( '' === $v ) { return array( '', '' ); }
				return preg_match( '/^-?\d+$/', $v ) ? array( (int) $v, '' ) : array( '', 'must be a whole number' );
			case 'year':
				$v = trim( self::scalar( $value ) );
				return ( '' === $v || preg_match( '/^\d{4}$/', $v ) ) ? array( $v, '' ) : array( '', 'must be a four-digit year' );
			case 'email':
				$v = trim( self::scalar( $value ) );
				if ( '' === $v ) { return array( '', '' ); }
				return is_email( $v ) ? array( sanitize_email( $v ), '' ) : array( '', 'must be a valid email address' );
			case 'url':
				$v = trim( self::scalar( $value ) );
				if ( '' === $v ) { return array( '', '' ); }
				$clean = esc_url_raw( $v, array( 'http', 'https', 'mailto', 'tel' ) );
				return $clean ? array( $clean, '' ) : array( '', 'must be a valid http(s) address' );
			case 'date':
				$v = trim( self::scalar( $value ) );
				if ( '' === $v ) { return array( '', '' ); }
				if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) { return array( $v, '' ); }
				if ( preg_match( '/^\d{8}$/', $v ) ) { return array( $v, '' ); } // ACF date_picker storage (Ymd)
				return array( '', 'must be a date like 2026-10-04' );
			case 'datetime':
				$v = trim( self::scalar( $value ) );
				if ( '' === $v ) { return array( '', '' ); }
				$v = str_replace( 'T', ' ', $v );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v ) && strtotime( $v ) ) { return array( strlen( $v ) === 16 ? $v . ':00' : $v, '' ); }
				return array( '', 'must be a date and time like 2026-10-04 14:30' );
			case 'select':
			case 'status':
				$v = self::scalar( $value );
				if ( '' === $v || ! $field['choices'] ) { return array( sanitize_text_field( $v ), '' ); }
				if ( array_key_exists( $v, $field['choices'] ) ) { return array( $v, '' ); }
				foreach ( $field['choices'] as $k => $label ) { if ( 0 === strcasecmp( (string) $label, $v ) ) { return array( (string) $k, '' ); } }
				return array( '', 'must be one of: ' . implode( ', ', array_keys( $field['choices'] ) ) );
			case 'multiselect':
				$items = self::list_of( $value );
				if ( $field['choices'] ) {
					foreach ( $items as $i ) { if ( ! array_key_exists( $i, $field['choices'] ) ) { return array( array(), '"' . $i . '" is not an allowed choice' ); } }
				}
				return array( array_values( array_map( 'sanitize_text_field', $items ) ), '' );
			case 'checkbox':
			case 'boolean':
				$v = is_bool( $value ) ? ( $value ? '1' : '0' ) : strtolower( trim( self::scalar( $value ) ) );
				if ( in_array( $v, array( '1', 'yes', 'true', 'on', 'y' ), true ) ) { return array( 1, '' ); }
				if ( in_array( $v, array( '', '0', 'no', 'false', 'off', 'n' ), true ) ) { return array( 0, '' ); }
				return array( 0, 'must be yes or no' );
			case 'json':
				if ( is_array( $value ) ) { return array( $value, '' ); }
				$v = trim( self::scalar( $value ) );
				if ( '' === $v ) { return array( array(), '' ); }
				$decoded = json_decode( $v, true );
				return JSON_ERROR_NONE === json_last_error() ? array( $decoded, '' ) : array( null, 'must be valid JSON (' . json_last_error_msg() . ')' );
			case 'media':
				return self::object_ref( $value, 'attachment' );
			case 'post':
				return self::object_ref( $value, $field['target'] ?: 'any' );
			case 'user':
				$id = absint( is_array( $value ) ? reset( $value ) : $value );
				if ( ! $id ) { return array( 0, '' ); }
				return get_userdata( $id ) ? array( $id, '' ) : array( 0, 'user #' . $id . ' does not exist' );
			case 'term':
				return self::term_ref( $value, $field['target'] );
			case 'media_list':
			case 'post_list':
			case 'user_list':
			case 'term_list':
				$single = array( 'media_list' => 'media', 'post_list' => 'post', 'user_list' => 'user', 'term_list' => 'term' )[ $type ];
				$out = array();
				foreach ( self::list_of( $value ) as $item ) {
					list( $v, $err ) = self::sanitize( array_merge( $field, array( 'type' => $single, 'sanitize' => null ) ), $item );
					if ( $err ) { return array( array(), $err ); }
					if ( $v ) { $out[] = $v; }
				}
				return array( array_values( array_unique( $out ) ), '' );
			case 'group':
				return self::structure( $field, $value, false );
			case 'repeater':
				return self::structure( $field, $value, true );
			case 'flexible':
				return self::flexible( $field, $value );
			case 'readonly':
				return array( $value, 'is read-only' );
		}
		return array( sanitize_text_field( self::scalar( $value ) ), '' );
	}

	private static function scalar( $v ) {
		if ( is_array( $v ) || is_object( $v ) ) { return wp_json_encode( $v ); }
		return (string) $v;
	}

	/** Accept arrays, JSON arrays, or comma/semicolon/newline separated strings. */
	public static function list_of( $value ) {
		if ( is_array( $value ) ) { return array_values( array_filter( $value, static function ( $v ) { return '' !== $v && null !== $v; } ) ); }
		$v = trim( (string) $value );
		if ( '' === $v ) { return array(); }
		if ( '[' === $v[0] ) { $d = json_decode( $v, true ); if ( is_array( $d ) ) { return array_values( $d ); } }
		return array_values( array_filter( array_map( 'trim', preg_split( '/[,;\n]+/', $v ) ), 'strlen' ) );
	}

	private static function object_ref( $value, $post_type ) {
		$raw = is_array( $value ) ? ( $value['id'] ?? $value['ID'] ?? reset( $value ) ) : $value;
		$raw = trim( (string) $raw );
		if ( '' === $raw || '0' === $raw ) { return array( 0, '' ); }
		$id = ctype_digit( $raw ) ? (int) $raw : ( 'attachment' === $post_type ? (int) attachment_url_to_postid( $raw ) : (int) url_to_postid( $raw ) );
		if ( ! $id || ! get_post( $id ) ) { return array( 0, '"' . $raw . '" is not an existing item on this site' ); }
		if ( 'any' !== $post_type && get_post_type( $id ) !== $post_type ) { return array( 0, '#' . $id . ' is not a ' . $post_type ); }
		return array( $id, '' );
	}

	private static function term_ref( $value, $taxonomy ) {
		$raw = is_array( $value ) ? ( $value['term_id'] ?? reset( $value ) ) : $value;
		$raw = trim( (string) $raw );
		if ( '' === $raw ) { return array( 0, '' ); }
		$term = ctype_digit( $raw ) ? get_term( (int) $raw, $taxonomy ?: '' ) : ( $taxonomy ? ( get_term_by( 'slug', sanitize_title( $raw ), $taxonomy ) ?: get_term_by( 'name', $raw, $taxonomy ) ) : null );
		if ( ! $term || is_wp_error( $term ) ) { return array( 0, '"' . $raw . '" is not an existing ' . ( $taxonomy ?: 'term' ) ); }
		return array( (int) $term->term_id, '' );
	}

	private static function structure( array $field, $value, $rows ) {
		if ( is_string( $value ) ) { $d = json_decode( $value, true ); $value = is_array( $d ) ? $d : array(); }
		$value = is_array( $value ) ? $value : array();
		$clean_row = static function ( $row ) use ( $field ) {
			$row = is_array( $row ) ? $row : array();
			$out = array();
			foreach ( $field['sub_fields'] as $key => $sub ) {
				if ( ! array_key_exists( $key, $row ) ) { continue; }
				list( $v, $err ) = self::sanitize( $sub, $row[ $key ] );
				if ( $err ) { return array( null, $sub['label'] . ' ' . $err ); }
				$out[ $key ] = $v;
			}
			return array( $out, '' );
		};
		if ( ! $rows ) { return $clean_row( $value ); }
		$out = array();
		foreach ( array_values( $value ) as $i => $row ) {
			list( $v, $err ) = $clean_row( $row );
			if ( $err ) { return array( array(), 'row ' . ( $i + 1 ) . ': ' . $err ); }
			$out[] = $v;
		}
		if ( null !== $field['min'] && count( $out ) < (int) $field['min'] ) { return array( array(), 'needs at least ' . (int) $field['min'] . ' rows' ); }
		if ( null !== $field['max'] && (int) $field['max'] > 0 && count( $out ) > (int) $field['max'] ) { return array( array(), 'allows at most ' . (int) $field['max'] . ' rows' ); }
		return array( $out, '' );
	}

	private static function flexible( array $field, $value ) {
		if ( is_string( $value ) ) { $d = json_decode( $value, true ); $value = is_array( $d ) ? $d : array(); }
		$out = array();
		foreach ( array_values( (array) $value ) as $i => $row ) {
			$layout = is_array( $row ) ? (string) ( $row['acf_fc_layout'] ?? '' ) : '';
			if ( ! isset( $field['layouts'][ $layout ] ) ) { return array( array(), 'row ' . ( $i + 1 ) . ': unknown layout "' . $layout . '"' ); }
			list( $clean, $err ) = self::structure( array_merge( $field, array( 'sub_fields' => $field['layouts'][ $layout ]['sub_fields'] ) ), $row, false );
			if ( $err ) { return array( array(), 'row ' . ( $i + 1 ) . ': ' . $err ); }
			$out[] = array( 'acf_fc_layout' => $layout ) + $clean;
		}
		return array( $out, '' );
	}

	/**
	 * Comparable representation for change detection. Empty forms ('' / null / [] / a group whose
	 * values are all empty / 0 for a reference field) compare equal, so spreadsheet round trips of
	 * empty ACF fields are not reported as changes.
	 *
	 * @param mixed      $v     Value.
	 * @param array|null $field Field definition, when known (enables type-aware empties).
	 */
	public static function normalize_for_compare( $v, $field = null ) {
		$type = is_array( $field ) ? ( $field['type'] ?? '' ) : '';
		if ( in_array( $type, array( 'media', 'post', 'user', 'term' ), true ) && ( null === $v || '' === $v || 0 === $v || '0' === $v || false === $v ) ) { return ''; }
		// ACF returns false for empty repeaters/relationships; only boolean fields treat false as a value.
		if ( false === $v && '' !== $type && ! in_array( $type, array( 'boolean', 'checkbox' ), true ) ) { return ''; }
		if ( is_array( $v ) ) {
			$o = array();
			foreach ( $v as $k => $x ) {
				$sub = null;
				if ( 'group' === $type ) { $sub = $field['sub_fields'][ $k ] ?? null; }
				elseif ( 'repeater' === $type ) { $sub = array( 'type' => 'group', 'sub_fields' => $field['sub_fields'] ); }
				elseif ( 'flexible' === $type && is_array( $x ) ) { $sub = array( 'type' => 'group', 'sub_fields' => $field['layouts'][ $x['acf_fc_layout'] ?? '' ]['sub_fields'] ?? array() ); }
				$o[ $k ] = self::normalize_for_compare( $x, $sub );
			}
			return self::all_empty( $o ) ? '' : $o;
		}
		if ( is_bool( $v ) ) { return $v ? '1' : '0'; }
		if ( null === $v ) { return ''; }
		return trim( (string) $v );
	}

	private static function all_empty( $v ) {
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) { if ( 'acf_fc_layout' !== $k && ! self::all_empty( $x ) ) { return false; } }
			return true;
		}
		return '' === $v;
	}

	public static function same( $a, $b, $field = null ) {
		return wp_json_encode( self::normalize_for_compare( $a, $field ) ) === wp_json_encode( self::normalize_for_compare( $b, $field ) );
	}

	public static function hash( $v ) { return substr( md5( (string) wp_json_encode( self::normalize_for_compare( $v ) ) ), 0, 12 ); }
}
