<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Compatibility helpers keep the Core-owned AI Data Manager independent from optional integrations.
 * Core owns the unified AI Data Manager and its authoritative data workflow; the Theme owns public presentation only.
 */
if ( ! function_exists( 'nce_ai_safe_value' ) ) {
    function nce_ai_safe_value( $value, $depth = 0 ) {
        if ( $depth > 5 ) { return '[depth limit]'; }
        if ( is_array( $value ) ) { $out = array(); foreach ( array_slice( $value, 0, 200, true ) as $key => $item ) { $out[ sanitize_key( $key ) ] = nce_ai_safe_value( $item, $depth + 1 ); } return $out; }
        if ( is_object( $value ) ) { return nce_ai_safe_value( (array) $value, $depth + 1 ); }
        if ( is_scalar( $value ) || null === $value ) { return $value; }
        return (string) $value;
    }
}
if ( ! function_exists( 'nce_get_design_tokens' ) ) {
    function nce_get_design_tokens() {
        if ( function_exists( 'ncu_get_effective_design_tokens' ) ) { return ncu_get_effective_design_tokens(); }
        $settings = function_exists( 'ncu_theme_settings' ) ? ncu_theme_settings() : array();
        return array(
            'primary' => isset( $settings['accent_color'] ) ? $settings['accent_color'] : '#000000',
            'surface' => isset( $settings['surface_color'] ) ? $settings['surface_color'] : '#ffffff',
            'text' => isset( $settings['text_color'] ) ? $settings['text_color'] : '#111111',
            'muted' => isset( $settings['muted_color'] ) ? $settings['muted_color'] : '#555555',
            'border' => '#e5e5e5',
        );
    }
}
if ( ! function_exists( 'nce_ai_build_recipes' ) ) {
    function nce_ai_build_recipes( $post_type = '' ) { return apply_filters( 'nce_ai_build_recipes', array(), $post_type ); }
}

/**
 * Central AI-first content workspace.
 *
 * The browser holds one synchronized working model. Server-side drafts protect
 * unfinished work; only an explicit Save or Publish action mutates content.
 */

function nce_ai_editor_post_types() {
    $out = array();
    foreach ( get_post_types( array(), 'objects' ) as $type ) {
        $viewable = function_exists( 'is_post_type_viewable' ) ? is_post_type_viewable( $type ) : ! empty( $type->public );
        if ( 'attachment' === $type->name || ! $viewable || empty( $type->cap->edit_posts ) || ! current_user_can( $type->cap->edit_posts ) ) { continue; }
        $out[ $type->name ] = array( 'name' => $type->name, 'label' => $type->labels->name, 'singular' => $type->labels->singular_name, 'description' => $type->description );
    }
    return $out;
}

function nce_ai_editor_create_capability( $object ) {
    if ( ! $object || empty( $object->cap ) ) { return 'edit_posts'; }
    $cap = ! empty( $object->cap->create_posts ) ? $object->cap->create_posts : $object->cap->edit_posts;
    return $cap ? $cap : 'edit_posts';
}

function nce_ai_editor_taxonomies() {
    $out = array();
    foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $taxonomy ) {
        if ( 'post_format' === $taxonomy->name || empty( $taxonomy->cap->edit_terms ) || ! current_user_can( $taxonomy->cap->edit_terms ) ) { continue; }
        $out[ $taxonomy->name ] = array( 'name' => $taxonomy->name, 'label' => $taxonomy->labels->name, 'singular' => $taxonomy->labels->singular_name, 'hierarchical' => (bool) $taxonomy->hierarchical, 'object_types' => array_values( (array) $taxonomy->object_type ) );
    }
    return $out;
}

function nce_ai_editor_target( $kind, $type, $id ) {
    $kind = in_array( $kind, array( 'post', 'term' ), true ) ? $kind : '';
    $type = sanitize_key( $type ); $id = absint( $id );
    if ( ! $kind || ! $type || ! $id ) { return new WP_Error( 'invalid_target', __( 'Choose a valid content target.', 'nine-code-ultra' ) ); }
    if ( 'post' === $kind ) {
        $post = get_post( $id ); $object = get_post_type_object( $type );
        if ( ! $post || $post->post_type !== $type || ! $object || ! current_user_can( 'edit_post', $id ) ) { return new WP_Error( 'forbidden_target', __( 'You cannot edit this content.', 'nine-code-ultra' ) ); }
        return array( 'kind' => 'post', 'type' => $type, 'id' => $id, 'object' => $post, 'type_object' => $object );
    }
    $term = get_term( $id, $type ); $taxonomy = get_taxonomy( $type );
    if ( ! $term || is_wp_error( $term ) || ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) { return new WP_Error( 'forbidden_target', __( 'You cannot edit this term.', 'nine-code-ultra' ) ); }
    return array( 'kind' => 'term', 'type' => $type, 'id' => $id, 'object' => $term, 'type_object' => $taxonomy );
}

function nce_ai_editor_profile( $target ) {
    $slug = strtolower( $target['type'] ); $label = $target['type_object']->labels->singular_name;
    $profiles = array(
        'flyer' => array( 'goal' => 'Create concise, scan-friendly promotional information for a flyer.', 'rules' => array( 'Prefer short headings and compact copy.', 'Do not turn flyer fields into long articles.', 'Keep dates, fees, contacts and calls to action precise.' ) ),
        'course' => array( 'goal' => 'Create structured learning information for a course.', 'rules' => array( 'Keep outcomes measurable.', 'Maintain progression between modules and lessons.', 'Use clear learner-facing language.' ) ),
        'lesson' => array( 'goal' => 'Create a focused teaching unit.', 'rules' => array( 'Align outcomes, explanations, examples and assessment.', 'Preserve academic accuracy.', 'Use scannable instructional structure.' ) ),
        'event' => array( 'goal' => 'Create accurate event information that supports attendance and registration.', 'rules' => array( 'Keep dates, venue, mode, fees and deadlines consistent.', 'Make the next action unmistakable.' ) ),
        'product' => array( 'goal' => 'Create decision-ready product information.', 'rules' => array( 'Separate benefits from specifications.', 'Do not invent prices, stock or guarantees.' ) ),
        'service' => array( 'goal' => 'Create clear service information and scope.', 'rules' => array( 'State inclusions, exclusions, requirements and delivery clearly.', 'Avoid unsupported promises.' ) ),
        'publication' => array( 'goal' => 'Create scholarly publication metadata and reader-facing summaries.', 'rules' => array( 'Preserve names, citations, identifiers and academic meaning.', 'Do not fabricate bibliographic facts.' ) ),
        'profile' => array( 'goal' => 'Create an accurate professional profile.', 'rules' => array( 'Preserve spelling of names, institutions and qualifications.', 'Do not invent achievements.' ) ),
        'insight' => array( 'goal' => 'Create a concise public insight connected to its author and course context.', 'rules' => array( 'Lead with practical value.', 'Keep author attribution accurate.' ) ),
    );
    $selected = array( 'goal' => sprintf( __( 'Create and maintain accurate %s content for this site.', 'nine-code-ultra' ), strtolower( $label ) ), 'rules' => array( __( 'Respect each field’s purpose and existing verified facts.', 'nine-code-ultra' ), __( 'Return only requested field changes.', 'nine-code-ultra' ) ) );
    foreach ( $profiles as $needle => $profile ) { if ( false !== strpos( $slug, $needle ) ) { $selected = $profile; break; } }
    $context = array(
        'type' => $target['type'], 'label' => $label, 'goal' => $selected['goal'], 'rules' => $selected['rules'],
        'presentation' => array( 'surface' => '9Core 15', 'design_tokens' => nce_get_design_tokens(), 'build_recipes' => nce_ai_build_recipes( 'post' === $target['kind'] ? $target['type'] : '' ) ),
    );
    if ( ! empty( $target['type_object']->description ) ) { $context['description'] = sanitize_text_field( $target['type_object']->description ); }
    return apply_filters( 'nce_ai_editor_target_context', $context, $target );
}

function nce_ai_editor_field( $id, $storage, $key, $label, $type, $value, $extra = array() ) {
    return array_merge( array( 'id' => sanitize_key( str_replace( ':', '_', $id ) ), 'storage' => $storage, 'key' => $key, 'label' => $label, 'type' => $type, 'value' => nce_ai_safe_value( $value ), 'editable' => true, 'ai_default' => true, 'instructions' => '' ), $extra );
}

