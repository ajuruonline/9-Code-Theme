<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NineCM_Export {
    public function __construct() {
        add_action( 'admin_post_ninecm_export', array( $this, 'download' ) );
    }

    private function fail( $message, $status = 403 ) {
        wp_die( esc_html( $message ), esc_html__( '9 Category Manager Export', 'nine-code-data' ), array( 'response' => (int) $status ) );
    }

    private function taxonomy( $name ) {
        $name = sanitize_key( $name ?: 'category' );
        $tax = get_taxonomy( $name );
        if ( ! $tax || empty( $tax->show_ui ) ) { $this->fail( 'Invalid taxonomy/tag system.', 400 ); }

        $allowed = false;
        foreach ( array( 'manage_terms', 'edit_terms', 'assign_terms' ) as $cap_name ) {
            if ( ! empty( $tax->cap->{$cap_name} ) && current_user_can( $tax->cap->{$cap_name} ) ) { $allowed = true; break; }
        }
        if ( ! $allowed ) { $this->fail( 'You do not have permission to export this taxonomy.' ); }
        return $tax;
    }

    private function post_type( $name ) {
        $name = sanitize_key( $name ?: 'page' );
        $pto = get_post_type_object( $name );
        if ( ! $pto || empty( $pto->show_ui ) || empty( $pto->cap->edit_posts ) || ! current_user_can( $pto->cap->edit_posts ) ) {
            $this->fail( 'Invalid or unavailable content type.', 400 );
        }
        return $pto;
    }

    private function editable_statuses( $pto ) {
        $statuses = array( 'draft', 'pending' );
        if ( ! empty( $pto->cap->edit_published_posts ) && current_user_can( $pto->cap->edit_published_posts ) ) {
            $statuses[] = 'publish'; $statuses[] = 'future';
        }
        if ( ! empty( $pto->cap->read_private_posts ) && current_user_can( $pto->cap->read_private_posts ) ) { $statuses[] = 'private'; }
        return array_values( array_unique( $statuses ) );
    }

    private function csv_headers( $filename ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        // UTF-8 BOM improves Excel compatibility for non-ASCII category names.
        echo "\xEF\xBB\xBF";
    }

    private function json_headers( $filename ) {
        nocache_headers();
        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'X-Content-Type-Options: nosniff' );
    }

    /**
     * Prevent spreadsheet formula execution when a CSV is opened in Excel/Sheets.
     * Numeric values remain numeric; potentially executable strings are prefixed with an apostrophe.
     */
    private function csv_safe( $value ) {
        if ( ! is_string( $value ) ) { return $value; }
        if ( preg_match( '/^[\x00-\x20]*[=+\-@]/u', $value ) ) { return "'" . $value; }
        return $value;
    }

    private function csv_row( $handle, $fields ) {
        $fields = array_map( array( $this, 'csv_safe' ), $fields );
        // Explicit empty escape keeps PHP 8.4+ quiet and produces RFC-4180-style quote escaping.
        return fputcsv( $handle, $fields, ',', '"', '' );
    }

    public function download() {
        if ( ! NineCM_Core::can_access_planner() ) { $this->fail( 'You do not have permission to export planning data.' ); }
        check_admin_referer( 'ninecm_export' );

        $type = sanitize_key( $_GET['type'] ?? 'categories' );
        if ( 'categories' === $type ) {
            $this->categories_csv();
        } elseif ( 'planning' === $type ) {
            $this->planning_csv();
        } elseif ( 'structure' === $type ) {
            $this->structure_json();
        } elseif ( 'blueprint' === $type ) {
            $this->blueprint_json();
        } else {
            $this->fail( 'Unknown export type.', 400 );
        }
        exit;
    }

    private function categories_csv() {
        $tax = $this->taxonomy( $_GET['taxonomy'] ?? 'category' );
        $this->csv_headers( '9-category-manager-' . $tax->name . '-categories-' . gmdate( 'Y-m-d' ) . '.csv' );
        $out = fopen( 'php://output', 'w' );
        if ( ! $out ) { $this->fail( 'Could not open export stream.', 500 ); }
        $this->csv_row( $out, array( 'taxonomy', 'term_id', 'name', 'slug', 'parent_id', 'parent_path', 'full_path', 'assigned_count', 'display_order', 'protected', 'archived', 'description', 'term_url' ) );

        $offset = 0;
        $batch = 500;
        do {
            $terms = get_terms( array(
                'taxonomy'   => $tax->name,
                'hide_empty' => false,
                'orderby'    => 'term_id',
                'order'      => 'ASC',
                'number'     => $batch,
                'offset'     => $offset,
            ) );
            if ( is_wp_error( $terms ) ) { fclose( $out ); $this->fail( $terms->get_error_message(), 500 ); }

            foreach ( $terms as $term ) {
                $parent_path = '';
                if ( $term->parent ) {
                    $parent = get_term( $term->parent, $tax->name );
                    if ( $parent && ! is_wp_error( $parent ) ) { $parent_path = NineCM_REST::term_path( $parent, $tax->name ); }
                }
                $url = get_term_link( $term );
                $this->csv_row( $out, array(
                    $tax->name,
                    (int) $term->term_id,
                    $term->name,
                    $term->slug,
                    (int) $term->parent,
                    $parent_path,
                    NineCM_REST::term_path( $term, $tax->name ),
                    (int) $term->count,
                    (int) get_term_meta( $term->term_id, 'ninecm_order', true ),
                    get_term_meta( $term->term_id, 'ninecm_protected', true ) ? 1 : 0,
                    get_term_meta( $term->term_id, 'ninecm_archived', true ) ? 1 : 0,
                    $term->description,
                    is_wp_error( $url ) ? '' : $url,
                ) );
            }
            $count = count( $terms );
            $offset += $count;
            if ( function_exists( 'flush' ) ) { @flush(); }
        } while ( $count === $batch );

        fclose( $out );
    }

    private function planning_csv() {
        $tax = $this->taxonomy( $_GET['taxonomy'] ?? 'category' );
        $pto = $this->post_type( $_GET['post_type'] ?? 'page' );

        $this->csv_headers( '9-category-manager-' . $pto->name . '-infrastructure-planning-' . gmdate( 'Y-m-d' ) . '.csv' );
        $out = fopen( 'php://output', 'w' );
        if ( ! $out ) { $this->fail( 'Could not open export stream.', 500 ); }
        $this->csv_row( $out, array(
            'post_id', 'post_type', 'status', 'title', 'excerpt', 'author_id', 'author_name', 'parent_id', 'parent_title',
            'menu_order', 'featured_media_id', 'featured_media_url', 'selected_taxonomy', 'selected_term_ids', 'selected_term_paths',
            'all_taxonomies_json', 'relationship_providers_json', 'planning_shell', 'modified_utc', 'view_url', 'edit_url'
        ) );

        $page = 1;
        do {
            $args = array(
                'post_type' => $pto->name, 'post_status' => $this->editable_statuses( $pto ), 'posts_per_page' => 250,
                'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC', 'ignore_sticky_posts' => true,
                'update_post_meta_cache' => true, 'update_post_term_cache' => true,
            );
            if ( empty( $pto->cap->edit_others_posts ) || ! current_user_can( $pto->cap->edit_others_posts ) ) { $args['author'] = get_current_user_id(); }
            $q = new WP_Query( $args );
            foreach ( $q->posts as $post ) {
                if ( ! current_user_can( 'edit_post', $post->ID ) ) { continue; }
                $plan = NineCM_Infrastructure::planning_payload( $post );
                $selected_ids = array(); $selected_paths = array();
                foreach ( (array) ( $plan['taxonomies'] ?? array() ) as $tax_row ) {
                    if ( $tax_row['name'] !== $tax->name ) { continue; }
                    foreach ( (array) ( $tax_row['terms'] ?? array() ) as $term ) { $selected_ids[] = (int) $term['id']; $selected_paths[] = (string) $term['path']; }
                }
                $tax_export = array();
                foreach ( (array) ( $plan['taxonomies'] ?? array() ) as $tax_row ) {
                    $tax_export[ $tax_row['name'] ] = array_map( function( $term ) { return array( 'id' => (int) $term['id'], 'path' => (string) $term['path'], 'archived' => ! empty( $term['archived'] ) ); }, (array) ( $tax_row['terms'] ?? array() ) );
                }
                $provider_export = array();
                foreach ( (array) ( $plan['providers'] ?? array() ) as $provider ) { $provider_export[ $provider['key'] ] = $provider['value']; }
                $this->csv_row( $out, array(
                    (int) $post->ID, $post->post_type, $post->post_status, get_the_title( $post ), $post->post_excerpt,
                    (int) ( $plan['author']['id'] ?? 0 ), (string) ( $plan['author']['name'] ?? '' ),
                    (int) ( $plan['parent']['id'] ?? 0 ), (string) ( $plan['parent']['title'] ?? '' ), (int) ( $plan['menuOrder'] ?? 0 ),
                    (int) ( $plan['featuredMedia']['id'] ?? 0 ), (string) ( $plan['featuredMedia']['url'] ?? '' ),
                    $tax->name, implode( '|', $selected_ids ), implode( ' || ', $selected_paths ),
                    wp_json_encode( $tax_export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                    wp_json_encode( $provider_export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                    get_post_meta( $post->ID, '_ninecm_shell', true ) ? 1 : 0,
                    get_post_modified_time( 'Y-m-d H:i:s', true, $post ),
                    'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post ), get_edit_post_link( $post->ID, 'raw' ),
                ) );
            }
            $more = $page < (int) $q->max_num_pages; $page++; wp_reset_postdata(); if ( function_exists( 'flush' ) ) { @flush(); }
        } while ( $more );
        fclose( $out );
    }

    private function structure_json() {
        $tax = $this->taxonomy( $_GET['taxonomy'] ?? 'category' );
        $this->json_headers( '9-category-manager-' . $tax->name . '-structure-' . gmdate( 'Y-m-d' ) . '.json' );

        $prefix = array(
            'format'      => 'nine-category-manager-structure',
            'version'     => NINECM_VERSION,
            'exported_at' => gmdate( 'c' ),
            'site'        => home_url( '/' ),
            'taxonomy'    => $tax->name,
        );
        echo '{';
        $first_field = true;
        foreach ( $prefix as $key => $value ) {
            if ( ! $first_field ) { echo ','; }
            echo wp_json_encode( (string) $key ) . ':' . wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            $first_field = false;
        }
        echo ',"terms":[';

        $first_term = true;
        $offset = 0;
        $batch = 500;
        $export_error = '';
        do {
            $terms = get_terms( array(
                'taxonomy' => $tax->name,
                'hide_empty' => false,
                'orderby' => 'term_id',
                'order' => 'ASC',
                'number' => $batch,
                'offset' => $offset,
            ) );
            if ( is_wp_error( $terms ) ) { $export_error = $terms->get_error_message(); break; }
            foreach ( $terms as $term ) {
                if ( ! $first_term ) { echo ','; }
                echo wp_json_encode( array(
                    'id'          => (int) $term->term_id,
                    'name'        => $term->name,
                    'slug'        => $term->slug,
                    'parent'      => (int) $term->parent,
                    'path'        => NineCM_REST::term_path( $term, $tax->name ),
                    'description' => $term->description,
                    'count'       => (int) $term->count,
                    'display_order'=> (int) get_term_meta( $term->term_id, 'ninecm_order', true ),
                    'protected'    => (bool) get_term_meta( $term->term_id, 'ninecm_protected', true ),
                    'archived'     => (bool) get_term_meta( $term->term_id, 'ninecm_archived', true ),
                ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                $first_term = false;
            }
            $count = count( $terms );
            $offset += $count;
            if ( function_exists( 'flush' ) ) { @flush(); }
        } while ( $count === $batch );

        echo ']';
        if ( $export_error ) { echo ',"error":' . wp_json_encode( $export_error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
        echo '}';
    }

    private function blueprint_json() {
        $selected_tax = $this->taxonomy( $_GET['taxonomy'] ?? 'category' );
        $pto = $this->post_type( $_GET['post_type'] ?? 'page' );
        $this->json_headers( '9-category-manager-' . $pto->name . '-site-infrastructure-blueprint-' . gmdate( 'Y-m-d' ) . '.json' );

        $descriptor = NineCM_Infrastructure::post_type_descriptor( $pto );
        if ( current_user_can( 'manage_options' ) ) {
            $site_infrastructure = NineCM_Drift::structure_snapshot();
            $drift = NineCM_Drift::status();
            $drift_summary = array(
                'has_baseline' => ! empty( $drift['has_baseline'] ),
                'changed'      => ! empty( $drift['changed'] ),
                'critical'     => absint( $drift['critical'] ?? 0 ),
                'warning'      => absint( $drift['warning'] ?? 0 ),
                'info'         => absint( $drift['info'] ?? 0 ),
                'captured_at'  => absint( $drift['captured_at'] ?? 0 ),
            );
        } else {
            // Editors may export the infrastructure they can work with, but the
            // whole-site registration baseline remains administrator-only.
            $site_infrastructure = array(
                'scope'       => 'current-user',
                'post_types'  => NineCM_Infrastructure::editable_post_types(),
                'taxonomies'  => NineCM_Infrastructure::usable_taxonomies(),
            );
            $drift_summary = array( 'restricted' => true );
        }
        echo '{"format":"nine-category-manager-infrastructure-blueprint","version":' . wp_json_encode( NINECM_VERSION ) . ',"exported_at":' . wp_json_encode( gmdate( 'c' ) ) . ',"site":' . wp_json_encode( home_url( '/' ), JSON_UNESCAPED_SLASHES ) . ',"site_infrastructure":' . wp_json_encode( $site_infrastructure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ',"drift_guard":' . wp_json_encode( $drift_summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ',"post_type":' . wp_json_encode( $pto->name ) . ',"content_type":' . wp_json_encode( $descriptor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ',"taxonomy":' . wp_json_encode( $selected_tax->name ) . ',"terms":[';

        // Keep a top-level selected-taxonomy term list for backwards-compatible structure restore.
        $first = true; $offset = 0; $batch = 500; $error = '';
        do {
            $terms = get_terms( array( 'taxonomy' => $selected_tax->name, 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC', 'number' => $batch, 'offset' => $offset ) );
            if ( is_wp_error( $terms ) ) { $error = $terms->get_error_message(); break; }
            foreach ( $terms as $term ) { if ( ! $first ) { echo ','; } echo wp_json_encode( $this->term_blueprint_row( $term, $selected_tax->name ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); $first = false; }
            $count = count( $terms ); $offset += $count; if ( function_exists( 'flush' ) ) { @flush(); }
        } while ( $count === $batch );

        echo '],"taxonomies":[';
        $first_tax = true;
        foreach ( NineCM_Infrastructure::usable_taxonomies( $pto->name ) as $tax_desc ) {
            $tax = get_taxonomy( $tax_desc['name'] );
            if ( ! $tax ) { continue; }
            if ( ! $first_tax ) { echo ','; } $first_tax = false;
            echo '{"descriptor":' . wp_json_encode( $tax_desc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ',"terms":[';
            $first_term = true; $offset = 0;
            do {
                $terms = get_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC', 'number' => $batch, 'offset' => $offset ) );
                if ( is_wp_error( $terms ) ) { $error = $terms->get_error_message(); break; }
                foreach ( $terms as $term ) { if ( ! $first_term ) { echo ','; } echo wp_json_encode( $this->term_blueprint_row( $term, $tax->name ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); $first_term = false; }
                $count = count( $terms ); $offset += $count;
            } while ( $count === $batch );
            echo ']}';
            if ( function_exists( 'flush' ) ) { @flush(); }
        }

        echo '],"items":[';
        $first = true; $page = 1;
        do {
            $args = array( 'post_type' => $pto->name, 'post_status' => $this->editable_statuses( $pto ), 'posts_per_page' => 200, 'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC', 'ignore_sticky_posts' => true, 'update_post_meta_cache' => true, 'update_post_term_cache' => true );
            if ( empty( $pto->cap->edit_others_posts ) || ! current_user_can( $pto->cap->edit_others_posts ) ) { $args['author'] = get_current_user_id(); }
            $q = new WP_Query( $args );
            foreach ( $q->posts as $post ) {
                if ( ! current_user_can( 'edit_post', $post->ID ) ) { continue; }
                if ( ! $first ) { echo ','; }
                $plan = NineCM_Infrastructure::planning_payload( $post );
                $plan['planningShell'] = (bool) get_post_meta( $post->ID, '_ninecm_shell', true );
                $plan['modifiedUtc'] = get_post_modified_time( 'c', true, $post );
                echo wp_json_encode( $plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); $first = false;
            }
            $more = $page < (int) $q->max_num_pages; $page++; wp_reset_postdata(); if ( function_exists( 'flush' ) ) { @flush(); }
        } while ( $more );
        echo ']';
        if ( $error ) { echo ',"error":' . wp_json_encode( $error ); }
        echo '}';
    }

    private function term_blueprint_row( $term, $taxonomy ) {
        return array(
            'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'parent' => (int) $term->parent,
            'path' => NineCM_REST::term_path( $term, $taxonomy ), 'description' => $term->description, 'count' => (int) $term->count,
            'display_order' => (int) get_term_meta( $term->term_id, 'ninecm_order', true ),
            'protected' => (bool) get_term_meta( $term->term_id, 'ninecm_protected', true ),
            'archived' => (bool) get_term_meta( $term->term_id, 'ninecm_archived', true ),
        );
    }

}
