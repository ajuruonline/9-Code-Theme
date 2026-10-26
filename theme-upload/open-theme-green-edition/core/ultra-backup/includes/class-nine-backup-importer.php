<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nine_Backup_Importer {
    private $package_path;
    private $extract_dir;
    private $manifest = array();
    private $data = array();
    private $maps = array( 'user'=>array(), 'term'=>array(), 'post'=>array(), 'media'=>array() );
    private $log = array();

    public function __construct( $package_path ) {
        $this->package_path = $package_path;
    }

    public function preview() {
        $loaded = $this->load_package();
        if ( is_wp_error( $loaded ) ) { return $loaded; }
        $summary = array();
        foreach ( $this->data as $bucket => $records ) {
            $summary[ $bucket ] = is_array( $records ) ? count( $records ) : 0;
        }
        $plugin_compat = array();
        $package_slugs = array();
        foreach ( (array) ( $this->data['plugins'] ?? array() ) as $plugin ) { if ( ! empty($plugin['slug']) ) { $package_slugs[] = sanitize_title($plugin['slug']); } }
        $installed_slugs = array();
        foreach ( Nine_Backup_Plugins::installed_plugins() as $file=>$pdata ) { $installed_slugs[] = Nine_Backup_Plugins::plugin_slug($file); }
        foreach ( (array) ( $this->data['plugins'] ?? array() ) as $plugin ) {
            $requires = (array)($plugin['requires_plugins'] ?? array());
            $missing = array();
            foreach ( $requires as $required ) {
                $required = sanitize_title($required);
                if ( $required && ! in_array($required,$package_slugs,true) && ! in_array($required,$installed_slugs,true) ) { $missing[] = $required; }
            }
            $plugin_compat[] = array_merge( array(
                'plugin_file' => $plugin['plugin_file'] ?? '',
                'name' => $plugin['name'] ?? ( $plugin['plugin_file'] ?? '' ),
                'requires_plugins' => $requires,
                'missing_dependencies' => $missing,
                'has_bundle' => ! empty( $plugin['bundle_file'] ),
            ), Nine_Backup_Plugins::destination_plugin_status( $plugin ) );
        }
        $result = array(
            'manifest' => $this->manifest,
            'counts' => $summary,
            'compatibility' => array(
                'acf_required' => ! empty( $this->data['acf_field_groups'] ),
                'acf_available' => function_exists( 'acf_get_field_groups' ),
                'elementor_required' => ! empty( $this->data['elementor_templates'] ),
                'elementor_available' => did_action( 'elementor/loaded' ) || post_type_exists( 'elementor_library' ),
                'plugins' => $plugin_compat,
            ),
        );
        $this->cleanup();
        return $result;
    }

    public function stage_plugins( $plugin_strategy = 'install_update', $activation_policy = 'preserve' ) {
        $plugin_strategy = in_array( $plugin_strategy, array( 'install_update','skip_existing','package_only' ), true ) ? $plugin_strategy : 'install_update';
        $activation_policy = in_array( $activation_policy, array( 'preserve','inactive','activate' ), true ) ? $activation_policy : 'preserve';
        $loaded = $this->load_package();
        if ( is_wp_error( $loaded ) ) { return $loaded; }
        Nine_Backup_Plugins::import_plugins( $this->extract_dir, (array) ( $this->data['plugins'] ?? array() ), $plugin_strategy, $activation_policy, $this->log );
        $result = array( 'log'=>$this->log, 'manifest'=>$this->manifest, 'plugins_staged'=>true );
        $this->cleanup();
        return $result;
    }

    public function import( $strategy = 'update', $plugin_strategy = 'package_only', $activation_policy = 'preserve', $include_plugin_files = false ) {
        $strategy = in_array( $strategy, array( 'update','skip','duplicate' ), true ) ? $strategy : 'update';
        $plugin_strategy = in_array( $plugin_strategy, array( 'install_update','skip_existing','package_only' ), true ) ? $plugin_strategy : 'package_only';
        $activation_policy = in_array( $activation_policy, array( 'preserve','inactive','activate' ), true ) ? $activation_policy : 'preserve';
        $loaded = $this->load_package();
        if ( is_wp_error( $loaded ) ) { return $loaded; }

        if ( $include_plugin_files ) {
            Nine_Backup_Plugins::import_plugins( $this->extract_dir, (array) ( $this->data['plugins'] ?? array() ), $plugin_strategy, $activation_policy, $this->log );
        }

        // Dependency-first import order. Plugin code should normally be staged in a prior request
        // so newly activated plugins can register CPTs/taxonomies before these records are restored.
        $this->import_terms( $strategy );
        $this->import_users( $strategy );
        $this->import_media( $strategy );
        $this->import_posts_bucket( 'posts', $strategy );
        $this->import_posts_bucket( 'elementor_templates', $strategy );
        $this->repair_post_relationships();
        $this->import_menus( $strategy );
        $this->import_acf_groups( $strategy );
        $this->import_options( $strategy );
        $this->import_tables( $strategy );
        Nine_Backup_Plugins::import_plugin_settings( (array) ( $this->data['plugin_settings'] ?? array() ), $strategy, $this->log, $this->manifest['source_site'] ?? '', $this->manifest['source_db_prefix'] ?? '', $this->maps );

        do_action( 'nine_backup_after_import', $this->data, $this->manifest, $strategy, $this->maps, $this->log );
        $result = array( 'log' => $this->log, 'maps' => $this->maps, 'manifest' => $this->manifest );
        $this->cleanup();
        return $result;
    }

    private function package_error( $code, $message ) {
        Nine_Backup_Health::log( 'import_package', $message, array( 'package'=>basename( (string)$this->package_path ) ) );
        $this->cleanup();
        return new WP_Error( $code, $message );
    }

    private function load_package() {
        if ( ! file_exists( $this->package_path ) ) {
            return $this->package_error( 'nine_backup_package_missing', 'Backup package could not be found.' );
        }
        $upload = wp_upload_dir();
        $root = trailingslashit( $upload['basedir'] ) . 'nine-backup-manager/tmp';
        wp_mkdir_p( $root );
        $this->extract_dir = trailingslashit( $root ) . 'import-' . wp_generate_password( 10, false, false );
        wp_mkdir_p( $this->extract_dir );
        $extracted = Nine_Backup_Zip::extract( $this->package_path, $this->extract_dir );
        if ( is_wp_error( $extracted ) ) { return $this->package_error( $extracted->get_error_code(), $extracted->get_error_message() ); }

        $manifest_file = $this->extract_dir . '/manifest.json';
        if ( ! file_exists( $manifest_file ) ) {
            return $this->package_error( 'nine_backup_manifest', 'This is not a valid 9 Backup package: manifest.json is missing.' );
        }
        $this->manifest = json_decode( file_get_contents( $manifest_file ), true );
        if ( ! is_array( $this->manifest ) || 'nine-backup-package' !== ( $this->manifest['format'] ?? '' ) ) {
            return $this->package_error( 'nine_backup_format', 'The uploaded ZIP is not a recognized 9 Backup package.' );
        }
        $format_version = (int) ( $this->manifest['format_version'] ?? 0 );
        if ( ! in_array( $format_version, array( 1, 2 ), true ) ) {
            return $this->package_error( 'nine_backup_format_version', 'This package format version is not supported by the installed plugin.' );
        }
        if ( 2 === $format_version ) {
            $verified = $this->verify_integrity();
            if ( is_wp_error( $verified ) ) { return $this->package_error( $verified->get_error_code(), $verified->get_error_message() ); }
        }

        $data_dir = $this->extract_dir . '/data';
        if ( is_dir( $data_dir ) ) {
            foreach ( glob( $data_dir . '/*.json' ) ?: array() as $file ) {
                $key = sanitize_key( basename( $file, '.json' ) );
                $raw = @file_get_contents( $file );
                if ( false === $raw ) { return $this->package_error( 'nine_backup_data_read', 'Could not read package data file: ' . basename($file) ); }
                $decoded = json_decode( $raw, true );
                if ( JSON_ERROR_NONE !== json_last_error() ) { return $this->package_error( 'nine_backup_data_json', 'Invalid JSON in package data file: ' . basename($file) ); }
                if ( is_array( $decoded ) ) { $this->data[ $key ] = $decoded; }
            }
        }
        return true;
    }

    private function verify_integrity() {
        $integrity = (array) ( $this->manifest['integrity'] ?? array() );
        foreach ( $integrity as $rel => $expected ) {
            $rel = ltrim( str_replace( '\\', '/', (string) $rel ), '/' );
            if ( ! $rel || false !== strpos( $rel, '../' ) ) { return new WP_Error( 'nine_backup_integrity_path', 'Package integrity list contains an unsafe path.' ); }
            $path = trailingslashit( $this->extract_dir ) . $rel;
            if ( ! is_file( $path ) ) { return new WP_Error( 'nine_backup_integrity_missing', 'Package file is missing: ' . $rel ); }
            if ( isset( $expected['size'] ) && (int) $expected['size'] !== (int) filesize( $path ) ) { return new WP_Error( 'nine_backup_integrity_size', 'Package file size check failed: ' . $rel ); }
            if ( ! empty( $expected['sha256'] ) && ! hash_equals( strtolower( $expected['sha256'] ), strtolower( hash_file( 'sha256', $path ) ) ) ) { return new WP_Error( 'nine_backup_integrity_hash', 'Package integrity check failed: ' . $rel ); }
        }
        return true;
    }

    private function import_terms( $strategy ) {
        $records = $this->data['terms'] ?? array();
        // Parents first when possible.
        usort( $records, function( $a, $b ) { return (int) ! empty( $a['parent_source_id'] ) <=> (int) ! empty( $b['parent_source_id'] ); } );
        foreach ( $records as $r ) {
            $taxonomy = sanitize_key( $r['taxonomy'] ?? '' );
            if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
                $this->log[] = array( 'type'=>'term','status'=>'skipped','message'=>'Taxonomy unavailable: ' . $taxonomy );
                continue;
            }
            $existing = get_term_by( 'slug', $r['slug'], $taxonomy );
            if ( $existing && 'skip' === $strategy ) {
                $this->maps['term'][ (int) $r['source_id'] ] = (int) $existing->term_id;
                $this->log[] = array( 'type'=>'term','status'=>'skipped','message'=>$taxonomy . ':' . $r['slug'] );
                continue;
            }
            $parent = 0;
            if ( ! empty( $r['parent_source_id'] ) && isset( $this->maps['term'][ (int) $r['parent_source_id'] ] ) ) {
                $parent = $this->maps['term'][ (int) $r['parent_source_id'] ];
            } elseif ( ! empty( $r['parent_slug'] ) ) {
                $p = get_term_by( 'slug', $r['parent_slug'], $taxonomy );
                if ( $p ) { $parent = $p->term_id; }
            }
            $args = array( 'slug'=>$r['slug'], 'description'=>$this->remap_context( $r['description'] ?? '' ), 'parent'=>$parent );
            if ( $existing && 'update' === $strategy ) {
                $res = wp_update_term( $existing->term_id, $taxonomy, array_merge( $args, array( 'name'=>$r['name'] ) ) );
            } else {
                $name = $r['name'];
                $slug = $r['slug'];
                if ( $existing && 'duplicate' === $strategy ) {
                    $slug .= '-import-' . wp_generate_password( 5, false, false );
                    $name .= ' (Imported)';
                    $args['slug'] = $slug;
                }
                $res = wp_insert_term( $name, $taxonomy, $args );
            }
            if ( is_wp_error( $res ) ) {
                $this->log[] = array( 'type'=>'term','status'=>'error','message'=>$res->get_error_message() );
                continue;
            }
            $term_id = (int) $res['term_id'];
            $this->maps['term'][ (int) $r['source_id'] ] = $term_id;
            $this->write_meta( 'term', $term_id, $r['meta'] ?? array() );
            $this->log[] = array( 'type'=>'term','status'=>'imported','message'=>$taxonomy . ':' . $r['slug'], 'new_id'=>$term_id );
        }
    }

    private function import_users( $strategy ) {
        foreach ( $this->data['users'] ?? array() as $r ) {
            $existing = get_user_by( 'login', $r['user_login'] );
            if ( ! $existing && ! empty( $r['user_email'] ) ) { $existing = get_user_by( 'email', $r['user_email'] ); }
            if ( $existing && 'skip' === $strategy ) {
                $this->maps['user'][ (int) $r['source_id'] ] = (int) $existing->ID;
                $this->log[] = array( 'type'=>'user','status'=>'skipped','message'=>$r['user_login'] );
                continue;
            }
            $userdata = array(
                'user_login' => $r['user_login'],
                'user_email' => $r['user_email'],
                'user_nicename' => $r['user_nicename'] ?? '',
                'display_name' => $r['display_name'] ?? $r['user_login'],
                'user_url' => $this->remap_context( $r['user_url'] ?? '' ),
            );
            if ( $existing && 'update' === $strategy ) {
                $userdata['ID'] = $existing->ID;
                unset( $userdata['user_login'] );
                $uid = wp_update_user( $userdata );
            } else {
                if ( $existing && 'duplicate' === $strategy ) {
                    $userdata['user_login'] .= '_' . strtolower( wp_generate_password( 5, false, false ) );
                    if ( ! empty( $userdata['user_email'] ) ) { $userdata['user_email'] = ''; }
                }
                $userdata['user_pass'] = wp_generate_password( 24, true, true );
                $uid = wp_insert_user( $userdata );
            }
            if ( is_wp_error( $uid ) ) {
                $this->log[] = array( 'type'=>'user','status'=>'error','message'=>$uid->get_error_message() );
                continue;
            }
            $uid = (int) $uid;
            $this->maps['user'][ (int) $r['source_id'] ] = $uid;
            $this->write_meta( 'user', $uid, $r['meta'] ?? array(), array( 'session_tokens','_application_passwords' ) );
            $u = new WP_User( $uid );
            if ( ! empty( $r['roles'] ) ) {
                $u->set_role( '' );
                foreach ( $r['roles'] as $role ) { if ( get_role( $role ) ) { $u->add_role( $role ); } }
            }
            $this->log[] = array( 'type'=>'user','status'=>'imported','message'=>$r['user_login'], 'new_id'=>$uid, 'password_reset_required'=>true );
        }
    }

    private function import_media( $strategy ) {
        foreach ( $this->data['media'] ?? array() as $r ) {
            $source_id = (int) $r['source_id'];
            $existing = $this->find_existing_post( $r );
            if ( $existing && 'skip' === $strategy ) {
                $this->maps['media'][ $source_id ] = $existing;
                $this->maps['post'][ $source_id ] = $existing;
                continue;
            }
            $new_id = 0;
            if ( ! empty( $r['package_file'] ) ) {
                $file_path = $this->extract_dir . '/' . ltrim( $r['package_file'], '/' );
                if ( file_exists( $file_path ) ) {
                    $filename = basename( $file_path );
                    $bits = wp_upload_bits( $filename, null, file_get_contents( $file_path ) );
                    if ( empty( $bits['error'] ) ) {
                        $attachment = $this->post_args_from_record( $r );
                        $attachment['guid'] = $bits['url'];
                        $attachment['post_mime_type'] = wp_check_filetype( $bits['file'] )['type'] ?? '';
                        unset( $attachment['ID'] );
                        $new_id = wp_insert_attachment( $attachment, $bits['file'] );
                        if ( ! is_wp_error( $new_id ) ) {
                            require_once ABSPATH . 'wp-admin/includes/image.php';
                            $meta = wp_generate_attachment_metadata( $new_id, $bits['file'] );
                            if ( $meta ) { wp_update_attachment_metadata( $new_id, $meta ); }
                        }
                    }
                }
            }
            if ( ! $new_id || is_wp_error( $new_id ) ) {
                // Metadata-only attachment restore fallback.
                $new_id = $this->upsert_post_record( $r, $strategy );
            }
            if ( is_wp_error( $new_id ) || ! $new_id ) {
                $this->log[] = array( 'type'=>'media','status'=>'error','message'=> is_wp_error($new_id) ? $new_id->get_error_message() : 'Could not import media.' );
                continue;
            }
            $new_id = (int) $new_id;
            $this->maps['media'][ $source_id ] = $new_id;
            $this->maps['post'][ $source_id ] = $new_id;
            $this->write_meta( 'post', $new_id, $r['meta'] ?? array(), array( '_wp_attached_file','_wp_attachment_metadata' ) );
            update_post_meta( $new_id, '_nine_backup_source_id', $source_id );
            update_post_meta( $new_id, '_nine_backup_source_site', esc_url_raw( $this->manifest['source_site'] ?? '' ) );
            $this->log[] = array( 'type'=>'media','status'=>'imported','message'=>$r['post_title'] ?? basename( $r['package_file'] ?? '' ), 'new_id'=>$new_id );
        }
    }

    private function import_posts_bucket( $bucket, $strategy ) {
        foreach ( $this->data[ $bucket ] ?? array() as $r ) {
            $source_id = (int) $r['source_id'];
            $required_type = sanitize_key( $r['post_type'] ?? 'post' );
            if ( ! post_type_exists( $required_type ) ) {
                $this->log[] = array( 'type'=>$bucket, 'status'=>'skipped', 'message'=>'Post type is not registered on this site: ' . $required_type . '. Activate the plugin/theme that provides it, then import again.' );
                continue;
            }
            if ( isset( $this->maps['post'][ $source_id ] ) ) { continue; }
            $new_id = $this->upsert_post_record( $r, $strategy );
            if ( is_wp_error( $new_id ) || ! $new_id ) {
                $this->log[] = array( 'type'=>$bucket,'status'=>'error','message'=> is_wp_error($new_id) ? $new_id->get_error_message() : 'Could not import post.' );
                continue;
            }
            $new_id = (int) $new_id;
            $this->maps['post'][ $source_id ] = $new_id;
            $this->write_meta( 'post', $new_id, $r['meta'] ?? array(), array( '_edit_lock','_edit_last' ) );
            update_post_meta( $new_id, '_nine_backup_source_id', $source_id );
            update_post_meta( $new_id, '_nine_backup_source_site', esc_url_raw( $this->manifest['source_site'] ?? '' ) );
            $this->assign_terms( $new_id, $r['terms'] ?? array() );
            $this->log[] = array( 'type'=>$bucket,'status'=>'imported','message'=>$r['post_title'] ?? '', 'new_id'=>$new_id );
        }
    }

    private function upsert_post_record( $r, $strategy ) {
        $existing = $this->find_existing_post( $r );
        if ( $existing && 'skip' === $strategy ) { return $existing; }
        $args = $this->post_args_from_record( $r );
        if ( ! empty( $r['post_author'] ) && isset( $this->maps['user'][ (int) $r['post_author'] ] ) ) {
            $args['post_author'] = $this->maps['user'][ (int) $r['post_author'] ];
        } else {
            $args['post_author'] = get_current_user_id();
        }
        $args['post_parent'] = 0; // Repaired after all posts are mapped.
        if ( $existing && 'update' === $strategy ) {
            $args['ID'] = $existing;
            return wp_update_post( wp_slash( $args ), true );
        }
        if ( $existing && 'duplicate' === $strategy ) {
            $args['post_name'] = sanitize_title( $r['post_name'] . '-import-' . wp_generate_password( 5, false, false ) );
            $args['post_title'] .= ' (Imported)';
        }
        return wp_insert_post( wp_slash( $args ), true );
    }

    private function post_args_from_record( $r ) {
        $allowed = array( 'post_type','post_status','post_date','post_date_gmt','post_title','post_name','post_content','post_excerpt','menu_order','comment_status','ping_status','post_password' );
        $args = array();
        foreach ( $allowed as $key ) {
            if ( array_key_exists( $key, $r ) ) {
                $args[ $key ] = in_array( $key, array( 'post_content','post_excerpt' ), true ) ? $this->remap_context( $r[ $key ] ) : $r[ $key ];
            }
        }
        if ( empty( $args['post_type'] ) ) { $args['post_type'] = 'post'; }
        return $args;
    }

    private function find_existing_post( $r ) {
        $source_id = (int) ( $r['source_id'] ?? 0 );
        $source_site = esc_url_raw( $this->manifest['source_site'] ?? '' );
        if ( $source_id && $source_site ) {
            $found = get_posts( array(
                'post_type' => $r['post_type'] ?? 'any', 'post_status'=>'any', 'posts_per_page'=>1, 'fields'=>'ids',
                'meta_query' => array(
                    'relation'=>'AND',
                    array( 'key'=>'_nine_backup_source_id', 'value'=>$source_id ),
                    array( 'key'=>'_nine_backup_source_site', 'value'=>$source_site ),
                ),
            ) );
            if ( $found ) { return (int) $found[0]; }
        }
        if ( ! empty( $r['post_name'] ) && ! empty( $r['post_type'] ) ) {
            $found = get_page_by_path( $r['post_name'], OBJECT, $r['post_type'] );
            if ( $found ) { return (int) $found->ID; }
        }
        return 0;
    }

    private function repair_post_relationships() {
        foreach ( array( 'posts','elementor_templates','media' ) as $bucket ) {
            foreach ( $this->data[ $bucket ] ?? array() as $r ) {
                $source_id = (int) $r['source_id'];
                if ( ! isset( $this->maps['post'][ $source_id ] ) ) { continue; }
                $new_id = $this->maps['post'][ $source_id ];
                $updates = array( 'ID'=>$new_id );
                $changed = false;
                if ( ! empty( $r['post_parent'] ) && isset( $this->maps['post'][ (int) $r['post_parent'] ] ) ) {
                    $updates['post_parent'] = $this->maps['post'][ (int) $r['post_parent'] ];
                    $changed = true;
                }
                if ( ! empty( $r['post_author'] ) && isset( $this->maps['user'][ (int) $r['post_author'] ] ) ) {
                    $updates['post_author'] = $this->maps['user'][ (int) $r['post_author'] ];
                    $changed = true;
                }
                if ( $changed ) { wp_update_post( $updates ); }

                // Repair common attachment references in metadata.
                $thumb = get_post_meta( $new_id, '_thumbnail_id', true );
                if ( $thumb && isset( $this->maps['media'][ (int) $thumb ] ) ) {
                    update_post_meta( $new_id, '_thumbnail_id', $this->maps['media'][ (int) $thumb ] );
                }
                $this->repair_elementor_media_meta( $new_id );
            }
        }
    }

    private function repair_elementor_media_meta( $post_id ) {
        $raw = get_post_meta( $post_id, '_elementor_data', true );
        if ( ! $raw ) { return; }
        $data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        if ( ! is_array( $data ) ) { return; }
        $changed = false;
        $walk = function( &$node ) use ( &$walk, &$changed ) {
            if ( ! is_array( $node ) ) { return; }
            if ( isset( $node['id'] ) && is_numeric( $node['id'] ) && isset( $this->maps['media'][ (int) $node['id'] ] ) ) {
                $node['id'] = $this->maps['media'][ (int) $node['id'] ];
                if ( isset( $node['url'] ) ) { $node['url'] = wp_get_attachment_url( $node['id'] ); }
                $changed = true;
            }
            foreach ( $node as &$value ) { if ( is_array( $value ) ) { $walk( $value ); } }
        };
        $walk( $data );
        if ( $changed ) { update_post_meta( $post_id, '_elementor_data', wp_json_encode( $data ) ); }
    }

    private function assign_terms( $post_id, $terms ) {
        $grouped = array();
        foreach ( $terms as $t ) {
            $taxonomy = sanitize_key( $t['taxonomy'] ?? '' );
            if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) { continue; }
            $term_id = 0;
            if ( ! empty( $t['source_term_id'] ) && isset( $this->maps['term'][ (int) $t['source_term_id'] ] ) ) {
                $term_id = $this->maps['term'][ (int) $t['source_term_id'] ];
            } elseif ( ! empty( $t['slug'] ) ) {
                $existing = get_term_by( 'slug', $t['slug'], $taxonomy );
                if ( $existing ) { $term_id = $existing->term_id; }
            }
            if ( $term_id ) { $grouped[ $taxonomy ][] = (int) $term_id; }
        }
        foreach ( $grouped as $taxonomy => $ids ) { wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), $taxonomy, false ); }
    }

    private function import_menus( $strategy ) {
        foreach ( $this->data['menus'] ?? array() as $r ) {
            $existing = wp_get_nav_menu_object( $r['slug'] );
            if ( $existing && 'skip' === $strategy ) {
                $this->log[] = array( 'type'=>'menu','status'=>'skipped','message'=>$r['name'] );
                continue;
            }
            if ( $existing && 'update' === $strategy ) {
                $menu_id = (int) $existing->term_id;
            } else {
                $name = $r['name'] . ( $existing && 'duplicate' === $strategy ? ' (Imported)' : '' );
                $menu_id = wp_create_nav_menu( $name );
                if ( is_wp_error( $menu_id ) ) { $this->log[] = array( 'type'=>'menu','status'=>'error','message'=>$menu_id->get_error_message() ); continue; }
            }
            $item_map = array();
            $items = $r['items'] ?? array();
            usort( $items, function( $a, $b ) { return (int) $a['menu_order'] <=> (int) $b['menu_order']; } );
            foreach ( $items as $item ) {
                $parent = ! empty( $item['menu_item_parent'] ) && isset( $item_map[ (int) $item['menu_item_parent'] ] ) ? $item_map[ (int) $item['menu_item_parent'] ] : 0;
                $object_id = (int) ( $item['object_id'] ?? 0 );
                if ( $object_id && isset( $this->maps['post'][ $object_id ] ) ) { $object_id = $this->maps['post'][ $object_id ]; }
                elseif ( $object_id && isset( $this->maps['term'][ $object_id ] ) ) { $object_id = $this->maps['term'][ $object_id ]; }
                $args = array(
                    'menu-item-title' => $item['title'] ?? '',
                    'menu-item-url' => $this->remap_context( $item['url'] ?? '' ),
                    'menu-item-parent-id' => $parent,
                    'menu-item-object-id' => $object_id,
                    'menu-item-object' => $item['object'] ?? 'custom',
                    'menu-item-type' => $item['type'] ?? 'custom',
                    'menu-item-target' => $item['target'] ?? '',
                    'menu-item-classes' => implode( ' ', (array) ( $item['classes'] ?? array() ) ),
                    'menu-item-xfn' => $item['xfn'] ?? '',
                    'menu-item-description' => $item['description'] ?? '',
                    'menu-item-status' => 'publish',
                    'menu-item-position' => (int) ( $item['menu_order'] ?? 0 ),
                );
                $new_item = wp_update_nav_menu_item( $menu_id, 0, $args );
                if ( ! is_wp_error( $new_item ) ) { $item_map[ (int) $item['source_id'] ] = (int) $new_item; }
            }
            $this->log[] = array( 'type'=>'menu','status'=>'imported','message'=>$r['name'], 'new_id'=>$menu_id );
        }
    }

    private function import_acf_groups( $strategy ) {
        $groups = $this->data['acf_field_groups'] ?? array();
        if ( ! $groups ) { return; }
        if ( ! function_exists( 'acf_import_field_group' ) ) {
            $this->log[] = array( 'type'=>'acf','status'=>'skipped','message'=>'ACF is not active; field groups were not imported.' );
            return;
        }
        foreach ( $groups as $group ) {
            $fields = $group['fields'] ?? array();
            unset( $group['fields'], $group['ID'] );
            if ( 'duplicate' === $strategy && ! empty( $group['key'] ) ) {
                $group['key'] = 'group_' . md5( $group['key'] . microtime( true ) . wp_rand() );
                $group['title'] = ( $group['title'] ?? 'Field Group' ) . ' (Imported)';
            }
            if ( 'skip' === $strategy && ! empty( $group['key'] ) && function_exists( 'acf_get_field_group' ) && acf_get_field_group( $group['key'] ) ) {
                $this->log[] = array( 'type'=>'acf','status'=>'skipped','message'=>$group['title'] ?? $group['key'] );
                continue;
            }
            $group = $this->remap_context( $group );
            if ( function_exists( 'acf_prepare_field_group_for_import' ) ) { $group = acf_prepare_field_group_for_import( $group ); }
            $imported = acf_import_field_group( $group );
            if ( ! $imported || empty( $imported['key'] ) ) {
                $this->log[] = array( 'type'=>'acf','status'=>'error','message'=>'Could not import ACF field group.' );
                continue;
            }
            if ( $fields && function_exists( 'acf_update_field' ) ) {
                $this->import_acf_fields_recursive( $fields, $imported['key'] );
            }
            $this->log[] = array( 'type'=>'acf','status'=>'imported','message'=>$imported['title'] ?? $imported['key'] );
        }
    }

    private function import_acf_fields_recursive( $fields, $parent ) {
        foreach ( (array) $fields as $field ) {
            $sub = $field['sub_fields'] ?? array();
            unset( $field['ID'], $field['sub_fields'] );
            $field['parent'] = $parent;
            $saved = acf_update_field( $field );
            $child_parent = is_array( $saved ) && ! empty( $saved['key'] ) ? $saved['key'] : ( $field['key'] ?? $parent );
            if ( $sub ) { $this->import_acf_fields_recursive( $sub, $child_parent ); }
        }
    }

    private function import_options( $strategy ) {
        foreach ( $this->data['options'] ?? array() as $r ) {
            $name = sanitize_text_field( $r['name'] ?? '' );
            if ( ! $name || 'cron' === $name ) { continue; }
            if ( 'skip' === $strategy && false !== get_option( $name, false ) ) { continue; }
            update_option( $name, $this->remap_context( $r['value'] ), false );
            $this->log[] = array( 'type'=>'option','status'=>'imported','message'=>$name );
        }
    }

    private function import_tables( $strategy ) {
        global $wpdb;
        foreach ( $this->data['tables'] ?? array() as $bundle ) {
            $source_table = preg_replace( '/[^A-Za-z0-9_]/', '', $bundle['table'] ?? '' );
            $table = $this->map_table_name( $source_table );
            if ( ! $table || 0 !== strpos( $table, $wpdb->prefix ) ) { continue; }
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) {
                $this->log[] = array( 'type'=>'table','status'=>'skipped','message'=>$table . ' does not exist on destination site.' );
                continue;
            }
            $count = 0;
            foreach ( $bundle['rows'] ?? array() as $row ) {
                if ( ! is_array( $row ) ) { continue; }
                if ( 'skip' === $strategy ) { continue; }
                $row = $this->remap_context( $row );
                $row = apply_filters( 'nine_backup_remap_table_row', $row, $source_table, $table, $this->maps, $this->manifest );
                $ok = 'duplicate' === $strategy ? $wpdb->insert( $table, $row ) : $wpdb->replace( $table, $row );
                if ( false !== $ok ) { $count++; }
            }
            $this->log[] = array( 'type'=>'table','status'=>'imported','message'=>$table, 'rows'=>$count );
        }
    }

    private function map_table_name( $source_table ) {
        global $wpdb;
        $source_prefix = (string) ( $this->manifest['source_db_prefix'] ?? '' );
        if ( $source_prefix && 0 === strpos( $source_table, $source_prefix ) ) {
            return $wpdb->prefix . substr( $source_table, strlen( $source_prefix ) );
        }
        return $source_table;
    }

    private function write_meta( $type, $id, $meta, $exclude = array() ) {
        foreach ( (array) $meta as $key => $values ) {
            if ( in_array( $key, $exclude, true ) ) { continue; }
            if ( 'user' === $type ) {
                global $wpdb;
                $source_prefix = (string) ( $this->manifest['source_db_prefix'] ?? '' );
                if ( $source_prefix && 0 === strpos( $key, $source_prefix ) ) { $key = $wpdb->prefix . substr( $key, strlen( $source_prefix ) ); }
            }
            switch ( $type ) {
                case 'user': delete_user_meta( $id, $key ); break;
                case 'term': delete_term_meta( $id, $key ); break;
                default: delete_post_meta( $id, $key ); break;
            }
            foreach ( (array) $values as $value ) {
                $value = $this->remap_context( $value );
                switch ( $type ) {
                    case 'user': add_user_meta( $id, $key, $value ); break;
                    case 'term': add_term_meta( $id, $key, $value ); break;
                    default: add_post_meta( $id, $key, $value ); break;
                }
            }
        }
    }

    private function remap_context( $value ) {
        $source = untrailingslashit( (string) ( $this->manifest['source_site'] ?? '' ) );
        $destination = untrailingslashit( (string) home_url( '/' ) );
        if ( ! $source || ! $destination || $source === $destination ) { return $value; }
        if ( is_array( $value ) ) {
            foreach ( $value as $k => $v ) { $value[ $k ] = $this->remap_context( $v ); }
            return $value;
        }
        if ( is_object( $value ) ) {
            foreach ( get_object_vars( $value ) as $k => $v ) { $value->$k = $this->remap_context( $v ); }
            return $value;
        }
        if ( is_string( $value ) ) {
            return str_replace( array( $source . '/', $source ), array( $destination . '/', $destination ), $value );
        }
        return $value;
    }

    private function cleanup() {
        if ( ! $this->extract_dir || ! is_dir( $this->extract_dir ) ) { return; }
        $items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->extract_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $items as $item ) {
            if ( $item->isDir() ) { @rmdir( $item->getPathname() ); } else { @unlink( $item->getPathname() ); }
        }
        @rmdir( $this->extract_dir );
    }
}