function nce_ai_editor_term_options( $taxonomy, $exclude = 0 ) {
    $options = array(); $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 500 ) );
    if ( is_wp_error( $terms ) ) { return $options; }
    foreach ( $terms as $term ) {
        if ( (int) $term->term_id === (int) $exclude ) { continue; }
        $ancestors = array_reverse( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) ); $prefix = $ancestors ? str_repeat( '— ', count( $ancestors ) ) : '';
        $options[] = array( 'value' => (int) $term->term_id, 'label' => $prefix . $term->name );
    }
    return $options;
}

function nce_ai_editor_acf_groups( $target ) {
    if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) || ! function_exists( 'get_field' ) ) { return array(); }
    $context = 'post' === $target['kind'] ? $target['id'] : $target['type'] . '_' . $target['id'];
    $screen = 'post' === $target['kind'] ? array( 'post_id' => $context, 'post_type' => $target['type'] ) : array( 'post_id' => $context, 'taxonomy' => $target['type'], 'term_id' => $target['id'] );
    $groups = acf_get_field_groups( $screen ); $out = array();
    foreach ( (array) $groups as $group ) {
        $fields = array();
        foreach ( (array) acf_get_fields( $group ) as $field ) {
            if ( empty( $field['name'] ) || empty( $field['key'] ) ) { continue; }
            $acf_type = isset( $field['type'] ) ? $field['type'] : 'text'; $input = 'text'; $extra = array( 'acf_key' => $field['key'], 'acf_type' => $acf_type );
            if ( in_array( $acf_type, array( 'textarea', 'wysiwyg' ), true ) ) { $input = 'textarea'; }
            elseif ( in_array( $acf_type, array( 'number', 'range' ), true ) ) { $input = 'number'; }
            elseif ( 'true_false' === $acf_type ) { $input = 'checkbox'; }
            elseif ( in_array( $acf_type, array( 'select', 'radio', 'button_group' ), true ) ) { $input = 'select'; $extra['options'] = array(); foreach ( (array) ( isset( $field['choices'] ) ? $field['choices'] : array() ) as $value => $label ) { $extra['options'][] = array( 'value' => (string) $value, 'label' => (string) $label ); } }
            elseif ( in_array( $acf_type, array( 'checkbox' ), true ) ) { $input = 'multiselect'; $extra['options'] = array(); foreach ( (array) ( isset( $field['choices'] ) ? $field['choices'] : array() ) as $value => $label ) { $extra['options'][] = array( 'value' => (string) $value, 'label' => (string) $label ); } }
            elseif ( 'image' === $acf_type ) { $input = 'media'; $raw_media = get_field( $field['name'], $context, false ); $extra['preview_url'] = $raw_media ? wp_get_attachment_image_url( absint( is_array( $raw_media ) ? ( $raw_media['ID'] ?? 0 ) : $raw_media ), 'medium' ) : ''; }
            elseif ( 'file' === $acf_type ) { $input = 'file'; $raw_file = get_field( $field['name'], $context, false ); $extra['file_url'] = $raw_file ? wp_get_attachment_url( absint( is_array( $raw_file ) ? ( $raw_file['ID'] ?? 0 ) : $raw_file ) ) : ''; }
            elseif ( in_array( $acf_type, array( 'repeater', 'flexible_content', 'group', 'relationship', 'post_object', 'taxonomy', 'user', 'gallery', 'link' ), true ) ) { $input = 'json'; }
            $extra['instructions'] = isset( $field['instructions'] ) ? wp_strip_all_tags( $field['instructions'] ) : '';
            $fields[] = nce_ai_editor_field( 'acf_' . $field['key'], 'acf', $field['name'], isset( $field['label'] ) ? $field['label'] : $field['name'], $input, get_field( $field['name'], $context, false ), $extra );
        }
        if ( $fields ) { $out[] = array( 'id' => 'acf_' . sanitize_key( isset( $group['key'] ) ? $group['key'] : wp_unique_id() ), 'label' => isset( $group['title'] ) ? $group['title'] : __( 'Structured fields', 'nine-code-ultra' ), 'description' => __( 'Fields supplied by Advanced Custom Fields.', 'nine-code-ultra' ), 'fields' => $fields ); }
    }
    return $out;
}

/**
 * Only expose ordinary content metadata in the AI workspace. Metadata is a
 * shared WordPress storage layer, so a key that is not private (no leading
 * underscore) can still be a connector, session or operational secret owned
 * by another plugin. Specialist plugins should contribute intentional fields
 * through nce_ai_editor_field_groups instead.
 */
function nce_ai_editor_is_safe_meta_key( $key, $target ) {
    $key = sanitize_key( $key );
    if ( ! $key || 0 === strpos( $key, '_' ) ) { return false; }
    $sensitive = '/(?:^|[_-])(api|auth|token|secret|password|passwd|credential|webhook|nonce|session|license|private|access[_-]?key)(?:[_-]|$)/i';
    $safe = ! preg_match( $sensitive, $key );
    return (bool) apply_filters( 'nce_ai_editor_safe_meta_key', $safe, $key, $target );
}

function nce_ai_editor_meta_group( $target, $known = array() ) {
    $meta = 'post' === $target['kind'] ? get_post_meta( $target['id'] ) : get_term_meta( $target['id'] ); $fields = array();
    foreach ( (array) $meta as $key => $values ) {
        if ( ! nce_ai_editor_is_safe_meta_key( $key, $target ) || isset( $known[ $key ] ) ) { continue; }
        $value = isset( $values[0] ) ? maybe_unserialize( $values[0] ) : ''; $type = is_array( $value ) || is_object( $value ) ? 'json' : ( is_bool( $value ) ? 'checkbox' : 'text' );
        $fields[] = nce_ai_editor_field( 'meta_' . $key, 'meta', $key, ucwords( str_replace( array( '-', '_' ), ' ', $key ) ), $type, $value );
    }
    return $fields ? array( 'id' => 'custom_fields', 'label' => __( 'Custom fields', 'nine-code-ultra' ), 'description' => __( 'Safe non-private metadata already attached to this item.', 'nine-code-ultra' ), 'fields' => $fields ) : array();
}


function nce_ai_editor_neighbor_ids( $target ) {
    if ( ! is_array( $target ) || 'post' !== ( $target['kind'] ?? '' ) ) { return array( 'previous' => 0, 'next' => 0 ); }
    global $wpdb;
    $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
    $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
    $base = array_merge( array( $target['type'] ), $statuses );
    $prev_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status IN ({$placeholders}) AND ID < %d ORDER BY ID DESC LIMIT 1";
    $next_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT 1";
    $prev = (int) $wpdb->get_var( $wpdb->prepare( $prev_sql, array_merge( $base, array( $target['id'] ) ) ) );
    $next = (int) $wpdb->get_var( $wpdb->prepare( $next_sql, array_merge( $base, array( $target['id'] ) ) ) );
    return array(
        'previous' => $prev && current_user_can( 'edit_post', $prev ) ? $prev : 0,
        'next' => $next && current_user_can( 'edit_post', $next ) ? $next : 0,
    );
}

