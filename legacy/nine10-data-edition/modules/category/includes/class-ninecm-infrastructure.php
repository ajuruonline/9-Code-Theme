<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Discovers the structural controls WordPress exposes for a content type.
 * This class deliberately avoids guessing arbitrary post-meta relationships.
 */
class NineCM_Infrastructure {
    public static function editable_post_types() {
        $out = array();
        foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pto ) {
            if ( in_array( $pto->name, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) { continue; }
            if ( empty( $pto->cap->edit_posts ) || ! current_user_can( $pto->cap->edit_posts ) ) { continue; }
            $out[ $pto->name ] = self::post_type_descriptor( $pto );
        }
        uasort( $out, function( $a, $b ) {
            if ( 'page' === $a['name'] ) { return -1; }
            if ( 'page' === $b['name'] ) { return 1; }
            return strcasecmp( $a['label'], $b['label'] );
        } );
        return array_values( $out );
    }

    public static function post_type_descriptor( $pto ) {
        if ( is_string( $pto ) ) { $pto = get_post_type_object( $pto ); }
        if ( ! $pto ) { return array(); }
        $taxonomies = array();
        foreach ( get_object_taxonomies( $pto->name, 'objects' ) as $tax ) {
            if ( empty( $tax->show_ui ) ) { continue; }
            $taxonomies[] = self::taxonomy_descriptor( $tax, $pto->name );
        }
        usort( $taxonomies, function( $a, $b ) { return strcasecmp( $a['label'], $b['label'] ); } );
        $create_cap = ! empty( $pto->cap->create_posts ) ? $pto->cap->create_posts : ( $pto->cap->edit_posts ?? 'edit_posts' );
        // Shell creation is intentionally conservative. Some plugins expose technical
        // show_ui post types (orders, logs, templates, sync records) that are editable
        // but should never receive a generic blank planning shell. Requiring native
        // title support is a safe default; integrations can override deliberately.
        $can_create_shell = $create_cap && current_user_can( $create_cap ) && post_type_supports( $pto->name, 'title' );
        $can_create_shell = (bool) apply_filters( 'ninecm_can_create_shell_for_post_type', $can_create_shell, $pto );
        return array(
            'name'            => $pto->name,
            'label'           => $pto->labels->name,
            'singular'        => $pto->labels->singular_name,
            'hierarchical'    => ! empty( $pto->hierarchical ),
            'canCreate'       => $can_create_shell,
            'canEditOthers'   => ! empty( $pto->cap->edit_others_posts ) && current_user_can( $pto->cap->edit_others_posts ),
            'supports'        => array(
                'title'       => post_type_supports( $pto->name, 'title' ),
                'excerpt'     => post_type_supports( $pto->name, 'excerpt' ),
                'thumbnail'   => post_type_supports( $pto->name, 'thumbnail' ),
                'author'      => post_type_supports( $pto->name, 'author' ),
                'pageAttrs'   => post_type_supports( $pto->name, 'page-attributes' ),
            ),
            // post_parent is a native structural field for hierarchical post types.
            'supportsParent'  => ! empty( $pto->hierarchical ),
            'supportsOrder'   => ! empty( $pto->hierarchical ) || post_type_supports( $pto->name, 'page-attributes' ),
            'taxonomies'      => $taxonomies,
            'providers'       => array_values( array_map( function( $provider ) {
                return array( 'key' => $provider['key'], 'label' => $provider['label'], 'type' => $provider['type'], 'hasOptions' => ! empty( $provider['options_callback'] ) );
            }, self::relationship_providers( $pto->name, 0 ) ) ),
        );
    }

    public static function taxonomy_descriptor( $tax, $post_type = '' ) {
        if ( is_string( $tax ) ) { $tax = get_taxonomy( $tax ); }
        if ( ! $tax ) { return array(); }
        return array(
            'name'         => $tax->name,
            'label'        => $tax->labels->name,
            'singular'     => $tax->labels->singular_name,
            'hierarchical' => ! empty( $tax->hierarchical ),
            'public'       => ! empty( $tax->public ),
            'objectTypes'  => array_values( (array) $tax->object_type ),
            'attached'     => $post_type ? is_object_in_taxonomy( $post_type, $tax->name ) : true,
            'canAssign'    => ! empty( $tax->cap->assign_terms ) && current_user_can( $tax->cap->assign_terms ),
            'canEdit'      => ! empty( $tax->cap->edit_terms ) && current_user_can( $tax->cap->edit_terms ),
            'canManage'    => ! empty( $tax->cap->manage_terms ) && current_user_can( $tax->cap->manage_terms ),
            'canDelete'    => ! empty( $tax->cap->delete_terms ) && current_user_can( $tax->cap->delete_terms ),
        );
    }

