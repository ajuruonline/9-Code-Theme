<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Lightweight WordPress-native field definitions for 9 Post Editor.
 *
 * This is intentionally much smaller than a general field-builder plugin. Definitions live in
 * one option, values live as registered post meta, and WordPress remains the source of truth.
 */
final class Nine_Post_Manager_Fields {
    private static $instance = null;
    const OPTION = 'npm9_native_field_schemas_v1';
    const MAX_FIELDS_PER_TYPE = 60;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_fields' ], 30 );
        add_action( 'wp_ajax_npm9_native_field_save_schema', [ $this, 'ajax_save_schema' ] );
        add_action( 'wp_ajax_npm9_native_field_delete_schema', [ $this, 'ajax_delete_schema' ] );
    }

    public function definitions_for_post_type( $post_type ) {
        $all = get_option( self::OPTION, [] );
        if ( ! is_array( $all ) ) { $all = []; }
        $list = isset( $all[ $post_type ] ) && is_array( $all[ $post_type ] ) ? $all[ $post_type ] : [];
        usort( $list, static function( $a, $b ) {
            $ga = (string) ( $a['group'] ?? '' ); $gb = (string) ( $b['group'] ?? '' );
            if ( $ga !== $gb ) { return strcasecmp( $ga, $gb ); }
            return intval( $a['order'] ?? 0 ) <=> intval( $b['order'] ?? 0 );
        } );
        return $list;
    }


    public function definition_for_key( $post_type, $key ) {
        foreach ( $this->definitions_for_post_type( $post_type ) as $definition ) {
            if ( ( $definition['key'] ?? '' ) === $key ) { return $definition; }
        }
        return null;
    }

    public function register_fields() {
        if ( ! function_exists( 'register_post_meta' ) ) { return; }
        $all = get_option( self::OPTION, [] );
        if ( ! is_array( $all ) ) { return; }
        foreach ( $all as $post_type => $definitions ) {
            if ( ! post_type_exists( $post_type ) || ! is_array( $definitions ) ) { continue; }
            foreach ( $definitions as $definition ) {
                $def = $this->normalize_definition( $definition, false );
                if ( ! $def ) { continue; }
                $type = $this->meta_type( $def['type'] );
                register_post_meta( $post_type, $def['key'], [
                    'type' => $type,
                    'single' => true,
                    'default' => $this->default_value( $def['type'] ),
                    'description' => $def['label'],
                    'show_in_rest' => ! empty( $def['rest_visible'] ),
                    'sanitize_callback' => function( $value ) use ( $def ) { return $this->sanitize_value( $value, $def ); },
                    'auth_callback' => static function( $allowed, $meta_key, $post_id ) {
                        return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
                    },
                ] );
            }
        }
    }

    private function meta_type( $type ) {
        if ( in_array( $type, [ 'image','file','integer' ], true ) ) { return 'integer'; }
        if ( 'number' === $type ) { return 'number'; }
        if ( 'boolean' === $type ) { return 'boolean'; }
        return 'string';
    }

    private function default_value( $type ) {
        if ( in_array( $type, [ 'image','file','integer' ], true ) ) { return 0; }
        if ( 'number' === $type ) { return 0; }
        if ( 'boolean' === $type ) { return false; }
        return '';
    }

    private function allowed_types() {
        return [ 'text','textarea','richtext','url','email','number','integer','boolean','select','image','file' ];
    }

    private function normalize_definition( $raw, $create_key = true ) {
        if ( ! is_array( $raw ) ) { return null; }
        $label = sanitize_text_field( $raw['label'] ?? '' );
        if ( '' === $label ) { return null; }
        $type = sanitize_key( $raw['type'] ?? 'text' );
        if ( ! in_array( $type, $this->allowed_types(), true ) ) { $type = 'text'; }
        $key = sanitize_key( $raw['key'] ?? '' );
        if ( $create_key && '' === $key ) { $key = sanitize_key( $label ); }
        if ( 0 !== strpos( $key, 'npm9f_' ) ) { $key = 'npm9f_' . ltrim( $key, '_' ); }
        if ( 'npm9f_' === $key ) { return null; }
        $choices = [];
        $raw_choices = $raw['choices'] ?? [];
        if ( is_string( $raw_choices ) ) { $raw_choices = preg_split( '/\r\n|\r|\n|,/', $raw_choices ); }
        foreach ( (array) $raw_choices as $choice ) {
            $choice = sanitize_text_field( $choice );
            if ( '' !== $choice ) { $choices[] = $choice; }
        }
        $choices = array_slice( array_values( array_unique( $choices ) ), 0, 100 );
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'group' => sanitize_text_field( $raw['group'] ?? 'General' ) ?: 'General',
            'choices' => $choices,
            'required' => ! empty( $raw['required'] ),
            'ai_fill' => array_key_exists( 'ai_fill', $raw ) ? ! empty( $raw['ai_fill'] ) : true,
            'rest_visible' => ! empty( $raw['rest_visible'] ),
            'instructions' => sanitize_textarea_field( $raw['instructions'] ?? '' ),
            'order' => intval( $raw['order'] ?? 0 ),
        ];
    }

    public function schema_for_ui( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) { return [ 'fields' => [], 'canManage' => false ]; }
        $fields = [];
        foreach ( $this->definitions_for_post_type( $post->post_type ) as $def ) {
            $value = get_post_meta( $post_id, $def['key'], true );
            $item = $def;
            $item['value'] = $value;
            if ( in_array( $def['type'], [ 'image','file' ], true ) ) {
                $id = absint( $value );
                $item['media'] = [
                    'id' => $id,
                    'url' => $id ? (string) wp_get_attachment_url( $id ) : '',
                    'thumb' => $id && 0 === strpos( (string) get_post_mime_type( $id ), 'image/' ) ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '',
                    'title' => $id ? (string) get_the_title( $id ) : '',
                ];
            }
            $fields[] = $item;
        }
        return [ 'fields' => $fields, 'canManage' => current_user_can( 'manage_options' ) ];
    }

    public function save_values( $post_id, array $values ) {
        $post = get_post( $post_id );
        if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) { return new WP_Error( 'permission', 'You do not have permission to edit these fields.' ); }
        $defs = [];
        foreach ( $this->definitions_for_post_type( $post->post_type ) as $def ) { $defs[ $def['key'] ] = $def; }
        foreach ( $defs as $key => $def ) {
            if ( ! array_key_exists( $key, $values ) ) { continue; }
            $value = $this->sanitize_value( $values[ $key ], $def );
            if ( ! empty( $def['required'] ) && ( '' === $value || null === $value || false === $value || ( is_numeric( $value ) && 0 === intval( $value ) && in_array( $def['type'], [ 'image','file' ], true ) ) ) ) {
                return new WP_Error( 'required_field', $def['label'] . ' is required.' );
            }
            update_post_meta( $post_id, $key, $value );
        }
        return true;
    }

    private function sanitize_value( $value, array $def ) {
        switch ( $def['type'] ) {
            case 'boolean': return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            case 'number': return is_numeric( $value ) ? (float) $value : 0;
            case 'integer': return intval( $value );
            case 'image':
            case 'file':
                $id = absint( $value );
                if ( ! $id || 'attachment' !== get_post_type( $id ) ) { return 0; }
                if ( 'image' === $def['type'] && 0 !== strpos( (string) get_post_mime_type( $id ), 'image/' ) ) { return 0; }
                return $id;
            case 'url': return esc_url_raw( (string) $value );
            case 'email': return sanitize_email( (string) $value );
            case 'textarea': return sanitize_textarea_field( (string) $value );
            case 'richtext': return wp_kses_post( (string) $value );
            case 'select':
                $value = sanitize_text_field( (string) $value );
                return ! empty( $def['choices'] ) && ! in_array( $value, $def['choices'], true ) ? '' : $value;
            default: return sanitize_text_field( (string) $value );
        }
    }

    public function merge_definitions_from_backup( $post_type, array $definitions ) {
        if ( ! post_type_exists( $post_type ) || ! current_user_can( 'manage_options' ) ) { return 0; }
        $all = get_option( self::OPTION, [] ); if ( ! is_array( $all ) ) { $all = []; }
        $list = isset( $all[ $post_type ] ) && is_array( $all[ $post_type ] ) ? $all[ $post_type ] : [];
        $keys = [];
        foreach ( $list as $existing ) { if ( ! empty( $existing['key'] ) ) $keys[ $existing['key'] ] = true; }
        $added = 0;
        foreach ( array_slice( $definitions, 0, self::MAX_FIELDS_PER_TYPE ) as $raw ) {
            $def = $this->normalize_definition( $raw, false );
            if ( ! $def || isset( $keys[ $def['key'] ] ) || count( $list ) >= self::MAX_FIELDS_PER_TYPE ) { continue; }
            $def['order'] = count( $list ) + 1; $list[] = $def; $keys[ $def['key'] ] = true; $added++;
        }
        if ( $added ) { $all[ $post_type ] = array_values( $list ); update_option( self::OPTION, $all, false ); }
        return $added;
    }

    public function ajax_save_schema() {
        check_ajax_referer( 'npm9_action', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 ); }
        $post = get_post( $post_id );
        $raw = isset( $_POST['definition'] ) ? json_decode( wp_unslash( $_POST['definition'] ), true ) : [];
        $def = $this->normalize_definition( $raw, true );
        if ( ! $def ) { wp_send_json_error( [ 'message' => 'Enter a valid field name.' ] ); }
        $all = get_option( self::OPTION, [] ); if ( ! is_array( $all ) ) { $all = []; }
        $list = isset( $all[ $post->post_type ] ) && is_array( $all[ $post->post_type ] ) ? $all[ $post->post_type ] : [];
        $found = false;
        foreach ( $list as &$existing ) { if ( ( $existing['key'] ?? '' ) === $def['key'] ) { $existing = $def; $found = true; break; } }
        unset( $existing );
        if ( ! $found ) {
            if ( count( $list ) >= self::MAX_FIELDS_PER_TYPE ) { wp_send_json_error( [ 'message' => 'This post type already has the maximum number of lightweight 9PM fields.' ] ); }
            $def['order'] = count( $list ) + 1; $list[] = $def;
        }
        $all[ $post->post_type ] = array_values( $list );
        update_option( self::OPTION, $all, false );
        wp_send_json_success( [ 'message' => 'Native field saved.', 'key' => $def['key'] ] );
    }

    public function ajax_delete_schema() {
        check_ajax_referer( 'npm9_action', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 ); }
        $post = get_post( $post_id );
        $all = get_option( self::OPTION, [] ); if ( ! is_array( $all ) ) { $all = []; }
        $list = isset( $all[ $post->post_type ] ) && is_array( $all[ $post->post_type ] ) ? $all[ $post->post_type ] : [];
        $all[ $post->post_type ] = array_values( array_filter( $list, static function( $def ) use ( $key ) { return ( $def['key'] ?? '' ) !== $key; } ) );
        update_option( self::OPTION, $all, false );
        wp_send_json_success( [ 'message' => 'Field definition removed. Existing post values were left intact for safety.' ] );
    }
}