function nce_ai_editor_schema( $target ) {
    if ( is_wp_error( $target ) ) { return $target; }
    $groups = array(); $known_meta = array();
    if ( 'post' === $target['kind'] ) {
        $post = $target['object'];
        $groups[] = array( 'id' => 'core_content', 'label' => __( 'Core content', 'nine-code-ultra' ), 'description' => __( 'The main identity and written content.', 'nine-code-ultra' ), 'fields' => array(
            nce_ai_editor_field( 'core_title', 'core', 'post_title', __( 'Title', 'nine-code-ultra' ), 'text', $post->post_title ),
            nce_ai_editor_field( 'core_slug', 'core', 'post_name', __( 'URL slug', 'nine-code-ultra' ), 'text', $post->post_name ),
            nce_ai_editor_field( 'core_excerpt', 'core', 'post_excerpt', __( 'Excerpt or summary', 'nine-code-ultra' ), 'textarea', $post->post_excerpt ),
            nce_ai_editor_field( 'core_content', 'core', 'post_content', __( 'Main content', 'nine-code-ultra' ), 'textarea', $post->post_content ),
        ) );
        $groups[] = array( 'id' => 'publishing', 'label' => __( 'Publishing', 'nine-code-ultra' ), 'description' => __( 'Visibility and featured presentation.', 'nine-code-ultra' ), 'fields' => array(
            nce_ai_editor_field( 'core_status', 'core', 'post_status', __( 'Status', 'nine-code-ultra' ), 'select', $post->post_status, array( 'options' => array( array( 'value' => 'draft', 'label' => 'Draft' ), array( 'value' => 'pending', 'label' => 'Pending review' ), array( 'value' => 'private', 'label' => 'Private' ), array( 'value' => 'publish', 'label' => 'Published' ) ) ) ),
            nce_ai_editor_field( 'core_featured_image', 'featured', '_thumbnail_id', __( 'Featured image', 'nine-code-ultra' ), 'media', get_post_thumbnail_id( $post->ID ), array( 'preview_url' => get_the_post_thumbnail_url( $post->ID, 'medium' ), 'instructions' => __( 'Choose or replace the image from the WordPress Media Library.', 'nine-code-ultra' ) ) ),
        ) );
        foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
            if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) { continue; }
            $groups[] = array( 'id' => 'taxonomy_' . sanitize_key( $taxonomy->name ), 'label' => $taxonomy->labels->name, 'description' => sprintf( __( 'Assign this %1$s within %2$s.', 'nine-code-ultra' ), strtolower( $target['type_object']->labels->singular_name ), $taxonomy->labels->name ), 'fields' => array(
                nce_ai_editor_field( 'taxonomy_' . $taxonomy->name, 'taxonomy', $taxonomy->name, $taxonomy->labels->name, 'multiselect', wp_get_object_terms( $post->ID, $taxonomy->name, array( 'fields' => 'ids' ) ), array( 'options' => nce_ai_editor_term_options( $taxonomy->name ) ) ),
            ) );
        }
    } else {
        $term = $target['object']; $taxonomy = $target['type_object'];
        $groups[] = array( 'id' => 'term_content', 'label' => __( 'Term content', 'nine-code-ultra' ), 'description' => sprintf( __( 'The identity and description of this %s.', 'nine-code-ultra' ), strtolower( $taxonomy->labels->singular_name ) ), 'fields' => array(
            nce_ai_editor_field( 'term_name', 'term_core', 'name', __( 'Name', 'nine-code-ultra' ), 'text', $term->name ),
            nce_ai_editor_field( 'term_slug', 'term_core', 'slug', __( 'URL slug', 'nine-code-ultra' ), 'text', $term->slug ),
            nce_ai_editor_field( 'term_description', 'term_core', 'description', __( 'Description', 'nine-code-ultra' ), 'textarea', $term->description ),
            nce_ai_editor_field( 'term_parent', 'term_core', 'parent', __( 'Parent', 'nine-code-ultra' ), 'select', (int) $term->parent, array( 'options' => array_merge( array( array( 'value' => 0, 'label' => __( 'No parent', 'nine-code-ultra' ) ) ), nce_ai_editor_term_options( $target['type'], $target['id'] ) ) ) ),
        ) );
    }
    $acf_groups = nce_ai_editor_acf_groups( $target );
    foreach ( $acf_groups as $group ) { foreach ( $group['fields'] as $field ) { if ( ! empty( $field['key'] ) ) { $known_meta[ sanitize_key( $field['key'] ) ] = true; } if ( ! empty( $field['name'] ) ) { $known_meta[ sanitize_key( $field['name'] ) ] = true; } } $groups[] = $group; }
    $meta_group = nce_ai_editor_meta_group( $target, $known_meta ); if ( $meta_group ) { $groups[] = $meta_group; }
    $groups = apply_filters( 'nce_ai_editor_field_groups', $groups, $target );
    /* The Data Manager v0.5 contract can add registered fields to this same workspace. */
    $external = apply_filters( 'ncu_data_manager_ai_field_groups', array(), $target );
    foreach ( (array) $external as $group ) { if ( is_array( $group ) && ! empty( $group['fields'] ) ) { $groups[] = $group; } }
    $neighbors = nce_ai_editor_neighbor_ids( $target );
    $target_data = array(
        'kind' => $target['kind'], 'type' => $target['type'], 'id' => $target['id'],
        'label' => 'post' === $target['kind'] ? ( get_the_title( $target['id'] ) ?: sprintf( __( 'Untitled #%d', 'nine-code-ultra' ), $target['id'] ) ) : $target['object']->name,
        'edit_url' => 'post' === $target['kind'] ? get_edit_post_link( $target['id'], 'raw' ) : get_edit_term_link( $target['id'], $target['type'] ),
        'view_url' => 'post' === $target['kind'] ? ( 'publish' === get_post_status( $target['id'] ) ? get_permalink( $target['id'] ) : get_preview_post_link( $target['id'] ) ) : get_term_link( $target['id'], $target['type'] ),
        'previous_id' => (int) $neighbors['previous'],
        'next_id' => (int) $neighbors['next'],
    );
    $schema = array( 'format' => '9-code-ai-editor-workspace', 'schema' => 1, 'target' => $target_data, 'context' => nce_ai_editor_profile( $target ), 'groups' => array_values( $groups ) );
    $schema['state_sha256'] = nce_ai_editor_schema_hash( $schema );
    return $schema;
}

function nce_ai_editor_schema_hash( $schema ) {
    $contract = array();
    foreach ( (array) $schema['groups'] as $group ) {
        $group_contract = array( 'id' => isset( $group['id'] ) ? $group['id'] : '', 'fields' => array() );
        foreach ( (array) $group['fields'] as $field ) {
            $group_contract['fields'][] = array(
                'id' => isset( $field['id'] ) ? $field['id'] : '',
                'storage' => isset( $field['storage'] ) ? $field['storage'] : '',
                'key' => isset( $field['key'] ) ? $field['key'] : '',
                'type' => isset( $field['type'] ) ? $field['type'] : '',
                'value' => isset( $field['value'] ) ? $field['value'] : null,
            );
        }
        $contract[] = $group_contract;
    }
    return hash( 'sha256', wp_json_encode( $contract ) );
}

function nce_ai_editor_field_map( $schema ) {
    $map = array(); foreach ( (array) $schema['groups'] as $group ) { foreach ( (array) $group['fields'] as $field ) { $map[ $field['id'] ] = $field; } } return $map;
}

function nce_ai_editor_decode_value( $value, $field ) {
    $type = isset( $field['type'] ) ? $field['type'] : 'text';
    if ( 'number' === $type ) { return is_numeric( $value ) ? 0 + $value : 0; }
    if ( 'checkbox' === $type ) { return empty( $value ) ? 0 : 1; }
    if ( 'multiselect' === $type ) { return is_array( $value ) ? array_values( array_map( 'sanitize_text_field', $value ) ) : array(); }
    if ( 'json' === $type ) {
        if ( is_array( $value ) ) { return $value; }
        $decoded = json_decode( (string) $value, true ); return is_array( $decoded ) ? $decoded : sanitize_textarea_field( (string) $value );
    }
    if ( 'textarea' === $type ) { return current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value ); }
    return sanitize_text_field( (string) $value );
}

function nce_ai_editor_workspace_key( $target ) { return $target['kind'] . '_' . $target['type'] . '_' . $target['id']; }
function nce_ai_editor_workspace_meta_key( $target ) { return '_nce_ai_workspace_' . md5( nce_ai_editor_workspace_key( $target ) ); }

