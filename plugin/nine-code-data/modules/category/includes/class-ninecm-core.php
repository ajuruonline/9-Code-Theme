<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NineCM_Core {
    private static $instance;
    private $localized = false;

    public static function instance() {
        if ( ! self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    public static function default_settings() {
        return array(
            'frontend_button'        => 1,
            'enable_page_categories' => 1,
            'enable_page_tags'       => 1,
            'enable_page_excerpt'    => 1,
            'default_taxonomy'       => 'category',
            'default_post_type'      => 'page',
            'cache_minutes'          => 15,
            'manager_per_page'       => 50,
            'public_post_limit'       => 10000,
            'launcher_position'      => 'bottom-right',
            'launcher_offset_x'      => 18,
            'launcher_offset_y'      => 78,
            'launcher_size'          => 48,
            'audit_depth_warning'    => 6,
            'audit_child_warning'    => 50,
            'shell_stale_days'       => 30,
        );
    }

    public static function activate() {
        add_option( 'ninecm_cache_version', 1, '', false );
        add_option( 'ninecm_db_version', NINECM_VERSION, '', false );
        $existing = get_option( 'ninecm_settings', array() );
        update_option( 'ninecm_settings', wp_parse_args( is_array( $existing ) ? $existing : array(), self::default_settings() ), false );
    }

    private function __construct() {
        new NineCM_Drift();
        new NineCM_REST();
        new NineCM_Export();
        new NineCM_Health();
        new NineCM_Abilities();
        new NineCM_Admin();

        add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 5 );
        add_action( 'init', array( $this, 'register_page_taxonomy_support' ), 20 );
        add_action( 'init', array( $this, 'register_planning_meta' ), 25 );
        add_action( 'init', array( $this, 'register_assets_and_block' ), 30 );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_manager_assets' ), 20 );
        add_action( 'admin_bar_menu', array( $this, 'admin_bar_link' ), 90 );
        add_action( 'wp_footer', array( $this, 'frontend_manager_shell' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( NINECM_FILE ), array( $this, 'plugin_action_links' ) );

        add_shortcode( 'nine_post_of_contents', array( $this, 'shortcode_poc' ) );
        add_shortcode( 'nine_category_manager', array( $this, 'shortcode_manager' ) );

        add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
        add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );

        // Keep the public hierarchy fresh even when content is changed by WordPress,
        // Elementor, ACF, 9 Post Editor, imports, or another plugin.
        add_action( 'save_post', array( $this, 'maintain_shell_marker' ), 15, 3 );
        add_action( 'added_post_meta', array( $this, 'maintain_shell_marker_from_meta' ), 15, 4 );
        add_action( 'updated_post_meta', array( $this, 'maintain_shell_marker_from_meta' ), 15, 4 );
        add_action( 'ninecm_content_populated', array( $this, 'mark_shell_populated' ), 10, 1 );
        add_action( 'save_post', array( $this, 'invalidate_public_cache' ), 20, 3 );
        add_action( 'set_object_terms', array( $this, 'invalidate_public_cache' ), 20, 6 );
        add_action( 'created_term', array( $this, 'invalidate_public_cache' ), 20, 3 );
        add_action( 'edited_term', array( $this, 'invalidate_public_cache' ), 20, 3 );
        add_action( 'delete_term', array( $this, 'invalidate_public_cache' ), 20, 5 );
        add_action( 'added_term_meta', array( $this, 'planning_term_meta_changed' ), 20, 4 );
        add_action( 'updated_term_meta', array( $this, 'planning_term_meta_changed' ), 20, 4 );
        add_action( 'deleted_term_meta', array( $this, 'planning_term_meta_changed' ), 20, 4 );
        add_action( 'trashed_post', array( $this, 'invalidate_public_cache' ), 20, 1 );
        add_action( 'untrashed_post', array( $this, 'invalidate_public_cache' ), 20, 1 );
        add_action( 'deleted_post', array( $this, 'invalidate_public_cache' ), 20, 1 );
        add_action( 'update_option_ninecm_settings', array( $this, 'settings_changed' ), 20, 3 );
    }

    public function plugin_action_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=nine-category-manager' ) ) . '">' . esc_html__( 'Open Manager', 'nine-code-data' ) . '</a>' );
        return $links;
    }

    public function maybe_upgrade() {
        $stored = (string) get_option( 'ninecm_db_version', '0' );
        if ( version_compare( $stored, NINECM_VERSION, '>=' ) ) { return; }
        $settings = wp_parse_args( (array) get_option( 'ninecm_settings', array() ), self::default_settings() );
        update_option( 'ninecm_settings', $settings, false );
        update_option( 'ninecm_db_version', NINECM_VERSION, false );
        $this->invalidate_public_cache();
    }

    public function maintain_shell_marker( $post_id, $post, $update ) {
        if ( ! $post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
        if ( get_post_meta( $post_id, '_ninecm_shell', true ) && self::is_shell_populated( $post_id, $post ) ) {
            self::mark_shell_populated( $post_id );
        }
    }

    /**
     * Remove the planning-shell marker when another editor populates meaningful
     * custom data without changing post_content (common with ACF/Elementor).
     */
    public function maintain_shell_marker_from_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || ! get_post_meta( $post_id, '_ninecm_shell', true ) ) { return; }

        $key = (string) $meta_key;
        if ( 0 === strpos( $key, '_ninecm_' ) || 0 === strpos( $key, '_edit_' ) || 0 === strpos( $key, '_wp_' ) ) { return; }

        // Elementor body/layout data is an explicit population signal. Featured media is
        // intentionally NOT a signal in v4 because featured image belongs to the planning layer. ACF values
        // are recognized only when the matching private reference points at a field_*
        // key. This avoids SEO/cache plugins accidentally marking a blank shell as done
        // merely because they write ordinary public post meta during save_post.
        $is_signal = in_array( $key, array( '_elementor_data' ), true );
        if ( ! $is_signal && 0 !== strpos( $key, '_' ) ) {
            $acf_reference = (string) get_post_meta( $post_id, '_' . $key, true );
            $is_signal = 0 === strpos( $acf_reference, 'field_' );
        }
        $is_signal = (bool) apply_filters( 'ninecm_meta_is_population_signal', $is_signal, $key, $meta_value, $post_id );
        if ( ! $is_signal || ! self::value_has_content( $meta_value ) ) { return; }

        $post = get_post( $post_id );
        if ( $post && apply_filters( 'ninecm_meta_marks_shell_populated', true, $key, $meta_value, $post_id, $post ) ) {
            self::mark_shell_populated( $post_id );
        }
    }

    private static function value_has_content( $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) { if ( self::value_has_content( $item ) ) { return true; } }
            return false;
        }
        if ( is_object( $value ) ) { return ! empty( get_object_vars( $value ) ); }
        return '' !== trim( (string) $value );
    }

    /**
     * Determine whether a shell has been populated outside the planning layer.
     * Integrations such as 9 Post Editor can override the final decision with
     * the ninecm_shell_is_populated filter.
     */
    public static function is_shell_populated( $post_id, $post = null ) {
        $post_id = absint( $post_id );
        $post = $post ?: get_post( $post_id );
        if ( ! $post ) { return false; }

        $populated = '' !== trim( (string) $post->post_content );
        if ( ! $populated ) {
            $elementor = get_post_meta( $post_id, '_elementor_data', true );
            $populated = self::value_has_content( $elementor );
        }
        if ( ! $populated ) {
            $all_meta = get_post_meta( $post_id );
            foreach ( $all_meta as $key => $values ) {
                if ( 0 === strpos( $key, '_' ) ) { continue; }
                $acf_reference = (string) get_post_meta( $post_id, '_' . $key, true );
                if ( 0 !== strpos( $acf_reference, 'field_' ) ) { continue; }
                if ( self::value_has_content( $values ) ) { $populated = true; break; }
            }
        }
        return (bool) apply_filters( 'ninecm_shell_is_populated', $populated, $post_id, $post );
    }

    /**
     * Explicit handoff for 9 Post Editor/9CF/other population tools.
     * They can call do_action( 'ninecm_content_populated', $post_id ).
     */
    public static function mark_shell_populated( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || ! get_post_meta( $post_id, '_ninecm_shell', true ) ) { return; }
        delete_post_meta( $post_id, '_ninecm_shell' );
        $post = get_post( $post_id );
        do_action( 'ninecm_shell_marked_populated', $post_id, $post );
    }

    public function planning_term_meta_changed( $meta_id, $term_id, $meta_key, $meta_value = null ) {
        if ( in_array( (string) $meta_key, array( 'ninecm_order', 'ninecm_protected', 'ninecm_archived' ), true ) ) {
            $this->invalidate_public_cache();
        }
    }

    public function settings_changed( $old_value, $value, $option ) {
        if ( $old_value !== $value ) { $this->invalidate_public_cache(); }
    }

    public function register_planning_meta() {
        foreach ( get_taxonomies( array( 'show_ui' => true ), 'names' ) as $taxonomy ) {
            register_term_meta( $taxonomy, 'ninecm_order', array(
                'type' => 'integer', 'single' => true, 'default' => 0, 'show_in_rest' => false,
                'sanitize_callback' => 'intval',
                'auth_callback' => function() use ( $taxonomy ) {
                    $cap = self::taxonomy_capability( $taxonomy, 'edit_terms' );
                    return $cap ? current_user_can( $cap ) : false;
                },
            ) );
            register_term_meta( $taxonomy, 'ninecm_protected', array(
                'type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback' => function() use ( $taxonomy ) {
                    $cap = self::taxonomy_capability( $taxonomy, 'manage_terms' );
                    return $cap ? current_user_can( $cap ) : false;
                },
            ) );
            register_term_meta( $taxonomy, 'ninecm_archived', array(
                'type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback' => function() use ( $taxonomy ) {
                    $cap = self::taxonomy_capability( $taxonomy, 'edit_terms' );
                    return $cap ? current_user_can( $cap ) : false;
                },
            ) );
        }

        foreach ( get_post_types( array( 'show_ui' => true ), 'names' ) as $post_type ) {
            register_post_meta( $post_type, '_ninecm_shell', array(
                'type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback' => function() { return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ); },
            ) );
            register_post_meta( $post_type, '_ninecm_plan_key', array(
                'type' => 'string', 'single' => true, 'default' => '', 'show_in_rest' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function() { return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ); },
            ) );
        }
    }

    public function register_page_taxonomy_support() {
        $settings = wp_parse_args( (array) get_option( 'ninecm_settings', array() ), self::default_settings() );
        if ( ! post_type_exists( 'page' ) ) { return; }
        if ( ! empty( $settings['enable_page_categories'] ) && taxonomy_exists( 'category' ) ) {
            register_taxonomy_for_object_type( 'category', 'page' );
        }
        if ( ! empty( $settings['enable_page_tags'] ) && taxonomy_exists( 'post_tag' ) ) {
            register_taxonomy_for_object_type( 'post_tag', 'page' );
        }
        if ( ! empty( $settings['enable_page_excerpt'] ) ) {
            add_post_type_support( 'page', 'excerpt' );
        }
    }

    public function register_assets_and_block() {
        wp_register_style( 'ninecm-frontend', NINECM_URL . 'assets/frontend.css', array(), NINECM_VERSION );
        wp_register_script( 'ninecm-frontend', NINECM_URL . 'assets/frontend.js', array(), NINECM_VERSION, true );
        wp_register_script(
            'ninecm-block-editor',
            NINECM_URL . 'blocks/post-of-contents/index.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-data', 'wp-core-data' ),
            NINECM_VERSION,
            true
        );

        $block_dir = NINECM_DIR . 'blocks/post-of-contents';
        if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
            register_block_type( $block_dir, array( 'render_callback' => array( 'NineCM_Renderer', 'render' ) ) );
        }
    }

    public static function can_access_planner() {
        foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
            if ( ! empty( $type->cap->edit_posts ) && current_user_can( $type->cap->edit_posts ) ) { return true; }
        }
        return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
    }

    public static function taxonomy_capability( $taxonomy, $cap_name ) {
        $tax = get_taxonomy( sanitize_key( $taxonomy ) );
        if ( ! $tax || empty( $tax->cap->{$cap_name} ) ) { return false; }
        return $tax->cap->{$cap_name};
    }

    private static function term_in_archived_branch( $term_id, $taxonomy ) {
        $term_id = absint( $term_id ); $guard = 0;
        while ( $term_id && $guard++ < 60 ) {
            if ( get_term_meta( $term_id, 'ninecm_archived', true ) ) { return true; }
            $term = get_term( $term_id, $taxonomy );
            if ( ! $term || is_wp_error( $term ) ) { break; }
            $term_id = (int) $term->parent;
        }
        return false;
    }

    private function current_context() {
        $id = is_singular() ? absint( get_queried_object_id() ) : 0;
        if ( ! $id || ! current_user_can( 'edit_post', $id ) ) { return null; }
        $post = get_post( $id );
        if ( ! $post ) { return null; }
        return NineCM_Infrastructure::planning_payload( $post );
    }

    private function localize_frontend() {
        if ( $this->localized || ! self::can_access_planner() ) { return; }
        $settings = wp_parse_args( (array) get_option( 'ninecm_settings', array() ), self::default_settings() );
        $post_types = NineCM_Infrastructure::editable_post_types();
        $taxonomies = array();
        foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) {
            $d = NineCM_Infrastructure::taxonomy_descriptor( $tax );
            if ( ! $d['canAssign'] && ! $d['canEdit'] && ! $d['canManage'] ) { continue; }
            $taxonomies[] = $d;
        }
        usort( $taxonomies, function( $a, $b ) { return strcasecmp( $a['label'], $b['label'] ); } );

        wp_localize_script( 'ninecm-frontend', 'NineCMFront', array(
            'root'            => esc_url_raw( rest_url( 'ninecm/v1/' ) ),
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'userId'          => get_current_user_id(),
            'version'         => NINECM_VERSION,
            'current'         => $this->current_context(),
            'postTypes'       => $post_types,
            'taxonomies'      => $taxonomies,
            'defaultPostType' => sanitize_key( $settings['default_post_type'] ),
            'defaultTaxonomy' => sanitize_key( $settings['default_taxonomy'] ),
            'canUpload'       => current_user_can( 'upload_files' ),
            'pageCategories'  => ! empty( $settings['enable_page_categories'] ),
            'pageTags'        => ! empty( $settings['enable_page_tags'] ),
            'launcher'        => array(
                'position' => $settings['launcher_position'],
                'offsetX'  => absint( $settings['launcher_offset_x'] ),
                'offsetY'  => absint( $settings['launcher_offset_y'] ),
                'size'     => absint( $settings['launcher_size'] ),
            ),
        ) );
        $this->localized = true;
    }

    public function enqueue_manager_assets() {
        wp_enqueue_style( 'ninecm-frontend' );
        wp_enqueue_script( 'ninecm-frontend' );
        if ( current_user_can( 'upload_files' ) ) { wp_enqueue_media(); }
        $this->localize_frontend();
    }

    public function frontend_manager_assets() {
        if ( is_admin() || ! self::can_access_planner() ) { return; }
        $settings = wp_parse_args( (array) get_option( 'ninecm_settings', array() ), self::default_settings() );
        $theme_launcher = function_exists( 'nine10_data_theme_launcher_available' ) && nine10_data_theme_launcher_available();
        if ( $theme_launcher ) { return; }
        if ( ! empty( $settings['frontend_button'] ) ) { $this->enqueue_manager_assets(); }
    }

    private static function shortcode_bool( $value, $default = false ) {
        if ( is_bool( $value ) ) { return $value; }
        $value = strtolower( trim( (string) $value ) );
        if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) { return true; }
        if ( in_array( $value, array( '0', 'false', 'no', 'off', '' ), true ) ) { return false; }
        return (bool) $default;
    }

    public function shortcode_poc( $atts ) {
        wp_enqueue_style( 'ninecm-frontend' );
        wp_enqueue_script( 'ninecm-frontend' );
        $atts = shortcode_atts( array(
            'taxonomy'          => 'category',
            'root'              => 0,
            'depth'             => 0,
            'collapsible'       => 1,
            'initially_open'    => 1,
            'marker'            => 'number',
            'icon'              => '›',
            'search'            => 1,
            'filter'            => 1,
            'style'             => 'clean',
            'post_types'        => 'post,page',
            'hide_empty'        => 1,
            'posts_per_category'=> 0,
            'show_counts'       => 0,
            'deduplicate'       => 0,
            'term_orderby'      => 'name',
            'term_order'        => 'ASC',
            'post_orderby'      => 'title',
            'post_order'        => 'ASC',
            'empty_message'     => 'No matching content found.',
        ), $atts, 'nine_post_of_contents' );

        $atts['showSearch']       = self::shortcode_bool( $atts['search'], true );
        $atts['showFilter']       = self::shortcode_bool( $atts['filter'], true );
        $atts['collapsible']      = self::shortcode_bool( $atts['collapsible'], true );
        $atts['initiallyOpen']    = self::shortcode_bool( $atts['initially_open'], true );
        $atts['hideEmpty']        = self::shortcode_bool( $atts['hide_empty'], true );
        $atts['showCounts']       = self::shortcode_bool( $atts['show_counts'], false );
        $atts['deduplicate']      = self::shortcode_bool( $atts['deduplicate'], false );
        $atts['postTypes']        = array_filter( array_map( 'sanitize_key', explode( ',', (string) $atts['post_types'] ) ) );
        $atts['postsPerCategory'] = absint( $atts['posts_per_category'] );
        $atts['termOrderby']      = sanitize_key( $atts['term_orderby'] );
        $atts['termOrder']        = strtoupper( sanitize_text_field( $atts['term_order'] ) );
        $atts['postOrderby']      = sanitize_key( $atts['post_orderby'] );
        $atts['postOrder']        = strtoupper( sanitize_text_field( $atts['post_order'] ) );
        $atts['emptyMessage']     = sanitize_text_field( $atts['empty_message'] );
        return NineCM_Renderer::render( $atts );
    }

    public function shortcode_manager() {
        if ( ! self::can_access_planner() ) { return ''; }
        $this->enqueue_manager_assets();
        return '<div class="ninecm-inline-manager" data-ninecm-manager="1"></div>';
    }

    public function admin_bar_link( $bar ) {
        if ( ! self::can_access_planner() ) { return; }
        if ( is_admin() && function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( $screen && 'post' === $screen->base ) { return; }
        }
        $bar->add_node( array(
            'id'    => 'ninecm-manager',
            'title' => '9 Structure Planner',
            'href'  => admin_url( 'admin.php?page=nine-category-manager' ),
        ) );
    }

    public function frontend_manager_shell() {
        if ( ! self::can_access_planner() ) { return; }
        $settings = wp_parse_args( (array) get_option( 'ninecm_settings', array() ), self::default_settings() );
        $theme_launcher = function_exists( 'nine10_data_theme_launcher_available' ) && nine10_data_theme_launcher_available();
        if ( $theme_launcher || empty( $settings['frontend_button'] ) ) { return; }

        $position = in_array( $settings['launcher_position'], array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' ), true ) ? $settings['launcher_position'] : 'bottom-right';
        $style = sprintf(
            '--ninecm-fab-x:%dpx;--ninecm-fab-y:%dpx;--ninecm-fab-size:%dpx;',
            absint( $settings['launcher_offset_x'] ),
            absint( $settings['launcher_offset_y'] ),
            max( 36, min( 80, absint( $settings['launcher_size'] ) ) )
        );
        echo '<button class="ninecm-fab" type="button" data-ninecm-open-manager data-position="' . esc_attr( $position ) . '" style="' . esc_attr( $style ) . '" aria-haspopup="dialog" aria-expanded="false" aria-label="Open 9 Structure Planner" title="9 Structure Planner"><span aria-hidden="true">9</span></button>';
        echo '<div class="ninecm-modal" data-ninecm-modal hidden><div class="ninecm-modal__backdrop" data-ninecm-close-manager></div><div class="ninecm-modal__panel" role="dialog" aria-modal="true" aria-label="9 Structure Planner" tabindex="-1"><button type="button" class="ninecm-modal__close" data-ninecm-close-manager aria-label="Close">×</button><div class="ninecm-inline-manager" data-ninecm-manager="1"></div></div></div>';
    }

    public function register_elementor_category( $elements_manager ) {
        if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) { return; }
        if ( method_exists( $elements_manager, 'get_categories' ) ) {
            $categories = $elements_manager->get_categories();
            if ( is_array( $categories ) && isset( $categories['nine-widgets'] ) ) { return; }
        }
        $elements_manager->add_category( 'nine-widgets', array( 'title' => '9 Widgets', 'icon' => 'fa fa-plug' ) );
    }

    public function register_elementor_widget( $widgets_manager ) {
        if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\\Elementor\\Widget_Base' ) || ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) { return; }
        if ( ! class_exists( 'NineCM_Elementor_Widget', false ) ) {
            require_once NINECM_DIR . 'includes/class-ninecm-elementor-widget.php';
        }
        if ( class_exists( 'NineCM_Elementor_Widget', false ) ) {
            $widgets_manager->register( new NineCM_Elementor_Widget() );
        }
    }

    public function invalidate_public_cache() {
        static $bumped = false;
        if ( $bumped ) { return; }
        $bumped = true;
        update_option( 'ninecm_cache_version', (int) get_option( 'ninecm_cache_version', 1 ) + 1, false );
    }
}
