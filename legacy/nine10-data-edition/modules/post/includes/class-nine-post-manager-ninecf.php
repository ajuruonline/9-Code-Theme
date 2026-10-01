<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 9CF — Nine Content Fields portable contract.
 *
 * 9CF does not create a second content database. It inventories the editable
 * data already owned by WordPress, Gutenberg blocks, ACF, taxonomies, public
 * meta and registered plugin providers, numbers the fields for humans/AI, and
 * writes imported values back to the original source.
 */
final class Nine_Post_Manager_9CF {
    private static $instance = null;

    const SCHEMA = '9cf-post';
    const SCHEMA_VERSION = 2;
    const NONCE_ACTION = 'npm9_action';
    const META_MIRROR_ENABLED = '_npm9_9cf_mirror_enabled';
    const META_MIRROR_INDEX = '_npm9_9cf_mirror_index';
    const META_IMPORT_HISTORY = '_npm9_9cf_import_history';
    const MAX_FIELDS = 1500;
    const MAX_BLOCK_BYTES = 1500000;
    const MAX_IMPORT_BYTES = 4000000;
    const MAX_HISTORY = 10;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $ajax = [
            '9cf_export'       => 'ajax_export',
            '9cf_preview'      => 'ajax_preview',
            '9cf_apply'        => 'ajax_apply',
            '9cf_mirror'       => 'ajax_mirror',
            '9cf_mirror_sync'  => 'ajax_mirror_sync',
            '9cf_acf_bridge'   => 'ajax_acf_bridge',
        ];
        foreach ( $ajax as $action => $method ) {
            add_action( 'wp_ajax_npm9_' . $action, [ $this, $method ] );
        }

