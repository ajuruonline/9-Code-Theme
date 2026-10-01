<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Native, bite-sized post/category backups for 9 Post Editor.
 *
 * The archive deliberately stores only post-owned data plus optional media.
 * It does not export plugins, themes, site options, users or global settings.
 */

/** Small archive adapter: native ZipArchive when available, WordPress PclZip fallback otherwise. */
final class NPM9_Portable_Archive {
    private $zip = null;
    private $pcl = null;
    private $path = '';
    private $stage = '';
    private $write = false;

    public static function create( $path ) {
        $self = new self(); $self->path = $path; $self->write = true;
        if ( class_exists( 'ZipArchive' ) ) {
            $self->zip = new ZipArchive();
            if ( true !== $self->zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return new WP_Error( 'zip_open', 'Could not create the backup archive.' ); }
            return $self;
        }
        require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        if ( ! class_exists( 'PclZip' ) ) { return new WP_Error( 'zip_missing', 'This WordPress installation cannot create ZIP backups.' ); }
        $self->stage = trailingslashit( get_temp_dir() ) . 'npm9-archive-' . wp_generate_password( 16, false, false );
        if ( ! wp_mkdir_p( $self->stage ) ) { return new WP_Error( 'zip_stage', 'Could not create a temporary backup directory.' ); }
        return $self;
    }

    public static function open_read( $path ) {
        $self = new self(); $self->path = $path;
        if ( class_exists( 'ZipArchive' ) ) {
            $self->zip = new ZipArchive();
            if ( true !== $self->zip->open( $path ) ) { return new WP_Error( 'bad_zip', 'This is not a readable ZIP archive.' ); }
            return $self;
        }
        require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        if ( ! class_exists( 'PclZip' ) ) { return new WP_Error( 'zip_missing', 'This WordPress installation cannot read ZIP backups.' ); }
        $self->pcl = new PclZip( $path );
        $list = $self->pcl->listContent();
        if ( 0 === $list || false === $list ) { return new WP_Error( 'bad_zip', 'This is not a readable ZIP archive.' ); }
        return $self;
    }

    public function addFromString( $name, $content ) {
        if ( $this->zip ) { return $this->zip->addFromString( $name, $content ); }
        if ( ! $this->write || ! $this->stage ) { return false; }
        $target = $this->stage_path( $name ); if ( is_wp_error( $target ) ) { return false; }
        wp_mkdir_p( dirname( $target ) );
        return false !== file_put_contents( $target, $content );
    }

    public function addFile( $source, $name ) {
        if ( $this->zip ) { return $this->zip->addFile( $source, $name ); }
        if ( ! $this->write || ! is_readable( $source ) ) { return false; }
        $target = $this->stage_path( $name ); if ( is_wp_error( $target ) ) { return false; }
        wp_mkdir_p( dirname( $target ) );
        return copy( $source, $target );
    }

    public function locateName( $name ) {
        if ( $this->zip ) { return $this->zip->locateName( $name ); }
        $list = $this->pcl ? $this->pcl->listContent() : [];
        foreach ( (array) $list as $i => $entry ) { if ( isset( $entry['filename'] ) && ltrim( str_replace( '\\', '/', $entry['filename'] ), '/' ) === ltrim( $name, '/' ) ) { return $i; } }
        return false;
    }

    public function getFromName( $name ) {
        if ( $this->zip ) { return $this->zip->getFromName( $name ); }
        if ( ! $this->pcl || false === $this->locateName( $name ) ) { return false; }
        $out = $this->pcl->extract( PCLZIP_OPT_BY_NAME, $name, PCLZIP_OPT_EXTRACT_AS_STRING );
        if ( 0 === $out || false === $out || empty( $out[0] ) || ! array_key_exists( 'content', $out[0] ) ) { return false; }
        return $out[0]['content'];
    }

    public function getEntrySize( $name ) {
        if ( $this->zip ) {
            $stat = $this->zip->statName( $name );
            return is_array( $stat ) && isset( $stat['size'] ) ? (int) $stat['size'] : -1;
        }
        if ( ! $this->pcl ) { return -1; }
        foreach ( (array) $this->pcl->listContent() as $entry ) {
            if ( isset( $entry['filename'] ) && ltrim( str_replace( '\\', '/', $entry['filename'] ), '/' ) === ltrim( $name, '/' ) ) {
                return isset( $entry['size'] ) ? (int) $entry['size'] : -1;
            }
        }
        return -1;
    }

    public function close() {
        if ( $this->zip ) { return $this->zip->close(); }
        if ( $this->write && $this->stage ) {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            $files = [];
            $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->stage, FilesystemIterator::SKIP_DOTS ) );
            foreach ( $it as $file ) { if ( $file->isFile() ) $files[] = $file->getPathname(); }
            $pcl = new PclZip( $this->path );
            $result = $files ? $pcl->create( $files, PCLZIP_OPT_REMOVE_PATH, $this->stage ) : 0;
            $this->remove_tree( $this->stage ); $this->stage = '';
            return 0 !== $result && false !== $result;
        }
        return true;
    }

    private function stage_path( $name ) {
        $name = ltrim( str_replace( '\\', '/', (string) $name ), '/' );
        if ( '' === $name || false !== strpos( $name, '../' ) ) return new WP_Error( 'bad_path', 'Unsafe archive path.' );
        return trailingslashit( $this->stage ) . $name;
    }

    private function remove_tree( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $it as $file ) { $file->isDir() ? @rmdir( $file->getPathname() ) : wp_delete_file( $file->getPathname() ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removes this plugin's own staging directory.
        @rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removes this plugin's own staging directory.
    }
}