function nce_ai_editor_sanitize_workspace( $raw, $schema ) {
    $map = nce_ai_editor_field_map( $schema ); $valid_groups = array(); foreach ( (array) $schema['groups'] as $group ) { if ( isset( $group['id'] ) ) { $valid_groups[ sanitize_key( $group['id'] ) ] = true; } } $now_ms = (int) floor( microtime( true ) * 1000 );
    $updated_at = isset( $raw['updated_at'] ) && is_numeric( $raw['updated_at'] ) ? (int) $raw['updated_at'] : $now_ms;
    if ( $updated_at > 0 && $updated_at < 1000000000000 ) { $updated_at *= 1000; }
    if ( $updated_at <= 0 || $updated_at > $now_ms + 300000 ) { $updated_at = $now_ms; }
    $out = array( 'selected' => array(), 'values' => array(), 'group_prompts' => array(), 'field_prompts' => array(), 'global_prompt' => '', 'connector' => 'export', 'updated_at' => $updated_at );
    foreach ( array_slice( array_unique( array_map( 'sanitize_key', isset( $raw['selected'] ) && is_array( $raw['selected'] ) ? $raw['selected'] : array() ) ), 0, 500 ) as $id ) { if ( isset( $map[ $id ] ) ) { $out['selected'][] = $id; } }
    foreach ( (array) ( isset( $raw['values'] ) ? $raw['values'] : array() ) as $id => $value ) { $id = sanitize_key( $id ); if ( isset( $map[ $id ] ) ) { $out['values'][ $id ] = nce_ai_editor_decode_value( $value, $map[ $id ] ); } }
    foreach ( array( 'group_prompts', 'field_prompts' ) as $bucket ) { foreach ( array_slice( (array) ( isset( $raw[ $bucket ] ) ? $raw[ $bucket ] : array() ), 0, 500, true ) as $id => $prompt ) { $id = sanitize_key( $id ); $allowed = 'group_prompts' === $bucket ? isset( $valid_groups[ $id ] ) : isset( $map[ $id ] ); if ( $allowed ) { $out[ $bucket ][ $id ] = substr( sanitize_textarea_field( $prompt ), 0, 5000 ); } } }
    $out['global_prompt'] = substr( sanitize_textarea_field( isset( $raw['global_prompt'] ) ? $raw['global_prompt'] : '' ), 0, 20000 );
    $out['connector'] = sanitize_key( isset( $raw['connector'] ) ? $raw['connector'] : 'export' );
    $allowed_connectors = array_merge( array( 'export' ), array_keys( nce_ai_editor_provider_registry() ) );
    if ( ! in_array( $out['connector'], $allowed_connectors, true ) ) { $out['connector'] = 'export'; }
    return $out;
}

function nce_ai_editor_saved_workspace( $target, $schema ) {
    $user_id = get_current_user_id(); $saved = get_user_meta( $user_id, nce_ai_editor_workspace_meta_key( $target ), true );
    if ( is_array( $saved ) ) { return nce_ai_editor_sanitize_workspace( $saved, $schema ); }
    $legacy = get_user_meta( $user_id, '_nce_ai_editor_workspaces', true ); $legacy_key = nce_ai_editor_workspace_key( $target );
    return is_array( $legacy ) && isset( $legacy[ $legacy_key ] ) && is_array( $legacy[ $legacy_key ] ) ? nce_ai_editor_sanitize_workspace( $legacy[ $legacy_key ], $schema ) : array();
}

function nce_ai_editor_store_workspace( $target, $workspace, $schema ) {
    $user_id = get_current_user_id(); $meta_key = nce_ai_editor_workspace_meta_key( $target ); $sanitized = nce_ai_editor_sanitize_workspace( $workspace, $schema );
    $sanitized['updated_at'] = (int) floor( microtime( true ) * 1000 );
    update_user_meta( $user_id, $meta_key, $sanitized );
    $index = get_user_meta( $user_id, '_nce_ai_workspace_index', true ); if ( ! is_array( $index ) ) { $index = array(); }
    $index[ $meta_key ] = time(); arsort( $index );
    foreach ( array_slice( array_keys( $index ), 25 ) as $expired_key ) { delete_user_meta( $user_id, $expired_key ); unset( $index[ $expired_key ] ); }
    update_user_meta( $user_id, '_nce_ai_workspace_index', $index );
    return $sanitized['updated_at'];
}

function nce_ai_editor_request_target() {
    $kind = isset( $_REQUEST['kind'] ) ? sanitize_key( wp_unslash( $_REQUEST['kind'] ) ) : '';
    $type = isset( $_REQUEST['type'] ) ? sanitize_key( wp_unslash( $_REQUEST['type'] ) ) : '';
    $id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
    return nce_ai_editor_target( $kind, $type, $id );
}

function nce_ai_editor_ajax_guard() { check_ajax_referer( 'nce_ai_editor', 'nonce' ); if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Your session expired.', 'nine-code-ultra' ) ), 403 ); } }

add_action( 'wp_ajax_nce_ai_editor_search', 'nce_ai_editor_search' );
function nce_ai_editor_search() {
    nce_ai_editor_ajax_guard(); $kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'post'; $type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : ''; $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : ''; $items = array();
    if ( 'post' === $kind ) {
        $types = nce_ai_editor_post_types(); if ( ! isset( $types[ $type ] ) ) { wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'nine-code-ultra' ) ), 400 ); }
        $posts = get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'auto-draft' ), 'posts_per_page' => 50, 's' => $search, 'orderby' => 'modified', 'order' => 'DESC' ) );
        foreach ( $posts as $post ) { if ( current_user_can( 'edit_post', $post->ID ) ) { $items[] = array( 'id' => $post->ID, 'label' => $post->post_title ?: sprintf( __( '(Untitled #%d)', 'nine-code-ultra' ), $post->ID ), 'meta' => $post->post_status . ' · ' . get_date_from_gmt( $post->post_modified_gmt, get_option( 'date_format' ) ) ); } }
    } else {
        $taxonomies = nce_ai_editor_taxonomies(); if ( ! isset( $taxonomies[ $type ] ) ) { wp_send_json_error( array( 'message' => __( 'Invalid taxonomy.', 'nine-code-ultra' ) ), 400 ); }
        $terms = get_terms( array( 'taxonomy' => $type, 'hide_empty' => false, 'number' => 100, 'search' => $search ) );
        if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { $depth = count( get_ancestors( $term->term_id, $type, 'taxonomy' ) ); $items[] = array( 'id' => $term->term_id, 'label' => ( $depth ? str_repeat( '— ', $depth ) : '' ) . $term->name, 'meta' => sprintf( __( '%d items', 'nine-code-ultra' ), $term->count ), 'parent' => (int) $term->parent ); } }
    }
    wp_send_json_success( array( 'items' => $items ) );
}

add_action( 'wp_ajax_nce_ai_editor_create', 'nce_ai_editor_create' );
function nce_ai_editor_create() {
    nce_ai_editor_ajax_guard();
    $type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
    $object = $type ? get_post_type_object( $type ) : null;
    if ( ! $object || empty( $object->show_ui ) || 'attachment' === $type || ( function_exists( 'is_post_type_viewable' ) && ! is_post_type_viewable( $object ) ) || ! current_user_can( nce_ai_editor_create_capability( $object ) ) ) {
        wp_send_json_error( array( 'message' => __( 'You cannot create this content type.', 'nine-code-ultra' ) ), 403 );
    }
    $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    $post_id = wp_insert_post( wp_slash( array( 'post_type' => $type, 'post_status' => 'draft', 'post_title' => $title, 'post_author' => get_current_user_id() ) ), true );
    if ( is_wp_error( $post_id ) ) { wp_send_json_error( array( 'message' => $post_id->get_error_message() ), 500 ); }
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { wp_send_json_error( array( 'message' => __( 'WordPress could not create an editable draft for this content type.', 'nine-code-ultra' ) ), 500 ); }
    wp_send_json_success( array( 'id' => (int) $post_id, 'type' => $type, 'edit_url' => get_edit_post_link( $post_id, 'raw' ) ) );
}

add_action( 'wp_ajax_nce_ai_editor_load', 'nce_ai_editor_load' );
function nce_ai_editor_load() {
    nce_ai_editor_ajax_guard(); $target = nce_ai_editor_request_target(); if ( is_wp_error( $target ) ) { wp_send_json_error( array( 'message' => $target->get_error_message() ), 403 ); }
    $schema = nce_ai_editor_schema( $target ); $schema['workspace'] = nce_ai_editor_saved_workspace( $target, $schema ); $schema['connectors'] = nce_ai_editor_connector_status();
    wp_send_json_success( $schema );
}

add_action( 'wp_ajax_nce_ai_editor_autosave', 'nce_ai_editor_autosave' );
function nce_ai_editor_autosave() {
    nce_ai_editor_ajax_guard(); $target = nce_ai_editor_request_target(); if ( is_wp_error( $target ) ) { wp_send_json_error( array( 'message' => $target->get_error_message() ), 403 ); }
    $encoded = isset( $_POST['workspace'] ) ? wp_unslash( $_POST['workspace'] ) : ''; if ( strlen( $encoded ) > 1048576 ) { wp_send_json_error( array( 'message' => __( 'This protected draft exceeds the 1 MB workspace limit.', 'nine-code-ultra' ) ), 413 ); }
    $raw = json_decode( $encoded, true ); if ( ! is_array( $raw ) ) { wp_send_json_error( array( 'message' => __( 'Invalid workspace.', 'nine-code-ultra' ) ), 400 ); }
    $schema = nce_ai_editor_schema( $target ); $updated_at = nce_ai_editor_store_workspace( $target, $raw, $schema );
    wp_send_json_success( array( 'saved_at' => gmdate( 'c' ), 'updated_at' => $updated_at ) );
}

