<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_notices', 'ncu_core_recommendation_notice' );
function ncu_core_recommendation_notice() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->base, array( 'themes', 'dashboard' ), true ) ) { return; }
    if ( function_exists( 'ncu_get_settings' ) && ! ncu_theme_core_compatible() ) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>9Code 15 Theme:</strong> ' . esc_html__( 'The active 9Core 15 Core API does not match this theme. The theme has isolated itself from Core and is using safe built-in defaults. Install the matching Core version before changing theme settings.', 'nine-code-ultra' ) . '</p></div>';
        return;
    }
    if ( ncu_theme_core_compatible() ) { return; }
    echo '<div class="notice notice-info is-dismissible"><p><strong>9Code 15 Theme:</strong> ' . esc_html__( 'The theme is running safely with built-in defaults. Activate 9Core 15 Core to unlock the dedicated dashboard, builder routing, Doctor, durable settings and recovery tools.', 'nine-code-ultra' ) . '</p></div>';
}

add_action( 'admin_menu', 'ncu_theme_fallback_admin_menu', 99 );
function ncu_theme_fallback_admin_menu() {
    if ( function_exists( 'ncu_get_settings' ) ) { return; }
    add_menu_page( '9Code 15 Theme', '9Code 15', 'manage_options', 'nine-code-ultra', 'ncu_theme_fallback_dashboard', 'dashicons-admin-appearance', 3 );
}

function ncu_theme_fallback_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    ?><div class="wrap"><h1>9Code 15 Theme</h1><p>The parent theme is operating in safe fallback mode. Install/activate <strong>9Core 15 Core</strong> for Data Manager, complete theme management, Builders and Doctor.</p><p>The public site remains usable without Core; no retired workflow plugin is required.</p></div><?php
}
