<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NineCM_REST {
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'routes' ) );
    }

    public function routes() {
        register_rest_route( 'ninecm/v1', '/taxonomies', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'taxonomies' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
        register_rest_route( 'ninecm/v1', '/post-types', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'post_types' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
        register_rest_route( 'ninecm/v1', '/infrastructure', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'infrastructure' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
        register_rest_route( 'ninecm/v1', '/authors', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'authors' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
        register_rest_route( 'ninecm/v1', '/parents', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'parents' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
        register_rest_route( 'ninecm/v1', '/provider-options', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'provider_options' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
        register_rest_route( 'ninecm/v1', '/terms', array(
            array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'terms' ), 'permission_callback' => array( $this, 'can_access' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_term' ), 'permission_callback' => array( $this, 'can_manage_terms' ) ),
        ) );
        register_rest_route( 'ninecm/v1', '/terms/(?P<id>\d+)', array(
            array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_term' ), 'permission_callback' => array( $this, 'can_edit_terms' ) ),
            array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_term' ), 'permission_callback' => array( $this, 'can_delete_terms' ) ),
        ) );
        register_rest_route( 'ninecm/v1', '/bulk-terms', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'bulk_terms' ),
            'permission_callback' => array( $this, 'can_manage_terms' ),
        ) );
        register_rest_route( 'ninecm/v1', '/bulk-shells', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'bulk_shells' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
        register_rest_route( 'ninecm/v1', '/restore-term-state', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'restore_term_state' ),
            'permission_callback' => array( $this, 'can_manage_terms' ),
        ) );
        register_rest_route( 'ninecm/v1', '/posts', array(
            array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'posts' ), 'permission_callback' => array( $this, 'can_access' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_post' ), 'permission_callback' => array( $this, 'can_access' ) ),
        ) );
        register_rest_route( 'ninecm/v1', '/assign', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'assign' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
        register_rest_route( 'ninecm/v1', '/undo-assignment', array(
            array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'undo_assignment_status' ), 'permission_callback' => array( $this, 'can_access' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'undo_assignment' ), 'permission_callback' => array( $this, 'can_access' ) ),
        ) );
        register_rest_route( 'ninecm/v1', '/bulk-structure', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'bulk_structure' ),
            'permission_callback' => array( $this, 'can_access' ),
        ) );
        register_rest_route( 'ninecm/v1', '/undo-structure', array(
            array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'undo_structure_status' ), 'permission_callback' => array( $this, 'can_access' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'undo_structure' ), 'permission_callback' => array( $this, 'can_access' ) ),
        ) );
        register_rest_route( 'ninecm/v1', '/plan/(?P<id>\d+)', array(
            array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_plan' ), 'permission_callback' => array( $this, 'can_edit_item' ) ),
            array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_plan' ), 'permission_callback' => array( $this, 'can_edit_item' ) ),
        ) );
    }

    public function can_access() {
        return NineCM_Core::can_access_planner();
    }

    public function can_edit_item( WP_REST_Request $request ) {
        return current_user_can( 'edit_post', absint( $request['id'] ) );
    }

    private function taxonomy_cap_check( $request, $cap_name ) {
        $tax = 'category';
        if ( $request instanceof WP_REST_Request && $request->get_param( 'taxonomy' ) ) {
            $tax = sanitize_key( $request->get_param( 'taxonomy' ) );
        }
        $obj = get_taxonomy( $tax );
        if ( ! $obj || empty( $obj->cap->{$cap_name} ) ) { return false; }
        return current_user_can( $obj->cap->{$cap_name} );
    }

    public function can_edit_terms( $request = null ) {
        return $this->taxonomy_cap_check( $request, 'edit_terms' );
    }

    public function can_delete_terms( $request = null ) {
        return $this->taxonomy_cap_check( $request, 'delete_terms' );
    }

    public function can_manage_terms( $request = null ) {
        return $this->taxonomy_cap_check( $request, 'manage_terms' );
    }

    private function can_assign_terms( $taxonomy ) {
        $obj = get_taxonomy( $taxonomy );
        return $obj && ! empty( $obj->cap->assign_terms ) && current_user_can( $obj->cap->assign_terms );
    }

    private function can_use_taxonomy( $taxonomy ) {
        $obj = get_taxonomy( $taxonomy );
        if ( ! $obj ) { return false; }
        foreach ( array( 'manage_terms', 'edit_terms', 'assign_terms' ) as $cap_name ) {
            if ( ! empty( $obj->cap->{$cap_name} ) && current_user_can( $obj->cap->{$cap_name} ) ) { return true; }
        }
        return false;
    }

    private function valid_taxonomy( WP_REST_Request $request, $require_hierarchical = false ) {
        $tax = sanitize_key( $request->get_param( 'taxonomy' ) ?: 'category' );
        $obj = get_taxonomy( $tax );
        if ( ! $obj || empty( $obj->show_ui ) || ( $require_hierarchical && empty( $obj->hierarchical ) ) ) {
            return new WP_Error( 'ninecm_taxonomy', $require_hierarchical ? 'A visible hierarchical taxonomy is required for this operation.' : 'A valid visible taxonomy is required.', array( 'status' => 400 ) );
        }
        return $tax;
    }

    private function term_in_archived_branch( $term_id, $taxonomy ) {
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

    private function archived_branch_ids( $taxonomy ) {
        static $cache = array();
        if ( isset( $cache[ $taxonomy ] ) ) { return $cache[ $taxonomy ]; }
        $roots = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'ids',
            'meta_query' => array( array( 'key' => 'ninecm_archived', 'value' => '1' ) ),
        ) );
        if ( is_wp_error( $roots ) || ! $roots ) { return $cache[ $taxonomy ] = array(); }
        $ids = array();
        foreach ( array_map( 'intval', $roots ) as $root_id ) {
            $ids[ $root_id ] = true;
            $children = get_term_children( $root_id, $taxonomy );
            if ( ! is_wp_error( $children ) ) {
                foreach ( $children as $child_id ) { $ids[ (int) $child_id ] = true; }
            }
        }
        return $cache[ $taxonomy ] = array_map( 'intval', array_keys( $ids ) );
    }

    private function set_order_meta( $term_id, $value ) {
        $order = intval( $value );
        if ( 0 === $order ) { delete_term_meta( absint( $term_id ), 'ninecm_order' ); }
        else { update_term_meta( absint( $term_id ), 'ninecm_order', $order ); }
    }

    private function set_boolean_term_meta( $term_id, $key, $value ) {
        $term_id = absint( $term_id );
        if ( rest_sanitize_boolean( $value ) ) { update_term_meta( $term_id, $key, 1 ); }
        else { delete_term_meta( $term_id, $key ); }
    }

    private function valid_post_type( $post_type, $fallback = 'page' ) {
        $post_type = sanitize_key( $post_type ?: $fallback );
        $obj = get_post_type_object( $post_type );
        if ( in_array( $post_type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) || ! $obj || empty( $obj->show_ui ) ) {
            return new WP_Error( 'ninecm_post_type', 'A valid editable content type is required.', array( 'status' => 400 ) );
        }
        return $obj;
    }

    public static function term_path( $term, $taxonomy ) {
        if ( ! $term || is_wp_error( $term ) ) { return ''; }
        $path = array( $term->name );
        $parent = (int) $term->parent;
        $guard = 0;
        while ( $parent && $guard++ < 50 ) {
            $pt = get_term( $parent, $taxonomy );
            if ( ! $pt || is_wp_error( $pt ) ) { break; }
            array_unshift( $path, $pt->name );
            $parent = (int) $pt->parent;
        }
        return implode( ' › ', $path );
    }

    private function term_row( $term, $taxonomy ) {
        return array(
            'id'          => (int) $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'parent'      => (int) $term->parent,
            'count'       => (int) $term->count,
            'description' => $term->description,
            'path'        => self::term_path( $term, $taxonomy ),
            'order'       => (int) get_term_meta( $term->term_id, 'ninecm_order', true ),
            'protected'    => (bool) get_term_meta( $term->term_id, 'ninecm_protected', true ),
            'archived'     => $this->term_in_archived_branch( $term->term_id, $taxonomy ),
            'archivedRoot' => (bool) get_term_meta( $term->term_id, 'ninecm_archived', true ),
        );
    }

    public function taxonomies() {
        return rest_ensure_response( NineCM_Infrastructure::usable_taxonomies() );
    }

    public function post_types( WP_REST_Request $r ) {
        $tax_filter = sanitize_key( $r->get_param( 'taxonomy' ) ?: '' );
        if ( $tax_filter && ! taxonomy_exists( $tax_filter ) ) {
            return new WP_Error( 'ninecm_taxonomy', 'Taxonomy not found.', array( 'status' => 400 ) );
        }
        if ( $tax_filter && ! $this->can_use_taxonomy( $tax_filter ) ) {
            return new WP_Error( 'ninecm_forbidden', 'You do not have permission to use this taxonomy in the planner.', array( 'status' => 403 ) );
        }
        $out = array();
        foreach ( NineCM_Infrastructure::editable_post_types() as $p ) {
            if ( $tax_filter && ! is_object_in_taxonomy( $p['name'], $tax_filter ) ) { continue; }
            $out[] = $p;
        }
        return rest_ensure_response( $out );
    }

    public function infrastructure( WP_REST_Request $r ) {
        $post_type = sanitize_key( $r->get_param( 'post_type' ) ?: '' );
        if ( $post_type ) {
            $pto = $this->valid_post_type( $post_type );
            if ( is_wp_error( $pto ) ) { return $pto; }
            if ( empty( $pto->cap->edit_posts ) || ! current_user_can( $pto->cap->edit_posts ) ) { return new WP_Error( 'ninecm_forbidden', 'You cannot inspect this content type.', array( 'status' => 403 ) ); }
            return rest_ensure_response( NineCM_Infrastructure::post_type_descriptor( $pto ) );
        }
        return rest_ensure_response( array( 'postTypes' => NineCM_Infrastructure::editable_post_types(), 'taxonomies' => NineCM_Infrastructure::usable_taxonomies() ) );
    }

    public function authors( WP_REST_Request $r ) {
        $pto = $this->valid_post_type( $r->get_param( 'post_type' ) ?: 'post' );
        if ( is_wp_error( $pto ) ) { return $pto; }
        if ( empty( $pto->cap->edit_posts ) || ! current_user_can( $pto->cap->edit_posts ) ) { return new WP_Error( 'ninecm_forbidden', 'You cannot inspect authors for this content type.', array( 'status' => 403 ) ); }
        return rest_ensure_response( NineCM_Infrastructure::author_rows( $pto->name, $r->get_param( 'search' ) ?: '', 60 ) );
    }

    public function parents( WP_REST_Request $r ) {
        $pto = $this->valid_post_type( $r->get_param( 'post_type' ) ?: 'page' );
        if ( is_wp_error( $pto ) ) { return $pto; }
        if ( empty( $pto->hierarchical ) ) { return rest_ensure_response( array() ); }
        return rest_ensure_response( NineCM_Infrastructure::parent_rows( $pto->name, $r->get_param( 'search' ) ?: '', absint( $r->get_param( 'exclude' ) ), 60 ) );
    }

    public function provider_options( WP_REST_Request $r ) {
        $pto = $this->valid_post_type( $r->get_param( 'post_type' ) ?: 'post' );
        if ( is_wp_error( $pto ) ) { return $pto; }
        $post_id = absint( $r->get_param( 'post_id' ) );
        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) { return new WP_Error( 'ninecm_forbidden', 'You cannot inspect this item.', array( 'status' => 403 ) ); }
        $key = sanitize_key( $r->get_param( 'key' ) );
        $providers = NineCM_Infrastructure::relationship_providers( $pto->name, $post_id );
        if ( empty( $providers[ $key ] ) || empty( $providers[ $key ]['options_callback'] ) ) { return rest_ensure_response( array() ); }
        try {
            $options = call_user_func( $providers[ $key ]['options_callback'], sanitize_text_field( $r->get_param( 'search' ) ?: '' ), $pto->name, $post_id );
        } catch ( Throwable $e ) {
            return new WP_Error( 'ninecm_provider_options_failed', 'The relationship provider failed while loading options. The site was not changed.', array( 'status' => 500, 'provider' => $key ) );
        }
        if ( ! is_array( $options ) ) { return rest_ensure_response( array() ); }
        $rows = array();
        foreach ( $options as $value => $option ) {
            if ( is_array( $option ) && array_key_exists( 'value', $option ) ) {
                $rows[] = array( 'value' => is_scalar( $option['value'] ) ? (string) $option['value'] : '', 'label' => sanitize_text_field( $option['label'] ?? $option['value'] ) );
            } elseif ( ! is_array( $option ) && ! is_object( $option ) ) {
                $rows[] = array( 'value' => is_int( $value ) ? (string) $option : (string) $value, 'label' => sanitize_text_field( (string) $option ) );
            }
            if ( count( $rows ) >= 100 ) { break; }
        }
        return rest_ensure_response( $rows );
    }

    public function terms( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        if ( ! $this->can_use_taxonomy( $tax ) ) { return new WP_Error( 'ninecm_forbidden', 'You do not have permission to browse this taxonomy in the planner.', array( 'status' => 403 ) ); }

        $page = max( 1, absint( $r['page'] ?: 1 ) );
        $per  = min( 200, max( 10, absint( $r['per_page'] ?: 50 ) ) );
        $args = array(
            'taxonomy'   => $tax,
            'hide_empty' => ! empty( $r['hide_empty'] ),
            'number'     => $per,
            'offset'     => ( $page - 1 ) * $per,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );
        if ( $r['search'] ) {
            $search = sanitize_text_field( $r['search'] );
            // A pasted hierarchy path searches by its last segment while returning full paths.
            $segments = preg_split( '/\s*(?:›|>|\/|\\\\)\s*/u', $search );
            $args['search'] = trim( (string) end( $segments ) );
        }
        $tax_obj = get_taxonomy( $tax );
        if ( $tax_obj && $tax_obj->hierarchical && null !== $r->get_param( 'parent' ) && '' !== $r['parent'] ) { $args['parent'] = absint( $r['parent'] ); }
        if ( $r['include'] ) {
            $ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $r['include'] ) ) ) );
            if ( $ids ) { $args['include'] = $ids; unset( $args['number'], $args['offset'] ); }
        }
        $archive_filter = sanitize_key( (string) $r->get_param( 'archived' ) );
        $archived_branch_ids = $archive_filter ? $this->archived_branch_ids( $tax ) : array();
        if ( 'archived' === $archive_filter ) {
            if ( ! $archived_branch_ids ) {
                $response = rest_ensure_response( array() );
                $response->header( 'X-WP-Total', 0 );
                $response->header( 'X-WP-TotalPages', 1 );
                return $response;
            }
            if ( ! empty( $args['include'] ) ) {
                $args['include'] = array_values( array_intersect( array_map( 'intval', (array) $args['include'] ), $archived_branch_ids ) );
            } else {
                $args['include'] = $archived_branch_ids;
            }
        } elseif ( 'active' === $archive_filter && $archived_branch_ids ) {
            $args['exclude'] = array_values( array_unique( array_merge( isset( $args['exclude'] ) ? (array) $args['exclude'] : array(), $archived_branch_ids ) ) );
        }

        $terms = get_terms( $args );
        if ( is_wp_error( $terms ) ) { return $terms; }

        $count_args = $args;
        unset( $count_args['number'], $count_args['offset'] );
        $count_args['fields'] = 'count';
        $count = get_terms( $count_args );
        if ( is_wp_error( $count ) ) { return $count; }
        $total = (int) $count;

        $rows = array();
        foreach ( $terms as $term ) { $rows[] = $this->term_row( $term, $tax ); }

        $response = rest_ensure_response( $rows );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', max( 1, (int) ceil( $total / $per ) ) );
        return $response;
    }

    public function create_term( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        if ( ! $this->taxonomy_cap_check( $r, 'manage_terms' ) ) { return new WP_Error( 'ninecm_forbidden', 'You cannot create categories in this taxonomy.', array( 'status' => 403 ) ); }

        $name = sanitize_text_field( $r['name'] );
        if ( ! $name ) { return new WP_Error( 'ninecm_name', 'Name is required.', array( 'status' => 400 ) ); }
        $tax_obj = get_taxonomy( $tax );
        $parent = $tax_obj && $tax_obj->hierarchical ? absint( $r['parent'] ) : 0;
        if ( $parent && ! term_exists( $parent, $tax ) ) { return new WP_Error( 'ninecm_parent', 'The selected parent term does not exist.', array( 'status' => 400 ) ); }
        if ( $parent && $this->term_in_archived_branch( $parent, $tax ) ) { return new WP_Error( 'ninecm_archived_parent', 'Archived branches are frozen. Unarchive the parent branch before creating new children there.', array( 'status' => 409 ) ); }

        $args = array( 'description' => sanitize_textarea_field( $r['description'] ?: '' ) );
        if ( $tax_obj && $tax_obj->hierarchical ) { $args['parent'] = $parent; }
        $slug = sanitize_title( $r['slug'] ?: '' );
        if ( $slug ) { $args['slug'] = $slug; }

        $res = wp_insert_term( $name, $tax, $args );
        if ( is_wp_error( $res ) ) { return $res; }
        $term_id = (int) $res['term_id'];
        if ( null !== $r->get_param( 'order' ) ) { $this->set_order_meta( $term_id, $r['order'] ); }
        if ( null !== $r->get_param( 'protected' ) ) { $this->set_boolean_term_meta( $term_id, 'ninecm_protected', $r['protected'] ); }
        if ( null !== $r->get_param( 'archived' ) ) { $this->set_boolean_term_meta( $term_id, 'ninecm_archived', $r['archived'] ); }
        return rest_ensure_response( $this->term_row( get_term( $term_id, $tax ), $tax ) );
    }

    public function update_term( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        if ( ! $this->taxonomy_cap_check( $r, 'edit_terms' ) ) { return new WP_Error( 'ninecm_forbidden', 'You cannot edit categories in this taxonomy.', array( 'status' => 403 ) ); }

        $id = absint( $r['id'] );
        if ( ! term_exists( $id, $tax ) ) { return new WP_Error( 'ninecm_term', 'Category not found.', array( 'status' => 404 ) ); }
        $term_obj = get_term( $id, $tax );
        $is_protected = (bool) get_term_meta( $id, 'ninecm_protected', true );
        $is_archived = $this->term_in_archived_branch( $id, $tax );
        $args = array();
        if ( null !== $r->get_param( 'name' ) ) { $args['name'] = sanitize_text_field( $r['name'] ); }
        if ( null !== $r->get_param( 'slug' ) ) { $args['slug'] = sanitize_title( $r['slug'] ); }
        if ( null !== $r->get_param( 'description' ) ) { $args['description'] = sanitize_textarea_field( $r['description'] ); }
        $tax_obj = get_taxonomy( $tax );
        if ( $tax_obj && $tax_obj->hierarchical && null !== $r->get_param( 'parent' ) ) {
            $parent = absint( $r['parent'] );
            if ( $is_protected && $term_obj && ! is_wp_error( $term_obj ) && $parent !== (int) $term_obj->parent ) {
                return new WP_Error( 'ninecm_protected_branch', 'This category is protected. Unprotect it before moving the branch.', array( 'status' => 409 ) );
            }
            if ( $is_archived && $term_obj && ! is_wp_error( $term_obj ) && $parent !== (int) $term_obj->parent ) {
                return new WP_Error( 'ninecm_archived_branch', 'This category is inside an archived branch. Unarchive the branch before moving it.', array( 'status' => 409 ) );
            }
            if ( $parent === $id ) { return new WP_Error( 'ninecm_parent', 'A category cannot be its own parent.', array( 'status' => 400 ) ); }
            if ( $parent && ! term_exists( $parent, $tax ) ) { return new WP_Error( 'ninecm_parent', 'Parent category not found.', array( 'status' => 400 ) ); }
            if ( $parent && term_is_ancestor_of( $id, $parent, $tax ) ) { return new WP_Error( 'ninecm_parent_cycle', 'A category cannot be moved beneath one of its own descendants.', array( 'status' => 400 ) ); }
            if ( $parent && $this->term_in_archived_branch( $parent, $tax ) ) { return new WP_Error( 'ninecm_archived_parent', 'A category cannot be moved beneath an archived branch. Unarchive that branch first.', array( 'status' => 409 ) ); }
            $args['parent'] = $parent;
        }
        $has_order = null !== $r->get_param( 'order' );
        $has_protected = null !== $r->get_param( 'protected' );
        $has_archived = null !== $r->get_param( 'archived' );
        if ( ( $has_protected || $has_archived ) && ! $this->taxonomy_cap_check( $r, 'manage_terms' ) ) {
            return new WP_Error( 'ninecm_structure_state_forbidden', 'You cannot change protection/archive state in this taxonomy.', array( 'status' => 403 ) );
        }
        if ( $args ) {
            $res = wp_update_term( $id, $tax, $args );
            if ( is_wp_error( $res ) ) { return $res; }
        } elseif ( ! $has_order && ! $has_protected && ! $has_archived ) {
            return rest_ensure_response( $this->term_row( get_term( $id, $tax ), $tax ) );
        }
        if ( $has_order ) { $this->set_order_meta( $id, $r['order'] ); }
        if ( $has_protected ) { $this->set_boolean_term_meta( $id, 'ninecm_protected', $r['protected'] ); }
        if ( $has_archived ) { $this->set_boolean_term_meta( $id, 'ninecm_archived', $r['archived'] ); }
        return rest_ensure_response( $this->term_row( get_term( $id, $tax ), $tax ) );
    }

    public function delete_term( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        if ( ! $this->taxonomy_cap_check( $r, 'delete_terms' ) ) { return new WP_Error( 'ninecm_forbidden', 'You cannot delete categories in this taxonomy.', array( 'status' => 403 ) ); }
        $id = absint( $r['id'] );
        $term = get_term( $id, $tax );
        if ( ! $term || is_wp_error( $term ) ) { return new WP_Error( 'ninecm_term', 'Category not found.', array( 'status' => 404 ) ); }
        if ( get_term_meta( $id, 'ninecm_protected', true ) ) {
            return new WP_Error( 'ninecm_protected_branch', 'This category is protected. Unprotect it before deletion.', array( 'status' => 409 ) );
        }

        $children = get_terms( array( 'taxonomy' => $tax, 'parent' => $id, 'hide_empty' => false, 'fields' => 'ids', 'number' => 2 ) );
        if ( is_wp_error( $children ) ) { return $children; }
        $destructive = ( (int) $term->count > 0 || ! empty( $children ) );
        if ( $destructive && ! rest_sanitize_boolean( $r->get_param( 'force' ) ) ) {
            return new WP_Error(
                'ninecm_delete_confirmation',
                sprintf(
                    'This category has %d assigned item(s)%s. Deleting it changes planning relationships. Confirm destructive deletion to continue.',
                    (int) $term->count,
                    $children ? ' and child categories' : ''
                ),
                array( 'status' => 409, 'assigned' => (int) $term->count, 'has_children' => ! empty( $children ) )
            );
        }
        $res = wp_delete_term( $id, $tax );
        if ( is_wp_error( $res ) ) { return $res; }
        return rest_ensure_response( $res );
    }

    public function bulk_terms( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        if ( ! $this->taxonomy_cap_check( $r, 'manage_terms' ) ) { return new WP_Error( 'ninecm_forbidden', 'You cannot create terms in this taxonomy.', array( 'status' => 403 ) ); }

        $paths = $r->get_param( 'paths' );
        if ( is_array( $paths ) ) {
            if ( count( $paths ) > 250 ) { return new WP_Error( 'ninecm_bulk_limit', 'Process no more than 250 hierarchy paths in one request.', array( 'status' => 400 ) ); }
            $segment_budget = 0;
            foreach ( $paths as $segments ) {
                if ( is_string( $segments ) ) { $segments = preg_split( '/\s*(?:›|>)\s*/u', $segments ); }
                $segment_budget += is_array( $segments ) ? count( $segments ) : 0;
                if ( $segment_budget > 2000 ) {
                    return new WP_Error( 'ninecm_bulk_segment_limit', 'This hierarchy batch is too deep/large. Send fewer paths per request.', array( 'status' => 400 ) );
                }
            }
            return rest_ensure_response( $this->create_paths( $paths, $tax, rest_sanitize_boolean( $r->get_param( 'restore_mode' ) ) ) );
        }

        // Backwards-compatible text mode. The current admin UI uses chunked path mode to
        // avoid long-running requests; direct API callers remain supported with a lower cap.
        $text = (string) $r['text'];
        $lines = preg_split( '/\R/', $text );
        if ( count( $lines ) > 1000 ) { return new WP_Error( 'ninecm_bulk_limit', 'For stability, text mode is limited to 1,000 lines. Use chunked path mode for larger plans.', array( 'status' => 400 ) ); }

        $stack = array();
        $paths = array();
        $errors = array();
        foreach ( $lines as $line_number => $line ) {
            if ( ! trim( $line ) ) { continue; }
            preg_match( '/^\s*(>*)(.*)$/', $line, $m );
            $depth = strlen( $m[1] );
            $name = sanitize_text_field( trim( $m[2] ) );
            if ( ! $name ) { continue; }
            if ( $depth > 50 ) { $errors[] = sprintf( 'Line %d (%s): hierarchy depth is too large.', $line_number + 1, $name ); continue; }
            if ( $depth > 0 && ! isset( $stack[ $depth - 1 ] ) ) { $errors[] = sprintf( 'Line %d (%s): missing parent level.', $line_number + 1, $name ); continue; }
            $stack[ $depth ] = $name;
            foreach ( array_keys( $stack ) as $k ) { if ( $k > $depth ) { unset( $stack[ $k ] ); } }
            $paths[] = array_values( array_slice( $stack, 0, $depth + 1, true ) );
        }
        $result = $this->create_paths( $paths, $tax, rest_sanitize_boolean( $r->get_param( 'restore_mode' ) ) );
        $result['errors'] = array_merge( $errors, $result['errors'] );
        return rest_ensure_response( $result );
    }

    private function create_paths( $paths, $tax, $allow_archived = false ) {
        $created = 0;
        $existing = 0;
        $errors = array();
        $cache = array();
        $tax_obj = get_taxonomy( $tax );
        $hierarchical = $tax_obj && ! empty( $tax_obj->hierarchical );

        foreach ( $paths as $path_index => $segments ) {
            if ( is_string( $segments ) ) { $segments = preg_split( '/\s*(?:›|>)\s*/u', $segments ); }
            if ( ! is_array( $segments ) || ! $segments ) { continue; }
            if ( ! $hierarchical ) { $segments = array( end( $segments ) ); }
            if ( count( $segments ) > 51 ) { $errors[] = sprintf( 'Path %d: hierarchy depth is too large.', $path_index + 1 ); continue; }
            $parent = 0;
            foreach ( $segments as $segment ) {
                $name = sanitize_text_field( trim( (string) $segment ) );
                if ( ! $name ) { continue 2; }
                $cache_key = $parent . '|' . strtolower( $name );
                if ( isset( $cache[ $cache_key ] ) ) {
                    $parent = $cache[ $cache_key ];
                    continue;
                }
                $exists = term_exists( $name, $tax, $parent );
                if ( $exists ) {
                    $term_id = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;
                    if ( ! $allow_archived && $this->term_in_archived_branch( $term_id, $tax ) ) {
                        $errors[] = sprintf( 'Path %d (%s): this path enters an archived branch. Unarchive it before extending the hierarchy.', $path_index + 1, $name );
                        continue 2;
                    }
                    $existing++;
                } else {
                    $res = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
                    if ( is_wp_error( $res ) ) { $errors[] = sprintf( 'Path %d (%s): %s', $path_index + 1, $name, $res->get_error_message() ); continue 2; }
                    $term_id = (int) $res['term_id'];
                    $created++;
                }
                $cache[ $cache_key ] = $term_id;
                $parent = $term_id;
            }
        }
        return compact( 'created', 'existing', 'errors' );
    }

    private function post_terms( $post_id, $taxonomy ) {
        if ( ! is_object_in_taxonomy( get_post_type( $post_id ), $taxonomy ) ) { return array(); }
        $terms = wp_get_object_terms( $post_id, $taxonomy, array( 'orderby' => 'name', 'order' => 'ASC' ) );
        if ( is_wp_error( $terms ) ) { return array(); }
        $rows = array();
        foreach ( $terms as $term ) { $rows[] = array( 'id' => (int) $term->term_id, 'name' => $term->name, 'path' => self::term_path( $term, $taxonomy ), 'archived' => $this->term_in_archived_branch( $term->term_id, $taxonomy ) ); }
        return $rows;
    }

    public function posts( WP_REST_Request $r ) {
        $pto = $this->valid_post_type( $r['post_type'] ?: 'page' );
        if ( is_wp_error( $pto ) ) { return $pto; }
        if ( empty( $pto->cap->edit_posts ) || ! current_user_can( $pto->cap->edit_posts ) ) { return new WP_Error( 'ninecm_forbidden', 'You cannot edit this content type.', array( 'status' => 403 ) ); }
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }

        $page = max( 1, absint( $r['page'] ?: 1 ) );
        $per  = min( 100, max( 10, absint( $r['per_page'] ?: 30 ) ) );
        $taxonomy_compatible = is_object_in_taxonomy( $pto->name, $tax );
        $statuses = array( 'publish', 'draft', 'pending', 'future' );
        if ( ! empty( $pto->cap->read_private_posts ) && current_user_can( $pto->cap->read_private_posts ) ) { $statuses[] = 'private'; }
        $requested_status = sanitize_key( (string) $r->get_param( 'status' ) );
        $query_statuses = $requested_status && in_array( $requested_status, $statuses, true ) ? array( $requested_status ) : $statuses;
        $args = array(
            'post_type'              => $pto->name,
            'post_status'            => $query_statuses,
            's'                      => sanitize_text_field( $r['search'] ?: '' ),
            'paged'                  => $page,
            'posts_per_page'         => $per,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => true,
        );
        if ( empty( $pto->cap->edit_others_posts ) || ! current_user_can( $pto->cap->edit_others_posts ) ) { $args['author'] = get_current_user_id(); }
        if ( $r['term'] && $taxonomy_compatible ) {
            $args['tax_query'] = array( array( 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => array( absint( $r['term'] ) ), 'include_children' => false ) );
        } elseif ( $taxonomy_compatible ) {
            $relationship_scope = sanitize_key( (string) $r->get_param( 'relationship_scope' ) );
            if ( in_array( $relationship_scope, array( 'assigned', 'unassigned' ), true ) ) {
                $args['tax_query'] = array( array( 'taxonomy' => $tax, 'operator' => 'assigned' === $relationship_scope ? 'EXISTS' : 'NOT EXISTS' ) );
            }
        }
        $q = new WP_Query( $args );
        $rows = array();
        foreach ( $q->posts as $p ) {
            if ( ! current_user_can( 'edit_post', $p->ID ) ) { continue; }
            $rows[] = array(
                'id'       => (int) $p->ID,
                'title'    => get_the_title( $p ),
                'excerpt'  => $p->post_excerpt,
                'status'   => $p->post_status,
                'type'     => $p->post_type,
                'modified' => get_post_modified_time( 'c', true, $p ),
                'terms'    => $taxonomy_compatible ? $this->post_terms( $p->ID, $tax ) : array(),
                'taxonomyCompatible' => $taxonomy_compatible,
                'view'     => 'publish' === $p->post_status ? get_permalink( $p ) : get_preview_post_link( $p ),
                'edit'     => get_edit_post_link( $p->ID, 'raw' ),
            );
        }
        $response = rest_ensure_response( $rows );
        $response->header( 'X-WP-Total', (int) $q->found_posts );
        $response->header( 'X-WP-TotalPages', max( 1, (int) $q->max_num_pages ) );
        return $response;
    }

    private function structure_undo_key( $post_type ) {
        return 'ninecm_struct_undo_' . get_current_user_id() . '_' . md5( sanitize_key( $post_type ) );
    }

    public function bulk_structure( WP_REST_Request $r ) {
        $pto = $this->valid_post_type( $r->get_param( 'post_type' ) ?: 'page' );
        if ( is_wp_error( $pto ) ) { return $pto; }
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $r->get_param( 'post_ids' ) ) ) ) );
        if ( ! $ids ) { return new WP_Error( 'ninecm_posts', 'Select at least one content item.', array( 'status' => 400 ) ); }
        if ( count( $ids ) > 200 ) { return new WP_Error( 'ninecm_bulk_structure_limit', 'Update no more than 200 content items at once.', array( 'status' => 400 ) ); }
        $action = sanitize_key( $r->get_param( 'action_type' ) );
        if ( ! in_array( $action, array( 'author', 'parent', 'order' ), true ) ) { return new WP_Error( 'ninecm_structure_action', 'Choose author, parent or order.', array( 'status' => 400 ) ); }
        $descriptor = NineCM_Infrastructure::post_type_descriptor( $pto );
        if ( 'author' === $action && empty( $descriptor['supports']['author'] ) ) { return new WP_Error( 'ninecm_author_unsupported', 'This content type does not expose native author assignment.', array( 'status' => 400 ) ); }
        if ( 'parent' === $action && empty( $descriptor['supportsParent'] ) ) { return new WP_Error( 'ninecm_parent_unsupported', 'This content type does not use a native parent hierarchy.', array( 'status' => 400 ) ); }
        if ( 'order' === $action && empty( $descriptor['supportsOrder'] ) ) { return new WP_Error( 'ninecm_order_unsupported', 'This content type does not expose native manual ordering.', array( 'status' => 400 ) ); }

        $before = array(); $updated = 0; $skipped = array(); $position = 0;
        $start = max( -999999, min( 999999, intval( $r->get_param( 'order_start' ) ) ) );
        $step = max( -10000, min( 10000, intval( $r->get_param( 'order_step' ) ?: 1 ) ) );
        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( ! $post || $post->post_type !== $pto->name || ! current_user_can( 'edit_post', $id ) ) { $skipped[] = $id; continue; }
            $before[ $id ] = array( 'post_author' => (int) $post->post_author, 'post_parent' => (int) $post->post_parent, 'menu_order' => (int) $post->menu_order );
            $update = array( 'ID' => $id );
            if ( 'author' === $action ) {
                $author = $this->validate_author( $r->get_param( 'author' ), $pto, $post->post_author );
                if ( is_wp_error( $author ) ) { $skipped[] = $id; unset( $before[ $id ] ); continue; }
                $update['post_author'] = $author;
            } elseif ( 'parent' === $action ) {
                $parent = $this->validate_parent( $r->get_param( 'parent' ), $pto, $id );
                if ( is_wp_error( $parent ) ) { $skipped[] = $id; unset( $before[ $id ] ); continue; }
                $update['post_parent'] = $parent;
            } else {
                $update['menu_order'] = max( -999999, min( 999999, $start + ( $position * $step ) ) );
                $position++;
            }
            $result = wp_update_post( $update, true );
            if ( is_wp_error( $result ) ) { $skipped[] = $id; unset( $before[ $id ] ); continue; }
            $updated++;
        }
        if ( $before ) {
            set_transient( $this->structure_undo_key( $pto->name ), array( 'post_type' => $pto->name, 'action' => $action, 'before' => $before, 'created' => time(), 'expires' => time() + 30 * MINUTE_IN_SECONDS ), 30 * MINUTE_IN_SECONDS );
        }
        return rest_ensure_response( array( 'updated' => $updated, 'skipped' => $skipped, 'undoAvailable' => (bool) $before ) );
    }

    public function undo_structure_status( WP_REST_Request $r ) {
        $pto = $this->valid_post_type( $r->get_param( 'post_type' ) ?: 'page' );
        if ( is_wp_error( $pto ) ) { return $pto; }
        $data = get_transient( $this->structure_undo_key( $pto->name ) );
        return rest_ensure_response( $data && ! empty( $data['before'] ) ? array( 'available' => true, 'action' => $data['action'], 'count' => count( $data['before'] ), 'expires' => (int) $data['expires'] ) : array( 'available' => false ) );
    }

    public function undo_structure( WP_REST_Request $r ) {
        $pto = $this->valid_post_type( $r->get_param( 'post_type' ) ?: 'page' );
        if ( is_wp_error( $pto ) ) { return $pto; }
        $key = $this->structure_undo_key( $pto->name ); $data = get_transient( $key );
        if ( empty( $data['before'] ) || ! is_array( $data['before'] ) ) { return new WP_Error( 'ninecm_no_structure_undo', 'There is no recent structural bulk change to undo.', array( 'status' => 404 ) ); }
        $restored = 0; $skipped = array(); $action = sanitize_key( $data['action'] ?? '' );
        foreach ( $data['before'] as $id => $old ) {
            $id = absint( $id ); $post = get_post( $id );
            if ( ! $post || $post->post_type !== $pto->name || ! current_user_can( 'edit_post', $id ) ) { $skipped[] = $id; continue; }
            $restore = array( 'ID' => $id );
            if ( 'author' === $action ) {
                $old_author = absint( $old['post_author'] ?? 0 );
                if ( ! $old_author || ! get_userdata( $old_author ) ) { $skipped[] = $id; continue; }
                $restore['post_author'] = $old_author;
            } elseif ( 'parent' === $action ) {
                $old_parent = absint( $old['post_parent'] ?? 0 );
                $valid_parent = $this->validate_parent( $old_parent, $pto, $id );
                if ( is_wp_error( $valid_parent ) ) { $skipped[] = $id; continue; }
                $restore['post_parent'] = $valid_parent;
            } elseif ( 'order' === $action ) {
                $restore['menu_order'] = intval( $old['menu_order'] ?? 0 );
            } else { $skipped[] = $id; continue; }
            $res = wp_update_post( $restore, true );
            if ( is_wp_error( $res ) ) { $skipped[] = $id; continue; }
            $restored++;
        }
        delete_transient( $key );
        return rest_ensure_response( array( 'restored' => $restored, 'skipped' => $skipped ) );
    }

    private function validated_term_ids( $ids, $taxonomy, $allow_archived = false ) {
        $raw = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
        $out = array(); $invalid = array(); $archived = array();
        foreach ( $raw as $id ) {
            if ( ! term_exists( $id, $taxonomy ) ) { $invalid[] = $id; continue; }
            if ( ! $allow_archived && $this->term_in_archived_branch( $id, $taxonomy ) ) { $archived[] = $id; continue; }
            $out[] = $id;
        }
        if ( $invalid ) {
            return new WP_Error( 'ninecm_stale_terms', 'One or more selected categories no longer exist. Refresh the category picker before saving.', array( 'status' => 409, 'term_ids' => $invalid ) );
        }
        if ( $archived ) {
            return new WP_Error( 'ninecm_archived_terms', 'One or more selected categories are in an archived branch. Unarchive the branch before making a new allocation there.', array( 'status' => 409, 'term_ids' => $archived ) );
        }
        return $out;
    }

    public function assign( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        if ( ! $this->can_assign_terms( $tax ) ) { return new WP_Error( 'ninecm_assign_forbidden', 'You cannot assign terms from this taxonomy.', array( 'status' => 403 ) ); }

        $post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $r['post_ids'] ) ) ) );
        if ( count( $post_ids ) > 200 ) { return new WP_Error( 'ninecm_assign_limit', 'For stability, update no more than 200 items per assignment request.', array( 'status' => 400 ) ); }
        $mode = sanitize_key( $r['mode'] ?: 'add' );
        if ( ! in_array( $mode, array( 'add', 'remove', 'replace' ), true ) ) { return new WP_Error( 'ninecm_mode', 'Invalid relationship mode.', array( 'status' => 400 ) ); }
        $term_ids = $this->validated_term_ids( $r['term_ids'], $tax, 'remove' === $mode );
        if ( is_wp_error( $term_ids ) ) { return $term_ids; }
        if ( ! $post_ids ) { return new WP_Error( 'ninecm_posts', 'Select at least one item.', array( 'status' => 400 ) ); }
        if ( 'replace' !== $mode && ! $term_ids ) { return new WP_Error( 'ninecm_terms', 'Select at least one category.', array( 'status' => 400 ) ); }

        $done = 0;
        $skipped = array();
        $before = array();
        foreach ( $post_ids as $pid ) {
            if ( ! current_user_can( 'edit_post', $pid ) ) { $skipped[] = $pid; continue; }
            $type = get_post_type( $pid );
            if ( ! $type || ! is_object_in_taxonomy( $type, $tax ) ) { $skipped[] = $pid; continue; }
            $current = wp_get_object_terms( $pid, $tax, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $current ) ) { $skipped[] = $pid; continue; }
            $current = array_map( 'intval', $current );
            $before[ $pid ] = $current;
            if ( 'remove' === $mode ) {
                $new = array_values( array_diff( $current, $term_ids ) );
                $res = wp_set_object_terms( $pid, $new, $tax, false );
            } elseif ( 'replace' === $mode ) {
                $res = wp_set_object_terms( $pid, $term_ids, $tax, false );
            } else {
                $res = wp_set_object_terms( $pid, $term_ids, $tax, true );
            }
            if ( is_wp_error( $res ) ) { unset( $before[ $pid ] ); $skipped[] = $pid; continue; }
            $done++;
        }
        if ( $done && $before ) {
            set_transient( $this->undo_key( $tax ), array( 'taxonomy' => $tax, 'before' => $before, 'created' => time(), 'expires' => time() + ( 30 * MINUTE_IN_SECONDS ) ), 30 * MINUTE_IN_SECONDS );
        }
        return rest_ensure_response( array( 'updated' => $done, 'skipped' => $skipped, 'undoAvailable' => (bool) ( $done && $before ) ) );
    }

    private function undo_key( $taxonomy ) {
        return 'ninecm_undo_' . get_current_user_id() . '_' . md5( sanitize_key( $taxonomy ) );
    }

    public function undo_assignment_status( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        $data = get_transient( $this->undo_key( $tax ) );
        return rest_ensure_response( array( 'available' => ! empty( $data['before'] ), 'count' => ! empty( $data['before'] ) ? count( $data['before'] ) : 0, 'expires' => ! empty( $data['expires'] ) ? (int) $data['expires'] : 0 ) );
    }

    public function undo_assignment( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        if ( ! $this->can_assign_terms( $tax ) ) { return new WP_Error( 'ninecm_assign_forbidden', 'You cannot restore assignments in this taxonomy.', array( 'status' => 403 ) ); }
        $key = $this->undo_key( $tax );
        $data = get_transient( $key );
        if ( empty( $data['before'] ) || ! is_array( $data['before'] ) ) { return new WP_Error( 'ninecm_no_undo', 'There is no recent allocation to undo.', array( 'status' => 404 ) ); }
        $restored = 0; $skipped = array();
        foreach ( $data['before'] as $pid => $term_ids ) {
            $pid = absint( $pid );
            if ( ! $pid || ! current_user_can( 'edit_post', $pid ) || ! is_object_in_taxonomy( get_post_type( $pid ), $tax ) ) { $skipped[] = $pid; continue; }
            // Undo restores a proven before-state, including historical relationships that may now sit in an archived branch.
            $valid = $this->validated_term_ids( $term_ids, $tax, true );
            if ( is_wp_error( $valid ) ) { $skipped[] = $pid; continue; }
            $res = wp_set_object_terms( $pid, $valid, $tax, false );
            if ( is_wp_error( $res ) ) { $skipped[] = $pid; continue; }
            $restored++;
        }
        delete_transient( $key );
        return rest_ensure_response( array( 'restored' => $restored, 'skipped' => $skipped ) );
    }


    public function restore_term_state( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        if ( ! $this->taxonomy_cap_check( $r, 'manage_terms' ) ) { return new WP_Error( 'ninecm_forbidden', 'You cannot restore category state in this taxonomy.', array( 'status' => 403 ) ); }
        $rows = $r->get_param( 'rows' );
        if ( ! is_array( $rows ) || ! $rows ) { return new WP_Error( 'ninecm_restore_rows', 'Structure-state rows are required.', array( 'status' => 400 ) ); }
        if ( count( $rows ) > 100 ) { return new WP_Error( 'ninecm_restore_limit', 'Restore no more than 100 category-state rows per request.', array( 'status' => 400 ) ); }
        $restored = 0; $errors = array();
        foreach ( $rows as $i => $row ) {
            if ( ! is_array( $row ) ) { $errors[] = sprintf( 'Row %d: invalid state row.', $i + 1 ); continue; }
            $resolved = $this->resolve_term_path( $row['path'] ?? '', $tax, true );
            if ( is_wp_error( $resolved ) ) { $errors[] = sprintf( 'Row %d: %s', $i + 1, $resolved->get_error_message() ); continue; }
            $id = (int) $resolved['term_id'];
            if ( array_key_exists( 'display_order', $row ) || array_key_exists( 'order', $row ) ) {
                $this->set_order_meta( $id, array_key_exists( 'display_order', $row ) ? $row['display_order'] : $row['order'] );
            }
            if ( array_key_exists( 'protected', $row ) ) { $this->set_boolean_term_meta( $id, 'ninecm_protected', $row['protected'] ); }
            if ( array_key_exists( 'archived', $row ) ) { $this->set_boolean_term_meta( $id, 'ninecm_archived', $row['archived'] ); }
            $restored++;
        }
        return rest_ensure_response( array( 'restored' => $restored, 'errors' => $errors ) );
    }


    private function resolve_term_path( $path, $taxonomy, $allow_archived = false ) {
        $segments = is_array( $path ) ? $path : preg_split( '/\s*(?:›|>|\/|\\\\)\s*/u', (string) $path );
        $segments = array_values( array_filter( array_map( function( $v ) { return sanitize_text_field( trim( (string) $v ) ); }, (array) $segments ) ) );
        if ( ! $segments ) { return new WP_Error( 'ninecm_shell_path', 'A taxonomy term/path is required.' ); }
        $tax_obj = get_taxonomy( $taxonomy );
        if ( $tax_obj && empty( $tax_obj->hierarchical ) && count( $segments ) > 1 ) { return new WP_Error( 'ninecm_shell_path_flat', 'Flat tag-like taxonomies accept one term name, not a hierarchy path.' ); }
        $parent = 0; $term = null;
        foreach ( $segments as $segment ) {
            $exists = term_exists( $segment, $taxonomy, $parent );
            if ( ! $exists ) { return new WP_Error( 'ninecm_shell_path_missing', sprintf( 'Category path not found at “%s”. Build the hierarchy first.', $segment ) ); }
            $id = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;
            $term = get_term( $id, $taxonomy );
            if ( ! $term || is_wp_error( $term ) ) { return new WP_Error( 'ninecm_shell_path_missing', 'Category path could not be resolved.' ); }
            if ( ! $allow_archived && get_term_meta( $id, 'ninecm_archived', true ) ) {
                return new WP_Error( 'ninecm_shell_path_archived', sprintf( 'Category “%s” is archived. Unarchive the branch before creating new planning shells there.', $term->name ) );
            }
            $parent = $id;
        }
        return array( 'term_id' => (int) $term->term_id, 'path' => self::term_path( $term, $taxonomy ) );
    }

    private function existing_plan_shell( $plan_key, $post_type ) {
        if ( ! $plan_key ) { return 0; }
        $ids = get_posts( array(
            'post_type' => $post_type,
            'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
            'author' => get_current_user_id(),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_ninecm_plan_key',
            'meta_value' => $plan_key,
            'no_found_rows' => true,
            'suppress_filters' => false,
        ) );
        return $ids ? (int) $ids[0] : 0;
    }

    public function bulk_shells( WP_REST_Request $r ) {
        $tax = $this->valid_taxonomy( $r );
        if ( is_wp_error( $tax ) ) { return $tax; }
        $pto = $this->valid_post_type( $r['post_type'] ?: 'page' );
        if ( is_wp_error( $pto ) ) { return $pto; }
        $descriptor = NineCM_Infrastructure::post_type_descriptor( $pto );
        if ( empty( $descriptor['canCreate'] ) ) { return new WP_Error( 'ninecm_create', 'This content type is not eligible for generic planning-shell creation. It may be a technical post type or your role may not be allowed to create it.', array( 'status' => 403 ) ); }
        if ( ! is_object_in_taxonomy( $pto->name, $tax ) ) { return new WP_Error( 'ninecm_relationship', 'This taxonomy is not enabled for the selected content type.', array( 'status' => 400 ) ); }
        if ( ! $this->can_assign_terms( $tax ) ) { return new WP_Error( 'ninecm_assign_forbidden', 'You cannot assign terms from this taxonomy.', array( 'status' => 403 ) ); }

        $rows = $r->get_param( 'rows' );
        if ( ! is_array( $rows ) || ! $rows ) { return new WP_Error( 'ninecm_shell_rows', 'Provide at least one page-shell row.', array( 'status' => 400 ) ); }
        if ( count( $rows ) > 50 ) { return new WP_Error( 'ninecm_shell_limit', 'Create no more than 50 page shells per request.', array( 'status' => 400 ) ); }

        $created = 0; $reused = 0; $errors = array(); $items = array();
        foreach ( $rows as $index => $row ) {
            $title = sanitize_text_field( $row['title'] ?? '' );
            $excerpt = sanitize_textarea_field( $row['excerpt'] ?? '' );
            if ( ! $title ) { $errors[] = sprintf( 'Row %d: title is required.', $index + 1 ); continue; }
            $resolved = $this->resolve_term_path( $row['path'] ?? '', $tax );
            if ( is_wp_error( $resolved ) ) { $errors[] = sprintf( 'Row %d (%s): %s', $index + 1, $title, $resolved->get_error_message() ); continue; }
            $plan_key = md5( get_current_user_id() . '|' . $pto->name . '|' . $tax . '|' . $resolved['term_id'] . '|' . $title . '|' . $excerpt );
            $existing = $this->existing_plan_shell( $plan_key, $pto->name );
            if ( $existing && current_user_can( 'edit_post', $existing ) ) {
                $reused++;
                $items[] = array( 'id' => $existing, 'title' => get_the_title( $existing ), 'path' => $resolved['path'], 'reused' => true, 'view' => get_preview_post_link( $existing ) );
                continue;
            }
            $pid = wp_insert_post( array(
                'post_type' => $pto->name,
                'post_status' => 'draft',
                'post_title' => wp_slash( $title ),
                'post_excerpt' => ! empty( $descriptor['supports']['excerpt'] ) ? wp_slash( $excerpt ) : '',
                'post_content' => '',
                'post_author' => get_current_user_id(),
                'meta_input' => array( '_ninecm_shell' => 1, '_ninecm_plan_key' => $plan_key ),
            ), true );
            if ( is_wp_error( $pid ) ) { $errors[] = sprintf( 'Row %d (%s): %s', $index + 1, $title, $pid->get_error_message() ); continue; }
            $set = wp_set_object_terms( $pid, array( (int) $resolved['term_id'] ), $tax, false );
            if ( is_wp_error( $set ) ) { wp_delete_post( $pid, true ); $errors[] = sprintf( 'Row %d (%s): %s', $index + 1, $title, $set->get_error_message() ); continue; }
            $created++;
            $items[] = array( 'id' => (int) $pid, 'title' => $title, 'path' => $resolved['path'], 'reused' => false, 'view' => get_preview_post_link( $pid ) );
        }
        return rest_ensure_response( array( 'created' => $created, 'reused' => $reused, 'errors' => $errors, 'items' => $items ) );
    }

    private function validate_featured_media( $attachment_id, $post_type, $changing = true ) {
        $attachment_id = absint( $attachment_id );
        if ( ! $attachment_id ) { return 0; }
        if ( ! current_user_can( 'upload_files' ) ) { return new WP_Error( 'ninecm_media_forbidden', 'You cannot assign featured images.', array( 'status' => 403 ) ); }
        if ( ! post_type_supports( $post_type, 'thumbnail' ) ) { return new WP_Error( 'ninecm_media_unsupported', 'This content type does not support featured images.', array( 'status' => 400 ) ); }
        $attachment = get_post( $attachment_id );
        if ( ! $attachment || 'attachment' !== $attachment->post_type || 0 !== strpos( (string) $attachment->post_mime_type, 'image/' ) ) {
            return new WP_Error( 'ninecm_media_invalid', 'Choose a valid image from the Media Library.', array( 'status' => 400 ) );
        }
        return $attachment_id;
    }

    private function validate_author( $author_id, $pto, $current_author = 0 ) {
        $author_id = absint( $author_id );
        if ( ! $author_id ) { return get_current_user_id(); }
        $author_user = get_userdata( $author_id );
        if ( ! $author_user ) { return new WP_Error( 'ninecm_author', 'Selected author does not exist.', array( 'status' => 400 ) ); }
        if ( ! empty( $pto->cap->edit_posts ) && ! user_can( $author_user, $pto->cap->edit_posts ) && $author_id !== absint( $current_author ) ) {
            return new WP_Error( 'ninecm_author_ineligible', 'Selected user cannot author this content type.', array( 'status' => 400 ) );
        }
        if ( $author_id !== get_current_user_id() && $author_id !== absint( $current_author ) ) {
            if ( empty( $pto->cap->edit_others_posts ) || ! current_user_can( $pto->cap->edit_others_posts ) ) {
                return new WP_Error( 'ninecm_author_forbidden', 'You cannot assign another author to this content type.', array( 'status' => 403 ) );
            }
        }
        return $author_id;
    }

    private function validate_parent( $parent_id, $pto, $post_id = 0 ) {
        $parent_id = absint( $parent_id );
        if ( ! $parent_id ) { return 0; }
        if ( empty( $pto->hierarchical ) ) { return new WP_Error( 'ninecm_parent_unsupported', 'This content type does not use a native parent/child hierarchy.', array( 'status' => 400 ) ); }
        $parent = get_post( $parent_id );
        if ( ! $parent || $parent->post_type !== $pto->name ) { return new WP_Error( 'ninecm_parent_invalid', 'Choose a parent from the same content type.', array( 'status' => 400 ) ); }
        if ( $post_id && $parent_id === absint( $post_id ) ) { return new WP_Error( 'ninecm_parent_cycle', 'An item cannot be its own parent.', array( 'status' => 400 ) ); }
        if ( $post_id ) {
            $ancestors = get_post_ancestors( $parent_id );
            if ( in_array( absint( $post_id ), array_map( 'intval', $ancestors ), true ) ) { return new WP_Error( 'ninecm_parent_cycle', 'An item cannot be placed beneath one of its descendants.', array( 'status' => 400 ) ); }
        }
        return $parent_id;
    }

    private function requested_taxonomy_map( WP_REST_Request $r ) {
        $map = $r->get_param( 'taxonomies' );
        $out = array();
        if ( is_array( $map ) ) {
            foreach ( $map as $tax => $ids ) { $out[ sanitize_key( $tax ) ] = (array) $ids; }
        }
        // Backwards compatibility with v1-v3 single-taxonomy calls.
        if ( null !== $r->get_param( 'term_ids' ) ) {
            $tax = sanitize_key( $r->get_param( 'taxonomy' ) ?: 'category' );
            $out[ $tax ] = (array) $r->get_param( 'term_ids' );
        }
        return $out;
    }

    private function validate_taxonomy_map( $post_type, $map, $post_id = 0, $allow_existing_archived = false ) {
        $validated = array();
        foreach ( (array) $map as $tax => $ids ) {
            $tax = sanitize_key( $tax );
            $obj = get_taxonomy( $tax );
            if ( ! $obj || empty( $obj->show_ui ) || ! is_object_in_taxonomy( $post_type, $tax ) ) {
                return new WP_Error( 'ninecm_relationship', sprintf( 'Taxonomy %s is not available for this content type.', $tax ), array( 'status' => 400 ) );
            }
            if ( ! $this->can_assign_terms( $tax ) ) { return new WP_Error( 'ninecm_assign_forbidden', sprintf( 'You cannot assign terms from %s.', $obj->labels->name ), array( 'status' => 403 ) ); }
            $term_ids = $this->validated_term_ids( $ids, $tax, $allow_existing_archived );
            if ( is_wp_error( $term_ids ) ) { return $term_ids; }
            if ( $allow_existing_archived && $post_id ) {
                $old = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
                if ( is_wp_error( $old ) ) { return $old; }
                $old = array_map( 'intval', $old );
                foreach ( $term_ids as $term_id ) {
                    if ( $this->term_in_archived_branch( $term_id, $tax ) && ! in_array( (int) $term_id, $old, true ) ) {
                        return new WP_Error( 'ninecm_archived_terms', 'Archived terms can be preserved or removed, but cannot be newly assigned until they are unarchived.', array( 'status' => 409, 'term_ids' => array( (int) $term_id ), 'taxonomy' => $tax ) );
                    }
                }
            }
            $validated[ $tax ] = $term_ids;
        }
        return $validated;
    }

    private function apply_taxonomy_map( $post_id, $map ) {
        $before = array();
        foreach ( $map as $tax => $ids ) {
            $old = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $old ) ) { return $old; }
            $before[ $tax ] = array_map( 'intval', $old );
            $set = wp_set_object_terms( $post_id, array_map( 'intval', $ids ), $tax, false );
            if ( is_wp_error( $set ) ) {
                foreach ( $before as $restore_tax => $restore_ids ) { wp_set_object_terms( $post_id, $restore_ids, $restore_tax, false ); }
                return $set;
            }
        }
        return $before;
    }

    private function validate_provider_values( $post_id, $post_type, $values ) {
        if ( ! is_array( $values ) || ! $values ) { return true; }
        $providers = NineCM_Infrastructure::relationship_providers( $post_type, $post_id );
        foreach ( $values as $key => $value ) {
            $key = sanitize_key( $key );
            if ( empty( $providers[ $key ] ) ) { return new WP_Error( 'ninecm_provider', 'A requested relationship provider is not available.', array( 'status' => 400, 'provider' => $key ) ); }
            if ( ! empty( $providers[ $key ]['validate_callback'] ) ) {
                try {
                    $valid = call_user_func( $providers[ $key ]['validate_callback'], $value, $post_id, $post_type );
                } catch ( Throwable $e ) {
                    return new WP_Error( 'ninecm_provider_validation_failed', sprintf( 'The relationship provider for %s failed during validation. No changes were made.', $providers[ $key ]['label'] ), array( 'status' => 500, 'provider' => $key ) );
                }
                if ( is_wp_error( $valid ) ) { return $valid; }
                if ( false === $valid ) { return new WP_Error( 'ninecm_provider_value', sprintf( 'Invalid value for %s.', $providers[ $key ]['label'] ), array( 'status' => 400, 'provider' => $key ) ); }
            }
        }
        return true;
    }

    private function apply_provider_values( $post_id, $post_type, $values ) {
        if ( ! is_array( $values ) || ! $values ) { return true; }
        $providers = NineCM_Infrastructure::relationship_providers( $post_type, $post_id );
        $changed = array();
        foreach ( $values as $key => $value ) {
            $key = sanitize_key( $key );
            if ( empty( $providers[ $key ] ) ) { return new WP_Error( 'ninecm_provider', 'A requested relationship provider is not available.', array( 'status' => 400, 'provider' => $key ) ); }
            try {
                $before = call_user_func( $providers[ $key ]['get_callback'], $post_id, $post_type );
                $result = call_user_func( $providers[ $key ]['update_callback'], $post_id, $value, $post_type );
            } catch ( Throwable $e ) {
                $result = new WP_Error( 'ninecm_provider_exception', sprintf( 'The relationship provider for %s failed safely. Other planning data will be rolled back.', $providers[ $key ]['label'] ), array( 'status' => 500, 'provider' => $key ) );
            }
            if ( is_wp_error( $result ) || false === $result ) {
                foreach ( array_reverse( $changed, true ) as $changed_key => $old_value ) {
                    try { call_user_func( $providers[ $changed_key ]['update_callback'], $post_id, $old_value, $post_type ); } catch ( Throwable $rollback_error ) { /* Best effort; caller also rolls back native planning fields. */ }
                }
                return is_wp_error( $result ) ? $result : new WP_Error( 'ninecm_provider_update', sprintf( 'Could not update %s.', $providers[ $key ]['label'] ), array( 'status' => 400 ) );
            }
            $changed[ $key ] = $before;
        }
        return true;
    }

    public function create_post( WP_REST_Request $r ) {
        $pto = $this->valid_post_type( $r['post_type'] ?: 'page' );
        if ( is_wp_error( $pto ) ) { return $pto; }
        $descriptor = NineCM_Infrastructure::post_type_descriptor( $pto );
        if ( empty( $descriptor['canCreate'] ) ) { return new WP_Error( 'ninecm_create', 'This content type is not eligible for generic planning-shell creation. It may be a technical post type or your role may not be allowed to create it.', array( 'status' => 403 ) ); }
        $title = sanitize_text_field( $r['title'] );
        if ( ! $title ) { return new WP_Error( 'ninecm_title', 'A title is required.', array( 'status' => 400 ) ); }

        $request_id = sanitize_key( (string) $r->get_param( 'request_id' ) );
        $request_key = $request_id ? 'ninecm_create_' . md5( get_current_user_id() . '|' . $request_id ) : '';
        if ( $request_key ) {
            $existing_post = absint( get_transient( $request_key ) );
            if ( $existing_post && get_post( $existing_post ) && current_user_can( 'edit_post', $existing_post ) ) {
                return rest_ensure_response( array_merge( NineCM_Infrastructure::planning_payload( $existing_post ), array( 'reused' => true ) ) );
            }
        }

        $tax_map = $this->validate_taxonomy_map( $pto->name, $this->requested_taxonomy_map( $r ) );
        if ( is_wp_error( $tax_map ) ) { return $tax_map; }
        $author_requested = null !== $r->get_param( 'author' );
        if ( $author_requested && empty( $descriptor['supports']['author'] ) ) { return new WP_Error( 'ninecm_author_unsupported', 'This content type does not expose native author assignment.', array( 'status' => 400 ) ); }
        $author = $author_requested || ! empty( $descriptor['supports']['author'] ) ? $this->validate_author( $r->get_param( 'author' ) ?: get_current_user_id(), $pto ) : get_current_user_id();
        if ( is_wp_error( $author ) ) { return $author; }
        $parent = $this->validate_parent( $r->get_param( 'parent' ), $pto );
        if ( is_wp_error( $parent ) ) { return $parent; }
        $featured_requested = null !== $r->get_param( 'featured_media' );
        if ( $featured_requested && empty( $descriptor['supports']['thumbnail'] ) ) { return new WP_Error( 'ninecm_media_unsupported', 'This content type does not support featured images.', array( 'status' => 400 ) ); }
        $featured = $featured_requested ? $this->validate_featured_media( $r->get_param( 'featured_media' ), $pto->name ) : 0;
        if ( is_wp_error( $featured ) ) { return $featured; }
        $order_requested = null !== $r->get_param( 'menu_order' );
        if ( $order_requested && empty( $descriptor['supportsOrder'] ) ) { return new WP_Error( 'ninecm_order_unsupported', 'This content type does not expose native manual ordering.', array( 'status' => 400 ) ); }
        $menu_order = $order_requested ? max( -999999, min( 999999, intval( $r->get_param( 'menu_order' ) ) ) ) : 0;
        $provider_check = $this->validate_provider_values( 0, $pto->name, $r->get_param( 'providers' ) );
        if ( is_wp_error( $provider_check ) ) { return $provider_check; }

        // Infrastructure Manager creates planning shells only. Detailed content/fields belong in 9 Post Editor.
        $pid = wp_insert_post( array(
            'post_type'    => $pto->name,
            'post_status'  => 'draft',
            'post_title'   => wp_slash( $title ),
            'post_excerpt' => ! empty( $descriptor['supports']['excerpt'] ) ? wp_slash( sanitize_textarea_field( $r['excerpt'] ?: '' ) ) : '',
            'post_content' => '',
            'post_author'  => $author,
            'post_parent'  => $parent,
            'menu_order'   => $menu_order,
            'meta_input'   => array( '_ninecm_shell' => 1 ),
        ), true );
        if ( is_wp_error( $pid ) ) { return $pid; }

        $applied = $this->apply_taxonomy_map( $pid, $tax_map );
        if ( is_wp_error( $applied ) ) { wp_delete_post( $pid, true ); return $applied; }
        if ( $featured && ! set_post_thumbnail( $pid, $featured ) ) {
            wp_delete_post( $pid, true );
            return new WP_Error( 'ninecm_media_apply', 'The featured image could not be attached. The planning shell was rolled back.', array( 'status' => 500 ) );
        }
        $provider_result = $this->apply_provider_values( $pid, $pto->name, $r->get_param( 'providers' ) );
        if ( is_wp_error( $provider_result ) ) { wp_delete_post( $pid, true ); return $provider_result; }
        if ( $request_key ) { set_transient( $request_key, (int) $pid, 10 * MINUTE_IN_SECONDS ); }
        return rest_ensure_response( NineCM_Infrastructure::planning_payload( $pid ) );
    }

    public function get_plan( WP_REST_Request $r ) {
        $id = absint( $r['id'] );
        $post = get_post( $id );
        if ( ! $post ) { return new WP_Error( 'ninecm_post', 'Page or post not found.', array( 'status' => 404 ) ); }
        return rest_ensure_response( NineCM_Infrastructure::planning_payload( $post ) );
    }

    public function update_plan( WP_REST_Request $r ) {
        $id = absint( $r['id'] );
        $post = get_post( $id );
        if ( ! $post ) { return new WP_Error( 'ninecm_post', 'Page or post not found.', array( 'status' => 404 ) ); }
        $pto = $this->valid_post_type( $post->post_type );
        if ( is_wp_error( $pto ) ) { return $pto; }

        // Validate the entire requested plan before mutating any WordPress state.
        $tax_requested = $this->requested_taxonomy_map( $r );
        $tax_map = $this->validate_taxonomy_map( $post->post_type, $tax_requested, $id, true );
        if ( is_wp_error( $tax_map ) ) { return $tax_map; }
        $update = array( 'ID' => $id );
        $has_post_update = false;
        if ( null !== $r->get_param( 'title' ) ) {
            if ( ! post_type_supports( $post->post_type, 'title' ) ) { return new WP_Error( 'ninecm_title_unsupported', 'This content type does not expose native title editing.', array( 'status' => 400 ) ); }
            $title = sanitize_text_field( $r->get_param( 'title' ) );
            if ( ! $title ) { return new WP_Error( 'ninecm_title', 'Title cannot be empty.', array( 'status' => 400 ) ); }
            $update['post_title'] = wp_slash( $title ); $has_post_update = true;
        }
        if ( null !== $r->get_param( 'excerpt' ) ) {
            if ( ! post_type_supports( $post->post_type, 'excerpt' ) ) { return new WP_Error( 'ninecm_excerpt_unsupported', 'This content type does not expose native excerpt editing.', array( 'status' => 400 ) ); }
            $update['post_excerpt'] = wp_slash( sanitize_textarea_field( $r->get_param( 'excerpt' ) ) ); $has_post_update = true;
        }
        if ( null !== $r->get_param( 'author' ) ) {
            if ( ! post_type_supports( $post->post_type, 'author' ) ) { return new WP_Error( 'ninecm_author_unsupported', 'This content type does not expose native author assignment.', array( 'status' => 400 ) ); }
            $author = $this->validate_author( $r->get_param( 'author' ), $pto, $post->post_author );
            if ( is_wp_error( $author ) ) { return $author; }
            $update['post_author'] = $author; $has_post_update = true;
        }
        if ( null !== $r->get_param( 'parent' ) ) {
            $parent = $this->validate_parent( $r->get_param( 'parent' ), $pto, $id );
            if ( is_wp_error( $parent ) ) { return $parent; }
            $update['post_parent'] = $parent; $has_post_update = true;
        }
        if ( null !== $r->get_param( 'menu_order' ) ) {
            $descriptor = NineCM_Infrastructure::post_type_descriptor( $pto );
            if ( empty( $descriptor['supportsOrder'] ) ) { return new WP_Error( 'ninecm_order_unsupported', 'This content type does not expose native manual ordering.', array( 'status' => 400 ) ); }
            $update['menu_order'] = max( -999999, min( 999999, intval( $r->get_param( 'menu_order' ) ) ) ); $has_post_update = true;
        }
        $featured_requested = null !== $r->get_param( 'featured_media' );
        if ( $featured_requested && ! post_type_supports( $post->post_type, 'thumbnail' ) ) { return new WP_Error( 'ninecm_media_unsupported', 'This content type does not support featured images.', array( 'status' => 400 ) ); }
        $featured = $featured_requested ? $this->validate_featured_media( $r->get_param( 'featured_media' ), $post->post_type ) : (int) get_post_thumbnail_id( $id );
        if ( is_wp_error( $featured ) ) { return $featured; }
        $provider_check = $this->validate_provider_values( $id, $post->post_type, $r->get_param( 'providers' ) );
        if ( is_wp_error( $provider_check ) ) { return $provider_check; }

        $before = array(
            'post_title' => $post->post_title, 'post_excerpt' => $post->post_excerpt, 'post_author' => (int) $post->post_author,
            'post_parent' => (int) $post->post_parent, 'menu_order' => (int) $post->menu_order,
            'featured' => (int) get_post_thumbnail_id( $id ), 'terms' => array(),
        );
        foreach ( $tax_map as $tax => $ids ) {
            $old = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $old ) ) { return $old; }
            $before['terms'][ $tax ] = array_map( 'intval', $old );
        }
        $rollback = function() use ( $id, $before ) {
            wp_update_post( array( 'ID' => $id, 'post_title' => $before['post_title'], 'post_excerpt' => $before['post_excerpt'], 'post_author' => $before['post_author'], 'post_parent' => $before['post_parent'], 'menu_order' => $before['menu_order'] ) );
            if ( $before['featured'] ) { set_post_thumbnail( $id, $before['featured'] ); } else { delete_post_thumbnail( $id ); }
            foreach ( $before['terms'] as $tax => $ids ) { wp_set_object_terms( $id, $ids, $tax, false ); }
        };

        if ( $has_post_update ) {
            $updated = wp_update_post( $update, true );
            if ( is_wp_error( $updated ) ) { return $updated; }
        }
        if ( $featured_requested ) {
            if ( $featured ) {
                if ( ! set_post_thumbnail( $id, $featured ) || (int) get_post_thumbnail_id( $id ) !== (int) $featured ) {
                    $rollback();
                    return new WP_Error( 'ninecm_media_apply', 'The featured image could not be attached. Other planning changes were rolled back.', array( 'status' => 500 ) );
                }
            } else {
                delete_post_thumbnail( $id );
                if ( get_post_thumbnail_id( $id ) ) {
                    $rollback();
                    return new WP_Error( 'ninecm_media_remove', 'The featured image could not be removed. Other planning changes were rolled back.', array( 'status' => 500 ) );
                }
            }
        }
        if ( $tax_requested ) {
            $applied = $this->apply_taxonomy_map( $id, $tax_map );
            if ( is_wp_error( $applied ) ) { $rollback(); return $applied; }
        }
        $provider_result = $this->apply_provider_values( $id, $post->post_type, $r->get_param( 'providers' ) );
        if ( is_wp_error( $provider_result ) ) { $rollback(); return $provider_result; }
        return rest_ensure_response( NineCM_Infrastructure::planning_payload( $id ) );
    }

}
