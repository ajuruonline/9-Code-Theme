<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Nine10_Form {
    private static $instance = null;
    const SHORTCODE = 'nine10_form';

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
        add_action( 'init', array( $this, 'register_storage' ) );
        add_action( 'admin_post_nine10_form_submit', array( $this, 'handle_submit' ) );
        add_action( 'admin_post_nopriv_nine10_form_submit', array( $this, 'handle_submit' ) );
        add_action( 'admin_post_nine10_form_save_definition', array( $this, 'handle_save_definition' ) );
        add_action( 'admin_post_nine10_form_delete_definition', array( $this, 'handle_delete_definition' ) );
        add_action( 'admin_post_nine10_form_download_responses', array( $this, 'handle_download_responses' ) );
        add_action( 'admin_post_nine10_form_download_category_responses', array( $this, 'handle_download_category_responses' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_form_attachment_metaboxes' ) );
        add_action( 'save_post', array( $this, 'save_form_attachment' ), 30, 2 );
        add_filter( 'the_content', array( $this, 'inject_attached_form' ), 30 );
        add_filter( 'npm9_9cf_field_contract', array( $this, 'enrich_9cf_form_field' ) );
    }

    public static function render_admin_page_static() {
        self::instance()->render_admin_page();
    }

    public function register_storage() {
        register_post_type( 'nine10_form_def', array(
            'labels' => array( 'name' => '9 Data Forms', 'singular_name' => '9 Data Form' ),
            'public' => false, 'show_ui' => false, 'show_in_rest' => false,
            'supports' => array( 'title' ),
        ) );
        register_post_type( 'nine10_form_response', array(
            'labels' => array( 'name' => '9 Data Form Responses', 'singular_name' => '9 Data Form Response' ),
            'public' => false, 'show_ui' => false, 'show_in_rest' => false,
            'supports' => array( 'title' ),
        ) );
        if ( taxonomy_exists( 'category' ) ) {
            register_taxonomy_for_object_type( 'category', 'nine10_form_def' );
            register_taxonomy_for_object_type( 'category', 'nine10_form_response' );
        }
        $form_types = get_post_types( array( 'show_ui' => true ), 'objects' );
        if ( function_exists( 'nine10_data_filter_internal_post_types' ) ) { $form_types = nine10_data_filter_internal_post_types( $form_types, 'form-meta-registration' ); }
        $form_types = apply_filters( 'nine10_data_form_post_types', $form_types, 'register-meta' );
        foreach ( is_array( $form_types ) ? $form_types : array() as $type => $obj ) {
            if ( in_array( $type, array( 'attachment', 'nine10_form_def', 'nine10_form_response' ), true ) ) { continue; }
            register_post_meta( $type, '_nine10_attached_form_id', array( 'type'=>'integer', 'single'=>true, 'show_in_rest'=>true, 'sanitize_callback'=>'absint', 'auth_callback'=>static function( $allowed, $meta_key, $post_id ) { return $post_id && current_user_can( 'edit_post', $post_id ); } ) );
            register_post_meta( $type, '_nine10_attached_form_position', array( 'type'=>'string', 'single'=>true, 'show_in_rest'=>true, 'sanitize_callback'=>array( __CLASS__, 'sanitize_form_position' ), 'auth_callback'=>static function( $allowed, $meta_key, $post_id ) { return $post_id && current_user_can( 'edit_post', $post_id ); } ) );
        }
    }

    public static function sanitize_form_position( $value ) {
        $value = sanitize_key( (string) $value );
        return in_array( $value, array( 'none', 'before', 'after' ), true ) ? $value : 'none';
    }

    /** Make Form Manager fields human-selectable inside Post Editor / 9CF instead of raw meta IDs. */
    public function enrich_9cf_form_field( $field ) {
        if ( ! is_array( $field ) || empty( $field['id'] ) ) { return $field; }
        if ( 'meta:_nine10_attached_form_id' === $field['id'] ) {
            $enum = array( '0' );
            $labels = array( '0' => 'No attached form' );
            $forms = get_posts( array(
                'post_type' => 'nine10_form_def', 'post_status' => 'publish', 'posts_per_page' => -1,
                'orderby' => 'title', 'order' => 'ASC', 'meta_key' => '_nine10_form_active', 'meta_value' => 1,
            ) );
            foreach ( $forms as $form ) {
                $id = (string) absint( $form->ID );
                $enum[] = $id;
                $labels[ $id ] = $form->post_title ? $form->post_title : 'Form #' . $id;
            }
            $current = (string) absint( $field['value'] ?? 0 );
            if ( ! in_array( $current, $enum, true ) ) { $enum[] = $current; $labels[ $current ] = 'Unavailable form #' . $current; }
            $field['label'] = '9 Data Form';
            $field['type'] = 'text';
            $field['value'] = $current;
            $field['ai_fill'] = false;
            $field['hint'] = 'Select a managed Form Manager form for this post/page. Use the placement field to show it before/after content, or leave placement manual for shortcode/block placement.';
            $field['validation'] = array_merge( isset( $field['validation'] ) && is_array( $field['validation'] ) ? $field['validation'] : array(), array( 'enum' => $enum, 'enum_labels' => $labels ) );
        } elseif ( 'meta:_nine10_attached_form_position' === $field['id'] ) {
            $field['label'] = '9 Data Form Placement';
            $field['type'] = 'text';
            $field['ai_fill'] = false;
            $field['validation'] = array_merge( isset( $field['validation'] ) && is_array( $field['validation'] ) ? $field['validation'] : array(), array(
                'enum' => array( 'none', 'before', 'after' ),
                'enum_labels' => array( 'none' => 'Selected only / manual placement', 'before' => 'Before content', 'after' => 'After content' ),
            ) );
            $field['hint'] = 'Choose automatic placement, or use manual placement when a shortcode/block controls the exact location.';
        }
        return $field;
    }

    /** Prevent spreadsheet formula execution when untrusted responses are opened from CSV. */
    public static function csv_safe_cell( $value ) {
        if ( ! is_scalar( $value ) && null !== $value ) { $value = function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value ); }
        $value = (string) $value;
        $probe = ltrim( $value );
        if ( '' !== $probe && preg_match( '/^[=+\-@]/', $probe ) ) { return "'" . $value; }
        return $value;
    }

    public static function normalize_field_rows( $rows ) {
        $allowed = array( 'text', 'email', 'textarea', 'number', 'url', 'select', 'checkbox' );
        $out = array();
        $used = array();
        foreach ( (array) $rows as $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
            if ( '' === $label ) { continue; }
            $type = isset( $row['type'] ) ? strtolower( trim( (string) $row['type'] ) ) : 'text';
            if ( ! in_array( $type, $allowed, true ) ) { $type = 'text'; }
            $base = function_exists( 'sanitize_key' ) ? sanitize_key( str_replace( ' ', '_', strtolower( $label ) ) ) : preg_replace( '/[^a-z0-9_]/', '', str_replace( ' ', '_', strtolower( $label ) ) );
            if ( '' === $base ) { $base = 'field'; }
            $key = $base; $n = 2;
            while ( isset( $used[ $key ] ) ) { $key = $base . '_' . $n++; }
            $used[ $key ] = true;
            $options = array();
            if ( 'select' === $type ) {
                $raw = isset( $row['options'] ) ? $row['options'] : array();
                if ( is_string( $raw ) ) { $raw = preg_split( '/[|,\r\n]+/', $raw ); }
                foreach ( (array) $raw as $option ) {
                    $option = trim( (string) $option );
                    if ( '' !== $option ) { $options[] = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $option ) : strip_tags( $option ); }
                }
                $options = array_values( array_unique( $options ) );
            }
            $out[] = array( 'key' => $key, 'label' => function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $label ) : strip_tags( $label ), 'type' => $type, 'required' => ! empty( $row['required'] ), 'options' => $options );
        }
        return $out;
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'You do not have permission to use Form Manager.', 'nine55-ultron-data' ) ); }
        $view = isset( $_GET['form_view'] ) ? sanitize_key( wp_unslash( $_GET['form_view'] ) ) : 'create';
        if ( ! in_array( $view, array( 'create', 'manage', 'responses', 'category_responses' ), true ) ) { $view = 'create'; }
        $edit_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        $base = admin_url( 'admin.php?page=nine10-form-manager' );
        echo '<div class="wrap ultron955-wrap nine10-form-admin">';
        echo '<header class="ultron955-hero"><div><p class="ultron955-kicker">9 DATA MANAGER</p><h1>Form Manager</h1><p>Create reusable forms, manage them, collect responses and download response data. Existing post/data/author form shortcodes remain supported.</p></div><div class="ultron955-version">FORM</div></header>';
        echo '<nav class="nine10-manager-tabs"><a class="' . ( 'create' === $view ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'form_view', 'create', $base ) ) . '">Create Form</a><a class="' . ( 'manage' === $view ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'form_view', 'manage', $base ) ) . '">Manage Forms</a><a class="' . ( 'responses' === $view ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'form_view', 'responses', $base ) ) . '">Responses</a><a class="' . ( 'category_responses' === $view ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'form_view', 'category_responses', $base ) ) . '">Category Responses</a></nav>';
        if ( ! empty( $_GET['nine10_form_saved'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>Form saved.</p></div>'; }
        if ( 'create' === $view ) { $this->render_definition_editor( $edit_id ); }
        elseif ( 'responses' === $view ) { $this->render_responses_admin(); }
        elseif ( 'category_responses' === $view ) { $this->render_category_responses_admin(); }
        else { $this->render_manage_forms(); }
        echo '</div>';
    }

    private function render_definition_editor( $form_id = 0 ) {
        $form = $form_id ? get_post( $form_id ) : null;
        if ( $form && 'nine10_form_def' !== $form->post_type ) { $form = null; $form_id = 0; }
        $fields = $form_id ? (array) get_post_meta( $form_id, '_nine10_form_fields', true ) : array();
        $active = $form_id ? (bool) get_post_meta( $form_id, '_nine10_form_active', true ) : true;
        echo '<section class="ultron955-section nine10-form-builder"><h2>' . ( $form_id ? 'Edit Form' : 'Create Form' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-nine10-form-builder>';
        echo '<input type="hidden" name="action" value="nine10_form_save_definition"><input type="hidden" name="form_id" value="' . intval( $form_id ) . '">'; wp_nonce_field( 'nine10_form_save_definition_' . intval( $form_id ) );
        echo '<label><span>Form name</span><input type="text" name="form_name" required value="' . esc_attr( $form ? $form->post_title : '' ) . '" placeholder="e.g. Conference Registration"></label>';
        echo '<label class="nine10-inline-check"><input type="checkbox" name="form_active" value="1" ' . checked( $active, true, false ) . '> Active / accepting responses</label>';
        $selected_categories = $form_id ? wp_get_post_terms( $form_id, 'category', array( 'fields'=>'ids' ) ) : array();
        $categories = get_terms( array( 'taxonomy'=>'category', 'hide_empty'=>false, 'orderby'=>'name' ) );
        echo '<fieldset class="nine10-form-categories"><legend>Response categories</legend><p class="description">Optional. Responses inherit these categories and also inherit the categories of the post/page where the form is submitted.</p><div class="nine10-category-checks">';
        if ( is_array( $categories ) && ! is_wp_error( $categories ) ) { foreach ( $categories as $category ) { echo '<label><input type="checkbox" name="form_categories[]" value="' . intval( $category->term_id ) . '" ' . checked( in_array( (int) $category->term_id, array_map( 'intval', (array) $selected_categories ), true ), true, false ) . '> ' . esc_html( $category->name ) . '</label>'; } }
        echo '</div></fieldset>';
        echo '<div class="nine10-builder-head"><div><h3>Fields</h3><p>Add the information you want the form to collect.</p></div><button type="button" class="button" data-nine10-add-field>Add Field</button></div>';
        echo '<div class="nine10-field-builder" data-nine10-field-list>';
        if ( ! $fields ) { $fields = array( array( 'label' => 'Name', 'type' => 'text', 'required' => true, 'options' => array() ), array( 'label' => 'Email', 'type' => 'email', 'required' => true, 'options' => array() ) ); }
        foreach ( $fields as $i => $field ) { $this->render_field_builder_row( $i, $field ); }
        echo '</div><button class="button button-primary button-hero" type="submit">Save Form</button></form></section>';
    }

    private function render_field_builder_row( $i, $field ) {
        $type = isset( $field['type'] ) ? $field['type'] : 'text';
        $options = isset( $field['options'] ) && is_array( $field['options'] ) ? implode( ' | ', $field['options'] ) : '';
        echo '<div class="nine10-field-row" data-nine10-field-row><span class="dashicons dashicons-menu"></span><label>Label<input name="fields[' . intval( $i ) . '][label]" value="' . esc_attr( $field['label'] ?? '' ) . '" required></label><label>Type<select name="fields[' . intval( $i ) . '][type]">';
        foreach ( array( 'text'=>'Text', 'email'=>'Email', 'textarea'=>'Long text', 'number'=>'Number', 'url'=>'URL', 'select'=>'Select', 'checkbox'=>'Checkbox' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $type, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></label><label>Options<input name="fields[' . intval( $i ) . '][options]" value="' . esc_attr( $options ) . '" placeholder="For Select: Yes | No | Maybe"></label><label class="nine10-inline-check"><input type="checkbox" name="fields[' . intval( $i ) . '][required]" value="1" ' . checked( ! empty( $field['required'] ), true, false ) . '> Required</label><button type="button" class="button-link-delete" data-nine10-remove-field>Remove</button></div>';
    }

    private function render_manage_forms() {
        $forms = get_posts( array( 'post_type' => 'nine10_form_def', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1, 'orderby' => 'modified', 'order' => 'DESC' ) );
        echo '<section class="ultron955-section"><div class="nine10-builder-head"><div><h2>Manage Forms</h2><p>Forms can be selected on any editable post/page/CPT through the 9 Data Form box, or placed manually with the shortcode in Gutenberg, widgets and shortcode-capable builders.</p></div><a class="button button-primary" href="' . esc_url( add_query_arg( 'form_view', 'create', admin_url( 'admin.php?page=nine10-form-manager' ) ) ) . '">Create Form</a></div>';
        if ( ! $forms ) { echo '<p>No managed forms yet.</p></section>'; return; }
        echo '<div class="nine10-form-list">';
        foreach ( $forms as $form ) {
            $count = count( get_posts( array( 'post_type'=>'nine10_form_response', 'post_status'=>'private', 'posts_per_page'=>-1, 'fields'=>'ids', 'meta_key'=>'_nine10_form_id', 'meta_value'=>$form->ID ) ) );
            $shortcode = '[nine10_form form_id="' . intval( $form->ID ) . '"]';
            $edit = add_query_arg( array( 'form_view'=>'create', 'form_id'=>$form->ID ), admin_url( 'admin.php?page=nine10-form-manager' ) );
            $responses = add_query_arg( array( 'form_view'=>'responses', 'form_id'=>$form->ID ), admin_url( 'admin.php?page=nine10-form-manager' ) );
            $delete = wp_nonce_url( admin_url( 'admin-post.php?action=nine10_form_delete_definition&form_id=' . intval( $form->ID ) ), 'nine10_form_delete_definition_' . intval( $form->ID ) );
            $category_names = wp_get_post_terms( $form->ID, 'category', array( 'fields'=>'names' ) );
            $category_text = is_array( $category_names ) && $category_names ? implode( ', ', $category_names ) : 'All / source categories';
            echo '<article><div><strong>' . esc_html( $form->post_title ) . '</strong><span>' . intval( $count ) . ' response(s) · ' . esc_html( $category_text ) . '</span><code>' . esc_html( $shortcode ) . '</code></div><div><a class="button" href="' . esc_url( $edit ) . '">Edit</a><a class="button" href="' . esc_url( $responses ) . '">Responses</a><a class="button-link-delete" href="' . esc_url( $delete ) . '" onclick="return confirm(\'Delete this form? Stored responses will remain available for audit until manually removed.\')">Delete</a></div></article>';
        }
        echo '</div></section>';
    }

    private function render_responses_admin() {
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        $forms = get_posts( array( 'post_type'=>'nine10_form_def', 'post_status'=>array('publish','draft'), 'posts_per_page'=>-1, 'orderby'=>'title', 'order'=>'ASC' ) );
        echo '<section class="ultron955-section"><div class="nine10-builder-head"><div><h2>Responses</h2><p>Review submissions and download them as CSV.</p></div>';
        if ( $form_id ) { echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nine10_form_download_responses&form_id=' . intval( $form_id ) ), 'nine10_form_download_responses_' . intval( $form_id ) ) ) . '">Download CSV</a>'; }
        echo '</div><form method="get" class="nine10-response-filter"><input type="hidden" name="page" value="nine10-form-manager"><input type="hidden" name="form_view" value="responses"><label>Form<select name="form_id"><option value="0">All forms</option>';
        foreach ( $forms as $form ) { echo '<option value="' . intval( $form->ID ) . '" ' . selected( $form_id, $form->ID, false ) . '>' . esc_html( $form->post_title ) . '</option>'; }
        echo '</select></label><button class="button">Filter</button></form>';
        $args = array( 'post_type'=>'nine10_form_response', 'post_status'=>'private', 'posts_per_page'=>100, 'orderby'=>'date', 'order'=>'DESC' );
        if ( $form_id ) { $args['meta_key'] = '_nine10_form_id'; $args['meta_value'] = $form_id; }
        $rows = get_posts( $args );
        $this->render_response_rows( $rows );
        echo '</section>';
    }

    private function render_category_responses_admin() {
        $category_id = isset( $_GET['category_id'] ) ? absint( $_GET['category_id'] ) : 0;
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        $categories = get_terms( array( 'taxonomy'=>'category', 'hide_empty'=>false, 'orderby'=>'name' ) );
        $forms = get_posts( array( 'post_type'=>'nine10_form_def', 'post_status'=>array('publish','draft'), 'posts_per_page'=>-1, 'orderby'=>'title', 'order'=>'ASC' ) );
        echo '<section class="ultron955-section"><div class="nine10-builder-head"><div><h2>Category Responses</h2><p>Review form submissions by WordPress category, including categories inherited from the source post/page and categories assigned directly to the form.</p></div>';
        if ( $category_id ) {
            $download = wp_nonce_url( add_query_arg( array( 'action'=>'nine10_form_download_category_responses', 'category_id'=>$category_id, 'form_id'=>$form_id ), admin_url( 'admin-post.php' ) ), 'nine10_form_download_category_responses_' . $category_id . '_' . $form_id );
            echo '<a class="button" href="' . esc_url( $download ) . '">Download Category CSV</a>';
        }
        echo '</div><form method="get" class="nine10-response-filter"><input type="hidden" name="page" value="nine10-form-manager"><input type="hidden" name="form_view" value="category_responses"><label>Category<select name="category_id"><option value="0">Choose category</option>';
        if ( is_array( $categories ) && ! is_wp_error( $categories ) ) { foreach ( $categories as $category ) { echo '<option value="' . intval( $category->term_id ) . '" ' . selected( $category_id, $category->term_id, false ) . '>' . esc_html( $category->name ) . '</option>'; } }
        echo '</select></label><label>Form<select name="form_id"><option value="0">All forms</option>';
        foreach ( $forms as $form ) { echo '<option value="' . intval( $form->ID ) . '" ' . selected( $form_id, $form->ID, false ) . '>' . esc_html( $form->post_title ) . '</option>'; }
        echo '</select></label><button class="button">Filter</button></form>';
        if ( ! $category_id ) { echo '<p>Select a category to review its responses.</p></section>'; return; }
        $args = array( 'post_type'=>'nine10_form_response', 'post_status'=>'private', 'posts_per_page'=>100, 'orderby'=>'date', 'order'=>'DESC', 'tax_query'=>array( array( 'taxonomy'=>'category', 'field'=>'term_id', 'terms'=>array( $category_id ) ) ) );
        if ( $form_id ) { $args['meta_query'] = array( array( 'key'=>'_nine10_form_id', 'value'=>$form_id, 'compare'=>'=' ) ); }
        $rows = get_posts( $args );
        $this->render_response_rows( $rows );
        echo '</section>';
    }

    private function render_response_rows( $rows ) {
        if ( ! $rows ) { echo '<p>No responses found.</p>'; return; }
        echo '<div class="nine10-response-list">';
        foreach ( $rows as $row ) {
            $data = (array) get_post_meta( $row->ID, '_nine10_response_data', true );
            $fid = absint( get_post_meta( $row->ID, '_nine10_form_id', true ) );
            $source_id = absint( get_post_meta( $row->ID, '_nine10_source_post_id', true ) );
            $cats = wp_get_post_terms( $row->ID, 'category', array( 'fields'=>'names' ) );
            echo '<article><div><strong>' . esc_html( get_the_title( $fid ) ?: 'Deleted form' ) . '</strong><span>' . esc_html( get_the_date( 'M j, Y g:i a', $row ) ) . ( $source_id ? ' · Source: ' . esc_html( get_the_title( $source_id ) ?: '#' . $source_id ) : '' ) . ( is_array( $cats ) && $cats ? ' · ' . esc_html( implode( ', ', $cats ) ) : '' ) . '</span></div><dl>';
            foreach ( $data as $key => $value ) { echo '<dt>' . esc_html( $this->friendly_label( $key ) ) . '</dt><dd>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</dd>'; }
            echo '</dl></article>';
        }
        echo '</div>';
    }

    public function handle_save_definition() {
        if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Permission denied.' ); }
        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        check_admin_referer( 'nine10_form_save_definition_' . $form_id );
        $name = isset( $_POST['form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['form_name'] ) ) : '';
        if ( '' === $name ) { wp_die( 'Form name is required.' ); }
        $fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? self::normalize_field_rows( wp_unslash( $_POST['fields'] ) ) : array();
        if ( ! $fields ) { wp_die( 'Add at least one form field.' ); }
        $postarr = array( 'post_type'=>'nine10_form_def', 'post_status'=>'publish', 'post_title'=>$name );
        if ( $form_id ) { if ( 'nine10_form_def' !== get_post_type( $form_id ) || ! current_user_can( 'edit_post', $form_id ) ) { wp_die( 'Form unavailable.' ); } $postarr['ID'] = $form_id; $saved = wp_update_post( $postarr, true ); }
        else { $saved = wp_insert_post( $postarr, true ); }
        if ( is_wp_error( $saved ) ) { wp_die( esc_html( $saved->get_error_message() ) ); }
        update_post_meta( $saved, '_nine10_form_fields', $fields ); update_post_meta( $saved, '_nine10_form_active', ! empty( $_POST['form_active'] ) ? 1 : 0 );
        $category_ids = isset( $_POST['form_categories'] ) && is_array( $_POST['form_categories'] ) ? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['form_categories'] ) ) ) ) : array();
        if ( taxonomy_exists( 'category' ) ) { wp_set_post_terms( $saved, $category_ids, 'category', false ); }
        wp_safe_redirect( add_query_arg( array( 'page'=>'nine10-form-manager', 'form_view'=>'manage', 'nine10_form_saved'=>1 ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_delete_definition() {
        if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Permission denied.' ); }
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; check_admin_referer( 'nine10_form_delete_definition_' . $form_id );
        if ( $form_id && 'nine10_form_def' === get_post_type( $form_id ) && current_user_can( 'delete_post', $form_id ) ) { wp_trash_post( $form_id ); }
        wp_safe_redirect( add_query_arg( array( 'page'=>'nine10-form-manager', 'form_view'=>'manage' ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_download_responses() {
        if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Permission denied.' ); }
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; check_admin_referer( 'nine10_form_download_responses_' . $form_id );
        if ( ! $form_id || 'nine10_form_def' !== get_post_type( $form_id ) ) { wp_die( 'Form unavailable.' ); }
        $fields = (array) get_post_meta( $form_id, '_nine10_form_fields', true ); $keys = array_map( static function( $f ) { return $f['key']; }, $fields );
        $rows = get_posts( array( 'post_type'=>'nine10_form_response', 'post_status'=>'private', 'posts_per_page'=>-1, 'orderby'=>'date', 'order'=>'ASC', 'meta_key'=>'_nine10_form_id', 'meta_value'=>$form_id ) );
        nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="nine10-form-' . intval( $form_id ) . '-responses-' . gmdate( 'Y-m-d' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' ); fputcsv( $out, array_merge( array( 'Response ID', 'Submitted' ), $keys ), ',', '"', '' );
        foreach ( $rows as $row ) { $data = (array) get_post_meta( $row->ID, '_nine10_response_data', true ); $line = array( self::csv_safe_cell( $row->ID ), self::csv_safe_cell( $row->post_date ) ); foreach ( $keys as $key ) { $value = $data[ $key ] ?? ''; $line[] = self::csv_safe_cell( $value ); } fputcsv( $out, $line, ',', '"', '' ); }
        fclose( $out ); exit;
    }

    public function handle_download_category_responses() {
        if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Permission denied.' ); }
        $category_id = isset( $_GET['category_id'] ) ? absint( $_GET['category_id'] ) : 0;
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        check_admin_referer( 'nine10_form_download_category_responses_' . $category_id . '_' . $form_id );
        if ( ! $category_id || ! term_exists( $category_id, 'category' ) ) { wp_die( 'Category unavailable.' ); }
        $args = array( 'post_type'=>'nine10_form_response', 'post_status'=>'private', 'posts_per_page'=>-1, 'orderby'=>'date', 'order'=>'ASC', 'tax_query'=>array( array( 'taxonomy'=>'category', 'field'=>'term_id', 'terms'=>array( $category_id ) ) ) );
        if ( $form_id ) { $args['meta_query'] = array( array( 'key'=>'_nine10_form_id', 'value'=>$form_id, 'compare'=>'=' ) ); }
        $rows = get_posts( $args );
        $this->download_response_rows_csv( $rows, 'nine10-category-' . $category_id . '-responses-' . gmdate( 'Y-m-d' ) . '.csv' );
    }

    private function download_response_rows_csv( $rows, $filename ) {
        $keys = array();
        foreach ( (array) $rows as $row ) { foreach ( array_keys( (array) get_post_meta( $row->ID, '_nine10_response_data', true ) ) as $key ) { $keys[ sanitize_key( $key ) ] = true; } }
        $keys = array_keys( $keys ); sort( $keys );
        nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array_merge( array( 'Response ID', 'Submitted', 'Form', 'Source Post', 'Categories' ), $keys ), ',', '"', '' );
        foreach ( (array) $rows as $row ) {
            $data = (array) get_post_meta( $row->ID, '_nine10_response_data', true );
            $fid = absint( get_post_meta( $row->ID, '_nine10_form_id', true ) );
            $source_id = absint( get_post_meta( $row->ID, '_nine10_source_post_id', true ) );
            $cats = wp_get_post_terms( $row->ID, 'category', array( 'fields'=>'names' ) );
            $line = array( self::csv_safe_cell( $row->ID ), self::csv_safe_cell( $row->post_date ), self::csv_safe_cell( get_the_title( $fid ) ), self::csv_safe_cell( $source_id ? get_the_title( $source_id ) : '' ), self::csv_safe_cell( is_array( $cats ) ? implode( ', ', $cats ) : '' ) );
            foreach ( $keys as $key ) { $line[] = self::csv_safe_cell( $data[ $key ] ?? '' ); }
            fputcsv( $out, $line, ',', '"', '' );
        }
        fclose( $out ); exit;
    }

    public function shortcode( $atts ) {
        if ( defined( 'NINE55_ULTRON_DATA_URL' ) ) { wp_enqueue_style( 'nine10-data-edition-forms', NINE55_ULTRON_DATA_URL . 'assets/admin.css', array(), defined( 'NINE55_ULTRON_DATA_VERSION' ) ? NINE55_ULTRON_DATA_VERSION : '9.10.2' ); }
        $atts = shortcode_atts( array( 'mode' => 'contact', 'post_type' => 'post', 'post_id' => 0, 'form_id' => 0, 'status' => 'draft', 'button' => '' ), $atts, self::SHORTCODE );
        $mode = sanitize_key( $atts['mode'] );
        if ( ! in_array( $mode, array( 'create', 'edit', 'data', 'contact' ), true ) ) { $mode = 'contact'; }
        $post_id = absint( $atts['post_id'] );
        if ( ! $post_id && is_singular() ) { $post_id = get_queried_object_id(); }
        $notice = isset( $_GET['nine10_form_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['nine10_form_notice'] ) ) : '';

        ob_start();
        echo '<div class="nine10-front-form">';
        if ( $notice ) { echo '<div class="nine10-form-notice">' . esc_html( $notice ) . '</div>'; }
        $managed_form_id = absint( $atts['form_id'] );
        if ( $managed_form_id ) { $this->render_managed_form( $managed_form_id, $atts, $post_id ); }
        elseif ( 'create' === $mode ) { $this->render_post_form( 0, sanitize_key( $atts['post_type'] ), $atts ); }
        elseif ( 'edit' === $mode ) { $this->render_post_form( $post_id, '', $atts ); }
        elseif ( 'data' === $mode ) { $this->render_data_form( $post_id, $atts ); }
        else { $this->render_contact_form( $post_id, $atts ); }
        echo '</div>';
        return ob_get_clean();
    }

    private function form_open( $mode, $post_id = 0, $post_type = '' ) {
        echo '<form class="nine10-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="nine10_form_submit">';
        echo '<input type="hidden" name="nine10_mode" value="' . esc_attr( $mode ) . '">';
        echo '<input type="hidden" name="nine10_post_id" value="' . intval( $post_id ) . '">';
        echo '<input type="hidden" name="nine10_post_type" value="' . esc_attr( $post_type ) . '">';
        echo '<input type="hidden" name="nine10_return" value="' . esc_url( $this->current_url() ) . '">';
        wp_nonce_field( 'nine10_form_' . $mode . '_' . intval( $post_id ) . '_' . $post_type, 'nine10_nonce' );
    }

    private function render_post_form( $post_id, $post_type, $atts ) {
        $post = $post_id ? get_post( $post_id ) : null;
        if ( $post_id && ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) ) { echo '<p>You cannot edit this record.</p>'; return; }
        if ( ! $post_id ) {
            $obj = get_post_type_object( $post_type );
            $cap = $obj && ! empty( $obj->cap->create_posts ) ? $obj->cap->create_posts : 'edit_posts';
            if ( ! is_user_logged_in() || ! $obj || 'attachment' === $post_type || ! current_user_can( $cap ) ) { echo '<p>Sign in with permission to create this record type.</p>'; return; }
        }
        $mode = $post ? 'edit' : 'create';
        $this->form_open( $mode, $post_id, $post ? $post->post_type : $post_type );
        echo '<label><span>Title</span><input required type="text" name="post_title" value="' . esc_attr( $post ? $post->post_title : '' ) . '"></label>';
        echo '<label><span>Summary</span><textarea name="post_excerpt" rows="3">' . esc_textarea( $post ? $post->post_excerpt : '' ) . '</textarea></label>';
        echo '<label><span>Content</span><textarea name="post_content" rows="10">' . esc_textarea( $post ? $post->post_content : '' ) . '</textarea></label>';
        $status = $post ? $post->post_status : sanitize_key( $atts['status'] );
        $type_obj = get_post_type_object( $post ? $post->post_type : $post_type );
        $publish_cap = $type_obj && ! empty( $type_obj->cap->publish_posts ) ? $type_obj->cap->publish_posts : 'publish_posts';
        $status_options = array( 'draft' => 'Draft', 'pending' => 'Pending review' );
        if ( current_user_can( $publish_cap ) || ( $post && 'publish' === $post->post_status ) ) { $status_options['publish'] = 'Published'; }
        if ( $post && ! isset( $status_options[ $post->post_status ] ) ) {
            $status_labels = array( 'private' => 'Private (keep current)', 'future' => 'Scheduled (keep current)' );
            $status_options[ $post->post_status ] = isset( $status_labels[ $post->post_status ] ) ? $status_labels[ $post->post_status ] : ucfirst( $post->post_status ) . ' (keep current)';
        }
        if ( ! isset( $status_options[ $status ] ) ) { $status = 'draft'; }
        echo '<label><span>Save as</span><select name="post_status">';
        foreach ( $status_options as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></label>';
        echo '<button class="button button-primary" type="submit">' . esc_html( $atts['button'] ?: ( $post ? 'Save changes' : 'Create post' ) ) . '</button></form>';
    }

    private function render_data_form( $post_id, $atts ) {
        if ( ! $post_id || ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { echo '<p>You cannot edit data for this record.</p>'; return; }
        if ( ! class_exists( 'NineCode_Data_Exporter' ) ) { echo '<p>Data Engine is not available.</p>'; return; }
        $record = ( new NineCode_Data_Exporter() )->export_post_record( $post_id );
        if ( ! is_array( $record ) ) { echo '<p>Data could not be loaded.</p>'; return; }
        $this->form_open( 'data', $post_id, get_post_type( $post_id ) );
        echo '<p class="nine10-form-help">ACF values, safe plugin fields and existing taxonomy assignments below are loaded from Data Engine. Complex values use JSON. New categories/tags are created in 9 Category Manager, not here.</p>';
        foreach ( (array) ( $record['fields'] ?? array() ) as $field ) { $this->render_value_input( 'acf[' . sanitize_key( $field['key'] ?? $field['name'] ?? '' ) . ']', $field['label'] ?? $field['name'] ?? 'Field', $field['value'] ?? '', $field['type'] ?? '' ); }
        foreach ( (array) ( $record['meta'] ?? array() ) as $meta ) { $this->render_value_input( 'meta[' . sanitize_key( $meta['key'] ?? '' ) . ']', $meta['label'] ?? $meta['key'] ?? 'Field', $meta['value'] ?? '', $meta['type'] ?? '' ); }
        foreach ( (array) ( $record['taxonomies'] ?? array() ) as $taxonomy => $items ) {
            $taxonomy = sanitize_key( $taxonomy );
            $tax_obj = get_taxonomy( $taxonomy );
            $assign_cap = $tax_obj && ! empty( $tax_obj->cap->assign_terms ) ? $tax_obj->cap->assign_terms : '';
            if ( ! $tax_obj || ! $assign_cap || ! current_user_can( $assign_cap ) ) { continue; }
            $slugs = array(); foreach ( (array) $items as $item ) { if ( ! empty( $item['slug'] ) ) { $slugs[] = sanitize_title( $item['slug'] ); } }
            echo '<label><span>' . esc_html( $this->friendly_label( $taxonomy ) ) . ' <small>(existing term slugs only)</small></span><input type="text" name="tax[' . esc_attr( $taxonomy ) . ']" value="' . esc_attr( implode( ', ', $slugs ) ) . '" placeholder="example-term, another-term"></label>';
        }
        echo '<button class="button button-primary" type="submit">' . esc_html( $atts['button'] ?: 'Save data safely' ) . '</button></form>';
    }

    private function render_value_input( $name, $label, $value, $type ) {
        $complex = is_array( $value ) || is_object( $value ) || in_array( $type, array( 'repeater', 'flexible_content', 'gallery', 'relationship', 'group' ), true );
        echo '<label><span>' . esc_html( $label ) . ( $type ? ' <small>(' . esc_html( $type ) . ')</small>' : '' ) . '</span>';
        if ( $complex ) {
            echo '<textarea rows="5" name="' . esc_attr( $name ) . '" data-nine10-json="1">' . esc_textarea( wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</textarea>';
        } elseif ( 'textarea' === $type || 'wysiwyg' === $type ) {
            echo '<textarea rows="5" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
        } else {
            $input_type = in_array( $type, array( 'number', 'range' ), true ) ? 'number' : ( 'email' === $type ? 'email' : ( 'url' === $type ? 'url' : 'text' ) );
            echo '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '">';
        }
        echo '</label>';
    }

    private function render_managed_form( $form_id, $atts, $source_post_id = 0 ) {
        $form = get_post( $form_id );
        if ( ! $form || 'nine10_form_def' !== $form->post_type || ! get_post_meta( $form_id, '_nine10_form_active', true ) ) { echo '<p>This form is not available.</p>'; return; }
        $fields = (array) get_post_meta( $form_id, '_nine10_form_fields', true );
        $this->form_open( 'managed', $form_id, 'nine10_form_def' );
        echo '<input type="hidden" name="nine10_source_post_id" value="' . intval( $source_post_id ) . '">';
        echo '<h3 class="nine10-managed-form-title">' . esc_html( $form->post_title ) . '</h3>';
        foreach ( $fields as $field ) {
            $key = sanitize_key( $field['key'] ?? '' ); if ( ! $key ) { continue; }
            $required = ! empty( $field['required'] ) ? ' required' : ''; $label = esc_html( $field['label'] ?? $key ); $type = $field['type'] ?? 'text';
            echo '<label><span>' . $label . ( ! empty( $field['required'] ) ? ' *' : '' ) . '</span>';
            if ( 'textarea' === $type ) { echo '<textarea name="managed[' . esc_attr( $key ) . ']" rows="5"' . $required . '></textarea>'; }
            elseif ( 'select' === $type ) { echo '<select name="managed[' . esc_attr( $key ) . ']"' . $required . '><option value="">Choose…</option>'; foreach ( (array) ( $field['options'] ?? array() ) as $option ) { echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>'; } echo '</select>'; }
            elseif ( 'checkbox' === $type ) { echo '<input type="hidden" name="managed[' . esc_attr( $key ) . ']" value="0"><input type="checkbox" name="managed[' . esc_attr( $key ) . ']" value="1"' . $required . '>'; }
            else { $input = in_array( $type, array( 'email','number','url' ), true ) ? $type : 'text'; echo '<input type="' . esc_attr( $input ) . '" name="managed[' . esc_attr( $key ) . ']"' . $required . '>'; }
            echo '</label>';
        }
        echo '<label class="nine10-hp" aria-hidden="true"><span>Website</span><input tabindex="-1" autocomplete="off" type="text" name="website"></label>';
        echo '<button class="button button-primary" type="submit">' . esc_html( $atts['button'] ?: 'Submit' ) . '</button></form>';
    }

    private function render_contact_form( $post_id, $atts ) {
        $post = $post_id ? get_post( $post_id ) : null;
        if ( ! $post || ! $this->can_contact_post( $post ) ) { echo '<p>This author form needs a publicly viewable record.</p>'; return; }
        $this->form_open( 'contact', $post_id, $post->post_type );
        echo '<label><span>Your name</span><input required type="text" name="sender_name"></label>';
        echo '<label><span>Your email</span><input required type="email" name="sender_email"></label>';
        echo '<label><span>Subject</span><input required type="text" name="message_subject"></label>';
        echo '<label><span>Information / message</span><textarea required name="message_body" rows="7" placeholder="Type the information you want the author to receive."></textarea></label>';
        echo '<label class="nine10-hp" aria-hidden="true"><span>Website</span><input tabindex="-1" autocomplete="off" type="text" name="website"></label>';
        echo '<button class="button button-primary" type="submit">' . esc_html( $atts['button'] ?: 'Send to author' ) . '</button></form>';
    }

    public function handle_submit() {
        $mode = isset( $_POST['nine10_mode'] ) ? sanitize_key( wp_unslash( $_POST['nine10_mode'] ) ) : '';
        $post_id = isset( $_POST['nine10_post_id'] ) ? absint( $_POST['nine10_post_id'] ) : 0;
        $post_type = isset( $_POST['nine10_post_type'] ) ? sanitize_key( wp_unslash( $_POST['nine10_post_type'] ) ) : '';
        $nonce = isset( $_POST['nine10_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nine10_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'nine10_form_' . $mode . '_' . $post_id . '_' . $post_type ) ) { $this->finish( 'Security check failed.' ); }
        if ( 'contact' === $mode ) { $this->handle_contact( $post_id ); }
        if ( 'managed' === $mode ) { $this->handle_managed( $post_id ); }
        if ( ! is_user_logged_in() ) { $this->finish( 'Please sign in first.' ); }
        if ( 'create' === $mode ) { $this->handle_create( $post_type ); }
        if ( 'edit' === $mode ) { $this->handle_edit( $post_id ); }
        if ( 'data' === $mode ) { $this->handle_data( $post_id ); }
        $this->finish( 'Unsupported form action.' );
    }

    private function handle_managed( $form_id ) {
        if ( ! empty( $_POST['website'] ) ) { $this->finish( 'Response rejected.' ); }
        $form = get_post( $form_id );
        if ( ! $form || 'nine10_form_def' !== $form->post_type || ! get_post_meta( $form_id, '_nine10_form_active', true ) ) { $this->finish( 'This form is not accepting responses.' ); }
        $fields = (array) get_post_meta( $form_id, '_nine10_form_fields', true );
        $posted = isset( $_POST['managed'] ) && is_array( $_POST['managed'] ) ? wp_unslash( $_POST['managed'] ) : array(); $data = array();
        foreach ( $fields as $field ) {
            $key = sanitize_key( $field['key'] ?? '' ); if ( ! $key ) { continue; } $type = $field['type'] ?? 'text'; $raw = $posted[ $key ] ?? '';
            if ( 'email' === $type ) { $value = sanitize_email( $raw ); }
            elseif ( 'url' === $type ) { $value = esc_url_raw( $raw ); }
            elseif ( 'number' === $type ) { $value = is_numeric( $raw ) ? 0 + $raw : ''; }
            elseif ( 'textarea' === $type ) { $value = sanitize_textarea_field( $raw ); }
            elseif ( 'checkbox' === $type ) { $value = empty( $raw ) ? 0 : 1; }
            else { $value = sanitize_text_field( $raw ); }
            if ( ! empty( $field['required'] ) && ( '' === $value || null === $value || ( 'checkbox' === $type && 1 !== $value ) ) ) { $this->finish( 'Complete the required field: ' . ( $field['label'] ?? $key ) . '.' ); }
            if ( 'select' === $type && '' !== $value && ! in_array( $value, (array) ( $field['options'] ?? array() ), true ) ) { $this->finish( 'Choose a valid option for ' . ( $field['label'] ?? $key ) . '.' ); }
            $data[ $key ] = $value;
        }
        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $rate_key = 'nine10_managed_form_' . $form_id . '_' . md5( $remote . '|' . wp_json_encode( $data ) );
        if ( get_transient( $rate_key ) ) { $this->finish( 'This response was already received. Please wait before submitting again.' ); }
        set_transient( $rate_key, 1, 30 );
        $response_id = wp_insert_post( array( 'post_type'=>'nine10_form_response', 'post_status'=>'private', 'post_title'=>$form->post_title . ' · ' . current_time( 'mysql' ) ), true );
        if ( is_wp_error( $response_id ) ) { delete_transient( $rate_key ); $this->finish( $response_id->get_error_message() ); }
        $source_post_id = isset( $_POST['nine10_source_post_id'] ) ? absint( $_POST['nine10_source_post_id'] ) : 0;
        if ( $source_post_id && ( ! get_post( $source_post_id ) || 'publish' !== get_post_status( $source_post_id ) ) ) { $source_post_id = 0; }
        $category_ids = array();
        if ( taxonomy_exists( 'category' ) ) {
            if ( $source_post_id ) { $category_ids = wp_get_post_terms( $source_post_id, 'category', array( 'fields'=>'ids' ) ); }
            $form_categories = wp_get_post_terms( $form_id, 'category', array( 'fields'=>'ids' ) );
            $category_ids = array_values( array_unique( array_filter( array_map( 'absint', array_merge( is_array( $category_ids ) ? $category_ids : array(), is_array( $form_categories ) ? $form_categories : array() ) ) ) ) );
            if ( $category_ids ) { wp_set_post_terms( $response_id, $category_ids, 'category', false ); }
        }
        update_post_meta( $response_id, '_nine10_form_id', $form_id ); update_post_meta( $response_id, '_nine10_response_data', $data ); update_post_meta( $response_id, '_nine10_response_ip_hash', hash( 'sha256', $remote . wp_salt( 'nonce' ) ) );
        update_post_meta( $response_id, '_nine10_source_post_id', $source_post_id ); update_post_meta( $response_id, '_nine10_response_category_ids', $category_ids );
        do_action( 'nine10_managed_form_response_saved', $response_id, $form_id, $data );
        $this->finish( 'Response received.' );
    }

    private function handle_create( $post_type ) {
        if ( ! class_exists( 'Nine_Post_Manager' ) || ! method_exists( Nine_Post_Manager::instance(), 'create_external_payload' ) ) { $this->finish( 'Post Editor is not ready.' ); }
        $payload = array( 'post' => $this->posted_post_payload() );
        $result = Nine_Post_Manager::instance()->create_external_payload( $post_type, $payload, 'Created through Form Manager' );
        if ( is_wp_error( $result ) ) { $this->finish( $result->get_error_message() ); }
        $this->finish( 'Post created through Post Editor.', isset( $result['post_id'] ) ? absint( $result['post_id'] ) : 0 );
    }

    private function handle_edit( $post_id ) {
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { $this->finish( 'You cannot edit this record.' ); }
        if ( ! class_exists( 'Nine_Post_Manager' ) ) { $this->finish( 'Post Editor is not ready.' ); }
        $payload = array( 'post' => $this->posted_post_payload() );
        $result = Nine_Post_Manager::instance()->apply_external_payload( $post_id, $payload, 'Before Form Manager post edit', false );
        if ( is_wp_error( $result ) ) { $this->finish( $result->get_error_message() ); }
        $this->finish( 'Post updated through Post Editor.', $post_id );
    }

    private function posted_post_payload() {
        return array(
            'title'   => isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '',
            // Pass post text to 9 Post Editor unchanged apart from WordPress slashes.
            // The destination engine owns capability-aware content sanitization and must
            // remain the single authority for Gutenberg/block-compatible post content.
            'excerpt' => isset( $_POST['post_excerpt'] ) ? wp_unslash( $_POST['post_excerpt'] ) : '',
            'content' => isset( $_POST['post_content'] ) ? wp_unslash( $_POST['post_content'] ) : '',
            'status'  => isset( $_POST['post_status'] ) ? sanitize_key( wp_unslash( $_POST['post_status'] ) ) : 'draft',
        );
    }

    private function handle_data( $post_id ) {
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { $this->finish( 'You cannot edit data for this record.' ); }
        if ( ! class_exists( 'NineCode_Data_Exporter' ) || ! class_exists( 'NineCode_Data_Importer' ) ) { $this->finish( 'Data Engine is not ready.' ); }
        $record = ( new NineCode_Data_Exporter() )->export_post_record( $post_id );
        if ( ! is_array( $record ) ) { $this->finish( 'Data Engine could not load this record.' ); }
        $acf = isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) ? wp_unslash( $_POST['acf'] ) : array();
        $meta = isset( $_POST['meta'] ) && is_array( $_POST['meta'] ) ? wp_unslash( $_POST['meta'] ) : array();
        foreach ( (array) $record['fields'] as &$field ) { $key = sanitize_key( $field['key'] ?? $field['name'] ?? '' ); if ( array_key_exists( $key, $acf ) ) { $field['value'] = $this->decode_value( $acf[$key], $field['value'] ?? null ); } } unset( $field );
        foreach ( (array) $record['meta'] as &$item ) { $key = sanitize_key( $item['key'] ?? '' ); if ( array_key_exists( $key, $meta ) ) { $item['value'] = $this->decode_value( $meta[$key], $item['value'] ?? null ); } } unset( $item );
        $tax = isset( $_POST['tax'] ) && is_array( $_POST['tax'] ) ? wp_unslash( $_POST['tax'] ) : array();
        foreach ( $tax as $taxonomy => $raw ) {
            $taxonomy = sanitize_key( $taxonomy );
            if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( get_post_type( $post_id ), $taxonomy ) ) { continue; }
            $tax_obj = get_taxonomy( $taxonomy );
            $assign_cap = $tax_obj && ! empty( $tax_obj->cap->assign_terms ) ? $tax_obj->cap->assign_terms : '';
            if ( ! $assign_cap || ! current_user_can( $assign_cap ) ) { $this->finish( 'You do not have permission to change ' . $this->friendly_label( $taxonomy ) . '.' ); }
            $slugs = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) $raw ) ) ) );
            foreach ( $slugs as $slug ) {
                if ( ! get_term_by( 'slug', $slug, $taxonomy ) ) {
                    $this->finish( 'The term “' . $slug . '” does not exist in ' . $this->friendly_label( $taxonomy ) . '. Create it first in 9 Category Manager.' );
                }
            }
            $record['taxonomies'][$taxonomy] = array_map( function( $slug ) use ( $taxonomy ) { return array( 'slug' => $slug, 'taxonomy' => $taxonomy ); }, $slugs );
        }
        $result = ( new NineCode_Data_Importer() )->import_records_atomic( array( $record ), array(
            'dry_run' => false, 'create_missing' => false, 'create_missing_records' => false, 'create_missing_terms' => false,
            'import_identity' => false, 'import_term_identity' => true, 'import_terms' => true, 'capture_versions' => true,
        ), 'Form Manager data update' );
        if ( is_wp_error( $result ) ) { $this->finish( $result->get_error_message() ); }
        if ( ! empty( $result['errors_count'] ) ) { $this->finish( implode( ' ', (array) ( $result['messages'] ?? array( 'Data update failed.' ) ) ) ); }
        $this->finish( 'Data saved through Data Engine with recovery protection.', $post_id );
    }

    private function decode_value( $raw, $original ) {
        if ( is_array( $original ) || is_object( $original ) ) {
            $decoded = json_decode( (string) $raw, true );
            return JSON_ERROR_NONE === json_last_error() ? $decoded : $original;
        }
        return is_string( $raw ) ? sanitize_textarea_field( $raw ) : $raw;
    }

    private function handle_contact( $post_id ) {
        if ( ! empty( $_POST['website'] ) ) { $this->finish( 'Message rejected.' ); }
        $post = get_post( $post_id );
        if ( ! $post || ! $this->can_contact_post( $post ) ) { $this->finish( 'The author form is not available for this record.' ); }
        $author = get_userdata( $post->post_author );
        if ( ! $author || ! is_email( $author->user_email ) ) { $this->finish( 'The author does not have a usable email address.' ); }
        $name = isset( $_POST['sender_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sender_name'] ) ) : '';
        $email = isset( $_POST['sender_email'] ) ? sanitize_email( wp_unslash( $_POST['sender_email'] ) ) : '';
        $subject = isset( $_POST['message_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['message_subject'] ) ) : '';
        $body = isset( $_POST['message_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message_body'] ) ) : '';
        $name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 120 ) : substr( $name, 0, 120 );
        $subject = function_exists( 'mb_substr' ) ? mb_substr( $subject, 0, 180 ) : substr( $subject, 0, 180 );
        $body = function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 5000 ) : substr( $body, 0, 5000 );
        if ( ! $name || ! is_email( $email ) || ! $subject || ! $body ) { $this->finish( 'Complete all message fields.' ); }
        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $rate_key = 'nine10_form_mail_' . md5( strtolower( $email ) . '|' . $post->ID . '|' . $remote );
        $rate_seconds = max( 15, absint( apply_filters( 'nine10_form_contact_rate_limit', 60, $post->ID, $email ) ) );
        if ( get_transient( $rate_key ) ) { $this->finish( 'Please wait before sending another message to this author.' ); }
        set_transient( $rate_key, 1, $rate_seconds );
        $mail_subject = '[9Form] ' . $subject . ' — ' . get_the_title( $post );
        $mail_body = "Record: " . get_the_title( $post ) . "\nRecord ID: " . $post->ID . "\nFrom: " . $name . " <" . $email . ">\n\n" . $body;
        $headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );
        if ( ! wp_mail( $author->user_email, $mail_subject, $mail_body, $headers ) ) {
            delete_transient( $rate_key );
            $this->finish( 'WordPress could not send the message. Check site email delivery.' );
        }
        do_action( 'nine10_form_author_message_sent', $post->ID, $post->post_author, $email );
        $this->finish( 'Message sent to the author.' );
    }


    private function can_contact_post( $post ) {
        if ( ! $post instanceof WP_Post ) { return false; }
        if ( is_user_logged_in() && current_user_can( 'read_post', $post->ID ) ) { return true; }
        if ( function_exists( 'is_post_publicly_viewable' ) ) { return is_post_publicly_viewable( $post ); }
        $type = get_post_type_object( $post->post_type );
        return 'publish' === $post->post_status && $type && ! empty( $type->publicly_queryable );
    }

    private function finish( $message, $post_id = 0 ) {
        $return = isset( $_POST['nine10_return'] ) ? esc_url_raw( wp_unslash( $_POST['nine10_return'] ) ) : home_url( '/' );
        if ( ! $return ) { $return = home_url( '/' ); }
        if ( $post_id ) { $return = add_query_arg( 'nine10_record', absint( $post_id ), $return ); }
        wp_safe_redirect( add_query_arg( 'nine10_form_notice', rawurlencode( $message ), $return ) );
        exit;
    }

    private function current_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        return esc_url_raw( $scheme . $host . $uri );
    }

    public function register_form_attachment_metaboxes() {
        $form_types = get_post_types( array( 'show_ui'=>true ), 'objects' );
        if ( function_exists( 'nine10_data_filter_internal_post_types' ) ) { $form_types = nine10_data_filter_internal_post_types( $form_types, 'form-metabox' ); }
        $form_types = apply_filters( 'nine10_data_form_post_types', $form_types, 'metabox' );
        foreach ( is_array( $form_types ) ? $form_types : array() as $type => $obj ) {
            if ( in_array( $type, array( 'attachment', 'nine10_form_def', 'nine10_form_response' ), true ) ) { continue; }
            $cap = ! empty( $obj->cap->edit_posts ) ? $obj->cap->edit_posts : 'edit_posts';
            if ( ! current_user_can( $cap ) ) { continue; }
            add_meta_box( 'nine10-data-form-selector', '9 Data Form', array( $this, 'render_form_attachment_metabox' ), $type, 'side', 'default' );
        }
    }

    public function render_form_attachment_metabox( $post ) {
        $forms = get_posts( array( 'post_type'=>'nine10_form_def', 'post_status'=>'publish', 'posts_per_page'=>-1, 'orderby'=>'title', 'order'=>'ASC', 'meta_key'=>'_nine10_form_active', 'meta_value'=>1 ) );
        $selected = absint( get_post_meta( $post->ID, '_nine10_attached_form_id', true ) );
        $position = self::sanitize_form_position( get_post_meta( $post->ID, '_nine10_attached_form_position', true ) );
        wp_nonce_field( 'nine10_form_attachment_' . $post->ID, 'nine10_form_attachment_nonce' );
        echo '<p><label for="nine10_attached_form_id"><strong>Select form</strong></label><select id="nine10_attached_form_id" name="nine10_attached_form_id" style="width:100%"><option value="0">No attached form</option>';
        foreach ( $forms as $form ) { echo '<option value="' . intval( $form->ID ) . '" ' . selected( $selected, $form->ID, false ) . '>' . esc_html( $form->post_title ) . '</option>'; }
        echo '</select></p><p><label for="nine10_attached_form_position"><strong>Placement</strong></label><select id="nine10_attached_form_position" name="nine10_attached_form_position" style="width:100%"><option value="none" ' . selected( $position, 'none', false ) . '>Selected only / manual placement</option><option value="before" ' . selected( $position, 'before', false ) . '>Before content</option><option value="after" ' . selected( $position, 'after', false ) . '>After content</option></select></p><p class="description">Available on every editable post, page and custom post type. For an exact position inside content, use <code>[nine10_form form_id=&quot;ID&quot;]</code>.</p>';
    }

    public function save_form_attachment( $post_id, $post ) {
        if ( ! $post || in_array( $post->post_type, array( 'attachment', 'nine10_form_def', 'nine10_form_response' ), true ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
        if ( empty( $_POST['nine10_form_attachment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nine10_form_attachment_nonce'] ) ), 'nine10_form_attachment_' . $post_id ) ) { return; }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
        $form_id = isset( $_POST['nine10_attached_form_id'] ) ? absint( $_POST['nine10_attached_form_id'] ) : 0;
        if ( $form_id && ( 'nine10_form_def' !== get_post_type( $form_id ) || ! get_post_meta( $form_id, '_nine10_form_active', true ) ) ) { $form_id = 0; }
        $position = isset( $_POST['nine10_attached_form_position'] ) ? self::sanitize_form_position( wp_unslash( $_POST['nine10_attached_form_position'] ) ) : 'none';
        update_post_meta( $post_id, '_nine10_attached_form_id', $form_id );
        update_post_meta( $post_id, '_nine10_attached_form_position', $form_id ? $position : 'none' );
    }

    public function inject_attached_form( $content ) {
        if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $content; }
        $post_id = get_queried_object_id();
        $form_id = absint( get_post_meta( $post_id, '_nine10_attached_form_id', true ) );
        $position = self::sanitize_form_position( get_post_meta( $post_id, '_nine10_attached_form_position', true ) );
        if ( ! $form_id || 'none' === $position || 'nine10_form_def' !== get_post_type( $form_id ) || ! get_post_meta( $form_id, '_nine10_form_active', true ) ) { return $content; }
        /* Host plugins may own exact form placement. Data Manager remains the
         * form/response authority but does not force a second copy when a host
         * renderer explicitly suppresses automatic injection. */
        $allow = apply_filters( 'nine10_form_should_inject_attached', true, $post_id, $form_id, $position, $content );
        if ( ! $allow ) { return $content; }
        $form = do_shortcode( '[nine10_form form_id="' . intval( $form_id ) . '" post_id="' . intval( $post_id ) . '"]' );
        return 'before' === $position ? $form . $content : $content . $form;
    }

    private function friendly_label( $value ) {
        return ucwords( trim( preg_replace( '/[_\-]+/', ' ', (string) $value ) ) );
    }
}