function nce_ai_editor_apply_post( $target, $schema, $values, $intent = 'save' ) {
    $publish = 'publish' === $intent;
    $map = nce_ai_editor_field_map( $schema ); $postarr = array( 'ID' => $target['id'] ); $after = array(); $current_status = (string) get_post_status( $target['id'] );
    foreach ( $values as $id => $value ) {
        if ( ! isset( $map[ $id ] ) ) { continue; } $field = $map[ $id ]; $value = nce_ai_editor_decode_value( $value, $field );
        if ( 'core' === $field['storage'] ) {
            if ( 'post_title' === $field['key'] ) { $postarr['post_title'] = sanitize_text_field( $value ); }
            elseif ( 'post_name' === $field['key'] ) { $postarr['post_name'] = sanitize_title( $value ); }
            elseif ( 'post_excerpt' === $field['key'] ) { $postarr['post_excerpt'] = wp_kses_post( $value ); }
            elseif ( 'post_content' === $field['key'] ) { $postarr['post_content'] = current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value ); }
            elseif ( 'post_status' === $field['key'] ) {
                $requested_status = in_array( $value, array( 'draft', 'pending', 'private', 'publish' ), true ) ? $value : 'draft';
                $postarr['post_status'] = ! $publish && 'publish' === $requested_status && 'publish' !== $current_status ? $current_status : $requested_status;
            }
        } else { $after[] = array( $field, $value ); }
    }
    if ( 'draft' === $intent ) { $postarr['post_status'] = 'draft'; }
    if ( $publish ) { $object = get_post_type_object( $target['type'] ); if ( ! $object || ! current_user_can( $object->cap->publish_posts ) ) { return new WP_Error( 'cannot_publish', __( 'You cannot publish this content.', 'nine-code-ultra' ) ); } $postarr['post_status'] = 'publish'; }
    if ( 'publish' === get_post_status( $target['id'] ) && function_exists( 'wp_save_post_revision' ) ) { wp_save_post_revision( $target['id'] ); }
    $result = wp_update_post( wp_slash( $postarr ), true );
    if ( is_wp_error( $result ) ) { return $result; }
    if ( ! $result || ! get_post( $target['id'] ) ) { return new WP_Error( 'post_not_saved', __( 'WordPress did not return a saved custom post type record.', 'nine-code-ultra' ) ); }
    foreach ( $after as $row ) {
        list( $field, $value ) = $row;
        if ( 'taxonomy' === $field['storage'] ) { $term_result = wp_set_object_terms( $target['id'], array_map( 'absint', (array) $value ), $field['key'], false ); if ( is_wp_error( $term_result ) ) { return $term_result; } }
        elseif ( 'featured' === $field['storage'] ) { $value ? set_post_thumbnail( $target['id'], absint( $value ) ) : delete_post_thumbnail( $target['id'] ); }
        elseif ( 'acf' === $field['storage'] && function_exists( 'update_field' ) ) { update_field( $field['acf_key'], $value, $target['id'] ); }
        elseif ( 'meta' === $field['storage'] && 0 !== strpos( $field['key'], '_' ) && current_user_can( 'edit_post_meta', $target['id'], $field['key'] ) ) { update_post_meta( $target['id'], $field['key'], $value ); }
    }
    clean_post_cache( $target['id'] );
    $saved = get_post( $target['id'] );
    if ( ! $saved || $saved->post_type !== $target['type'] ) { return new WP_Error( 'post_not_saved', __( 'The custom post type was not available after saving.', 'nine-code-ultra' ) ); }
    if ( isset( $postarr['post_status'] ) && $saved->post_status !== $postarr['post_status'] ) { return new WP_Error( 'status_not_saved', __( 'WordPress or another plugin changed the requested custom post type status. Reload the item and try again.', 'nine-code-ultra' ) ); }
    return true;
}

function nce_ai_editor_apply_term( $target, $schema, $values ) {
    $map = nce_ai_editor_field_map( $schema ); $core = array(); $after = array(); $context = $target['type'] . '_' . $target['id'];
    foreach ( $values as $id => $value ) {
        if ( ! isset( $map[ $id ] ) ) { continue; } $field = $map[ $id ]; $value = nce_ai_editor_decode_value( $value, $field );
        if ( 'term_core' === $field['storage'] ) { if ( 'name' === $field['key'] ) { $core['name'] = sanitize_text_field( $value ); } elseif ( 'slug' === $field['key'] ) { $core['slug'] = sanitize_title( $value ); } elseif ( 'description' === $field['key'] ) { $core['description'] = wp_kses_post( $value ); } elseif ( 'parent' === $field['key'] ) { $core['parent'] = absint( $value ); } }
        else { $after[] = array( $field, $value ); }
    }
    $result = wp_update_term( $target['id'], $target['type'], $core ); if ( is_wp_error( $result ) ) { return $result; }
    foreach ( $after as $row ) { list( $field, $value ) = $row; if ( 'acf' === $field['storage'] && function_exists( 'update_field' ) ) { update_field( $field['acf_key'], $value, $context ); } elseif ( 'meta' === $field['storage'] && 0 !== strpos( $field['key'], '_' ) && current_user_can( 'edit_term_meta', $target['id'], $field['key'] ) ) { update_term_meta( $target['id'], $field['key'], $value ); } }
    clean_term_cache( $target['id'], $target['type'] ); return true;
}

add_action( 'wp_ajax_nce_ai_editor_commit', 'nce_ai_editor_commit' );
function nce_ai_editor_commit() {
    nce_ai_editor_ajax_guard(); $target = nce_ai_editor_request_target(); if ( is_wp_error( $target ) ) { wp_send_json_error( array( 'message' => $target->get_error_message() ), 403 ); }
    $schema = nce_ai_editor_schema( $target ); $base = isset( $_POST['base_state_sha256'] ) ? sanitize_text_field( wp_unslash( $_POST['base_state_sha256'] ) ) : '';
    if ( ! $base || ! hash_equals( $schema['state_sha256'], $base ) ) { wp_send_json_error( array( 'message' => __( 'This content changed after the workspace opened. Reload it before saving so newer work is not overwritten.', 'nine-code-ultra' ), 'conflict' => true ), 409 ); }
    $encoded_values = isset( $_POST['values'] ) ? wp_unslash( $_POST['values'] ) : ''; if ( strlen( $encoded_values ) > 1048576 ) { wp_send_json_error( array( 'message' => __( 'The content values exceed the 1 MB workspace limit.', 'nine-code-ultra' ) ), 413 ); }
    $values = $encoded_values ? json_decode( $encoded_values, true ) : array(); if ( ! is_array( $values ) ) { wp_send_json_error( array( 'message' => __( 'Invalid field values.', 'nine-code-ultra' ) ), 400 ); }
    $intent = isset( $_POST['intent'] ) ? sanitize_key( wp_unslash( $_POST['intent'] ) ) : ( ! empty( $_POST['publish'] ) ? 'publish' : 'save' );
    if ( ! in_array( $intent, array( 'save', 'draft', 'publish' ), true ) ) { $intent = 'save'; }
    if ( class_exists( 'NCU_Data_Data_Version_Manager' ) ) { NCU_Data_Data_Version_Manager::capture_object( $target['kind'], $target['id'], 'term' === $target['kind'] ? $target['type'] : '', 'Before unified AI Data Manager save', 'manual' ); }
    $result = 'post' === $target['kind'] ? nce_ai_editor_apply_post( $target, $schema, $values, $intent ) : nce_ai_editor_apply_term( $target, $schema, $values );
    if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 ); }
    $fresh = nce_ai_editor_schema( nce_ai_editor_target( $target['kind'], $target['type'], $target['id'] ) );
    $workspace = nce_ai_editor_saved_workspace( $target, $fresh );
    $workspace['values'] = $values;
    if ( empty( $workspace['selected'] ) ) { $workspace['selected'] = array_keys( $values ); }
    nce_ai_editor_store_workspace( $target, $workspace, $fresh );
    wp_send_json_success( array( 'message' => 'publish' === $intent ? __( 'Content published.', 'nine-code-ultra' ) : ( 'draft' === $intent ? __( 'Saved as draft.', 'nine-code-ultra' ) : __( 'Content saved.', 'nine-code-ultra' ) ), 'state_sha256' => $fresh['state_sha256'], 'status' => 'post' === $target['kind'] ? get_post_status( $target['id'] ) : 'saved', 'target' => $fresh['target'] ?? array() ) );
}