        add_action( 'npm9_after_post_manager_save', [ $this, 'sync_mirror_after_manager_save' ], 10, 2 );
        add_action( 'save_post', [ $this, 'sync_mirror_on_save' ], 99, 3 );
        add_action( 'acf/save_post', [ $this, 'sync_mirror_after_acf' ], 50 );
    }

    public function ui_for_post( $post_id ) {
        $contract = $this->build_contract( $post_id, 'current' );
        if ( is_wp_error( $contract ) ) {
            return [ 'fields' => [], 'blockFields' => [], 'pluginFields' => [], 'counts' => [], 'warnings' => [ $contract->get_error_message() ], 'mirrorEnabled' => false ];
        }
        $block_fields = [];
        $plugin_fields = [];
        foreach ( $contract['fields'] as $field ) {
            if ( empty( $field['editable'] ) ) {
                continue;
            }
            if ( 'block' === $field['provider'] ) {
                $block_fields[] = $field;
            } elseif ( in_array( $field['provider'], [ 'plugin', 'meta' ], true ) ) {
                $plugin_fields[] = $field;
            }
        }
        return [
            'fields'         => $contract['fields'],
            'blockFields'    => $block_fields,
            'pluginFields'   => $plugin_fields,
            'counts'         => $contract['summary']['counts'],
            'warnings'       => $contract['summary']['warnings'],
            'fingerprint'    => $contract['structure_fingerprint'],
            'mirrorEnabled'  => (bool) get_post_meta( $post_id, self::META_MIRROR_ENABLED, true ),
            'acfActive'      => function_exists( 'acf_get_field_groups' ),
            'importHistory'  => $this->import_history( $post_id ),
            'schemaVersion'  => self::SCHEMA_VERSION,
        ];
    }

    public function contract_for_post( $post_id, $mode = 'current' ) {
        return $this->build_contract( $post_id, $mode );
    }

    public function value_for_id( $post_id, $id ) {
        $contract = $this->build_contract( $post_id, 'current' );
        if ( is_wp_error( $contract ) ) {
            return null;
        }
        foreach ( $contract['fields'] as $field ) {
            if ( isset( $field['id'] ) && $id === $field['id'] ) {
                return $field['value'];
            }
        }
        return null;
    }

    public function payload_touches_blocks( $post_id, $values ) {
        if ( ! is_array( $values ) || ! $values ) {
            return false;
        }
        $contract = $this->build_contract( $post_id, 'current' );
        $current = [];
        if ( ! is_wp_error( $contract ) ) {
            foreach ( $contract['fields'] as $field ) {
                $current[ $field['id'] ] = $field['value'];
            }
        }
        foreach ( $values as $id => $value ) {
            $id = (string) $id;
            if ( 0 !== strpos( $id, 'block:' ) ) {
                continue;
            }
            if ( ! array_key_exists( $id, $current ) || wp_json_encode( $current[ $id ] ) !== wp_json_encode( $value ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Apply the subset of 9CF fields rendered inside the normal 9PM editor.
     * Core/ACF/meta/taxonomy fields are handled by Nine_Post_Manager itself;
     * this method handles block and plugin-provider fields only.
     */
    public function apply_editor_values( $post_id, $values ) {
        if ( ! is_array( $values ) || empty( $values ) ) {
            return true;
        }
        $contract = $this->build_contract( $post_id, 'current' );
        if ( is_wp_error( $contract ) ) {
            return $contract;
        }
        $map = [];
        foreach ( $contract['fields'] as $field ) {
            $map[ $field['id'] ] = $field;
        }

        $block_changes = [];
        foreach ( $values as $id => $value ) {
            $id = (string) $id;
            if ( empty( $map[ $id ] ) || empty( $map[ $id ]['editable'] ) ) {
                continue;
            }
            $field = $map[ $id ];
            if ( 'block' === $field['provider'] ) {
                if ( wp_json_encode( $field['value'] ) !== wp_json_encode( $value ) ) {
                    $block_changes[] = [ 'field' => $field, 'value' => $value ];
                }
            } elseif ( 'meta' === $field['provider'] && ! empty( $field['source']['meta_key'] ) ) {
                if ( wp_json_encode( $field['value'] ) === wp_json_encode( $value ) ) {
                    continue;
                }
                $meta_key = sanitize_key( $field['source']['meta_key'] );
                if ( $meta_key ) {
                    if ( ! $this->can_edit_meta( $post_id, $meta_key ) ) { return new WP_Error( '9cf_meta_permission', 'You no longer have permission to edit ' . $meta_key . '.' ); }
                    $valid = $this->validate_field_value( $post_id, $field, $value );
                    if ( is_wp_error( $valid ) ) { return $valid; }
                    $value = apply_filters( 'npm9_9cf_sanitize_field_value', $value, $post_id, $field, [ 'source' => 'editor' ] );
                    $value = $this->sanitize_value( $value, $field['type'] );
                    if ( ! empty( $field['source']['registered'] ) && function_exists( 'sanitize_meta' ) ) {
                        $value = sanitize_meta( $meta_key, $value, 'post', get_post_type( $post_id ) );
                    }
                    update_post_meta( $post_id, $meta_key, $value );
                }
            } elseif ( 'plugin' === $field['provider'] ) {
                if ( wp_json_encode( $field['value'] ) === wp_json_encode( $value ) ) {
                    continue;
                }
                $valid = $this->validate_field_value( $post_id, $field, $value );
                if ( is_wp_error( $valid ) ) { return $valid; }
                $value = apply_filters( 'npm9_9cf_sanitize_field_value', $value, $post_id, $field, [ 'source' => 'editor' ] );
                $result = apply_filters( 'npm9_9cf_apply_field', null, $post_id, $field, $value );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                if ( null === $result && ! empty( $field['source']['meta_key'] ) ) {
                    update_post_meta( $post_id, sanitize_key( $field['source']['meta_key'] ), $this->sanitize_value( $value, $field['type'] ) );
                }
            }
        }

        if ( $block_changes ) {
            $result = $this->apply_block_changes( $post_id, $block_changes );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }
        return true;
    }

    private function build_contract( $post_id, $mode = 'current' ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( '9cf_missing_post', 'Post not found.' );
        }
        $mode = 'blank' === $mode ? 'blank' : 'current';
        $fields = [];
        $warnings = [];

        $this->add_field( $fields, [
            'id' => 'wp:title', 'section' => 'Post', 'provider' => 'wordpress', 'label' => 'Post title', 'type' => 'text',
            'value' => $post->post_title, 'editable' => true, 'ai_fill' => true,
            'source' => [ 'core_key' => 'title' ], 'hint' => 'Main WordPress title.'
        ] );
        $this->add_field( $fields, [
            'id' => 'wp:excerpt', 'section' => 'Post', 'provider' => 'wordpress', 'label' => 'Excerpt / summary', 'type' => 'textarea',
            'value' => $post->post_excerpt, 'editable' => true, 'ai_fill' => true,
            'source' => [ 'core_key' => 'excerpt' ], 'hint' => 'Short summary used by themes, archives and cards.'
        ] );
        $this->add_field( $fields, [
            'id' => 'wp:slug', 'section' => 'Post', 'provider' => 'wordpress', 'label' => 'URL slug', 'type' => 'slug',
            'value' => $post->post_name, 'editable' => true, 'ai_fill' => false,
            'source' => [ 'core_key' => 'slug' ], 'hint' => 'Leave unchanged unless you intentionally want a different URL.'
        ] );
        $this->add_field( $fields, [
            'id' => 'wp:status', 'section' => 'Post', 'provider' => 'wordpress', 'label' => 'Publication status', 'type' => 'status',
            'value' => $post->post_status, 'editable' => true, 'ai_fill' => false,
            'source' => [ 'core_key' => 'status' ], 'hint' => 'AI should normally leave this unchanged.'
        ] );
        $this->add_field( $fields, [
            'id' => 'wp:author', 'section' => 'Post', 'provider' => 'wordpress', 'label' => 'Author user ID', 'type' => 'number',
            'value' => (int) $post->post_author, 'editable' => true, 'ai_fill' => false,
            'source' => [ 'core_key' => 'author_id' ], 'hint' => 'Keep the existing author unless deliberately reassigned.'
        ] );
        $featured = get_post_thumbnail_id( $post_id );
        $this->add_field( $fields, [
            'id' => 'wp:featured_image', 'section' => 'Media', 'provider' => 'wordpress', 'label' => 'Featured image', 'type' => 'media',
            'value' => $this->media_reference( $featured ), 'editable' => true, 'ai_fill' => true,
            'source' => [ 'core_key' => 'featured_image_id' ], 'hint' => 'Use an existing attachment ID, URL or filename. New files are uploaded through WordPress Media Library.'
        ] );

        foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
            if ( empty( $taxonomy->show_ui ) ) {
                continue;
            }
            $terms = wp_get_object_terms( $post_id, $taxonomy->name );
            if ( is_wp_error( $terms ) ) {
                continue;
            }
            $value = [];
            foreach ( $terms as $term ) {
                $value[] = [ 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug ];
            }
            $this->add_field( $fields, [
                'id' => 'tax:' . $taxonomy->name, 'section' => 'Categories & Taxonomies', 'provider' => 'taxonomy',
                'label' => $taxonomy->label, 'type' => 'terms', 'value' => $value, 'editable' => true, 'ai_fill' => false,
                'source' => [ 'taxonomy' => $taxonomy->name ],
                'hint' => 'Use existing term IDs or names. Create/restructure terms in 9 Category Manager.'
            ] );
        }

        $content = (string) $post->post_content;
        if ( strlen( $content ) <= self::MAX_BLOCK_BYTES && function_exists( 'parse_blocks' ) ) {
            $blocks = parse_blocks( $content );
            $this->walk_blocks( $blocks, [], $fields );
        } elseif ( '' !== trim( $content ) ) {
            $warnings[] = 'This post is too large for safe block-by-block 9CF parsing. Raw post content is included as one advanced field.';
            $this->add_field( $fields, [
                'id' => 'wp:content_raw', 'section' => 'Gutenberg', 'provider' => 'wordpress', 'label' => 'Raw post content', 'type' => 'html',
                'value' => $content, 'editable' => true, 'ai_fill' => false, 'source' => [ 'core_key' => 'content' ],
                'hint' => 'Advanced fallback for an unusually large document.'
            ] );
        }

        $acf_names = [];
        $acf_reference_keys = [];
        if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
            $groups = acf_get_field_groups( [ 'post_id' => $post_id ] );
            foreach ( (array) $groups as $group ) {
                $acf_fields = acf_get_fields( $group['key'] );
                foreach ( (array) $acf_fields as $acf_field ) {
                    if ( empty( $acf_field['name'] ) || in_array( $acf_field['type'] ?? '', [ 'tab', 'accordion', 'message' ], true ) ) {
                        continue;
                    }
                    $key = $acf_field['key'] ?? '';
                    $name = $acf_field['name'];
                    $acf_names[ $name ] = true;
                    if ( $key ) { $acf_reference_keys[ '_' . $name ] = true; }
                    $value = function_exists( 'get_field' ) ? get_field( $key ?: $name, $post_id, false ) : get_post_meta( $post_id, $name, true );
                    $this->add_field( $fields, [
                        'id' => 'acf:' . ( $key ?: $name ), 'section' => 'ACF Bridge', 'provider' => 'acf',
                        'label' => ! empty( $acf_field['label'] ) ? $acf_field['label'] : $name,
                        'type' => $this->acf_type_to_9cf( $acf_field['type'] ?? 'text' ), 'value' => $value,
                        'editable' => true, 'ai_fill' => true,
                        'source' => [ 'acf_key' => $key, 'acf_name' => $name, 'acf_type' => $acf_field['type'] ?? 'text' ],
                        'validation' => $this->acf_validation( $acf_field ),
                        'hint' => ! empty( $acf_field['instructions'] ) ? wp_strip_all_tags( $acf_field['instructions'] ) : 'ACF bridge field; ACF remains optional to the 9CF format.'
                    ] );
                }
            }
        }

        $registered = [];
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            $registered = array_merge( (array) get_registered_meta_keys( 'post', '' ), (array) get_registered_meta_keys( 'post', $post->post_type ) );
        }
        $all_meta = get_post_meta( $post_id );
        foreach ( $all_meta as $key => $values ) {
            if ( isset( $acf_names[ $key ] ) || isset( $acf_reference_keys[ $key ] ) ) {
                continue;
            }
            if ( $this->skip_meta_key( $key ) ) {
                continue;
            }
            $is_private = 0 === strpos( $key, '_' );
            $reg = isset( $registered[ $key ] ) && is_array( $registered[ $key ] ) ? $registered[ $key ] : [];
            $native_def = class_exists( 'Nine_Post_Manager_Fields' ) ? Nine_Post_Manager_Fields::instance()->definition_for_key( $post->post_type, $key ) : null;
            $manual_private = $is_private && empty( $reg['show_in_rest'] );
            $decoded = array_map( 'maybe_unserialize', $values );
            $value = 1 === count( $decoded ) ? $decoded[0] : $decoded;
            $label = $native_def && ! empty( $native_def['label'] ) ? $native_def['label'] : ( ! empty( $reg['description'] ) ? wp_strip_all_tags( $reg['description'] ) : $this->humanize_key( $key ) );
            $type = $native_def ? $this->native_field_type_to_9cf( $native_def['type'] ?? 'text' ) : ( ! empty( $reg['type'] ) ? $this->registered_meta_type_to_9cf( $reg['type'] ) : $this->infer_type( $value ) );
            $can_edit_meta = $this->can_edit_meta( $post_id, $key );
            $this->add_field( $fields, [
                'id' => 'meta:' . $key, 'section' => 'Plugin / Custom Fields', 'provider' => 'meta',
                'label' => $label, 'type' => $type, 'value' => $value, 'editable' => $can_edit_meta,
                'ai_fill' => $can_edit_meta && ! $manual_private && $this->meta_is_ai_fillable( $key, $reg ) && ( ! $native_def || ! empty( $native_def['ai_fill'] ) ),
                'source' => [
                    'meta_key' => $key,
                    'registered' => ! empty( $reg ),
                    'single' => array_key_exists( 'single', $reg ) ? (bool) $reg['single'] : ( 1 === count( $decoded ) ),
                    'rest_exposed' => ! empty( $reg['show_in_rest'] ),
                ],
                'validation' => $this->native_field_validation( $native_def, $this->registered_meta_validation( $reg ) ),
                'hint' => $can_edit_meta ? ( $manual_private ? 'Post-specific plugin/private setting. Editable manually in Post Manager; excluded from AI fill unless the owning plugin registers a semantic provider.' : ( $is_private ? 'Registered plugin field exposed by WordPress/REST.' : 'WordPress custom field / plugin data.' ) ) : 'Visible field, but your current WordPress capability does not allow 9CF to change it.'
            ] );
        }

        // Registered post-meta fields can be valid/fillable even before they have a stored value.
        // Include those empty declarations so an AI form describes what the post *can* contain,
        // not only what has already been populated.
        foreach ( $registered as $key => $reg ) {
            if ( array_key_exists( $key, $all_meta ) || isset( $acf_names[ $key ] ) || isset( $acf_reference_keys[ $key ] ) || $this->skip_meta_key( $key ) ) {
                continue;
            }
            $reg = is_array( $reg ) ? $reg : [];
            $native_def = class_exists( 'Nine_Post_Manager_Fields' ) ? Nine_Post_Manager_Fields::instance()->definition_for_key( $post->post_type, $key ) : null;
            $is_private = 0 === strpos( $key, '_' );
            $manual_private = $is_private && empty( $reg['show_in_rest'] );
            $default = array_key_exists( 'default', $reg ) ? $reg['default'] : $this->blank_for_type( $this->registered_meta_type_to_9cf( $reg['type'] ?? 'string' ) );
            $label = $native_def && ! empty( $native_def['label'] ) ? $native_def['label'] : ( ! empty( $reg['description'] ) ? wp_strip_all_tags( $reg['description'] ) : $this->humanize_key( $key ) );
            $type = $native_def ? $this->native_field_type_to_9cf( $native_def['type'] ?? 'text' ) : ( ! empty( $reg['type'] ) ? $this->registered_meta_type_to_9cf( $reg['type'] ) : $this->infer_type( $default ) );
            $can_edit_meta = $this->can_edit_meta( $post_id, $key );
            $this->add_field( $fields, [
                'id' => 'meta:' . $key, 'section' => 'Plugin / Custom Fields', 'provider' => 'meta',
                'label' => $label, 'type' => $type, 'value' => $default, 'editable' => $can_edit_meta,
                'ai_fill' => $can_edit_meta && ! $manual_private && $this->meta_is_ai_fillable( $key, $reg ) && ( ! $native_def || ! empty( $native_def['ai_fill'] ) ),
                'source' => [ 'meta_key' => $key, 'registered_empty' => true, 'registered' => true, 'single' => ! empty( $reg['single'] ), 'rest_exposed' => ! empty( $reg['show_in_rest'] ) ],
                'validation' => $this->native_field_validation( $native_def, $this->registered_meta_validation( $reg ) ),
                'hint' => $can_edit_meta ? ( $manual_private ? 'Registered private post setting. Editable manually; AI fill is disabled until the provider exposes a semantic contract.' : 'Registered WordPress/plugin field that is currently empty on this post.' ) : 'Registered field exists but is read-only for your current WordPress capability.'
            ] );
        }

        /**
         * Plugins can register semantic fields that are not ordinary post meta or blocks.
         * Each item should provide at least: id, label, value. Optional: type, section,
         * editable, ai_fill, hint, source, validation and provider_version. Use
         * npm9_9cf_validate_field/npm9_9cf_sanitize_field_value for validation/sanitising
         * and npm9_9cf_apply_field to handle writes.
         */
        $extra = apply_filters( 'npm9_9cf_fields', [], $post_id, $mode );
        foreach ( (array) $extra as $item ) {
            if ( ! is_array( $item ) || empty( $item['id'] ) || ! array_key_exists( 'value', $item ) ) {
                continue;
            }
            $item['provider'] = 'plugin';
            $item['section'] = $item['section'] ?? 'Plugin / Custom Fields';
            $item['label'] = $item['label'] ?? $this->humanize_key( $item['id'] );
            $item['type'] = $item['type'] ?? $this->infer_type( $item['value'] );
            $item['editable'] = array_key_exists( 'editable', $item ) ? (bool) $item['editable'] : true;
            $item['ai_fill'] = array_key_exists( 'ai_fill', $item ) ? (bool) $item['ai_fill'] : true;
            if ( 0 !== strpos( $item['id'], 'plugin:' ) ) {
                $item['id'] = 'plugin:' . sanitize_key( $item['id'] );
            }
            $this->add_field( $fields, $item );
        }

        $seen = [];
        $deduped = [];
        foreach ( $fields as $field ) {
            if ( isset( $seen[ $field['id'] ] ) ) {
                continue;
            }
            $seen[ $field['id'] ] = true;
            $deduped[] = $field;
            if ( count( $deduped ) >= self::MAX_FIELDS ) {
                $warnings[] = '9CF stopped at ' . self::MAX_FIELDS . ' fields to protect mobile/server memory. Split unusually large content into smaller posts.';
                break;
            }
        }
        $fields = $deduped;

        $number = 1;
        $counts = [];
        $structure = [];
        foreach ( $fields as &$field ) {
            $field['number'] = $number;
            $field['code'] = 'F' . str_pad( (string) $number, 3, '0', STR_PAD_LEFT );
            $field['display_label'] = str_pad( (string) $number, 3, '0', STR_PAD_LEFT ) . ' — ' . $field['label'];
            $field['baseline_hash'] = $this->field_hash( $field['value'] );
            $field['validation'] = isset( $field['validation'] ) && is_array( $field['validation'] ) ? $field['validation'] : $this->field_validation_rules( $field );
            $field['bridge'] = [
                'meta_key' => $this->bridge_meta_key( $field['id'] ),
                'acf_type' => $this->suggest_acf_type( $field ),
            ];
            if ( 'blank' === $mode && ! empty( $field['ai_fill'] ) ) {
                $field['value'] = $this->blank_for_type( $field['type'] );
            }
            $counts[ $field['provider'] ] = isset( $counts[ $field['provider'] ] ) ? $counts[ $field['provider'] ] + 1 : 1;
            $structure[] = [ $field['id'], $field['provider'], $field['type'], ! empty( $field['editable'] ), $this->field_source_signature( $field ) ];
            $number++;
        }
        unset( $field );

        $structure_fingerprint = hash( 'sha256', wp_json_encode( $structure ) );
        $modified_gmt = get_post_modified_time( 'c', true, $post_id );
        $contract_id = '9cf-' . substr( hash( 'sha256', home_url( '/' ) . '|' . $post_id . '|' . $modified_gmt . '|' . $structure_fingerprint . '|' . microtime( true ) ), 0, 24 );

        return [
            'format' => self::SCHEMA,
            'version' => self::SCHEMA_VERSION,
            'contract_id' => $contract_id,
            'mode' => $mode,
            'generated_at' => current_time( 'mysql' ),
            'goal' => '',
            'target' => [ 'post_id' => (int) $post_id, 'post_type' => $post->post_type, 'title' => $post->post_title ],
            'source' => [
                'site' => home_url( '/' ),
                'wordpress_version' => get_bloginfo( 'version' ),
                'modified_gmt' => $modified_gmt,
                'latest_revision_id' => $this->latest_revision_id( $post_id ),
                'content_hash' => hash( 'sha256', (string) $post->post_content ),
                'gutenberg_primary' => true,
                'acf_available' => function_exists( 'acf_get_field_groups' ),
                'nine_elements_available' => class_exists( 'Nine_Elements' ) || function_exists( 'nine_elements_render' ) || has_block( 'nine/elements', $post ),
            ],
            'instructions_for_ai' => [
                'This is a 9CF (Nine Content Fields) file. It is intentionally vendor-neutral and does not require ACF.',
                'Fill or improve field.value only. Do not rename field.id, field.code, provider, type, baseline_hash, validation, bridge or source metadata.',
                'Use the field codes (F001, F002, ...) when reporting progress, for example: “Filled F001–F010; F011 needs an image reference.”',
                'Fields with ai_fill=false are protected settings/structure. Leave them unchanged; 9 Post Editor intentionally skips AI changes to protected fields.',
                'Respect validation rules such as enum/required/type. If information is unknown, keep the existing/blank value and report the field code instead of inventing it.',
                'For media fields, prefer an existing WordPress attachment ID, URL or filename. Do not invent attachment IDs.',
                'For category/taxonomy fields, leave the structure unchanged; site structure is managed by 9 Category Manager.',
                'Return the complete file as valid JSON with format, version, contract_id, target, structure_fingerprint and every field preserved.',
            ],
            'structure_fingerprint' => $structure_fingerprint,
            'summary' => [ 'total_fields' => count( $fields ), 'counts' => $counts, 'warnings' => array_values( array_unique( $warnings ) ) ],
            'fields' => $fields,
        ];
    }

    private function add_field( &$fields, $field ) {
        if ( count( $fields ) >= self::MAX_FIELDS ) {
            return;
        }
        /** Allow the owning plugin to turn generic registered meta into a richer selectable field contract. */
        $field = apply_filters( 'npm9_9cf_field_contract', $field );
        if ( ! is_array( $field ) ) { return; }
        $field['id'] = (string) ( $field['id'] ?? '' );
        if ( '' === $field['id'] ) {
            return;
        }
        $field['section'] = (string) ( $field['section'] ?? 'Other' );
        $field['provider'] = sanitize_key( $field['provider'] ?? 'plugin' );
        $field['label'] = (string) ( $field['label'] ?? $field['id'] );
        $field['type'] = sanitize_key( $field['type'] ?? $this->infer_type( $field['value'] ?? '' ) );
        $field['editable'] = array_key_exists( 'editable', $field ) ? (bool) $field['editable'] : true;
        $field['ai_fill'] = array_key_exists( 'ai_fill', $field ) ? (bool) $field['ai_fill'] : $field['editable'];
        $field['hint'] = isset( $field['hint'] ) ? (string) $field['hint'] : '';
        $field['source'] = isset( $field['source'] ) && is_array( $field['source'] ) ? $field['source'] : [];
        $field['validation'] = isset( $field['validation'] ) && is_array( $field['validation'] ) ? $field['validation'] : [];
        if ( isset( $field['provider_version'] ) ) { $field['provider_version'] = sanitize_text_field( (string) $field['provider_version'] ); }
        $fields[] = $field;
    }

    private function walk_blocks( $blocks, $prefix, &$fields ) {
        foreach ( (array) $blocks as $index => $block ) {
            if ( count( $fields ) >= self::MAX_FIELDS ) {
                return;
            }
            $path = array_merge( $prefix, [ (int) $index ] );
            $path_key = implode( '.', $path );
            $name = isset( $block['blockName'] ) ? $block['blockName'] : null;
            if ( ! $name ) {
                $raw = isset( $block['innerHTML'] ) ? trim( (string) $block['innerHTML'] ) : '';
                if ( '' !== $raw ) {
                    $this->add_field( $fields, [
                        'id' => 'block:' . $path_key . ':raw', 'section' => 'Gutenberg', 'provider' => 'block',
                        'label' => 'Unstructured Gutenberg / classic content', 'type' => 'html', 'value' => $raw,
                        'editable' => true, 'ai_fill' => true,
                        'source' => [ 'block_path' => $path, 'operation' => 'leaf_html' ],
                        'group' => 'Content fragment ' . $this->path_label( $path ),
                        'hint' => 'Content outside a registered block.'
                    ] );
                }
                continue;
            }

            $title = $this->block_title( $name );
            $group = $this->path_label( $path ) . ' · ' . $title;
            $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
            $block_identity = $this->block_identity( $name, $attrs );
            $used = [];

            if ( 'nine/elements' === $name ) {
                $element_type = isset( $attrs['elementType'] ) ? sanitize_key( $attrs['elementType'] ) : 'heading';
                $special = [
                    'heading' => [ 'content' ], 'paragraph' => [ 'content' ], 'text' => [ 'content' ],
                    'list' => [ 'items', 'listStyle' ], 'icon-list' => [ 'items', 'icon', 'listStyle' ], 'tabs' => [ 'items' ], 'tab' => [ 'items' ],
                    'image' => [ 'mediaId', 'mediaUrl', 'alt', 'caption' ],
                    'audio' => [ 'mediaId', 'mediaUrl', 'caption' ], 'video' => [ 'mediaId', 'mediaUrl', 'caption' ],
                ];
                foreach ( (array) ( $special[ $element_type ] ?? [ 'content' ] ) as $attr ) {
                    $value = array_key_exists( $attr, $attrs ) ? $attrs[ $attr ] : '';
                    $type = in_array( $attr, [ 'mediaId' ], true ) ? 'number' : ( in_array( $attr, [ 'mediaUrl' ], true ) ? 'url' : ( 'items' === $attr ? 'textarea' : 'richtext' ) );
                    if ( in_array( $attr, [ 'listStyle', 'icon' ], true ) ) { $type = 'text'; }
                    $ai = ! in_array( $attr, [ 'listStyle', 'icon', 'mediaId' ], true );
                    $label = $this->humanize_key( $attr );
                    if ( 'content' === $attr ) { $label = ucfirst( $element_type ) . ' content'; }
                    if ( 'items' === $attr ) { $label = in_array( $element_type, [ 'tabs', 'tab' ], true ) ? 'Tab titles and content' : 'List items'; }
                    $this->add_field( $fields, [
                        'id' => 'block:' . $path_key . ':attr:' . $attr, 'section' => 'Gutenberg / 9 Elements', 'provider' => 'block',
                        'label' => $label, 'type' => $type, 'value' => $value, 'editable' => true, 'ai_fill' => $ai,
                        'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'element_type' => $element_type, 'operation' => 'attr', 'attr' => $attr ],
                        'group' => $group, 'hint' => '9 Elements ' . $element_type . ' block.'
                    ] );
                    $used[ $attr ] = true;
                }
                foreach ( [ 'elementType', 'preset', 'headingLevel' ] as $attr ) {
                    if ( ! array_key_exists( $attr, $attrs ) ) { continue; }
                    $this->add_field( $fields, [
                        'id' => 'block:' . $path_key . ':attr:' . $attr, 'section' => 'Gutenberg / 9 Elements', 'provider' => 'block',
                        'label' => $this->humanize_key( $attr ), 'type' => $this->infer_type( $attrs[ $attr ] ), 'value' => $attrs[ $attr ],
                        'editable' => true, 'ai_fill' => false,
                        'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'operation' => 'attr', 'attr' => $attr ],
                        'group' => $group, 'hint' => '9 Elements presentation setting; normally leave unchanged during AI content filling.'
                    ] );
                    $used[ $attr ] = true;
                }
            }

            $known = $this->known_inner_block_field( $name, $block, $path, $group, $block_identity );
            if ( $known ) {
                foreach ( $known as $field ) {
                    $this->add_field( $fields, $field );
                }
            }

            $schema = $this->block_attribute_schema( $name );
            foreach ( $attrs as $attr => $value ) {
                if ( isset( $used[ $attr ] ) || in_array( $attr, [ 'metadata', 'lock' ], true ) ) {
                    continue;
                }
                $def = isset( $schema[ $attr ] ) && is_array( $schema[ $attr ] ) ? $schema[ $attr ] : [];
                if ( isset( $def['role'] ) && 'local' === $def['role'] ) {
                    continue;
                }
                $type = ! empty( $def['type'] ) ? $this->registered_meta_type_to_9cf( $def['type'] ) : $this->infer_type( $value );
                $ai = ( ! empty( $def['role'] ) && 'content' === $def['role'] ) || $this->attr_is_ai_fillable( $attr );
                $this->add_field( $fields, [
                    'id' => 'block:' . $path_key . ':attr:' . $attr, 'section' => 'Gutenberg', 'provider' => 'block',
                    'label' => $title . ' · ' . $this->humanize_key( $attr ), 'type' => $type, 'value' => $value,
                    'editable' => true, 'ai_fill' => $ai,
                    'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'operation' => 'attr', 'attr' => $attr ],
                    'group' => $group,
                    'validation' => $this->block_attribute_validation( $def ),
                    'hint' => $ai ? 'Registered Gutenberg block content/setting.' : 'Gutenberg block setting. AI should leave unchanged unless asked.'
                ] );
            }

            // Some registered block attributes are semantically fillable but have not yet
            // been written into attrs because they are empty/default. Export them too so 9CF can
            // describe the complete fillable surface of an installed widget/block.
            foreach ( $schema as $attr => $def ) {
                if ( array_key_exists( $attr, $attrs ) || isset( $used[ $attr ] ) || in_array( $attr, [ 'metadata', 'lock' ], true ) ) {
                    continue;
                }
                $def = is_array( $def ) ? $def : [];
                if ( isset( $def['role'] ) && 'local' === $def['role'] ) {
                    continue;
                }
                // Source-backed attributes (html/text/query/attribute) are stored inside the
                // block markup rather than the delimiter attrs. Generic writes would be unsafe;
                // those need a known-core handler or a plugin-specific 9CF provider adapter.
                if ( ! empty( $def['source'] ) ) {
                    continue;
                }
                $ai = ( ! empty( $def['role'] ) && 'content' === $def['role'] ) || $this->attr_is_ai_fillable( $attr );
                if ( ! $ai ) {
                    continue;
                }
                $value = array_key_exists( 'default', $def ) ? $def['default'] : $this->blank_for_type( $this->registered_meta_type_to_9cf( $def['type'] ?? 'string' ) );
                $type = ! empty( $def['type'] ) ? $this->registered_meta_type_to_9cf( $def['type'] ) : $this->infer_type( $value );
                $this->add_field( $fields, [
                    'id' => 'block:' . $path_key . ':attr:' . $attr, 'section' => 'Gutenberg', 'provider' => 'block',
                    'label' => $title . ' · ' . $this->humanize_key( $attr ), 'type' => $type, 'value' => $value,
                    'editable' => true, 'ai_fill' => true,
                    'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'operation' => 'attr', 'attr' => $attr, 'registered_empty' => true ],
                    'group' => $group, 'validation' => $this->block_attribute_validation( $def ), 'hint' => 'Registered Gutenberg/widget content attribute that is currently empty.'
                ] );
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                $this->walk_blocks( $block['innerBlocks'], $path, $fields );
            } elseif ( empty( $known ) && 'nine/elements' !== $name && empty( $attrs ) && ! empty( trim( (string) ( $block['innerHTML'] ?? '' ) ) ) ) {
                $this->add_field( $fields, [
                    'id' => 'block:' . $path_key . ':markup', 'section' => 'Gutenberg', 'provider' => 'block',
                    'label' => $title . ' · raw block markup', 'type' => 'html', 'value' => (string) $block['innerHTML'],
                    'editable' => true, 'ai_fill' => false,
                    'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'operation' => 'leaf_html' ],
                    'group' => $group, 'hint' => 'Advanced fallback for an unmapped static block. Editing can invalidate poorly-built third-party blocks.'
                ] );
            }
        }
    }

    private function known_inner_block_field( $name, $block, $path, $group, $block_identity = '' ) {
        $html = (string) ( $block['innerHTML'] ?? '' );
        $path_key = implode( '.', $path );
        $out = [];
        $tag = '';
        $label = '';
        if ( 'core/paragraph' === $name ) { $tag = 'p'; $label = 'Paragraph'; }
        elseif ( 'core/heading' === $name ) { $tag = 'h1|h2|h3|h4|h5|h6'; $label = 'Heading'; }
        elseif ( 'core/list-item' === $name ) { $tag = 'li'; $label = 'List item'; }
        elseif ( 'core/button' === $name ) { $tag = 'a'; $label = 'Button text'; }
        if ( $tag ) {
            $value = $this->extract_first_tag_inner( $html, $tag );
            if ( null !== $value ) {
                $out[] = [
                    'id' => 'block:' . $path_key . ':text', 'section' => 'Gutenberg', 'provider' => 'block',
                    'label' => $label, 'type' => 'richtext', 'value' => $value, 'editable' => true, 'ai_fill' => true,
                    'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'operation' => 'inner_tag', 'tag' => $tag ],
                    'group' => $group, 'hint' => 'Visible Gutenberg content.'
                ];
            }
        }
        if ( 'core/button' === $name && empty( $block['attrs']['url'] ) ) {
            $href = $this->extract_first_tag_attr( $html, 'a', 'href' );
            if ( null !== $href ) {
                $out[] = [
                    'id' => 'block:' . $path_key . ':href', 'section' => 'Gutenberg', 'provider' => 'block',
                    'label' => 'Button URL', 'type' => 'url', 'value' => $href, 'editable' => true, 'ai_fill' => true,
                    'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'operation' => 'html_attr', 'tag' => 'a', 'attr' => 'href' ],
                    'group' => $group, 'hint' => 'Visible button destination.'
                ];
            }
        }
        if ( 'core/image' === $name ) {
            $alt = $this->extract_first_tag_attr( $html, 'img', 'alt' );
            if ( null !== $alt ) {
                $out[] = [
                    'id' => 'block:' . $path_key . ':alt', 'section' => 'Gutenberg', 'provider' => 'block',
                    'label' => 'Image alt text', 'type' => 'text', 'value' => html_entity_decode( $alt, ENT_QUOTES, get_bloginfo( 'charset' ) ),
                    'editable' => true, 'ai_fill' => true,
                    'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'operation' => 'html_attr', 'tag' => 'img', 'attr' => 'alt' ],
                    'group' => $group, 'hint' => 'Accessibility/search description for the image.'
                ];
            }
            $caption = $this->extract_first_tag_inner( $html, 'figcaption' );
            if ( null !== $caption ) {
                $out[] = [
                    'id' => 'block:' . $path_key . ':caption', 'section' => 'Gutenberg', 'provider' => 'block',
                    'label' => 'Image caption', 'type' => 'richtext', 'value' => $caption, 'editable' => true, 'ai_fill' => true,
                    'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'operation' => 'inner_tag', 'tag' => 'figcaption' ],
                    'group' => $group, 'hint' => 'Visible image caption.'
                ];
            }
        }
        if ( in_array( $name, [ 'core/html', 'core/shortcode', 'core/freeform', 'core/code', 'core/preformatted' ], true ) ) {
            $out[] = [
                'id' => 'block:' . $path_key . ':body', 'section' => 'Gutenberg', 'provider' => 'block',
                'label' => $this->block_title( $name ) . ' content', 'type' => 'html', 'value' => $html,
                'editable' => true, 'ai_fill' => in_array( $name, [ 'core/html', 'core/shortcode', 'core/freeform' ], true ) ? false : true,
                'source' => [ 'block_path' => $path, 'block_name' => $name, 'block_identity' => $block_identity, 'operation' => 'leaf_html' ],
                'group' => $group, 'hint' => 'Raw content of this Gutenberg block.'
            ];
        }
        return $out;
    }

    private function block_title( $name ) {
        if ( class_exists( 'WP_Block_Type_Registry' ) ) {
            $type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
            if ( $type && ! empty( $type->title ) ) {
                return wp_strip_all_tags( $type->title );
            }
        }
        return ucwords( str_replace( [ '/', '-', '_' ], ' ', $name ) );
    }

    private function block_attribute_schema( $name ) {
        if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
            return [];
        }
        $type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
        return $type && is_array( $type->attributes ) ? $type->attributes : [];
    }

    private function path_label( $path ) {
        return 'Block ' . implode( '.', array_map( static function( $i ) { return (string) ( (int) $i + 1 ); }, $path ) );
    }

    private function extract_first_tag_inner( $html, $tag_pattern ) {
        if ( preg_match( '#<(?:' . $tag_pattern . ')\b[^>]*>(.*?)</(?:' . $tag_pattern . ')>#is', $html, $m ) ) {
            return $m[1];
        }
        return null;
    }

    private function extract_first_tag_attr( $html, $tag, $attr ) {
        if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '\b[^>]*\s' . preg_quote( $attr, '#' ) . '\s*=\s*(["\'])(.*?)\1#is', $html, $m ) ) {
            return html_entity_decode( $m[2], ENT_QUOTES, get_bloginfo( 'charset' ) );
        }
        return null;
    }

    private function apply_block_changes( $post_id, $changes ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( '9cf_missing_post', 'Post not found.' );
        }
        if ( strlen( (string) $post->post_content ) > self::MAX_BLOCK_BYTES ) {
            return new WP_Error( '9cf_block_too_large', 'This post is too large for safe block-by-block 9CF editing. Use the raw content editor or Gutenberg.' );
        }
        if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) {
            return new WP_Error( '9cf_blocks_unavailable', 'WordPress block parser is unavailable.' );
        }
        $blocks = parse_blocks( (string) $post->post_content );
        foreach ( $changes as $change ) {
            $field = $change['field'];
            $source = $field['source'] ?? [];
            if ( empty( $source['block_path'] ) && ! isset( $source['block_path'] ) ) {
                continue;
            }
            $result = $this->apply_block_change_at_path( $blocks, (array) $source['block_path'], $source, $change['value'], $field );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }
        $serialized = serialize_blocks( $blocks );
        if ( $serialized === (string) $post->post_content ) {
            return true;
        }
        $updated = wp_update_post( wp_slash( [ 'ID' => $post_id, 'post_content' => $serialized ] ), true );
        return is_wp_error( $updated ) ? $updated : true;
    }

    private function apply_block_change_at_path( &$blocks, $path, $source, $value, $field ) {
        if ( empty( $path ) ) {
            return new WP_Error( '9cf_block_path', 'Invalid Gutenberg block path.' );
        }
        $index = (int) array_shift( $path );
        if ( ! isset( $blocks[ $index ] ) ) {
            return new WP_Error( '9cf_block_changed', 'The Gutenberg structure changed after the 9CF file was created. Export a fresh 9CF file before applying it.' );
        }
        if ( $path ) {
            if ( ! isset( $blocks[ $index ]['innerBlocks'] ) || ! is_array( $blocks[ $index ]['innerBlocks'] ) ) {
                return new WP_Error( '9cf_block_changed', 'The Gutenberg structure changed after the 9CF file was created.' );
            }
            return $this->apply_block_change_at_path( $blocks[ $index ]['innerBlocks'], $path, $source, $value, $field );
        }
        $block =& $blocks[ $index ];
        if ( ! empty( $source['block_name'] ) && ( $block['blockName'] ?? '' ) !== $source['block_name'] ) {
            return new WP_Error( '9cf_block_changed', 'A Gutenberg block moved or changed type. Export a fresh 9CF file.' );
        }
        $operation = $source['operation'] ?? 'attr';
        if ( 'attr' === $operation ) {
            $attr = (string) ( $source['attr'] ?? '' );
            if ( '' === $attr ) { return new WP_Error( '9cf_attr', 'Invalid block attribute.' ); }
            if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) { $block['attrs'] = []; }
            $block['attrs'][ $attr ] = $this->sanitize_value( $value, $field['type'] ?? 'text' );
            return true;
        }
        if ( 'leaf_html' === $operation ) {
            $html = current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value );
            $block['innerHTML'] = $html;
            $block['innerContent'] = [ $html ];
            return true;
        }
        if ( 'inner_tag' === $operation ) {
            $html = (string) ( $block['innerHTML'] ?? '' );
            $tag = (string) ( $source['tag'] ?? '' );
            $clean = current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value );
            $new = $this->replace_first_tag_inner( $html, $tag, $clean );
            if ( null === $new ) { return new WP_Error( '9cf_block_markup', 'The expected Gutenberg markup is no longer present. Export a fresh 9CF file.' ); }
            $block['innerHTML'] = $new;
            $block['innerContent'] = [ $new ];
            return true;
        }
        if ( 'html_attr' === $operation ) {
            $html = (string) ( $block['innerHTML'] ?? '' );
            $tag = (string) ( $source['tag'] ?? '' );
            $attr = (string) ( $source['attr'] ?? '' );
            $clean = 'href' === $attr || 'src' === $attr ? esc_url_raw( (string) $value ) : sanitize_text_field( (string) $value );
            $new = $this->replace_first_tag_attr( $html, $tag, $attr, $clean );
            if ( null === $new ) { return new WP_Error( '9cf_block_markup', 'The expected Gutenberg attribute is no longer present. Export a fresh 9CF file.' ); }
            $block['innerHTML'] = $new;
            $block['innerContent'] = [ $new ];
            return true;
        }
        return new WP_Error( '9cf_operation', 'Unsupported 9CF Gutenberg operation.' );
    }

    private function replace_first_tag_inner( $html, $tag_pattern, $replacement ) {
        $pattern = '#(<(?:' . $tag_pattern . ')\b[^>]*>)(.*?)(</(?:' . $tag_pattern . ')>)#is';
        $count = 0;
        $new = preg_replace_callback( $pattern, static function( $m ) use ( $replacement ) { return $m[1] . $replacement . $m[3]; }, $html, 1, $count );
        return $count ? $new : null;
    }

    private function replace_first_tag_attr( $html, $tag, $attr, $replacement ) {
        $pattern = '#(<' . preg_quote( $tag, '#' ) . '\b[^>]*\s' . preg_quote( $attr, '#' ) . '\s*=\s*)(["\'])(.*?)\2#is';
        $count = 0;
        $escaped = esc_attr( $replacement );
        $new = preg_replace_callback( $pattern, static function( $m ) use ( $escaped ) { return $m[1] . $m[2] . $escaped . $m[2]; }, $html, 1, $count );
        if ( $count ) { return $new; }
        $tag_pattern = '#<' . preg_quote( $tag, '#' ) . '\b[^>]*>#i';
        $new = preg_replace_callback( $tag_pattern, static function( $m ) use ( $attr, $escaped ) {
            return preg_replace( '/>$/', ' ' . $attr . '="' . $escaped . '">', $m[0] );
        }, $html, 1, $count );
        return $count ? $new : null;
    }

    public function ajax_export() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $mode = isset( $_POST['mode'] ) && 'blank' === sanitize_key( $_POST['mode'] ) ? 'blank' : 'current';
        $format = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : '9cf';
        $contract = $this->build_contract( $post_id, $mode );
        if ( is_wp_error( $contract ) ) { wp_send_json_error( [ 'message' => $contract->get_error_message() ] ); }
        $post = get_post( $post_id );
        $base = sanitize_file_name( ( $post->post_name ?: 'post-' . $post_id ) . '-' . ( 'blank' === $mode ? 'ai-form' : 'current' ) );
        if ( 'markdown' === $format ) {
            $content = $this->to_markdown( $contract );
            $filename = $base . '.9cf.md';
            $mime = 'text/markdown';
        } elseif ( 'json' === $format ) {
            $content = wp_json_encode( $contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $filename = $base . '.9cf.json';
            $mime = 'application/json';
        } else {
            $content = wp_json_encode( $contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $filename = $base . '.9cf';
            $mime = 'application/json';
        }
        wp_send_json_success( [ 'filename' => $filename, 'mime' => $mime, 'content' => $content, 'summary' => $contract['summary'] ] );
    }

    public function ajax_preview() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $incoming = $this->parse_content( $content );
        if ( is_wp_error( $incoming ) ) { wp_send_json_error( [ 'message' => $incoming->get_error_message() ] ); }
        $current = $this->build_contract( $post_id, 'current' );
        if ( is_wp_error( $current ) ) { wp_send_json_error( [ 'message' => $current->get_error_message() ] ); }
        $preview = $this->compare_contracts( $post_id, $current, $incoming );
        if ( is_wp_error( $preview ) ) { wp_send_json_error( [ 'message' => $preview->get_error_message() ] ); }
        wp_send_json_success( $preview );
    }

    public function ajax_apply() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $incoming = $this->parse_content( $content );
        if ( is_wp_error( $incoming ) ) { wp_send_json_error( [ 'message' => $incoming->get_error_message() ] ); }
        $current = $this->build_contract( $post_id, 'current' );
        if ( is_wp_error( $current ) ) { wp_send_json_error( [ 'message' => $current->get_error_message() ] ); }
        $preview = $this->compare_contracts( $post_id, $current, $incoming );
        if ( is_wp_error( $preview ) ) { wp_send_json_error( [ 'message' => $preview->get_error_message() ] ); }
        if ( ! empty( $preview['requiresConfirmation'] ) && empty( $_POST['confirm_risk'] ) ) {
            wp_send_json_error( [ 'message' => 'This 9CF file needs explicit confirmation because its target/revision/structure changed. Re-run preview and confirm the warning.' ], 409 );
        }
        $payload = $this->payload_from_incoming( $post_id, $current, $incoming, $preview );
        if ( is_wp_error( $payload ) ) { wp_send_json_error( [ 'message' => $payload->get_error_message() ] ); }
        if ( empty( $payload['post'] ) && empty( $payload['taxonomies'] ) && empty( $payload['acf'] ) && empty( $payload['meta'] ) && empty( $payload['ninecf'] ) ) {
            $this->record_import_history( $post_id, $incoming, $preview, 0 );
            wp_send_json_success( [
                'message' => 'No safe 9CF field values needed applying.' . ( ! empty( $preview['blockedCount'] ) ? ' ' . (int) $preview['blockedCount'] . ' field(s) were protected, conflicted or invalid.' : '' ),
                'changed' => 0,
                'blocked' => (int) ( $preview['blockedCount'] ?? 0 ),
            ] );
        }
        if ( ! class_exists( 'Nine_Post_Manager' ) || ! method_exists( Nine_Post_Manager::instance(), 'apply_external_payload' ) ) {
            wp_send_json_error( [ 'message' => '9 Post Editor bridge is unavailable.' ] );
        }
        $reason = 'Before 9CF AI import' . ( ! empty( $incoming['contract_id'] ) ? ' · ' . sanitize_text_field( $incoming['contract_id'] ) : '' );
        $result = Nine_Post_Manager::instance()->apply_external_payload( $post_id, $payload, $reason, true );
        if ( is_wp_error( $result ) ) { wp_send_json_error( [ 'message' => $result->get_error_message() ] ); }
        $this->sync_mirror( $post_id );
        $applied = count( $preview['safeChanges'] ?? $preview['changes'] ?? [] );
        $this->record_import_history( $post_id, $incoming, $preview, $applied );
        $message = 'Filled 9CF file applied safely. A recovery snapshot was saved first.';
        if ( ! empty( $preview['blockedCount'] ) ) {
            $message .= ' ' . (int) $preview['blockedCount'] . ' field(s) were skipped because they were protected, conflicted or invalid.';
        }
        wp_send_json_success( [ 'message' => $message, 'changed' => $applied, 'blocked' => (int) ( $preview['blockedCount'] ?? 0 ) ] );
    }

    private function compare_contracts( $post_id, $current, $incoming ) {
        if ( empty( $incoming['format'] ) || ! in_array( $incoming['format'], [ self::SCHEMA, 'ninecf-post', '9cf' ], true ) ) {
            return new WP_Error( '9cf_schema', 'This is not a recognised 9CF post file.' );
        }
        $incoming_version = isset( $incoming['version'] ) ? absint( $incoming['version'] ) : 1;
        if ( $incoming_version > self::SCHEMA_VERSION ) {
            return new WP_Error( '9cf_future_schema', 'This 9CF file uses schema version ' . $incoming_version . ', but this site supports up to version ' . self::SCHEMA_VERSION . '. Update 9 Post Editor before importing it.' );
        }
        if ( empty( $incoming['fields'] ) || ! is_array( $incoming['fields'] ) ) {
            return new WP_Error( '9cf_fields', 'The 9CF file does not contain a fields list.' );
        }
        if ( count( $incoming['fields'] ) > self::MAX_FIELDS ) {
            return new WP_Error( '9cf_too_many_fields', 'This 9CF file exceeds the safe field limit of ' . self::MAX_FIELDS . '.' );
        }

        $current_map = [];
        foreach ( $current['fields'] as $f ) { $current_map[ $f['id'] ] = $f; }
        $changes = [];
        $safe_changes = [];
        $unknown = [];
        $conflicts = [];
        $invalid = [];
        $protected = [];
        $remapped = [];
        $resolution = [];

        foreach ( $incoming['fields'] as $f ) {
            if ( ! is_array( $f ) || empty( $f['id'] ) || ! array_key_exists( 'value', $f ) ) { continue; }
            $incoming_id = (string) $f['id'];
            $resolved = $this->resolve_incoming_field( $f, $current_map, $current['fields'] );
            if ( empty( $resolved['field'] ) ) {
                $unknown[] = $incoming_id;
                continue;
            }
            $field = $resolved['field'];
            $id = $field['id'];
            $resolution[ $incoming_id ] = $id;
            if ( ! empty( $resolved['remapped'] ) ) {
                $remapped[] = [ 'from' => $incoming_id, 'to' => $id, 'code' => $field['code'], 'label' => $field['label'] ];
            }
            // An unchanged protected/read-only field is not a blocked change. Compare first so
            // the preview only reports fields the imported file actually tried to alter.
            if ( wp_json_encode( $field['value'] ) === wp_json_encode( $f['value'] ) ) {
                continue;
            }
            if ( empty( $field['editable'] ) ) {
                $protected[] = [ 'code' => $field['code'], 'id' => $id, 'label' => $field['label'], 'reason' => 'read_only' ];
                continue;
            }
            $change = [
                'code' => $field['code'], 'id' => $id, 'incoming_id' => $incoming_id, 'label' => $field['label'],
                'section' => $field['section'], 'old' => $field['value'], 'new' => $f['value'], 'provider' => $field['provider'],
            ];
            $changes[] = $change;

            if ( empty( $field['ai_fill'] ) ) {
                $change['reason'] = 'protected_setting';
                $protected[] = $change;
                continue;
            }

            if ( ! empty( $resolved['conflict'] ) ) {
                $change['reason'] = 'changed_since_export';
                $conflicts[] = $change;
                continue;
            }

            $valid = $this->validate_field_value( $post_id, $field, $f['value'] );
            if ( is_wp_error( $valid ) ) {
                $change['reason'] = $valid->get_error_message();
                $invalid[] = $change;
                continue;
            }
            $safe_changes[] = $change;
        }

        $warnings = [];
        $target_mismatch = false;
        if ( ! empty( $incoming['target']['post_id'] ) && (int) $incoming['target']['post_id'] !== (int) $post_id ) {
            $warnings[] = 'This 9CF file was exported from a different post ID.';
            $target_mismatch = true;
        }
        $post = get_post( $post_id );
        if ( ! empty( $incoming['target']['post_type'] ) && $incoming['target']['post_type'] !== $post->post_type ) {
            $warnings[] = 'This 9CF file targets a different post type.';
            $target_mismatch = true;
        }
        $structure_changed = ! empty( $incoming['structure_fingerprint'] ) && $incoming['structure_fingerprint'] !== $current['structure_fingerprint'];
        if ( $structure_changed ) {
            $warnings[] = 'The field/block structure changed after export. Schema v2 will apply only fields that can still be matched safely.';
        }
        if ( $unknown ) { $warnings[] = count( $unknown ) . ' field(s) no longer exist and will be ignored.'; }
        if ( $remapped ) { $warnings[] = count( $remapped ) . ' Gutenberg field(s) moved but were safely remapped using their block/value identity.'; }
        if ( $conflicts ) { $warnings[] = count( $conflicts ) . ' field(s) changed on the site after export and will be skipped to prevent overwriting newer work.'; }
        if ( $invalid ) { $warnings[] = count( $invalid ) . ' incoming field value(s) failed validation and will be skipped.'; }
        if ( $protected ) { $warnings[] = count( $protected ) . ' protected/read-only setting field(s) were changed in the file and will not be applied by the AI import workflow.'; }

        $stale = false;
        if ( ! empty( $incoming['source']['modified_gmt'] ) && $incoming['source']['modified_gmt'] !== $current['source']['modified_gmt'] ) {
            $warnings[] = 'The WordPress post changed after this 9CF file was exported. Field-level conflict checks will protect newer values.';
            $stale = true;
        }
        $revision_changed = false;
        if ( ! empty( $incoming['source']['latest_revision_id'] ) && ! empty( $current['source']['latest_revision_id'] ) && (int) $incoming['source']['latest_revision_id'] !== (int) $current['source']['latest_revision_id'] ) {
            $revision_changed = true;
            $warnings[] = 'The WordPress revision changed after export.';
        }
        if ( $incoming_version < self::SCHEMA_VERSION ) {
            $warnings[] = 'Legacy 9CF schema v' . $incoming_version . ' detected. It can be imported, but schema v2 field-level baseline protection is unavailable; export a fresh file for the safest workflow.';
        }

        return [
            'schemaVersion' => $incoming_version,
            'contractId' => isset( $incoming['contract_id'] ) ? sanitize_text_field( $incoming['contract_id'] ) : '',
            'changes' => $changes,
            'safeChanges' => $safe_changes,
            'warnings' => array_values( array_unique( $warnings ) ),
            'unknownFields' => $unknown,
            'conflicts' => $conflicts,
            'invalidFields' => $invalid,
            'protectedFields' => $protected,
            'remappedFields' => $remapped,
            'resolution' => $resolution,
            'blockedCount' => count( $unknown ) + count( $conflicts ) + count( $invalid ) + count( $protected ),
            'targetMismatch' => $target_mismatch,
            'structureChanged' => $structure_changed,
            'stale' => $stale,
            'revisionChanged' => $revision_changed,
            'requiresConfirmation' => $target_mismatch || $structure_changed || $stale || $revision_changed,
            'totalFields' => count( $current['fields'] ),
        ];
    }

    private function payload_from_incoming( $post_id, $current, $incoming, $preview = [] ) {
        $map = [];
        foreach ( $current['fields'] as $f ) { $map[ $f['id'] ] = $f; }
        $resolution = isset( $preview['resolution'] ) && is_array( $preview['resolution'] ) ? $preview['resolution'] : [];
        $safe_ids = [];
        foreach ( (array) ( $preview['safeChanges'] ?? [] ) as $change ) {
            if ( ! empty( $change['id'] ) ) { $safe_ids[ $change['id'] ] = true; }
        }
        $payload = [ 'post' => [], 'taxonomies' => [], 'acf' => [], 'meta' => [], 'ninecf' => [] ];
        foreach ( $incoming['fields'] as $incoming_field ) {
            if ( ! is_array( $incoming_field ) || empty( $incoming_field['id'] ) || ! array_key_exists( 'value', $incoming_field ) ) { continue; }
            $incoming_id = (string) $incoming_field['id'];
            $id = isset( $resolution[ $incoming_id ] ) ? (string) $resolution[ $incoming_id ] : $incoming_id;
            if ( empty( $map[ $id ] ) || empty( $map[ $id ]['editable'] ) || empty( $safe_ids[ $id ] ) ) { continue; }
            $field = $map[ $id ];
            $value = apply_filters( 'npm9_9cf_sanitize_field_value', $incoming_field['value'], $post_id, $field, $incoming_field );
            if ( wp_json_encode( $field['value'] ) === wp_json_encode( $value ) ) { continue; }
            if ( 'wordpress' === $field['provider'] ) {
                $key = $field['source']['core_key'] ?? '';
                if ( 'content' === $key ) { $payload['post']['content'] = (string) $value; }
                elseif ( 'featured_image_id' === $key ) { $payload['post']['featured_image_id'] = $this->media_id_from_value( $value ); }
                elseif ( $key ) { $payload['post'][ $key ] = $this->sanitize_value( $value, $field['type'] ); }
            } elseif ( 'taxonomy' === $field['provider'] ) {
                // Taxonomy fields are protected (ai_fill=false) in the AI workflow and normally never reach this branch.
                $taxonomy = $field['source']['taxonomy'] ?? '';
                $resolved = $this->resolve_terms( $taxonomy, $value );
                if ( is_wp_error( $resolved ) ) { return $resolved; }
                $payload['taxonomies'][ $taxonomy ] = $resolved;
            } elseif ( 'acf' === $field['provider'] ) {
                $name = $field['source']['acf_name'] ?? '';
                $key = $field['source']['acf_key'] ?? '';
                if ( $name ) { $payload['acf'][ $name ] = [ 'key' => $key, 'type' => $field['source']['acf_type'] ?? '', 'value' => $value ]; }
            } elseif ( 'meta' === $field['provider'] ) {
                $key = $field['source']['meta_key'] ?? '';
                if ( $key && 0 !== strpos( $key, '_' ) ) { $payload['meta'][ $key ] = $value; }
                else { $payload['ninecf'][ $id ] = $value; }
            } elseif ( in_array( $field['provider'], [ 'block', 'plugin' ], true ) ) {
                $payload['ninecf'][ $id ] = $value;
            }
        }
        return $payload;
    }

    private function resolve_terms( $taxonomy, $value ) {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( '9cf_taxonomy', 'Taxonomy no longer exists: ' . $taxonomy );
        }
        $ids = [];
        foreach ( (array) $value as $item ) {
            if ( is_array( $item ) ) {
                if ( ! empty( $item['id'] ) ) { $ids[] = absint( $item['id'] ); continue; }
                $item = $item['name'] ?? ( $item['slug'] ?? '' );
            }
            if ( is_numeric( $item ) ) { $ids[] = absint( $item ); continue; }
            $item = trim( (string) $item );
            if ( '' === $item ) { continue; }
            $term = get_term_by( 'name', $item, $taxonomy );
            if ( ! $term ) { $term = get_term_by( 'slug', sanitize_title( $item ), $taxonomy ); }
            if ( ! $term ) {
                return new WP_Error( '9cf_unknown_term', '9CF will not create site structure automatically. Create the category/term in 9 Category Manager first: ' . $item );
            }
            $ids[] = (int) $term->term_id;
        }
        return array_values( array_unique( array_filter( $ids ) ) );
    }

    public function ajax_mirror() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $enabled = ! empty( $_POST['enabled'] );
        if ( $enabled ) { update_post_meta( $post_id, self::META_MIRROR_ENABLED, 1 ); $this->sync_mirror( $post_id ); }
        else { delete_post_meta( $post_id, self::META_MIRROR_ENABLED ); }
        wp_send_json_success( [ 'enabled' => $enabled, 'message' => $enabled ? '9CF bridge mirror enabled and synchronized.' : '9CF bridge mirror disabled. Existing mirror values are left in place for safety.' ] );
    }

    public function ajax_mirror_sync() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $count = $this->sync_mirror( $post_id, true );
        if ( is_wp_error( $count ) ) { wp_send_json_error( [ 'message' => $count->get_error_message() ] ); }
        wp_send_json_success( [ 'message' => '9CF bridge mirror synchronized.', 'count' => $count ] );
    }

    public function ajax_acf_bridge() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $contract = $this->build_contract( $post_id, 'current' );
        if ( is_wp_error( $contract ) ) { wp_send_json_error( [ 'message' => $contract->get_error_message() ] ); }
        // Seed the mirror once so an imported ACF bridge can read the current values immediately.
        // Continuous synchronization remains opt-in through the 9CF Bridge Mirror switch.
        $seeded = $this->sync_mirror( $post_id, true );
        if ( is_wp_error( $seeded ) ) { wp_send_json_error( [ 'message' => $seeded->get_error_message() ] ); }
        $group = $this->acf_bridge_group( $post_id, $contract );
        $post = get_post( $post_id );
        wp_send_json_success( [
            'filename' => sanitize_file_name( ( $post->post_name ?: 'post-' . $post_id ) . '-9cf-acf-bridge.json' ),
            'mime' => 'application/json',
            'content' => wp_json_encode( [ $group ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'fieldCount' => count( $group['fields'] ),
            'seededMirrorCount' => (int) $seeded,
        ] );
    }

    private function acf_bridge_group( $post_id, $contract ) {
        $post = get_post( $post_id );
        $fields = [];
        foreach ( $contract['fields'] as $field ) {
            if ( empty( $field['editable'] ) || empty( $field['bridge']['meta_key'] ) ) { continue; }
            $acf_type = $field['bridge']['acf_type'];
            $hash = substr( hash( 'sha256', $field['id'] ), 0, 14 );
            $acf_field = [
                'key' => 'field_9cf_' . $hash,
                'label' => $field['code'] . ' — ' . $field['label'],
                'name' => $field['bridge']['meta_key'],
                'type' => $acf_type,
                'instructions' => '9CF bridge mirror of ' . $field['id'] . '. Source remains owned by ' . $field['provider'] . '; do not rename this field if you want the mirror mapping to remain portable.',
                'required' => 0,
            ];
            if ( 'textarea' === $acf_type ) { $acf_field['rows'] = 4; }
            if ( 'wysiwyg' === $acf_type ) { $acf_field['tabs'] = 'all'; $acf_field['toolbar'] = 'basic'; $acf_field['media_upload'] = 0; }
            if ( 'image' === $acf_type ) { $acf_field['return_format'] = 'id'; $acf_field['preview_size'] = 'medium'; }
            if ( 'file' === $acf_type ) { $acf_field['return_format'] = 'id'; }
            $fields[] = $acf_field;
        }
        return [
            'key' => 'group_9cf_bridge_' . substr( hash( 'sha256', $post->post_type . '|' . $contract['structure_fingerprint'] ), 0, 14 ),
            'title' => '9CF Bridge — ' . ( $post->post_title ?: 'Post ' . $post_id ),
            'fields' => $fields,
            'location' => [[ [ 'param' => 'post_type', 'operator' => '==', 'value' => $post->post_type ] ]],
            'menu_order' => 99,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => [],
            'active' => true,
            'description' => 'Generated by 9 Post Editor from the vendor-neutral 9CF field contract. Values are mirrored into matching post-meta keys when 9CF Bridge Mirror is enabled.',
            'show_in_rest' => 1,
        ];
    }

    public function sync_mirror_after_manager_save( $post_id, $payload ) {
        if ( get_post_meta( $post_id, self::META_MIRROR_ENABLED, true ) ) {
            $this->sync_mirror( $post_id );
        }
    }

    public function sync_mirror_on_save( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $update ) { return; }
        if ( get_post_meta( $post_id, self::META_MIRROR_ENABLED, true ) ) {
            $this->sync_mirror( $post_id );
        }
    }

    public function sync_mirror_after_acf( $post_id ) {
        if ( ! is_numeric( $post_id ) ) { return; }
        $post_id = absint( $post_id );
        if ( $post_id && get_post_meta( $post_id, self::META_MIRROR_ENABLED, true ) ) {
            $this->sync_mirror( $post_id );
        }
    }

    private function sync_mirror( $post_id, $force = false ) {
        if ( ! $force && ! get_post_meta( $post_id, self::META_MIRROR_ENABLED, true ) ) { return 0; }
        static $running = [];
        if ( ! empty( $running[ $post_id ] ) ) { return 0; }
        $running[ $post_id ] = true;
        $contract = $this->build_contract( $post_id, 'current' );
        if ( is_wp_error( $contract ) ) { unset( $running[ $post_id ] ); return $contract; }
        $index = [];
        $count = 0;
        foreach ( $contract['fields'] as $field ) {
            if ( empty( $field['editable'] ) || empty( $field['bridge']['meta_key'] ) ) { continue; }
            $meta_key = $field['bridge']['meta_key'];
            $value = $this->mirror_value( $field['value'], $field['type'] );
            update_post_meta( $post_id, $meta_key, $value );
            $index[ $meta_key ] = [ 'id' => $field['id'], 'code' => $field['code'], 'label' => $field['label'], 'provider' => $field['provider'], 'type' => $field['type'] ];
            $count++;
        }
        update_post_meta( $post_id, self::META_MIRROR_INDEX, $index );
        unset( $running[ $post_id ] );
        return $count;
    }

    private function mirror_value( $value, $type ) {
        if ( 'media' === $type ) { return $this->media_id_from_value( $value ); }
        if ( in_array( $type, [ 'json', 'terms', 'array', 'object' ], true ) || is_array( $value ) || is_object( $value ) ) {
            return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        }
        if ( 'boolean' === $type ) { return empty( $value ) ? 0 : 1; }
        return is_scalar( $value ) || null === $value ? $value : wp_json_encode( $value );
    }

    private function parse_content( $content ) {
        $content = (string) $content;
        if ( strlen( $content ) > self::MAX_IMPORT_BYTES ) { return new WP_Error( '9cf_import_too_large', 'This 9CF file exceeds the safe import limit of ' . size_format( self::MAX_IMPORT_BYTES ) . '.' ); }
        $content = trim( $content );
        if ( '' === $content ) { return new WP_Error( '9cf_empty', '9CF file is empty.' ); }
        $data = json_decode( $content, true );
        if ( is_array( $data ) ) { return $data; }
        if ( preg_match( '/<!--\s*NINECF_DATA_START\s*-->.*?```json\s*(\{.*?\})\s*```.*?<!--\s*NINECF_DATA_END\s*-->/si', $content, $m ) ) {
            $data = json_decode( $m[1], true );
            if ( is_array( $data ) ) { return $data; }
        }
        if ( preg_match( '/```json\s*(\{.*\})\s*```/si', $content, $m ) ) {
            $data = json_decode( $m[1], true );
            if ( is_array( $data ) ) { return $data; }
        }
        return new WP_Error( '9cf_invalid', 'Could not find valid 9CF JSON data in this file.' );
    }

    private function to_markdown( $contract ) {
        $lines = [ '# 9CF AI Post Form', '', 'Goal: ' . ( $contract['goal'] ?: '(describe your goal to the AI)' ), '', '## Instructions', '' ];
        foreach ( $contract['instructions_for_ai'] as $instruction ) { $lines[] = '- ' . $instruction; }
        $lines[] = '';
        $last_section = '';
        foreach ( $contract['fields'] as $field ) {
            if ( $field['section'] !== $last_section ) {
                $last_section = $field['section'];
                $lines[] = '## ' . $last_section;
                $lines[] = '';
            }
            $lines[] = '### ' . $field['code'] . ' — ' . $field['label'];
            $lines[] = '- ID: `' . $field['id'] . '`';
            $lines[] = '- Provider: `' . $field['provider'] . '`';
            $lines[] = '- Type: `' . $field['type'] . '`';
            $lines[] = '- AI fill: ' . ( $field['ai_fill'] ? 'yes' : 'no — leave unchanged unless asked' );
            if ( $field['hint'] ) { $lines[] = '- Note: ' . $field['hint']; }
            $lines[] = '```json';
            $lines[] = wp_json_encode( $field['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            $lines[] = '```';
            $lines[] = '';
        }
        $lines[] = '## Machine Data';
        $lines[] = 'The plugin imports this JSON block. Preserve every field ID and edit values only.';
        $lines[] = '';
        $lines[] = '<!-- NINECF_DATA_START -->';
        $lines[] = '```json';
        $lines[] = wp_json_encode( $contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $lines[] = '```';
        $lines[] = '<!-- NINECF_DATA_END -->';
        return implode( "\n", $lines );
    }

    private function verify( $post_id ) {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }
    }

    private function native_field_type_to_9cf( $type ) {
        $type = sanitize_key( $type );
        if ( in_array( $type, [ 'image','file' ], true ) ) return 'media';
        if ( in_array( $type, [ 'number','integer' ], true ) ) return 'number';
        if ( 'boolean' === $type ) return 'boolean';
        if ( 'url' === $type ) return 'url';
        if ( 'email' === $type ) return 'email';
        if ( 'select' === $type ) return 'text';
        return in_array( $type, [ 'textarea','richtext' ], true ) ? 'textarea' : 'text';
    }

    private function native_field_validation( $definition, $base ) {
        $base = is_array( $base ) ? $base : [];
        if ( ! is_array( $definition ) ) return $base;
        if ( ! empty( $definition['required'] ) ) $base['required'] = true;
        if ( 'select' === ( $definition['type'] ?? '' ) && ! empty( $definition['choices'] ) ) $base['enum'] = array_values( $definition['choices'] );
        return $base;
    }

    private function skip_meta_key( $key ) {
        if ( in_array( $key, [ '_edit_lock', '_edit_last', '_thumbnail_id', '_wp_page_template' ], true ) ) { return true; }
        foreach ( [ '_npm9_', 'ninecf_', '_elementor_', '_wp_old_', '_wp_attached_', '_wp_attachment_', '_acf_', '_oembed_' ] as $prefix ) {
            if ( 0 === strpos( $key, $prefix ) ) { return true; }
        }
        return false;
    }

    private function field_hash( $value ) {
        return hash( 'sha256', wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    private function latest_revision_id( $post_id ) {
        if ( ! function_exists( 'wp_get_post_revisions' ) ) { return 0; }
        $revisions = wp_get_post_revisions( $post_id, [ 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'DESC', 'check_enabled' => false ] );
        if ( ! $revisions ) { return 0; }
        $first = reset( $revisions );
        return $first && isset( $first->ID ) ? (int) $first->ID : 0;
    }

    private function can_edit_meta( $post_id, $key ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return false; }
        $registered = [];
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            $registered = array_merge( (array) get_registered_meta_keys( 'post', '' ), (array) get_registered_meta_keys( 'post', get_post_type( $post_id ) ) );
        }
        // A plugin that explicitly registered its field owns the permission contract.
        if ( isset( $registered[ $key ] ) ) { return current_user_can( 'edit_post_meta', $post_id, $key ); }
        // Unregistered private post meta is common in classic WordPress sidebars/drawers.
        // 9PM exposes it for the human editor who can edit the post, but private fields remain
        // excluded from AI fill unless the owning plugin supplies a semantic 9CF provider.
        return true;
    }

    private function registered_meta_validation( $reg ) {
        $out = [];
        if ( ! is_array( $reg ) ) { return $out; }
        if ( ! empty( $reg['type'] ) ) { $out['registered_type'] = sanitize_key( (string) $reg['type'] ); }
        if ( isset( $reg['single'] ) ) { $out['single'] = (bool) $reg['single']; }
        if ( isset( $reg['show_in_rest'] ) && is_array( $reg['show_in_rest'] ) && ! empty( $reg['show_in_rest']['schema'] ) && is_array( $reg['show_in_rest']['schema'] ) ) {
            $schema = $reg['show_in_rest']['schema'];
            if ( ! empty( $schema['enum'] ) && is_array( $schema['enum'] ) ) { $out['enum'] = array_values( $schema['enum'] ); }
            if ( isset( $schema['minLength'] ) ) { $out['min_length'] = absint( $schema['minLength'] ); }
            if ( isset( $schema['maxLength'] ) ) { $out['max_length'] = absint( $schema['maxLength'] ); }
        }
        return $out;
    }

    private function acf_validation( $field ) {
        $out = [];
        if ( ! is_array( $field ) ) { return $out; }
        if ( ! empty( $field['required'] ) ) { $out['required'] = true; }
        if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) { $out['enum'] = array_values( array_map( 'strval', array_keys( $field['choices'] ) ) ); }
        if ( isset( $field['maxlength'] ) && '' !== (string) $field['maxlength'] ) { $out['max_length'] = absint( $field['maxlength'] ); }
        if ( isset( $field['min'] ) && '' !== (string) $field['min'] ) { $out['min'] = is_numeric( $field['min'] ) ? 0 + $field['min'] : $field['min']; }
        if ( isset( $field['max'] ) && '' !== (string) $field['max'] ) { $out['max'] = is_numeric( $field['max'] ) ? 0 + $field['max'] : $field['max']; }
        return $out;
    }

    private function block_attribute_validation( $def ) {
        $out = [];
        if ( ! is_array( $def ) ) { return $out; }
        if ( ! empty( $def['type'] ) ) { $out['registered_type'] = sanitize_key( (string) $def['type'] ); }
        if ( ! empty( $def['enum'] ) && is_array( $def['enum'] ) ) { $out['enum'] = array_values( $def['enum'] ); }
        if ( ! empty( $def['role'] ) ) { $out['role'] = sanitize_key( (string) $def['role'] ); }
        return $out;
    }

    private function field_validation_rules( $field ) {
        $out = [];
        if ( 'status' === ( $field['type'] ?? '' ) ) { $out['enum'] = [ 'publish', 'draft', 'pending', 'private', 'future' ]; }
        if ( 'media' === ( $field['type'] ?? '' ) ) { $out['must_resolve_to_media_library'] = true; }
        return $out;
    }

    private function normalize_boolean( $value ) {
        if ( is_bool( $value ) ) { return $value; }
        if ( is_numeric( $value ) ) { return 0 !== (int) $value; }
        $value = strtolower( trim( (string) $value ) );
        if ( in_array( $value, [ '', '0', 'false', 'no', 'off', 'disabled', 'null' ], true ) ) { return false; }
        if ( in_array( $value, [ '1', 'true', 'yes', 'on', 'enabled' ], true ) ) { return true; }
        return ! empty( $value );
    }

    private function validate_field_value( $post_id, $field, $value ) {
        $code = ! empty( $field['code'] ) ? $field['code'] : ( $field['id'] ?? '9CF field' );
        $label = ! empty( $field['label'] ) ? $field['label'] : ( $field['id'] ?? 'field' );
        $type = sanitize_key( $field['type'] ?? 'text' );
        $validation = isset( $field['validation'] ) && is_array( $field['validation'] ) ? $field['validation'] : [];

        if ( ! empty( $validation['required'] ) && ( null === $value || '' === $value || [] === $value ) ) {
            return new WP_Error( '9cf_required', $code . ' (' . $label . ') is required.' );
        }
        if ( isset( $validation['enum'] ) && is_array( $validation['enum'] ) && ! is_array( $value ) && '' !== (string) $value && ! in_array( (string) $value, array_map( 'strval', $validation['enum'] ), true ) ) {
            return new WP_Error( '9cf_enum', $code . ' (' . $label . ') must use one of the allowed values: ' . implode( ', ', array_map( 'strval', $validation['enum'] ) ) . '.' );
        }
        if ( 'number' === $type && '' !== $value && null !== $value && ! is_numeric( $value ) ) {
            return new WP_Error( '9cf_number', $code . ' (' . $label . ') must be numeric.' );
        }
        if ( 'url' === $type && '' !== trim( (string) $value ) && ! filter_var( (string) $value, FILTER_VALIDATE_URL ) && 0 !== strpos( (string) $value, '/' ) ) {
            return new WP_Error( '9cf_url', $code . ' (' . $label . ') must be a valid URL.' );
        }
        if ( 'email' === $type && '' !== trim( (string) $value ) && ! is_email( (string) $value ) ) {
            return new WP_Error( '9cf_email', $code . ' (' . $label . ') must be a valid email address.' );
        }
        if ( 'status' === $type && ! in_array( sanitize_key( (string) $value ), [ 'publish', 'draft', 'pending', 'private', 'future' ], true ) ) {
            return new WP_Error( '9cf_status', $code . ' (' . $label . ') has an unsupported publication status.' );
        }
        if ( 'media' === $type && ! empty( $value ) && ! $this->media_id_from_value( $value ) ) {
            return new WP_Error( '9cf_media', $code . ' (' . $label . ') could not be matched to an existing WordPress Media Library item.' );
        }
        if ( in_array( $type, [ 'json', 'array', 'object' ], true ) && is_string( $value ) && '' !== trim( $value ) ) {
            json_decode( $value, true );
            if ( JSON_ERROR_NONE !== json_last_error() ) { return new WP_Error( '9cf_json', $code . ' (' . $label . ') must contain valid JSON/structured data.' ); }
        }
        if ( isset( $validation['max_length'] ) && is_scalar( $value ) && strlen( (string) $value ) > (int) $validation['max_length'] ) {
            return new WP_Error( '9cf_max_length', $code . ' (' . $label . ') is longer than the allowed ' . (int) $validation['max_length'] . ' characters.' );
        }
        if ( isset( $validation['min'] ) && is_numeric( $value ) && $value < $validation['min'] ) { return new WP_Error( '9cf_min', $code . ' (' . $label . ') is below the allowed minimum.' ); }
        if ( isset( $validation['max'] ) && is_numeric( $value ) && $value > $validation['max'] ) { return new WP_Error( '9cf_max', $code . ' (' . $label . ') is above the allowed maximum.' ); }

        $provider_result = apply_filters( 'npm9_9cf_validate_field', null, $post_id, $field, $value );
        if ( is_wp_error( $provider_result ) ) { return $provider_result; }
        if ( false === $provider_result ) { return new WP_Error( '9cf_provider_validation', $code . ' (' . $label . ') was rejected by its provider.' ); }
        return true;
    }

    private function block_identity( $name, $attrs ) {
        $attrs = is_array( $attrs ) ? $attrs : [];
        if ( ! empty( $attrs['anchor'] ) ) { return sanitize_key( $name ) . '#anchor:' . sanitize_title( (string) $attrs['anchor'] ); }
        if ( ! empty( $attrs['metadata']['name'] ) ) { return sanitize_key( $name ) . '#name:' . sanitize_title( (string) $attrs['metadata']['name'] ); }
        // Element type alone is not a stable identity: a page can contain many paragraph/image/etc. blocks.
        // Only an explicit anchor or WordPress block name is strong enough for cross-path remapping.
        return '';
    }

    private function field_source_signature( $field ) {
        $source = isset( $field['source'] ) && is_array( $field['source'] ) ? $field['source'] : [];
        if ( 'block' !== ( $field['provider'] ?? '' ) ) { return ''; }
        $parts = [
            (string) ( $source['block_name'] ?? '' ),
            (string) ( $source['block_identity'] ?? '' ),
            (string) ( $source['element_type'] ?? '' ),
            (string) ( $source['operation'] ?? '' ),
            (string) ( $source['attr'] ?? '' ),
            (string) ( $source['tag'] ?? '' ),
        ];
        return implode( '|', $parts );
    }

    private function resolve_incoming_field( $incoming_field, $current_map, $current_fields ) {
        $incoming_id = (string) ( $incoming_field['id'] ?? '' );
        $baseline = isset( $incoming_field['baseline_hash'] ) ? (string) $incoming_field['baseline_hash'] : '';
        if ( isset( $current_map[ $incoming_id ] ) ) {
            $exact = $current_map[ $incoming_id ];
            if ( '' === $baseline || hash_equals( $baseline, $this->field_hash( $exact['value'] ) ) ) {
                return [ 'field' => $exact, 'remapped' => false, 'conflict' => false ];
            }
            // The path now points at different content. Try a unique safe block remap before calling it a conflict.
            $remap = $this->find_block_remap( $incoming_field, $current_fields );
            if ( $remap ) { return [ 'field' => $remap, 'remapped' => $remap['id'] !== $incoming_id, 'conflict' => false ]; }
            return [ 'field' => $exact, 'remapped' => false, 'conflict' => true ];
        }
        $remap = $this->find_block_remap( $incoming_field, $current_fields );
        if ( $remap ) { return [ 'field' => $remap, 'remapped' => true, 'conflict' => false ]; }
        return [ 'field' => null, 'remapped' => false, 'conflict' => false ];
    }

    private function find_block_remap( $incoming_field, $current_fields ) {
        if ( 'block' !== ( $incoming_field['provider'] ?? '' ) || empty( $incoming_field['baseline_hash'] ) ) { return null; }
        $source = isset( $incoming_field['source'] ) && is_array( $incoming_field['source'] ) ? $incoming_field['source'] : [];
        // Cross-path remapping is deliberately conservative. A block type or 9 Elements
        // elementType is not unique enough; require an explicit anchor or named block.
        if ( empty( $source['block_identity'] ) ) { return null; }
        $signature = implode( '|', [ (string) ( $source['block_name'] ?? '' ), (string) ( $source['block_identity'] ?? '' ), (string) ( $source['element_type'] ?? '' ), (string) ( $source['operation'] ?? '' ), (string) ( $source['attr'] ?? '' ), (string) ( $source['tag'] ?? '' ) ] );
        $candidates = [];
        foreach ( (array) $current_fields as $field ) {
            if ( 'block' !== ( $field['provider'] ?? '' ) ) { continue; }
            if ( $signature !== $this->field_source_signature( $field ) ) { continue; }
            if ( hash_equals( (string) $incoming_field['baseline_hash'], $this->field_hash( $field['value'] ) ) ) { $candidates[] = $field; }
        }
        return 1 === count( $candidates ) ? $candidates[0] : null;
    }

    private function import_history( $post_id ) {
        $items = get_post_meta( $post_id, self::META_IMPORT_HISTORY, true );
        if ( ! is_array( $items ) ) { return []; }
        return array_slice( array_values( $items ), 0, self::MAX_HISTORY );
    }

    private function record_import_history( $post_id, $incoming, $preview, $applied_count ) {
        $items = $this->import_history( $post_id );
        $user = wp_get_current_user();
        $codes = [];
        foreach ( (array) ( $preview['safeChanges'] ?? [] ) as $change ) { if ( ! empty( $change['code'] ) ) { $codes[] = $change['code']; } }
        array_unshift( $items, [
            'created_at' => current_time( 'mysql' ),
            'user_id' => get_current_user_id(),
            'user' => $user && $user->exists() ? $user->display_name : '',
            'contract_id' => sanitize_text_field( (string) ( $incoming['contract_id'] ?? '' ) ),
            'schema_version' => absint( $incoming['version'] ?? 1 ),
            'applied_count' => (int) $applied_count,
            'blocked_count' => (int) ( $preview['blockedCount'] ?? 0 ),
            'codes' => array_slice( array_values( array_unique( $codes ) ), 0, 100 ),
            'warnings' => array_slice( array_map( 'sanitize_text_field', (array) ( $preview['warnings'] ?? [] ) ), 0, 20 ),
        ] );
        update_post_meta( $post_id, self::META_IMPORT_HISTORY, array_slice( $items, 0, self::MAX_HISTORY ) );
    }

    private function meta_is_ai_fillable( $key, $registered ) {
        $lower = strtolower( $key );
        foreach ( [ 'id', 'count', 'order', 'position', 'status', 'template', 'layout', 'style', 'color', 'size', 'width', 'height', 'enabled', 'disabled', 'version' ] as $word ) {
            if ( false !== strpos( $lower, $word ) ) { return false; }
        }
        return true;
    }

    private function attr_is_ai_fillable( $attr ) {
        $lower = strtolower( $attr );
        foreach ( [ 'content', 'text', 'title', 'label', 'caption', 'alt', 'description', 'excerpt', 'summary', 'items', 'url', 'href', 'name' ] as $word ) {
            if ( false !== strpos( $lower, $word ) ) { return true; }
        }
        return false;
    }

    private function acf_type_to_9cf( $type ) {
        if ( in_array( $type, [ 'textarea' ], true ) ) return 'textarea';
        if ( in_array( $type, [ 'wysiwyg', 'oembed' ], true ) ) return 'richtext';
        if ( in_array( $type, [ 'image', 'file' ], true ) ) return 'media';
        if ( in_array( $type, [ 'number', 'range' ], true ) ) return 'number';
        if ( 'true_false' === $type ) return 'boolean';
        if ( in_array( $type, [ 'gallery', 'repeater', 'flexible_content', 'group', 'relationship', 'post_object', 'taxonomy', 'user', 'checkbox', 'select', 'link', 'google_map' ], true ) ) return 'json';
        if ( 'url' === $type ) return 'url';
        if ( 'email' === $type ) return 'email';
        return 'text';
    }

    private function suggest_acf_type( $field ) {
        $source = isset( $field['source'] ) && is_array( $field['source'] ) ? $field['source'] : [];
        $element = sanitize_key( $source['element_type'] ?? '' );
        $attr = sanitize_key( $source['attr'] ?? '' );
        $id = (string) ( $field['id'] ?? '' );
        if ( 'wp:featured_image' === $id || ( 'image' === $element && 'mediaid' === $attr ) ) {
            return 'image';
        }
        if ( in_array( $element, [ 'audio', 'video' ], true ) && 'mediaid' === $attr ) {
            return 'file';
        }
        return $this->ninecf_type_to_acf( $field['type'] ?? 'text' );
    }

    private function ninecf_type_to_acf( $type ) {
        switch ( $type ) {
            case 'number': return 'number';
            case 'boolean': return 'true_false';
            case 'url': return 'url';
            case 'email': return 'email';
            case 'media': return 'image';
            case 'richtext': return 'wysiwyg';
            case 'textarea':
            case 'html':
            case 'json':
            case 'terms':
            case 'array':
            case 'object': return 'textarea';
            default: return 'text';
        }
    }

    private function registered_meta_type_to_9cf( $type ) {
        if ( in_array( $type, [ 'integer', 'number' ], true ) ) return 'number';
        if ( 'boolean' === $type ) return 'boolean';
        if ( in_array( $type, [ 'array', 'object' ], true ) ) return 'json';
        return 'text';
    }

    private function infer_type( $value ) {
        if ( is_bool( $value ) ) return 'boolean';
        if ( is_int( $value ) || is_float( $value ) ) return 'number';
        if ( is_array( $value ) || is_object( $value ) ) return 'json';
        if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) return 'url';
        if ( is_string( $value ) && strlen( $value ) > 220 ) return 'textarea';
        return 'text';
    }

    private function sanitize_value( $value, $type ) {
        switch ( $type ) {
            case 'number': return is_numeric( $value ) ? 0 + $value : 0;
            case 'boolean': return $this->normalize_boolean( $value ) ? 1 : 0;
            case 'url': return esc_url_raw( (string) $value );
            case 'email': return sanitize_email( (string) $value );
            case 'slug': return sanitize_title( (string) $value );
            case 'status': return sanitize_key( (string) $value );
            case 'textarea': return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
            case 'richtext':
            case 'html': return current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value );
            case 'json':
            case 'array':
            case 'object': return is_array( $value ) || is_object( $value ) ? $value : $this->decode_jsonish( $value );
            case 'media': return $this->media_id_from_value( $value );
            default: return is_array( $value ) || is_object( $value ) ? $value : sanitize_text_field( (string) $value );
        }
    }

    private function decode_jsonish( $value ) {
        if ( ! is_string( $value ) ) return $value;
        $decoded = json_decode( $value, true );
        return JSON_ERROR_NONE === json_last_error() ? $decoded : $value;
    }

    private function blank_for_type( $type ) {
        if ( in_array( $type, [ 'json', 'terms', 'array' ], true ) ) return [];
        if ( 'object' === $type ) return (object) [];
        if ( in_array( $type, [ 'number', 'boolean', 'media' ], true ) ) return 0;
        return '';
    }

    private function media_reference( $id ) {
        $id = absint( $id );
        if ( ! $id ) return [ 'id' => 0, 'url' => '', 'filename' => '' ];
        $url = wp_get_attachment_url( $id );
        return [ 'id' => $id, 'url' => $url ?: '', 'filename' => $url ? wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ) : '' ];
    }

    private function media_id_from_value( $value ) {
        if ( is_array( $value ) ) {
            foreach ( [ 'id', 'ID', 'attachment_id' ] as $key ) { if ( ! empty( $value[ $key ] ) ) return absint( $value[ $key ] ); }
            $value = $value['url'] ?? ( $value['filename'] ?? '' );
        }
        if ( is_numeric( $value ) ) return absint( $value );
        $value = trim( (string) $value );
        if ( '' === $value ) return 0;
        if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
            $id = attachment_url_to_postid( $value );
            if ( $id ) return (int) $id;
            $value = wp_basename( wp_parse_url( $value, PHP_URL_PATH ) );
        }
        $filename = sanitize_file_name( $value );
        if ( ! $filename ) return 0;
        $posts = get_posts( [
            'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 10, 'fields' => 'ids',
            'meta_query' => [[ 'key' => '_wp_attached_file', 'value' => $filename, 'compare' => 'LIKE' ]],
        ] );
        return ! empty( $posts[0] ) ? (int) $posts[0] : 0;
    }

    private function bridge_meta_key( $id ) {
        return 'ninecf_' . substr( hash( 'sha256', $id ), 0, 12 );
    }

    private function humanize_key( $key ) {
        $key = preg_replace( '/([a-z])([A-Z])/', '$1 $2', (string) $key );
        return ucwords( trim( str_replace( [ '_', '-', ':', '/' ], ' ', $key ) ) );
    }
}
