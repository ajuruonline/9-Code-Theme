<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NCU_Data_Data_Exporter {
    private $media_ids = array();

    public function export_post_record( $post_id, $collect_media = false ) {
        $post = get_post( $post_id );
        if ( ! $post ) { return null; }
        $taxonomies = array();
        foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
            $terms = wp_get_object_terms( $post_id, $taxonomy );
            if ( is_wp_error( $terms ) ) { continue; }
            $taxonomies[ $taxonomy ] = array_map( function( $term ) {
                return array( 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'taxonomy' => $term->taxonomy );
            }, $terms );
        }
        return array(
            'object' => array(
                'kind' => 'post', 'id' => (int) $post->ID, 'post_type' => $post->post_type,
                'title' => $post->post_title, 'slug' => $post->post_name, 'status' => $post->post_status,
                'excerpt' => $post->post_excerpt, 'content' => $post->post_content,
                'featured_image_id' => (int) get_post_thumbnail_id( $post->ID ),
                'featured_image_url' => get_the_post_thumbnail_url( $post->ID, 'full' ) ?: '',
                'parent' => (int) $post->post_parent, 'modified_gmt' => $post->post_modified_gmt,
            ),
            'taxonomies' => $taxonomies,
            'fields' => $this->export_fields_for_object( $post_id, $collect_media ),
            'meta' => $this->export_meta_for_post( $post_id ),
        );
    }


    public function export_meta_for_post( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || ! function_exists( 'get_post_meta' ) ) { return array(); }

        $all = (array) get_post_meta( $post_id );
        $post = get_post( $post_id );
        $post_type = $post ? $post->post_type : '';
        $registered = array();
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            $registered = (array) get_registered_meta_keys( 'post', '' );
            if ( $post_type ) { $registered = array_merge( $registered, (array) get_registered_meta_keys( 'post', $post_type ) ); }
        }
        $acf_meta = $this->acf_managed_meta_keys( $post_id, $all );
        $keys = array_values( array_unique( array_merge( array_keys( $all ), array_keys( $registered ) ) ) );
        $out = array();
        foreach ( $keys as $key ) {
            $key = (string) $key;
            if ( isset( $acf_meta[ $key ] ) || $this->is_protected_meta_key( $key ) ) { continue; }
            $schema = isset( $registered[ $key ] ) && is_array( $registered[ $key ] ) ? $registered[ $key ] : array();
            $raw_values = (array) ( $all[ $key ] ?? array() );
            $values = array_map( 'maybe_unserialize', $raw_values );
            $is_multi = array_key_exists( 'single', $schema ) ? ! (bool) $schema['single'] : count( $values ) > 1;
            $value = $is_multi ? $values : ( array_key_exists( 0, $values ) ? $values[0] : '' );
            $out[] = array(
                'key' => $key,
                'label' => $this->friendly_meta_label( $key ),
                'storage' => $is_multi ? 'multi' : 'single',
                'type' => isset( $schema['type'] ) ? (string) $schema['type'] : '',
                'instructions' => isset( $schema['description'] ) ? wp_strip_all_tags( (string) $schema['description'] ) : '',
                'value' => $this->json_safe_value( $value ),
            );
        }
        usort( $out, function( $a, $b ) { return strcasecmp( $a['label'] ?? $a['key'], $b['label'] ?? $b['key'] ); } );
        return $out;
    }

    private function acf_managed_meta_keys( $post_id, $all_meta ) {
        $managed = array();
        foreach ( (array) $all_meta as $key => $values ) {
            if ( 0 !== strpos( (string) $key, '_' ) ) { continue; }
            $candidate = isset( $values[0] ) ? maybe_unserialize( $values[0] ) : null;
            if ( is_string( $candidate ) && 0 === strpos( $candidate, 'field_' ) ) {
                $managed[ $key ] = true;
                $managed[ substr( $key, 1 ) ] = true;
            }
        }
        if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
            foreach ( (array) acf_get_field_groups( array( 'post_id' => $post_id ) ) as $group ) {
                foreach ( (array) acf_get_fields( $group ) as $field ) {
                    $this->collect_acf_field_meta_names( $field, $managed );
                }
            }
        }
        return $managed;
    }

    private function collect_acf_field_meta_names( $field, &$managed ) {
        if ( ! empty( $field['name'] ) ) {
            $managed[ (string) $field['name'] ] = true;
            $managed[ '_' . (string) $field['name'] ] = true;
        }
        foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sub ) { $this->collect_acf_field_meta_names( $sub, $managed ); }
        foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
            foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $sub ) { $this->collect_acf_field_meta_names( $sub, $managed ); }
        }
    }

    private function is_protected_meta_key( $key ) {
        $exact = array( '_edit_lock', '_edit_last', '_thumbnail_id', '_wp_old_slug', '_wp_trash_meta_status', '_wp_trash_meta_time' );
        $exact = (array) apply_filters( 'ninecode_data_engine_protected_meta_keys', $exact );
        if ( in_array( $key, $exact, true ) ) { return true; }
        $prefixes = array( '_elementor_', '_wp_', '_oembed_', '_menu_item_', '_customize_', '_transient_', '_site_transient_' );
        $prefixes = (array) apply_filters( 'ninecode_data_engine_protected_meta_prefixes', $prefixes );
        foreach ( $prefixes as $prefix ) { if ( '' !== $prefix && 0 === strpos( $key, (string) $prefix ) ) { return true; } }
        return (bool) apply_filters( 'ninecode_data_engine_is_protected_meta_key', false, $key );
    }

    private function friendly_meta_label( $key ) {
        $label = preg_replace( '/[_\-]+/', ' ', ltrim( (string) $key, '_' ) );
        $label = trim( preg_replace( '/\s+/', ' ', (string) $label ) );
        return $label ? ucwords( $label ) : (string) $key;
    }

    public function export_term_record( $term_id, $taxonomy = '', $collect_media = false ) {
        $term = get_term( $term_id, $taxonomy ?: '' );
        if ( ! $term || is_wp_error( $term ) ) { return null; }
        return array(
            'object' => array(
                'kind' => 'term', 'id' => (int) $term->term_id, 'taxonomy' => $term->taxonomy,
                'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description,
                'parent' => (int) $term->parent,
            ),
            'fields' => $this->export_fields_for_object( 'term_' . $term->term_id, $collect_media ),
        );
    }

    public function export_post_collection( $post_type, $search = '', $collect_media = false, $filter_taxonomy = '', $filter_term = '' ) {
        $records = array();
        if ( ! post_type_exists( $post_type ) ) {
            return $this->pack( 'post', array( 'post_type' => $post_type ), $records );
        }
        $query_args = array(
            'post_type' => $post_type,
            'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
            'posts_per_page' => -1, 'fields' => 'ids', 's' => $search, 'orderby' => 'ID', 'order' => 'ASC',
        );
        $filter_taxonomy = sanitize_key( (string) $filter_taxonomy );
        $filter_term = trim( (string) $filter_term );
        if ( $filter_taxonomy && $filter_term && taxonomy_exists( $filter_taxonomy ) && is_object_in_taxonomy( $post_type, $filter_taxonomy ) ) {
            $term_obj = get_term_by( 'slug', sanitize_title( $filter_term ), $filter_taxonomy );
            if ( ! $term_obj ) { $term_obj = get_term_by( 'name', $filter_term, $filter_taxonomy ); }
            if ( $term_obj && ! is_wp_error( $term_obj ) ) {
                $query_args['tax_query'] = array( array( 'taxonomy' => $filter_taxonomy, 'field' => 'term_id', 'terms' => array( (int) $term_obj->term_id ) ) );
            } else {
                return $this->pack( 'post', array( 'post_type' => $post_type, 'search' => $search, 'filter_taxonomy' => $filter_taxonomy, 'filter_term' => $filter_term, 'filter_status' => 'term_not_found' ), array() );
            }
        }
        $ids = get_posts( $query_args );
        foreach ( $ids as $id ) {
            $record = $this->export_post_record( $id, $collect_media );
            if ( $record ) { $records[] = $record; }
        }
        return $this->pack( 'post', array( 'post_type' => $post_type, 'search' => $search, 'filter_taxonomy' => $filter_taxonomy, 'filter_term' => $filter_term ), $records );
    }

    public function export_term_collection( $taxonomy, $search = '', $collect_media = false ) {
        $records = array();
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return $this->pack( 'term', array( 'taxonomy' => $taxonomy ), $records );
        }
        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => $search ) );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $record = $this->export_term_record( $term->term_id, $taxonomy, $collect_media );
                if ( $record ) { $records[] = $record; }
            }
        }
        return $this->pack( 'term', array( 'taxonomy' => $taxonomy, 'search' => $search ), $records );
    }

    private function pack( $kind, $scope, $records ) {
        $package = array(
            'format' => 'ninecode-acf-ai-pack',
            'version' => 2,
            'generated_at' => current_time( 'c' ),
            'site' => array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
            'scope' => array_merge( array( 'kind' => $kind ), $scope ),
            'ai_contract' => array(
                'role' => 'You are editing structured WordPress data for 9Code ACF Data Engine.',
                'goal' => 'Return this complete JSON package after making only the requested data corrections or completions.',
                'editable_paths' => array( 'records[].object.title', 'records[].object.slug', 'records[].object.excerpt', 'records[].object.content', 'records[].object.featured_image_id', 'records[].object.status', 'records[].fields[].value', 'records[].meta[].value', 'records[].taxonomies' ),
                'protected_paths' => array( 'records[].object.id', 'records[].object.post_type', 'records[].object.parent', 'records[].fields[].key', 'records[].meta[].key' ),
                'rules' => array(
                    'Never create or delete WordPress records. Only change editable object data when the user explicitly requests it.',
                    'Never change ACF field keys or plugin meta keys.',
                    'Respect field.type, field.required and field.choices when supplied.',
                    'Keep arrays/objects as valid JSON values rather than converting them to prose.',
                    'Taxonomy values must stay under their existing taxonomy key.',
                    'If uncertain about a value, preserve the current value rather than inventing a structural identifier.',
                    'Return valid JSON only, using this same package structure.',
                ),
                'recommended_prompt' => 'Review the attached 9Code AI Data Package. Make only the data changes I request. Preserve all protected identifiers and return the complete valid JSON package ready for Validate / Preview import.',
            ),
            'records' => array_values( $records ),
        );
        if ( class_exists( 'NCU_Data_Scope_Lock' ) ) {
            $scope_name = 'term' === $kind ? sanitize_key( $scope['taxonomy'] ?? '' ) : sanitize_key( $scope['post_type'] ?? '' );
            $package['scope_guard'] = NCU_Data_Scope_Lock::build( $package['records'], $kind, $scope_name, $package['scope'] );
            $package['ai_contract']['protected_paths'][] = 'scope_guard — do not edit, remove or recreate it.';
        }
        return $package;
    }

    public function export_fields_for_object( $acf_id, $collect_media = false ) {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) || ! function_exists( 'get_field' ) ) {
            return array();
        }
        $groups = acf_get_field_groups( array( 'post_id' => $acf_id ) );
        $out = array();
        $seen = array();
        foreach ( $groups as $group ) {
            foreach ( (array) acf_get_fields( $group ) as $field ) {
                if ( empty( $field['key'] ) || isset( $seen[ $field['key'] ] ) ) { continue; }
                $seen[ $field['key'] ] = true;
                $value = get_field( $field['key'], $acf_id, false );
                if ( $collect_media ) { $this->collect_media_from_value( $field, $value ); }
                $out[] = array(
                    'key' => $field['key'],
                    'name' => $field['name'] ?? '',
                    'label' => $field['label'] ?? '',
                    'type' => $field['type'] ?? '',
                    'required' => ! empty( $field['required'] ),
                    'instructions' => isset( $field['instructions'] ) ? wp_strip_all_tags( $field['instructions'] ) : '',
                    'choices' => in_array( $field['type'] ?? '', array( 'select', 'checkbox', 'radio', 'button_group' ), true ) ? (array) ( $field['choices'] ?? array() ) : null,
                    'value' => $this->json_safe_value( $value ),
                );
            }
        }
        return $out;
    }

    private function json_safe_value( $value ) {
        if ( $value instanceof WP_Post ) {
            return array( '__type' => 'post_ref', 'id' => (int) $value->ID, 'post_type' => $value->post_type, 'slug' => $value->post_name, 'title' => $value->post_title );
        }
        if ( $value instanceof WP_Term ) {
            return array( '__type' => 'term_ref', 'id' => (int) $value->term_id, 'taxonomy' => $value->taxonomy, 'slug' => $value->slug, 'name' => $value->name );
        }
        if ( $value instanceof WP_User ) {
            return array( '__type' => 'user_ref', 'id' => (int) $value->ID, 'login' => $value->user_login, 'display_name' => $value->display_name );
        }
        if ( is_object( $value ) ) { return array_map( array( $this, 'json_safe_value' ), get_object_vars( $value ) ); }
        if ( is_array( $value ) ) {
            $safe = array();
            foreach ( $value as $key => $item ) { $safe[ $key ] = $this->json_safe_value( $item ); }
            return $safe;
        }
        return $value;
    }

    public function export_schema_pack() {
        $groups_out = array();
        if ( function_exists( 'acf_get_field_groups' ) ) {
            foreach ( acf_get_field_groups() as $group ) {
                $groups_out[] = array(
                    'key' => $group['key'] ?? '', 'title' => $group['title'] ?? '', 'location' => $group['location'] ?? array(),
                    'menu_order' => $group['menu_order'] ?? 0, 'position' => $group['position'] ?? 'normal', 'style' => $group['style'] ?? 'default',
                    'fields' => $this->schema_fields( function_exists( 'acf_get_fields' ) ? (array) acf_get_fields( $group ) : array() ),
                );
            }
        }
        $acf_import_groups = array();
        if ( function_exists( 'acf_get_field_groups' ) ) {
            foreach ( acf_get_field_groups() as $group ) {
                $full = $group;
                $full['fields'] = function_exists( 'acf_get_fields' ) ? (array) acf_get_fields( $group ) : array();
                if ( function_exists( 'acf_prepare_field_group_for_export' ) ) { $full = acf_prepare_field_group_for_export( $full ); }
                $acf_import_groups[] = $full;
            }
        }
        return array(
            'format' => 'ninecode-acf-schema-pack', 'version' => 1, 'generated_at' => current_time( 'c' ),
            'acf_version' => defined( 'ACF_VERSION' ) ? ACF_VERSION : null,
            'field_groups' => $groups_out,
            'acf_import_field_groups' => $acf_import_groups,
            'post_types' => $this->post_type_schema(),
            'taxonomies' => $this->taxonomy_schema(),
            'virtual_allocations' => (array) get_option( 'ninecode_acf_allocations', array() ),
            'managed_registry' => (array) get_option( 'ninecode_acf_managed_registry', array() ),
        );
    }

    private function schema_fields( $fields ) {
        $out = array();
        foreach ( $fields as $field ) {
            $row = array(
                'key' => $field['key'] ?? '', 'name' => $field['name'] ?? '', 'label' => $field['label'] ?? '', 'type' => $field['type'] ?? '',
                'instructions' => isset( $field['instructions'] ) ? wp_strip_all_tags( $field['instructions'] ) : '', 'required' => ! empty( $field['required'] ),
                'default_value' => $field['default_value'] ?? null, 'choices' => $field['choices'] ?? null, 'return_format' => $field['return_format'] ?? null,
            );
            if ( ! empty( $field['sub_fields'] ) ) { $row['sub_fields'] = $this->schema_fields( $field['sub_fields'] ); }
            if ( ! empty( $field['layouts'] ) ) {
                $row['layouts'] = array();
                foreach ( $field['layouts'] as $layout ) {
                    $row['layouts'][] = array( 'key' => $layout['key'] ?? '', 'name' => $layout['name'] ?? '', 'label' => $layout['label'] ?? '', 'sub_fields' => $this->schema_fields( $layout['sub_fields'] ?? array() ) );
                }
            }
            $out[] = $row;
        }
        return $out;
    }

    private function post_type_schema() {
        $out = array();
        foreach ( get_post_types( array(), 'objects' ) as $type ) {
            if ( in_array( $type->name, array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request' ), true ) ) { continue; }
            $out[] = array( 'name' => $type->name, 'label' => $type->labels->singular_name, 'plural' => $type->labels->name, 'public' => (bool) $type->public, 'hierarchical' => (bool) $type->hierarchical, 'show_ui' => (bool) $type->show_ui, 'taxonomies' => get_object_taxonomies( $type->name ) );
        }
        return $out;
    }

    private function taxonomy_schema() {
        $out = array();
        foreach ( get_taxonomies( array(), 'objects' ) as $tax ) {
            $out[] = array( 'name' => $tax->name, 'label' => $tax->labels->singular_name, 'plural' => $tax->labels->name, 'public' => (bool) $tax->public, 'hierarchical' => (bool) $tax->hierarchical, 'show_ui' => (bool) $tax->show_ui, 'object_types' => $tax->object_type );
        }
        return $out;
    }

    public function send_csv( $records, $filename, $scope_guard = array() ) {
        $rows = array();
        $headers = array( '__ninecode_scope_guard', 'kind', 'object_id', 'post_type', 'taxonomy', 'title_or_name', 'slug', 'excerpt', 'content', 'featured_image_id', 'featured_image_url', 'status', 'parent' );
        $field_headers = array();
        $meta_headers = array();
        $tax_headers = array();
        foreach ( $records as $record ) {
            foreach ( (array) ( $record['fields'] ?? array() ) as $field ) {
                $header = 'acf:' . ( $field['key'] ?? '' ) . ':' . ( $field['name'] ?? '' );
                $field_headers[ $header ] = true;
            }
            foreach ( (array) ( $record['meta'] ?? array() ) as $meta ) {
                $meta_key = trim( (string) ( $meta['key'] ?? '' ) );
                if ( $meta_key ) { $meta_headers[ 'meta:' . ( 'multi' === ( $meta['storage'] ?? '' ) ? 'multi' : 'single' ) . ':' . $meta_key ] = true; }
            }
            foreach ( array_keys( (array) ( $record['taxonomies'] ?? array() ) ) as $tax ) { $tax_headers[ 'tax:' . $tax ] = true; }
        }
        $headers = array_merge( $headers, array_keys( $tax_headers ), array_keys( $field_headers ), array_keys( $meta_headers ) );
        foreach ( $records as $record ) {
            $object = $record['object'] ?? array();
            $row = array_fill_keys( $headers, '' );
            $row['__ninecode_scope_guard'] = class_exists( 'NCU_Data_Scope_Lock' ) && $scope_guard ? NCU_Data_Scope_Lock::encode( $scope_guard ) : '';
            $row['kind'] = $object['kind'] ?? '';
            $row['object_id'] = $object['id'] ?? '';
            $row['post_type'] = $object['post_type'] ?? '';
            $row['taxonomy'] = $object['taxonomy'] ?? '';
            $row['title_or_name'] = $object['title'] ?? ( $object['name'] ?? '' );
            $row['slug'] = $object['slug'] ?? '';
            $row['excerpt'] = $object['excerpt'] ?? '';
            $row['content'] = $object['content'] ?? '';
            $row['featured_image_id'] = $object['featured_image_id'] ?? '';
            $row['featured_image_url'] = $object['featured_image_url'] ?? '';
            $row['status'] = $object['status'] ?? '';
            $row['parent'] = $object['parent'] ?? '';
            foreach ( (array) ( $record['taxonomies'] ?? array() ) as $tax => $terms ) {
                $row[ 'tax:' . $tax ] = implode( '|', array_map( function( $t ) { return $t['slug'] ?? $t['name'] ?? ''; }, (array) $terms ) );
            }
            foreach ( (array) ( $record['fields'] ?? array() ) as $field ) {
                $header = 'acf:' . ( $field['key'] ?? '' ) . ':' . ( $field['name'] ?? '' );
                $value = $field['value'] ?? null;
                $row[ $header ] = is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            }
            foreach ( (array) ( $record['meta'] ?? array() ) as $meta ) {
                $meta_key = trim( (string) ( $meta['key'] ?? '' ) );
                if ( ! $meta_key ) { continue; }
                $header = 'meta:' . ( 'multi' === ( $meta['storage'] ?? '' ) ? 'multi' : 'single' ) . ':' . $meta_key;
                $value = $meta['value'] ?? null;
                $row[ $header ] = is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            }
            $rows[] = $row;
        }
        nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, $headers );
        foreach ( $rows as $row ) { fputcsv( $out, array_map( function( $h ) use ( $row ) { return $row[ $h ] ?? ''; }, $headers ) ); }
        fclose( $out ); exit;
    }

    public function create_backup_zip( $include_media = true, $include_builtin = false ) {
        if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'zip_missing', 'PHP ZipArchive is required to create ZIP data packs.' ); }
        $tmp = wp_tempnam( 'ninecode-acf-data-pack.zip' );
        if ( ! $tmp ) { return new WP_Error( 'temp_failed', 'Could not create temporary backup file.' ); }
        $zip_path = $tmp . '.zip'; @unlink( $tmp );
        $zip = new ZipArchive();
        if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return new WP_Error( 'zip_open_failed', 'Could not create backup ZIP.' ); }

        $this->media_ids = array();
        $post_packs = array();
        $types = get_post_types( array( 'show_ui' => true ), 'objects' );
        foreach ( $types as $type ) {
            if ( 'attachment' === $type->name ) { continue; }
            if ( ! $include_builtin && ! empty( $type->_builtin ) ) { continue; }
            $post_packs[ $type->name ] = $this->export_post_collection( $type->name, '', $include_media );
        }
        $term_packs = array();
        foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) {
            $term_packs[ $tax->name ] = $this->export_term_collection( $tax->name, '', $include_media );
        }
        $schema = $this->export_schema_pack();
        $manifest = array(
            'format' => 'ninecode-acf-data-backup', 'version' => 1, 'created_at' => current_time( 'c' ),
            'site_url' => home_url( '/' ), 'wordpress_version' => get_bloginfo( 'version' ), 'acf_version' => defined( 'ACF_VERSION' ) ? ACF_VERSION : null,
            'includes_media' => (bool) $include_media,
        );
        $zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
        $zip->addFromString( 'schema/schema.json', wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        $zip->addFromString( 'data/posts.json', wp_json_encode( $post_packs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        $zip->addFromString( 'data/terms.json', wp_json_encode( $term_packs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

        $media_index = array();
        if ( $include_media ) {
            foreach ( array_keys( $this->media_ids ) as $attachment_id ) {
                $file = get_attached_file( $attachment_id );
                if ( ! $file || ! is_readable( $file ) ) { continue; }
                $post = get_post( $attachment_id );
                $relative = 'media/' . $attachment_id . '/' . sanitize_file_name( basename( $file ) );
                if ( $zip->addFile( $file, $relative ) ) {
                    $media_index[] = array(
                        'old_id' => (int) $attachment_id, 'path' => $relative, 'mime' => get_post_mime_type( $attachment_id ),
                        'title' => $post ? $post->post_title : '', 'caption' => $post ? $post->post_excerpt : '', 'description' => $post ? $post->post_content : '',
                        'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
                    );
                }
            }
        }
        $zip->addFromString( 'media/index.json', wp_json_encode( $media_index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        $zip->close();
        return $zip_path;
    }

    private function collect_media_from_value( $field, $value ) {
        $type = $field['type'] ?? '';
        if ( in_array( $type, array( 'image', 'file' ), true ) ) {
            $id = $this->extract_attachment_id( $value );
            if ( $id ) { $this->media_ids[ $id ] = true; }
            return;
        }
        if ( 'gallery' === $type && is_array( $value ) ) {
            foreach ( $value as $item ) { $id = $this->extract_attachment_id( $item ); if ( $id ) { $this->media_ids[ $id ] = true; } }
            return;
        }
        if ( ! is_array( $value ) ) { return; }
        if ( in_array( $type, array( 'repeater', 'group', 'clone' ), true ) ) {
            $subs = (array) ( $field['sub_fields'] ?? array() );
            $rows = 'group' === $type ? array( $value ) : $value;
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) ) { continue; }
                foreach ( $subs as $sub ) {
                    $sub_value = $row[ $sub['name'] ?? '' ] ?? ( $row[ $sub['key'] ?? '' ] ?? null );
                    $this->collect_media_from_value( $sub, $sub_value );
                }
            }
        }
        if ( 'flexible_content' === $type ) {
            foreach ( $value as $row ) {
                if ( ! is_array( $row ) ) { continue; }
                $layout_name = $row['acf_fc_layout'] ?? '';
                foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
                    if ( $layout_name !== ( $layout['name'] ?? '' ) ) { continue; }
                    foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $sub ) {
                        $sub_value = $row[ $sub['name'] ?? '' ] ?? ( $row[ $sub['key'] ?? '' ] ?? null );
                        $this->collect_media_from_value( $sub, $sub_value );
                    }
                }
            }
        }
    }

    private function extract_attachment_id( $value ) {
        if ( is_numeric( $value ) ) { return absint( $value ); }
        if ( is_array( $value ) && ! empty( $value['ID'] ) ) { return absint( $value['ID'] ); }
        if ( is_object( $value ) && ! empty( $value->ID ) ) { return absint( $value->ID ); }
        return 0;
    }
}