    public static function usable_taxonomies( $post_type = '' ) {
        $out = array();
        $objects = $post_type ? get_object_taxonomies( $post_type, 'objects' ) : get_taxonomies( array( 'show_ui' => true ), 'objects' );
        foreach ( $objects as $tax ) {
            if ( empty( $tax->show_ui ) ) { continue; }
            $d = self::taxonomy_descriptor( $tax, $post_type );
            if ( ! $d['canAssign'] && ! $d['canEdit'] && ! $d['canManage'] ) { continue; }
            $out[] = $d;
        }
        usort( $out, function( $a, $b ) {
            if ( 'category' === $a['name'] ) { return -1; }
            if ( 'category' === $b['name'] ) { return 1; }
            if ( 'post_tag' === $a['name'] ) { return -1; }
            if ( 'post_tag' === $b['name'] ) { return 1; }
            return strcasecmp( $a['label'], $b['label'] );
        } );
        return $out;
    }

    public static function author_rows( $post_type, $search = '', $limit = 40 ) {
        $pto = get_post_type_object( $post_type );
        if ( ! $pto || empty( $pto->cap->edit_posts ) || ! current_user_can( $pto->cap->edit_posts ) ) { return array(); }
        $args = array(
            'number'  => max( 1, min( 100, absint( $limit ) ) ),
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => array( 'ID', 'display_name', 'user_login' ),
        );
        $search = trim( (string) $search );
        if ( $search ) { $args['search'] = '*' . sanitize_text_field( $search ) . '*'; $args['search_columns'] = array( 'user_login', 'user_nicename', 'user_email', 'display_name' ); }
        // Prefer users who can edit the selected content type. On unusual custom-role
        // setups WP_User_Query may not support the mapped capability cleanly, so the
        // result is filtered again with user_can below.
        if ( ! empty( $pto->cap->edit_posts ) ) { $args['capability'] = $pto->cap->edit_posts; }
        $users = get_users( $args );
        $out = array();
        foreach ( $users as $u ) {
            if ( ! user_can( $u, $pto->cap->edit_posts ) ) { continue; }
            $out[] = array(
                'id'    => (int) $u->ID,
                'name'  => $u->display_name ?: $u->user_login,
                'login' => $u->user_login,
            );
        }
        return $out;
    }

