<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read-only taxonomy/planning health diagnostics.
 * The audit intentionally does not auto-delete or auto-rewrite structures.
 */
class NineCM_Health {
    const SCAN_LIMIT = 3000;

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'routes' ) );
    }

    public function routes() {
        register_rest_route( 'ninecm/v1', '/health', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'audit' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
    }

    public function can_access() { return NineCM_Core::can_access_planner(); }

    private function taxonomy( $name ) {
        $name = sanitize_key( $name ?: 'category' );
        $tax = get_taxonomy( $name );
        if ( ! $tax || empty( $tax->show_ui ) ) {
            return new WP_Error( 'ninecm_health_taxonomy', 'A valid visible taxonomy is required.', array( 'status' => 400 ) );
        }
        $allowed = false;
        foreach ( array( 'manage_terms', 'edit_terms', 'assign_terms' ) as $cap_name ) {
            if ( ! empty( $tax->cap->{$cap_name} ) && current_user_can( $tax->cap->{$cap_name} ) ) { $allowed = true; break; }
        }
        return $allowed ? $tax : new WP_Error( 'ninecm_health_forbidden', 'You cannot audit this taxonomy.', array( 'status' => 403 ) );
    }

    private function post_type( $name, $taxonomy ) {
        $name = sanitize_key( $name ?: 'page' );
        $pto = get_post_type_object( $name );
        if ( ! $pto || empty( $pto->show_ui ) || empty( $pto->cap->edit_posts ) || ! current_user_can( $pto->cap->edit_posts ) ) {
            return new WP_Error( 'ninecm_health_post_type', 'A valid editable content type is required.', array( 'status' => 400 ) );
        }
        if ( ! is_object_in_taxonomy( $name, $taxonomy ) ) {
            return new WP_Error( 'ninecm_health_relationship', 'The selected taxonomy is not enabled for this content type.', array( 'status' => 400 ) );
        }
        return $pto;
    }

    private function depth( $id, $by_id, &$memo ) {
        $id = (int) $id;
        if ( isset( $memo[ $id ] ) ) { return $memo[ $id ]; }
        $seen = array();
        $depth = 1;
        $cur = $id;
        while ( isset( $by_id[ $cur ] ) && (int) $by_id[ $cur ]->parent ) {
            if ( isset( $seen[ $cur ] ) || $depth > 60 ) { break; }
            $seen[ $cur ] = true;
            $cur = (int) $by_id[ $cur ]->parent;
            $depth++;
        }
        $memo[ $id ] = $depth;
        return $depth;
    }

    private function archived_branch( $id, $by_id, &$memo, $guard = 0 ) {
        $id = (int) $id;
        if ( isset( $memo[ $id ] ) ) { return $memo[ $id ]; }
        if ( ! $id || $guard > 60 || ! isset( $by_id[ $id ] ) ) { return false; }
        if ( get_term_meta( $id, 'ninecm_archived', true ) ) { return $memo[ $id ] = true; }
        $parent = (int) $by_id[ $id ]->parent;
        return $memo[ $id ] = ( $parent ? $this->archived_branch( $parent, $by_id, $memo, $guard + 1 ) : false );
    }

    private function editable_query_args( $pto, $extra = array() ) {
        $statuses = array( 'draft', 'pending' );
        if ( ! empty( $pto->cap->edit_published_posts ) && current_user_can( $pto->cap->edit_published_posts ) ) {
            $statuses[] = 'publish';
            $statuses[] = 'future';
        }
        if ( ! empty( $pto->cap->read_private_posts ) && current_user_can( $pto->cap->read_private_posts ) ) {
            $statuses[] = 'private';
        }
        $args = array(
            'post_type'              => $pto->name,
            'post_status'            => array_values( array_unique( $statuses ) ),
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        if ( empty( $pto->cap->edit_others_posts ) || ! current_user_can( $pto->cap->edit_others_posts ) ) {
            $args['author'] = get_current_user_id();
        }
        return array_merge( $args, $extra );
    }

    public function audit( WP_REST_Request $r ) {
        $tax = $this->taxonomy( $r->get_param( 'taxonomy' ) );
        if ( is_wp_error( $tax ) ) { return $tax; }
        $pto = $this->post_type( $r->get_param( 'post_type' ), $tax->name );
        if ( is_wp_error( $pto ) ) { return $pto; }

        $settings = wp_parse_args( (array) get_option( 'ninecm_settings', array() ), NineCM_Core::default_settings() );
        $depth_warning = max( 3, min( 20, absint( $settings['audit_depth_warning'] ?? 6 ) ) );
        $child_warning = max( 10, min( 500, absint( $settings['audit_child_warning'] ?? 50 ) ) );
        $stale_days    = max( 7, min( 365, absint( $settings['shell_stale_days'] ?? 30 ) ) );

        $term_total = get_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false, 'fields' => 'count' ) );
        if ( is_wp_error( $term_total ) ) { return $term_total; }
        $term_total = (int) $term_total;
        if ( $term_total > 30000 ) {
            return new WP_Error( 'ninecm_health_term_limit', 'Health Audit is capped at 30,000 terms per taxonomy. Narrow or split an exceptionally large taxonomy before auditing it.', array( 'status' => 413, 'term_count' => $term_total ) );
        }

        $terms = get_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC' ) );
        if ( is_wp_error( $terms ) ) { return $terms; }

        $by_id = array(); $children = array(); $memo = array(); $archive_memo = array();
        $empty_leaves = array(); $deep = array(); $wide = array(); $duplicates = array(); $name_map = array();
        $protected = 0; $manual_ordered = 0; $archived_roots = 0; $archived_total = 0;
        foreach ( $terms as $term ) {
            $id = (int) $term->term_id;
            $by_id[ $id ] = $term;
            $children[ (int) $term->parent ][] = $id;
            if ( get_term_meta( $id, 'ninecm_protected', true ) ) { $protected++; }
            if ( metadata_exists( 'term', $id, 'ninecm_order' ) ) { $manual_ordered++; }
            if ( get_term_meta( $id, 'ninecm_archived', true ) ) { $archived_roots++; }
        }
        foreach ( $terms as $term ) {
            $id = (int) $term->term_id;
            if ( $this->archived_branch( $id, $by_id, $archive_memo ) ) { $archived_total++; continue; }
            $key = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $term->name ) ) : strtolower( trim( $term->name ) );
            $name_map[ $key ][] = $term;
            $d = $this->depth( $id, $by_id, $memo );
            if ( $d > $depth_warning && count( $deep ) < 25 ) {
                $deep[] = array( 'id' => $id, 'path' => NineCM_REST::term_path( $term, $tax->name ), 'depth' => $d );
            }
            $child_count = count( array_filter( $children[ $id ] ?? array(), function( $child_id ) use ( $by_id, &$archive_memo ) { return ! $this->archived_branch( $child_id, $by_id, $archive_memo ); } ) );
            if ( $child_count > $child_warning && count( $wide ) < 25 ) {
                $wide[] = array( 'id' => $id, 'path' => NineCM_REST::term_path( $term, $tax->name ), 'children' => $child_count );
            }
        }
        foreach ( $name_map as $same ) {
            if ( count( $same ) < 2 ) { continue; }
            if ( count( $duplicates ) >= 20 ) { break; }
            $duplicates[] = array(
                'name'  => $same[0]->name,
                'paths' => array_map( function( $term ) use ( $tax ) { return NineCM_REST::term_path( $term, $tax->name ); }, array_slice( $same, 0, 8 ) ),
            );
        }

        // Content with no planning relationship.
        $orphan_query = new WP_Query( $this->editable_query_args( $pto, array(
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'tax_query'      => array( array( 'taxonomy' => $tax->name, 'operator' => 'NOT EXISTS' ) ),
        ) ) );
        $orphan_samples = array();
        foreach ( $orphan_query->posts as $id ) {
            $orphan_samples[] = array( 'id' => (int) $id, 'title' => get_the_title( $id ), 'status' => get_post_status( $id ) );
        }
        $orphan_total = (int) $orphan_query->found_posts;

        // Scan a bounded set for ancestor+descendant duplicate assignment.
        $scan = new WP_Query( $this->editable_query_args( $pto, array(
            'posts_per_page' => self::SCAN_LIMIT,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'tax_query'      => array( array( 'taxonomy' => $tax->name, 'operator' => 'EXISTS' ) ),
        ) ) );
        $scan_ids = array_map( 'intval', $scan->posts );
        if ( $scan_ids ) { update_object_term_cache( $scan_ids, $pto->name ); }
        $redundant_total = 0; $redundant_samples = array(); $related_term_ids = array();
        $archived_only_total = 0; $archived_only_samples = array();
        foreach ( $scan_ids as $post_id ) {
            $assigned = wp_get_object_terms( $post_id, $tax->name, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $assigned ) ) { continue; }
            $set = array_fill_keys( array_map( 'intval', $assigned ), true );
            $has_active_relationship = false;
            foreach ( array_keys( $set ) as $assigned_id ) {
                if ( ! $this->archived_branch( $assigned_id, $by_id, $archive_memo ) ) {
                    $related_term_ids[ (int) $assigned_id ] = true;
                    $has_active_relationship = true;
                }
            }
            if ( $set && ! $has_active_relationship ) {
                $archived_only_total++;
                if ( count( $archived_only_samples ) < 20 ) { $archived_only_samples[] = array( 'id' => $post_id, 'title' => get_the_title( $post_id ) ); }
            }
            if ( empty( $tax->hierarchical ) || count( $set ) < 2 ) { continue; }
            $redundant = array();
            foreach ( array_keys( $set ) as $tid ) {
                $ancestors = get_ancestors( $tid, $tax->name, 'taxonomy' );
                foreach ( $ancestors as $aid ) {
                    if ( isset( $set[ (int) $aid ] ) ) { $redundant[ (int) $aid ] = true; }
                }
            }
            if ( $redundant ) {
                $redundant_total++;
                if ( count( $redundant_samples ) < 20 ) {
                    $redundant_samples[] = array( 'id' => $post_id, 'title' => get_the_title( $post_id ), 'ancestor_term_ids' => array_map( 'intval', array_keys( $redundant ) ) );
                }
            }
        }

        // Shells created by v3+ that still look unpopulated. The check understands
        // post_content, Elementor data and ordinary custom-field/ACF values, and it
        // is filterable so 9 Post Editor or another population layer can extend it.
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $stale_days * DAY_IN_SECONDS ) );
        $stale_query = new WP_Query( $this->editable_query_args( $pto, array(
            'post_status'              => 'draft',
            'posts_per_page'           => self::SCAN_LIMIT,
            'fields'                   => 'ids',
            'no_found_rows'            => false,
            'update_post_meta_cache'   => true,
            'date_query'               => array( array( 'before' => $cutoff, 'inclusive' => true, 'column' => 'post_date_gmt' ) ),
            'meta_query'               => array( array( 'key' => '_ninecm_shell', 'value' => '1' ) ),
        ) ) );
        $stale_samples = array(); $stale_total = 0;
        foreach ( $stale_query->posts as $id ) {
            $post = get_post( $id );
            if ( ! $post || NineCM_Core::is_shell_populated( $id, $post ) ) { continue; }
            $stale_total++;
            if ( count( $stale_samples ) < 20 ) {
                $stale_samples[] = array( 'id' => (int) $id, 'title' => get_the_title( $id ), 'created' => get_post_time( 'c', true, $post ) );
            }
        }

        $deep_total = 0; $wide_total = 0; $empty_leaf_total = 0;
        $relationship_scan_limited = (int) $scan->found_posts > self::SCAN_LIMIT;
        foreach ( $terms as $term ) {
            $id = (int) $term->term_id;
            if ( $this->archived_branch( $id, $by_id, $archive_memo ) ) { continue; }
            if ( $this->depth( $id, $by_id, $memo ) > $depth_warning ) { $deep_total++; }
            $child_count = count( array_filter( $children[ $id ] ?? array(), function( $child_id ) use ( $by_id, &$archive_memo ) { return ! $this->archived_branch( $child_id, $by_id, $archive_memo ); } ) );
            if ( $child_count > $child_warning ) { $wide_total++; }
            // Draft/pending planning shells do not contribute to WP_Term->count. When
            // the relationship scan is complete we can safely treat a leaf as empty only
            // if it has no relationship in the full editable-content scope. If the scan
            // was bounded, skip this diagnostic rather than report false positives.
            if ( ! $relationship_scan_limited && 0 === $child_count && empty( $related_term_ids[ $id ] ) ) {
                $empty_leaf_total++;
                if ( count( $empty_leaves ) < 30 ) {
                    $empty_leaves[] = array( 'id' => $id, 'path' => NineCM_REST::term_path( $term, $tax->name ) );
                }
            }
        }
        $duplicate_total = count( array_filter( $name_map, function( $same ) { return count( $same ) > 1; } ) );

        $deductions = 0;
        $deductions += min( 25, $orphan_total > 0 ? 5 + (int) floor( log( $orphan_total + 1, 2 ) ) : 0 );
        $deductions += min( 20, $redundant_total > 0 ? 5 + (int) floor( log( $redundant_total + 1, 2 ) ) : 0 );
        $deductions += min( 15, $archived_only_total > 0 ? 5 + (int) floor( log( $archived_only_total + 1, 2 ) ) : 0 );
        $deductions += min( 15, $deep_total * 2 );
        $deductions += min( 10, $wide_total * 2 );
        $deductions += min( 10, (int) floor( $empty_leaf_total / 20 ) );
        $score = max( 0, 100 - $deductions );

        return rest_ensure_response( array(
            'score'  => $score,
            'label'  => $score >= 90 ? 'Healthy' : ( $score >= 75 ? 'Good with cleanup opportunities' : ( $score >= 55 ? 'Needs attention' : 'High maintenance risk' ) ),
            'scope'  => array( 'taxonomy' => $tax->name, 'post_type' => $pto->name, 'term_count' => $term_total, 'scanned_items' => count( $scan_ids ), 'scan_limited' => $relationship_scan_limited, 'empty_leaf_check_limited' => $relationship_scan_limited, 'stale_scan_limited' => (int) $stale_query->found_posts > self::SCAN_LIMIT ),
            'counts' => array(
                'unassigned_content'      => $orphan_total,
                'redundant_relationships' => $redundant_total,
                'archived_only_content'    => $archived_only_total,
                'empty_leaf_categories'   => $empty_leaf_total,
                'deep_categories'         => $deep_total,
                'wide_branches'           => $wide_total,
                'duplicate_names'         => $duplicate_total,
                'stale_shells'            => $stale_total,
                'protected_categories'    => $protected,
                'archived_roots'          => $archived_roots,
                'archived_categories'     => $archived_total,
                'manual_ordered'          => $manual_ordered,
            ),
            'samples' => array(
                'unassigned_content'      => $orphan_samples,
                'redundant_relationships' => $redundant_samples,
                'archived_only_content'    => $archived_only_samples,
                'empty_leaf_categories'   => $empty_leaves,
                'deep_categories'         => $deep,
                'wide_branches'           => $wide,
                'duplicate_names'         => $duplicates,
                'stale_shells'            => $stale_samples,
            ),
            'thresholds' => array( 'depth' => $depth_warning, 'children' => $child_warning, 'stale_days' => $stale_days ),
        ) );
    }
}
