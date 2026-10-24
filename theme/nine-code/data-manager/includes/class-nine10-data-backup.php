<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Nine10_Data_Backup {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_post_nine10_data_backup_export', array( $this, 'handle_export' ) );
        add_action( 'admin_post_nine10_data_backup_pack_preview', array( $this, 'handle_pack_preview' ) );
        add_action( 'admin_post_nine10_data_backup_pack_apply', array( $this, 'handle_pack_apply' ) );
    }

    public static function render_admin_page_static() { self::instance()->render_admin_page(); }

    public static function normalize_scope( $raw ) {
        $scope = isset( $raw['scope'] ) ? sanitize_key( $raw['scope'] ) : 'category';
        if ( ! in_array( $scope, array( 'category', 'taxonomy', 'page', 'post_type' ), true ) ) { $scope = 'category'; }
        return array(
            'scope' => $scope,
            'taxonomy' => isset( $raw['taxonomy'] ) ? sanitize_key( $raw['taxonomy'] ) : ( 'category' === $scope ? 'category' : '' ),
            'term_id' => isset( $raw['term_id'] ) ? absint( $raw['term_id'] ) : 0,
            'post_id' => isset( $raw['post_id'] ) ? absint( $raw['post_id'] ) : 0,
            'post_type' => isset( $raw['post_type'] ) ? sanitize_key( $raw['post_type'] ) : 'post',
        );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'You do not have permission to use Data Backup.' ); }
        $taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
        $post_types = get_post_types( array( 'show_ui' => true ), 'objects' ); unset( $post_types['attachment'] );
        $pages = get_posts( array( 'post_type'=>'page', 'post_status'=>array('publish','draft','pending','private','future'), 'posts_per_page'=>500, 'orderby'=>'title', 'order'=>'ASC' ) );
        $stage = isset( $_GET['pack_stage'] ) ? sanitize_text_field( wp_unslash( $_GET['pack_stage'] ) ) : '';
        echo '<div class="wrap ultron955-wrap nine10-backup-admin"><header class="ultron955-hero"><div><p class="ultron955-kicker">9 DATA MANAGER</p><h1>Data Backup</h1><p>Select a category, taxonomy term, page or post type. Export editable CSV/Excel/9Data Template, or use the complete 9Data Pack for full post-content restore.</p></div><div class="ultron955-version">BACKUP</div></header>';
        if ( ! empty( $_GET['nine10_backup_notice'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( wp_unslash( $_GET['nine10_backup_notice'] ) ) . '</p></div>'; }
        echo '<section class="ultron955-section"><h2>Download</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nine10-backup-grid"><input type="hidden" name="action" value="nine10_data_backup_export">'; wp_nonce_field( 'nine10_data_backup_export' );
        echo '<label>Scope<select name="scope" data-nine10-backup-scope><option value="category">Category</option><option value="taxonomy">Taxonomy term</option><option value="page">Page</option><option value="post_type">Post type</option></select></label>';
        echo '<label data-nine10-category-wrap>Category<select name="category_term_id"><option value="0">Choose category</option>'; foreach ( get_terms( array( 'taxonomy'=>'category', 'hide_empty'=>false, 'number'=>1000 ) ) as $term ) { if ( is_object( $term ) ) echo '<option value="' . intval( $term->term_id ) . '">' . esc_html( $term->name ) . '</option>'; } echo '</select></label>';
        echo '<label data-nine10-taxonomy-wrap hidden>Taxonomy<select name="taxonomy"><option value="">Choose taxonomy</option>'; foreach ( $taxonomies as $tax ) { echo '<option value="' . esc_attr( $tax->name ) . '">' . esc_html( $tax->labels->singular_name ) . '</option>'; } echo '</select></label>';
        echo '<label data-nine10-term-wrap hidden>Term<select name="term_id"><option value="0">Choose term</option>'; foreach ( $taxonomies as $tax ) { $terms = get_terms( array( 'taxonomy'=>$tax->name, 'hide_empty'=>false, 'number'=>1000 ) ); if ( is_wp_error( $terms ) ) continue; foreach ( $terms as $term ) { echo '<option data-taxonomy="' . esc_attr( $tax->name ) . '" value="' . intval( $term->term_id ) . '">' . esc_html( $tax->labels->singular_name . ': ' . $term->name ) . '</option>'; } } echo '</select></label>';
        echo '<label data-nine10-page-wrap hidden>Page<select name="post_id"><option value="0">Choose page</option>'; foreach ( $pages as $page ) { echo '<option value="' . intval( $page->ID ) . '">' . esc_html( $page->post_title ?: '(no title)' ) . ' (#' . intval( $page->ID ) . ')</option>'; } echo '</select></label>';
        echo '<label data-nine10-posttype-wrap>Post type<select name="post_type">'; foreach ( $post_types as $type ) { echo '<option value="' . esc_attr( $type->name ) . '" ' . selected( 'post', $type->name, false ) . '>' . esc_html( $type->labels->name ) . '</option>'; } echo '</select></label>';
        echo '<label>Format<select name="format"><option value="xlsx">Excel (.xlsx)</option><option value="csv">CSV (.csv)</option><option value="json">9Data Template (.json)</option><option value="9pack">9Data Pack — complete post/content ZIP</option></select></label>';
        echo '<label class="nine10-inline-check"><input type="checkbox" name="with_media" value="1"> Include media in 9Data Pack</label><button class="button button-primary button-hero" type="submit">Download Backup</button></form>';
        echo '<p class="description"><strong>CSV/Excel/9Data Template:</strong> editable structured-data round-trip for all matching existing records. <strong>9Data Pack:</strong> complete post content, post settings, taxonomies, supported fields/meta and optional media; the full-pack engine enforces its per-archive safety limit instead of silently omitting records.</p></section>';

        echo '<section class="ultron955-section"><h2>Upload / Restore</h2><div class="nine10-backup-import-grid"><article><h3>CSV / Excel / 9Data Template</h3><p>Preview first, then selectively apply the reviewed changes with the existing Data Engine conflict protection.</p><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'ninecode_acf_import' ); echo '<input type="hidden" name="action" value="ninecode_acf_import"><input type="file" name="data_file" accept=".xlsx,.json,.csv" required><label class="nine10-inline-check"><input type="checkbox" name="create_missing_terms" value="1" checked> Create missing taxonomy terms</label><input type="hidden" name="import_terms" value="1"><button class="button button-primary" name="import_mode" value="preview">Validate / Preview</button></form></article>';
        echo '<article><h3>9Data Pack</h3><p>Inspect the complete backup first. Nothing is written until you approve the restore mode.</p>';
        if ( $stage ) {
            $count = isset( $_GET['post_count'] ) ? absint( $_GET['post_count'] ) : 0; $media = isset( $_GET['media_count'] ) ? absint( $_GET['media_count'] ) : 0;
            echo '<div class="nine10-pack-preview"><strong>Backup inspected</strong><span>' . intval( $count ) . ' post(s) · ' . intval( $media ) . ' media item(s)</span><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nine10_data_backup_pack_apply"><input type="hidden" name="token" value="' . esc_attr( $stage ) . '">'; wp_nonce_field( 'nine10_data_backup_pack_apply_' . $stage ); echo '<label>Restore mode<select name="mode"><option value="safe">Safe — restore only when source record still matches</option><option value="match_slug">Match by post type + slug</option><option value="duplicate">Create duplicates instead of replacing</option></select></label><button class="button button-primary">Apply 9Data Pack</button></form></div>';
        } else {
            echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nine10_data_backup_pack_preview">'; wp_nonce_field( 'nine10_data_backup_pack_preview' ); echo '<input type="file" name="backup" accept=".zip,.9data.zip,.9post.zip,application/zip" required><button class="button button-primary">Inspect 9Data Pack</button></form>';
        }
        echo '</article></div></section></div>';
    }

    public function handle_export() {
        if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Permission denied.' ); }
        check_admin_referer( 'nine10_data_backup_export' );
        $input = $_POST; $scope = self::normalize_scope( $input );
        if ( 'category' === $scope['scope'] ) { $scope['taxonomy'] = 'category'; $scope['term_id'] = isset( $input['category_term_id'] ) ? absint( $input['category_term_id'] ) : 0; $scope['post_type'] = 'post'; }
        $format = isset( $input['format'] ) ? sanitize_key( wp_unslash( $input['format'] ) ) : 'xlsx';
        if ( ! in_array( $format, array( 'xlsx', 'csv', 'json', '9pack' ), true ) ) { $format = 'xlsx'; }
        $records = array(); $ids = array(); $label = $scope['scope']; $exporter = new NineCode_Data_Exporter();
        if ( 'page' === $scope['scope'] ) {
            $scope['post_type'] = 'page'; $post = get_post( $scope['post_id'] ); if ( ! $post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) { wp_die( 'Choose an editable page.' ); }
            $ids = array( $post->ID ); $record = $exporter->export_post_record( $post->ID ); if ( $record ) $records[] = $record; $label = 'page-' . $post->ID;
        } elseif ( in_array( $scope['scope'], array( 'category', 'taxonomy' ), true ) ) {
            if ( ! taxonomy_exists( $scope['taxonomy'] ) || ! $scope['term_id'] ) { wp_die( 'Choose a valid taxonomy term.' ); }
            $term = get_term( $scope['term_id'], $scope['taxonomy'] ); if ( ! $term || is_wp_error( $term ) ) { wp_die( 'Choose a valid taxonomy term.' ); }
            if ( ! post_type_exists( $scope['post_type'] ) || ! is_object_in_taxonomy( $scope['post_type'], $scope['taxonomy'] ) ) { wp_die( 'The selected post type does not use this taxonomy.' ); }
            $pack = $exporter->export_post_collection( $scope['post_type'], '', false, $scope['taxonomy'], $term->slug ); $records = (array) ( $pack['records'] ?? array() );
            $ids = get_posts( array( 'post_type'=>$scope['post_type'], 'post_status'=>array('publish','draft','pending','private','future'), 'posts_per_page'=>-1, 'fields'=>'ids', 'tax_query'=>array( array( 'taxonomy'=>$scope['taxonomy'], 'field'=>'term_id', 'terms'=>array($scope['term_id']) ) ) ) );
            $ids = array_values( array_filter( array_map( 'absint', $ids ), static function( $id ) { return current_user_can( 'edit_post', $id ); } ) ); $label = $scope['taxonomy'] . '-' . $term->slug;
        } else {
            if ( ! post_type_exists( $scope['post_type'] ) ) { wp_die( 'Choose a valid post type.' ); }
            $pack = $exporter->export_post_collection( $scope['post_type'] ); $records = (array) ( $pack['records'] ?? array() );
            $ids = get_posts( array( 'post_type'=>$scope['post_type'], 'post_status'=>array('publish','draft','pending','private','future'), 'posts_per_page'=>-1, 'fields'=>'ids' ) );
            $ids = array_values( array_filter( array_map( 'absint', $ids ), static function( $id ) { return current_user_can( 'edit_post', $id ); } ) ); $label = $scope['post_type'];
        }
        if ( ! $records && '9pack' !== $format ) { wp_die( 'No records found for this scope.' ); }
        $filename = '9data-' . sanitize_file_name( $label ) . '-' . gmdate( 'Y-m-d-His' );
        $template_package = $records ? $exporter->package_records( 'post', array( 'post_type'=>$scope['post_type'] ?: 'post', 'backup_scope'=>$scope ), $records ) : array();
        $scope_guard = isset( $template_package['scope_guard'] ) && is_array( $template_package['scope_guard'] ) ? $template_package['scope_guard'] : array();
        if ( '9pack' === $format ) {
            if ( ! class_exists( 'Nine_Post_Manager_Backup' ) ) { wp_die( 'Complete 9Data Pack engine is unavailable.' ); }
            $context = array( 'scope'=>$scope['scope'], 'taxonomy'=>$scope['taxonomy'], 'term_id'=>$scope['term_id'], 'post_type'=>$scope['post_type'], 'post_id'=>$scope['post_id'], 'slug'=>$label, 'nine10_data_backup'=>true );
            $result = Nine_Post_Manager_Backup::instance()->create_external_archive( $ids, 'data_backup', ! empty( $input['with_media'] ), $context );
            if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
            wp_safe_redirect( $result['downloadUrl'] ); exit;
        }
        if ( 'csv' === $format ) { $exporter->send_csv( $records, $filename . '.csv', $scope_guard ); }
        if ( 'xlsx' === $format ) {
            if ( ! class_exists( 'NineCode_Excel' ) ) { wp_die( 'Excel engine unavailable.' ); }
            $excel = new NineCode_Excel(); $path = $excel->create_workbook( $records, 'post', $scope['post_type'] ?: 'post', $filename . '.xlsx', $scope_guard );
            if ( is_wp_error( $path ) ) { wp_die( esc_html( $path->get_error_message() ) ); }
            nocache_headers(); header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename . '.xlsx' ) . '"' ); header( 'Content-Length: ' . filesize( $path ) ); readfile( $path ); wp_delete_file( $path ); exit; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams a generated download to the browser.
        }
        $payload = $template_package;
        nocache_headers(); header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename . '.json' ) . '"' ); echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); exit;
    }

    public function handle_pack_preview() {
        if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
        check_admin_referer( 'nine10_data_backup_pack_preview' );
        if ( ! class_exists( 'Nine_Post_Manager_Backup' ) ) { wp_die( '9Data Pack engine is unavailable.' ); }
        $result = Nine_Post_Manager_Backup::instance()->stage_external_upload( isset( $_FILES['backup'] ) ? $_FILES['backup'] : array() );
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
        wp_safe_redirect( add_query_arg( array( 'page'=>'nine10-data-backup', 'pack_stage'=>$result['token'], 'post_count'=>$result['postCount'], 'media_count'=>$result['mediaCount'] ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_pack_apply() {
        if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : ''; check_admin_referer( 'nine10_data_backup_pack_apply_' . $token );
        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'safe';
        $result = Nine_Post_Manager_Backup::instance()->apply_external_stage( $token, $mode );
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
        $message = sprintf( '9Data Pack restored: %d created, %d updated, %d skipped.', absint( $result['created'] ?? 0 ), absint( $result['updated'] ?? 0 ), absint( $result['skipped'] ?? 0 ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'nine10-data-backup', 'nine10_backup_notice'=>$message ), admin_url( 'admin.php' ) ) ); exit;
    }
}
