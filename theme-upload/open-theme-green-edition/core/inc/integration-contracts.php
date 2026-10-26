<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Additive 9CodePress contract registry.
 *
 * Plugins announce compatibility and assistance metadata through the
 * ninecodepress_component_contracts filter. Core only normalizes/collects the
 * contracts; it does not take ownership of plugin data or business logic.
 */
function ninecodepress_component_contracts() {
    $contracts = array(
        '9core' => array(
            'id'      => '9core',
            'version' => defined( 'NCU_CORE_VERSION' ) ? NCU_CORE_VERSION : '',
            'api'     => defined( 'NCU_CORE_API_VERSION' ) ? (int) NCU_CORE_API_VERSION : 0,
            'role'    => 'shared-infrastructure',
        ),
    );
    $contracts = apply_filters( 'ninecodepress_component_contracts', $contracts );
    if ( ! is_array( $contracts ) ) { return array(); }
    $out = array();
    foreach ( $contracts as $id => $meta ) {
        $key = sanitize_key( is_string( $id ) ? $id : ( is_array( $meta ) && isset( $meta['id'] ) ? $meta['id'] : '' ) );
        if ( ! $key || ! is_array( $meta ) ) { continue; }
        $meta['id'] = $key;
        $out[ $key ] = $meta;
    }
    return $out;
}

function ninecodepress_semantic_host_context( $context = array() ) {
    $context = is_array( $context ) ? $context : array();
    $defaults = array(
        'host'                  => 'wordpress',
        'navigation_owner'      => 'host_plugin',
        'data_owner'            => 'host_plugin',
        'business_logic_owner'  => 'host_plugin',
        'theme_assistance'      => 'style_only',
        'allow_nav_rewrite'     => false,
        'allow_data_rewrite'    => false,
    );
    return apply_filters( 'ninecodepress_semantic_host_context', array_merge( $defaults, $context ) );
}


/**
 * Additive application-surface ownership contract.
 *
 * A companion plugin may claim only the request/surface it actually owns.
 */
function ninecodepress_surface_context( $context = array() ) {
    $context = is_array( $context ) ? $context : array();
    $defaults = array(
        'owner'                        => 'wordpress',
        'owns_shell'                   => false,
        'suppress_theme_header'        => false,
        'suppress_theme_footer'        => false,
        'suppress_theme_quick_actions' => false,
        'inherit_design_tokens'        => true,
        'reason'                       => '',
    );
    $resolved = apply_filters( 'ninecodepress_surface_context', array_merge( $defaults, $context ) );
    return is_array( $resolved ) ? $resolved : $defaults;
}

/**
 * Conference Update interoperability policy.
 *
 * This describes boundaries only. Core does not register foreign CPTs,
 * taxonomies, Elementor locations, ACF groups or front-end-admin routes.
 */
function ninecodepress_interop_policy() {
    $policy = array(
        'routing_owner'          => 'wordpress',
        'template_owner'         => 'request_owner',
        'data_owner'             => 'provider_plugin',
        'register_foreign_cpts'  => false,
        'register_foreign_taxonomies' => false,
        'rewrite_foreign_meta'   => false,
        'rewrite_foreign_nav'    => false,
        'elementor_yield'        => true,
        'acf_provider_only'      => true,
        'frontend_admin_coexist' => true,
        'public_maintenance'     => false,
        'rewrite_flush_on_request' => false,
    );
    $filtered = apply_filters( 'ninecodepress_interop_policy', $policy );
    return is_array( $filtered ) ? array_merge( $policy, $filtered ) : $policy;
}

/** Runtime capability discovery is descriptive only; it never loads a plugin. */
function ninecodepress_interop_capabilities() {
    $caps = array(
        'elementor'      => defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' ),
        'acf'            => function_exists( 'acf_get_field_groups' ) || class_exists( 'ACF', false ),
        'frontend_admin' => defined( 'FRONTEND_ADMIN_VERSION' ) || class_exists( 'Frontend_Admin', false ) || function_exists( 'frontend_admin_form' ),
        'open_scholar'   => post_type_exists( 'os_publication' ) || defined( 'OSE_VERSION' ) || defined( 'OPEN_SCHOLAR_VERSION' ),
    );
    return apply_filters( 'ninecodepress_interop_capabilities', $caps );
}

/**
 * Small Open Scholar plugin seam. Plugins describe themselves through the
 * filter; the theme never scans or rewrites their private records.
 */
function ncu_open_scholar_theme_integrations() {
    $items = apply_filters( 'ncu_open_scholar_theme_integrations', array() );
    if ( ! is_array( $items ) ) { return array(); }
    $out = array();
    foreach ( $items as $id => $item ) {
        if ( ! is_array( $item ) ) { continue; }
        $key = sanitize_key( is_string( $id ) ? $id : ( $item['id'] ?? '' ) );
        if ( ! $key ) { continue; }
        $out[ $key ] = array(
            'id'          => $key,
            'label'       => sanitize_text_field( $item['label'] ?? $key ),
            'description' => sanitize_text_field( $item['description'] ?? '' ),
            'version'     => sanitize_text_field( $item['version'] ?? '' ),
            'url'         => isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '',
            'capabilities'=> array_values( array_filter( array_map( 'sanitize_key', (array) ( $item['capabilities'] ?? array() ) ) ) ),
        );
    }
    return $out;
}

function ncu_open_scholar_theme_design_tokens() {
    $contract = function_exists( 'ncu_open_scholar_theme_contract' ) ? ncu_open_scholar_theme_contract() : array();
    $tokens = isset( $contract['palette'] ) && is_array( $contract['palette'] ) ? $contract['palette'] : array();
    $tokens = array_merge( $tokens, array(
        'primary' => $tokens['accent'] ?? '#14532d',
        'surface' => $tokens['card'] ?? '#ffffff',
        'radius'  => 8,
        'runtime' => 'plugin-host',
    ) );
    return apply_filters( 'ncu_open_scholar_theme_design_tokens', $tokens );
}

/** Legacy name retained as an adapter for existing Open Scholar plugins. */
if ( ! function_exists( 'ninecode_theme_get_design_tokens' ) ) {
    function ninecode_theme_get_design_tokens() { return ncu_open_scholar_theme_design_tokens(); }
}

add_action( 'after_setup_theme', 'ncu_open_scholar_theme_announce_host', 100 );
function ncu_open_scholar_theme_announce_host() {
    add_theme_support( 'open-scholar-plugin-host', ncu_open_scholar_theme_contract() );
    do_action( 'ncu_open_scholar_theme_ready', ncu_open_scholar_theme_contract() );
}