final class Nine_Post_Manager_Backup {
    private static $instance = null;
    /** Old media URLs (including generated sizes) mapped to restored URLs during one import. */
    private $restore_media_replacements = [];
    const FORMAT = '9pm-native-backup';
    const VERSION = 1;
    const MAX_POSTS = 500;
    const CATEGORY_CHUNK_POSTS = 100;
    const MAX_MEDIA = 600;
    const MAX_MEDIA_FILE_BYTES = 67108864; // 64 MB per media item.
    const MAX_MEDIA_TOTAL_BYTES = 230686720; // ~220 MB media payload keeps archives below the 256 MB import guard.
    const MAX_ARCHIVE_BYTES = 268435456; // 256 MB import guard.
    const MAX_MANIFEST_BYTES = 4194304; // 4 MB manifest guard.
    const MAX_POST_JSON_BYTES = 8388608; // 8 MB per post data file.
    const TOKEN_TTL = 1800;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        foreach ( [
            'native_backup_post' => 'ajax_backup_post',
            'native_backup_category' => 'ajax_backup_category',
            'native_backup_import_preview' => 'ajax_import_preview',
            'native_backup_import_apply' => 'ajax_import_apply',
        ] as $action => $method ) {
            add_action( 'wp_ajax_npm9_' . $action, [ $this, $method ] );
        }
        add_action( 'admin_post_npm9_download_backup', [ $this, 'download_backup' ] );
    }

    private function verify_post( $post_id ) {
        check_ajax_referer( 'npm9_action', 'nonce' );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }
    }

    private function temp_archive_path( $label = 'post' ) {
        $base = wp_tempnam( '9pm-' . sanitize_file_name( $label ) . '.zip' );
        if ( ! $base ) { return new WP_Error( 'temp_failed', 'WordPress could not create a temporary backup file.' ); }
        $zip = preg_replace( '/\.[^.]+$/', '', $base ) . '.zip';
        @rename( $base, $zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- moves a staged file within this plugin's own directory.
        return $zip;
    }

    private function store_download_token( $path, $filename ) {
        $token = wp_generate_password( 32, false, false );
        $key = 'npm9_dl_' . get_current_user_id() . '_' . hash( 'sha256', $token );
        set_transient( $key, [ 'path' => $path, 'filename' => $filename ], self::TOKEN_TTL );
        return [
            'token' => $token,
            'url' => wp_nonce_url( admin_url( 'admin-post.php?action=npm9_download_backup&token=' . rawurlencode( $token ) ), 'npm9_download_' . $token ),
        ];
    }

    public function download_backup() {
        if ( ! is_user_logged_in() ) { wp_die( 'Permission denied.' ); }
        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        if ( ! $token || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'npm9_download_' . $token ) ) {
            wp_die( 'Invalid or expired backup link.' );
        }
        $key = 'npm9_dl_' . get_current_user_id() . '_' . hash( 'sha256', $token );
        $item = get_transient( $key );
        delete_transient( $key );
        if ( ! is_array( $item ) || empty( $item['path'] ) || ! is_readable( $item['path'] ) ) { wp_die( 'Backup file expired.' ); }
        $path = $item['path'];
        $filename = sanitize_file_name( $item['filename'] ?? wp_basename( $path ) );
        nocache_headers();
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams a generated download to the browser.
        wp_delete_file( $path );
        exit;
    }

    /**
     * Public bridge for the consolidated 9 Data Manager backup workspace.
     * Keeps the existing portable archive format and restore engine as the sole owner.
     */
    public function create_external_archive( array $post_ids, $kind = 'selection', $with_media = false, array $context = array() ) {
        if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'permission', 'Permission denied.' ); }
        $allowed = array();
        foreach ( $post_ids as $post_id ) { $post_id = absint( $post_id ); if ( $post_id && current_user_can( 'edit_post', $post_id ) ) { $allowed[] = $post_id; } }
        if ( ! $allowed ) { return new WP_Error( 'empty', 'No editable records were selected.' ); }
        if ( count( $allowed ) > self::MAX_POSTS ) { return new WP_Error( 'too_many', sprintf( 'This 9Data Pack scope contains %d records; the complete archive safety limit is %d per pack. Use CSV/Excel/9Data Template for the full structured-data set, or split the complete archive into smaller scopes.', count( $allowed ), self::MAX_POSTS ) ); }
        return $this->create_archive( $allowed, sanitize_key( $kind ), (bool) $with_media, $context );
    }

    public function stage_external_upload( $file ) {
        if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'permission', 'Permission denied.' ); }
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) { return new WP_Error( 'missing', 'Choose a .9data.zip / .9post.zip backup.' ); }
        $size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
        if ( $size > self::MAX_ARCHIVE_BYTES ) { return new WP_Error( 'too_large', 'Backup is larger than the 256 MB safety limit.' ); }
        $tmp = wp_tempnam( '9data-import.zip' );
        if ( ! $tmp || ! copy( $file['tmp_name'], $tmp ) ) { return new WP_Error( 'stage', 'Could not stage the uploaded backup.' ); }
        $inspect = $this->inspect_archive( $tmp );
        if ( is_wp_error( $inspect ) ) { wp_delete_file( $tmp ); return $inspect; }
        $token = wp_generate_password( 32, false, false );
        $key = 'npm9_import_' . get_current_user_id() . '_' . hash( 'sha256', $token );
        set_transient( $key, array( 'path' => $tmp, 'manifest' => $inspect['manifest'] ), self::TOKEN_TTL );
        return array(
            'token' => $token,
            'kind' => $inspect['manifest']['kind'] ?? 'post',
            'postCount' => count( $inspect['manifest']['posts'] ?? array() ),
            'mediaCount' => count( $inspect['manifest']['media'] ?? array() ),
            'includesMedia' => ! empty( $inspect['manifest']['options']['includes_media'] ),
            'context' => $inspect['manifest']['context'] ?? array(),
            'warnings' => $inspect['warnings'],
        );
    }

    public function apply_external_stage( $token, $mode = 'safe' ) {
        if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'permission', 'Permission denied.' ); }
        $token = sanitize_text_field( (string) $token );
        if ( ! $token ) { return new WP_Error( 'missing', 'Import session is missing. Preview the backup again.' ); }
        $mode = sanitize_key( $mode ); if ( ! in_array( $mode, array( 'safe', 'match_slug', 'duplicate' ), true ) ) { $mode = 'safe'; }
        $key = 'npm9_import_' . get_current_user_id() . '_' . hash( 'sha256', $token );
        $item = get_transient( $key ); delete_transient( $key );
        if ( ! is_array( $item ) || empty( $item['path'] ) || ! is_readable( $item['path'] ) ) { return new WP_Error( 'expired', 'Import session expired. Preview the backup again.' ); }
        $result = $this->restore_archive( $item['path'], $mode ); wp_delete_file( $item['path'] ); return $result;
    }

    public function ajax_backup_post() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify_post( $post_id );
        $with_media = ! empty( $_POST['with_media'] );
        $result = $this->create_archive( [ $post_id ], 'post', $with_media, [] );
        if ( is_wp_error( $result ) ) { wp_send_json_error( [ 'message' => $result->get_error_message() ] ); }
        wp_send_json_success( $result );
    }

    public function ajax_backup_category() {
        check_ajax_referer( 'npm9_action', 'nonce' );
        $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : 'category';
        $term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
        $requested_post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'any';
        $with_media = ! empty( $_POST['with_media'] );
        if ( ! taxonomy_exists( $taxonomy ) || ! $term_id ) {
            wp_send_json_error( [ 'message' => 'Choose a valid category/term.' ] );
        }
        if ( 'any' === $requested_post_type ) {
            $post_types = [];
            foreach ( get_post_types( [ 'show_ui' => true ], 'names' ) as $candidate ) {
                if ( 'attachment' !== $candidate && is_object_in_taxonomy( $candidate, $taxonomy ) ) { $post_types[] = $candidate; }
            }
            if ( ! $post_types ) { wp_send_json_error( [ 'message' => 'No editable post types use this taxonomy.' ] ); }
        } else {
            if ( ! post_type_exists( $requested_post_type ) || ! is_object_in_taxonomy( $requested_post_type, $taxonomy ) ) {
                wp_send_json_error( [ 'message' => 'This post type does not use the selected category/term.' ] );
            }
            $post_types = [ $requested_post_type ];
        }
        $tax = get_taxonomy( $taxonomy );
        $assign_cap = $tax && ! empty( $tax->cap->assign_terms ) ? $tax->cap->assign_terms : 'edit_posts';
        if ( ! current_user_can( $assign_cap ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }
        $page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $q = new WP_Query( [
            'post_type' => $post_types,
            'post_status' => [ 'publish','draft','pending','private','future' ],
            'posts_per_page' => self::CATEGORY_CHUNK_POSTS,
            'paged' => $page,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'tax_query' => [[ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => [ $term_id ] ]],
        ] );
        $ids = array_values( array_filter( array_map( 'absint', (array) $q->posts ), static function( $id ) { return current_user_can( 'edit_post', $id ); } ) );
        $page_count = max( 1, (int) $q->max_num_pages );
        if ( ! $ids && 1 === $page_count ) { wp_send_json_error( [ 'message' => 'No editable posts were found in this category/term.' ] ); }
        $term = get_term( $term_id, $taxonomy );
        $context = is_wp_error( $term ) ? [] : [ 'taxonomy' => $taxonomy, 'term_id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name, 'post_type' => 'any' === $requested_post_type ? 'any' : $requested_post_type, 'post_types' => $post_types, 'part' => $page, 'parts' => $page_count ];
        if ( $ids ) {
            $result = $this->create_archive( $ids, 'category', $with_media, $context );
            if ( is_wp_error( $result ) ) { wp_send_json_error( [ 'message' => $result->get_error_message() ] ); }
        } else {
            $result = [ 'downloadUrl' => '', 'filename' => '', 'postCount' => 0, 'mediaCount' => 0, 'size' => 0, 'includesMedia' => (bool) $with_media ];
        }
        $result['page'] = $page;
        $result['pageCount'] = $page_count;
        $result['hasMore'] = $page < $page_count;
        $result['totalMatching'] = (int) $q->found_posts;
        wp_send_json_success( $result );
    }

    private function create_archive( array $post_ids, $kind, $with_media, array $context ) {
        $post_ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) ), 0, self::MAX_POSTS );
        if ( ! $post_ids ) { return new WP_Error( 'empty', 'Nothing to back up.' ); }
        $label = 'post' === $kind ? 'post-' . $post_ids[0] : ( ! empty( $context['slug'] ) ? $context['slug'] : 'category' );
        if ( 'category' === $kind && ! empty( $context['part'] ) ) { $label .= '-part-' . absint( $context['part'] ); }
        $path = $this->temp_archive_path( $label );
        if ( is_wp_error( $path ) ) { return $path; }
        $zip = NPM9_Portable_Archive::create( $path );
        if ( is_wp_error( $zip ) ) { wp_delete_file( $path ); return $zip; }

        $manifest = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'kind' => sanitize_key( $kind ),
            'generated_at' => current_time( 'mysql' ),
            'source' => [ 'site_url' => site_url( '/' ), 'home_url' => home_url( '/' ), 'wordpress_version' => get_bloginfo( 'version' ) ],
            'options' => [ 'includes_media' => (bool) $with_media ],
            'context' => $context,
            'posts' => [],
            'media' => [],
            'warnings' => [],
        ];
        $media_ids = [];
        $post_media_map = [];
        foreach ( $post_ids as $post_id ) {
            $package = $this->native_post_package( $post_id );
            if ( is_wp_error( $package ) ) { continue; }
            $json = wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            $file = 'posts/post-' . $post_id . '.json';
            if ( ! $zip->addFromString( $file, $json ) ) { $manifest['warnings'][] = 'Could not add post #' . $post_id . ' to the archive.'; continue; }
            $ids = $with_media ? $this->collect_media_ids( $post_id, $package ) : [];
            $post_media_map[ $post_id ] = $ids;
            foreach ( $ids as $id ) { $media_ids[ $id ] = true; }
            $manifest['posts'][] = [
                'source_id' => $post_id,
                'post_type' => $package['post']['post_type'],
                'slug' => $package['post']['post_name'],
                'title' => $package['post']['post_title'],
                'url' => get_permalink( $post_id ),
                'file' => $file,
                'checksum' => hash( 'sha256', $json ),
                'media_ids' => $ids,
            ];
        }

        if ( $with_media ) {
            $count = 0; $media_bytes = 0; $omitted = 0;
            foreach ( array_keys( $media_ids ) as $attachment_id ) {
                if ( ++$count > self::MAX_MEDIA ) { $omitted++; continue; }
                $media = $this->media_manifest_item( $attachment_id );
                if ( ! $media || empty( $media['absolute_path'] ) || ! is_readable( $media['absolute_path'] ) ) { continue; }
                $file_size = (int) @filesize( $media['absolute_path'] );
                if ( $file_size <= 0 || $file_size > self::MAX_MEDIA_FILE_BYTES || ( $media_bytes + $file_size ) > self::MAX_MEDIA_TOTAL_BYTES ) { $omitted++; continue; }
                $archive_name = 'media/' . $attachment_id . '-' . sanitize_file_name( wp_basename( $media['absolute_path'] ) );
                if ( ! $zip->addFile( $media['absolute_path'], $archive_name ) ) { $omitted++; continue; }
                $media_bytes += $file_size;
                $media['file'] = $archive_name;
                $media['size'] = $file_size;
                $media['checksum'] = hash_file( 'sha256', $media['absolute_path'] );
                unset( $media['absolute_path'] );
                $manifest['media'][] = $media;
            }
            if ( $omitted ) { $manifest['warnings'][] = $omitted . ' media item(s) were omitted because of the per-file/total archive safety limits. The post data is still complete.'; }
        }
        $manifest_json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( ! $zip->addFromString( 'manifest.json', $manifest_json ) ) { $zip->close(); wp_delete_file( $path ); return new WP_Error( 'manifest_write', 'WordPress could not write the backup manifest.' ); }
        $closed = $zip->close();
        if ( ! $closed ) { wp_delete_file( $path ); return new WP_Error( 'zip_close', 'WordPress could not finish writing the backup archive.' ); }
        if ( ! is_readable( $path ) || filesize( $path ) < 100 ) { wp_delete_file( $path ); return new WP_Error( 'zip_empty', 'The generated backup archive is invalid.' ); }
        $is_data_backup = ! empty( $context['nine10_data_backup'] );
        $prefix = $is_data_backup ? '9data-' : '9pm-';
        $suffix = $is_data_backup ? '.9data.zip' : '.9post.zip';
        $filename = $prefix . sanitize_file_name( $label ) . '-' . gmdate( 'Y-m-d-His' ) . ( $with_media ? '-with-images' : '-data-only' ) . $suffix;
        $token = $this->store_download_token( $path, $filename );
        return [
            'downloadUrl' => $token['url'],
            'filename' => $filename,
            'postCount' => count( $manifest['posts'] ),
            'mediaCount' => count( $manifest['media'] ),
            'size' => filesize( $path ),
            'includesMedia' => (bool) $with_media,
            'warnings' => (array) ( $manifest['warnings'] ?? [] ),
        ];
    }

    private function native_post_package( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) { return new WP_Error( 'missing_post', 'Post not found.' ); }
        $author = get_userdata( $post->post_author );
        $taxonomies = [];
        foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
            $terms = wp_get_object_terms( $post_id, $taxonomy->name );
            if ( is_wp_error( $terms ) ) { continue; }
            $rows = [];
            foreach ( $terms as $term ) {
                $parent_slug = '';
                $parent_chain = [];
                if ( $term->parent ) {
                    $parent = get_term( $term->parent, $taxonomy->name );
                    if ( $parent && ! is_wp_error( $parent ) ) { $parent_slug = $parent->slug; }
                    $ancestor_ids = array_reverse( get_ancestors( $term->term_id, $taxonomy->name, 'taxonomy' ) );
                    foreach ( $ancestor_ids as $ancestor_id ) {
                        $ancestor = get_term( $ancestor_id, $taxonomy->name );
                        if ( $ancestor && ! is_wp_error( $ancestor ) ) { $parent_chain[] = [ 'slug' => $ancestor->slug, 'name' => $ancestor->name ]; }
                    }
                }
                $rows[] = [ 'slug' => $term->slug, 'name' => $term->name, 'parent_slug' => $parent_slug, 'parent_chain' => $parent_chain ];
            }
            $taxonomies[ $taxonomy->name ] = $rows;
        }
        $meta = [];
        foreach ( get_post_meta( $post_id ) as $key => $values ) {
            if ( $this->volatile_meta_key( $key ) ) { continue; }
            if ( ! apply_filters( 'npm9_backup_include_meta', true, $key, $post_id ) ) { continue; }
            $decoded = array_map( 'maybe_unserialize', $values );
            $meta[ $key ] = [ 'multiple' => count( $decoded ) > 1, 'values' => $decoded ];
        }
        $extra = apply_filters( 'npm9_backup_post_extra', [], $post_id );
        if ( ! is_array( $extra ) ) { $extra = []; }
        return [
            'format' => '9pm-native-post',
            'version' => 1,
            'source' => [ 'site_url' => site_url( '/' ), 'post_id' => (int) $post_id, 'url' => get_permalink( $post_id ) ?: '', 'fingerprint' => hash( 'sha256', untrailingslashit( site_url( '/' ) ) . '|' . (int) $post_id ) ],
            'post' => [
                'post_type' => $post->post_type,
                'post_title' => $post->post_title,
                'post_name' => $post->post_name,
                'post_status' => $post->post_status,
                'post_excerpt' => $post->post_excerpt,
                'post_content' => $post->post_content,
                'post_date' => $post->post_date,
                'post_date_gmt' => $post->post_date_gmt,
                'post_parent_source_id' => (int) $post->post_parent,
                'menu_order' => (int) $post->menu_order,
                'comment_status' => $post->comment_status,
                'ping_status' => $post->ping_status,
                'post_password' => $post->post_password,
                'author' => [ 'source_id' => (int) $post->post_author, 'login' => $author ? $author->user_login : '', 'email' => $author ? $author->user_email : '', 'display_name' => $author ? $author->display_name : '' ],
                'featured_image_source_id' => (int) get_post_thumbnail_id( $post_id ),
                'page_template' => get_page_template_slug( $post_id ),
                'post_format' => get_post_format( $post_id ) ?: '',
                'sticky' => 'post' === $post->post_type ? is_sticky( $post_id ) : false,
            ],
            'taxonomies' => $taxonomies,
            'native_field_schema' => class_exists( 'Nine_Post_Manager_Fields' ) ? Nine_Post_Manager_Fields::instance()->definitions_for_post_type( $post->post_type ) : [],
            'meta' => $meta,
            // Opt-in extension point for plugins whose per-post data is not stored in post meta.
            'extra' => $extra,
        ];
    }

    private function volatile_meta_key( $key ) {
        if ( in_array( $key, [ '_edit_lock','_edit_last','_thumbnail_id','_wp_page_template','_wp_old_slug','_wp_trash_meta_status','_wp_trash_meta_time','_wp_desired_post_slug','_pingme','_encloseme' ], true ) ) { return true; }
        foreach ( [ '_npm9_snapshot', '_npm9_9cf_import_history', '_npm9_phone', '_npm9_restore_', '_oembed_' ] as $prefix ) {
            if ( 0 === strpos( $key, $prefix ) ) { return true; }
        }
        return false;
    }

    private function collect_media_ids( $post_id, array $package ) {
        $ids = [];
        $featured = absint( $package['post']['featured_image_source_id'] ?? 0 );
        if ( $featured && 'attachment' === get_post_type( $featured ) ) { $ids[ $featured ] = true; }
        $content = (string) ( $package['post']['post_content'] ?? '' );
        if ( preg_match_all( '/wp-image-(\d+)/', $content, $m ) ) { foreach ( $m[1] as $id ) { $id = absint( $id ); if ( 'attachment' === get_post_type( $id ) ) { $ids[ $id ] = true; } } }
        if ( preg_match_all( '/https?:\/\/[^\s"\'<>]+/', $content, $urls ) ) {
            foreach ( $urls[0] as $url ) { $id = attachment_url_to_postid( html_entity_decode( $url ) ); if ( $id ) { $ids[ $id ] = true; } }
        }
        $this->collect_attachment_ids_recursive( $package['meta'] ?? [], $ids, 0 );
        $children = get_children( [ 'post_parent' => $post_id, 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'numberposts' => self::MAX_MEDIA ] );
        foreach ( (array) $children as $id ) { $id = absint( $id ); if ( $id ) { $ids[ $id ] = true; } }
        return array_slice( array_keys( $ids ), 0, self::MAX_MEDIA );
    }

    private function collect_attachment_ids_recursive( $value, array &$ids, $depth ) {
        if ( $depth > 6 || count( $ids ) >= self::MAX_MEDIA ) { return; }
        if ( is_array( $value ) ) { foreach ( $value as $v ) { $this->collect_attachment_ids_recursive( $v, $ids, $depth + 1 ); } return; }
        if ( is_object( $value ) ) { $this->collect_attachment_ids_recursive( (array) $value, $ids, $depth + 1 ); return; }
        if ( is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^\d+$/D', $value ) ) ) {
            $id = absint( $value ); if ( $id && 'attachment' === get_post_type( $id ) ) { $ids[ $id ] = true; } return;
        }
        if ( is_string( $value ) && preg_match( '#^https?://#i', $value ) ) { $id = attachment_url_to_postid( $value ); if ( $id ) { $ids[ $id ] = true; } }
    }

    private function media_manifest_item( $attachment_id ) {
        $post = get_post( $attachment_id );
        $path = get_attached_file( $attachment_id );
        if ( ! $post || 'attachment' !== $post->post_type || ! $path ) { return null; }
        $mime = get_post_mime_type( $attachment_id ) ?: 'application/octet-stream';
        $variants = [];
        if ( 0 === strpos( $mime, 'image/' ) ) {
            $metadata = wp_get_attachment_metadata( $attachment_id );
            foreach ( array_keys( (array) ( is_array( $metadata ) ? ( $metadata['sizes'] ?? [] ) : [] ) ) as $size_name ) {
                $src = wp_get_attachment_image_src( $attachment_id, $size_name );
                if ( $src && ! empty( $src[0] ) ) { $variants[ sanitize_key( $size_name ) ] = $src[0]; }
            }
        }
        return [
            'source_id' => (int) $attachment_id,
            'url' => wp_get_attachment_url( $attachment_id ) ?: '',
            'original_name' => wp_basename( $path ),
            'variants' => $variants,
            'mime' => $mime,
            'title' => $post->post_title,
            'caption' => $post->post_excerpt,
            'description' => $post->post_content,
            'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
            'absolute_path' => $path,
        ];
    }

    public function ajax_import_preview() {
        check_ajax_referer( 'npm9_action', 'nonce' );
        $result = $this->stage_external_upload( isset( $_FILES['backup'] ) ? $_FILES['backup'] : array() );
        if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 'permission' === $result->get_error_code() ? 403 : 400 ); }
        wp_send_json_success( $result );
    }

    private function inspect_archive( $path ) {
        $zip = NPM9_Portable_Archive::open_read( $path );
        if ( is_wp_error( $zip ) ) { return $zip; }
        $manifest_size = $zip->getEntrySize( 'manifest.json' );
        if ( $manifest_size < 0 || $manifest_size > self::MAX_MANIFEST_BYTES ) { $zip->close(); return new WP_Error( 'manifest_size', 'Backup manifest is missing or exceeds the safe size limit.' ); }
        $raw = $zip->getFromName( 'manifest.json' );
        if ( false === $raw ) { $zip->close(); return new WP_Error( 'manifest_missing', 'This archive is not a 9 Post Editor backup.' ); }
        $manifest = json_decode( $raw, true );
        if ( ! is_array( $manifest ) || self::FORMAT !== ( $manifest['format'] ?? '' ) || self::VERSION !== absint( $manifest['version'] ?? 0 ) ) { $zip->close(); return new WP_Error( 'manifest_invalid', 'Unsupported 9 Post Editor backup format.' ); }
        $warnings = (array) ( $manifest['warnings'] ?? [] );
        if ( count( $manifest['media'] ?? [] ) > self::MAX_MEDIA ) { $zip->close(); return new WP_Error( 'media_count', 'Backup contains too many media entries.' ); }
        $declared_media_bytes = 0;
        foreach ( (array) ( $manifest['media'] ?? [] ) as $media ) {
            $file = (string) ( $media['file'] ?? '' );
            if ( ! $this->safe_zip_path( $file ) || false === $zip->locateName( $file ) ) { $zip->close(); return new WP_Error( 'media_file_missing', 'A media file is missing from the backup.' ); }
            $entry_size = $zip->getEntrySize( $file );
            if ( $entry_size > self::MAX_MEDIA_FILE_BYTES ) { $zip->close(); return new WP_Error( 'media_too_large', 'A media file exceeds the 64 MB restore safety limit.' ); }
            if ( $entry_size > 0 ) { $declared_media_bytes += $entry_size; }
            if ( $declared_media_bytes > self::MAX_MEDIA_TOTAL_BYTES ) { $zip->close(); return new WP_Error( 'media_total_too_large', 'Media payload exceeds the safe restore size for one archive.' ); }
        }
        if ( count( $manifest['posts'] ?? [] ) > self::MAX_POSTS ) { $warnings[] = 'Only the first ' . self::MAX_POSTS . ' posts can be restored in one archive.'; }
        foreach ( array_slice( (array) ( $manifest['posts'] ?? [] ), 0, self::MAX_POSTS ) as $p ) {
            $file = $p['file'] ?? '';
            if ( ! $this->safe_zip_path( $file ) || false === $zip->locateName( $file ) ) { $zip->close(); return new WP_Error( 'post_file_missing', 'A post data file is missing from the backup.' ); }
            $post_file_size = $zip->getEntrySize( $file );
            if ( $post_file_size < 0 || $post_file_size > self::MAX_POST_JSON_BYTES ) { $zip->close(); return new WP_Error( 'post_file_size', 'A post data file exceeds the safe restore size.' ); }
            $json = $zip->getFromName( $file );
            if ( false === $json ) { $zip->close(); return new WP_Error( 'post_file_read', 'A post data file could not be read.' ); }
            if ( ! empty( $p['checksum'] ) && ! hash_equals( (string) $p['checksum'], hash( 'sha256', $json ) ) ) { $zip->close(); return new WP_Error( 'checksum', 'A post data checksum failed. The backup may be damaged.' ); }
            $pt = sanitize_key( $p['post_type'] ?? '' );
            if ( $pt && ! post_type_exists( $pt ) ) { $warnings[] = 'Post type “' . $pt . '” is not currently registered. Those items cannot be restored until its plugin/theme is active.'; }
        }
        $zip->close();
        return [ 'manifest' => $manifest, 'warnings' => array_values( array_unique( $warnings ) ) ];
    }

    private function safe_zip_path( $path ) {
        $path = str_replace( '\\', '/', (string) $path );
        return '' !== $path && false === strpos( $path, '../' ) && '/' !== substr( $path, 0, 1 );
    }

    public function ajax_import_apply() {
        check_ajax_referer( 'npm9_action', 'nonce' );
        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'safe';
        $result = $this->apply_external_stage( $token, $mode );
        if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 'permission' === $result->get_error_code() ? 403 : 400 ); }
        wp_send_json_success( $result );
    }

    private function restore_archive( $path, $mode ) {
        if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'permission', 'Permission denied.' ); }
        $this->restore_media_replacements = [];
        $zip = NPM9_Portable_Archive::open_read( $path );
        if ( is_wp_error( $zip ) ) { return $zip; }
        $manifest_size = $zip->getEntrySize( 'manifest.json' );
        if ( $manifest_size < 0 || $manifest_size > self::MAX_MANIFEST_BYTES ) { $zip->close(); return new WP_Error( 'manifest_size', 'Backup manifest is missing or exceeds the safe size limit.' ); }
        $manifest_raw = $zip->getFromName( 'manifest.json' );
        $manifest = is_string( $manifest_raw ) ? json_decode( $manifest_raw, true ) : null;
        if ( ! is_array( $manifest ) ) { $zip->close(); return new WP_Error( 'manifest', 'Manifest missing.' ); }
        $packages = [];
        foreach ( array_slice( (array) ( $manifest['posts'] ?? [] ), 0, self::MAX_POSTS ) as $entry ) {
            $file = $entry['file'] ?? '';
            if ( ! $this->safe_zip_path( $file ) ) { continue; }
            $entry_size = $zip->getEntrySize( $file );
            if ( $entry_size < 0 || $entry_size > self::MAX_POST_JSON_BYTES ) { continue; }
            $package_raw = $zip->getFromName( $file );
            $package = is_string( $package_raw ) ? json_decode( $package_raw, true ) : null;
            if ( is_array( $package ) && '9pm-native-post' === ( $package['format'] ?? '' ) ) { $packages[ absint( $entry['source_id'] ?? 0 ) ] = $package; }
        }
        if ( ! $packages ) { $zip->close(); return new WP_Error( 'empty', 'No valid posts were found in this backup.' ); }

        $post_map = [];
        $created = 0; $updated = 0; $skipped = 0; $warnings = [];
        foreach ( $packages as $source_id => $package ) {
            $p = $package['post'] ?? [];
            $post_type = sanitize_key( $p['post_type'] ?? 'post' );
            if ( ! post_type_exists( $post_type ) ) { $skipped++; $warnings[] = 'Skipped “' . ( $p['post_title'] ?? '' ) . '”: missing post type ' . $post_type . '.'; continue; }
            if ( ! empty( $package['native_field_schema'] ) && class_exists( 'Nine_Post_Manager_Fields' ) ) {
                if ( current_user_can( 'manage_options' ) ) { Nine_Post_Manager_Fields::instance()->merge_definitions_from_backup( $post_type, (array) $package['native_field_schema'] ); }
                else { $warnings[] = 'Native Post Manager field definitions were not added because your account cannot change site field definitions.'; }
            }
            $target_id = 0;
            $source_site = untrailingslashit( (string) ( $package['source']['site_url'] ?? '' ) );
            $source_fingerprint = (string) ( $package['source']['fingerprint'] ?? '' );
            if ( ! $source_fingerprint && $source_site && $source_id ) { $source_fingerprint = hash( 'sha256', $source_site . '|' . $source_id ); }
            if ( 'duplicate' !== $mode ) {
                if ( $source_fingerprint ) {
                    $matched = get_posts( [ 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_npm9_restore_source_key', 'meta_value' => $source_fingerprint ] );
                    if ( $matched ) { $target_id = (int) $matched[0]; }
                }
                if ( ! $target_id && $source_site && $source_site === untrailingslashit( site_url( '/' ) ) && $source_id && get_post( $source_id ) && get_post_type( $source_id ) === $post_type ) { $target_id = $source_id; }
                if ( ! $target_id && 'match_slug' === $mode && ! empty( $p['post_name'] ) ) {
                    $existing = get_page_by_path( sanitize_title( $p['post_name'] ), OBJECT, $post_type );
                    if ( $existing ) { $target_id = (int) $existing->ID; }
                }
            }
            $author_id = $this->resolve_author( $p['author'] ?? [] );
            $args = [
                'post_type' => $post_type,
                'post_title' => sanitize_text_field( $p['post_title'] ?? '' ),
                'post_name' => sanitize_title( $p['post_name'] ?? '' ),
                'post_status' => $this->allowed_status( $p['post_status'] ?? 'draft', $post_type ),
                'post_excerpt' => wp_kses_post( $p['post_excerpt'] ?? '' ),
                'post_content' => '', // media URLs/IDs are remapped in second pass.
                'menu_order' => intval( $p['menu_order'] ?? 0 ),
                'comment_status' => in_array( $p['comment_status'] ?? '', [ 'open','closed' ], true ) ? $p['comment_status'] : 'closed',
                'ping_status' => in_array( $p['ping_status'] ?? '', [ 'open','closed' ], true ) ? $p['ping_status'] : 'closed',
                'post_password' => sanitize_text_field( $p['post_password'] ?? '' ),
                'post_author' => $author_id,
            ];
            if ( ! empty( $p['post_date'] ) ) { $args['post_date'] = sanitize_text_field( $p['post_date'] ); }
            if ( $target_id ) {
                if ( ! current_user_can( 'edit_post', $target_id ) ) { $skipped++; continue; }
                $args['ID'] = $target_id;
                $id = wp_update_post( wp_slash( $args ), true );
                if ( ! is_wp_error( $id ) ) { $updated++; }
            } else {
                $pt = get_post_type_object( $post_type );
                $cap = $pt && ! empty( $pt->cap->create_posts ) ? $pt->cap->create_posts : 'edit_posts';
                if ( ! current_user_can( $cap ) ) { $skipped++; continue; }
                $id = wp_insert_post( wp_slash( $args ), true );
                if ( ! is_wp_error( $id ) ) { $created++; }
            }
            if ( is_wp_error( $id ) ) { $skipped++; $warnings[] = $id->get_error_message(); continue; }
            $post_map[ $source_id ] = (int) $id;
            if ( $source_fingerprint ) { update_post_meta( $id, '_npm9_restore_source_key', $source_fingerprint ); }
            if ( $source_site ) { update_post_meta( $id, '_npm9_restore_source_site', esc_url_raw( $source_site ) ); }
            if ( ! empty( $package['source']['url'] ) ) { update_post_meta( $id, '_npm9_restore_source_url', esc_url_raw( $package['source']['url'] ) ); }
            if ( ! empty( $p['post_parent_source_id'] ) && $source_site ) { update_post_meta( $id, '_npm9_restore_parent_source_key', hash( 'sha256', $source_site . '|' . absint( $p['post_parent_source_id'] ) ) ); }
        }

        $media_map = [];
        if ( ! empty( $manifest['options']['includes_media'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            foreach ( array_slice( (array) ( $manifest['media'] ?? [] ), 0, self::MAX_MEDIA ) as $media ) {
                $source_media_id = absint( $media['source_id'] ?? 0 );
                $file = $media['file'] ?? '';
                if ( ! $source_media_id || ! $this->safe_zip_path( $file ) ) { continue; }
                if ( ! empty( $media['url'] ) ) {
                    $existing = attachment_url_to_postid( $media['url'] );
                    if ( $existing ) { $media_map[ $source_media_id ] = $existing; $this->register_media_replacements( $media, $existing ); continue; }
                }
                $entry_size = $zip->getEntrySize( $file );
                if ( $entry_size > self::MAX_MEDIA_FILE_BYTES ) { $warnings[] = 'Skipped oversized media item: ' . wp_basename( $file ); continue; }
                $bytes = $zip->getFromName( $file );
                if ( false === $bytes ) { continue; }
                if ( ! empty( $media['checksum'] ) && ! hash_equals( (string) $media['checksum'], hash( 'sha256', $bytes ) ) ) { $warnings[] = 'Skipped a damaged media item: ' . wp_basename( $file ); continue; }
                $checksum = hash( 'sha256', $bytes );
                $existing_by_hash = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_npm9_backup_checksum', 'meta_value' => $checksum ] );
                if ( $existing_by_hash ) {
                    $media_map[ $source_media_id ] = (int) $existing_by_hash[0];
                    $this->register_media_replacements( $media, (int) $existing_by_hash[0] );
                    continue;
                }
                $tmp = wp_tempnam( wp_basename( $file ) );
                if ( ! $tmp || false === file_put_contents( $tmp, $bytes ) ) { continue; }
                $parent = 0;
                foreach ( (array) ( $manifest['posts'] ?? [] ) as $pentry ) { if ( in_array( $source_media_id, array_map( 'absint', (array) ( $pentry['media_ids'] ?? [] ) ), true ) ) { $parent = $post_map[ absint( $pentry['source_id'] ?? 0 ) ] ?? 0; if ( $parent ) { break; } } }
                $file_array = [ 'name' => sanitize_file_name( $media['original_name'] ?? wp_basename( $file ) ), 'tmp_name' => $tmp ];
                $new_id = media_handle_sideload( $file_array, $parent, sanitize_text_field( $media['title'] ?? '' ), [ 'post_excerpt' => wp_kses_post( $media['caption'] ?? '' ), 'post_content' => wp_kses_post( $media['description'] ?? '' ) ] );
                if ( is_wp_error( $new_id ) ) { wp_delete_file( $tmp ); $warnings[] = $new_id->get_error_message(); continue; }
                update_post_meta( $new_id, '_npm9_backup_checksum', $checksum );
                if ( isset( $media['alt'] ) ) { update_post_meta( $new_id, '_wp_attachment_image_alt', sanitize_text_field( $media['alt'] ) ); }
                $media_map[ $source_media_id ] = (int) $new_id;
                $this->register_media_replacements( $media, (int) $new_id );
            }
        }

        foreach ( $packages as $source_id => $package ) {
            $target_id = $post_map[ $source_id ] ?? 0;
            if ( ! $target_id ) { continue; }
            $p = $package['post'] ?? [];
            $content = $this->remap_value( (string) ( $p['post_content'] ?? '' ), $media_map, $manifest, $post_map, false );
            $content = $this->remap_content_media_blocks( $content, $media_map );
            wp_update_post( wp_slash( [ 'ID' => $target_id, 'post_content' => $content ] ) );
            $featured_source = absint( $p['featured_image_source_id'] ?? 0 );
            if ( $featured_source && ! empty( $media_map[ $featured_source ] ) ) { set_post_thumbnail( $target_id, $media_map[ $featured_source ] ); }
            elseif ( ! $featured_source ) { delete_post_thumbnail( $target_id ); }

            $registered_meta = function_exists( 'get_registered_meta_keys' ) ? get_registered_meta_keys( 'post', get_post_type( $target_id ) ) : [];
            foreach ( (array) ( $package['meta'] ?? [] ) as $meta_key => $meta_item ) {
                $meta_key = sanitize_key( $meta_key );
                if ( ! $meta_key || $this->volatile_meta_key( $meta_key ) ) { continue; }
                if ( ! apply_filters( 'npm9_backup_restore_meta_allowed', true, $meta_key, $target_id, $meta_item ) ) { $warnings[] = 'Skipped plugin field “' . $meta_key . '” by provider policy.'; continue; }
                $is_registered = isset( $registered_meta[ $meta_key ] );
                if ( $is_registered && ! current_user_can( 'edit_post_meta', $target_id, $meta_key ) ) {
                    $warnings[] = 'Skipped protected registered field “' . $meta_key . '”.';
                    continue;
                }
                $values = isset( $meta_item['values'] ) && is_array( $meta_item['values'] ) ? $meta_item['values'] : [ $meta_item ];
                delete_post_meta( $target_id, $meta_key );
                foreach ( $values as $value ) {
                    $value = $this->remap_meta_value( $meta_key, $value, $media_map, $manifest, $post_map );
                    if ( $is_registered ) { $value = sanitize_meta( $meta_key, $value, 'post', get_post_type( $target_id ) ); }
                    add_post_meta( $target_id, $meta_key, $value );
                }
            }
            $this->restore_terms( $target_id, $package['taxonomies'] ?? [], $warnings );
            if ( ! empty( $p['page_template'] ) && 'default' !== $p['page_template'] ) { update_post_meta( $target_id, '_wp_page_template', sanitize_text_field( $p['page_template'] ) ); }
            if ( ! empty( $p['post_format'] ) && current_theme_supports( 'post-formats' ) ) { set_post_format( $target_id, sanitize_key( $p['post_format'] ) ); }
            if ( 'post' === get_post_type( $target_id ) ) { ! empty( $p['sticky'] ) ? stick_post( $target_id ) : unstick_post( $target_id ); }
            /** Let an owning plugin restore opt-in per-post data stored outside post meta. */
            do_action( 'npm9_backup_restore_post_extra', $target_id, (array) ( $package['extra'] ?? [] ), $package, $post_map );
        }
        // Parent relationships are restored after all posts have target IDs.
        foreach ( $packages as $source_id => $package ) {
            $target_id = $post_map[ $source_id ] ?? 0;
            $parent_source = absint( $package['post']['post_parent_source_id'] ?? 0 );
            if ( $target_id && $parent_source && ! empty( $post_map[ $parent_source ] ) ) { wp_update_post( [ 'ID' => $target_id, 'post_parent' => $post_map[ $parent_source ] ] ); }
        }
        $source_sites = [];
        foreach ( $packages as $package ) { $site = untrailingslashit( (string) ( $package['source']['site_url'] ?? '' ) ); if ( $site ) { $source_sites[ $site ] = true; } }
        foreach ( array_keys( $source_sites ) as $source_site ) { $this->repair_cross_chunk_relationships( $source_site, $warnings ); }
        $zip->close();
        return [ 'message' => 'Backup restored.', 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'mediaRestored' => count( $media_map ), 'postMap' => $post_map, 'warnings' => array_slice( array_values( array_unique( $warnings ) ), 0, 30 ) ];
    }

    /**
     * Repair links and hierarchical parents after bite-sized category parts are restored.
     * Each imported post remembers its original source URL/key, so later parts can safely
     * repair earlier parts without needing one giant category archive.
     */
    private function repair_cross_chunk_relationships( $source_site, array &$warnings ) {
        $repair_post_types = array_values( array_diff( get_post_types( [ 'show_ui' => true ], 'names' ), [ 'attachment' ] ) );
        if ( ! $repair_post_types ) { return; }
        $ids = get_posts( [
            'post_type' => $repair_post_types, 'post_status' => 'any', 'posts_per_page' => 3000, 'fields' => 'ids',
            'meta_key' => '_npm9_restore_source_site', 'meta_value' => esc_url_raw( $source_site ),
            'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true,
        ] );
        if ( ! $ids ) { return; }
        if ( count( $ids ) >= 3000 ) { $warnings[] = 'Cross-part link repair was limited to 3,000 restored posts from this source site.'; }
        $url_map = []; $key_map = [];
        foreach ( $ids as $id ) {
            $old_url = (string) get_post_meta( $id, '_npm9_restore_source_url', true );
            $source_key = (string) get_post_meta( $id, '_npm9_restore_source_key', true );
            if ( $old_url ) { $url_map[ $old_url ] = get_permalink( $id ); }
            if ( $source_key ) { $key_map[ $source_key ] = (int) $id; }
        }
        if ( $url_map ) { uksort( $url_map, static function( $a, $b ) { return strlen( $b ) <=> strlen( $a ); } ); }
        foreach ( $ids as $id ) {
            $post = get_post( $id ); if ( ! $post ) { continue; }
            $content = (string) $post->post_content; $new_content = $content;
            foreach ( $url_map as $old => $new ) { if ( $old && $new ) { $new_content = str_replace( $old, $new, $new_content ); } }
            if ( $new_content !== $content && current_user_can( 'edit_post', $id ) ) { wp_update_post( wp_slash( [ 'ID' => $id, 'post_content' => $new_content ] ) ); }
            $parent_key = (string) get_post_meta( $id, '_npm9_restore_parent_source_key', true );
            if ( $parent_key && ! empty( $key_map[ $parent_key ] ) && current_user_can( 'edit_post', $id ) ) {
                if ( (int) $post->post_parent !== (int) $key_map[ $parent_key ] ) { wp_update_post( [ 'ID' => $id, 'post_parent' => (int) $key_map[ $parent_key ] ] ); }
            }
        }
    }

    private function resolve_author( $author ) {
        if ( ! is_array( $author ) ) { return get_current_user_id(); }
        if ( ! empty( $author['login'] ) ) { $u = get_user_by( 'login', sanitize_user( $author['login'] ) ); if ( $u ) { return (int) $u->ID; } }
        if ( ! empty( $author['email'] ) ) { $u = get_user_by( 'email', sanitize_email( $author['email'] ) ); if ( $u ) { return (int) $u->ID; } }
        return get_current_user_id();
    }

    private function allowed_status( $status, $post_type ) {
        $status = sanitize_key( $status );
        if ( ! in_array( $status, [ 'publish','draft','pending','private','future' ], true ) ) { return 'draft'; }
        if ( 'publish' === $status ) { $pt = get_post_type_object( $post_type ); $cap = $pt && ! empty( $pt->cap->publish_posts ) ? $pt->cap->publish_posts : 'publish_posts'; if ( ! current_user_can( $cap ) ) { return 'pending'; } }
        return $status;
    }

    private function restore_terms( $post_id, $taxonomies, array &$warnings ) {
        foreach ( (array) $taxonomies as $taxonomy => $terms ) {
            $taxonomy = sanitize_key( $taxonomy );
            if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( get_post_type( $post_id ), $taxonomy ) ) { $warnings[] = 'Skipped missing taxonomy ' . $taxonomy . '.'; continue; }
            $tax = get_taxonomy( $taxonomy );
            $assign_cap = $tax && ! empty( $tax->cap->assign_terms ) ? $tax->cap->assign_terms : 'edit_posts';
            if ( ! current_user_can( $assign_cap ) ) { continue; }
            $ids = [];
            foreach ( (array) $terms as $term_data ) {
                if ( ! is_array( $term_data ) ) { continue; }
                $parent_id = 0;
                foreach ( (array) ( $term_data['parent_chain'] ?? [] ) as $ancestor_data ) {
                    if ( ! is_array( $ancestor_data ) ) { continue; }
                    $ancestor_slug = sanitize_title( $ancestor_data['slug'] ?? '' );
                    $ancestor_name = sanitize_text_field( $ancestor_data['name'] ?? $ancestor_slug );
                    if ( ! $ancestor_slug && ! $ancestor_name ) { continue; }
                    $ancestor = $ancestor_slug ? get_term_by( 'slug', $ancestor_slug, $taxonomy ) : false;
                    if ( ! $ancestor && $ancestor_name ) { $ancestor = get_term_by( 'name', $ancestor_name, $taxonomy ); }
                    if ( ! $ancestor ) {
                        $created_ancestor = wp_insert_term( $ancestor_name ?: $ancestor_slug, $taxonomy, [ 'slug' => $ancestor_slug, 'parent' => $parent_id ] );
                        if ( is_wp_error( $created_ancestor ) ) { $warnings[] = $created_ancestor->get_error_message(); continue; }
                        $parent_id = (int) $created_ancestor['term_id'];
                    } else { $parent_id = (int) $ancestor->term_id; }
                }
                $slug = sanitize_title( $term_data['slug'] ?? '' );
                $name = sanitize_text_field( $term_data['name'] ?? $slug );
                if ( ! $slug && ! $name ) { continue; }
                $term = $slug ? get_term_by( 'slug', $slug, $taxonomy ) : false;
                if ( ! $term && $name ) { $term = get_term_by( 'name', $name, $taxonomy ); }
                if ( ! $term ) {
                    if ( ! $parent_id && ! empty( $term_data['parent_slug'] ) ) { $parent = get_term_by( 'slug', sanitize_title( $term_data['parent_slug'] ), $taxonomy ); if ( $parent ) { $parent_id = (int) $parent->term_id; } }
                    $created = wp_insert_term( $name ?: $slug, $taxonomy, [ 'slug' => $slug, 'parent' => $parent_id ] );
                    if ( is_wp_error( $created ) ) { $warnings[] = $created->get_error_message(); continue; }
                    $ids[] = (int) $created['term_id'];
                } else { $ids[] = (int) $term->term_id; }
            }
            wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), $taxonomy, false );
        }
    }

    /** Build old-original + old-thumbnail/srcset URL replacements for one restored attachment. */
    private function register_media_replacements( array $media, $new_id ) {
        $old_id = absint( $media['source_id'] ?? 0 );
        $new_id = absint( $new_id );
        if ( ! $old_id || ! $new_id ) { return; }
        $new_original = wp_get_attachment_url( $new_id );
        $map = [];
        if ( ! empty( $media['url'] ) && $new_original ) { $map[ (string) $media['url'] ] = $new_original; }
        foreach ( (array) ( $media['variants'] ?? [] ) as $size_name => $old_url ) {
            $old_url = (string) $old_url;
            if ( ! $old_url ) { continue; }
            $src = wp_get_attachment_image_src( $new_id, sanitize_key( $size_name ) );
            $map[ $old_url ] = $src && ! empty( $src[0] ) ? $src[0] : $new_original;
        }
        // Replace longer variant URLs before the original URL to avoid partial substitutions.
        uksort( $map, static function( $a, $b ) { return strlen( $b ) <=> strlen( $a ); } );
        $this->restore_media_replacements[ $old_id ] = $map;
    }

    private function meta_key_looks_like_media( $key ) {
        $key = strtolower( (string) $key );
        if ( '' === $key ) { return false; }
        return 1 === preg_match( '/(?:^|_)(?:image|media|attachment|thumbnail|thumb|photo|avatar|logo|icon|file|audio|video)(?:_id)?(?:$|_)/', $key );
    }

    private function remap_meta_value( $meta_key, $value, array $media_map, array $manifest, array $post_map = [] ) {
        return $this->remap_value( $value, $media_map, $manifest, $post_map, $this->meta_key_looks_like_media( $meta_key ) );
    }

    /**
     * Remap portable URLs/IDs without assuming that every number in arbitrary plugin data is a media ID.
     * Numeric media remapping is enabled only for a semantically media-like meta key (and descendants).
     */
    private function remap_value( $value, array $media_map, array $manifest, array $post_map = [], $allow_numeric_media = false ) {
        if ( is_array( $value ) ) {
            $out = [];
            foreach ( $value as $k => $v ) {
                $child_media = $allow_numeric_media || ( is_string( $k ) && $this->meta_key_looks_like_media( $k ) );
                $out[ $k ] = $this->remap_value( $v, $media_map, $manifest, $post_map, $child_media );
            }
            return $out;
        }
        if ( is_object( $value ) ) { return (object) $this->remap_value( (array) $value, $media_map, $manifest, $post_map, $allow_numeric_media ); }
        if ( is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^\d+$/D', $value ) ) ) {
            $id = absint( $value );
            if ( $allow_numeric_media && isset( $media_map[ $id ] ) ) { return is_int( $value ) ? $media_map[ $id ] : (string) $media_map[ $id ]; }
            return $value;
        }
        if ( ! is_string( $value ) ) { return $value; }
        $out = $value;
        foreach ( (array) ( $manifest['media'] ?? [] ) as $media ) {
            $old_id = absint( $media['source_id'] ?? 0 );
            if ( ! $old_id || empty( $media_map[ $old_id ] ) ) { continue; }
            $new_id = $media_map[ $old_id ];
            if ( empty( $this->restore_media_replacements[ $old_id ] ) ) { $this->register_media_replacements( $media, $new_id ); }
            foreach ( (array) ( $this->restore_media_replacements[ $old_id ] ?? [] ) as $old_url => $new_url ) {
                if ( $old_url && $new_url ) { $out = str_replace( $old_url, $new_url, $out ); }
            }
            $out = str_replace( 'wp-image-' . $old_id, 'wp-image-' . $new_id, $out );
            // Serialized/JSON plugin values: change only keys that explicitly describe media identifiers.
            $out = preg_replace( '/(["\'](?:image_id|imageId|media_id|mediaId|attachment_id|attachmentId|thumbnail_id|thumbnailId|photo_id|photoId|logo_id|logoId|icon_id|iconId|file_id|fileId|audio_id|audioId|video_id|videoId)["\']\s*:\s*)' . preg_quote( (string) $old_id, '/' ) . '(?=\s*[,}])/', '$1' . $new_id, $out );
        }
        foreach ( (array) ( $manifest['posts'] ?? [] ) as $post_entry ) {
            $old_id = absint( $post_entry['source_id'] ?? 0 );
            if ( ! $old_id || empty( $post_map[ $old_id ] ) ) { continue; }
            $old_url = (string) ( $post_entry['url'] ?? '' );
            $new_url = get_permalink( $post_map[ $old_id ] );
            if ( $old_url && $new_url ) { $out = str_replace( $old_url, $new_url, $out ); }
            $out = preg_replace( '/(["\'](?:post_id|page_id|postId|pageId)["\']\s*:\s*)' . preg_quote( (string) $old_id, '/' ) . '(?=\s*[,}])/', '$1' . $post_map[ $old_id ], $out );
        }
        return $out;
    }

    /** Remap IDs in actual Gutenberg media blocks rather than generic numeric values. */
    private function remap_content_media_blocks( $content, array $media_map ) {
        if ( ! $content || ! $media_map || ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) { return $content; }
        $blocks = parse_blocks( $content );
        if ( ! is_array( $blocks ) ) { return $content; }
        $this->remap_block_tree_media( $blocks, $media_map );
        return serialize_blocks( $blocks );
    }

    private function remap_block_tree_media( array &$blocks, array $media_map ) {
        foreach ( $blocks as &$block ) {
            if ( ! is_array( $block ) ) { continue; }
            $name = (string) ( $block['blockName'] ?? '' );
            $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
            $keys = [];
            if ( in_array( $name, [ 'core/image','core/cover','core/video','core/audio','core/file' ], true ) ) { $keys = [ 'id' ]; }
            elseif ( 'core/media-text' === $name ) { $keys = [ 'mediaId' ]; }
            elseif ( 0 === strpos( $name, 'nine/' ) || 0 === strpos( $name, '9/' ) ) { $keys = [ 'id','mediaId','imageId','attachmentId','fileId','audioId','videoId' ]; }
            else { $keys = [ 'mediaId','imageId','attachmentId','thumbnailId','fileId','audioId','videoId' ]; }
            foreach ( $keys as $key ) {
                if ( isset( $attrs[ $key ] ) && is_numeric( $attrs[ $key ] ) ) {
                    $old = absint( $attrs[ $key ] );
                    if ( isset( $media_map[ $old ] ) ) { $attrs[ $key ] = (int) $media_map[ $old ]; }
                }
            }
            if ( isset( $attrs['ids'] ) && is_array( $attrs['ids'] ) && ( 'core/gallery' === $name || false !== stripos( $name, 'gallery' ) ) ) {
                $attrs['ids'] = array_map( static function( $id ) use ( $media_map ) { $id = absint( $id ); return $media_map[ $id ] ?? $id; }, $attrs['ids'] );
            }
            $block['attrs'] = $attrs;
            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) { $this->remap_block_tree_media( $block['innerBlocks'], $media_map ); }
        }
        unset( $block );
    }

}
