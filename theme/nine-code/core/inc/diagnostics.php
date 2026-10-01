<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_post_ncu_export_diagnostics', 'ncu_export_diagnostics' );
function ncu_export_diagnostics() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code' ) ); }
    check_admin_referer( 'ncu_export_diagnostics' );
    $theme = wp_get_theme();
    $parent = $theme->parent();
    $payload = array(
        'format'       => '9-code-ultra-diagnostics',
        'schema'       => 5,
        'generated_at' => gmdate( 'c' ),
        'wordpress'    => get_bloginfo( 'version' ),
        'php'          => PHP_VERSION,
        'theme'        => array(
            'name'    => $theme->get( 'Name' ),
            'version' => $theme->get( 'Version' ),
            'template'=> $theme->get_template(),
            'parent'  => $parent ? $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) : '',
            'api'     => defined( 'NCU_THEME_API_VERSION' ) ? NCU_THEME_API_VERSION : 0,
        ),
        'core'         => array( 'version' => NCU_CORE_VERSION, 'api' => NCU_CORE_API_VERSION ),
        'design_authority' => array(
            'aggressive_style_takeover' => ! empty( ncu_get_settings()['aggressive_style_takeover'] ),
            'selected_preset' => ncu_get_settings()['style_takeover_preset'],
            'tokens' => function_exists( 'ncu_get_effective_design_tokens' ) ? ncu_get_effective_design_tokens() : array(),
            'dark_tokens' => function_exists( 'ncu_style_dark_companion_tokens' ) ? ncu_style_dark_companion_tokens() : array(),
        ),
        'builders'     => function_exists( 'ncu_get_builder_settings' ) ? ncu_get_builder_settings() : array(),
        'component_contracts' => function_exists( 'ninecodepress_component_contracts' ) ? ninecodepress_component_contracts() : array(),
        'semantic_host' => function_exists( 'ninecodepress_semantic_host_context' ) ? ninecodepress_semantic_host_context() : array(),
        'surface_context' => function_exists( 'ninecodepress_surface_context' ) ? ninecodepress_surface_context() : array(),
        'interop_policy' => function_exists( 'ninecodepress_interop_policy' ) ? ninecodepress_interop_policy() : array(),
        'interop_capabilities' => function_exists( 'ninecodepress_interop_capabilities' ) ? ninecodepress_interop_capabilities() : array(),
        'active_plugins' => function_exists( 'ncu_doctor_active_plugins' ) ? ncu_doctor_active_plugins() : array(),
        'environment'  => array(
            'memory_limit'       => ini_get( 'memory_limit' ),
            'max_execution_time' => ini_get( 'max_execution_time' ),
            'debug'              => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'debug_log'          => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
            'multisite'          => is_multisite(),
        ),
    );
    ncu_send_json_download( '9-code-ultra-diagnostics-' . gmdate( 'Ymd-His' ) . '.json', $payload );
}

add_filter( 'site_status_tests', 'ncu_register_site_health_tests' );
function ncu_register_site_health_tests( $tests ) {
    $tests['direct']['ncu_stack'] = array( 'label' => __( 'Nine Code stack', 'nine-code' ), 'test' => 'ncu_site_health_stack' );
    return $tests;
}

function ncu_site_health_stack() {
    $theme = wp_get_theme();
    $slugs = array( $theme->get_stylesheet(), $theme->get_template() );
    $is_ncu = defined( 'NCU_THEME_API_VERSION' ) || (bool) array_intersect( array( 'nine-code-ultra', '10-code-theme', '9code-12-theme', '9code-13-theme' ), $slugs );
    $theme_api = defined( 'NCU_THEME_API_VERSION' ) ? (int) NCU_THEME_API_VERSION : 0;
    $api_ok = $is_ncu && ncu_core_theme_api_compatible( $theme_api );
    $status = $api_ok ? 'good' : 'recommended';
    $label = $api_ok ? __( 'Nine Code theme and Core API are aligned', 'nine-code' ) : __( 'Review the Nine Code theme/Core pairing', 'nine-code' );
    return array(
        'label'       => $label,
        'status'      => $status,
        'badge'       => array( 'label' => 'Nine Code', 'color' => 'gray' ),
        'description' => '<p>' . esc_html__( 'Conference Update stack: Nine Code + 9Core (Core API 14) + 9 Data Manager. WordPress owns routing; each Conference/Open Scholar/flyer/9stagram/Special/Elementor/front-end-admin surface keeps its own template, data and business logic; the suite supplies additive tools and design tokens only.', 'nine-code' ) . '</p>',
        'actions'     => '',
        'test'        => 'ncu_stack',
    );
}


add_action( 'admin_notices', 'ncu_core_api_mismatch_notice' );
function ncu_core_api_mismatch_notice() {
    if ( ! current_user_can( 'manage_options' ) || ! defined( 'NCU_THEME_API_VERSION' ) ) { return; }
    if ( ncu_core_theme_api_compatible( NCU_THEME_API_VERSION ) ) { return; }
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $id = $screen && isset( $screen->id ) ? (string) $screen->id : '';
    if ( false === strpos( $id, 'nine-code' ) && ! in_array( $id, array( 'themes', 'plugins' ), true ) ) { return; }
    echo '<div class="notice notice-error is-dismissible"><p><strong>Nine Code:</strong> ' . esc_html__( 'Theme/Core API mismatch detected. Public rendering remains isolated by the parent theme fallback, but builder and Doctor settings should not be changed until matching versions are installed.', 'nine-code' ) . '</p></div>';
}