function nce_ai_editor_encrypt( $plain ) {
    if ( '' === $plain ) { return ''; }
    $key = hash( 'sha256', wp_salt( 'auth' ), true );
    if ( function_exists( 'sodium_crypto_secretbox' ) ) {
        try { $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ); } catch ( Exception $error ) { return ''; }
        return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) );
    }
    if ( function_exists( 'openssl_encrypt' ) ) {
        try { $iv = random_bytes( 12 ); } catch ( Exception $error ) { return ''; }
        $tag = ''; $cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
        return false === $cipher ? '' : 'o1:' . base64_encode( $iv . $tag . $cipher );
    }
    return '';
}

function nce_ai_editor_decrypt( $cipher ) {
    if ( ! $cipher || false === strpos( $cipher, ':' ) ) { return ''; }
    list( $version, $encoded ) = explode( ':', $cipher, 2 ); $raw = base64_decode( $encoded, true ); $key = hash( 'sha256', wp_salt( 'auth' ), true );
    if ( 's1' === $version && function_exists( 'sodium_crypto_secretbox_open' ) && false !== $raw && strlen( $raw ) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
        $nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ); $opened = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key ); return false === $opened ? '' : $opened;
    }
    if ( 'o1' === $version && function_exists( 'openssl_decrypt' ) && false !== $raw && strlen( $raw ) > 28 ) {
        $iv = substr( $raw, 0, 12 ); $tag = substr( $raw, 12, 16 ); $opened = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag ); return false === $opened ? '' : $opened;
    }
    return '';
}

function nce_ai_editor_provider_registry() {
    return apply_filters( 'nce_ai_editor_providers', array(
        'openai' => array( 'label' => 'ChatGPT / OpenAI', 'model_help' => 'Enter an OpenAI model ID available to your account.' ),
        'anthropic' => array( 'label' => 'Claude / Anthropic', 'model_help' => 'Enter an Anthropic Claude model ID available to your account.' ),
        'gemini' => array( 'label' => 'Google Gemini', 'model_help' => 'Enter a Gemini model ID available to your account.' ),
    ) );
}

function nce_ai_editor_connector_settings() { $saved = get_option( 'nce_ai_editor_connectors', array() ); return is_array( $saved ) ? $saved : array(); }

function nce_ai_editor_connector_status() {
    $settings = nce_ai_editor_connector_settings(); $out = array( 'export' => array( 'label' => __( 'Export file', 'nine-code-ultra' ), 'configured' => true, 'enabled' => true, 'model' => '' ) );
    foreach ( nce_ai_editor_provider_registry() as $id => $provider ) { $row = isset( $settings[ $id ] ) && is_array( $settings[ $id ] ) ? $settings[ $id ] : array(); $out[ $id ] = array( 'label' => $provider['label'], 'configured' => ! empty( $row['key'] ) && ! empty( $row['model'] ), 'enabled' => ! empty( $row['enabled'] ), 'model' => isset( $row['model'] ) ? $row['model'] : '' ); }
    return $out;
}

add_action( 'admin_post_nce_ai_editor_save_connectors', 'nce_ai_editor_save_connectors' );
function nce_ai_editor_save_connectors() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You cannot manage AI connections.', 'nine-code-ultra' ) ); }
    check_admin_referer( 'nce_ai_editor_save_connectors' ); $raw = isset( $_POST['providers'] ) && is_array( $_POST['providers'] ) ? wp_unslash( $_POST['providers'] ) : array(); $saved = nce_ai_editor_connector_settings();
    foreach ( nce_ai_editor_provider_registry() as $id => $provider ) {
        $row = isset( $raw[ $id ] ) && is_array( $raw[ $id ] ) ? $raw[ $id ] : array(); $current = isset( $saved[ $id ] ) && is_array( $saved[ $id ] ) ? $saved[ $id ] : array();
        $next = array( 'enabled' => ! empty( $row['enabled'] ) ? 1 : 0, 'model' => isset( $row['model'] ) ? sanitize_text_field( $row['model'] ) : '' );
        if ( ! empty( $row['clear'] ) ) { $next['key'] = ''; }
        elseif ( ! empty( $row['api_key'] ) ) { $next['key'] = nce_ai_editor_encrypt( sanitize_text_field( $row['api_key'] ) ); if ( ! $next['key'] ) { wp_die( esc_html__( 'This server cannot securely encrypt connector keys. No new key was stored.', 'nine-code-ultra' ) ); } }
        else { $next['key'] = isset( $current['key'] ) ? $current['key'] : ''; }
        $saved[ $id ] = $next;
    }
    update_option( 'nce_ai_editor_connectors', $saved, false );
    wp_safe_redirect( admin_url( 'admin.php?page=nine-code-ultra&connectors_saved=1#nce-ai-connections' ) ); exit;
}

