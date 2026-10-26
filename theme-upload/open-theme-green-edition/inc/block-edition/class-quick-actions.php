<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class N9BE_Quick_Actions {
    const OPTION = 'n9be_quick_actions';
    const CUSTOM_OPTION = 'n9be_quick_custom_actions';
    const USER_SCHEMA = 'n9be_quick_actions_schema';
    const CURRENT_SCHEMA = 9;
    const MAX_ACTIONS = 60;

    public static function boot() {
        /* Theme 14 owns the editor launcher. Remove the 13.x Core copy when present
         * so the user sees one coherent rail rather than duplicated controls. */
        add_action( 'after_setup_theme', array( __CLASS__, 'take_ownership_from_core' ), 100 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 1001 );
        add_action( 'admin_footer', array( __CLASS__, 'markup' ), 1001 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ), 1001 );
        add_action( 'wp_footer', array( __CLASS__, 'markup_frontend' ), 1001 );
        add_action( 'wp_ajax_n9be_quick_plugin_install', array( __CLASS__, 'ajax_plugin_install' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ), 80 );
        add_action( 'admin_post_n9be_quick_actions_save', array( __CLASS__, 'handle_settings_save' ) );
        add_action( 'admin_post_n9be_mason_save_settings', array( __CLASS__, 'handle_settings_save' ) );
    }


    public static function register_settings_page() {
        if ( ! current_user_can( 'edit_posts' ) ) { return; }
        add_submenu_page(
            'nine-code-ultra',
            __( 'Quick Actions', 'nine-code' ),
            __( 'Quick Actions', 'nine-code' ),
            'edit_posts',
            'ninecode-quick-actions',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function take_ownership_from_core() {
        if ( function_exists( 'ncu_admin_quick_controls_assets' ) ) { remove_action( 'admin_enqueue_scripts', 'ncu_admin_quick_controls_assets', 1000 ); }
        if ( function_exists( 'ncu_admin_quick_controls_markup' ) ) { remove_action( 'admin_footer', 'ncu_admin_quick_controls_markup', 1000 ); }
    }

    private static function allowed() {
        if ( function_exists( 'ninecode_theme_surface_suppresses' ) && ninecode_theme_surface_suppresses( 'quick_actions' ) ) { return false; }
        if ( ! is_admin() || ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) ) { return false; }
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) { return false; }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        // Mobile Editor Update: the post editor for post/page/CPT editing gets one compact Core-owned
        // Editor Tools drawer. Do not add a second Theme rail to that workspace.
        return ! $screen || 'customize' !== $screen->base; // Site Editor still needs Top Bar/Search navigation.
    }

    private static function allowed_frontend() {
        if ( is_admin() || ! is_user_logged_in() || ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) ) { return false; }
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) { return false; }
        if ( function_exists( 'is_feed' ) && is_feed() ) { return false; }
        if ( function_exists( 'is_trackback' ) && is_trackback() ) { return false; }
        return (bool) apply_filters( 'ninecode_theme_frontend_quick_actions_enabled', true );
    }


    private static function can_install_replace_plugins() {
        return current_user_can( 'install_plugins' ) && current_user_can( 'upload_plugins' ) && current_user_can( 'activate_plugins' );
    }

    private static function data_manager_ready() {
        return defined( 'NINE55_ULTRON_DATA_VERSION' ) || class_exists( 'Nine55_Ultron_Data', false );
    }


    public static function defaults() {
        $defaults = array( 'dashboard', 'save', 'menu', 'view_site', 'search', 'ninecode_theme' );
        if ( self::data_manager_ready() ) {
            $defaults = array_merge( $defaults, array( 'data_post_editor', 'data_category_manager', 'data_post_creator', 'data_form_manager', 'data_backup' ) );
        }
        return array_merge( $defaults, array( 'new_post', 'new_page', 'media_upload', 'add_plugin', 'plugins', 'settings' ) );
    }

    public static function catalog() {
        $catalog = array(
            'save' => array( 'label'=>'Save / Update', 'icon'=>'saved', 'handler'=>'save', 'cap'=>'edit_posts' ),
            'menu' => array( 'label'=>'Open Scholar Menu', 'icon'=>'menu', 'handler'=>'menu', 'cap'=>'edit_posts' ),
            'theme_menu' => array( 'label'=>'Theme Menu', 'icon'=>'menu', 'handler'=>'theme_menu', 'cap'=>'read' ),
            'view_site' => array( 'label'=>'View Site', 'icon'=>'admin-home', 'handler'=>'view_site', 'cap'=>'edit_posts' ),
            'search' => array( 'label'=>'Search content', 'icon'=>'search', 'url'=>admin_url( 'edit.php' ), 'cap'=>'edit_posts' ),
            'ninecode_theme' => array( 'label'=>'Open Scholar', 'icon'=>'admin-appearance', 'url'=>admin_url( 'admin.php?page=nine-code-ultra' ), 'cap'=>'edit_theme_options' ),
                        'new_post' => array( 'label'=>'Add Post', 'icon'=>'edit-page', 'url'=>admin_url( 'post-new.php' ), 'cap'=>'edit_posts' ),
            'new_page' => array( 'label'=>'Add Page', 'icon'=>'admin-page', 'url'=>admin_url( 'post-new.php?post_type=page' ), 'cap'=>'edit_pages' ),
            'media_upload' => array( 'label'=>'Upload Media', 'icon'=>'upload', 'url'=>admin_url( 'media-new.php' ), 'cap'=>'upload_files' ),
            'media' => array( 'label'=>'Media Library', 'icon'=>'admin-media', 'url'=>admin_url( 'upload.php' ), 'cap'=>'upload_files' ),
            'add_plugin' => array( 'label'=>'Install / Replace Plugin', 'icon'=>'plus-alt2', 'handler'=>'add_plugin', 'cap'=>'install_plugins' ),
            'plugins' => array( 'label'=>'Manage Plugins', 'icon'=>'admin-plugins', 'url'=>self_admin_url( 'plugins.php' ), 'cap'=>'activate_plugins' ),
            'settings' => array( 'label'=>'Settings', 'icon'=>'admin-settings', 'url'=>admin_url( 'options-general.php' ), 'cap'=>'manage_options' ),
            'appearance' => array( 'label'=>'Appearance', 'icon'=>'admin-appearance', 'url'=>admin_url( 'themes.php' ), 'cap'=>'edit_theme_options' ),
            'menus' => array( 'label'=>'Menus', 'icon'=>'menu-alt3', 'url'=>admin_url( 'nav-menus.php' ), 'cap'=>'edit_theme_options' ),
            'widgets' => array( 'label'=>'Widgets', 'icon'=>'screenoptions', 'url'=>admin_url( 'widgets.php' ), 'cap'=>'edit_theme_options' ),
            'users' => array( 'label'=>'Users', 'icon'=>'admin-users', 'url'=>admin_url( 'users.php' ), 'cap'=>'list_users' ),
            'profile' => array( 'label'=>'My Profile', 'icon'=>'admin-users', 'url'=>admin_url( 'profile.php' ), 'cap'=>'read' ),
            'comments' => array( 'label'=>'Comments', 'icon'=>'admin-comments', 'url'=>admin_url( 'edit-comments.php' ), 'cap'=>'edit_posts' ),
            'updates' => array( 'label'=>'Updates', 'icon'=>'update', 'url'=>admin_url( 'update-core.php' ), 'cap'=>'update_core' ),
            'dashboard' => array( 'label'=>'Dashboard', 'icon'=>'dashboard', 'url'=>admin_url(), 'cap'=>'read' ),
        );
        if ( self::data_manager_ready() ) {
            $catalog['data_post_editor'] = array( 'label'=>'Post Editor', 'icon'=>'edit-page', 'url'=>admin_url( 'admin.php?page=nine-post-manager' ), 'cap'=>'edit_posts' );
            $catalog['data_category_manager'] = array( 'label'=>'Category Manager', 'icon'=>'category', 'url'=>admin_url( 'admin.php?page=nine-category-manager' ), 'cap'=>'manage_categories' );
            $catalog['data_post_creator'] = array( 'label'=>'Post Creator', 'icon'=>'superhero-alt', 'url'=>admin_url( 'admin.php?page=nine-ai-manager-post' ), 'cap'=>'manage_options' );
            $catalog['data_form_manager'] = array( 'label'=>'Form Manager', 'icon'=>'feedback', 'url'=>admin_url( 'admin.php?page=nine10-form-manager' ), 'cap'=>'edit_posts' );
            $catalog['data_backup'] = array( 'label'=>'Data Backup', 'icon'=>'download', 'url'=>admin_url( 'admin.php?page=nine10-data-backup' ), 'cap'=>'edit_posts' );
        }
        $catalog['manage_post'] = array( 'label'=>'All Posts', 'icon'=>'admin-post', 'url'=>admin_url( 'edit.php' ), 'cap'=>'edit_posts', 'group'=>'content' );
        $catalog['manage_page'] = array( 'label'=>'All Pages', 'icon'=>'admin-page', 'url'=>admin_url( 'edit.php?post_type=page' ), 'cap'=>'edit_pages', 'group'=>'content' );
        foreach ( get_taxonomies( array( 'show_ui'=>true ), 'objects' ) as $tax => $tax_obj ) {
            if ( in_array( $tax, array( 'post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category' ), true ) ) { continue; }
            $tax_cap = isset( $tax_obj->cap->manage_terms ) ? $tax_obj->cap->manage_terms : 'manage_categories';
            $catalog[ 'tax_' . $tax ] = array( 'label'=>'Manage ' . $tax_obj->labels->name, 'icon'=>'category', 'url'=>admin_url( 'edit-tags.php?taxonomy=' . $tax ), 'cap'=>$tax_cap, 'group'=>'taxonomies' );
        }
        $post_types = get_post_types( array( 'show_ui'=>true ), 'objects' );
        foreach ( $post_types as $type => $obj ) {
            if ( in_array( $type, array( 'post', 'page', 'attachment' ), true ) ) { continue; }
            $cap = isset( $obj->cap->edit_posts ) ? $obj->cap->edit_posts : 'edit_posts';
            $catalog[ 'new_cpt_' . $type ] = array( 'label'=>'Add ' . $obj->labels->singular_name, 'icon'=>'plus-alt2', 'url'=>admin_url( 'post-new.php?post_type=' . $type ), 'cap'=>$cap, 'group'=>'types' );
            $catalog[ 'manage_cpt_' . $type ] = array( 'label'=>$obj->labels->name, 'icon'=>'list-view', 'url'=>admin_url( 'edit.php?post_type=' . $type ), 'cap'=>$cap, 'group'=>'types' );
        }
        foreach ( self::custom_actions() as $id => $action ) {
            $catalog[ $id ] = array( 'label'=>$action['label'], 'icon'=>$action['icon'], 'url'=>admin_url( ltrim( $action['route'], '/' ) ), 'cap'=>'read', 'custom'=>true );
        }
        return apply_filters( 'ninecode_block_edition_quick_action_catalog', $catalog );
    }

    public static function configured_ids() {
        $user_id = get_current_user_id();
        $ids = $user_id ? get_user_meta( $user_id, self::OPTION, true ) : array();
        if ( ! is_array( $ids ) || empty( $ids ) ) {
            // One-time compatibility fallback if a pre-release Block Edition build stored a site-wide list.
            $legacy = get_option( self::OPTION, array() );
            $ids = is_array( $legacy ) && ! empty( $legacy ) ? $legacy : self::defaults();
        }
        $ids = array_values( array_unique( array_filter( array_map( 'sanitize_key', $ids ) ) ) );

        // Edition 9.10 schema 5 migration: keep Post Editor and Category Manager inside the
        // Theme-owned drawer while the public rail itself stays intentionally minimal:
        // Theme hamburger, Save and Launcher only. Later manual drawer removals are respected.
        if ( $user_id && (int) get_user_meta( $user_id, self::USER_SCHEMA, true ) < self::CURRENT_SCHEMA ) {
            $ids = array_merge( $ids, array( 'view_site', 'search' ) );
            $ids = array_values( array_diff( $ids, array( 'mason' ) ) );
            $theme_id = 'ninecode_theme';
            $ids = array_values( array_diff( $ids, array( $theme_id ) ) );
            $at = array_search( 'view_site', $ids, true );
            $at = false === $at ? min( 3, count( $ids ) ) : $at + 1;
            array_splice( $ids, $at, 0, array( $theme_id ) );
            $ids = array_values( array_diff( $ids, array( 'nine' . '_pages' ) ) );
            if ( self::data_manager_ready() ) {
                $data_ids = array( 'data_post_editor', 'data_category_manager', 'data_post_creator', 'data_form_manager', 'data_backup' );
                $ids = array_values( array_diff( $ids, $data_ids ) );
                $at = array_search( $theme_id, $ids, true );
                $at = false === $at ? min( 4, count( $ids ) ) : $at + 1;
                array_splice( $ids, $at, 0, $data_ids );
            }
            if ( self::can_install_replace_plugins() ) {
                $ids = array_values( array_diff( $ids, array( 'add_plugin' ) ) );
                $at = self::data_manager_ready() ? array_search( 'data_category_manager', $ids, true ) : array_search( $theme_id, $ids, true );
                $at = false === $at ? min( 6, count( $ids ) ) : $at + 1;
                array_splice( $ids, $at, 0, array( 'add_plugin' ) );
            }
            $ids = array_slice( array_values( array_unique( $ids ) ), 0, self::MAX_ACTIONS );
            update_user_meta( $user_id, self::OPTION, $ids );
            update_user_meta( $user_id, self::USER_SCHEMA, self::CURRENT_SCHEMA );
        }
        return $ids;
    }

    private static function custom_actions() {
        $items = get_option( self::CUSTOM_OPTION, array() );
        return is_array( $items ) ? $items : array();
    }

    private static function enqueue_assets( $context ) {
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'n9be-quick-actions', NCU_THEME_URI . '/assets/css/block-edition-quick-actions.css', array(), NCU_THEME_VERSION );
        wp_enqueue_style( 'n9be-topbar-popup', NCU_THEME_URI . '/assets/css/open-scholar-topbar.css', array( 'n9be-quick-actions' ), NCU_THEME_VERSION );
        if ( 'frontend' === $context ) {
            wp_enqueue_style( 'osch-compact-frontend', NCU_THEME_URI . '/assets/css/open-scholar-compact.css', array( 'n9be-topbar-popup' ), NCU_THEME_VERSION );
            wp_enqueue_style( 'open-theme-visual', NCU_THEME_URI . '/assets/css/open-theme.css', array( 'osch-compact-frontend' ), NCU_THEME_VERSION );
        }
        wp_enqueue_script( 'n9be-quick-actions', NCU_THEME_URI . '/assets/js/block-edition-quick-actions.js', array(), NCU_THEME_VERSION, true );
        wp_enqueue_script( 'n9be-topbar-popup', NCU_THEME_URI . '/assets/js/open-scholar-topbar.js', array( 'n9be-quick-actions' ), NCU_THEME_VERSION, true );
        wp_localize_script( 'n9be-topbar-popup', 'n9beTopBar', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => current_user_can( 'manage_options' ) ? wp_create_nonce( 'osch_clear_object_cache' ) : '',
        ) );
        wp_localize_script( 'n9be-quick-actions', 'n9beQuickActions', array(
            'ajaxUrl'=>admin_url( 'admin-ajax.php' ),
            'nonce'=>wp_create_nonce( 'n9be_quick_plugin_install' ),
            'siteUrl'=>home_url( '/' ),
            'context'=>sanitize_key( $context ),
            'maxUploadBytes'=>(int) wp_max_upload_size(),
        ) );
    }

    public static function enqueue() {
        if ( ! self::allowed() ) { return; }
        self::enqueue_assets( 'admin' );
    }

    public static function enqueue_frontend() {
        if ( ! self::allowed_frontend() ) { return; }
        try {
            self::enqueue_assets( 'frontend' );
        } catch ( \Throwable $e ) {
            self::record_frontend_error( 'enqueue', $e );
        }
    }

    public static function markup() {
        if ( ! self::allowed() ) { return; }
        self::render_markup( 'admin' );
    }

    public static function markup_frontend() {
        if ( ! self::allowed_frontend() ) { return; }
        try {
            self::render_markup( 'frontend' );
        } catch ( \Throwable $e ) {
            self::record_frontend_error( 'markup', $e );
        }
    }

    private static function record_frontend_error( $stage, $error ) {
        $message = '[Nine Code Quick Actions] Front-end ' . sanitize_key( (string) $stage ) . ' disabled for this request: ' . $error->getMessage();
        if ( function_exists( 'error_log' ) ) { error_log( $message ); }
        do_action( 'ninecode_theme_quick_actions_frontend_error', $stage, $error );
    }

    /** Editor URL for whatever the visitor is viewing, or '' when it cannot be edited by this user. */
    private static function current_edit_url() {
        if ( is_admin() ) { return ''; }
        if ( is_home() && ! is_front_page() && get_option( 'page_for_posts' ) ) {
            $id = (int) get_option( 'page_for_posts' );
            return current_user_can( 'edit_post', $id ) ? (string) get_edit_post_link( $id, 'raw' ) : '';
        }
        if ( is_singular() || ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) ) {
            $id = (int) get_queried_object_id();
            return $id && current_user_can( 'edit_post', $id ) ? (string) get_edit_post_link( $id, 'raw' ) : '';
        }
        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            return $term instanceof WP_Term && current_user_can( 'edit_term', $term->term_id ) ? (string) get_edit_term_link( $term, $term->taxonomy ) : '';
        }
        if ( is_author() ) {
            $id = (int) get_queried_object_id();
            return $id && current_user_can( 'edit_user', $id ) ? (string) get_edit_user_link( $id ) : '';
        }
        return '';
    }

    private static function render_markup( $context ) {
        $frontend = 'frontend' === $context;
        $catalog = self::catalog();
        $items = array();
        foreach ( self::configured_ids() as $id ) {
            if ( empty( $catalog[ $id ] ) ) { continue; }
            $action = $catalog[ $id ];
            if ( ! empty( $action['cap'] ) && ! current_user_can( $action['cap'] ) ) { continue; }
            if ( 'add_plugin' === $id && ! self::can_install_replace_plugins() ) { continue; }
            $items[ $id ] = $action;
        }
        echo '<div id="n9be-admin-launcher" data-n9be-context="' . esc_attr( $context ) . '" data-n9be-site-url="' . esc_attr( home_url( '/' ) ) . '">';
        // Primary navigation: Dashboard always leads the rail on every surface.
        echo '<a class="n9be-rail-button n9be-dashboard-launch" data-n9be-dashboard-launch href="' . esc_url( admin_url( 'index.php' ) ) . '" title="Dashboard — all applications"><span class="dashicons dashicons-dashboard"></span><span class="screen-reader-text">Dashboard</span></a>';
        if ( $frontend ) {
            echo '<button type="button" class="n9be-rail-button" data-n9be-action="save" title="Save current front-end work"><span class="dashicons dashicons-saved"></span><span class="screen-reader-text">Save</span></button>';
            echo '<button type="button" class="n9be-rail-button" data-n9be-quick-toggle aria-expanded="false" aria-controls="n9be-quick-drawer" title="Quick Actions"><span class="dashicons dashicons-grid-view"></span><span class="screen-reader-text">Quick Actions</span></button>';
        } else {
            echo '<button type="button" class="n9be-rail-button" data-n9be-quick-toggle aria-expanded="false" aria-controls="n9be-quick-drawer" title="Quick Actions"><span class="dashicons dashicons-grid-view"></span><span class="screen-reader-text">Quick Actions</span></button>';
            echo '<button type="button" class="n9be-rail-button" data-n9be-action="save" title="Save or update current work" disabled><span class="dashicons dashicons-saved"></span><span class="screen-reader-text">Save</span></button>';
        }
        $edit_url = self::current_edit_url();
        if ( $edit_url ) {
            echo '<a class="n9be-rail-button n9be-edit-launch" data-n9be-edit href="' . esc_url( $edit_url ) . '" title="' . esc_attr__( 'Edit this page', 'nine-code' ) . '"><span class="dashicons dashicons-edit"></span><span class="screen-reader-text">' . esc_html__( 'Edit this page', 'nine-code' ) . '</span></a>';
        }
        // Permanent right rail: search and toolbar access, not a duplicate hamburger.
        echo '<button type="button" class="n9be-rail-button" data-n9be-search-toggle aria-expanded="false" aria-controls="n9be-search-popup" title="Search"><span class="dashicons dashicons-search"></span><span class="screen-reader-text">Search</span></button>';
        echo '<button type="button" class="n9be-rail-button" data-n9be-topbar-toggle aria-expanded="false" aria-controls="n9be-topbar-popup" title="Top Bar · site utilities"><span class="dashicons dashicons-admin-settings"></span><span class="screen-reader-text">Top Bar</span></button>';
        echo '</div>';
        // Normal WordPress routes, capability checks and signed logout nonces remain intact.
        echo '<aside id="n9be-topbar-popup" class="n9be-side-popup" role="dialog" aria-modal="true" aria-labelledby="n9be-topbar-title" hidden>';
        echo '<button type="button" class="n9be-side-backdrop" data-n9be-popup-close tabindex="-1" aria-label="Close Top Bar"></button>';
        echo '<div class="n9be-side-panel"><header><div><small>OPEN SCHOLAR · UTILITIES</small><h2 id="n9be-topbar-title">Top Bar</h2></div><button type="button" data-n9be-popup-close aria-label="Close Top Bar">×</button></header><div class="n9be-side-content"><div class="n9be-side-actions">';
        if ( ! $frontend ) { echo '<button type="button" data-n9be-open-nav><span class="dashicons dashicons-menu"></span>Open Scholar menu</button>'; }
        if ( $frontend ) { echo '<button type="button" data-n9be-action="theme_menu"><span class="dashicons dashicons-admin-appearance"></span>Theme menu</button>'; }
        echo '<a href="' . esc_url( home_url( '/' ) ) . '"><span class="dashicons dashicons-admin-home"></span>View site</a>';
        echo '<a href="' . esc_url( admin_url() ) . '"><span class="dashicons dashicons-dashboard"></span>Dashboard</a>';
        if ( current_user_can( 'edit_posts' ) ) { echo '<a href="' . esc_url( admin_url( 'post-new.php' ) ) . '"><span class="dashicons dashicons-plus-alt2"></span>Add post</a>'; }
        if ( current_user_can( 'edit_pages' ) ) { echo '<a href="' . esc_url( admin_url( 'post-new.php?post_type=page' ) ) . '"><span class="dashicons dashicons-admin-page"></span>Add page</a>'; }
        if ( current_user_can( 'upload_files' ) ) { echo '<a href="' . esc_url( admin_url( 'media-new.php' ) ) . '"><span class="dashicons dashicons-upload"></span>Upload media</a>'; }
        if ( current_user_can( 'read' ) ) { echo '<a href="' . esc_url( admin_url( 'profile.php' ) ) . '"><span class="dashicons dashicons-admin-users"></span>My profile</a>'; }
        echo '<a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '"><span class="dashicons dashicons-exit"></span>Log out</a>';
        echo '</div><section aria-label="Cache and plugin toolbar commands"><h3>Cache &amp; site tools</h3>';
        if ( current_user_can( 'manage_options' ) ) { echo '<button type="button" class="n9be-clear-cache-button" data-n9be-clear-cache>Clear cache <small>WordPress object cache</small></button><p class="n9be-cache-result" role="status" aria-live="polite"></p>'; }
        echo '<div id="n9be-topbar-cache" class="n9be-tool-list"><p class="n9be-tool-empty">Cache controls appear when an active plugin adds them to the WordPress toolbar.</p></div></section>';
        echo '<details class="n9be-toolbar-extra"><summary>Other toolbar items</summary><div id="n9be-topbar-extra" class="n9be-tool-list"></div></details>';
        echo '</div></div></aside>';
        echo '<aside id="n9be-search-popup" class="n9be-side-popup" role="dialog" aria-modal="true" aria-labelledby="n9be-search-title" hidden>';
        echo '<button type="button" class="n9be-side-backdrop" data-n9be-popup-close tabindex="-1" aria-label="Close Search"></button>';
        echo '<div class="n9be-side-panel"><header><div><small>OPEN SCHOLAR · FIND</small><h2 id="n9be-search-title">Search</h2></div><button type="button" data-n9be-popup-close aria-label="Close Search">×</button></header><div class="n9be-side-content">';
        echo '<form class="n9be-search-form" action="' . esc_url( $frontend ? home_url( '/' ) : admin_url( 'edit.php' ) ) . '" method="get"><label for="n9be-search-input">' . ( $frontend ? 'Search site' : 'Search posts and pages' ) . '</label><div class="n9be-search-fields"><input id="n9be-search-input" type="search" name="s" placeholder="Enter keywords…" required><button type="submit">Find</button></div>';
        if ( ! $frontend ) { echo '<label for="n9be-search-type">Content type</label><select id="n9be-search-type" name="post_type"><option value="post">Posts</option><option value="page">Pages</option></select>'; }
        echo '</form>';
        if ( ! $frontend ) { echo '<section><h3>Find an application</h3><input id="n9be-search-apps-input" type="search" placeholder="Filter apps and settings…" aria-label="Filter applications"><div id="n9be-search-apps-results" class="n9be-tool-list" aria-live="polite"></div></section>'; }
        echo '</div></div></aside>';
        if ( ! $frontend ) { echo '<button type="button" id="n9be-admin-tablet-menu-backdrop" data-n9be-menu-backdrop aria-label="Close Open Scholar menu" tabindex="-1"></button>'; }
        echo '<aside id="n9be-quick-drawer" class="n9be-quick-drawer" hidden aria-label="Open Scholar Quick Actions"><button type="button" class="n9be-quick-drawer__backdrop" data-n9be-quick-close tabindex="-1"></button><section class="n9be-quick-drawer__panel"><header><div><span>OPEN SCHOLAR · QUICK ACTIONS</span><strong>Quick Actions</strong></div><div class="n9be-quick-drawer__tools">' . ( ! $frontend && current_user_can( 'edit_posts' ) ? '<a href="' . esc_url( admin_url( 'admin.php?page=ninecode-quick-actions' ) ) . '" class="n9be-quick-configure">Arrange</a>' : '' ) . '<button type="button" data-n9be-quick-close aria-label="Close quick actions"><span class="dashicons dashicons-minus"></span></button></div></header><p class="n9be-quick-drawer__hint">Choose an action now, or use Arrange to add, remove and reorder this launcher.</p><div class="n9be-quick-grid">';
        // Dashboard is pinned first even when user-selected shortcuts change.
        echo '<a class="n9be-quick-item n9be-quick-dashboard" href="' . esc_url( admin_url( 'index.php' ) ) . '" data-n9be-action-id="dashboard"><span class="dashicons dashicons-dashboard"></span><b>Dashboard</b></a>';
        foreach ( $items as $id => $action ) {
            if ( 'dashboard' === $id || in_array( $id, array( 'menu', 'theme_menu', 'search', 'save' ), true ) || ( $frontend && 'view_site' === $id ) ) { continue; }
            $href = isset( $action['url'] ) ? $action['url'] : '';
            if ( $href ) {
                echo '<a class="n9be-quick-item" href="' . esc_url( $href ) . '" data-n9be-action-id="' . esc_attr( $id ) . '"><span class="dashicons dashicons-' . esc_attr( $action['icon'] ) . '"></span><b>' . esc_html( $action['label'] ) . '</b></a>';
            } else {
                echo '<button type="button" class="n9be-quick-item" data-n9be-action="' . esc_attr( $action['handler'] ) . '" data-n9be-action-id="' . esc_attr( $id ) . '"><span class="dashicons dashicons-' . esc_attr( $action['icon'] ) . '"></span><b>' . esc_html( $action['label'] ) . '</b></button>';
            }
        }
        echo '</div><footer>';
        if ( current_user_can( 'edit_posts' ) ) { echo '<a class="n9be-quick-arrange-footer" href="' . esc_url( admin_url( 'admin.php?page=ninecode-quick-actions' ) ) . '">Arrange these actions</a>'; }
        echo '</footer></section></aside>';

        if ( self::can_install_replace_plugins() ) {
            echo '<div id="n9be-plugin-modal" class="n9be-plugin-modal" hidden><button type="button" class="n9be-plugin-backdrop" data-n9be-plugin-close tabindex="-1"></button><section class="n9be-plugin-dialog" role="dialog" aria-modal="true" aria-labelledby="n9be-plugin-title"><header><div><strong id="n9be-plugin-title">Install / Replace Plugin</strong><span>Upload a plugin ZIP. If that plugin already exists, its package is replaced and the current version is activated without leaving the site.</span></div><button type="button" data-n9be-plugin-close aria-label="Close"><span class="dashicons dashicons-minus"></span></button></header><form id="n9be-plugin-form"><label>Plugin ZIP<input type="file" name="pluginzip" accept=".zip,application/zip" required></label><button class="button button-primary" type="submit">Install / Replace + Activate</button><p data-n9be-plugin-status role="status">Choose a plugin ZIP. Existing plugin folders may be replaced.</p><a href="' . esc_url( self_admin_url( 'plugin-install.php?tab=upload' ) ) . '">Open plugin upload page</a></form></section></div>';
        }
    }

    public static function handle_settings_save() {
        if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'You are not allowed to change quick actions.', 'nine-code' ) ); }
        check_admin_referer( 'n9be_quick_actions_save' );
        $catalog = self::catalog();
        $ids = isset( $_POST['action_ids'] ) && is_array( $_POST['action_ids'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['action_ids'] ) ) : array();
        $ids = array_values( array_unique( array_filter( $ids, function( $id ) use ( $catalog ) { return isset( $catalog[ $id ] ); } ) ) );
        update_user_meta( get_current_user_id(), self::OPTION, array_slice( $ids, 0, self::MAX_ACTIONS ) );
        if ( current_user_can( 'manage_options' ) && ! empty( $_POST['custom_label'] ) && ! empty( $_POST['custom_route'] ) ) {
            $label = sanitize_text_field( wp_unslash( $_POST['custom_label'] ) );
            $route = sanitize_text_field( wp_unslash( $_POST['custom_route'] ) );
            if ( preg_match( '#^(?:[a-z0-9_-]+\.php|admin\.php)(?:\?.*)?$#i', $route ) ) {
                $custom = self::custom_actions();
                $id = 'custom_' . sanitize_key( $label ) . '_' . substr( md5( $route ), 0, 6 );
                $custom[ $id ] = array( 'label'=>$label, 'route'=>$route, 'icon'=>sanitize_key( isset( $_POST['custom_icon'] ) ? wp_unslash( $_POST['custom_icon'] ) : 'admin-generic' ) );
                update_option( self::CUSTOM_OPTION, $custom, false );
                $ids[] = $id;
                update_user_meta( get_current_user_id(), self::OPTION, array_slice( array_values( array_unique( $ids ) ), 0, self::MAX_ACTIONS ) );
            }
        }
        wp_safe_redirect( add_query_arg( array( 'page'=>'ninecode-quick-actions', 'n9be_saved'=>1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /** Section a catalogue action is listed under on the settings page. */
    private static function group_of( $id, array $action ) {
        if ( ! empty( $action['group'] ) ) { return $action['group']; }
        if ( ! empty( $action['custom'] ) ) { return 'custom'; }
        if ( 0 === strpos( $id, 'data_' ) ) { return 'data'; }
        return 'site';
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'edit_posts' ) ) { return; }
        $catalog = self::catalog();
        $configured = self::configured_ids();
        echo '<div class="wrap n9be-mason"><div class="n9be-mason-hero"><div><span class="n9be-kicker">OPEN SCHOLAR · QUICK ACTIONS</span><h1>Quick Actions</h1><p>Choose, switch on/off and drag the site functions that appear in the right-side mobile editing drawer. 9 Data Manager actions are included automatically when the Data Manager is active. Other plugins can still register additional actions through the Block Edition action catalogue.</p></div><div class="n9be-mason-badge">Q</div></div>';
        if ( ! empty( $_GET['n9be_saved'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>Quick Actions updated.</p></div>'; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="n9be_quick_actions_save">';
        wp_nonce_field( 'n9be_quick_actions_save' );
        echo '<div class="n9be-panel"><h2>Enabled actions</h2><p class="description">Drag to arrange. Remove an action by unticking it. The right rail permanently provides Quick Actions, Save, Search and Top Bar. The Open Scholar icon alone opens the main menu; Post Editor and Category Manager remain inside the drawer.</p><ul id="n9be-action-sortable" class="n9be-action-sortable">';
        foreach ( $configured as $id ) {
            // These controls are permanent rail / popup actions, never configurable duplicates.
            if ( in_array( $id, array( 'save', 'menu', 'theme_menu', 'search' ), true ) ) { continue; }
            if ( empty( $catalog[ $id ] ) ) { continue; }
            $a = $catalog[ $id ];
            echo '<li><span class="dashicons dashicons-menu"></span><input type="checkbox" checked name="action_ids[]" value="' . esc_attr( $id ) . '"><span class="dashicons dashicons-' . esc_attr( $a['icon'] ) . '"></span><strong>' . esc_html( $a['label'] ) . '</strong><code>' . esc_html( $id ) . '</code></li>';
        }
        echo '</ul></div>';
        $remaining = array_diff_key( $catalog, array_flip( array_merge( $configured, array( 'save', 'menu', 'theme_menu', 'search' ) ) ) );
        echo '<div class="n9be-panel"><h2>Add site functions</h2><p class="description">Every post type, list, taxonomy and tool you can use. Type to filter.</p>';
        echo '<input type="search" class="n9be-action-search" placeholder="Search functions…" aria-label="Search functions">';
        $groups = array( 'content' => 'Content', 'types' => 'Post types', 'taxonomies' => 'Taxonomies', 'data' => 'Data Manager', 'site' => 'Site & tools', 'custom' => 'Custom' );
        $by_group = array_fill_keys( array_keys( $groups ), array() );
        foreach ( $remaining as $id => $a ) {
            if ( ! empty( $a['cap'] ) && ! current_user_can( $a['cap'] ) ) { continue; }
            $by_group[ self::group_of( $id, $a ) ][ $id ] = $a;
        }
        foreach ( $groups as $key => $title ) {
            if ( empty( $by_group[ $key ] ) ) { continue; }
            echo '<h3 class="n9be-action-group">' . esc_html( $title ) . '</h3><div class="n9be-action-library">';
            foreach ( $by_group[ $key ] as $id => $a ) {
                echo '<label data-n9be-filter="' . esc_attr( strtolower( $a['label'] . ' ' . $id ) ) . '"><input type="checkbox" name="action_ids[]" value="' . esc_attr( $id ) . '"><span class="dashicons dashicons-' . esc_attr( $a['icon'] ) . '"></span><b>' . esc_html( $a['label'] ) . '</b><code>' . esc_html( $id ) . '</code></label>';
            }
            echo '</div>';
        }
        echo '<script>(function(){var box=document.querySelector(".n9be-action-search");if(!box)return;box.addEventListener("input",function(){var q=box.value.trim().toLowerCase();document.querySelectorAll("[data-n9be-filter]").forEach(function(el){el.hidden=q!==""&&el.getAttribute("data-n9be-filter").indexOf(q)===-1;});});}());</script>';
        echo '</div>';
        if ( current_user_can( 'manage_options' ) ) {
            echo '<div class="n9be-panel"><h2>Add a custom admin action</h2><p class="description">This adds a safe WordPress admin destination to the action catalogue. Example: <code>edit.php?post_type=workshop</code> or <code>admin.php?page=my-plugin</code>. Future plugins can register richer action functions programmatically.</p><div class="n9be-field-grid"><label>Label<input name="custom_label" placeholder="Workshops"></label><label>Admin route<input name="custom_route" placeholder="edit.php?post_type=workshop"></label><label>Dashicon<input name="custom_icon" value="admin-generic" placeholder="admin-generic"></label></div></div>';
        }
        echo '<div class="n9be-sticky-save"><button class="button button-primary button-hero">Save Quick Actions</button><span>Up to ' . self::MAX_ACTIONS . ' configured actions · Theme actions plus 9 Data Manager actions</span></div></form></div>';
    }

    public static function ajax_plugin_install() {
        check_ajax_referer( 'n9be_quick_plugin_install', 'nonce' );
        if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'upload_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( array( 'message'=>__( 'You are not allowed to install and activate plugins.', 'nine-code' ) ), 403 );
        }
        if ( empty( $_FILES['pluginzip'] ) || ! is_array( $_FILES['pluginzip'] ) ) { wp_send_json_error( array( 'message'=>'Choose a plugin ZIP.' ), 400 ); }
        $upload = $_FILES['pluginzip']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $error = isset( $upload['error'] ) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
        if ( UPLOAD_ERR_OK !== $error ) { wp_send_json_error( array( 'message'=>'WordPress could not receive the ZIP (upload error ' . $error . ').' ), 400 ); }
        $name = isset( $upload['name'] ) ? sanitize_file_name( wp_unslash( $upload['name'] ) ) : '';
        $tmp = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
        $size = isset( $upload['size'] ) ? (int) $upload['size'] : 0;
        if ( ! $name || 'zip' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || ! $tmp || ! is_uploaded_file( $tmp ) ) { wp_send_json_error( array( 'message'=>'The uploaded file is not a verified ZIP package.' ), 400 ); }
        $max = (int) wp_max_upload_size();
        if ( $max > 0 && $size > $max ) { wp_send_json_error( array( 'message'=>'The plugin ZIP exceeds this site upload limit.' ), 413 ); }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        if ( ! class_exists( 'Plugin_Upgrader', false ) ) { require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php'; }
        if ( ! class_exists( 'WP_Ajax_Upgrader_Skin', false ) ) { require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php'; }
        if ( ! class_exists( 'Plugin_Upgrader', false ) || ! class_exists( 'WP_Ajax_Upgrader_Skin', false ) ) { wp_send_json_error( array( 'message'=>'WordPress plugin installer classes are unavailable.' ), 500 ); }
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        $result = $upgrader->install( $tmp, array( 'clear_update_cache'=>true, 'overwrite_package'=>true ) );
        if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message'=>$result->get_error_message(), 'code'=>$result->get_error_code() ), 500 ); }
        if ( is_wp_error( $skin->result ) ) { wp_send_json_error( array( 'message'=>$skin->result->get_error_message(), 'code'=>$skin->result->get_error_code() ), 500 ); }
        if ( method_exists( $skin, 'get_errors' ) && $skin->get_errors()->has_errors() ) {
            $errors = $skin->get_errors();
            wp_send_json_error( array( 'message'=>$errors->get_error_message(), 'code'=>$errors->get_error_code() ), 500 );
        }
        if ( ! $result ) { wp_send_json_error( array( 'message'=>'WordPress could not install the plugin package.' ), 500 ); }
        $plugin = $upgrader->plugin_info();
        if ( ! $plugin || ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) { wp_send_json_error( array( 'message'=>'The plugin was unpacked, but its main file could not be identified.' ), 500 ); }
        $network_wide = false;
        if ( is_multisite() && function_exists( 'is_network_only_plugin' ) && is_network_only_plugin( $plugin ) ) {
            if ( ! current_user_can( 'manage_network_plugins' ) ) {
                wp_send_json_error( array( 'message'=>'The plugin was installed, but it is network-only and this account cannot activate network plugins.', 'installed'=>true, 'plugin'=>$plugin ), 403 );
            }
            $network_wide = true;
        }
        $activated = activate_plugin( $plugin, '', $network_wide, false );
        if ( is_wp_error( $activated ) ) { wp_send_json_error( array( 'message'=>'Installed, but activation failed: ' . $activated->get_error_message(), 'installed'=>true, 'plugin'=>$plugin ), 500 ); }
        wp_clean_plugins_cache( true );
        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
        $plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : basename( dirname( $plugin ) );
        wp_send_json_success( array( 'message'=>$plugin_name . ' installed/replaced and activated.', 'pluginName'=>$plugin_name, 'pluginFile'=>$plugin, 'pluginsUrl'=>self_admin_url( 'plugins.php' ) ) );
    }
}
