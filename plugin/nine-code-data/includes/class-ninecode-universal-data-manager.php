<?php
/**
 * Universal Data Manager bridge for the Nine Code team.
 *
 * This is deliberately provider-neutral: plugins register data with WordPress,
 * and Data Manager discovers those registrations instead of needing a bespoke
 * screen for every app. Providers may add richer schemas through the filter
 * ninecode_data_manager_providers.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'NineCode_Universal_Data_Manager' ) ) {
class NineCode_Universal_Data_Manager {
    const VERSION = '1.0.0';
    const CAP = 'manage_ninecode_data';

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) { $instance = new self(); }
        return $instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
        add_action( 'rest_api_init', array( $this, 'rest_api' ) );
        add_action( 'admin_post_ninecode_universal_export', array( $this, 'handle_export' ) );
        add_action( 'admin_post_ninecode_universal_import', array( $this, 'handle_import' ) );
        add_filter( 'ninecode_data_manager_schema', array( $this, 'schema' ) );
        add_filter( 'ninecode_data_manager_providers', array( $this, 'default_providers' ) );
        add_filter( 'ninecode_data_editable_post_types', array( $this, 'editable_post_types' ), 20 );
    }

    public function default_providers( $providers ) {
        $providers = is_array( $providers ) ? $providers : array();
        $providers['nine-code'] = array( 'id' => 'nine-code', 'label' => 'Nine Code Team', 'status' => 'active' );
        if ( post_type_exists( 'os_publication' ) ) {
            $providers['open-scholar'] = array(
                'id' => 'open-scholar', 'label' => 'Open Scholar', 'status' => 'active',
                'post_types' => array( 'os_publication' ), 'data_manager' => 'universal',
            );
        }
        foreach ( get_post_types( array(), 'objects' ) as $type => $object ) {
            if ( false !== stripos( $type, 'conference' ) || false !== stripos( $type, 'event' ) ) {
                $providers['conference-' . sanitize_key( $type )] = array(
                    'id' => 'conference-' . sanitize_key( $type ), 'label' => $object->labels->name,
                    'status' => 'active', 'post_types' => array( $type ), 'data_manager' => 'universal',
                );
            }
        }
        return apply_filters( 'ninecode_data_manager_providers_discovered', $providers );
    }

    public function editable_post_types( $types ) {
        foreach ( get_post_types( array(), 'objects' ) as $name => $object ) {
            if ( 'attachment' === $name || 'revision' === $name || 'nav_menu_item' === $name ) { continue; }
            if ( ! empty( $object->show_ui ) || ! empty( $object->public ) || ! empty( $object->publicly_queryable ) ) {
                $types[ $name ] = $object;
            }
        }
        return $types;
    }

    public function schema( $schema = array() ) {
        $schema = is_array( $schema ) ? $schema : array();
        $post_types = array();
        foreach ( get_post_types( array(), 'objects' ) as $name => $object ) {
            if ( 'attachment' === $name || 'revision' === $name || 'nav_menu_item' === $name ) { continue; }
            $meta = get_registered_meta_keys( 'post', $name );
            $post_types[ $name ] = array(
                'key' => $name,
                'label' => $object->labels->name,
                'public' => (bool) $object->public,
                'show_ui' => (bool) $object->show_ui,
                'supports' => array_values( (array) get_all_post_type_supports( $name ) ),
                'taxonomies' => array_values( get_object_taxonomies( $name, 'names' ) ),
                'fields' => $this->meta_schema( $name, $meta ),
            );
        }
        $taxonomies = array();
        foreach ( get_taxonomies( array(), 'objects' ) as $name => $object ) {
            if ( 'nav_menu' === $name || 'link_category' === $name || 'post_format' === $name ) { continue; }
            $taxonomies[ $name ] = array(
                'key' => $name, 'label' => $object->labels->name,
                'object_types' => array_values( (array) $object->object_type ),
                'hierarchical' => (bool) $object->hierarchical,
            );
        }
        $out = array(
            'format' => 'ninecode-universal-data-schema',
            'version' => self::VERSION,
            'generated_at' => current_time( 'c' ),
            'providers' => apply_filters( 'ninecode_data_manager_providers', array() ),
            'post_types' => $post_types,
            'taxonomies' => $taxonomies,
        );
        if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
            $out['acf'] = array();
            foreach ( (array) acf_get_field_groups() as $group ) {
                $fields = array();
                foreach ( (array) acf_get_fields( $group ) as $field ) { $fields[] = $this->acf_field_schema( $field ); }
                $out['acf'][] = array( 'key' => $group['key'] ?? '', 'title' => $group['title'] ?? '', 'fields' => $fields );
            }
        }
        return $out;
    }

    private function meta_schema( $post_type, $registered ) {
        $fields = array();
        foreach ( (array) $registered as $key => $def ) {
            $fields[ $key ] = array(
                'key' => $key, 'label' => ucwords( str_replace( array( '_', '-' ), ' ', ltrim( $key, '_' ) ) ),
                'type' => $def['type'] ?? 'string', 'single' => ! empty( $def['single'] ),
                'writable' => $this->writable_meta( $key ), 'source' => 'registered-meta',
            );
        }
        return $fields;
    }

    private function acf_field_schema( $field ) {
        $out = array( 'key' => $field['key'] ?? '', 'name' => $field['name'] ?? '', 'label' => $field['label'] ?? '', 'type' => $field['type'] ?? 'text', 'required' => ! empty( $field['required'] ) );
        foreach ( array( 'sub_fields', 'layouts', 'choices' ) as $key ) { if ( ! empty( $field[ $key ] ) ) { $out[ $key ] = $field[ $key ]; } }
        return $out;
    }

    private function writable_meta( $key ) {
        $key = (string) $key;
        $protected = array( '_edit_lock', '_edit_last', '_thumbnail_id', '_wp_old_slug' );
        if ( in_array( $key, $protected, true ) ) { return false; }
        return (bool) apply_filters( 'ninecode_data_manager_writable_meta', true, $key );
    }

    private function package( $post_type, $ids = array() ) {
        $ids = array_filter( array_map( 'absint', (array) $ids ) );
        if ( ! $ids ) {
            $ids = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
        }
        $records = array();
        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( ! $post || $post->post_type !== $post_type ) { continue; }
            $meta = array();
            foreach ( (array) get_post_meta( $id ) as $key => $values ) {
                $meta[ $key ] = count( $values ) > 1 ? array_map( 'maybe_unserialize', $values ) : maybe_unserialize( $values[0] );
            }
            $terms = array();
            foreach ( get_object_taxonomies( $post_type, 'names' ) as $taxonomy ) {
                $terms[ $taxonomy ] = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'slugs' ) );
            }
            $record = array(
                'id' => (int) $id, 'post_type' => $post_type, 'title' => $post->post_title,
                'content' => $post->post_content, 'excerpt' => $post->post_excerpt,
                'slug' => $post->post_name, 'status' => $post->post_status,
                'date' => $post->post_date, 'parent' => (int) $post->post_parent,
                'meta' => $meta, 'taxonomies' => $terms,
            );
            if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'get_fields' ) ) {
                $record['acf'] = array();
                foreach ( (array) acf_get_field_groups( array( 'post_id' => $id ) ) as $group ) {
                    foreach ( (array) acf_get_fields( $group ) as $field ) {
                        if ( ! empty( $field['name'] ) ) { $record['acf'][ $field['name'] ] = get_field( $field['name'], $id, false ); }
                    }
                }
            }
            $records[] = $record;
        }
        return array(
            'format' => 'ninecode-universal-data-package', 'version' => self::VERSION,
            'generated_at' => current_time( 'c' ), 'scope' => array( 'post_type' => $post_type ),
            'schema' => $this->schema(), 'records' => $records,
            'ai_contract' => array(
                'editable_paths' => array( 'records[].title', 'records[].content', 'records[].excerpt', 'records[].status', 'records[].meta', 'records[].acf', 'records[].taxonomies' ),
                'protected_paths' => array( 'records[].id', 'records[].post_type', 'schema', 'ai_contract' ),
                'rules' => array( 'Never create or delete records.', 'Keep IDs and field keys unchanged.', 'Return the complete package as valid JSON.', 'Use publish status only when explicitly requested.' ),
            ),
        );
    }

    public function admin_menu() {
        add_submenu_page( 'ninecode-acf-data-engine', 'Universal Data', 'Universal Data', self::CAP, 'ninecode-universal-data', array( $this, 'render_page' ) );
    }

    public function render_page() {
        if ( ! current_user_can( self::CAP ) ) { return; }
        $types = $this->editable_post_types( array() );
        $selected = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
        if ( ! isset( $types[ $selected ] ) ) { $selected = key( $types ); }
        echo '<div class="wrap"><h1>Nine Code Universal Data Manager</h1><p>One workspace for ACF, Open Scholar, Conference, and every registered plugin data type.</p>';
        echo '<p><strong>Providers:</strong> ' . esc_html( implode( ', ', wp_list_pluck( $this->default_providers( array() ), 'label' ) ) ) . '</p>';
        echo '<form method="get"><input type="hidden" name="page" value="ninecode-universal-data"><select name="post_type">';
        foreach ( $types as $name => $object ) { echo '<option value="' . esc_attr( $name ) . '"' . selected( $selected, $name, false ) . '>' . esc_html( $object->labels->name . ' (' . $name . ')' ) . '</option>'; }
        echo '</select> <button class="button">Load</button></form>';
        echo '<hr><h2>Export / AI round trip</h2><p>Export a complete package, edit it with AI or a spreadsheet, validate it, then import it below. A recovery snapshot is created before changes.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ninecode_universal_export"><input type="hidden" name="post_type" value="' . esc_attr( $selected ) . '">' . wp_nonce_field( 'ninecode_universal_export', '_wpnonce', true, false ) . '<button class="button button-primary">Download JSON package</button></form>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:16px"><input type="hidden" name="action" value="ninecode_universal_import"><input type="hidden" name="post_type" value="' . esc_attr( $selected ) . '">' . wp_nonce_field( 'ninecode_universal_import', '_wpnonce', true, false );
        echo '<textarea name="package" rows="18" style="width:100%;max-width:1100px;font-family:monospace" placeholder="Paste the complete JSON package here"></textarea><p><label><input type="checkbox" name="preview" value="1" checked> Validate / preview only</label> &nbsp; <label><input type="checkbox" name="allow_publish" value="1"> Allow status changes / publishing</label></p><button class="button button-primary">Validate / Import package</button></form></div>';
    }

    public function handle_export() {
        $this->require_cap();
        check_admin_referer( 'ninecode_universal_export' );
        $type = sanitize_key( wp_unslash( $_POST['post_type'] ?? '' ) );
        if ( ! post_type_exists( $type ) ) { wp_die( 'Unknown post type.' ); }
        $json = wp_json_encode( $this->package( $type ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        nocache_headers(); header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $type . '-ninecode-data.json' ) ); echo $json; exit;
    }

    public function handle_import() {
        $this->require_cap();
        check_admin_referer( 'ninecode_universal_import' );
        $raw = wp_unslash( $_POST['package'] ?? '' ); $package = json_decode( $raw, true );
        if ( ! is_array( $package ) || empty( $package['records'] ) ) { wp_die( 'Invalid or empty Data Manager package.' ); }
        $preview = ! empty( $_POST['preview'] ); $allow_publish = ! empty( $_POST['allow_publish'] ) && current_user_can( 'publish_posts' );
        $result = $this->apply( $package, $preview, $allow_publish );
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
        $message = $result['changed'] . ' record(s) updated; ' . $result['errors'] . ' error(s).' . ( $preview ? ' Preview only — nothing was changed.' : '' );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-universal-data', 'ncu_message' => rawurlencode( $message ) ), admin_url( 'admin.php' ) ) ); exit;
    }

    private function apply( $package, $preview, $allow_publish ) {
        $result = array( 'changed' => 0, 'errors' => 0, 'messages' => array() );
        $backup = array();
        foreach ( (array) $package['records'] as $record ) {
            $id = absint( $record['id'] ?? 0 ); $post = $id ? get_post( $id ) : null;
            if ( ! $post || $post->post_type !== sanitize_key( $record['post_type'] ?? '' ) ) { $result['errors']++; $result['messages'][] = 'Record #' . $id . ' does not match its post type.'; continue; }
            $before = array( 'post' => array( 'title' => $post->post_title, 'content' => $post->post_content, 'excerpt' => $post->post_excerpt, 'status' => $post->post_status ), 'meta' => get_post_meta( $id ), 'terms' => array() );
            foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $tax ) { $before['terms'][ $tax ] = wp_get_object_terms( $id, $tax, array( 'fields' => 'slugs' ) ); }
            $backup[] = array( 'id' => $id, 'before' => $before );
            $after = $record;
            if ( ! $preview ) {
                $post_update = array( 'ID' => $id, 'post_title' => sanitize_text_field( $after['title'] ?? $post->post_title ), 'post_content' => wp_kses_post( $after['content'] ?? $post->post_content ), 'post_excerpt' => sanitize_textarea_field( $after['excerpt'] ?? $post->post_excerpt ) );
                if ( $allow_publish && isset( $after['status'] ) ) { $post_update['post_status'] = sanitize_key( $after['status'] ); }
                wp_update_post( $post_update );
                foreach ( (array) ( $after['meta'] ?? array() ) as $key => $value ) {
                    if ( ! $this->writable_meta( $key ) ) { continue; }
                    if ( is_array( $value ) ) { delete_post_meta( $id, $key ); foreach ( $value as $item ) { add_post_meta( $id, $key, $this->clean_value( $item ) ); } }
                    else { update_post_meta( $id, $key, $this->clean_value( $value ) ); }
                }
                if ( function_exists( 'update_field' ) ) { foreach ( (array) ( $after['acf'] ?? array() ) as $key => $value ) { update_field( $key, $value, $id ); } }
                foreach ( (array) ( $after['taxonomies'] ?? array() ) as $tax => $slugs ) {
                    if ( taxonomy_exists( $tax ) && is_object_in_taxonomy( $post->post_type, $tax ) ) { wp_set_object_terms( $id, array_map( 'sanitize_title', (array) $slugs ), $tax, false ); }
                }
                $result['changed']++;
            }
        }
        if ( ! $preview ) { update_option( 'ninecode_universal_data_last_backup', array( 'time' => current_time( 'mysql' ), 'user_id' => get_current_user_id(), 'records' => $backup ), false ); }
        return $result;
    }

    private function clean_value( $value ) {
        if ( is_array( $value ) ) { return array_map( array( $this, 'clean_value' ), $value ); }
        return is_string( $value ) ? wp_kses_post( $value ) : $value;
    }

    private function require_cap() { if ( ! current_user_can( self::CAP ) ) { wp_die( 'You do not have permission to use Universal Data Manager.' ); } }

    public function rest_api() {
        register_rest_route( 'ninecode/v1', '/data/schema', array( 'methods' => 'GET', 'permission_callback' => array( $this, 'rest_permission' ), 'callback' => function() { return rest_ensure_response( $this->schema() ); } ) );
        register_rest_route( 'ninecode/v1', '/data/export/(?P<post_type>[a-zA-Z0-9_-]+)', array( 'methods' => 'GET', 'permission_callback' => array( $this, 'rest_permission' ), 'callback' => function( $request ) { return rest_ensure_response( $this->package( sanitize_key( $request['post_type'] ) ) ); } ) );
        register_rest_route( 'ninecode/v1', '/data/import', array( 'methods' => 'POST', 'permission_callback' => array( $this, 'rest_permission' ), 'callback' => function( $request ) { $p = $request->get_json_params(); return rest_ensure_response( $this->apply( $p, ! empty( $p['preview'] ), ! empty( $p['allow_publish'] ) ) ); } ) );
    }

    public function rest_permission() { return current_user_can( self::CAP ); }
}
}
NineCode_Universal_Data_Manager::instance();
