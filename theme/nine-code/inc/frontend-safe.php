<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Minimal public runtime for Nine Code.
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

/* Legacy chrome helpers remain callable for compatibility but never render. */
function ncu_safe_engine_surface_active( $surface ) {
    return false;
}

function ncu_safe_render_site_header() {
    return;
}

function ncu_safe_render_site_footer() {
    return;
}

/** Whether a presentation feature (title, meta, comments...) should print for this entry. */
function ncu_safe_show( $feature, $default = true ) {
    return (bool) apply_filters( 'open_scholar_theme_show_' . sanitize_key( $feature ), $default, get_the_ID() );
}
