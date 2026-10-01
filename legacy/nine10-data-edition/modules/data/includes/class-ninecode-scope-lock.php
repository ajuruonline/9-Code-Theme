<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Signed export boundary for AI / CSV / Excel round trips.
 * The lock protects *where* an import may write, not the values themselves.
 */
class NineCode_Scope_Lock {
    const VERSION = 1;

    public static function build( $records, $kind, $scope_name, $scope = array() ) {
        $manifest = array(
            'version' => self::VERSION,
            'site' => untrailingslashit( home_url( '/' ) ),
            'kind' => sanitize_key( $kind ),
            'scope_name' => sanitize_key( $scope_name ),
            'record_ids' => array(),
            'acf_fields' => array(),
            'meta_keys' => array(),
            'taxonomies' => array(),
            'workspace' => ! empty( $scope['workspace_export'] ),
        );
        foreach ( (array) $records as $record ) {
            $object = (array) ( $record['object'] ?? array() );
            $id = absint( $object['id'] ?? 0 );
            if ( $id ) { $manifest['record_ids'][] = $id; }
            foreach ( (array) ( $record['fields'] ?? array() ) as $field ) {
                $key = sanitize_key( $field['key'] ?? '' );
                if ( $key ) { $manifest['acf_fields'][] = $key; }
            }
            foreach ( (array) ( $record['meta'] ?? array() ) as $meta ) {
                $key = trim( (string) ( $meta['key'] ?? '' ) );
                if ( $key ) { $manifest['meta_keys'][] = $key; }
            }
            foreach ( array_keys( (array) ( $record['taxonomies'] ?? array() ) ) as $taxonomy ) {
                $taxonomy = sanitize_key( $taxonomy );
                if ( $taxonomy ) { $manifest['taxonomies'][] = $taxonomy; }
            }
        }
        foreach ( array( 'record_ids', 'acf_fields', 'meta_keys', 'taxonomies' ) as $key ) {
            $manifest[ $key ] = array_values( array_unique( $manifest[ $key ] ) );
            sort( $manifest[ $key ], SORT_NATURAL );
        }
        $guard = $manifest;
        $guard['signature'] = self::signature( $manifest );
        return $guard;
    }

    public static function verify( $guard, $records ) {
        if ( ! is_array( $guard ) || empty( $guard['signature'] ) ) {
            return new WP_Error( 'scope_lock_missing', 'This protected export is missing its 9Code Scope Lock.' );
        }
        $signature = (string) $guard['signature'];
        $manifest = $guard;
        unset( $manifest['signature'] );
        if ( ! hash_equals( self::signature( $manifest ), $signature ) ) {
            return new WP_Error( 'scope_lock_changed', '9Code Scope Lock was changed. Export a fresh file and edit only the data values.' );
        }
        if ( untrailingslashit( home_url( '/' ) ) !== (string) ( $manifest['site'] ?? '' ) ) {
            return new WP_Error( 'scope_lock_site', 'This protected export belongs to a different WordPress site.' );
        }

        $kind = sanitize_key( $manifest['kind'] ?? '' );
        $scope_name = sanitize_key( $manifest['scope_name'] ?? '' );
        $allowed_ids = array_fill_keys( array_map( 'absint', (array) ( $manifest['record_ids'] ?? array() ) ), true );
        $allowed_fields = array_fill_keys( array_map( 'sanitize_key', (array) ( $manifest['acf_fields'] ?? array() ) ), true );
        $allowed_meta = array_fill_keys( array_map( 'strval', (array) ( $manifest['meta_keys'] ?? array() ) ), true );
        $allowed_tax = array_fill_keys( array_map( 'sanitize_key', (array) ( $manifest['taxonomies'] ?? array() ) ), true );
        $seen = array();

        foreach ( (array) $records as $record ) {
            $object = (array) ( $record['object'] ?? array() );
            $id = absint( $object['id'] ?? 0 );
            if ( ! $id || ! isset( $allowed_ids[ $id ] ) ) {
                return new WP_Error( 'scope_lock_record', 'Import blocked: the file contains a record that was not in the protected export.' );
            }
            if ( isset( $seen[ $id ] ) ) {
                return new WP_Error( 'scope_lock_duplicate', 'Import blocked: the same record appears more than once in this protected file.' );
            }
            $seen[ $id ] = true;
            if ( $kind !== sanitize_key( $object['kind'] ?? '' ) ) {
                return new WP_Error( 'scope_lock_kind', 'Import blocked: a record type was changed outside the protected export scope.' );
            }
            $record_scope = 'term' === $kind ? sanitize_key( $object['taxonomy'] ?? '' ) : sanitize_key( $object['post_type'] ?? '' );
            if ( $scope_name && $scope_name !== $record_scope ) {
                return new WP_Error( 'scope_lock_type', 'Import blocked: the data type/taxonomy was changed outside the protected export scope.' );
            }
            foreach ( (array) ( $record['fields'] ?? array() ) as $field ) {
                $key = sanitize_key( $field['key'] ?? '' );
                if ( ! $key || ! isset( $allowed_fields[ $key ] ) ) {
                    return new WP_Error( 'scope_lock_field', 'Import blocked: the file contains an ACF field that was not exported.' );
                }
            }
            foreach ( (array) ( $record['meta'] ?? array() ) as $meta ) {
                $key = trim( (string) ( $meta['key'] ?? '' ) );
                if ( ! $key || ! isset( $allowed_meta[ $key ] ) ) {
                    return new WP_Error( 'scope_lock_meta', 'Import blocked: the file contains a plugin/meta field that was not exported.' );
                }
            }
            foreach ( array_keys( (array) ( $record['taxonomies'] ?? array() ) ) as $taxonomy ) {
                $taxonomy = sanitize_key( $taxonomy );
                if ( ! $taxonomy || ! isset( $allowed_tax[ $taxonomy ] ) ) {
                    return new WP_Error( 'scope_lock_taxonomy', 'Import blocked: the file contains a taxonomy column that was not exported.' );
                }
            }
        }
        return true;
    }

    public static function encode( $guard ) {
        return base64_encode( wp_json_encode( $guard, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
    }

    public static function decode( $encoded ) {
        $json = base64_decode( trim( (string) $encoded ), true );
        if ( false === $json ) { return null; }
        $guard = json_decode( $json, true );
        return is_array( $guard ) ? $guard : null;
    }

    private static function signature( $manifest ) {
        return hash_hmac( 'sha256', wp_json_encode( self::canonicalize( $manifest ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), wp_salt( 'auth' ) );
    }

    private static function canonicalize( $value ) {
        if ( ! is_array( $value ) ) { return $value; }
        if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
            ksort( $value, SORT_STRING );
        }
        foreach ( $value as $key => $item ) { $value[ $key ] = self::canonicalize( $item ); }
        return $value;
    }
}
