<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Plugin portability layer for 9 Backup Manager.
 *
 * A plugin is treated as two independent assets:
 * 1) its executable package (plugin files), and
 * 2) its site-specific configuration/data (options + plugin tables).
 */
class Nine_Backup_Plugins {
    private static function ensure_plugin_api() {
        if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) || ! function_exists( 'activate_plugin' ) ) {
            $file = ABSPATH . 'wp-admin/includes/plugin.php';
            if ( file_exists( $file ) ) { require_once $file; }
        }
    }

    public static function installed_plugins() {
        self::ensure_plugin_api();
        if ( ! function_exists( 'get_plugins' ) ) { return array(); }
        $plugins = get_plugins();
        uasort( $plugins, function( $a, $b ) {
            return strcasecmp( $a['Name'] ?? '', $b['Name'] ?? '' );
        } );
        return $plugins;
    }

    public static function plugin_slug( $plugin_file ) {
        $plugin_file = plugin_basename( $plugin_file );
        $dir = dirname( $plugin_file );
        if ( '.' !== $dir ) { return sanitize_title( basename( $dir ) ); }
        return sanitize_title( basename( $plugin_file, '.php' ) );
    }

    public static function is_nine_plugin( $plugin_file, $data = array() ) {
        $haystack = strtolower( implode( ' ', array(
            $plugin_file,
            $data['Name'] ?? '',
            $data['Author'] ?? '',
            $data['TextDomain'] ?? '',
            $data['Description'] ?? '',
        ) ) );
        return false !== strpos( $haystack, '9igeria' )
            || false !== strpos( $haystack, 'nine-' )
            || false !== strpos( $haystack, 'nine_' )
            || preg_match( '/(^|[\s\/])9\s+(backup|acf|post|gallery|header|footer|category|featured|embed|user|optim)/i', $haystack );
    }

    public static function registry() {
        /**
         * Plugin-specific portability declarations.
         * Key by plugin basename (e.g. my-plugin/my-plugin.php) or slug.
         * Supported keys: option_names, option_prefixes, table_names, table_suffixes, post_types, taxonomies.
         */
        return apply_filters( 'nine_backup_plugin_registry', array() );
    }

    public static function resolve_selected_files( array $config ) {
        $installed = self::installed_plugins();
        $selected = array();
        foreach ( (array) ( $config['plugin_files'] ?? array() ) as $file ) {
            $file = plugin_basename( $file );
            if ( isset( $installed[ $file ] ) ) { $selected[ $file ] = true; }
        }
        $bulk = (array) ( $config['bulk'] ?? array() );
        if ( in_array( 'plugins', $bulk, true ) || in_array( 'full_portability', $bulk, true ) ) {
            foreach ( $installed as $file => $data ) { $selected[ $file ] = true; }
        } elseif ( in_array( 'nine_plugins', $bulk, true ) ) {
            foreach ( $installed as $file => $data ) {
                if ( self::is_nine_plugin( $file, $data ) ) { $selected[ $file ] = true; }
            }
        }
        if ( ! empty( $config['plugin_include_dependencies'] ) ) {
            $changed = true;
            while ( $changed ) {
                $changed = false;
                foreach ( array_keys( $selected ) as $file ) {
                    $requires = self::parse_requires_plugins( $installed[$file]['RequiresPlugins'] ?? '' );
                    foreach ( $requires as $required_slug ) {
                        foreach ( $installed as $candidate=>$candidate_data ) {
                            if ( self::plugin_slug( $candidate ) === sanitize_title( $required_slug ) && empty( $selected[$candidate] ) ) {
                                $selected[$candidate] = true; $changed = true; break;
                            }
                        }
                    }
                }
            }
        }
        return array_keys( $selected );
    }

    public static function export_plugins( array $plugin_files, $work_dir, array $config ) {
        self::ensure_plugin_api();
        $installed = self::installed_plugins();
        $records = array();
        $settings_bundles = array();
        $context = array();
        $plugin_dir = trailingslashit( WP_PLUGIN_DIR );
        $bundle_dir = trailingslashit( $work_dir ) . 'plugins';
        wp_mkdir_p( $bundle_dir );

        $include_files = ! empty( $config['plugin_include_files'] );
        $include_settings = ! empty( $config['plugin_include_settings'] );
        $include_tables = ! empty( $config['plugin_include_tables'] );
        $include_sensitive = false;
        $registry = self::registry();

        foreach ( $plugin_files as $plugin_file ) {
            $plugin_file = plugin_basename( $plugin_file );
            if ( ! isset( $installed[ $plugin_file ] ) ) { continue; }
            $data = $installed[ $plugin_file ];
            $slug = self::plugin_slug( $plugin_file );
            $entry = array(
                'plugin_file' => $plugin_file,
                'slug' => $slug,
                'name' => $data['Name'] ?? $slug,
                'version' => $data['Version'] ?? '',
                'description' => wp_strip_all_tags( $data['Description'] ?? '' ),
                'author' => wp_strip_all_tags( $data['AuthorName'] ?? ( $data['Author'] ?? '' ) ),
                'text_domain' => $data['TextDomain'] ?? '',
                'requires_wp' => $data['RequiresWP'] ?? '',
                'requires_php' => $data['RequiresPHP'] ?? '',
                'requires_plugins' => self::parse_requires_plugins( $data['RequiresPlugins'] ?? '' ),
                'active' => is_plugin_active( $plugin_file ),
                'network_active' => is_multisite() && is_plugin_active_for_network( $plugin_file ),
                'nine_plugin' => self::is_nine_plugin( $plugin_file, $data ),
                'bundle_file' => '',
                'bundle_sha256' => '',
                'bundle_size' => 0,
            );

            if ( $include_files ) {
                $bundle = self::create_plugin_zip( $plugin_file, $slug, $bundle_dir );
                if ( ! is_wp_error( $bundle ) ) {
                    $entry['bundle_file'] = 'plugins/' . basename( $bundle );
                    $entry['bundle_sha256'] = hash_file( 'sha256', $bundle );
                    $entry['bundle_size'] = (int) filesize( $bundle );
                } else {
                    $entry['bundle_error'] = $bundle->get_error_message();
                }
            }

            $spec = self::plugin_spec( $plugin_file, $slug, $registry );
            $source_scan = self::scan_plugin_source( $plugin_file );
            $post_types = array_values( array_unique( array_merge( (array)$spec['post_types'], (array)$source_scan['post_types'] ) ) );
            $taxonomies = array_values( array_unique( array_merge( (array)$spec['taxonomies'], (array)$source_scan['taxonomies'] ) ) );
            $context[$plugin_file] = array( 'post_types'=>$post_types, 'taxonomies'=>$taxonomies, 'media_ids'=>array() );
            $entry['detected_post_types'] = $post_types;
            $entry['detected_taxonomies'] = $taxonomies;

            if ( $include_settings || $include_tables ) {
                $bundle = array(
                    'plugin_file' => $plugin_file,
                    'slug' => $slug,
                    'options' => array(),
                    'tables' => array(),
                    'discovery' => array( 'options' => array(), 'tables' => array(), 'post_types'=>$post_types, 'taxonomies'=>$taxonomies ),
                );
                if ( $include_settings ) {
                    $names = self::discover_option_names( $plugin_file, $slug, $spec );
                    $bundle['discovery']['options'] = $names;
                    foreach ( $names as $name ) {
                        $exists = get_option( $name, '__NINE_BACKUP_MISSING__' );
                        if ( '__NINE_BACKUP_MISSING__' === $exists ) { continue; }
                        $sensitive = self::is_sensitive_option( $name );
                        if ( $sensitive && ! $include_sensitive ) {
                            $bundle['options'][] = array( 'name' => $name, 'redacted' => true, 'sensitive' => true );
                        } else {
                            $bundle['options'][] = array( 'name' => $name, 'value' => $exists, 'redacted' => false, 'sensitive' => $sensitive );
                            $context[$plugin_file]['media_ids'] = array_values( array_unique( array_merge( $context[$plugin_file]['media_ids'], self::discover_media_ids( $exists ) ) ) );
                        }
                    }
                }
                if ( $include_tables ) {
                    $table_names = self::discover_table_names( $plugin_file, $spec );
                    $bundle['discovery']['tables'] = $table_names;
                    foreach ( $table_names as $table ) {
                        $table_bundle = self::read_table( $table );
                        if ( $table_bundle ) { $bundle['tables'][] = $table_bundle; }
                    }
                }
                $settings_bundles[] = $bundle;
                $entry['settings_count'] = count( $bundle['options'] );
                $entry['tables_count'] = count( $bundle['tables'] );
            }
            $records[] = $entry;
        }
        return array( 'plugins' => $records, 'plugin_settings' => $settings_bundles, 'context' => $context );
    }

    private static function create_plugin_zip( $plugin_file, $slug, $bundle_dir ) {
        $plugin_file = plugin_basename( $plugin_file );
        $abs_main = trailingslashit( WP_PLUGIN_DIR ) . $plugin_file;
        if ( ! is_file( $abs_main ) ) { return new WP_Error( 'nine_plugin_missing', 'Plugin main file is missing: ' . $plugin_file ); }
        $dest = trailingslashit( $bundle_dir ) . sanitize_file_name( $slug . '.zip' );
        $dir = dirname( $plugin_file );
        if ( '.' === $dir ) {
            $result = Nine_Backup_Zip::create_named_files( array( array( 'path' => $abs_main, 'name' => basename( $plugin_file ) ) ), $dest );
        } else {
            $source = trailingslashit( WP_PLUGIN_DIR ) . $dir;
            $result = Nine_Backup_Zip::create_from_directory_prefixed( $source, $dest, basename( $dir ) );
        }
        return is_wp_error( $result ) ? $result : $dest;
    }

    private static function plugin_spec( $plugin_file, $slug, array $registry ) {
        $spec = array( 'option_names'=>array(), 'option_prefixes'=>array(), 'table_names'=>array(), 'table_suffixes'=>array(), 'post_types'=>array(), 'taxonomies'=>array() );
        foreach ( array( $plugin_file, $slug ) as $key ) {
            if ( isset( $registry[ $key ] ) && is_array( $registry[ $key ] ) ) {
                foreach ( $spec as $field => $default ) {
                    if ( ! empty( $registry[ $key ][ $field ] ) ) { $spec[ $field ] = array_merge( $spec[ $field ], (array) $registry[ $key ][ $field ] ); }
                }
            }
        }
        return $spec;
    }

    private static function discover_option_names( $plugin_file, $slug, array $spec ) {
        global $wpdb;
        $names = array_values( array_filter( array_map( 'sanitize_text_field', $spec['option_names'] ) ) );
        $source = self::scan_plugin_source( $plugin_file );
        foreach ( $source['options'] as $name ) { $names[] = $name; }

        $slug_under = str_replace( '-', '_', sanitize_key( $slug ) );
        $prefixes = array_merge( (array) $spec['option_prefixes'], array( $slug_under . '_', $slug . '_' ) );
        foreach ( array_values( array_unique( array_filter( $prefixes ) ) ) as $prefix ) {
            $like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $prefix ) . '%' : $prefix . '%';
            $rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 500", $like ) );
            $names = array_merge( $names, (array) $rows );
        }
        $names = array_values( array_unique( array_filter( $names ) ) );
        sort( $names, SORT_NATURAL | SORT_FLAG_CASE );
        return $names;
    }

    private static function discover_table_names( $plugin_file, array $spec ) {
        global $wpdb;
        $names = array_values( array_filter( array_map( function( $n ) { return preg_replace( '/[^A-Za-z0-9_]/', '', $n ); }, $spec['table_names'] ) ) );
        foreach ( (array) $spec['table_suffixes'] as $suffix ) {
            $suffix = preg_replace( '/[^A-Za-z0-9_]/', '', $suffix );
            if ( $suffix ) { $names[] = $wpdb->prefix . $suffix; }
        }
        $source = self::scan_plugin_source( $plugin_file );
        foreach ( $source['table_suffixes'] as $suffix ) { $names[] = $wpdb->prefix . $suffix; }
        $existing = array();
        foreach ( array_values( array_unique( $names ) ) as $table ) {
            if ( 0 !== strpos( $table, $wpdb->prefix ) ) { continue; }
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $found === $table ) { $existing[] = $table; }
        }
        return $existing;
    }

    private static function scan_plugin_source( $plugin_file ) {
        static $cache = array();
        if ( isset( $cache[ $plugin_file ] ) ) { return $cache[ $plugin_file ]; }
        $base = trailingslashit( WP_PLUGIN_DIR ) . $plugin_file;
        $dir = dirname( $plugin_file );
        $files = array();
        if ( '.' === $dir ) {
            $files[] = $base;
        } else {
            $root = trailingslashit( WP_PLUGIN_DIR ) . $dir;
            if ( is_dir( $root ) ) {
                $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
                $settings = get_option( 'nine_backup_settings', array() );
                $safe_mode = ! array_key_exists( 'shared_host_safe_mode', $settings ) || ! empty( $settings['shared_host_safe_mode'] );
                $scan_limit = (int) apply_filters( 'nine_backup_plugin_scan_file_limit', $safe_mode ? 120 : 400, $plugin_file );
                $scan_limit = max( 20, min( 1000, $scan_limit ) );
                foreach ( $it as $file ) {
                    if ( count( $files ) >= $scan_limit ) { break; }
                    if ( $file->isFile() && ! $file->isLink() && 'php' === strtolower( $file->getExtension() ) && $file->getSize() <= 2 * MB_IN_BYTES ) { $files[] = $file->getPathname(); }
                }
            }
        }
        $options = array();
        $tables = array();
        $post_types = array();
        $taxonomies = array();
        foreach ( $files as $file ) {
            $text = @file_get_contents( $file );
            if ( false === $text ) { continue; }
            if ( preg_match_all( '/\b(?:get|update|add|delete)_(?:site_)?option\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i', $text, $m ) ) {
                foreach ( $m[1] as $name ) { if ( strlen( $name ) <= 191 ) { $options[] = $name; } }
            }
            if ( preg_match_all( '/\bregister_setting\s*\(\s*[\'\"][^\'\"]+[\'\"]\s*,\s*[\'\"]([^\'\"]+)[\'\"]/i', $text, $m ) ) {
                foreach ( $m[1] as $name ) { if ( strlen( $name ) <= 191 ) { $options[] = $name; } }
            }
            if ( preg_match_all( '/\bregister_post_type\s*\(\s*[\'\"]([a-zA-Z0-9_\-]+)[\'\"]/i', $text, $m ) ) { $post_types = array_merge( $post_types, $m[1] ); }
            if ( preg_match_all( '/\bregister_taxonomy\s*\(\s*[\'\"]([a-zA-Z0-9_\-]+)[\'\"]/i', $text, $m ) ) { $taxonomies = array_merge( $taxonomies, $m[1] ); }
            if ( preg_match_all( '/\$wpdb->(?:base_)?prefix\s*\.\s*[\'\"]([A-Za-z0-9_]+)[\'\"]/i', $text, $m ) ) {
                $tables = array_merge( $tables, $m[1] );
            }
            if ( preg_match_all( '/\{\$wpdb->(?:base_)?prefix\}([A-Za-z0-9_]+)/i', $text, $m ) ) {
                $tables = array_merge( $tables, $m[1] );
            }
        }
        $cache[ $plugin_file ] = array(
            'options' => array_values( array_unique( $options ) ),
            'table_suffixes' => array_values( array_unique( $tables ) ),
            'post_types' => array_values( array_unique( $post_types ) ),
            'taxonomies' => array_values( array_unique( $taxonomies ) ),
        );
        return $cache[ $plugin_file ];
    }

    private static function discover_media_ids( $value ) {
        $ids = array();
        $walk = function( $node, $key = '' ) use ( &$walk, &$ids ) {
            if ( is_array( $node ) ) {
                foreach ( $node as $k=>$v ) { $walk( $v, (string)$k ); }
                return;
            }
            if ( is_object( $node ) ) { foreach ( get_object_vars($node) as $k=>$v ) { $walk($v,(string)$k); } return; }
            if ( is_numeric( $node ) && preg_match( '/(?:image|media|attachment|logo|icon|thumbnail).*id|^id$/i', $key ) ) {
                $id = absint( $node ); if ( $id && 'attachment' === get_post_type( $id ) ) { $ids[] = $id; }
            }
            if ( is_string( $node ) && preg_match( '#^https?://#i', $node ) && function_exists('attachment_url_to_postid') ) {
                $id = absint( attachment_url_to_postid( $node ) ); if ( $id ) { $ids[] = $id; }
            }
        };
        $walk( $value );
        return array_values( array_unique( $ids ) );
    }

    private static function read_table( $table ) {
        global $wpdb;
        $table = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
        if ( ! $table || 0 !== strpos( $table, $wpdb->prefix ) ) { return null; }
        $rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $schema = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return array( 'table'=>$table, 'schema'=>$schema, 'rows'=>$rows );
    }

    private static function parse_requires_plugins( $value ) {
        if ( is_array( $value ) ) { return array_values( array_filter( $value ) ); }
        return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
    }

    public static function is_sensitive_option( $name ) {
        $patterns = apply_filters( 'nine_backup_sensitive_option_patterns', array(
            'password','passwd','secret','token','api_key','apikey','private_key','client_secret','smtp_pass','auth_key','license_key','access_key','consumer_secret'
        ) );
        $lower = strtolower( (string) $name );
        foreach ( $patterns as $pattern ) { if ( '' !== $pattern && false !== strpos( $lower, strtolower( $pattern ) ) ) { return true; } }
        return false;
    }

    public static function destination_plugin_status( array $record ) {
        self::ensure_plugin_api();
        $installed = self::installed_plugins();
        $file = $record['plugin_file'] ?? '';
        $current = $installed[ $file ] ?? null;
        return array(
            'installed' => (bool) $current,
            'current_version' => $current['Version'] ?? '',
            'package_version' => $record['version'] ?? '',
            'active' => $file ? is_plugin_active( $file ) : false,
            'php_compatible' => empty( $record['requires_php'] ) || version_compare( PHP_VERSION, $record['requires_php'], '>=' ),
            'wp_compatible' => empty( $record['requires_wp'] ) || version_compare( get_bloginfo( 'version' ), $record['requires_wp'], '>=' ),
        );
    }

    public static function import_plugins( $extract_dir, array $records, $plugin_strategy, $activation_policy, &$log ) {
        self::ensure_plugin_api();
        if ( 'package_only' === $plugin_strategy ) {
            foreach ( $records as $r ) { $log[] = array( 'type'=>'plugin','status'=>'package-only','message'=>$r['name'] ?? ( $r['plugin_file'] ?? '' ) ); }
            return;
        }
        if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'update_plugins' ) ) {
            $log[] = array( 'type'=>'plugin','status'=>'skipped','message'=>'Current user cannot install/update plugins.' );
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        wp_mkdir_p( Nine_Backup_Exporter::rollback_dir() );

        foreach ( $records as $r ) {
            $plugin_file = plugin_basename( $r['plugin_file'] ?? '' );
            if ( ! $plugin_file ) {
                $log[] = array( 'type'=>'plugin','status'=>'skipped','message'=>'Plugin package metadata is incomplete.' );
                continue;
            }
            if ( empty( $r['bundle_file'] ) ) {
                $log[] = array( 'type'=>'plugin','status'=>'settings-only','message'=>$r['name'] ?? $plugin_file );
                continue;
            }
            $bundle_rel = ltrim( str_replace( '\\', '/', $r['bundle_file'] ?? '' ), '/' );
            if ( ! $bundle_rel || false !== strpos( $bundle_rel, '../' ) ) {
                $log[] = array( 'type'=>'plugin','status'=>'skipped','message'=>'Plugin package metadata is incomplete.' );
                continue;
            }
            $self_plugin = defined( 'NINE_BACKUP_FILE' ) ? plugin_basename( NINE_BACKUP_FILE ) : '';
            if ( $self_plugin && $plugin_file === $self_plugin ) {
                $log[] = array( 'type'=>'plugin','status'=>'skipped','message'=>'9 Backup Manager code was not replaced while it was running. Its settings/data can still be imported; update the manager ZIP separately first.' );
                continue;
            }
            $bundle = trailingslashit( $extract_dir ) . $bundle_rel;
            if ( ! is_file( $bundle ) ) {
                $log[] = array( 'type'=>'plugin','status'=>'error','message'=>'Missing plugin bundle: ' . $bundle_rel );
                continue;
            }
            if ( ! empty( $r['bundle_sha256'] ) && ! hash_equals( strtolower( $r['bundle_sha256'] ), strtolower( hash_file( 'sha256', $bundle ) ) ) ) {
                $log[] = array( 'type'=>'plugin','status'=>'error','message'=>'Plugin bundle integrity check failed: ' . $plugin_file );
                continue;
            }
            $dest_main = trailingslashit( WP_PLUGIN_DIR ) . $plugin_file;
            $exists = file_exists( $dest_main );
            if ( $exists && 'skip_existing' === $plugin_strategy ) {
                $log[] = array( 'type'=>'plugin','status'=>'skipped','message'=>$plugin_file . ' already exists.' );
                continue;
            }
            $status = self::destination_plugin_status( $r );
            if ( empty( $status['php_compatible'] ) || empty( $status['wp_compatible'] ) ) {
                $log[] = array( 'type'=>'plugin','status'=>'skipped','message'=>$plugin_file . ' is not compatible with this WordPress/PHP version.' );
                continue;
            }
            $rollback = '';
            if ( $exists ) {
                $rollback = self::create_rollback( $plugin_file );
                if ( is_wp_error( $rollback ) ) {
                    $log[] = array( 'type'=>'plugin','status'=>'error','message'=>'Could not create rollback for ' . $plugin_file . ': ' . $rollback->get_error_message() );
                    continue;
                }
            }
            $temp = trailingslashit( wp_upload_dir()['basedir'] ) . 'nine-backup-manager/tmp/plugin-' . wp_generate_password( 10, false, false );
            wp_mkdir_p( $temp );
            $unzipped = Nine_Backup_Zip::extract( $bundle, $temp );
            if ( is_wp_error( $unzipped ) ) {
                self::delete_tree( $temp );
                $log[] = array( 'type'=>'plugin','status'=>'error','message'=>$plugin_file . ': ' . $unzipped->get_error_message() );
                continue;
            }
            $source_main = trailingslashit( $temp ) . $plugin_file;
            if ( ! is_file( $source_main ) ) {
                self::delete_tree( $temp );
                $log[] = array( 'type'=>'plugin','status'=>'error','message'=>'Plugin ZIP does not contain expected main file: ' . $plugin_file );
                continue;
            }
            do_action( 'nine_backup_before_plugin_import', $r, $plugin_strategy, $activation_policy );
            $ok = self::replace_plugin_files( $plugin_file, $temp );
            self::delete_tree( $temp );
            if ( is_wp_error( $ok ) ) {
                if ( $rollback && is_file( $rollback ) ) { self::restore_rollback( $plugin_file, $rollback ); }
                $log[] = array( 'type'=>'plugin','status'=>'error','message'=>$plugin_file . ': ' . $ok->get_error_message() );
                continue;
            }
            wp_clean_plugins_cache( true );
            $activation = self::apply_activation_policy( $plugin_file, $r, $activation_policy );
            $detail = $r['name'] ?? $plugin_file;
            if ( $rollback ) { $detail .= ' · rollback: ' . basename( $rollback ); }
            if ( is_wp_error( $activation ) ) { $detail .= ' · activation warning: ' . $activation->get_error_message(); }
            $log[] = array( 'type'=>'plugin','status'=>$exists?'updated':'installed','message'=>$detail );
            do_action( 'nine_backup_after_plugin_import', $r, $plugin_strategy, $activation_policy );
        }
    }

    public static function import_plugin_settings( array $bundles, $strategy, &$log, $source_site = '', $source_prefix = '', $maps = array() ) {
        global $wpdb;
        foreach ( $bundles as $bundle ) {
            $plugin = $bundle['plugin_file'] ?? ( $bundle['slug'] ?? 'plugin' );
            foreach ( (array) ( $bundle['options'] ?? array() ) as $r ) {
                $name = sanitize_text_field( $r['name'] ?? '' );
                if ( ! $name || ! empty( $r['redacted'] ) || 'cron' === $name ) { continue; }
                if ( 'skip' === $strategy && false !== get_option( $name, false ) ) { continue; }
                $value = self::remap_context_value( $r['value'] ?? null, $source_site, home_url( '/' ) );
                $value = apply_filters( 'nine_backup_remap_plugin_option', $value, $name, $plugin, $bundle );
                update_option( $name, $value, false );
                $log[] = array( 'type'=>'plugin-setting','status'=>'imported','message'=>$plugin . ' · ' . $name );
            }
            foreach ( (array) ( $bundle['tables'] ?? array() ) as $table_bundle ) {
                $source_table = preg_replace( '/[^A-Za-z0-9_]/', '', $table_bundle['table'] ?? '' );
                $table = $source_table;
                if ( $source_prefix && 0 === strpos( $source_table, $source_prefix ) ) { $table = $wpdb->prefix . substr( $source_table, strlen( $source_prefix ) ); }
                if ( ! $table || 0 !== strpos( $table, $wpdb->prefix ) ) { continue; }
                $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
                if ( $exists !== $table ) {
                    $log[] = array( 'type'=>'plugin-table','status'=>'skipped','message'=>$table . ' does not exist; activate/install its plugin first.' );
                    continue;
                }
                if ( 'skip' === $strategy ) { continue; }
                $count = 0;
                foreach ( (array) ( $table_bundle['rows'] ?? array() ) as $row ) {
                    if ( ! is_array( $row ) ) { continue; }
                    $row = self::remap_context_value( $row, $source_site, home_url( '/' ) );
                    $row = apply_filters( 'nine_backup_remap_plugin_table_row', $row, $source_table, $table, $plugin, $maps, $bundle );
                    $ok = 'duplicate' === $strategy ? $wpdb->insert( $table, $row ) : $wpdb->replace( $table, $row );
                    if ( false !== $ok ) { $count++; }
                }
                $log[] = array( 'type'=>'plugin-table','status'=>'imported','message'=>$plugin . ' · ' . $table,'rows'=>$count );
            }
        }
    }

    private static function create_rollback( $plugin_file ) {
        $stamp = current_time( 'Y-m-d_H-i-s' );
        $slug = self::plugin_slug( $plugin_file );
        $dest = trailingslashit( Nine_Backup_Exporter::rollback_dir() ) . sanitize_file_name( $slug . '-before-import-' . $stamp . '.zip' );
        $dir = dirname( $plugin_file );
        if ( '.' === $dir ) {
            $main = trailingslashit( WP_PLUGIN_DIR ) . $plugin_file;
            $result = Nine_Backup_Zip::create_named_files( array( array( 'path'=>$main,'name'=>basename($plugin_file) ) ), $dest );
        } else {
            $result = Nine_Backup_Zip::create_from_directory_prefixed( trailingslashit( WP_PLUGIN_DIR ) . $dir, $dest, basename( $dir ) );
        }
        return is_wp_error( $result ) ? $result : $dest;
    }

    private static function replace_plugin_files( $plugin_file, $extract_root ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        global $wp_filesystem;
        // Bluehost/shared hosting can have correct WordPress credentials while raw PHP copy/unlink
        // fails because of ownership. Prefer the WordPress Filesystem API before touching plugins.
        if ( ! function_exists( 'WP_Filesystem' ) || ! WP_Filesystem() || ! is_object( $wp_filesystem ) ) {
            return new WP_Error( 'nine_plugin_filesystem', 'WordPress could not obtain filesystem write access. On Bluehost, use Files & Access → Fix File Ownership, then retry. Settings/data can still be imported without replacing plugin code.' );
        }
        $dir = dirname( $plugin_file );
        if ( '.' === $dir ) {
            $src = trailingslashit( $extract_root ) . basename( $plugin_file );
            $dst = trailingslashit( WP_PLUGIN_DIR ) . basename( $plugin_file );
            if ( ! $wp_filesystem->copy( $src, $dst, true, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : false ) ) {
                return new WP_Error( 'nine_plugin_copy', 'WordPress Filesystem could not copy the plugin file into wp-content/plugins.' );
            }
            return true;
        }
        $src_dir = trailingslashit( $extract_root ) . $dir;
        $dst_dir = trailingslashit( WP_PLUGIN_DIR ) . $dir;
        if ( $wp_filesystem->is_dir( $dst_dir ) && ! $wp_filesystem->delete( $dst_dir, true ) ) {
            return new WP_Error( 'nine_plugin_remove', 'WordPress Filesystem could not remove the existing plugin directory.' );
        }
        if ( ! $wp_filesystem->mkdir( $dst_dir, defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : false ) && ! $wp_filesystem->is_dir( $dst_dir ) ) {
            return new WP_Error( 'nine_plugin_mkdir', 'WordPress Filesystem could not create the plugin destination directory.' );
        }
        $copied = copy_dir( $src_dir, $dst_dir );
        return is_wp_error( $copied ) ? $copied : true;
    }

    private static function restore_rollback( $plugin_file, $rollback_zip ) {
        $temp = trailingslashit( wp_upload_dir()['basedir'] ) . 'nine-backup-manager/tmp/rollback-' . wp_generate_password( 8, false, false );
        wp_mkdir_p( $temp );
        $ok = Nine_Backup_Zip::extract( $rollback_zip, $temp );
        if ( ! is_wp_error( $ok ) ) { self::replace_plugin_files( $plugin_file, $temp ); }
        self::delete_tree( $temp );
    }

    private static function apply_activation_policy( $plugin_file, array $record, $policy ) {
        if ( ! current_user_can( 'activate_plugins' ) ) { return true; }
        if ( 'inactive' === $policy ) {
            if ( is_plugin_active( $plugin_file ) ) { deactivate_plugins( $plugin_file, true ); }
            return true;
        }
        $source_active = ! empty( $record['active'] );
        if ( 'preserve' === $policy && ! $source_active && is_plugin_active( $plugin_file ) ) {
            deactivate_plugins( $plugin_file, true );
            return true;
        }
        $should_activate = 'activate' === $policy || ( 'preserve' === $policy && $source_active );
        if ( $should_activate && ! is_plugin_active( $plugin_file ) ) {
            $network_wide = is_multisite() && ! empty( $record['network_active'] ) && current_user_can( 'manage_network_plugins' );
            $res = activate_plugin( $plugin_file, '', $network_wide, true );
            return is_wp_error( $res ) ? $res : true;
        }
        return true;
    }

    private static function remap_context_value( $value, $source_site, $destination_site ) {
        $source = untrailingslashit( (string) $source_site );
        $destination = untrailingslashit( (string) $destination_site );
        if ( ! $source || ! $destination || $source === $destination ) { return $value; }
        if ( is_array( $value ) ) { foreach ( $value as $k=>$v ) { $value[$k] = self::remap_context_value( $v, $source, $destination ); } return $value; }
        if ( is_object( $value ) ) { foreach ( get_object_vars( $value ) as $k=>$v ) { $value->$k = self::remap_context_value( $v, $source, $destination ); } return $value; }
        return is_string( $value ) ? str_replace( array($source.'/', $source), array($destination.'/', $destination), $value ) : $value;
    }

    private static function delete_tree( $dir ) {
        if ( ! is_dir( $dir ) ) { return true; }
        $items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $items as $item ) {
            $ok = $item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
            if ( ! $ok && file_exists( $item->getPathname() ) ) { return false; }
        }
        return @rmdir( $dir );
    }
}
