<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NineCode_Data_Version_Manager {
    const DB_VERSION = '1.0';

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ninecode_data_versions';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_kind varchar(20) NOT NULL,
            object_id bigint(20) unsigned NOT NULL,
            object_type varchar(191) NOT NULL DEFAULT '',
            object_label text NOT NULL,
            version_label varchar(191) NOT NULL DEFAULT '',
            source varchar(40) NOT NULL DEFAULT 'manual',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            snapshot longtext NOT NULL,
            PRIMARY KEY  (id),
            KEY object_lookup (object_kind,object_id),
            KEY created_at (created_at)
        ) {$charset};";
        dbDelta( $sql );
        update_option( 'ninecode_data_versions_db_version', self::DB_VERSION, false );
    }

    public static function maybe_install() {
        if ( self::DB_VERSION !== get_option( 'ninecode_data_versions_db_version' ) ) {
            self::install();
        }
    }

    public static function capture_object( $kind, $id, $taxonomy = '', $label = 'Before data change', $source = 'manual' ) {
        global $wpdb;
        $id = absint( $id );
        if ( ! $id ) { return false; }
        $exporter = new NineCode_Data_Exporter();
        if ( 'term' === $kind ) {
            $record = $exporter->export_term_record( $id, $taxonomy );
            if ( ! $record ) { return false; }
            $object_type = sanitize_key( $record['object']['taxonomy'] ?? $taxonomy );
            $object_label = sanitize_text_field( $record['object']['name'] ?? (string) $id );
        } else {
            $record = $exporter->export_post_record( $id );
            if ( ! $record ) { return false; }
            $object_type = sanitize_key( $record['object']['post_type'] ?? '' );
            $object_label = sanitize_text_field( $record['object']['title'] ?? (string) $id );
            $kind = 'post';
        }
        $ok = $wpdb->insert(
            self::table_name(),
            array(
                'object_kind' => $kind,
                'object_id' => $id,
                'object_type' => $object_type,
                'object_label' => $object_label,
                'version_label' => sanitize_text_field( $label ),
                'source' => sanitize_key( $source ),
                'user_id' => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
                'snapshot' => wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
        if ( false !== $ok ) {
            self::trim_object_versions( $kind, $id, 30 );
            self::trim_global_versions( 2000 );
            return (int) $wpdb->insert_id;
        }
        return false;
    }

    public static function capture_records( $records, $label = 'Before import', $source = 'import' ) {
        $seen = array();
        $count = 0;
        foreach ( (array) $records as $record ) {
            $object = is_array( $record ) ? (array) ( $record['object'] ?? array() ) : array();
            $kind = ( 'term' === sanitize_key( $object['kind'] ?? 'post' ) ) ? 'term' : 'post';
            $id = absint( $object['id'] ?? 0 );
            if ( ! $id ) { continue; }
            $taxonomy = 'term' === $kind ? sanitize_key( $object['taxonomy'] ?? '' ) : '';
            $key = $kind . ':' . $id . ':' . $taxonomy;
            if ( isset( $seen[ $key ] ) ) { continue; }
            $seen[ $key ] = true;
            if ( self::capture_object( $kind, $id, $taxonomy, $label, $source ) ) { $count++; }
        }
        return $count;
    }

    public static function capture_acf_post_id( $post_id, $label = 'Before Data Editor save', $source = 'manual' ) {
        if ( is_numeric( $post_id ) ) {
            return self::capture_object( 'post', absint( $post_id ), '', $label, $source );
        }
        if ( is_string( $post_id ) && preg_match( '/^(?:term|taxonomy)_(\d+)$/', $post_id, $m ) ) {
            $term = get_term( absint( $m[1] ) );
            if ( $term && ! is_wp_error( $term ) ) {
                return self::capture_object( 'term', $term->term_id, $term->taxonomy, $label, $source );
            }
        }
        return false;
    }

    public static function list_versions( $limit = 50, $kind = '', $object_id = 0 ) {
        global $wpdb;
        $limit = max( 1, min( 200, absint( $limit ) ) );
        $where = '1=1';
        $args = array();
        if ( $kind ) { $where .= ' AND object_kind=%s'; $args[] = sanitize_key( $kind ); }
        if ( $object_id ) { $where .= ' AND object_id=%d'; $args[] = absint( $object_id ); }
        $sql = "SELECT id,object_kind,object_id,object_type,object_label,version_label,source,user_id,created_at FROM " . self::table_name() . " WHERE {$where} ORDER BY id DESC LIMIT {$limit}";
        if ( $args ) { $sql = $wpdb->prepare( $sql, $args ); }
        return (array) $wpdb->get_results( $sql, ARRAY_A );
    }

    public static function get_version( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id=%d', absint( $id ) ), ARRAY_A );
    }

    public static function restore_version( $id ) {
        $row = self::get_version( $id );
        if ( ! $row ) { return new WP_Error( 'version_missing', 'That saved data version could not be found.' ); }
        $record = json_decode( $row['snapshot'], true );
        if ( ! is_array( $record ) || empty( $record['object'] ) ) { return new WP_Error( 'version_invalid', 'That saved data version is damaged.' ); }

        self::capture_object( $row['object_kind'], absint( $row['object_id'] ), $row['object_type'], 'Before restoring version #' . absint( $id ), 'rollback' );
        $importer = new NineCode_Data_Importer();
        $is_term = 'term' === $row['object_kind'];
        $report = $importer->import_records( array( $record ), array(
            'dry_run' => false,
            'create_missing' => false,
            'create_missing_records' => false,
            'create_missing_terms' => false,
            'import_identity' => false,
            'import_term_identity' => $is_term,
            'import_terms' => true,
            'capture_versions' => false,
        ) );
        if ( is_array( $report ) ) {
            $report['messages'][] = 'Restored saved version #' . absint( $id ) . ' from ' . $row['created_at'] . '.';
        }
        return $report;
    }

    public static function count_versions() {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
    }

    private static function trim_object_versions( $kind, $id, $keep ) {
        global $wpdb;
        $ids = $wpdb->get_col( $wpdb->prepare(
            'SELECT id FROM ' . self::table_name() . ' WHERE object_kind=%s AND object_id=%d ORDER BY id DESC LIMIT 18446744073709551615 OFFSET %d',
            $kind, absint( $id ), absint( $keep )
        ) );
        if ( $ids ) {
            $ids = array_map( 'absint', $ids );
            $wpdb->query( 'DELETE FROM ' . self::table_name() . ' WHERE id IN (' . implode( ',', $ids ) . ')' );
        }
    }

    private static function trim_global_versions( $keep ) {
        global $wpdb;
        $count = self::count_versions();
        if ( $count <= $keep ) { return; }
        $delete = $count - $keep;
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' ORDER BY id ASC LIMIT %d', $delete ) );
    }
}
