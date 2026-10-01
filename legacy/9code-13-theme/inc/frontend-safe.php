<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Minimal public runtime for 9Code Theme.
 *
 * No Header/Footer engine, style takeover, host-mode resolver, custom content
 * renderer, starter migration, or editor runtime is loaded here. WordPress and
 * plugins retain control of routing/content. This file is intentionally small.
 */
$GLOBALS['ncu_safe_template_owner'] = 'unknown';

/**
 * Record whether WordPress selected a Theme template or a template supplied by
 * another plugin.  The Theme never needs to know a Conference/Open Scholar/etc
 * slug: ownership is derived from the actual selected template path.
 */
function ncu_safe_capture_template_owner( $template ) {
    $template = (string) $template;
    if ( '' === $template ) { return $template; }
    $normal = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $template ) : str_replace( '\\', '/', $template );
    $roots = array();
    foreach ( array( 'get_template_directory', 'get_stylesheet_directory' ) as $fn ) {
        if ( function_exists( $fn ) ) {
            $root = call_user_func( $fn );
            $root = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $root ) : str_replace( '\\', '/', $root );
            $root = rtrim( (string) $root, '/' ) . '/';
            if ( '/' !== $root ) { $roots[] = $root; }
        }
    }
    $owner = 'external';
    foreach ( array_unique( $roots ) as $root ) {
        if ( 0 === strpos( $normal, $root ) ) { $owner = 'theme'; break; }
    }
    $GLOBALS['ncu_safe_template_owner'] = apply_filters( 'ninecode_theme_template_owner', $owner, $template );
    return $template;
}
add_filter( 'template_include', 'ncu_safe_capture_template_owner', PHP_INT_MAX );

function ncu_safe_is_elementor_owned_context() {
    $post_id = function_exists( 'get_queried_object_id' ) ? absint( get_queried_object_id() ) : 0;
    if ( $post_id && function_exists( 'get_post_meta' ) && 'builder' === (string) get_post_meta( $post_id, '_elementor_edit_mode', true ) ) { return true; }
    if ( $post_id && function_exists( 'get_page_template_slug' ) ) {
        $slug = (string) get_page_template_slug( $post_id );
        if ( in_array( $slug, array( 'elementor_canvas', 'elementor_header_footer' ), true ) ) { return true; }
    }
    if ( function_exists( 'get_query_var' ) && get_query_var( 'elementor-preview', '' ) ) { return true; }
    return (bool) apply_filters( 'ninecode_theme_elementor_owned_context', false, $post_id );
}

function ncu_safe_is_custom_post_type_context() {
    if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! function_exists( 'get_post_type' ) ) { return false; }
    $post_id = function_exists( 'get_queried_object_id' ) ? absint( get_queried_object_id() ) : 0;
    $post_type = (string) get_post_type( $post_id );
    $custom = $post_type && ! in_array( $post_type, array( 'post', 'page' ), true );
    return (bool) apply_filters( 'ninecode_theme_custom_post_type_context', $custom, $post_type, $post_id );
}

/**
 * Presentation CSS is deliberately opt-in for Theme-owned native WordPress
 * views.  CPT/plugin shells, Elementor and any surface that explicitly opts out
 * receive design tokens and isolated tools only, never Theme global resets.
 */
function ncu_safe_should_enqueue_presentation_css() {
    $owner = isset( $GLOBALS['ncu_safe_template_owner'] ) ? (string) $GLOBALS['ncu_safe_template_owner'] : 'unknown';
    $allow = true;
    if ( 'external' === $owner || ncu_safe_is_custom_post_type_context() || ncu_safe_is_elementor_owned_context() ) { $allow = false; }
    return (bool) apply_filters( 'ninecode_theme_enqueue_presentation_css', $allow, $owner );
}

function ncu_safe_public_enqueue() {
    /* style.css contains the Theme header only; enqueue it as the stable handle
     * used for token injection without imposing presentation rules. */
    wp_enqueue_style( 'ncu-theme-style', get_stylesheet_uri(), array(), NCU_THEME_VERSION );
    if ( ncu_safe_should_enqueue_presentation_css() ) {
        wp_enqueue_style( 'ncu-theme-main', NCU_THEME_URI . '/assets/css/main.css', array( 'ncu-theme-style' ), NCU_THEME_VERSION );
    }
    if ( function_exists( 'wp_add_inline_style' ) && function_exists( 'ninecode_theme_get_design_tokens' ) ) {
        $t = ninecode_theme_get_design_tokens();
        $css = ':root{--ncu-accent:' . $t['primary'] . ';--ncu-surface:' . $t['surface'] . ';--ncu-text:' . $t['text'] . ';--ncu-muted:' . $t['muted'] . ';--ncu-border:' . $t['border'] . ';--ncu-radius:' . absint( $t['radius'] ) . 'px;}';
        wp_add_inline_style( 'ncu-theme-style', $css );
    }
}
add_action( 'wp_enqueue_scripts', 'ncu_safe_public_enqueue', 20 );