function nce_ai_editor_extract_json( $text ) {
    $text = trim( (string) $text ); if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*\})\s*```/i', $text, $match ) ) { $text = $match[1]; }
    $data = json_decode( $text, true ); if ( is_array( $data ) ) { return $data; }
    $start = strpos( $text, '{' ); $end = strrpos( $text, '}' ); return false !== $start && false !== $end && $end > $start ? json_decode( substr( $text, $start, $end - $start + 1 ), true ) : null;
}

function nce_ai_editor_contract_target_matches( $candidate, $target ) {
    return is_array( $candidate )
        && isset( $candidate['kind'], $candidate['type'], $candidate['id'] )
        && $candidate['kind'] === $target['kind']
        && $candidate['type'] === $target['type']
        && (int) $candidate['id'] === (int) $target['id'];
}

function nce_ai_editor_remote_run( $provider, $model, $key, $prompt ) {
    $headers = array( 'Content-Type' => 'application/json' ); $url = ''; $body = array();
    if ( 'openai' === $provider ) { $url = 'https://api.openai.com/v1/chat/completions'; $headers['Authorization'] = 'Bearer ' . $key; $body = array( 'model' => $model, 'messages' => array( array( 'role' => 'system', 'content' => 'You are the 9Core Unified AI Data Manager. Return valid JSON only and obey the supplied data-repair contract.' ), array( 'role' => 'user', 'content' => $prompt ) ), 'response_format' => array( 'type' => 'json_object' ) ); }
    elseif ( 'anthropic' === $provider ) { $url = 'https://api.anthropic.com/v1/messages'; $headers['x-api-key'] = $key; $headers['anthropic-version'] = '2023-06-01'; $body = array( 'model' => $model, 'max_tokens' => 8192, 'system' => 'You are the 9Core Unified AI Data Manager. Return valid JSON only and obey the supplied data-repair contract.', 'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ) ); }
    elseif ( 'gemini' === $provider ) { $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent'; $headers['x-goog-api-key'] = $key; $body = array( 'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ), 'generationConfig' => array( 'responseMimeType' => 'application/json' ) ); }
    else { return new WP_Error( 'invalid_provider', __( 'Unknown AI provider.', 'nine-code-ultra' ) ); }
    $response = wp_safe_remote_post( $url, array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'timeout' => 120, 'redirection' => 0, 'data_format' => 'body' ) );
    if ( is_wp_error( $response ) ) { return $response; } $code = wp_remote_retrieve_response_code( $response ); $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code < 200 || $code >= 300 ) { return new WP_Error( 'provider_error', sprintf( __( 'The provider returned HTTP %d.', 'nine-code-ultra' ), $code ) ); }
    $text = '';
    if ( 'openai' === $provider ) { $text = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : ''; }
    elseif ( 'anthropic' === $provider ) { $text = isset( $data['content'][0]['text'] ) ? $data['content'][0]['text'] : ''; }
    elseif ( 'gemini' === $provider ) { $text = isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ? $data['candidates'][0]['content']['parts'][0]['text'] : ''; }
    $result = nce_ai_editor_extract_json( $text ); return is_array( $result ) ? $result : new WP_Error( 'invalid_provider_response', __( 'The provider did not return a valid 9 Code JSON result.', 'nine-code-ultra' ) );
}

add_action( 'wp_ajax_nce_ai_editor_run', 'nce_ai_editor_run' );
function nce_ai_editor_run() {
    nce_ai_editor_ajax_guard(); $target = nce_ai_editor_request_target(); if ( is_wp_error( $target ) ) { wp_send_json_error( array( 'message' => $target->get_error_message() ), 403 ); }
    $provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : ''; $settings = nce_ai_editor_connector_settings(); $row = isset( $settings[ $provider ] ) ? $settings[ $provider ] : array();
    if ( empty( $row['enabled'] ) || empty( $row['model'] ) || empty( $row['key'] ) ) { wp_send_json_error( array( 'message' => __( 'Configure and enable this connector first.', 'nine-code-ultra' ) ), 400 ); }
    $payload = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : array(); if ( ! is_array( $payload ) || strlen( wp_json_encode( $payload ) ) > 1048576 ) { wp_send_json_error( array( 'message' => __( 'The AI package is invalid or exceeds 1 MB.', 'nine-code-ultra' ) ), 400 ); }
    $schema = nce_ai_editor_schema( $target ); if ( ! nce_ai_editor_contract_target_matches( isset( $payload['target'] ) ? $payload['target'] : array(), $target ) || empty( $payload['base_state_sha256'] ) || ! hash_equals( $schema['state_sha256'], sanitize_text_field( $payload['base_state_sha256'] ) ) ) { wp_send_json_error( array( 'message' => __( 'The AI package is stale or targets different content.', 'nine-code-ultra' ) ), 409 ); }
    $prompt = "Read this 9 Code package and implement its selected-field instructions. Return only the result contract described inside.\n\n" . wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    $key = nce_ai_editor_decrypt( $row['key'] ); if ( ! $key ) { wp_send_json_error( array( 'message' => __( 'The stored connector key could not be decrypted. Save it again.', 'nine-code-ultra' ) ), 400 ); }
    $result = nce_ai_editor_remote_run( $provider, $row['model'], $key, $prompt ); if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 ); }
    if ( '9-code-ai-editor-result' !== ( isset( $result['format'] ) ? $result['format'] : '' ) || 1 !== (int) ( isset( $result['schema'] ) ? $result['schema'] : 0 ) || empty( $result['changes'] ) || ! is_array( $result['changes'] ) || ! nce_ai_editor_contract_target_matches( isset( $result['target'] ) ? $result['target'] : array(), $target ) || empty( $result['base_state_sha256'] ) || ! hash_equals( $schema['state_sha256'], sanitize_text_field( $result['base_state_sha256'] ) ) ) { wp_send_json_error( array( 'message' => __( 'The provider response did not prove its target and saved content state.', 'nine-code-ultra' ) ), 422 ); }
    $map = nce_ai_editor_field_map( $schema ); $allowed = array(); $declared = array(); $invalid_declared = false; $has_declared_selection = array_key_exists( 'selected_field_ids', $payload ) && is_array( $payload['selected_field_ids'] );
    foreach ( (array) ( isset( $payload['selected_field_ids'] ) ? $payload['selected_field_ids'] : array() ) as $field_id ) { $field_id = sanitize_key( $field_id ); if ( $field_id && isset( $map[ $field_id ] ) ) { $declared[ $field_id ] = true; } elseif ( $has_declared_selection ) { $invalid_declared = true; } }
    foreach ( (array) ( isset( $payload['fields'] ) ? $payload['fields'] : array() ) as $field ) { if ( ! empty( $field['id'] ) ) { $field_id = sanitize_key( $field['id'] ); if ( isset( $map[ $field_id ] ) ) { $allowed[ $field_id ] = true; } } }
    if ( $has_declared_selection && ( $invalid_declared || array_diff_key( $allowed, $declared ) || array_diff_key( $declared, $allowed ) ) ) { wp_send_json_error( array( 'message' => __( 'The AI package selected-field list does not match its field records.', 'nine-code-ultra' ) ), 422 ); }
    $changes = array(); foreach ( $result['changes'] as $id => $value ) { $id = sanitize_key( $id ); if ( isset( $map[ $id ], $allowed[ $id ] ) ) { $changes[ $id ] = nce_ai_editor_decode_value( $value, $map[ $id ] ); } }
    if ( ! $changes ) { wp_send_json_error( array( 'message' => __( 'The provider did not return changes for any selected field.', 'nine-code-ultra' ) ), 422 ); }
    wp_send_json_success( array( 'changes' => $changes, 'summary' => isset( $result['summary'] ) ? sanitize_textarea_field( $result['summary'] ) : '', 'provider' => $provider, 'model' => $row['model'] ) );
}

function nce_ai_editor_is_screen( $hook = '' ) {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( in_array( $page, array( 'nine-code-ultra', 'nine-code-ultra-data-engine' ), true ) ) { return true; }
    if ( $hook && ( false !== strpos( (string) $hook, 'nine-code-ultra-data-engine' ) || false !== strpos( (string) $hook, 'toplevel_page_nine-code-ultra' ) ) ) { return true; }
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    return $screen && ( false !== strpos( (string) $screen->id, 'nine-code-ultra-data-engine' ) || false !== strpos( (string) $screen->id, 'toplevel_page_nine-code-ultra' ) );
}

add_action( 'admin_enqueue_scripts', 'nce_ai_editor_assets', 20 );
function nce_ai_editor_assets( $hook ) {
    if ( ! nce_ai_editor_is_screen( $hook ) ) { return; }
    wp_enqueue_style( 'nce-ai-editor', NCU_CORE_URL . 'assets/data-engine/ai-data-workspace.css', array(), NCU_CORE_VERSION );
    wp_enqueue_script( 'nce-ai-editor', NCU_CORE_URL . 'assets/data-engine/ai-data-workspace.js', array(), NCU_CORE_VERSION, true );
    wp_enqueue_media();
    $workspace_scope = substr( hash_hmac( 'sha256', (string) get_current_user_id(), wp_salt( 'auth' ) ), 0, 20 );
    wp_localize_script( 'nce-ai-editor', 'nceAiEditor', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'nce_ai_editor' ), 'workspaceScope' => $workspace_scope, 'pageSlug' => 'nine-code-ultra', 'postTypes' => array_values( nce_ai_editor_post_types() ), 'taxonomies' => array_values( nce_ai_editor_taxonomies() ), 'excelExportBase' => admin_url( 'admin-post.php?action=ninecode_acf_export_collection&kind=post&format=xlsx&_wpnonce=' . wp_create_nonce( 'ninecode_export_collection' ) ), 'maxPackageBytes' => 1048576, 'strings' => array( 'loading' => __( 'Loading…', 'nine-code-ultra' ), 'saved' => __( 'Workspace saved', 'nine-code-ultra' ), 'error' => __( 'Something went wrong.', 'nine-code-ultra' ), 'confirmPublish' => __( 'Publish this record?', 'nine-code-ultra' ), 'confirmDraft' => __( 'Save this record as a draft?', 'nine-code-ultra' ) ) ) );
}

function nce_ai_editor_connections_panel() { $settings = nce_ai_editor_connector_settings(); ?>
    <section class="nce-panel" id="nce-ai-connections"><h2><?php esc_html_e( 'AI connectors', 'nine-code-ultra' ); ?></h2><p><?php esc_html_e( 'Connect ChatGPT, Claude or Google Gemini for direct runs. Keys are encrypted with this WordPress installation’s authentication salt and never exported to AI packages.', 'nine-code-ultra' ); ?></p><?php if ( current_user_can( 'manage_options' ) ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="nce_ai_editor_save_connectors"><?php wp_nonce_field( 'nce_ai_editor_save_connectors' ); ?><div class="nce-ai-connectors"><?php foreach ( nce_ai_editor_provider_registry() as $id => $provider ) : $row = isset( $settings[ $id ] ) ? $settings[ $id ] : array(); ?><details class="nce-ai-connector"><summary><b><?php echo esc_html( $provider['label'] ); ?></b><span><?php echo ! empty( $row['key'] ) && ! empty( $row['model'] ) ? esc_html__( 'Configured', 'nine-code-ultra' ) : esc_html__( 'Not configured', 'nine-code-ultra' ); ?></span></summary><div class="nce-grid"><label class="nce-check"><input type="checkbox" name="providers[<?php echo esc_attr( $id ); ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?>><span><?php esc_html_e( 'Enable direct use', 'nine-code-ultra' ); ?></span></label><label class="nce-field"><span><?php esc_html_e( 'Model ID', 'nine-code-ultra' ); ?></span><input name="providers[<?php echo esc_attr( $id ); ?>][model]" value="<?php echo esc_attr( isset( $row['model'] ) ? $row['model'] : '' ); ?>" autocomplete="off"><small><?php echo esc_html( $provider['model_help'] ); ?></small></label><label class="nce-field"><span><?php esc_html_e( 'API key', 'nine-code-ultra' ); ?></span><input type="password" name="providers[<?php echo esc_attr( $id ); ?>][api_key]" value="" placeholder="<?php echo ! empty( $row['key'] ) ? esc_attr__( 'Stored securely — enter only to replace', 'nine-code-ultra' ) : ''; ?>" autocomplete="new-password"></label><label class="nce-check"><input type="checkbox" name="providers[<?php echo esc_attr( $id ); ?>][clear]" value="1"><span><?php esc_html_e( 'Remove stored key', 'nine-code-ultra' ); ?></span></label></div></details><?php endforeach; ?></div><p><button class="button button-primary"><?php esc_html_e( 'Save connections', 'nine-code-ultra' ); ?></button></p></form><?php else : ?><p><?php esc_html_e( 'A site administrator manages connector keys.', 'nine-code-ultra' ); ?></p><?php endif; ?></section>
<?php }

function nce_ai_editor_page() {
    if ( ! current_user_can( 'manage_ninecode_data' ) ) { return; }
    ?>
    <div class="nce-ai-editor nce-unified-data-manager" data-nce-ai-editor>
        <section class="nce-ai-editor__hero"><div class="nce-ai-editor__hero-identity"><div><p>9CORE 13 • UNIFIED DATA ENGINE</p><h2><?php esc_html_e( 'AI Data Manager', 'nine-code-ultra' ); ?></h2><span><?php esc_html_e( 'Choose any front-end-viewable post type. Edit only its data, images, taxonomies and safe fields; use AI when useful; save draft or publish; move previous/next; round-trip the same data through Excel.', 'nine-code-ultra' ); ?></span></div></div><div><div class="nce-ai-save-state" data-nce-save-state aria-live="polite"><?php esc_html_e( 'Choose content to begin', 'nine-code-ultra' ); ?></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-responses' ) ); ?>"><?php esc_html_e( 'Responses', 'nine-code-ultra' ); ?></a></div></section>
        <div class="nce-ai-editor__layout">
            <aside class="nce-ai-targets">
                <section class="nce-ai-drawer" open><h3><?php esc_html_e( '1. Choose data', 'nine-code-ultra' ); ?></h3><label><span><?php esc_html_e( 'Front-end content type', 'nine-code-ultra' ); ?></span><select data-nce-type></select></label><label><span><?php esc_html_e( 'Find record', 'nine-code-ultra' ); ?></span><input type="search" data-nce-search placeholder="<?php esc_attr_e( 'Search title…', 'nine-code-ultra' ); ?>"></label><div class="nce-ai-results" data-nce-results></div><button type="button" class="button" data-nce-new-target hidden><?php esc_html_e( 'Create new draft', 'nine-code-ultra' ); ?></button></section>
                <details class="nce-ai-drawer" open><summary><?php esc_html_e( 'Excel round-trip', 'nine-code-ultra' ); ?></summary><p><?php esc_html_e( 'Export the selected post type to Excel, edit the white data cells, then import it here. Record identity, field mapping, conflict checks, versions and rollback are protected automatically.', 'nine-code-ultra' ); ?></p><button type="button" class="button button-primary" data-nce-excel-export><?php esc_html_e( 'Export this type to Excel', 'nine-code-ultra' ); ?></button><form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nce-data-import" data-ninecode-import-form><input type="hidden" name="action" value="ninecode_acf_import"><?php wp_nonce_field( 'ninecode_acf_import' ); ?><input type="hidden" name="create_missing_terms" value="1"><input type="hidden" name="import_terms" value="1"><label><span><?php esc_html_e( 'Import edited Excel / CSV / JSON', 'nine-code-ultra' ); ?></span><input type="file" name="data_file" accept=".xlsx,.json,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/json,text/csv" required></label><button class="button" name="import_mode" value="preview"><?php esc_html_e( 'Preview import', 'nine-code-ultra' ); ?></button></form></details>
                <details class="nce-ai-drawer"><summary><?php esc_html_e( 'AI connections', 'nine-code-ultra' ); ?></summary><div data-nce-connector-picker><p><?php esc_html_e( 'Load a record to see available connections.', 'nine-code-ultra' ); ?></p></div></details>
                <details class="nce-ai-drawer"><summary><?php esc_html_e( 'Import AI result', 'nine-code-ultra' ); ?></summary><p><?php esc_html_e( 'Import a 9 Code AI result into this same data workspace for review. Nothing publishes automatically.', 'nine-code-ultra' ); ?></p><input type="file" accept="application/json,.json" data-nce-import></details>
            </aside>
            <main class="nce-ai-workspace">
                <div class="nce-ai-empty" data-nce-empty><h3><?php esc_html_e( 'Select a post, page or custom post type with a public front-end view', 'nine-code-ultra' ); ?></h3><p><?php esc_html_e( 'Only its data opens here: core text, images, ACF fields, safe plugin fields and taxonomy assignments.', 'nine-code-ultra' ); ?></p></div>
                <div class="nce-ai-loaded" data-nce-loaded hidden>
                    <header class="nce-ai-target-header"><div><p data-nce-target-type></p><h2 data-nce-target-title></h2><span data-nce-target-goal></span></div><div class="nce-data-header-actions"><button type="button" class="button" data-nce-previous><?php esc_html_e( '← Previous', 'nine-code-ultra' ); ?></button><button type="button" class="button" data-nce-next><?php esc_html_e( 'Next →', 'nine-code-ultra' ); ?></button><a class="button" data-nce-view-site target="ncu-data-preview" rel="noopener"><?php esc_html_e( 'View', 'nine-code-ultra' ); ?></a></div></header>
                    <div class="nce-ai-mode-switch" role="tablist" aria-label="<?php esc_attr_e( 'Data mode', 'nine-code-ultra' ); ?>"><button type="button" class="is-active" role="tab" aria-selected="true" data-nce-mode="content"><?php esc_html_e( 'Data', 'nine-code-ultra' ); ?></button><button type="button" role="tab" aria-selected="false" data-nce-mode="ai"><?php esc_html_e( 'AI Assist', 'nine-code-ultra' ); ?></button></div>
                    <section data-nce-ai-mode hidden>
                        <div class="nce-ai-global"><label class="nce-ai-select-all"><input type="checkbox" data-nce-select-all checked><span><?php esc_html_e( 'Select all fields', 'nine-code-ultra' ); ?></span></label><button type="button" class="button" data-nce-global-prompt-toggle><?php esc_html_e( 'Global prompt', 'nine-code-ultra' ); ?></button><button type="button" class="button" data-nce-export><?php esc_html_e( 'Export AI package', 'nine-code-ultra' ); ?></button><button type="button" class="button button-primary" data-nce-run><?php esc_html_e( 'Run selected AI', 'nine-code-ultra' ); ?></button></div>
                        <div class="nce-ai-global-prompt" data-nce-global-prompt-wrap hidden><label><span><?php esc_html_e( 'Instruction for the whole data job', 'nine-code-ultra' ); ?></span><textarea rows="5" data-nce-global-prompt placeholder="<?php esc_attr_e( 'Example: Complete missing fields without changing verified names, dates or prices.', 'nine-code-ultra' ); ?>"></textarea></label></div><div data-nce-ai-groups></div>
                    </section>
                    <section data-nce-content-mode><nav class="nce-content-tabs" data-nce-content-tabs></nav><div data-nce-content-fields></div></section>
                    <footer class="nce-ai-actions"><button type="button" class="button" data-nce-save-draft><?php esc_html_e( 'Save Draft', 'nine-code-ultra' ); ?></button><button type="button" class="button" data-nce-save-content><?php esc_html_e( 'Save Changes', 'nine-code-ultra' ); ?></button><button type="button" class="button button-primary" data-nce-publish><?php esc_html_e( 'Publish', 'nine-code-ultra' ); ?></button></footer>
                </div>
            </main>
        </div>
    </div>
    <?php nce_ai_editor_connections_panel(); ?>
    <details class="nce-system-tools"><summary><?php esc_html_e( 'System tools', 'nine-code-ultra' ); ?></summary><p><a href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-menu-ui' ) ); ?>">Menu & UI</a> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-design' ) ); ?>">Design</a> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-header-footer' ) ); ?>">Header & Footer</a> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-backup' ) ); ?>">Backup</a> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-health' ) ); ?>">Health</a></p></details>
    <?php
}

