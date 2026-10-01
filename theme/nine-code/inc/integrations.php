<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'after_setup_theme', 'ncu_elementor_support' );
function ncu_elementor_support() {
    add_theme_support( 'elementor' );
    add_theme_support( 'elementor-pro' );
}

add_action( 'elementor/theme/register_locations', 'ncu_elementor_register_locations' );
function ncu_elementor_register_locations( $manager ) {
    if ( is_object( $manager ) && method_exists( $manager, 'register_all_core_location' ) ) {
        $manager->register_all_core_location();
    }
}

add_filter( 'rank_math/frontend/breadcrumb/html', 'ncu_rankmath_breadcrumb_html' );
function ncu_rankmath_breadcrumb_html( $html ) {
    if ( is_singular() && function_exists( 'ncu_should_show_post_feature' ) ) {
        $post_id = get_queried_object_id();
        if ( $post_id && ! ncu_should_show_post_feature( $post_id, 'breadcrumbs', true ) ) { return ''; }
    }
    return '<div class="ncu-breadcrumbs">' . $html . '</div>';
}


/**
 * 9CodePress assistance contract.
 *
 * The Theme may style and assist a host plugin, but must not reinterpret the
 * host plugin's navigation, data ownership or business logic. Plugins can use
 * this contract without hard-depending on 9Core or Mason.
 */
if ( ! function_exists( 'ninecode_theme_get_design_tokens' ) ) {
    function ninecode_theme_get_design_tokens() {
        $tokens = function_exists( 'ncu_theme_effective_design_tokens' ) ? ncu_theme_effective_design_tokens() : array();
        return apply_filters( 'ninecode_theme_design_tokens', is_array( $tokens ) ? $tokens : array() );
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
            'css_variable_prefix'   => '--ncu-design-',
            'semantic_policy'       => 'style_only',
            'navigation_owner'      => 'host_plugin',
            'data_owner'            => 'host_plugin',
            'business_logic_owner'  => 'host_plugin',
            'may_rewrite_host_nav'  => false,
            'may_rewrite_host_data' => false,
        );
    }
}


add_filter( 'ninecodepress_component_contracts', 'ninecode_theme_component_contract', 20 );
function ninecode_theme_component_contract( $contracts ) {
    $contracts = is_array( $contracts ) ? $contracts : array();
    $contracts['9code-theme'] = ninecode_theme_assistance_contract();
    return $contracts;
}

add_filter( 'ninecodepress_semantic_host_context', 'ninecode_theme_semantic_host_guard_defaults', 5 );
function ninecode_theme_semantic_host_guard_defaults( $context ) {
    $context = is_array( $context ) ? $context : array();
    $defaults = array(
        'navigation_owner'      => 'host_plugin',
        'data_owner'            => 'host_plugin',
        'business_logic_owner'  => 'host_plugin',
        'theme_assistance'      => 'style_only',
        'allow_nav_rewrite'     => false,
        'allow_data_rewrite'    => false,
    );
    return array_merge( $defaults, $context );
}