/* Elementor theme support is declarative and safe; do not register locations here. */
function ncu_safe_elementor_support() {
    add_theme_support( 'elementor' );
    add_theme_support( 'elementor-pro' );
}
add_action( 'after_setup_theme', 'ncu_safe_elementor_support', 30 );

/*
 * Keep a tiny component contract available to companion plugins without loading
 * the full presentation/host-compatibility engine.
 */
if ( ! function_exists( 'ninecode_theme_get_design_tokens' ) ) {
    function ninecode_theme_get_design_tokens() {
        $defaults = function_exists( 'ncu_theme_defaults' ) ? ncu_theme_defaults() : array();
        $stored = get_option( 'ncu_settings', array() );
        $stored = is_array( $stored ) ? $stored : array();
        $d = array_merge( $defaults, $stored );
        $primary = ! empty( $d['style_custom_primary'] ) ? $d['style_custom_primary'] : ( $d['accent_color'] ?? '#000000' );
        $surface = ! empty( $d['style_custom_surface'] ) ? $d['style_custom_surface'] : ( $d['surface_color'] ?? '#ffffff' );
        $text = ! empty( $d['style_custom_text'] ) ? $d['style_custom_text'] : ( $d['text_color'] ?? '#000000' );
        $muted = ! empty( $d['style_custom_muted'] ) ? $d['style_custom_muted'] : ( $d['muted_color'] ?? '#5f6368' );
        $border = ! empty( $d['style_custom_border'] ) ? $d['style_custom_border'] : '#e5e7eb';
        return array(
            'primary' => sanitize_hex_color( $primary ) ?: '#000000',
            'surface' => sanitize_hex_color( $surface ) ?: '#ffffff',
            'text'    => sanitize_hex_color( $text ) ?: '#000000',
            'muted'   => sanitize_hex_color( $muted ) ?: '#5f6368',
            'border'  => sanitize_hex_color( $border ) ?: '#e5e7eb',
            'radius'  => isset( $d['radius'] ) ? absint( $d['radius'] ) : 16,
            'runtime' => 'safe-public',
        );
    }
}


if ( ! function_exists( 'ninecode_theme_assistance_contract' ) ) {
    function ninecode_theme_assistance_contract() {
        return array(
            'id'                    => '9code-theme',
            'version'               => defined( 'NCU_THEME_VERSION' ) ? NCU_THEME_VERSION : '',
            'api'                   => defined( 'NCU_THEME_API_VERSION' ) ? (int) NCU_THEME_API_VERSION : 0,
            'role'                  => 'presentation-parent',
            'design_tokens'         => 'ninecode_theme_get_design_tokens',
            'semantic_policy'       => 'style_only',
            'navigation_owner'      => 'wordpress-or-host-plugin',
            'data_owner'            => 'host_plugin',
            'business_logic_owner'  => 'host_plugin',
            'may_rewrite_host_nav'  => false,
            'may_rewrite_host_data' => false,
            'public_runtime'        => 'safe',
            'css_policy'            => 'theme-native-only',
            'external_template_policy' => 'yield',
            'custom_post_type_policy'  => 'yield',
            'elementor_policy'         => 'yield',
            'acf_policy'               => 'provider-only',
            'frontend_admin_policy'    => 'coexist',
        );
    }
}


function ncu_safe_theme_component_contract( $contracts ) {
    $contracts = is_array( $contracts ) ? $contracts : array();
    $contracts['9code-theme'] = ninecode_theme_assistance_contract();
    return $contracts;
}
add_filter( 'ninecodepress_component_contracts', 'ncu_safe_theme_component_contract', 20 );

/**
 * Minimal logged-in front-end launcher.
 * Uses only stable WordPress APIs. The plugin-install AJAX handler remains in
 * the admin runtime because admin-ajax.php is an admin request.
 */
function ncu_safe_surface_context() {
    $defaults = array(
        'owner'                        => 'wordpress',
        'owns_shell'                   => false,
        'suppress_theme_header'        => false,
        'suppress_theme_footer'        => false,
        'suppress_theme_quick_actions' => false,
        'inherit_design_tokens'        => true,
        'reason'                       => '',
    );
    if ( function_exists( 'ninecodepress_surface_context' ) ) {
        $resolved = ninecodepress_surface_context( $defaults );
    } else {
        $resolved = apply_filters( 'ninecodepress_surface_context', $defaults );
    }
    return is_array( $resolved ) ? array_merge( $defaults, $resolved ) : $defaults;
}

function ncu_safe_front_launcher_allowed() {
    $surface = ncu_safe_surface_context();
    if ( ! empty( $surface['suppress_theme_quick_actions'] ) ) { return false; }
    return function_exists( 'is_user_logged_in' ) && is_user_logged_in()
        && ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ) );
}

