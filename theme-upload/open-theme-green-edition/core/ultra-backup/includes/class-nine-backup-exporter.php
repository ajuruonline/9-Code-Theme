<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nine_Backup_Exporter {
    private $config = array();
    private $payload = array();
    private $counts = array();
    private $seen = array();
    private $base_dir = '';
    private $work_dir = '';

    public function __construct( array $config = array() ) {
        $this->config = $this->normalize_config( $config );
        $upload = wp_upload_dir();
        $this->base_dir = trailingslashit( $upload['basedir'] ) . 'nine-backup-manager';
    }

    public static function archive_dir() {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'nine-backup-manager/archives';
    }

    public static function rollback_dir() {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'nine-backup-manager/plugin-rollbacks';
    }

    public static function ensure_dirs() {
        $dirs = array(
            self::archive_dir(),
            self::rollback_dir(),
            dirname( self::archive_dir() ) . '/tmp',
        );
        foreach ( $dirs as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
            if ( file_exists( $dir ) && ! file_exists( trailingslashit( $dir ) . 'index.html' ) ) {
                @file_put_contents( trailingslashit( $dir ) . 'index.html', '' );
            }
        }
        $base = dirname( self::archive_dir() );
        if ( is_dir( $base ) ) {
            if ( ! file_exists( trailingslashit($base) . '.htaccess' ) ) {
                @file_put_contents( trailingslashit($base) . '.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
            }
            if ( ! file_exists( trailingslashit($base) . 'web.config' ) ) {
                @file_put_contents( trailingslashit($base) . 'web.config', '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>' );
            }
        }
    }

    private function normalize_config( array $config ) {
        $defaults = array(
            'name' => 'Manual Backup',
            'tokens' => array(),
            'bulk' => array(),
            'post_types' => array(),
            'taxonomies' => array(),
            'roles' => array(),
            'date_from' => '',
            'date_to' => '',
            'keyword' => '',
            'include_dependencies' => true,
            'include_term_children' => true,
            'include_term_content' => false,
            'include_user_content' => false,
            'include_media_files' => false,
            'include_nine_options' => false,
            'plugin_files' => array(),
            'plugin_include_files' => true,
            'plugin_include_settings' => true,
            'plugin_include_tables' => true,
            'plugin_include_records' => true,
            'plugin_include_dependencies' => true,
            'include_sensitive_settings' => false,
            'option_names' => array(),
            'table_names' => array(),
            'keep_server_copy' => true,
            'copy_to_drive_folder' => true,
            'source' => 'manual',
        );
        $config = wp_parse_args( $config, $defaults );
        foreach ( array( 'tokens','bulk','post_types','taxonomies','roles','plugin_files','option_names','table_names' ) as $key ) {
            if ( ! is_array( $config[ $key ] ) ) { $config[ $key ] = array(); }
            $config[ $key ] = array_values( array_filter( array_map( 'sanitize_text_field', $config[ $key ] ) ) );
        }
        $config['name'] = sanitize_text_field( $config['name'] );
        $config['keyword'] = sanitize_text_field( $config['keyword'] );
        $config['date_from'] = sanitize_text_field( $config['date_from'] );
        $config['date_to'] = sanitize_text_field( $config['date_to'] );
        return $config;
    }

    public function create_package() {
        self::ensure_dirs();
        $failures = Nine_Backup_Health::hard_failures();
        if ( $failures ) {
            $names = array();
            foreach ( $failures as $row ) { $names[] = $row['name'] . ': ' . $row['value']; }
            return new WP_Error( 'nine_backup_preflight', 'Backup preflight failed — ' . implode( '; ', $names ) );
        }

        $slug = sanitize_title( $this->config['name'] );
        if ( '' === $slug ) { $slug = 'backup'; }
        $stamp = current_time( 'Y-m-d_H-i-s' );
        $token = wp_generate_password( 6, false, false );
        $this->work_dir = dirname( self::archive_dir() ) . '/tmp/' . $slug . '-' . $stamp . '-' . strtolower( $token );

        try {
            foreach ( array( $this->work_dir, $this->work_dir . '/data', $this->work_dir . '/reports', $this->work_dir . '/elementor-json', $this->work_dir . '/media', $this->work_dir . '/plugins' ) as $dir ) {
                if ( ! wp_mkdir_p( $dir ) && ! is_dir( $dir ) ) {
                    throw new RuntimeException( 'Could not create backup workspace: ' . $dir );
                }
            }
            if ( ! is_writable( $this->work_dir ) ) {
                throw new RuntimeException( 'Backup workspace is not writable: ' . $this->work_dir );
            }

            $this->collect_explicit_tokens();
            $this->collect_bulk();
            $this->collect_plugins();
            $this->collect_options();
            $this->collect_tables();
            if ( in_array( 'disaster_snapshot', $this->config['bulk'], true ) ) {
                $snapshot = $this->create_disaster_snapshot();
                if ( is_wp_error( $snapshot ) ) { throw new RuntimeException( $snapshot->get_error_message() ); }
            }
            $elementor_bundle = $this->create_elementor_bundle();
            if ( is_wp_error( $elementor_bundle ) ) { Nine_Backup_Health::log( 'elementor_bundle', $elementor_bundle->get_error_message() ); }

            $this->payload = apply_filters( 'nine_backup_export_payload', $this->payload, $this->config );
            $written = $this->write_payload_files();
            if ( is_wp_error( $written ) ) { throw new RuntimeException( $written->get_error_message() ); }
            $manifest = $this->write_manifest();
            if ( is_wp_error( $manifest ) ) { throw new RuntimeException( $manifest->get_error_message() ); }

            $filename = '9backup-' . $slug . '-' . $stamp . '-' . strtolower(wp_generate_password(20,false,false)) . '.zip';
            $archive_path = trailingslashit( self::archive_dir() ) . $filename;
            $zipped = $this->zip_directory( $this->work_dir, $archive_path );
            if ( is_wp_error( $zipped ) ) { throw new RuntimeException( $zipped->get_error_message() ); }
            if ( ! is_file( $archive_path ) || filesize( $archive_path ) < 22 ) {
                throw new RuntimeException( 'The final ZIP was not created correctly.' );
            }

            $drive_copy = $this->copy_to_drive_sync_folder( $archive_path );
            if ( is_wp_error( $drive_copy ) ) {
                Nine_Backup_Health::log( 'drive_copy', $drive_copy->get_error_message() );
                $drive_copy = array( 'status' => 'error', 'message' => $drive_copy->get_error_message() );
            }
            // Direct-to-device exports must not prune existing disaster-recovery backups.
            if ( 'direct-download' !== $this->config['source'] ) { $this->apply_retention_policy( $archive_path ); }

            $result = array(
                'path' => $archive_path,
                'filename' => $filename,
                'size' => @filesize( $archive_path ),
                'counts' => $this->counts,
                'drive_copy' => $drive_copy,
            );
            $this->delete_tree( $this->work_dir );
            return $result;
        } catch ( Throwable $e ) {
            Nine_Backup_Health::log( 'export', $e->getMessage(), array( 'backup'=>$this->config['name'] ) );
            if ( $this->work_dir ) { $this->delete_tree( $this->work_dir ); }
            return new WP_Error( 'nine_backup_export_failed', $e->getMessage() );
        }
    }

    private function collect_explicit_tokens() {
        foreach ( $this->config['tokens'] as $token ) {
            $parts = explode( ':', $token, 2 );
            if ( 2 !== count( $parts ) ) { continue; }
            list( $kind, $raw ) = $parts;
            if ( 'plugin' === $kind ) {
                $plugin_file = plugin_basename( sanitize_text_field( $raw ) );
                if ( $plugin_file ) { $this->config['plugin_files'][] = $plugin_file; }
                continue;
            }
            $id = absint( $raw );
            if ( ! $id ) { continue; }
            switch ( $kind ) {
                case 'post': $this->add_post( $id, 'posts' ); break;
                case 'media': $this->add_post( $id, 'media' ); break;
                case 'user': $this->add_user( $id ); break;
                case 'term': $this->add_term( $id ); break;
                case 'elementor': $this->add_elementor_template( $id ); break;
                case 'acf': $this->add_acf_group( $id ); break;
                case 'menu': $this->add_menu( $id ); break;
            }
        }
        $this->config['plugin_files'] = array_values( array_unique( $this->config['plugin_files'] ) );
    }

    private function collect_bulk() {
        $bulk = $this->config['bulk'];
        if ( in_array( 'full_portability', $bulk, true ) ) {
            $bulk = array_values( array_unique( array_merge( $bulk, array( 'full_records', 'plugins' ) ) ) );
            $this->config['bulk'] = $bulk;
        }
        if ( in_array( 'full_records', $bulk, true ) ) {
            $bulk = array_values( array_unique( array_merge( $bulk, array( 'posts','users','terms','media','elementor','acf','menus','theme_settings','site_settings' ) ) ) );
            $this->config['bulk'] = $bulk;
            $this->config['include_nine_options'] = true;
        }
        if ( in_array( 'posts', $bulk, true ) ) {
            $post_types = $this->config['post_types'];
            if ( empty( $post_types ) ) {
                $post_types = get_post_types( array( 'show_ui'=>true ), 'names' );
                foreach ( array( 'wp_template','wp_template_part','wp_global_styles','wp_navigation' ) as $design_type ) { if ( post_type_exists( $design_type ) ) { $post_types[] = $design_type; } }
                $post_types = array_values( array_diff( array_unique( $post_types ), array( 'attachment','elementor_library','acf-field','acf-field-group' ) ) );
            }
            $args = array(
                'post_type' => $post_types,
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => true,
                'suppress_filters' => false,
            );
            if ( $this->config['keyword'] ) { $args['s'] = $this->config['keyword']; }
            if ( $this->config['date_from'] || $this->config['date_to'] ) {
                $date = array( 'inclusive' => true );
                if ( $this->config['date_from'] ) { $date['after'] = $this->config['date_from']; }
                if ( $this->config['date_to'] ) { $date['before'] = $this->config['date_to'] . ' 23:59:59'; }
                $args['date_query'] = array( $date );
            }
            foreach ( get_posts( $args ) as $id ) { $this->add_post( $id, 'posts' ); }
        }

        if ( in_array( 'users', $bulk, true ) ) {
            $args = array( 'fields' => 'ID' );
            if ( $this->config['roles'] ) { $args['role__in'] = $this->config['roles']; }
            $q = new WP_User_Query( $args );
            foreach ( $q->get_results() as $id ) { $this->add_user( $id ); }
        }

        if ( in_array( 'terms', $bulk, true ) ) {
            $taxonomies = $this->config['taxonomies'];
            if ( empty( $taxonomies ) ) { $taxonomies = get_taxonomies( array( 'public' => true ), 'names' ); }
            $terms = get_terms( array( 'taxonomy' => $taxonomies, 'hide_empty' => false ) );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) { $this->add_term( $term->term_id, $term->taxonomy ); }
            }
        }

        if ( in_array( 'media', $bulk, true ) ) {
            foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
                $this->add_post( $id, 'media' );
            }
        }

        if ( in_array( 'elementor', $bulk, true ) ) {
            foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
                $this->add_elementor_template( $id );
            }
        }

        if ( in_array( 'acf', $bulk, true ) && function_exists( 'acf_get_field_groups' ) ) {
            foreach ( acf_get_field_groups() as $group ) {
                if ( ! empty( $group['ID'] ) ) { $this->add_acf_group( $group['ID'] ); }
            }
        }

        if ( in_array( 'menus', $bulk, true ) ) {
            foreach ( wp_get_nav_menus() as $menu ) { $this->add_menu( $menu->term_id ); }
        }
    }

    private function collect_plugins() {
        $plugin_files = Nine_Backup_Plugins::resolve_selected_files( $this->config );
        if ( ! $plugin_files ) { return; }
        $bundles = Nine_Backup_Plugins::export_plugins( $plugin_files, $this->work_dir, $this->config );
        if ( ! empty( $bundles['plugins'] ) ) {
            $this->payload['plugins'] = $bundles['plugins'];
            $this->counts['plugins'] = count( $bundles['plugins'] );
        }
        if ( ! empty( $bundles['plugin_settings'] ) ) {
            $this->payload['plugin_settings'] = $bundles['plugin_settings'];
            $option_count = 0; $table_count = 0; $table_rows = 0;
            foreach ( $bundles['plugin_settings'] as $bundle ) {
                $option_count += count( (array) ( $bundle['options'] ?? array() ) );
                $table_count += count( (array) ( $bundle['tables'] ?? array() ) );
                foreach ( (array) ( $bundle['tables'] ?? array() ) as $table ) { $table_rows += count( (array) ( $table['rows'] ?? array() ) ); }
            }
            $this->counts['plugin_settings'] = $option_count;
            $this->counts['plugin_tables'] = $table_count;
            $this->counts['plugin_table_rows'] = $table_rows;
        }
        if ( ! empty( $this->config['plugin_include_records'] ) ) {
            foreach ( (array) ( $bundles['context'] ?? array() ) as $context ) {
                foreach ( (array) ( $context['post_types'] ?? array() ) as $post_type ) {
                    $post_type = sanitize_key( $post_type );
                    if ( ! $post_type ) { continue; }
                    foreach ( get_posts( array( 'post_type'=>$post_type, 'post_status'=>'any', 'posts_per_page'=>-1, 'fields'=>'ids', 'no_found_rows'=>true ) ) as $id ) { $this->add_post( $id, 'posts' ); }
                }
                foreach ( (array) ( $context['taxonomies'] ?? array() ) as $taxonomy ) {
                    $taxonomy = sanitize_key( $taxonomy );
                    if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) { continue; }
                    $terms = get_terms( array( 'taxonomy'=>$taxonomy, 'hide_empty'=>false ) );
                    if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { $this->add_term( $term->term_id, $taxonomy ); } }
                }
                if ( ! empty( $this->config['include_dependencies'] ) ) {
                    foreach ( (array) ( $context['media_ids'] ?? array() ) as $media_id ) { $this->add_post( absint($media_id), 'media' ); }
                }
            }
        }
    }

    private function add_post( $id, $bucket = 'posts' ) {
        $id = absint( $id );
        if ( ! $id || isset( $this->seen[ $bucket ][ $id ] ) ) { return; }
        $post = get_post( $id );
        if ( ! $post ) { return; }
        $this->seen[ $bucket ][ $id ] = true;

        $record = array(
            'source_id' => $post->ID,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'post_author' => $post->post_author,
            'post_date' => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
            'post_modified' => $post->post_modified,
            'post_modified_gmt' => $post->post_modified_gmt,
            'post_title' => $post->post_title,
            'post_name' => $post->post_name,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'post_password' => $post->post_password,
            'guid' => $post->guid,
            'meta' => $this->read_meta( 'post', $id ),
            'terms' => array(),
        );

        foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
            $terms = wp_get_object_terms( $id, $taxonomy );
            if ( is_wp_error( $terms ) ) { continue; }
            foreach ( $terms as $term ) {
                $record['terms'][] = array(
                    'taxonomy' => $taxonomy,
                    'source_term_id' => $term->term_id,
                    'slug' => $term->slug,
                    'name' => $term->name,
                );
                if ( $this->config['include_dependencies'] ) { $this->add_term( $term->term_id, $taxonomy ); }
            }
        }

        if ( 'attachment' === $post->post_type ) {
            $file = get_attached_file( $id );
            $record['attachment_url'] = wp_get_attachment_url( $id );
            $record['attached_file_meta'] = get_post_meta( $id, '_wp_attached_file', true );
            if ( $this->config['include_media_files'] && $file && file_exists( $file ) ) {
                $safe = $id . '-' . sanitize_file_name( basename( $file ) );
                if ( @copy( $file, $this->work_dir . '/media/' . $safe ) ) {
                    $record['package_file'] = 'media/' . $safe;
                }
            }
        }

        if ( $this->config['include_dependencies'] && ! empty( $record['meta']['_elementor_data'][0] ) ) {
            $this->collect_elementor_media_dependencies( $record['meta']['_elementor_data'][0] );
        }

        $this->payload[ $bucket ][] = $record;
        $this->counts[ $bucket ] = isset( $this->counts[ $bucket ] ) ? $this->counts[ $bucket ] + 1 : 1;

        if ( $this->config['include_dependencies'] ) {
            if ( $post->post_author ) { $this->add_user( $post->post_author ); }
            $thumb = get_post_thumbnail_id( $id );
            if ( $thumb ) { $this->add_post( $thumb, 'media' ); }
        }
    }

    private function add_user( $id ) {
        $id = absint( $id );
        if ( ! $id || isset( $this->seen['users'][ $id ] ) ) { return; }
        $user = get_userdata( $id );
        if ( ! $user ) { return; }
        $this->seen['users'][ $id ] = true;
        $meta = $this->read_meta( 'user', $id );
        unset( $meta['session_tokens'], $meta['_application_passwords'] );
        foreach (array_keys($meta) as $meta_key) {
            if (preg_match('/(?:pass(?:word)?|secret|token|auth|2fa|totp|otp|recovery.?code|api.?key|payment.?key|license.?key|session)/i', (string)$meta_key)) {
                unset($meta[$meta_key]);
            }
        }
        $this->payload['users'][] = array(
            'source_id' => $id,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'user_nicename' => $user->user_nicename,
            'display_name' => $user->display_name,
            'user_url' => $user->user_url,
            'user_registered' => $user->user_registered,
            'roles' => array_values( $user->roles ),
            'meta' => $meta,
            'password_included' => false,
        );
        $this->counts['users'] = isset( $this->counts['users'] ) ? $this->counts['users'] + 1 : 1;
        if ( ! empty( $this->config['include_user_content'] ) ) {
            $types = get_post_types( array( 'show_ui' => true ), 'names' );
            unset( $types['attachment'], $types['acf-field'], $types['acf-field-group'] );
            foreach ( get_posts( array( 'post_type'=>array_values($types), 'post_status'=>'any', 'posts_per_page'=>-1, 'fields'=>'ids', 'author'=>$id ) ) as $post_id ) {
                $this->add_post( $post_id, 'posts' );
            }
        }
    }

    private function add_term( $id, $taxonomy = '' ) {
        $id = absint( $id );
        if ( ! $id ) { return; }
        $term = $taxonomy ? get_term( $id, $taxonomy ) : get_term( $id );
        if ( ! $term || is_wp_error( $term ) ) { return; }
        $key = $term->taxonomy . ':' . $id;
        if ( isset( $this->seen['terms'][ $key ] ) ) { return; }
        $this->seen['terms'][ $key ] = true;
        $parent_slug = '';
        if ( $term->parent ) {
            $parent = get_term( $term->parent, $term->taxonomy );
            if ( $parent && ! is_wp_error( $parent ) ) { $parent_slug = $parent->slug; }
            if ( $this->config['include_dependencies'] ) { $this->add_term( $term->parent, $term->taxonomy ); }
        }
        $this->payload['terms'][] = array(
            'source_id' => $term->term_id,
            'taxonomy' => $term->taxonomy,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'parent_source_id' => $term->parent,
            'parent_slug' => $parent_slug,
            'meta' => $this->read_meta( 'term', $term->term_id ),
        );
        $this->counts['terms'] = isset( $this->counts['terms'] ) ? $this->counts['terms'] + 1 : 1;
        if ( ! empty( $this->config['include_term_children'] ) && is_taxonomy_hierarchical( $term->taxonomy ) ) {
            foreach ( get_term_children( $term->term_id, $term->taxonomy ) as $child_id ) { $this->add_term( $child_id, $term->taxonomy ); }
        }
        if ( ! empty( $this->config['include_term_content'] ) ) {
            $object_ids = get_objects_in_term( $term->term_id, $term->taxonomy );
            if ( ! is_wp_error( $object_ids ) ) { foreach ( $object_ids as $object_id ) { $this->add_post( $object_id, 'posts' ); } }
        }
    }

    private function add_elementor_template( $id ) {
        $id = absint( $id );
        if ( ! $id || isset( $this->seen['elementor'][ $id ] ) ) { return; }
        $post = get_post( $id );
        if ( ! $post || 'elementor_library' !== $post->post_type ) { return; }
        $this->seen['elementor'][ $id ] = true;

        $this->add_post( $id, 'elementor_templates' );
        $raw = get_post_meta( $id, '_elementor_data', true );
        $content = json_decode( $raw, true );
        if ( ! is_array( $content ) ) { $content = array(); }
        $settings = get_post_meta( $id, '_elementor_page_settings', true );
        if ( ! is_array( $settings ) ) { $settings = maybe_unserialize( $settings ); }
        if ( ! is_array( $settings ) ) { $settings = array(); }
        $template = array(
            'version' => '0.4',
            'title' => $post->post_title,
            'type' => (string) get_post_meta( $id, '_elementor_template_type', true ),
            'page_settings' => $settings,
            'content' => $content,
            '_nine_source_id' => $id,
        );
        $name = sanitize_file_name( ( $post->post_name ? $post->post_name : 'template' ) . '-' . $id . '.json' );
        @file_put_contents( $this->work_dir . '/elementor-json/' . $name, wp_json_encode( $template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    private function add_acf_group( $id ) {
        $id = absint( $id );
        if ( ! $id || isset( $this->seen['acf'][ $id ] ) || ! function_exists( 'acf_get_field_group' ) ) { return; }
        $group = acf_get_field_group( $id );
        if ( ! $group ) { return; }
        $this->seen['acf'][ $id ] = true;
        if ( function_exists( 'acf_prepare_field_group_for_export' ) ) {
            $group = acf_prepare_field_group_for_export( $group );
        }
        $fields = function_exists( 'acf_get_fields' ) ? acf_get_fields( $id ) : array();
        if ( $fields ) { $group['fields'] = $fields; }
        $this->payload['acf_field_groups'][] = $group;
        $this->counts['acf_field_groups'] = isset( $this->counts['acf_field_groups'] ) ? $this->counts['acf_field_groups'] + 1 : 1;
    }

    private function add_menu( $menu_id ) {
        $menu_id = absint( $menu_id );
        if ( ! $menu_id || isset( $this->seen['menus'][ $menu_id ] ) ) { return; }
        $menu = wp_get_nav_menu_object( $menu_id );
        if ( ! $menu ) { return; }
        $this->seen['menus'][ $menu_id ] = true;
        $items = array();
        foreach ( wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) ) ?: array() as $item ) {
            $items[] = array(
                'source_id' => $item->ID,
                'title' => $item->title,
                'url' => $item->url,
                'menu_item_parent' => (int) $item->menu_item_parent,
                'object_id' => (int) $item->object_id,
                'object' => $item->object,
                'type' => $item->type,
                'target' => $item->target,
                'classes' => is_array( $item->classes ) ? $item->classes : array(),
                'xfn' => $item->xfn,
                'description' => $item->description,
                'menu_order' => (int) $item->menu_order,
                'meta' => $this->read_meta( 'post', $item->ID ),
            );
        }
        $this->payload['menus'][] = array(
            'source_id' => $menu_id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'description' => $menu->description,
            'items' => $items,
        );
        $this->counts['menus'] = isset( $this->counts['menus'] ) ? $this->counts['menus'] + 1 : 1;
    }

    private function collect_options() {
        global $wpdb;
        $names = $this->config['option_names'];
        if ( in_array( 'site_settings', $this->config['bulk'], true ) ) {
            $names = array_merge( $names, array( 'blogname','blogdescription','show_on_front','page_on_front','page_for_posts','permalink_structure','timezone_string','date_format','time_format','start_of_week' ) );
        }
        if ( in_array( 'theme_settings', $this->config['bulk'], true ) ) {
            $names[] = 'theme_mods_' . get_option( 'stylesheet' );
            $names[] = 'sidebars_widgets';
            $widget_names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'widget\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $names = array_merge( $names, $widget_names );
        }
        if ( $this->config['include_nine_options'] ) {
            $patterns = array( 'nine_%', '9\_%', 'n9\_%' );
            foreach ( $patterns as $pattern ) {
                $sql = $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern );
                $names = array_merge( $names, $wpdb->get_col( $sql ) );
            }
        }
        $names = array_values( array_unique( array_filter( $names ) ) );
        foreach ( $names as $name ) {
            if ( in_array( $name, array( 'cron', 'auth_key', 'secure_auth_key', 'logged_in_key', 'nonce_key' ), true ) ) { continue; }
            $value = get_option( $name, '__NINE_BACKUP_MISSING__' );
            if ( '__NINE_BACKUP_MISSING__' === $value ) { continue; }
            $this->payload['options'][] = array( 'name' => $name, 'value' => $value );
            $this->counts['options'] = isset( $this->counts['options'] ) ? $this->counts['options'] + 1 : 1;
        }
    }

    private function collect_tables() {
        global $wpdb;
        foreach ( $this->config['table_names'] as $table ) {
            $table = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
            if ( ! $table || 0 !== strpos( $table, $wpdb->prefix ) ) { continue; }
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) { continue; }
            $rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $schema = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->payload['tables'][] = array( 'table' => $table, 'schema' => $schema, 'rows' => $rows );
            $this->counts['tables'] = isset( $this->counts['tables'] ) ? $this->counts['tables'] + count( $rows ) : count( $rows );
        }
    }

    private function read_meta( $type, $id ) {
        switch ( $type ) {
            case 'user': $all = get_user_meta( $id ); break;
            case 'term': $all = get_term_meta( $id ); break;
            default: $all = get_post_meta( $id ); break;
        }
        $clean = array();
        foreach ( $all as $key => $values ) {
            if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) { continue; }
            $clean[ $key ] = array_map( 'maybe_unserialize', (array) $values );
        }
        return $clean;
    }

    private function create_disaster_snapshot() {
        if ( ! wp_mkdir_p( $this->work_dir . '/disaster' ) && ! is_dir( $this->work_dir . '/disaster' ) ) {
            return new WP_Error( 'nine_backup_disaster_dir', 'Could not create the disaster snapshot folder.' );
        }
        $db = $this->write_database_sql( $this->work_dir . '/disaster/database.sql' );
        if ( is_wp_error( $db ) ) { return $db; }

        if ( defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR ) ) {
            $own_root = realpath( $this->base_dir );
            $own_root = $own_root ? trailingslashit( str_replace( '\\', '/', $own_root ) ) : '';
            $excluded_roots = array_filter( array(
                $own_root,
                realpath( trailingslashit( WP_CONTENT_DIR ) . 'cache' ),
                realpath( trailingslashit( WP_CONTENT_DIR ) . 'updraft' ),
                realpath( trailingslashit( WP_CONTENT_DIR ) . 'ai1wm-backups' ),
            ) );
            $excluded_roots = array_map( function( $r ) { return trailingslashit( str_replace( '\\', '/', $r ) ); }, $excluded_roots );
            $excluded_roots = apply_filters( 'nine_backup_disaster_excluded_roots', $excluded_roots, $this->work_dir );
            $zip_path = $this->work_dir . '/disaster/wp-content.zip';
            $result = Nine_Backup_Zip::create_from_directory( WP_CONTENT_DIR, $zip_path, function( $path ) use ( $excluded_roots, $zip_path ) {
                $real = realpath( $path );
                $real = $real ? str_replace( '\\', '/', $real ) : str_replace( '\\', '/', $path );
                if ( $real === str_replace( '\\', '/', $zip_path ) ) { return true; }
                foreach ( (array) $excluded_roots as $root ) {
                    $root = trailingslashit( str_replace( '\\', '/', $root ) );
                    if ( $root && 0 === strpos( $real . ( is_dir($real) ? '/' : '' ), $root ) ) { return true; }
                }
                return false;
            } );
            if ( is_wp_error( $result ) ) { return $result; }
        }
        return true;
    }

    private function write_database_sql( $path ) {
        global $wpdb;
        $fh = @fopen( $path, 'wb' );
        if ( ! $fh ) { return new WP_Error( 'nine_backup_db_file', 'Could not create database.sql.' ); }
        fwrite( $fh, "-- 9 Backup Import & Export Manager database snapshot\n-- Created: " . current_time( 'mysql' ) . "\n-- Source: " . home_url( '/' ) . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n" );
        $like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $wpdb->prefix ) . '%' : $wpdb->prefix . '%';
        $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
        foreach ( $tables as $table ) {
            $safe = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
            if ( $safe !== $table ) { continue; }
            $create = $wpdb->get_row( "SHOW CREATE TABLE `{$safe}`", ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( $create && isset( $create[1] ) ) {
                fwrite( $fh, "DROP TABLE IF EXISTS `{$safe}`;\n" . $create[1] . ";\n\n" );
            }
            $offset = 0;
            $limit = 250;
            do {
                $rows = $wpdb->get_results( "SELECT * FROM `{$safe}` LIMIT {$offset}, {$limit}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ( ! $rows ) { break; }
                foreach ( $rows as $row ) {
                    $cols = array(); $vals = array();
                    foreach ( $row as $col=>$value ) {
                        $cols[] = '`' . str_replace( '`', '``', $col ) . '`';
                        $vals[] = null === $value ? 'NULL' : $wpdb->prepare( '%s', $value );
                    }
                    fwrite( $fh, "INSERT INTO `{$safe}` (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', $vals ) . ");\n" );
                }
                $offset += $limit;
            } while ( count( $rows ) === $limit );
            fwrite( $fh, "\n" );
        }
        fwrite( $fh, "SET FOREIGN_KEY_CHECKS=1;\n" );
        fclose( $fh );
        return true;
    }

    private function collect_elementor_media_dependencies( $raw ) {
        $data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        if ( ! is_array( $data ) ) { return; }
        $walk = function( $node ) use ( &$walk ) {
            if ( ! is_array( $node ) ) { return; }
            if ( isset( $node['id'], $node['url'] ) && is_numeric( $node['id'] ) ) {
                $attachment_id = absint( $node['id'] );
                if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) ) { $this->add_post( $attachment_id, 'media' ); }
            }
            foreach ( $node as $value ) { if ( is_array( $value ) ) { $walk( $value ); } }
        };
        $walk( $data );
    }

    private function create_elementor_bundle() {
        $dir = $this->work_dir . '/elementor-json';
        $files = glob( $dir . '/*.json' ) ?: array();
        if ( ! $files ) { return true; }
        $path = $this->work_dir . '/elementor-templates.zip';
        return Nine_Backup_Zip::create_from_files( $files, $path );
    }

    private function write_payload_files() {
        foreach ( $this->payload as $bucket => $records ) {
            $path = $this->work_dir . '/data/' . sanitize_file_name( $bucket ) . '.json';
            $json = wp_json_encode( $records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            if ( false === $json || false === @file_put_contents( $path, $json ) ) { return new WP_Error( 'nine_backup_write_data', 'Could not write backup data file: ' . basename( $path ) ); }
            $rows = $this->rows_for_report( $bucket, $records );
            if ( $rows ) {
                Nine_Backup_XLSX::write( $this->work_dir . '/reports/' . sanitize_file_name( $bucket ) . '.xlsx', $rows, ucfirst( str_replace( '_', ' ', $bucket ) ) );
                $this->write_csv( $this->work_dir . '/reports/' . sanitize_file_name( $bucket ) . '.csv', $rows );
            }
            if ( 'tables' === $bucket ) {
                foreach ( (array) $records as $table_bundle ) {
                    $table_rows = (array) ( $table_bundle['rows'] ?? array() );
                    $headers = array();
                    foreach ( $table_rows as $tr ) { foreach ( array_keys( (array) $tr ) as $col ) { $headers[$col] = true; } }
                    $headers = array_keys( $headers );
                    $detail = array( $headers );
                    foreach ( $table_rows as $tr ) { $line=array(); foreach($headers as $col){$line[]=$this->report_value($tr[$col] ?? '');} $detail[]=$line; }
                    $safe_table = sanitize_file_name( $table_bundle['table'] ?? 'table' );
                    Nine_Backup_XLSX::write( $this->work_dir . '/reports/table-' . $safe_table . '.xlsx', $detail, substr( $safe_table, 0, 31 ) );
                    $this->write_csv( $this->work_dir . '/reports/table-' . $safe_table . '.csv', $detail );
                }
            }
            if ( 'plugin_settings' === $bucket ) {
                foreach ( (array) $records as $plugin_bundle ) {
                    $plugin_slug = sanitize_file_name( $plugin_bundle['slug'] ?? 'plugin' );
                    foreach ( (array) ( $plugin_bundle['tables'] ?? array() ) as $table_bundle ) {
                        $table_rows = (array) ( $table_bundle['rows'] ?? array() );
                        $headers = array();
                        foreach ( $table_rows as $tr ) { foreach ( array_keys( (array) $tr ) as $col ) { $headers[$col] = true; } }
                        $headers = array_keys( $headers );
                        if ( ! $headers ) { continue; }
                        $detail = array( $headers );
                        foreach ( $table_rows as $tr ) { $line=array(); foreach($headers as $col){$line[]=$this->report_value($tr[$col] ?? '');} $detail[]=$line; }
                        $safe_table = sanitize_file_name( $table_bundle['table'] ?? 'table' );
                        $base = 'plugin-' . $plugin_slug . '-table-' . $safe_table;
                        Nine_Backup_XLSX::write( $this->work_dir . '/reports/' . $base . '.xlsx', $detail, substr( $safe_table, 0, 31 ) );
                        $this->write_csv( $this->work_dir . '/reports/' . $base . '.csv', $detail );
                    }
                }
            }
        }
        return true;
    }

    private function rows_for_report( $bucket, $records ) {
        $rows = array();
        switch ( $bucket ) {
            case 'posts':
            case 'media':
            case 'elementor_templates':
                $meta_keys = $this->collect_meta_keys( $records );
                $header = array( 'Source ID','Type','Status','Title','Slug','Author ID','Date','Parent ID','Terms','Excerpt/URL' );
                $header = array_merge( $header, $meta_keys );
                $rows[] = $header;
                foreach ( $records as $r ) {
                    $terms = array();
                    foreach ( $r['terms'] as $t ) { $terms[] = $t['taxonomy'] . ':' . $t['slug']; }
                    $row = array( $r['source_id'], $r['post_type'], $r['post_status'], $r['post_title'], $r['post_name'], $r['post_author'], $r['post_date'], $r['post_parent'], implode( ' | ', $terms ), 'attachment' === $r['post_type'] ? ( $r['attachment_url'] ?? '' ) : wp_strip_all_tags( $r['post_excerpt'] ) );
                    foreach ( $meta_keys as $key ) { $row[] = $this->report_value( $r['meta'][ $key ] ?? '' ); }
                    $rows[] = $row;
                }
                break;
            case 'users':
                $meta_keys = $this->collect_meta_keys( $records );
                $rows[] = array_merge( array( 'Source ID','Login','Email','Display Name','Roles','Registered' ), $meta_keys );
                foreach ( $records as $r ) {
                    $row = array( $r['source_id'], $r['user_login'], $r['user_email'], $r['display_name'], implode( ', ', $r['roles'] ), $r['user_registered'] );
                    foreach ( $meta_keys as $key ) { $row[] = $this->report_value( $r['meta'][ $key ] ?? '' ); }
                    $rows[] = $row;
                }
                break;
            case 'terms':
                $meta_keys = $this->collect_meta_keys( $records );
                $rows[] = array_merge( array( 'Source ID','Taxonomy','Name','Slug','Parent Slug','Description' ), $meta_keys );
                foreach ( $records as $r ) {
                    $row = array( $r['source_id'], $r['taxonomy'], $r['name'], $r['slug'], $r['parent_slug'], $r['description'] );
                    foreach ( $meta_keys as $key ) { $row[] = $this->report_value( $r['meta'][ $key ] ?? '' ); }
                    $rows[] = $row;
                }
                break;
            case 'acf_field_groups':
                $rows[] = array( 'Group Title','Group Key','Group Active','Field Key','Field Name','Field Label','Field Type','Parent','Required','Instructions','Choices / Settings' );
                foreach ( $records as $g ) {
                    $this->append_acf_field_rows( $rows, $g['fields'] ?? array(), $g['title'] ?? '', $g['key'] ?? '', ! empty( $g['active'] ) ? 'Yes' : 'No', $g['key'] ?? '' );
                    if ( empty( $g['fields'] ) ) { $rows[] = array( $g['title'] ?? '', $g['key'] ?? '', ! empty($g['active'])?'Yes':'No', '', '', '', '', '', '', '', '' ); }
                }
                break;
            case 'menus':
                $rows[] = array( 'Menu ID','Menu','Menu Slug','Item Source ID','Item Title','URL','Type','Object','Object ID','Parent Item ID','Order','Classes','Description' );
                foreach ( $records as $r ) {
                    if ( empty( $r['items'] ) ) { $rows[] = array( $r['source_id'],$r['name'],$r['slug'],'','','','','','','','','','' ); continue; }
                    foreach ( $r['items'] as $item ) {
                        $rows[] = array( $r['source_id'],$r['name'],$r['slug'],$item['source_id'],$item['title'],$item['url'],$item['type'],$item['object'],$item['object_id'],$item['menu_item_parent'],$item['menu_order'],implode(' ',(array)$item['classes']),$item['description'] );
                    }
                }
                break;
            case 'plugins':
                $rows[] = array( 'Plugin File','Name','Version','Active','Network Active','Nine Plugin','Requires WordPress','Requires PHP','Requires Plugins','Detected Post Types','Detected Taxonomies','Bundle','Bundle Size','Settings','Tables','Description' );
                foreach ( $records as $r ) {
                    $rows[] = array( $r['plugin_file'] ?? '', $r['name'] ?? '', $r['version'] ?? '', ! empty($r['active'])?'Yes':'No', ! empty($r['network_active'])?'Yes':'No', ! empty($r['nine_plugin'])?'Yes':'No', $r['requires_wp'] ?? '', $r['requires_php'] ?? '', implode(', ', (array)($r['requires_plugins'] ?? array())), implode(', ', (array)($r['detected_post_types'] ?? array())), implode(', ', (array)($r['detected_taxonomies'] ?? array())), $r['bundle_file'] ?? '', $r['bundle_size'] ?? 0, $r['settings_count'] ?? 0, $r['tables_count'] ?? 0, $r['description'] ?? '' );
                }
                break;
            case 'plugin_settings':
                $rows[] = array( 'Plugin File','Type','Name / Table','Status','Value / Rows','Sensitive' );
                foreach ( $records as $bundle ) {
                    $plugin = $bundle['plugin_file'] ?? ( $bundle['slug'] ?? '' );
                    foreach ( (array)($bundle['options'] ?? array()) as $opt ) {
                        $rows[] = array( $plugin, 'Option', $opt['name'] ?? '', ! empty($opt['redacted'])?'Redacted':'Exported', ! empty($opt['redacted'])?'[REDACTED]':$this->report_value($opt['value'] ?? ''), ! empty($opt['sensitive'])?'Yes':'No' );
                    }
                    foreach ( (array)($bundle['tables'] ?? array()) as $table ) {
                        $rows[] = array( $plugin, 'Database Table', $table['table'] ?? '', 'Exported', count((array)($table['rows'] ?? array())) . ' rows', '' );
                    }
                }
                break;
            case 'options':
                $rows[] = array( 'Option Name','Value' );
                foreach ( $records as $r ) { $rows[] = array( $r['name'], $this->report_value( $r['value'] ) ); }
                break;
            case 'tables':
                $rows[] = array( 'Table','Rows','Columns' );
                foreach ( $records as $r ) { $rows[] = array( $r['table'], count( $r['rows'] ), count( $r['schema'] ) ); }
                break;
            default:
                if ( is_array( $records ) ) {
                    $rows[] = array( 'Record' );
                    foreach ( $records as $r ) { $rows[] = array( wp_json_encode( $r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); }
                }
        }
        return $rows;
    }

    private function collect_meta_keys( $records ) {
        $keys = array();
        foreach ( (array) $records as $r ) { foreach ( array_keys( (array) ( $r['meta'] ?? array() ) ) as $key ) { $keys[ $key ] = true; } }
        $keys = array_keys( $keys );
        sort( $keys, SORT_NATURAL | SORT_FLAG_CASE );
        return $keys;
    }

    private function report_value( $value ) {
        if ( is_array( $value ) && 1 === count( $value ) ) { $value = reset( $value ); }
        if ( is_bool( $value ) ) { return $value ? 'true' : 'false'; }
        if ( is_scalar( $value ) || null === $value ) { return (string) $value; }
        return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    private function append_acf_field_rows( &$rows, $fields, $group_title, $group_key, $active, $parent ) {
        foreach ( (array) $fields as $field ) {
            $settings = $field;
            unset( $settings['sub_fields'], $settings['ID'], $settings['key'], $settings['name'], $settings['label'], $settings['type'], $settings['parent'], $settings['required'], $settings['instructions'] );
            $rows[] = array(
                $group_title, $group_key, $active,
                $field['key'] ?? '', $field['name'] ?? '', $field['label'] ?? '', $field['type'] ?? '', $parent,
                ! empty( $field['required'] ) ? 'Yes' : 'No', $field['instructions'] ?? '', $this->report_value( $settings ),
            );
            if ( ! empty( $field['sub_fields'] ) ) { $this->append_acf_field_rows( $rows, $field['sub_fields'], $group_title, $group_key, $active, $field['key'] ?? $parent ); }
        }
    }

    private function write_csv( $path, array $rows ) {
        $fh = @fopen( $path, 'w' );
        if ( ! $fh ) { return; }
        // UTF-8 BOM improves Excel compatibility for non-ASCII text.
        fwrite( $fh, "\xEF\xBB\xBF" );
        foreach ( $rows as $row ) { fputcsv( $fh, $row ); }
        fclose( $fh );
    }

    private function write_manifest() {
        global $wpdb;
        $package_type = $this->package_type();
        $integrity = $this->build_integrity_manifest();
        $manifest = array(
            'format' => 'nine-backup-package',
            'format_version' => 2,
            'plugin_version' => NINE_BACKUP_VERSION,
            'backup_name' => $this->config['name'],
            'package_type' => $package_type,
            'created_at' => current_time( 'mysql' ),
            'created_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
            'source_site' => home_url( '/' ),
            'source_host' => wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
            'wordpress_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'source_db_prefix' => $wpdb->prefix,
            'source' => $this->config['source'],
            'counts' => $this->counts,
            'integrity' => $integrity,
            'config' => array(
                'include_dependencies' => (bool) $this->config['include_dependencies'],
                'include_term_children' => (bool) $this->config['include_term_children'],
                'include_term_content' => (bool) $this->config['include_term_content'],
                'include_user_content' => (bool) $this->config['include_user_content'],
                'include_media_files' => (bool) $this->config['include_media_files'],
                'plugin_include_files' => (bool) $this->config['plugin_include_files'],
                'plugin_include_settings' => (bool) $this->config['plugin_include_settings'],
                'plugin_include_tables' => (bool) $this->config['plugin_include_tables'],
                'plugin_include_records' => (bool) $this->config['plugin_include_records'],
                'plugin_include_dependencies' => (bool) $this->config['plugin_include_dependencies'],
                'include_sensitive_settings' => (bool) $this->config['include_sensitive_settings'],
                'disaster_snapshot' => in_array( 'disaster_snapshot', $this->config['bulk'], true ),
            ),
            'notes' => array(
                'Machine restore data is in /data.',
                'Human-readable Excel and CSV reports are in /reports.',
                'Plugin transfer ZIPs are in /plugins and can be extracted/sent independently.',
                'Plugin code and plugin settings are independent: settings-only and code-only packages are supported.',
                'Sensitive plugin options are redacted by default unless explicitly included during export.',
                'Elementor-compatible JSON snapshots are in /elementor-json when Elementor templates are included.',
                'A separate /elementor-templates.zip is included when Elementor templates are selected, for template-only handoff/import.',
                'User password hashes are intentionally not exported; imported users should reset/set passwords.',
                'When Disaster Snapshot is selected, /disaster/database.sql and /disaster/wp-content.zip are included for full emergency recovery; the selective importer does not automatically execute this destructive restore.',
            ),
        );
        $manifest_json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( false === $manifest_json || false === @file_put_contents( $this->work_dir . '/manifest.json', $manifest_json ) ) {
            return new WP_Error( 'nine_backup_manifest_write', 'Could not write manifest.json.' );
        }
        $readme = "9 Backup Import & Export Manager

Backup: {$this->config['name']}
Type: {$package_type}
Created: " . current_time( 'mysql' ) . "
Source: " . home_url( '/' ) . "

Import this ZIP through 9 Backup Import & Export Manager on another WordPress site. Reports can be opened directly in Excel or supplied to an AI assistant for analysis. Plugin ZIPs in /plugins can also be handed off independently.
";
        if ( false === @file_put_contents( $this->work_dir . '/README.txt', $readme ) ) {
            Nine_Backup_Health::log( 'readme', 'Could not write README.txt into package.' );
        }
        return true;
    }

    private function build_integrity_manifest() {
        $entries = array();
        $root = realpath( $this->work_dir );
        if ( ! $root ) { return $entries; }
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $file ) {
            if ( $file->isDir() ) { continue; }
            $path = $file->getRealPath();
            $rel = ltrim( str_replace( '\\', '/', substr( $path, strlen( $root ) ) ), '/' );
            if ( 'manifest.json' === $rel ) { continue; }
            $entries[ $rel ] = array( 'sha256'=>hash_file( 'sha256', $path ), 'size'=>(int) filesize( $path ) );
        }
        ksort( $entries, SORT_NATURAL | SORT_FLAG_CASE );
        return $entries;
    }

    private function package_type() {
        $bulk = (array) $this->config['bulk'];
        if ( in_array( 'disaster_snapshot', $bulk, true ) ) { return 'disaster'; }
        if ( in_array( 'full_portability', $bulk, true ) ) { return 'full-portability'; }
        $has_plugins = ! empty( $this->payload['plugins'] ) || ! empty( $this->payload['plugin_settings'] );
        $non_plugin_tokens = array_filter( (array)$this->config['tokens'], function($token){ return 0 !== strpos((string)$token, 'plugin:'); } );
        $non_plugin_bulk = array_diff( $bulk, array('plugins','nine_plugins') );
        $manual_extra = ! empty($this->config['option_names']) || ! empty($this->config['table_names']) || ! empty($this->config['include_nine_options']);
        if ( $has_plugins && empty($non_plugin_tokens) && empty($non_plugin_bulk) && !$manual_extra ) { return 'plugin-transfer'; }
        if ( $has_plugins ) { return 'mixed'; }
        if ( ! empty( $this->payload['elementor_templates'] ) && count( array_diff( array_keys( $this->payload ), array('elementor_templates','media','users','terms') ) ) === 0 ) { return 'elementor-templates'; }
        if ( in_array( 'full_records', $bulk, true ) ) { return 'complete-records'; }
        return 'selective-records';
    }


    private function zip_directory( $source, $destination ) {
        return Nine_Backup_Zip::create_from_directory( $source, $destination );
    }

    private function copy_to_drive_sync_folder( $archive_path ) {
        if ( empty( $this->config['copy_to_drive_folder'] ) ) { return array( 'status' => 'disabled' ); }
        $settings = get_option( 'nine_backup_settings', array() );
        $root = isset( $settings['drive_sync_path'] ) ? trim( $settings['drive_sync_path'] ) : '';
        $result = array();
        if ( $root ) {
            if ( ! is_dir( $root ) || ! is_writable( $root ) ) { $result['folder'] = array( 'status'=>'error', 'message'=>'Configured Drive sync folder is not writable on this server.' ); }
            else {
                $host = sanitize_file_name( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
                $group_by_type = ! array_key_exists( 'drive_group_by_type', $settings ) || ! empty( $settings['drive_group_by_type'] );
                $sub = trailingslashit( $root ) . $host . '/'; if ( $group_by_type ) { $sub .= sanitize_file_name( $this->package_type() ) . '/'; } $sub .= current_time( 'Y' ) . '/' . current_time( 'm' );
                if ( ! wp_mkdir_p( $sub ) ) { $result['folder'] = array( 'status'=>'error', 'message'=>'Could not create the Drive-ready folder structure.' ); }
                else { $dest = trailingslashit( $sub ) . basename( $archive_path ); $result['folder'] = @copy( $archive_path, $dest ) ? array( 'status'=>'copied', 'path'=>$dest ) : array( 'status'=>'error', 'message'=>'Could not copy the package to the Drive sync folder.' ); }
            }
        }
        if ( ! empty( $settings['google_drive_enabled'] ) && class_exists( 'Nine_Backup_Google_Drive' ) ) {
            $remote = Nine_Backup_Google_Drive::upload( $archive_path );
            $result['google'] = is_wp_error($remote) ? array( 'status'=>'error', 'message'=>$remote->get_error_message() ) : $remote;
        }
        if ( ! $result ) { return array( 'status'=>'not_configured' ); }
        $success = array_filter( $result, function($item){ return in_array($item['status'] ?? '', array('copied','uploaded'), true); } );
        return array( 'status'=>$success ? 'copied' : 'error', 'destinations'=>$result );
    }

    private function apply_retention_policy( $current_path = '' ) {
        $settings = get_option( 'nine_backup_settings', array() );
        $keep = isset( $settings['archive_retention'] ) ? absint( $settings['archive_retention'] ) : 30;
        if ( $keep <= 0 ) { return; }
        $files = glob( trailingslashit( self::archive_dir() ) . '*.zip' ) ?: array();
        if ( count( $files ) <= $keep ) { return; }
        // Tie-breaking by filename can accidentally expire a freshly written backup.
        usort( $files, function( $a, $b ) use ( $current_path ) {
            if ( $a === $current_path ) { return -1; }
            if ( $b === $current_path ) { return 1; }
            return filemtime( $b ) <=> filemtime( $a );
        } );
        foreach ( array_slice( $files, $keep ) as $file ) { if ( $file !== $current_path ) { @unlink( $file ); } }
    }

    private function delete_tree( $dir ) {
        if ( ! is_dir( $dir ) ) { return; }
        $items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $items as $item ) {
            if ( $item->isDir() ) { @rmdir( $item->getPathname() ); } else { @unlink( $item->getPathname() ); }
        }
        @rmdir( $dir );
    }
}
