<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NineCM_Renderer {
    public static function defaults() {
        return array(
            'taxonomy' => 'category',
            'root' => 0,
            'depth' => 0,
            'hideEmpty' => true,
            'collapsible' => true,
            'initiallyOpen' => true,
            'marker' => 'number',
            'icon' => '›',
            'showSearch' => true,
            'showFilter' => true,
            'style' => 'clean',
            'postTypes' => array( 'page', 'post' ),
            'postsPerCategory' => 0,
            // 0 means use the site-wide safety limit from plugin settings.
            'maxPosts' => 0,
            'termOrderby' => 'name',
            'termOrder' => 'ASC',
            'postOrderby' => 'title',
            'postOrder' => 'ASC',
            'showCounts' => false,
            'deduplicate' => false,
            'include' => array(),
            'exclude' => array(),
            'emptyMessage' => 'No matching content found.',
        );
    }

    private static function sanitize_args( $attributes ) {
        $a = wp_parse_args( is_array( $attributes ) ? $attributes : array(), self::defaults() );
        $a['taxonomy'] = sanitize_key( $a['taxonomy'] );
        $a['root'] = absint( $a['root'] );
        $a['depth'] = max( 0, min( 12, absint( $a['depth'] ) ) );
        $a['hideEmpty'] = ! empty( $a['hideEmpty'] );
        $a['collapsible'] = ! empty( $a['collapsible'] );
        $a['initiallyOpen'] = ! empty( $a['initiallyOpen'] );
        $a['showSearch'] = ! empty( $a['showSearch'] );
        $a['showFilter'] = ! empty( $a['showFilter'] );
        $a['showCounts'] = ! empty( $a['showCounts'] );
        $a['deduplicate'] = ! empty( $a['deduplicate'] );
        $a['postsPerCategory'] = max( 0, min( 5000, absint( $a['postsPerCategory'] ) ) );
        $a['maxPosts'] = max( 0, min( 50000, absint( $a['maxPosts'] ) ) );
        $a['include'] = array_values( array_filter( array_map( 'absint', (array) $a['include'] ) ) );
        $a['exclude'] = array_values( array_filter( array_map( 'absint', (array) $a['exclude'] ) ) );
        $a['emptyMessage'] = sanitize_text_field( $a['emptyMessage'] );
        $icon = sanitize_text_field( $a['icon'] );
        $a['icon'] = function_exists( 'mb_substr' ) ? mb_substr( $icon, 0, 8 ) : substr( $icon, 0, 8 );

        $markers = array( 'number', 'bullet', 'icon', 'none' );
        $styles = array( 'clean', 'compact', 'bordered', 'academic', 'minimal' );
        $term_orderby = array( 'name', 'slug', 'term_id', 'id', 'count', 'parent', 'term_order', 'ninecm_order' );
        $post_orderby = array( 'title', 'date', 'menu_order', 'modified', 'ID' );
        $a['marker'] = in_array( sanitize_key( $a['marker'] ), $markers, true ) ? sanitize_key( $a['marker'] ) : 'number';
        $a['style'] = in_array( sanitize_key( $a['style'] ), $styles, true ) ? sanitize_key( $a['style'] ) : 'clean';
        $a['termOrderby'] = in_array( sanitize_key( $a['termOrderby'] ), $term_orderby, true ) ? sanitize_key( $a['termOrderby'] ) : 'name';
        $a['postOrderby'] = in_array( $a['postOrderby'], $post_orderby, true ) ? $a['postOrderby'] : 'title';
        $a['termOrder'] = 'DESC' === strtoupper( (string) $a['termOrder'] ) ? 'DESC' : 'ASC';
        $a['postOrder'] = 'DESC' === strtoupper( (string) $a['postOrder'] ) ? 'DESC' : 'ASC';
        return $a;
    }

    public static function render( $attributes = array(), $content = '', $block = null ) {
        $a = self::sanitize_args( $attributes );
        $taxonomy = $a['taxonomy'];
        $tax = get_taxonomy( $taxonomy );
        if ( ! $tax ) { return ''; }
        $taxonomy_is_public = ! empty( $tax->public );
        if ( ! apply_filters( 'ninecm_public_taxonomy_allowed', $taxonomy_is_public, $tax, $a ) ) { return ''; }

        $post_types = array();
        foreach ( array_unique( array_map( 'sanitize_key', (array) $a['postTypes'] ) ) as $post_type ) {
            $pto = get_post_type_object( $post_type );
            if ( ! $pto || empty( $pto->public ) || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) { continue; }
            $post_types[] = $post_type;
        }
        if ( ! $post_types ) { return self::empty_shell( $a ); }
        $a['postTypes'] = $post_types;

        $root_term = null;
        if ( $a['root'] ) {
            $root_term = get_term( $a['root'], $taxonomy );
            if ( ! $root_term || is_wp_error( $root_term ) ) { return self::empty_shell( $a ); }
        }

        $settings = wp_parse_args(
            (array) get_option( 'ninecm_settings', array() ),
            array( 'cache_minutes' => 15, 'public_post_limit' => 10000 )
        );
        if ( ! $a['maxPosts'] ) {
            $a['maxPosts'] = max( 100, min( 50000, absint( $settings['public_post_limit'] ) ) );
        }

        $cache_version = (int) get_option( 'ninecm_cache_version', 1 );
        $cache_key = 'ninecm_' . md5( wp_json_encode( array( $a, get_current_blog_id(), $cache_version ) ) );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) { return $cached; }

        // Always load the structural terms, even when empty. We prune empty branches after
        // posts are mapped so an empty parent remains visible when descendants contain content.
        $term_args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'ninecm_order' === $a['termOrderby'] ? 'name' : $a['termOrderby'],
            'order'      => $a['termOrder'],
        );
        if ( $a['root'] && ! empty( $tax->hierarchical ) ) {
            $term_args['child_of'] = $a['root'];
        } elseif ( $a['include'] ) {
            $term_args['include'] = $a['include'];
        }
        if ( $a['exclude'] ) { $term_args['exclude'] = $a['exclude']; }

        $terms = get_terms( $term_args );
        if ( is_wp_error( $terms ) ) { return ''; }
        if ( 'ninecm_order' === $a['termOrderby'] && $terms ) {
            $direction = 'DESC' === $a['termOrder'] ? -1 : 1;
            usort( $terms, function( $left, $right ) use ( $direction ) {
                $lo = (int) get_term_meta( $left->term_id, 'ninecm_order', true );
                $ro = (int) get_term_meta( $right->term_id, 'ninecm_order', true );
                if ( $lo === $ro ) { return strcasecmp( $left->name, $right->name ); }
                return ( $lo < $ro ? -1 : 1 ) * $direction;
            } );
        }
        if ( $a['root'] && ! in_array( $a['root'], array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ), true ) ) {
            array_unshift( $terms, $root_term );
        }
        if ( ! $terms ) { return self::empty_shell( $a ); }

        // Archived categories are a non-destructive way to retire a branch while
        // preserving relationships. Hide both the archived term and descendants,
        // including cases where an include/root query did not load the ancestor.
        $preload_ids = array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) );
        if ( $preload_ids ) { update_meta_cache( 'term', $preload_ids ); }
        $archive_memo = array();
        $terms = array_values( array_filter( $terms, function( $term ) use ( $taxonomy, &$archive_memo ) {
            return ! self::term_in_archived_branch( (int) $term->term_id, $taxonomy, $archive_memo );
        } ) );
        if ( ! $terms ) { return self::empty_shell( $a ); }

        $term_ids = array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) );
        $query = new WP_Query( array(
            'post_type'              => $post_types,
            'post_status'            => 'publish',
            // Fetch one additional post so truncation is never silent.
            'posts_per_page'         => $a['maxPosts'] + 1,
            'orderby'                => $a['postOrderby'],
            'order'                  => $a['postOrder'],
            'tax_query'              => array( array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_ids, 'include_children' => false ) ),
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => true,
            'ignore_sticky_posts'    => true,
        ) );

        $truncated = count( $query->posts ) > $a['maxPosts'];
        $query_posts = $truncated ? array_slice( $query->posts, 0, $a['maxPosts'] ) : $query->posts;

        $posts_by_term = array();
        foreach ( $query_posts as $p ) {
            $assigned = get_the_terms( $p, $taxonomy );
            if ( ! $assigned || is_wp_error( $assigned ) ) { continue; }
            foreach ( $assigned as $t ) {
                $tid = (int) $t->term_id;
                if ( in_array( $tid, $term_ids, true ) ) { $posts_by_term[ $tid ][] = $p; }
            }
        }

        $children = array();
        $by_id = array();
        foreach ( $terms as $term ) {
            $by_id[ (int) $term->term_id ] = $term;
            $children[ (int) $term->parent ][] = (int) $term->term_id;
        }

        if ( $a['root'] ) {
            $roots = array( $a['root'] );
        } else {
            // Treat a term as a visual root when its parent is not part of the loaded set.
            // This keeps multiple included branches and children of excluded parents visible.
            $roots = array();
            foreach ( $terms as $term ) {
                $parent = (int) $term->parent;
                if ( 0 === $parent || ! isset( $by_id[ $parent ] ) ) { $roots[] = (int) $term->term_id; }
            }
        }
        if ( ! $roots && $terms ) { $roots = array( (int) $terms[0]->term_id ); }

        $visible = array();
        foreach ( $roots as $rid ) {
            self::collect_visible_terms( $rid, $by_id, $children, $posts_by_term, $a, 1, $visible );
        }
        if ( ! $visible ) { return self::empty_shell( $a ); }

        ob_start();
        echo '<div class="ninecm-poc ninecm-style-' . esc_attr( $a['style'] ) . '" data-ninecm-poc>';
        if ( $a['showSearch'] || $a['showFilter'] ) {
            echo '<div class="ninecm-toolbar">';
            if ( $a['showSearch'] ) { echo '<input class="ninecm-search" type="search" placeholder="Search posts or categories…" aria-label="Search posts or categories" autocomplete="off">'; }
            if ( $a['showFilter'] ) {
                $filter_limit = 500;
                $visible_ids = array_map( 'intval', array_keys( $visible ) );
                $filter_ids = $visible_ids;
                $filter_limited = count( $filter_ids ) > $filter_limit;
                if ( $filter_limited ) {
                    // Avoid thousands of <option> elements on large directories. Prefer major branches;
                    // search still reaches every rendered category and page/post.
                    $filter_ids = array_slice( array_values( array_filter( array_map( 'intval', $roots ), function( $id ) use ( $visible ) { return ! empty( $visible[ $id ] ); } ) ), 0, $filter_limit );
                    if ( count( $filter_ids ) < 50 ) {
                        foreach ( $filter_ids as $rid ) {
                            foreach ( $children[ $rid ] ?? array() as $cid ) {
                                if ( ! empty( $visible[ $cid ] ) && ! in_array( (int) $cid, $filter_ids, true ) ) { $filter_ids[] = (int) $cid; }
                                if ( count( $filter_ids ) >= $filter_limit ) { break 2; }
                            }
                        }
                    }
                }
                $all_label = $filter_limited ? 'All categories — search for deeper levels' : 'All categories';
                echo '<select class="ninecm-filter" aria-label="Filter category"><option value="">' . esc_html( $all_label ) . '</option>';
                $filter_lookup = array_fill_keys( $filter_ids, true );
                foreach ( $terms as $term ) {
                    if ( empty( $filter_lookup[ (int) $term->term_id ] ) ) { continue; }
                    echo '<option value="term-' . esc_attr( $term->term_id ) . '">' . esc_html( self::term_path_from_loaded( $term, $by_id ) ) . '</option>';
                }
                echo '</select>';
            }
            echo '</div>';
        }
        echo '<div class="ninecm-tree" data-marker="' . esc_attr( $a['marker'] ) . '">';
        $seen = array();
        $counter = array();
        foreach ( $roots as $rid ) {
            self::render_term( $rid, $by_id, $children, $posts_by_term, $visible, $a, 1, $seen, $counter );
        }
        echo '</div>';
        if ( $truncated ) {
            echo '<div class="ninecm-limit-note" role="note">This directory reached its safety limit of ' . esc_html( number_format_i18n( $a['maxPosts'] ) ) . ' published items. Narrow the root/category selection or increase the Public directory safety limit in 9 Category Manager settings.</div>';
        }
        echo '<div class="ninecm-empty" hidden>' . esc_html( $a['emptyMessage'] ) . '</div></div>';
        $html = ob_get_clean();

        // Avoid turning an extremely large text directory into a multi-megabyte
        // transient/option on hosts without an external object cache.
        $max_cache_bytes = max( 0, (int) apply_filters( 'ninecm_max_cached_html_bytes', 1048576, $a ) );
        if ( $max_cache_bytes && strlen( $html ) <= $max_cache_bytes ) {
            set_transient( $cache_key, $html, max( 1, absint( $settings['cache_minutes'] ) ) * MINUTE_IN_SECONDS );
        }
        return $html;
    }

    private static function collect_visible_terms( $term_id, $by_id, $children, $posts_by_term, $a, $level, &$visible ) {
        if ( ! isset( $by_id[ $term_id ] ) ) { return false; }
        if ( $a['depth'] && $level > $a['depth'] ) { return false; }

        $child_has_content = false;
        foreach ( $children[ $term_id ] ?? array() as $cid ) {
            if ( self::collect_visible_terms( $cid, $by_id, $children, $posts_by_term, $a, $level + 1, $visible ) ) {
                $child_has_content = true;
            }
        }
        $own_has_content = ! empty( $posts_by_term[ $term_id ] );
        $show = ! $a['hideEmpty'] || $own_has_content || $child_has_content;
        if ( $show ) { $visible[ (int) $term_id ] = true; }
        return $show;
    }

    private static function render_term( $term_id, $by_id, $children, $posts_by_term, $visible, $a, $level, &$seen, &$counter ) {
        if ( ! isset( $by_id[ $term_id ] ) || empty( $visible[ $term_id ] ) ) { return false; }
        if ( $a['depth'] && $level > $a['depth'] ) { return false; }

        $t = $by_id[ $term_id ];
        $all_posts = $posts_by_term[ $term_id ] ?? array();
        $visible_posts = array();
        foreach ( $all_posts as $p ) {
            if ( $a['deduplicate'] && isset( $seen[ $p->ID ] ) ) { continue; }
            $visible_posts[] = $p;
            if ( $a['deduplicate'] ) { $seen[ $p->ID ] = true; }
            if ( $a['postsPerCategory'] && count( $visible_posts ) >= $a['postsPerCategory'] ) { break; }
        }

        // Render children to a buffer first. This allows de-duplication to prune a branch that
        // became empty after an earlier branch already displayed the same pages/posts.
        $counter_snapshot = $counter;
        $counter[ $level ] = ( $counter[ $level ] ?? 0 ) + 1;
        foreach ( array_keys( $counter ) as $k ) { if ( $k > $level ) { unset( $counter[ $k ] ); } }
        $num = implode( '.', array_values( $counter ) );

        ob_start();
        $has_rendered_child = false;
        foreach ( $children[ $term_id ] ?? array() as $cid ) {
            if ( self::render_term( $cid, $by_id, $children, $posts_by_term, $visible, $a, $level + 1, $seen, $counter ) ) {
                $has_rendered_child = true;
            }
        }
        $child_html = ob_get_clean();

        if ( $a['hideEmpty'] && ! $visible_posts && ! $has_rendered_child ) {
            $counter = $counter_snapshot;
            return false;
        }

        $open = $a['initiallyOpen'];
        $term_path = self::term_path_from_loaded( $t, $by_id );
        $search_name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term_path ) : strtolower( $term_path );

        echo '<section class="ninecm-term term-' . esc_attr( $t->term_id ) . '" data-term-id="' . esc_attr( $t->term_id ) . '" data-name="' . esc_attr( $search_name ) . '">';
        echo '<div class="ninecm-term-head">';
        if ( $a['collapsible'] ) { echo '<button class="ninecm-toggle" type="button" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-label="Toggle ' . esc_attr( $t->name ) . '">▾</button>'; }
        echo '<div class="ninecm-term-title level-' . esc_attr( $level ) . '">';
        if ( 'number' === $a['marker'] ) { echo '<span class="ninecm-marker">' . esc_html( $num ) . '</span>'; }
        elseif ( 'bullet' === $a['marker'] ) { echo '<span class="ninecm-marker">•</span>'; }
        elseif ( 'icon' === $a['marker'] ) { echo '<span class="ninecm-marker">' . esc_html( $a['icon'] ) . '</span>'; }
        echo '<span class="ninecm-term-name">' . esc_html( $t->name ) . '</span>';
        if ( $a['showCounts'] ) { echo '<span class="ninecm-count">' . esc_html( count( $all_posts ) ) . '</span>'; }
        echo '</div></div>';
        echo '<div class="ninecm-term-body"' . ( $open ? '' : ' hidden' ) . '>';

        if ( $visible_posts ) {
            echo '<ol class="ninecm-posts">';
            foreach ( $visible_posts as $p ) {
                $search = $p->post_title . ' ' . $term_path;
                $search = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );
                echo '<li class="ninecm-post" data-search="' . esc_attr( $search ) . '"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
            }
            echo '</ol>';
        }
        if ( $has_rendered_child ) { echo '<div class="ninecm-children">' . $child_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $child_html is escaped by the recursive render call.
        }
        echo '</div></section>';
        return true;
    }

    private static function term_in_archived_branch( $term_id, $taxonomy, &$memo ) {
        $term_id = (int) $term_id;
        if ( isset( $memo[ $term_id ] ) ) { return $memo[ $term_id ]; }
        if ( get_term_meta( $term_id, 'ninecm_archived', true ) ) { return $memo[ $term_id ] = true; }
        $term = get_term( $term_id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) || ! $term->parent ) { return $memo[ $term_id ] = false; }
        return $memo[ $term_id ] = self::term_in_archived_branch( (int) $term->parent, $taxonomy, $memo );
    }

    private static function term_path_from_loaded( $term, $by_id ) {
        $parts = array( $term->name );
        $parent = (int) $term->parent;
        $guard = 0;
        while ( $parent && $guard++ < 50 ) {
            if ( isset( $by_id[ $parent ] ) ) {
                $p = $by_id[ $parent ];
            } else {
                $p = get_term( $parent, $term->taxonomy );
                if ( ! $p || is_wp_error( $p ) ) { break; }
            }
            array_unshift( $parts, $p->name );
            $parent = (int) $p->parent;
        }
        return implode( ' › ', $parts );
    }

    private static function empty_shell( $a ) {
        return '<div class="ninecm-poc"><div class="ninecm-empty">' . esc_html( $a['emptyMessage'] ) . '</div></div>';
    }
}