function ncu_safe_front_launcher_assets() {
    if ( ! ncu_safe_front_launcher_allowed() ) { return; }
    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style( 'n9be-quick-actions', NCU_THEME_URI . '/assets/css/block-edition-quick-actions.css', array(), NCU_THEME_VERSION );
    wp_enqueue_script( 'n9be-quick-actions', NCU_THEME_URI . '/assets/js/block-edition-quick-actions.js', array(), NCU_THEME_VERSION, true );
    wp_localize_script( 'n9be-quick-actions', 'n9beQuickActions', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'n9be_quick_plugin_install' ),
        'siteUrl' => home_url( '/' ),
        'context' => 'frontend',
        'maxUploadBytes' => (int) wp_max_upload_size(),
    ) );
}
add_action( 'wp_enqueue_scripts', 'ncu_safe_front_launcher_assets', 1001 );

function ncu_safe_front_launcher_item( $label, $icon, $url ) {
    echo '<a class="n9be-quick-item" href="' . esc_url( $url ) . '"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span><b>' . esc_html( $label ) . '</b></a>';
}

function ncu_safe_front_launcher_markup() {
    if ( ! ncu_safe_front_launcher_allowed() ) { return; }
    echo '<div id="n9be-admin-launcher" data-n9be-context="frontend" data-n9be-site-url="' . esc_attr( home_url( '/' ) ) . '">';
    echo '<button type="button" class="n9be-rail-button" data-n9be-action="theme_menu" title="Theme menu"><span class="dashicons dashicons-menu"></span><span class="screen-reader-text">Theme menu</span></button>';
    echo '<button type="button" class="n9be-rail-button" data-n9be-action="save" title="Save"><span class="dashicons dashicons-saved"></span><span class="screen-reader-text">Save</span></button>';
    echo '<button type="button" class="n9be-rail-button" data-n9be-quick-toggle aria-expanded="false" aria-controls="n9be-quick-drawer" title="Quick Actions"><span class="dashicons dashicons-grid-view"></span><span class="screen-reader-text">Quick Actions</span></button>';
    echo '</div>';
    echo '<aside id="n9be-quick-drawer" class="n9be-quick-drawer" hidden aria-label="9Code Quick Actions"><button type="button" class="n9be-quick-drawer__backdrop" data-n9be-quick-close tabindex="-1"></button><section class="n9be-quick-drawer__panel"><header><div><span>9CODE · SAFE RUNTIME</span><strong>Quick Actions</strong></div><button type="button" data-n9be-quick-close aria-label="Close quick actions"><span class="dashicons dashicons-no-alt"></span></button></header><div class="n9be-quick-grid">';
    if ( current_user_can( 'edit_theme_options' ) ) { ncu_safe_front_launcher_item( '9Code Theme', 'admin-appearance', admin_url( 'themes.php?page=ninecode-theme-display' ) ); }
    if ( defined( 'NINE55_ULTRON_DATA_VERSION' ) || class_exists( 'Nine55_Ultron_Data', false ) ) {
        if ( current_user_can( 'edit_posts' ) ) { ncu_safe_front_launcher_item( 'Post Editor', 'edit-page', admin_url( 'admin.php?page=nine-post-manager' ) ); }
        if ( current_user_can( 'manage_categories' ) ) { ncu_safe_front_launcher_item( 'Category Manager', 'category', admin_url( 'admin.php?page=nine-category-manager' ) ); }
        if ( current_user_can( 'edit_posts' ) ) { ncu_safe_front_launcher_item( 'Form Manager', 'feedback', admin_url( 'admin.php?page=nine10-form-manager' ) ); }
        if ( current_user_can( 'edit_posts' ) ) { ncu_safe_front_launcher_item( 'Data Backup', 'download', admin_url( 'admin.php?page=nine10-data-backup' ) ); }
    }
    if ( current_user_can( 'install_plugins' ) && current_user_can( 'upload_plugins' ) && current_user_can( 'activate_plugins' ) ) {
        echo '<button type="button" class="n9be-quick-item" data-n9be-action="add_plugin"><span class="dashicons dashicons-plus-alt2"></span><b>Install / Replace Plugin</b></button>';
    }
    echo '</div></section></aside>';
    if ( current_user_can( 'install_plugins' ) && current_user_can( 'upload_plugins' ) && current_user_can( 'activate_plugins' ) ) {
        echo '<div id="n9be-plugin-modal" class="n9be-plugin-modal" hidden><button type="button" class="n9be-plugin-backdrop" data-n9be-plugin-close tabindex="-1"></button><section class="n9be-plugin-dialog" role="dialog" aria-modal="true" aria-labelledby="n9be-plugin-title"><header><div><strong id="n9be-plugin-title">Install / Replace Plugin</strong><span>Upload a plugin ZIP from the front end.</span></div><button type="button" data-n9be-plugin-close aria-label="Close"><span class="dashicons dashicons-no-alt"></span></button></header><form id="n9be-plugin-form"><label>Plugin ZIP<input type="file" name="pluginzip" accept=".zip,application/zip" required></label><button class="button button-primary" type="submit">Install / Replace + Activate</button><p data-n9be-plugin-status role="status">Choose a plugin ZIP.</p><a href="' . esc_url( self_admin_url( 'plugin-install.php?tab=upload' ) ) . '">Use WordPress uploader</a></form></section></div>';
    }
}
add_action( 'wp_footer', 'ncu_safe_front_launcher_markup', 1001 );