    public static function parent_rows( $post_type, $search = '', $exclude = 0, $limit = 50 ) {
        $pto = get_post_type_object( $post_type );
        if ( ! $pto || empty( $pto->hierarchical ) || empty( $pto->cap->edit_posts ) || ! current_user_can( $pto->cap->edit_posts ) ) { return array(); }
        $statuses = array( 'publish', 'draft', 'pending', 'future' );
        if ( ! empty( $pto->cap->read_private_posts ) && current_user_can( $pto->cap->read_private_posts ) ) { $statuses[] = 'private'; }
        $args = array(
            'post_type'              => $post_type,
            'post_status'            => $statuses,
            'posts_per_page'         => max( 1, min( 100, absint( $limit ) ) ),
            'orderby'                => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
            'order'                  => 'ASC',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        if ( $search ) { $args['s'] = sanitize_text_field( $search ); }
        if ( $exclude ) { $args['post__not_in'] = array( absint( $exclude ) ); }
        if ( empty( $pto->cap->edit_others_posts ) || ! current_user_can( $pto->cap->edit_others_posts ) ) { $args['author'] = get_current_user_id(); }
        $q = new WP_Query( $args );
        $out = array();
        foreach ( $q->posts as $p ) {
            if ( ! current_user_can( 'edit_post', $p->ID ) ) { continue; }
            $out[] = array( 'id' => (int) $p->ID, 'title' => get_the_title( $p ), 'status' => $p->post_status, 'parent' => (int) $p->post_parent, 'order' => (int) $p->menu_order );
        }
        return $out;
    }

    /**
     * Extension point for non-native relationships (for example a Module -> Course
     * relationship stored by an LMS plugin). Providers must be explicit; this plugin
     * never guesses arbitrary meta keys.
     *
     * Provider schema:
     * key, label, type (select|number|text), get_callback, update_callback,
     * options_callback (optional for select), validate_callback (optional),
     * permission_callback (optional). A select options callback may return
     * [{value,label}] rows or an associative value => label map.
     */
    public static function relationship_providers( $post_type, $post_id = 0 ) {
        $providers = apply_filters( 'ninecm_relationship_providers', array(), sanitize_key( $post_type ), absint( $post_id ) );
        if ( ! is_array( $providers ) ) { return array(); }
        $out = array();
        foreach ( $providers as $provider ) {
            if ( ! is_array( $provider ) || empty( $provider['key'] ) || empty( $provider['label'] ) || empty( $provider['get_callback'] ) || empty( $provider['update_callback'] ) ) { continue; }
            if ( ! is_callable( $provider['get_callback'] ) || ! is_callable( $provider['update_callback'] ) ) { continue; }
            $key = sanitize_key( $provider['key'] );
            if ( ! $key ) { continue; }
            $allowed = true;
            if ( ! empty( $provider['permission_callback'] ) && is_callable( $provider['permission_callback'] ) ) {
                try {
                    $allowed = (bool) call_user_func( $provider['permission_callback'], $post_type, $post_id );
                } catch ( Throwable $e ) {
                    $allowed = false;
                }
            }
            if ( ! $allowed ) { continue; }
            $type = sanitize_key( $provider['type'] ?? 'select' );
            if ( ! in_array( $type, array( 'select', 'number', 'text' ), true ) ) { $type = 'select'; }
            $out[ $key ] = array(
                'key'              => $key,
                'label'            => sanitize_text_field( $provider['label'] ),
                'type'             => $type,
                'get_callback'     => $provider['get_callback'],
                'update_callback'  => $provider['update_callback'],
                'options_callback' => ! empty( $provider['options_callback'] ) && is_callable( $provider['options_callback'] ) ? $provider['options_callback'] : null,
                'validate_callback'=> ! empty( $provider['validate_callback'] ) && is_callable( $provider['validate_callback'] ) ? $provider['validate_callback'] : null,
            );
        }
        return $out;
    }
    public static function term_in_archived_branch( $term_id, $taxonomy ) {
        $term_id = absint( $term_id );
        $guard = 0;
        while ( $term_id && $guard++ < 60 ) {
            if ( get_term_meta( $term_id, 'ninecm_archived', true ) ) { return true; }
            $term = get_term( $term_id, $taxonomy );
            if ( ! $term || is_wp_error( $term ) ) { break; }
            $term_id = (int) $term->parent;
        }
        return false;
    }

    public static function planning_payload( $post ) {
        if ( is_numeric( $post ) ) { $post = get_post( absint( $post ) ); }
        if ( ! $post ) { return array(); }
        $descriptor = self::post_type_descriptor( get_post_type_object( $post->post_type ) );
        $tax_data = array();
        foreach ( self::usable_taxonomies( $post->post_type ) as $tax ) {
            $assigned = wp_get_object_terms( $post->ID, $tax['name'], array( 'orderby' => 'name', 'order' => 'ASC' ) );
            $rows = array();
            if ( ! is_wp_error( $assigned ) ) {
                foreach ( $assigned as $term ) {
                    $rows[] = array(
                        'id'       => (int) $term->term_id,
                        'name'     => $term->name,
                        'path'     => NineCM_REST::term_path( $term, $tax['name'] ),
                        'archived' => self::term_in_archived_branch( $term->term_id, $tax['name'] ),
                    );
                }
            }
            $tax['terms'] = $rows;
            $tax_data[] = $tax;
        }
        $thumb_id = (int) get_post_thumbnail_id( $post->ID );
        $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
        $author = get_userdata( (int) $post->post_author );
        $parent = $post->post_parent ? get_post( $post->post_parent ) : null;
        $providers = array();
        foreach ( self::relationship_providers( $post->post_type, $post->ID ) as $key => $provider ) {
            try {
                $value = call_user_func( $provider['get_callback'], $post->ID, $post->post_type );
            } catch ( Throwable $e ) {
                continue;
            }
            $providers[] = array( 'key' => $key, 'label' => $provider['label'], 'type' => $provider['type'], 'value' => $value, 'hasOptions' => ! empty( $provider['options_callback'] ) );
        }
        return array(
            'id'            => (int) $post->ID,
            'title'         => get_the_title( $post ),
            'type'          => $post->post_type,
            'typeLabel'     => $descriptor['singular'] ?? $post->post_type,
            'status'        => $post->post_status,
            'excerpt'       => $post->post_excerpt,
            'author'        => array( 'id' => (int) $post->post_author, 'name' => $author ? $author->display_name : '' ),
            'parent'        => array( 'id' => (int) $post->post_parent, 'title' => $parent ? get_the_title( $parent ) : '' ),
            'menuOrder'     => (int) $post->menu_order,
            'featuredMedia' => array( 'id' => $thumb_id, 'url' => $thumb_url ?: '' ),
            'supports'      => $descriptor['supports'] ?? array(),
            'supportsParent'=> ! empty( $descriptor['supportsParent'] ),
            'supportsOrder' => ! empty( $descriptor['supportsOrder'] ),
            'taxonomies'    => $tax_data,
            'providers'     => $providers,
            'view'          => 'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post ),
            'edit'          => get_edit_post_link( $post->ID, 'raw' ),
        );
    }

}
