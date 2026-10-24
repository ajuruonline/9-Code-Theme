<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Optional WordPress 6.9+ Abilities API bridge.
 * Read-only by design: automation may inspect infrastructure, not mutate it.
 */
class NineCM_Abilities {
    public function __construct() {
        add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
        add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
    }

    public function register_category() {
        if ( ! function_exists( 'wp_register_ability_category' ) ) { return; }
        wp_register_ability_category( 'nine-category-planning', array(
            'label'       => __( '9 Site Infrastructure', 'nine-code-data' ),
            'description' => __( 'Read-only discovery of content types, taxonomies/tags and planning-layer relationships managed by 9 Category Manager.', 'nine-code-data' ),
        ) );
    }

    public function register_abilities() {
        if ( ! function_exists( 'wp_register_ability' ) ) { return; }
        wp_register_ability( 'nine-category-manager/list-infrastructure', array(
            'label' => __( 'List site infrastructure', 'nine-code-data' ),
            'description' => __( 'Lists editable content types and compatible taxonomy/tag systems with their structural capabilities.', 'nine-code-data' ),
            'category' => 'nine-category-planning',
            'input_schema' => array( 'type' => 'object', 'properties' => array(), 'additionalProperties' => false ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'ability_list_infrastructure' ),
            'permission_callback' => array( $this, 'ability_can_access' ),
            'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
        ) );
        wp_register_ability( 'nine-category-manager/list-taxonomy-terms', array(
            'label' => __( 'List taxonomy terms', 'nine-code-data' ),
            'description' => __( 'Returns terms from a visible hierarchical or tag-like taxonomy. Hierarchical systems include full paths.', 'nine-code-data' ),
            'category' => 'nine-category-planning',
            'input_schema' => array(
                'type' => 'object', 'properties' => array(
                    'taxonomy' => array( 'type' => 'string', 'default' => 'category' ),
                    'search' => array( 'type' => 'string', 'default' => '', 'maxLength' => 200 ),
                    'limit' => array( 'type' => 'integer', 'default' => 200, 'minimum' => 1, 'maximum' => 500 ),
                ), 'additionalProperties' => false,
            ),
            'output_schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
            'execute_callback' => array( $this, 'ability_list_taxonomy_terms' ),
            'permission_callback' => array( $this, 'ability_can_list_taxonomy_terms' ),
            'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
        ) );
        // Backward-compatible v3 ability name. It now delegates to the generic term reader.
        wp_register_ability( 'nine-category-manager/list-category-paths', array(
            'label' => __( 'List category paths', 'nine-code-data' ),
            'description' => __( 'Backward-compatible read-only category path discovery.', 'nine-code-data' ),
            'category' => 'nine-category-planning',
            'input_schema' => array( 'type' => 'object', 'properties' => array( 'taxonomy' => array( 'type' => 'string', 'default' => 'category' ), 'search' => array( 'type' => 'string', 'default' => '' ), 'limit' => array( 'type' => 'integer', 'default' => 200, 'minimum' => 1, 'maximum' => 500 ) ), 'additionalProperties' => false ),
            'output_schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
            'execute_callback' => array( $this, 'ability_list_taxonomy_terms' ),
            'permission_callback' => array( $this, 'ability_can_list_taxonomy_terms' ),
            'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
        ) );
        wp_register_ability( 'nine-category-manager/get-planning-item', array(
            'label' => __( 'Get planning item', 'nine-code-data' ),
            'description' => __( 'Returns the planning-layer structure for one editable item: title, excerpt, featured image, author, parent/order, all attached taxonomy/tag allocations and registered relationship providers. Body content and private detailed fields are excluded.', 'nine-code-data' ),
            'category' => 'nine-category-planning',
            'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer', 'minimum' => 1 ) ), 'required' => array( 'post_id' ), 'additionalProperties' => false ),
            'output_schema' => array( 'type' => 'object' ),
            'execute_callback' => array( $this, 'ability_get_planning_item' ),
            'permission_callback' => array( $this, 'ability_can_get_planning_item' ),
            'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
        ) );
    }

    public function ability_can_access() { return NineCM_Core::can_access_planner(); }

    private function taxonomy_from_input( $input ) {
        $taxonomy = sanitize_key( is_array( $input ) && ! empty( $input['taxonomy'] ) ? $input['taxonomy'] : 'category' );
        $tax = get_taxonomy( $taxonomy );
        return ( $tax && ! empty( $tax->show_ui ) ) ? $tax : false;
    }

    private function can_use_taxonomy( $tax ) {
        if ( ! $tax ) { return false; }
        foreach ( array( 'assign_terms', 'edit_terms', 'manage_terms' ) as $cap_name ) {
            if ( ! empty( $tax->cap->{$cap_name} ) && current_user_can( $tax->cap->{$cap_name} ) ) { return true; }
        }
        return false;
    }

    public function ability_list_infrastructure() {
        $drift = array( 'restricted' => true );
        if ( current_user_can( 'manage_options' ) ) {
            $drift = NineCM_Drift::status();
            unset( $drift['current'], $drift['issues'] );
        }
        return array( 'postTypes' => NineCM_Infrastructure::editable_post_types(), 'taxonomies' => NineCM_Infrastructure::usable_taxonomies(), 'driftGuard' => $drift );
    }

    public function ability_can_list_taxonomy_terms( $input = null ) {
        $tax = $this->taxonomy_from_input( $input );
        return $tax && $this->can_use_taxonomy( $tax );
    }

    public function ability_list_taxonomy_terms( $input = null ) {
        $input = is_array( $input ) ? $input : array();
        $tax = $this->taxonomy_from_input( $input );
        if ( ! $tax ) { return new WP_Error( 'ninecm_ability_taxonomy', 'A visible taxonomy/tag system is required.' ); }
        $limit = max( 1, min( 500, absint( $input['limit'] ?? 200 ) ) );
        $args = array( 'taxonomy' => $tax->name, 'hide_empty' => false, 'number' => $limit, 'orderby' => 'name', 'order' => 'ASC' );
        if ( ! empty( $input['search'] ) ) { $args['search'] = sanitize_text_field( $input['search'] ); }
        $terms = get_terms( $args ); if ( is_wp_error( $terms ) ) { return $terms; }
        $out = array();
        foreach ( $terms as $term ) {
            $out[] = array(
                'id' => (int) $term->term_id, 'name' => (string) $term->name,
                'path' => NineCM_REST::term_path( $term, $tax->name ), 'parent' => (int) $term->parent, 'count' => (int) $term->count,
                'hierarchical' => ! empty( $tax->hierarchical ), 'order' => (int) get_term_meta( $term->term_id, 'ninecm_order', true ),
                'protected' => (bool) get_term_meta( $term->term_id, 'ninecm_protected', true ),
                'archived' => NineCM_Infrastructure::term_in_archived_branch( $term->term_id, $tax->name ),
                'archive_root' => (bool) get_term_meta( $term->term_id, 'ninecm_archived', true ),
            );
        }
        return $out;
    }

    public function ability_can_get_planning_item( $input = null ) {
        return is_array( $input ) && ! empty( $input['post_id'] ) && current_user_can( 'edit_post', absint( $input['post_id'] ) );
    }

    public function ability_get_planning_item( $input = null ) {
        $post = get_post( absint( is_array( $input ) ? ( $input['post_id'] ?? 0 ) : 0 ) );
        if ( ! $post ) { return new WP_Error( 'ninecm_ability_item', 'Planning item not found.' ); }
        $plan = NineCM_Infrastructure::planning_payload( $post );
        $plan['planningShell'] = (bool) get_post_meta( $post->ID, '_ninecm_shell', true );
        return $plan;
    }
}
