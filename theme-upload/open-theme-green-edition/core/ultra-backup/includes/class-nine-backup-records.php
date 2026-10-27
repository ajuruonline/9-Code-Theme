<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Records exchange: export and import posts of every type, users with their
 * profiles, taxonomy terms and comments as Excel, CSV or JSON.
 *
 * Every format carries the same rows, so a file can be edited in a spreadsheet
 * and imported back. Records are matched by slug (posts, terms), login or email
 * (users) and post + date + author (comments); an id is used only when it points
 * at the same record. Imports are previewed first, and a JSON safety copy of the
 * affected records is saved before anything is written. Passwords, sessions,
 * capabilities and secret-looking keys are never exported or imported.
 *
 * The full-fidelity ZIP package (media files, menus, Elementor, ACF, plugins)
 * stays with Nine_Backup_Exporter and Nine_Backup_Importer.
 */
class Nine_Backup_Records {
    const CAP     = 'manage_options';
    const FORMAT  = 'open-scholar-records';
    const VERSION = 1;
    const SECRET  = '/(?:pass(?:word)?|secret|token|(?:^|_)auth(?:_|$)|auth_?key|2fa|totp|(?:^|_)otp(?:_|$)|recovery.?code|api.?key|payment.?key|license.?key|session)/i';
    const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

    /** Internal post types that are not records people keep. */
    private static $skip_post_types = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'acf-field', 'acf-field-group', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page', 'elementor_library' );
    /** Post meta that is WordPress bookkeeping, or handled by its own column. */
    private static $skip_post_meta = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_encloseme', '_pingme', '_thumbnail_id', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug', '_wp_attached_file', '_wp_attachment_metadata' );
    /** User meta that is a column of its own, or a per-screen preference. */
    private static $skip_user_meta = array( 'first_name', 'last_name', 'nickname', 'description', 'session_tokens', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl', 'show_admin_bar_front', 'locale', 'dismissed_wp_pointers', 'show_welcome_panel', 'community-events-location', 'default_password_nag' );

    public static function init() {
        add_action( 'admin_post_osch_records_export', array( __CLASS__, 'handle_export' ) );
        add_action( 'admin_post_osch_records_upload', array( __CLASS__, 'handle_upload' ) );
        add_action( 'admin_post_osch_records_import', array( __CLASS__, 'handle_import' ) );
        add_action( 'admin_post_osch_records_cancel', array( __CLASS__, 'handle_cancel' ) );
        add_action( 'admin_post_osch_records_download', array( __CLASS__, 'handle_download' ) );
    }

    /* ================================================================ Types */

    public static function types() {
        $types = array();
        foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $name => $object ) {
            if ( in_array( $name, self::$skip_post_types, true ) ) { continue; }
            $counts = wp_count_posts( $name );
            $total = 0;
            foreach ( self::STATUSES as $status ) { $total += isset( $counts->$status ) ? (int) $counts->$status : 0; }
            $types[ 'post:' . $name ] = array( 'label' => $object->labels->name, 'count' => $total );
        }
        $users = count_users();
        $types['users'] = array( 'label' => 'Users & profiles', 'count' => (int) $users['total_users'] );
        $terms = 0;
        foreach ( self::taxonomies() as $taxonomy ) {
            $count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
            $terms += is_wp_error( $count ) ? 0 : (int) $count;
        }
        $types['terms'] = array( 'label' => 'Categories & tags', 'count' => $terms );
        $comments = wp_count_comments();
        $types['comments'] = array( 'label' => 'Comments', 'count' => (int) $comments->approved + (int) $comments->moderated );
        return $types;
    }

    private static function taxonomies() {
        $taxonomies = get_taxonomies( array( 'show_ui' => true ), 'names' );
        unset( $taxonomies['nav_menu'], $taxonomies['link_category'], $taxonomies['wp_theme'], $taxonomies['wp_template_part_area'], $taxonomies['wp_pattern_category'] );
        return array_values( $taxonomies );
    }

    private static function post_type_allowed( $post_type ) {
        return $post_type && post_type_exists( $post_type ) && ! in_array( $post_type, self::$skip_post_types, true );
    }

    private static function user_meta_blocked( $key ) {
        global $wpdb;
        return in_array( $key, self::$skip_user_meta, true )
            || 0 === strpos( $key, $wpdb->base_prefix )
            || preg_match( self::SECRET, $key );
    }

    /* =============================================================== Export */

    /** Collect rows for the chosen types: array( group => rows ). */
    public static function collect( array $types ) {
        $groups = array();
        foreach ( $types as $type ) {
            if ( 0 === strpos( $type, 'post:' ) ) {
                $post_type = substr( $type, 5 );
                if ( self::post_type_allowed( $post_type ) ) { $groups[ in_array( $post_type, array( 'users', 'terms', 'comments' ), true ) ? 'type-' . $post_type : $post_type ] = self::post_rows( $post_type ); }
            } elseif ( 'users' === $type ) {
                $groups['users'] = self::user_rows();
            } elseif ( 'terms' === $type ) {
                $groups['terms'] = self::term_rows();
            } elseif ( 'comments' === $type ) {
                $groups['comments'] = self::comment_rows();
            }
        }
        return $groups;
    }

    private static function meta_columns( array $all, $kind ) {
        $out = array();
        ksort( $all );
        foreach ( $all as $key => $values ) {
            $key = (string) $key;
            if ( 'post' === $kind && ( in_array( $key, self::$skip_post_meta, true ) || preg_match( self::SECRET, $key ) ) ) { continue; }
            if ( 'user' === $kind && self::user_meta_blocked( $key ) ) { continue; }
            if ( 'term' === $kind && preg_match( self::SECRET, $key ) ) { continue; }
            if ( 'comment' === $kind && preg_match( self::SECRET, $key ) ) { continue; }
            $values = array_map( 'maybe_unserialize', (array) $values );
            if ( count( $values ) > 1 ) { $out[ 'meta[]:' . $key ] = array_values( $values ); }
            else { $out[ 'meta:' . $key ] = reset( $values ); }
        }
        return $out;
    }

    private static function post_rows( $post_type ) {
        $rows = array();
        $page = 1;
        do {
            $ids = get_posts( array( 'post_type' => $post_type, 'post_status' => self::STATUSES, 'posts_per_page' => 200, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true ) );
            foreach ( $ids as $id ) {
                $post = get_post( $id );
                if ( $post ) { $rows[] = self::post_row( $post ); }
            }
            $page++;
        } while ( 200 === count( $ids ) );
        return $rows;
    }

    private static function post_row( WP_Post $post ) {
        $author = $post->post_author ? get_userdata( $post->post_author ) : null;
        $parent = $post->post_parent ? get_post( $post->post_parent ) : null;
        $terms  = array();
        foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
            $slugs = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'slugs' ) );
            if ( ! is_wp_error( $slugs ) && $slugs ) { $terms[ $taxonomy ] = array_values( $slugs ); }
        }
        $thumb = get_post_thumbnail_id( $post );
        return array(
            'record_type'    => 'post',
            'id'             => $post->ID,
            'post_type'      => $post->post_type,
            'status'         => $post->post_status,
            'title'          => $post->post_title,
            'slug'           => $post->post_name,
            'date'           => $post->post_date,
            'modified'       => $post->post_modified,
            'author'         => $author ? $author->user_login : '',
            'parent'         => $parent ? $parent->post_name : '',
            'menu_order'     => (int) $post->menu_order,
            'comment_status' => $post->comment_status,
            'excerpt'        => $post->post_excerpt,
            'content'        => $post->post_content,
            'terms'          => $terms,
            'featured_image' => $thumb ? (string) wp_get_attachment_url( $thumb ) : '',
        ) + self::meta_columns( get_post_meta( $post->ID ), 'post' );
    }

    private static function user_rows() {
        $rows = array();
        $query = new WP_User_Query( array( 'orderby' => 'ID', 'order' => 'ASC', 'number' => -1, 'count_total' => false ) );
        foreach ( $query->get_results() as $user ) {
            $rows[] = array(
                'record_type'  => 'user',
                'id'           => $user->ID,
                'login'        => $user->user_login,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'nickname'     => $user->nickname,
                'description'  => $user->description,
                'url'          => $user->user_url,
                'registered'   => $user->user_registered,
                'roles'        => implode( ',', (array) $user->roles ),
            ) + self::meta_columns( get_user_meta( $user->ID ), 'user' );
        }
        return $rows;
    }

    private static function term_rows() {
        $rows = array();
        foreach ( self::taxonomies() as $taxonomy ) {
            $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC' ) );
            if ( is_wp_error( $terms ) ) { continue; }
            foreach ( $terms as $term ) {
                $parent = $term->parent ? get_term( $term->parent, $taxonomy ) : null;
                $rows[] = array(
                    'record_type' => 'term',
                    'id'          => $term->term_id,
                    'taxonomy'    => $taxonomy,
                    'name'        => $term->name,
                    'slug'        => $term->slug,
                    'parent'      => ( $parent && ! is_wp_error( $parent ) ) ? $parent->slug : '',
                    'description' => $term->description,
                ) + self::meta_columns( get_term_meta( $term->term_id ), 'term' );
            }
        }
        return $rows;
    }

    private static function comment_rows() {
        $rows = array();
        $comments = get_comments( array( 'status' => 'all', 'orderby' => 'comment_ID', 'order' => 'ASC', 'number' => 0, 'type' => 'all' ) );
        foreach ( $comments as $comment ) {
            $post = get_post( $comment->comment_post_ID );
            if ( ! $post ) { continue; }
            $user = $comment->user_id ? get_userdata( $comment->user_id ) : null;
            $rows[] = array(
                'record_type'  => 'comment',
                'id'           => (int) $comment->comment_ID,
                'post_type'    => $post->post_type,
                'post_slug'    => $post->post_name,
                'parent'       => (int) $comment->comment_parent,
                'author'       => $comment->comment_author,
                'author_email' => $comment->comment_author_email,
                'author_url'   => $comment->comment_author_url,
                'user'         => $user ? $user->user_login : '',
                'date'         => $comment->comment_date,
                'status'       => wp_get_comment_status( $comment ),
                'type'         => $comment->comment_type,
                'content'      => $comment->comment_content,
            ) + self::meta_columns( get_comment_meta( $comment->comment_ID ), 'comment' );
        }
        return $rows;
    }

    /** One cell value for CSV or Excel. Arrays and objects become JSON. */
    private static function cell( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) { return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
        if ( is_bool( $value ) ) { return $value ? '1' : '0'; }
        return null === $value ? '' : $value;
    }

    /** Rows of a group as a table: header row first, every column present in every row. */
    private static function table( array $rows ) {
        $columns = array();
        foreach ( $rows as $row ) { foreach ( array_keys( $row ) as $key ) { $columns[ $key ] = true; } }
        $columns = array_keys( $columns );
        $table = array( $columns );
        foreach ( $rows as $row ) {
            $line = array();
            foreach ( $columns as $column ) { $line[] = self::cell( array_key_exists( $column, $row ) ? $row[ $column ] : '' ); }
            $table[] = $line;
        }
        return $table;
    }

    /** Write rows to a file in the protected archive folder. Returns array( path, filename ) or WP_Error. */
    public static function write( array $groups, $format, $label = 'records' ) {
        Nine_Backup_Exporter::ensure_dirs();
        $base = 'open-scholar-' . sanitize_title( $label ) . '-' . wp_date( 'Y-m-d-His' ) . '-' . strtolower( wp_generate_password( 6, false, false ) );
        $dir  = trailingslashit( Nine_Backup_Exporter::archive_dir() );
        if ( 'json' === $format ) {
            $payload = array(
                'format'      => self::FORMAT,
                'version'     => self::VERSION,
                'site'        => home_url(),
                'exported_at' => gmdate( 'c' ),
                'counts'      => array_map( 'count', $groups ),
                'groups'      => $groups,
            );
            $path = $dir . $base . '.json';
            $ok = file_put_contents( $path, wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
            return $ok ? array( 'path' => $path, 'filename' => basename( $path ) ) : new WP_Error( 'osch_records_write', 'The JSON file could not be written.' );
        }
        if ( 'xlsx' === $format ) {
            $sheets = array( 'About' => self::about_rows( $groups ) );
            foreach ( $groups as $group => $rows ) { $sheets[ $group ] = self::table( $rows ); }
            $path = $dir . $base . '.xlsx';
            $ok = Nine_Backup_XLSX::write_book( $path, $sheets );
            return is_wp_error( $ok ) ? $ok : array( 'path' => $path, 'filename' => basename( $path ) );
        }
        // CSV: one file per group; several groups travel together in a ZIP.
        $files = array();
        foreach ( $groups as $group => $rows ) { $files[ sanitize_file_name( $group ) . '.csv' ] = self::csv( self::table( $rows ) ); }
        if ( 1 === count( $files ) ) {
            $path = $dir . $base . '-' . key( $files );
            $ok = file_put_contents( $path, reset( $files ) );
            return $ok ? array( 'path' => $path, 'filename' => basename( $path ) ) : new WP_Error( 'osch_records_write', 'The CSV file could not be written.' );
        }
        $path = $dir . $base . '-csv.zip';
        $ok = Nine_Backup_Zip::create_entries( $path, $files );
        return is_wp_error( $ok ) ? $ok : array( 'path' => $path, 'filename' => basename( $path ) );
    }

    private static function about_rows( array $groups ) {
        $rows = array(
            array( 'Open Scholar records', 'Exported ' . wp_date( 'Y-m-d H:i' ) . ' from ' . home_url() ),
            array( '', '' ),
            array( 'Sheet', 'Records' ),
        );
        foreach ( $groups as $group => $list ) { $rows[] = array( $group, count( $list ) ); }
        $rows[] = array( '', '' );
        $rows[] = array( 'How to edit', 'Change cells and import the file back from Export & Import. Do not rename the column headings.' );
        $rows[] = array( 'Matching', 'Posts and terms are matched by slug, users by login or email. Rows that match nothing are added as new records.' );
        $rows[] = array( 'Partial files', 'You may delete columns you do not want to change. Empty cells in a kept column are saved as empty.' );
        $rows[] = array( 'Lists', 'Cells holding lists or groups of values use JSON, for example {"category":["news"]}.' );
        $rows[] = array( 'Not included', 'Passwords, login sessions and secret keys are never exported or imported.' );
        return $rows;
    }

    /** CSV text with a UTF-8 marker for Excel. Cells that a spreadsheet would run as a formula get a leading apostrophe. */
    private static function csv( array $table ) {
        $handle = fopen( 'php://temp', 'w+' );
        fwrite( $handle, "\xEF\xBB\xBF" );
        foreach ( $table as $line ) {
            foreach ( $line as &$value ) {
                $value = (string) $value;
                if ( '' !== $value && preg_match( '/^[=+\-@\t\r]/', $value ) && ! preg_match( '/^-?\d+(\.\d+)?$/', $value ) ) { $value = "'" . $value; }
            }
            unset( $value );
            fputcsv( $handle, $line, ',', '"', '\\' );
        }
        rewind( $handle );
        $csv = stream_get_contents( $handle );
        fclose( $handle );
        return $csv;
    }

    /* ================================================================ Parse */

    /** Read an uploaded file into rows. Returns array( group => rows ), 'package' for a full ZIP, or WP_Error. */
    public static function parse( $path, $original_name ) {
        $ext = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
        if ( 'json' === $ext ) {
            $data = json_decode( (string) file_get_contents( $path ), true );
            if ( ! is_array( $data ) || self::FORMAT !== ( $data['format'] ?? '' ) || ! isset( $data['groups'] ) || ! is_array( $data['groups'] ) ) {
                return new WP_Error( 'osch_records_json', 'This JSON file was not exported as Open Scholar records.' );
            }
            return array_map( function ( $rows ) { return is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : array(); }, $data['groups'] );
        }
        if ( 'xlsx' === $ext ) {
            $sheets = Nine_Backup_XLSX::read( $path );
            if ( is_wp_error( $sheets ) ) { return $sheets; }
            $groups = array();
            foreach ( $sheets as $name => $table ) {
                if ( 'about' === strtolower( $name ) ) { continue; }
                $groups[ $name ] = self::rows_from_table( $table, false );
            }
            return $groups;
        }
        if ( 'csv' === $ext ) {
            return array( basename( $original_name, '.csv' ) => self::rows_from_table( self::read_csv( file_get_contents( $path ) ), true ) );
        }
        if ( 'zip' === $ext ) {
            if ( ! class_exists( 'ZipArchive' ) ) { return 'package'; }
            $zip = new ZipArchive();
            if ( true !== $zip->open( $path ) ) { return new WP_Error( 'osch_records_zip', 'The ZIP file could not be opened.' ); }
            $groups = array();
            $package = false;
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $name = $zip->getNameIndex( $i );
                if ( 'manifest.json' === basename( $name ) ) { $package = true; }
                if ( '.csv' === strtolower( substr( $name, -4 ) ) && false === strpos( $name, 'reports/' ) ) {
                    $groups[ basename( $name, '.csv' ) ] = self::rows_from_table( self::read_csv( $zip->getFromIndex( $i ) ), true );
                }
            }
            $zip->close();
            if ( $package ) { return 'package'; }
            return $groups ? $groups : new WP_Error( 'osch_records_zip', 'The ZIP holds no CSV files and is not an Open Scholar package.' );
        }
        return new WP_Error( 'osch_records_type', 'Choose an Excel (.xlsx), CSV, JSON or ZIP file.' );
    }

    private static function read_csv( $text ) {
        $text = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text );
        $handle = fopen( 'php://temp', 'w+' );
        fwrite( $handle, $text );
        rewind( $handle );
        $table = array();
        while ( false !== ( $line = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) {
            if ( array( null ) === $line ) { continue; }
            $table[] = $line;
        }
        fclose( $handle );
        return $table;
    }

    private static function rows_from_table( array $table, $from_csv ) {
        $header = array_shift( $table );
        if ( ! $header ) { return array(); }
        $header = array_map( function ( $h ) { return trim( (string) $h ); }, $header );
        $rows = array();
        foreach ( $table as $line ) {
            if ( ! array_filter( $line, function ( $v ) { return '' !== trim( (string) $v ); } ) ) { continue; }
            $row = array();
            foreach ( $header as $i => $column ) {
                if ( '' === $column ) { continue; }
                $value = isset( $line[ $i ] ) ? (string) $line[ $i ] : '';
                if ( $from_csv && preg_match( "/^'[=+\\-@\\t\\r]/", $value ) ) { $value = substr( $value, 1 ); }
                $row[ $column ] = $value;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /* =============================================================== Import */

    /** Lists and groups are stored as JSON text in CSV and Excel cells. */
    private static function decode( $value ) {
        if ( is_string( $value ) ) {
            $trim = trim( $value );
            if ( '' !== $trim && ( '[' === $trim[0] || '{' === $trim[0] ) ) {
                $decoded = json_decode( $trim, true );
                if ( null !== $decoded ) { return $decoded; }
            }
        }
        return $value;
    }

    private static function date_value( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) { return ''; }
        if ( is_numeric( $value ) && $value > 20000 && $value < 80000 ) { // A date a spreadsheet stored as a serial number.
            return gmdate( 'Y-m-d H:i:s', (int) round( ( $value - 25569 ) * DAY_IN_SECONDS ) );
        }
        $time = strtotime( $value );
        return $time ? gmdate( 'Y-m-d H:i:s', $time ) : '';
    }

    private static function blank_report() {
        return array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0 );
    }

    /**
     * Import (or, with $dry_run, only count) rows. $mode: both | new | update.
     * Order: terms, users, posts, comments, so references resolve.
     */
    public static function import( array $groups, $mode = 'both', $dry_run = false ) {
        if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }
        @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- hosts may forbid it.
        $by_type = array( 'term' => array(), 'user' => array(), 'post' => array(), 'comment' => array() );
        foreach ( $groups as $group => $rows ) {
            foreach ( (array) $rows as $index => $row ) {
                if ( ! is_array( $row ) ) { continue; }
                $type = isset( $row['record_type'] ) ? sanitize_key( $row['record_type'] ) : '';
                if ( ! isset( $by_type[ $type ] ) ) {
                    $type = isset( $row['post_type'] ) ? 'post' : ( isset( $row['login'] ) ? 'user' : ( isset( $row['taxonomy'] ) ? 'term' : '' ) );
                }
                if ( isset( $by_type[ $type ] ) ) { $by_type[ $type ][] = array( 'where' => $group . ' row ' . ( $index + 2 ), 'row' => $row ); }
            }
        }
        $state = array(
            'mode'     => in_array( $mode, array( 'both', 'new', 'update' ), true ) ? $mode : 'both',
            'dry'      => (bool) $dry_run,
            'report'   => array(),
            'messages' => array(),
            'comment_ids' => array(),
        );
        foreach ( array_keys( $by_type ) as $type ) { if ( $by_type[ $type ] ) { $state['report'][ $type ] = self::blank_report(); } }
        if ( ! $state['dry'] ) { wp_defer_term_counting( true ); wp_defer_comment_counting( true ); }
        self::import_terms( $by_type['term'], $state );
        self::import_users( $by_type['user'], $state );
        self::import_posts( $by_type['post'], $state );
        self::import_comments( $by_type['comment'], $state );
        if ( ! $state['dry'] ) { wp_defer_term_counting( false ); wp_defer_comment_counting( false ); }
        return array( 'report' => $state['report'], 'messages' => array_slice( $state['messages'], 0, 50 ), 'message_total' => count( $state['messages'] ) );
    }

    private static function fail( &$state, $type, $where, $message ) {
        $state['report'][ $type ]['errors']++;
        $state['messages'][] = $where . ': ' . $message;
    }

    /** Decide create/update/skip for a match. Returns 'create', 'update' or ''. */
    private static function action_for( $exists, &$state, $type ) {
        if ( $exists && 'new' === $state['mode'] ) { $state['report'][ $type ]['skipped']++; return ''; }
        if ( ! $exists && 'update' === $state['mode'] ) { $state['report'][ $type ]['skipped']++; return ''; }
        $state['report'][ $type ][ $exists ? 'updated' : 'created' ]++;
        return $exists ? 'update' : 'create';
    }

    private static function write_meta( $kind, $id, array $row, $dry ) {
        if ( $dry ) { return; }
        foreach ( $row as $column => $value ) {
            $multi = 0 === strpos( $column, 'meta[]:' );
            if ( ! $multi && 0 !== strpos( $column, 'meta:' ) ) { continue; }
            $key = substr( $column, $multi ? 7 : 5 );
            if ( '' === $key || preg_match( self::SECRET, $key ) ) { continue; }
            if ( 'post' === $kind && in_array( $key, self::$skip_post_meta, true ) ) { continue; }
            if ( 'user' === $kind && self::user_meta_blocked( $key ) ) { continue; }
            $value = self::decode( $value );
            if ( $multi ) {
                delete_metadata( $kind, $id, $key );
                foreach ( (array) $value as $single ) { add_metadata( $kind, $id, $key, wp_slash( $single ) ); }
            } else {
                update_metadata( $kind, $id, $key, wp_slash( $value ) );
            }
        }
    }

    private static function import_terms( array $items, &$state ) {
        $parents = array();
        foreach ( $items as $item ) {
            $row = $item['row'];
            $taxonomy = sanitize_key( $row['taxonomy'] ?? '' );
            if ( ! taxonomy_exists( $taxonomy ) ) { self::fail( $state, 'term', $item['where'], 'unknown taxonomy "' . $taxonomy . '"' ); continue; }
            $slug = sanitize_title( $row['slug'] ?? '' );
            $name = trim( (string) ( $row['name'] ?? '' ) );
            if ( '' === $slug && '' === $name ) { self::fail( $state, 'term', $item['where'], 'needs a name or slug' ); continue; }
            $existing = $slug ? get_term_by( 'slug', $slug, $taxonomy ) : get_term_by( 'name', $name, $taxonomy );
            $action = self::action_for( (bool) $existing, $state, 'term' );
            if ( ! $action || $state['dry'] ) { continue; }
            $args = array();
            if ( $slug ) { $args['slug'] = $slug; }
            if ( array_key_exists( 'description', $row ) ) { $args['description'] = (string) $row['description']; }
            if ( 'update' === $action ) {
                if ( '' !== $name ) { $args['name'] = $name; }
                $result = wp_update_term( $existing->term_id, $taxonomy, wp_slash( $args ) );
            } else {
                $result = wp_insert_term( wp_slash( '' !== $name ? $name : $slug ), $taxonomy, wp_slash( $args ) );
            }
            if ( is_wp_error( $result ) ) { $state['report']['term'][ 'update' === $action ? 'updated' : 'created' ]--; self::fail( $state, 'term', $item['where'], $result->get_error_message() ); continue; }
            self::write_meta( 'term', (int) $result['term_id'], $row, false );
            if ( array_key_exists( 'parent', $row ) ) { $parents[] = array( (int) $result['term_id'], $taxonomy, sanitize_title( $row['parent'] ) ); }
        }
        foreach ( $parents as $link ) {
            list( $term_id, $taxonomy, $parent_slug ) = $link;
            $parent = $parent_slug ? get_term_by( 'slug', $parent_slug, $taxonomy ) : null;
            if ( $parent_slug && ! $parent ) { continue; }
            if ( $parent && (int) $parent->term_id === $term_id ) { continue; }
            wp_update_term( $term_id, $taxonomy, array( 'parent' => $parent ? (int) $parent->term_id : 0 ) );
        }
    }

    private static function import_users( array $items, &$state ) {
        foreach ( $items as $item ) {
            $row = $item['row'];
            $login = sanitize_user( (string) ( $row['login'] ?? '' ), true );
            $email = sanitize_email( (string) ( $row['email'] ?? '' ) );
            $user = $login ? get_user_by( 'login', $login ) : false;
            if ( ! $user && $email ) { $user = get_user_by( 'email', $email ); }
            if ( ! $user && ( ! $login || ! $email ) ) { self::fail( $state, 'user', $item['where'], 'a new user needs a login and an email' ); continue; }
            if ( $email && $user ) {
                $owner = get_user_by( 'email', $email );
                if ( $owner && (int) $owner->ID !== (int) $user->ID ) { self::fail( $state, 'user', $item['where'], 'email ' . $email . ' already belongs to another user' ); continue; }
            } elseif ( $email && ! $user && email_exists( $email ) ) {
                self::fail( $state, 'user', $item['where'], 'email already in use' ); continue;
            }
            $action = self::action_for( (bool) $user, $state, 'user' );
            if ( ! $action || $state['dry'] ) { continue; }
            $data = array();
            foreach ( array( 'display_name', 'first_name', 'last_name', 'nickname', 'description' ) as $field ) {
                if ( array_key_exists( $field, $row ) ) { $data[ $field ] = (string) $row[ $field ]; }
            }
            if ( array_key_exists( 'url', $row ) ) { $data['user_url'] = esc_url_raw( (string) $row['url'] ); }
            if ( $email ) { $data['user_email'] = $email; }
            if ( 'update' === $action ) {
                $data['ID'] = $user->ID;
                $result = wp_update_user( $data );
            } else {
                $data['user_login'] = $login;
                $data['user_pass']  = wp_generate_password( 24, true, true ); // People set their own with "Lost your password?".
                $data['role']       = '';
                $registered = self::date_value( $row['registered'] ?? '' );
                if ( $registered ) { $data['user_registered'] = $registered; }
                $result = wp_insert_user( $data );
            }
            if ( is_wp_error( $result ) ) { $state['report']['user'][ 'update' === $action ? 'updated' : 'created' ]--; self::fail( $state, 'user', $item['where'], $result->get_error_message() ); continue; }
            $user_id = (int) $result;
            // Roles: only existing roles, and never on the account doing the import.
            if ( array_key_exists( 'roles', $row ) && get_current_user_id() !== $user_id ) {
                $roles = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) $row['roles'] ) ), function ( $role ) { return wp_roles()->is_role( $role ); } ) );
                $target = new WP_User( $user_id );
                if ( $roles ) {
                    $target->set_role( array_shift( $roles ) );
                    foreach ( $roles as $role ) { $target->add_role( $role ); }
                } elseif ( 'create' === $action ) {
                    $target->set_role( get_option( 'default_role', 'subscriber' ) );
                }
            } elseif ( 'create' === $action ) {
                ( new WP_User( $user_id ) )->set_role( get_option( 'default_role', 'subscriber' ) );
            }
            self::write_meta( 'user', $user_id, $row, false );
        }
    }

    private static function find_post( array $row, $post_type ) {
        $slug = sanitize_title( $row['slug'] ?? '' );
        $id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
        if ( $id ) {
            $post = get_post( $id );
            if ( $post && $post->post_type === $post_type && ( '' === $slug || $post->post_name === $slug ) ) { return $post; }
        }
        if ( '' === $slug ) { return null; }
        $found = get_posts( array( 'name' => $slug, 'post_type' => $post_type, 'post_status' => array_merge( self::STATUSES, array( 'trash' ) ), 'numberposts' => 1, 'suppress_filters' => true ) );
        return $found ? $found[0] : null;
    }

    private static function import_posts( array $items, &$state ) {
        $parents = array();
        foreach ( $items as $item ) {
            $row = $item['row'];
            $post_type = sanitize_key( $row['post_type'] ?? '' );
            if ( ! self::post_type_allowed( $post_type ) ) { self::fail( $state, 'post', $item['where'], 'unknown post type "' . $post_type . '"' ); continue; }
            $existing = self::find_post( $row, $post_type );
            if ( ! $existing && '' === trim( (string) ( $row['title'] ?? '' ) ) && '' === trim( (string) ( $row['slug'] ?? '' ) ) ) { self::fail( $state, 'post', $item['where'], 'a new record needs a title or slug' ); continue; }
            $action = self::action_for( (bool) $existing, $state, 'post' );
            if ( ! $action || $state['dry'] ) { continue; }
            $args = array( 'post_type' => $post_type );
            $map = array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt' );
            foreach ( $map as $column => $field ) { if ( array_key_exists( $column, $row ) ) { $args[ $field ] = (string) $row[ $column ]; } }
            if ( array_key_exists( 'slug', $row ) && '' !== trim( (string) $row['slug'] ) ) { $args['post_name'] = sanitize_title( $row['slug'] ); }
            if ( array_key_exists( 'status', $row ) ) { $args['post_status'] = in_array( $row['status'], self::STATUSES, true ) ? $row['status'] : 'draft'; }
            elseif ( 'create' === $action ) { $args['post_status'] = 'draft'; }
            if ( array_key_exists( 'menu_order', $row ) && '' !== $row['menu_order'] ) { $args['menu_order'] = (int) $row['menu_order']; }
            if ( array_key_exists( 'comment_status', $row ) && in_array( $row['comment_status'], array( 'open', 'closed' ), true ) ) { $args['comment_status'] = $row['comment_status']; }
            if ( ! empty( $row['date'] ) ) {
                $date = self::date_value( $row['date'] );
                if ( $date ) { $args['post_date'] = $date; $args['post_date_gmt'] = get_gmt_from_date( $date ); $args['edit_date'] = true; }
            }
            if ( array_key_exists( 'author', $row ) && '' !== $row['author'] ) {
                $author = get_user_by( 'login', (string) $row['author'] );
                if ( $author ) { $args['post_author'] = $author->ID; }
            }
            if ( 'create' === $action && empty( $args['post_author'] ) ) { $args['post_author'] = get_current_user_id(); }
            if ( 'update' === $action ) {
                $args['ID'] = $existing->ID;
                $post_id = wp_update_post( wp_slash( $args ), true );
            } else {
                $post_id = wp_insert_post( wp_slash( $args ), true );
            }
            if ( is_wp_error( $post_id ) || ! $post_id ) {
                $state['report']['post'][ 'update' === $action ? 'updated' : 'created' ]--;
                self::fail( $state, 'post', $item['where'], is_wp_error( $post_id ) ? $post_id->get_error_message() : 'could not be saved' );
                continue;
            }
            if ( array_key_exists( 'terms', $row ) ) {
                $terms = self::decode( $row['terms'] );
                if ( is_array( $terms ) ) {
                    foreach ( $terms as $taxonomy => $slugs ) {
                        if ( ! is_object_in_taxonomy( $post_type, $taxonomy ) ) { continue; }
                        $ids = array();
                        foreach ( (array) $slugs as $slug ) {
                            $slug = sanitize_title( $slug );
                            if ( '' === $slug ) { continue; }
                            $term = get_term_by( 'slug', $slug, $taxonomy );
                            if ( ! $term ) { $made = wp_insert_term( $slug, $taxonomy, array( 'slug' => $slug ) ); $term = is_wp_error( $made ) ? null : get_term( $made['term_id'], $taxonomy ); }
                            if ( $term ) { $ids[] = (int) $term->term_id; }
                        }
                        wp_set_object_terms( $post_id, $ids, $taxonomy );
                    }
                }
            }
            if ( ! empty( $row['featured_image'] ) ) {
                $attachment = attachment_url_to_postid( (string) $row['featured_image'] );
                if ( $attachment ) { set_post_thumbnail( $post_id, $attachment ); }
            }
            self::write_meta( 'post', $post_id, $row, false );
            if ( array_key_exists( 'parent', $row ) && is_post_type_hierarchical( $post_type ) ) { $parents[] = array( $post_id, $post_type, sanitize_title( $row['parent'] ) ); }
        }
        foreach ( $parents as $link ) {
            list( $post_id, $post_type, $parent_slug ) = $link;
            $parent = $parent_slug ? get_posts( array( 'name' => $parent_slug, 'post_type' => $post_type, 'post_status' => self::STATUSES, 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) ) : array();
            if ( $parent_slug && ! $parent ) { continue; }
            $parent_id = $parent ? (int) $parent[0] : 0;
            if ( $parent_id !== $post_id ) { wp_update_post( array( 'ID' => $post_id, 'post_parent' => $parent_id ) ); }
        }
    }

    private static function import_comments( array $items, &$state ) {
        $statuses = array( 'approved' => '1', 'approve' => '1', '1' => '1', 'unapproved' => '0', 'hold' => '0', '0' => '0', 'spam' => 'spam', 'trash' => 'trash' );
        $parents = array();
        foreach ( $items as $item ) {
            $row = $item['row'];
            $post_type = sanitize_key( $row['post_type'] ?? '' );
            $slug = sanitize_title( $row['post_slug'] ?? '' );
            $post = ( $post_type && $slug ) ? get_posts( array( 'name' => $slug, 'post_type' => $post_type, 'post_status' => self::STATUSES, 'numberposts' => 1, 'suppress_filters' => true ) ) : array();
            if ( ! $post ) {
                // In a preview the post may be one this same file is about to create.
                if ( $state['dry'] ) { $state['report']['comment']['created']++; continue; }
                self::fail( $state, 'comment', $item['where'], 'post "' . $slug . '" not found' ); continue;
            }
            $post_id = (int) $post[0]->ID;
            $date = self::date_value( $row['date'] ?? '' );
            $email = sanitize_email( (string) ( $row['author_email'] ?? '' ) );
            $existing = null;
            $id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
            if ( $id ) {
                $candidate = get_comment( $id );
                if ( $candidate && (int) $candidate->comment_post_ID === $post_id && ( ! $date || $candidate->comment_date === $date ) ) { $existing = $candidate; }
            }
            if ( ! $existing && $date ) {
                $found = get_comments( array( 'post_id' => $post_id, 'status' => 'all', 'number' => 1, 'author_email' => $email, 'date_query' => array( array( 'after' => $date, 'before' => $date, 'inclusive' => true ) ) ) );
                $existing = $found ? $found[0] : null;
            }
            $action = self::action_for( (bool) $existing, $state, 'comment' );
            if ( ! $action || $state['dry'] ) { continue; }
            $data = array( 'comment_post_ID' => $post_id );
            $map = array( 'author' => 'comment_author', 'author_url' => 'comment_author_url', 'content' => 'comment_content', 'type' => 'comment_type' );
            foreach ( $map as $column => $field ) { if ( array_key_exists( $column, $row ) ) { $data[ $field ] = (string) $row[ $column ]; } }
            if ( $email ) { $data['comment_author_email'] = $email; }
            if ( $date ) { $data['comment_date'] = $date; $data['comment_date_gmt'] = get_gmt_from_date( $date ); }
            if ( isset( $row['status'] ) && isset( $statuses[ strtolower( (string) $row['status'] ) ] ) ) { $data['comment_approved'] = $statuses[ strtolower( (string) $row['status'] ) ]; }
            if ( ! empty( $row['user'] ) ) { $user = get_user_by( 'login', (string) $row['user'] ); if ( $user ) { $data['user_id'] = $user->ID; } }
            if ( 'update' === $action ) {
                $data['comment_ID'] = $existing->comment_ID;
                $done = wp_update_comment( wp_slash( $data ), true );
                $comment_id = is_wp_error( $done ) ? $done : (int) $existing->comment_ID;
            } else {
                $comment_id = wp_insert_comment( wp_slash( $data ) );
                if ( ! $comment_id ) { $comment_id = new WP_Error( 'osch_comment', 'could not be saved' ); }
            }
            if ( is_wp_error( $comment_id ) ) { $state['report']['comment'][ 'update' === $action ? 'updated' : 'created' ]--; self::fail( $state, 'comment', $item['where'], $comment_id->get_error_message() ); continue; }
            if ( $id ) { $state['comment_ids'][ $id ] = $comment_id; }
            if ( ! empty( $row['parent'] ) ) { $parents[ $comment_id ] = absint( $row['parent'] ); }
            self::write_meta( 'comment', $comment_id, $row, false );
        }
        foreach ( $parents as $comment_id => $source_parent ) {
            $parent = isset( $state['comment_ids'][ $source_parent ] ) ? $state['comment_ids'][ $source_parent ] : ( get_comment( $source_parent ) ? $source_parent : 0 );
            if ( $parent && $parent !== $comment_id ) { wp_update_comment( array( 'comment_ID' => $comment_id, 'comment_parent' => $parent ) ); }
        }
    }

    /** The record types an upload touches, as export type keys, for the safety copy. */
    private static function types_in( array $groups ) {
        $types = array();
        foreach ( $groups as $rows ) {
            foreach ( (array) $rows as $row ) {
                $type = $row['record_type'] ?? '';
                if ( 'post' === $type && ! empty( $row['post_type'] ) ) { $types[ 'post:' . sanitize_key( $row['post_type'] ) ] = true; }
                elseif ( 'user' === $type ) { $types['users'] = true; }
                elseif ( 'term' === $type ) { $types['terms'] = true; }
                elseif ( 'comment' === $type ) { $types['comments'] = true; }
            }
        }
        return array_keys( $types );
    }

    /* ============================================================= Handlers */

    private static function guard( $nonce ) {
        if ( ! current_user_can( self::CAP ) ) { wp_die( 'You do not have permission to export or import records.', '', array( 'response' => 403 ) ); }
        check_admin_referer( $nonce );
    }

    private static function back( array $args = array() ) {
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=osch-export-import' ) ) . '#osch-records' );
        exit;
    }

    private static function upload_key() { return 'osch_records_upload_' . get_current_user_id(); }
    private static function result_key() { return 'osch_records_result_' . get_current_user_id(); }

    public static function handle_export() {
        self::guard( 'osch_records_export' );
        $types  = isset( $_POST['types'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['types'] ) ) : array();
        $format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'xlsx';
        $known  = self::types();
        $types  = array_values( array_intersect( $types, array_keys( $known ) ) );
        if ( ! $types ) { self::back( array( 'records_error' => rawurlencode( 'Choose at least one kind of record to export.' ) ) ); }
        if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }
        @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- hosts may forbid it.

        if ( 'package' === $format ) {
            $post_types = array();
            $bulk = array();
            foreach ( $types as $type ) {
                if ( 0 === strpos( $type, 'post:' ) ) { $post_types[] = substr( $type, 5 ); $bulk['posts'] = 'posts'; }
                elseif ( 'users' === $type ) { $bulk['users'] = 'users'; }
                elseif ( 'terms' === $type ) { $bulk['terms'] = 'terms'; }
            }
            if ( ! $bulk ) { self::back( array( 'records_error' => rawurlencode( 'Comments travel in Excel, CSV or JSON. Choose another format, or add posts, users or terms.' ) ) ); }
            $result = ( new Nine_Backup_Exporter( array(
                'name' => 'Open Scholar records',
                'bulk' => array_values( $bulk ),
                'post_types' => $post_types,
                'include_dependencies' => true,
                'include_media_files' => false,
                'include_sensitive_settings' => false,
                'copy_to_drive_folder' => false,
                'source' => 'direct-download',
            ) ) )->create_package();
            if ( is_wp_error( $result ) ) { self::back( array( 'records_error' => rawurlencode( $result->get_error_message() ) ) ); }
            self::stream( $result['path'], true );
        }

        if ( ! in_array( $format, array( 'xlsx', 'csv', 'json' ), true ) ) { $format = 'xlsx'; }
        try {
            $file = self::write( self::collect( $types ), $format );
        } catch ( Throwable $e ) {
            $file = new WP_Error( 'osch_records_export', $e->getMessage() );
        }
        if ( is_wp_error( $file ) ) { self::back( array( 'records_error' => rawurlencode( 'Export failed: ' . $file->get_error_message() ) ) ); }
        self::stream( $file['path'], true );
    }

    /** Send a file from the protected archive folder. */
    private static function stream( $path, $remove_after ) {
        $real = realpath( $path );
        $dir  = realpath( Nine_Backup_Exporter::archive_dir() );
        $ext  = strtolower( pathinfo( (string) $real, PATHINFO_EXTENSION ) );
        $types = array( 'zip' => 'application/zip', 'json' => 'application/json', 'csv' => 'text/csv; charset=utf-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        if ( ! $real || ! $dir || 0 !== strpos( $real, trailingslashit( $dir ) ) || ! is_file( $real ) || ! isset( $types[ $ext ] ) ) {
            wp_die( 'That file is not available.', '', array( 'response' => 404 ) );
        }
        while ( ob_get_level() ) { ob_end_clean(); }
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: ' . $types[ $ext ] );
        header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', basename( $real ) ) . '"' );
        header( 'Content-Length: ' . filesize( $real ) );
        if ( $remove_after ) { register_shutdown_function( function () use ( $real ) { if ( is_file( $real ) ) { @unlink( $real ); } } ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
        readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }

    public static function handle_upload() {
        self::guard( 'osch_records_upload' );
        if ( empty( $_FILES['records']['tmp_name'] ) || ! is_uploaded_file( $_FILES['records']['tmp_name'] ) ) { self::back( array( 'records_error' => rawurlencode( 'Choose a file to import.' ) ) ); } // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $original = sanitize_file_name( wp_unslash( $_FILES['records']['name'] ?? 'records' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $ext = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'xlsx', 'csv', 'json', 'zip' ), true ) ) { self::back( array( 'records_error' => rawurlencode( 'Choose an Excel (.xlsx), CSV, JSON or ZIP file.' ) ) ); }
        Nine_Backup_Exporter::ensure_dirs();
        $upload_info = wp_upload_dir();
        $protected = trailingslashit( $upload_info['basedir'] ) . 'nine-backup-manager/tmp';
        wp_mkdir_p( $protected );
        $path = trailingslashit( $protected ) . 'records-import-' . strtolower( wp_generate_password( 16, false, false ) ) . '.' . $ext;
        if ( ! move_uploaded_file( $_FILES['records']['tmp_name'], $path ) ) { self::back( array( 'records_error' => rawurlencode( 'The file uploaded but could not be stored safely.' ) ) ); } // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        $groups = self::parse( $path, $original );
        if ( 'package' === $groups ) {
            // A full Open Scholar package: hand over to the package importer and its preview.
            $preview = ( new Nine_Backup_Importer( $path ) )->preview();
            if ( is_wp_error( $preview ) ) { @unlink( $path ); self::back( array( 'records_error' => rawurlencode( $preview->get_error_message() ) ) ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
            set_transient( 'nine_backup_preview_' . get_current_user_id(), array( 'path' => $path, 'preview' => $preview ), 2 * HOUR_IN_SECONDS );
            wp_safe_redirect( admin_url( 'admin.php?page=nine-backup-manager&tab=import' ) );
            exit;
        }
        if ( is_wp_error( $groups ) ) { @unlink( $path ); self::back( array( 'records_error' => rawurlencode( $groups->get_error_message() ) ) ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
        $preview = self::import( $groups, 'both', true );
        $previous = get_transient( self::upload_key() );
        if ( is_array( $previous ) && ! empty( $previous['path'] ) && is_file( $previous['path'] ) ) { @unlink( $previous['path'] ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
        set_transient( self::upload_key(), array( 'path' => $path, 'name' => $original, 'preview' => $preview ), 2 * HOUR_IN_SECONDS );
        delete_transient( self::result_key() );
        self::back();
    }

    public static function handle_import() {
        self::guard( 'osch_records_import' );
        $upload = get_transient( self::upload_key() );
        if ( ! is_array( $upload ) || empty( $upload['path'] ) || ! is_file( $upload['path'] ) ) { self::back( array( 'records_error' => rawurlencode( 'The uploaded file has expired. Upload it again.' ) ) ); }
        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'both';
        $groups = self::parse( $upload['path'], $upload['name'] );
        if ( ! is_array( $groups ) ) { self::back( array( 'records_error' => rawurlencode( 'The uploaded file could not be read again.' ) ) ); }

        // Safety copy of everything this import can change, saved before any write.
        $safety = null;
        $types = self::types_in( $groups );
        if ( $types ) {
            $copy = self::write( self::collect( $types ), 'json', 'safety-copy' );
            if ( is_wp_error( $copy ) ) { self::back( array( 'records_error' => rawurlencode( 'Import stopped: the safety copy could not be saved (' . $copy->get_error_message() . ').' ) ) ); }
            $safety = $copy['filename'];
        }
        try {
            $result = self::import( $groups, $mode, false );
        } catch ( Throwable $e ) {
            Nine_Backup_Health::log( 'records-import', $e->getMessage() );
            $result = array( 'report' => array(), 'messages' => array( 'Import stopped: ' . $e->getMessage() ), 'message_total' => 1 );
        }
        $result['safety'] = $safety;
        $result['file']   = $upload['name'];
        $result['time']   = time();
        @unlink( $upload['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        delete_transient( self::upload_key() );
        set_transient( self::result_key(), $result, DAY_IN_SECONDS );
        Nine_Backup_Health::log( 'records-import', 'Imported ' . $upload['name'], $result['report'] );
        self::back( array( 'records_done' => 1 ) );
    }

    public static function handle_cancel() {
        self::guard( 'osch_records_cancel' );
        $upload = get_transient( self::upload_key() );
        if ( is_array( $upload ) && ! empty( $upload['path'] ) && is_file( $upload['path'] ) ) { @unlink( $upload['path'] ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
        delete_transient( self::upload_key() );
        delete_transient( self::result_key() );
        self::back();
    }

    public static function handle_download() {
        self::guard( 'osch_records_download' );
        $file = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
        if ( ! preg_match( '/^open-scholar-safety-copy-[a-z0-9-]+\.json$/', $file ) ) { wp_die( 'That file is not available.', '', array( 'response' => 404 ) ); }
        self::stream( trailingslashit( Nine_Backup_Exporter::archive_dir() ) . $file, false );
    }

    /* =================================================================== UI */

    private static function labels() {
        return array( 'post' => 'Posts & other content', 'user' => 'Users & profiles', 'term' => 'Categories & tags', 'comment' => 'Comments' );
    }

    private static function report_table( array $report ) {
        echo '<table class="widefat striped osch-records-report"><thead><tr><th>Records</th><th>New</th><th>Updated</th><th>Skipped</th><th>Problems</th></tr></thead><tbody>';
        foreach ( $report as $type => $counts ) {
            $labels = self::labels();
            echo '<tr><td>' . esc_html( $labels[ $type ] ?? $type ) . '</td><td>' . (int) $counts['created'] . '</td><td>' . (int) $counts['updated'] . '</td><td>' . (int) $counts['skipped'] . '</td><td>' . (int) $counts['errors'] . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function messages( array $result ) {
        if ( empty( $result['messages'] ) ) { return; }
        echo '<details class="osch-records-messages"><summary>' . esc_html( sprintf( '%d row(s) need attention', (int) $result['message_total'] ) ) . '</summary><ul>';
        foreach ( $result['messages'] as $message ) { echo '<li>' . esc_html( $message ) . '</li>'; }
        echo '</ul></details>';
    }

    public static function render() {
        $types   = self::types();
        $upload  = get_transient( self::upload_key() );
        $result  = get_transient( self::result_key() );
        $error   = isset( $_GET['records_error'] ) ? sanitize_text_field( wp_unslash( $_GET['records_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
        $post    = admin_url( 'admin-post.php' );
        ?>
        <section class="nine-box osch-records" id="osch-records">
            <h2>Records: export and import</h2>
            <p class="description">Keep a copy of every record: posts and every other content type, users with their scholar profiles, categories and tags, and comments. Open it in Excel, edit it, and import it back. Passwords are never included.</p>
            <?php if ( $error ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

            <form method="post" action="<?php echo esc_url( $post ); ?>" class="osch-records-export">
                <input type="hidden" name="action" value="osch_records_export">
                <?php wp_nonce_field( 'osch_records_export' ); ?>
                <h3><span class="osch-step">1/2</span> Export</h3>
                <fieldset>
                    <legend>What to export</legend>
                    <p class="osch-records-pick"><button type="button" class="button-link" data-osch-records-all="1">Select all</button> · <button type="button" class="button-link" data-osch-records-all="0">Select none</button></p>
                    <div class="osch-records-types">
                        <?php foreach ( $types as $key => $type ) : ?>
                            <label><input type="checkbox" name="types[]" value="<?php echo esc_attr( $key ); ?>" checked> <?php echo esc_html( $type['label'] ); ?> <small>(<?php echo esc_html( number_format_i18n( $type['count'] ) ); ?>)</small></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>Format</legend>
                    <div class="osch-records-formats">
                        <label><input type="radio" name="format" value="xlsx" checked> <b>Excel (.xlsx)</b> <i>One sheet per kind of record. Best for reading and editing.</i></label>
                        <label><input type="radio" name="format" value="csv"> <b>CSV</b> <i>One file per kind of record (several come in a ZIP). Opens anywhere.</i></label>
                        <label><input type="radio" name="format" value="json"> <b>JSON</b> <i>One file with everything, for developers and archives.</i></label>
                        <label><input type="radio" name="format" value="package"> <b>Open Scholar package (.zip)</b> <i>Full copy with relationships, for moving to another Open Scholar site. No comments.</i></label>
                    </div>
                </fieldset>
                <button type="submit" class="button button-primary">Download export</button>
            </form>

            <div class="osch-records-import">
                <h3><span class="osch-step">2/2</span> Import</h3>
                <?php if ( is_array( $result ) && ! empty( $_GET['records_done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <div class="notice notice-success inline"><p><strong>Import finished</strong> from <?php echo esc_html( $result['file'] ); ?>.</p></div>
                    <?php self::report_table( (array) $result['report'] ); self::messages( $result ); ?>
                    <?php if ( ! empty( $result['safety'] ) ) : ?>
                        <p class="description">A safety copy of the records as they were before this import was saved. Import it to put changed records back (records added by the import stay).
                        <a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'osch_records_download', 'file' => $result['safety'] ), $post ), 'osch_records_download' ) ); ?>">Download safety copy</a></p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ( is_array( $upload ) && ! empty( $upload['preview'] ) ) : ?>
                    <div class="osch-records-preview">
                        <p><strong>Ready to import <?php echo esc_html( $upload['name'] ); ?>.</strong> Nothing has changed yet. This is what the import will do:</p>
                        <?php self::report_table( (array) $upload['preview']['report'] ); self::messages( $upload['preview'] ); ?>
                        <form method="post" action="<?php echo esc_url( $post ); ?>">
                            <input type="hidden" name="action" value="osch_records_import">
                            <?php wp_nonce_field( 'osch_records_import' ); ?>
                            <label class="osch-records-mode"><b>How to import</b>
                                <select name="mode">
                                    <option value="both">Update matching records and add new ones</option>
                                    <option value="new">Only add new records</option>
                                    <option value="update">Only update existing records</option>
                                </select>
                            </label>
                            <button type="submit" class="button button-primary">Import now</button>
                        </form>
                        <form method="post" action="<?php echo esc_url( $post ); ?>" class="osch-records-cancel">
                            <input type="hidden" name="action" value="osch_records_cancel">
                            <?php wp_nonce_field( 'osch_records_cancel' ); ?>
                            <button type="submit" class="button">Cancel</button>
                        </form>
                    </div>
                <?php else : ?>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( $post ); ?>">
                        <input type="hidden" name="action" value="osch_records_upload">
                        <?php wp_nonce_field( 'osch_records_upload' ); ?>
                        <label class="osch-records-file"><b>File to import</b>
                            <input type="file" name="records" accept=".xlsx,.csv,.json,.zip" required>
                            <i>Excel, CSV, JSON or an Open Scholar package. You will see a preview before anything changes.</i>
                        </label>
                        <button type="submit" class="button button-primary">Upload &amp; preview</button>
                    </form>
                <?php endif; ?>
            </div>
            <script>
            document.querySelectorAll('[data-osch-records-all]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var on = button.getAttribute('data-osch-records-all') === '1';
                    document.querySelectorAll('.osch-records-types input[type=checkbox]').forEach(function (box) { box.checked = on; });
                });
            });
            </script>
        </section>
        <?php
    }
}
