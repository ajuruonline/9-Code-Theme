<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NCU_THEME_VERSION', '16.1.0' );
define( 'NCU_THEME_RELEASE_SHOT', 'Nine Code baseline' );
define( 'NCU_THEME_API_VERSION', 14 );
define( 'NINECODE_SUITE_THEME_VERSION', NCU_THEME_VERSION );
if ( ! defined( 'NINECODE_SUITE_CONTRACT_MAJOR' ) ) { define( 'NINECODE_SUITE_CONTRACT_MAJOR', 14 ); }
define( 'NCU_THEME_DIR', get_template_directory() );
define( 'NCU_THEME_URI', get_template_directory_uri() );

/*
 * 14.2.8 Mobile Editor Focus Update safe-public architecture.
 *
 * The public site must remain renderable even if an optional 9Code presentation
 * subsystem has bad legacy settings, a companion integration fails, or a future
 * plugin behaves unexpectedly.  Public requests therefore load a deliberately
 * small WordPress-native runtime.  Full configuration/admin modules continue to
 * load in wp-admin where they cannot take down public Pages/Posts.
 */
function ncu_theme_is_admin_runtime() {
    return function_exists( 'is_admin' ) && is_admin();
}

/**
 * The Header & Footer engine is only needed on the public site when an
 * administrator has switched a surface on. Load it then, isolated so a fault in
 * it can never take the site down.
 */
function ncu_theme_maybe_load_header_footer_engine() {
    $header = get_option( 'n9lh8_settings', array() );
    $footer = get_option( 'n9f_settings', array() );
    $on = ( is_array( $header ) && 'yes' === ( $header['enabled'] ?? '' ) ) || ( is_array( $footer ) && 'yes' === ( $footer['enabled'] ?? '' ) );
    if ( ! $on ) { return; }
    try {
        require_once NCU_THEME_DIR . '/inc/surface-contract.php';
        require_once NCU_THEME_DIR . '/inc/header-footer/bootstrap.php';
    } catch ( \Throwable $e ) {
        error_log( '[Nine Code ' . NCU_THEME_VERSION . '] header/footer engine disabled for this request: ' . $e->getMessage() );
    }
}

/* Safe on every request: defaults, native WordPress theme support and helpers. */
require_once NCU_THEME_DIR . '/inc/defaults.php';
require_once NCU_THEME_DIR . '/inc/setup.php';
require_once NCU_THEME_DIR . '/inc/migrate-legacy.php';

/*
 * Core module (settings, Style Authority, admin workspace, editor tools...).
 * It used to ship as the separate "Nine Code" plugin. If that plugin is still
 * active in this request it has already declared the same functions, so skip
 * the bundled copy; inc/migrate-legacy.php deactivates the plugin on the next
 * admin request and the bundled module takes over.
 */
if ( ! function_exists( 'ncu_core_defaults' ) ) {
    require_once NCU_THEME_DIR . '/core/bootstrap.php';
}

if ( ncu_theme_is_admin_runtime() ) {
    require_once NCU_THEME_DIR . '/inc/helpers.php';
    /* Configuration/editor modules. These no longer execute on normal public requests. */
    require_once NCU_THEME_DIR . '/inc/host-compatibility.php';
    require_once NCU_THEME_DIR . '/inc/post-display-settings.php';
    require_once NCU_THEME_DIR . '/inc/style-takeover.php';
    require_once NCU_THEME_DIR . '/inc/surface-contract.php';
    require_once NCU_THEME_DIR . '/inc/header-footer/bootstrap.php';
    require_once NCU_THEME_DIR . '/inc/block-edition/bootstrap.php';
    require_once NCU_THEME_DIR . '/inc/renderers.php';
    require_once NCU_THEME_DIR . '/inc/performance.php';
    require_once NCU_THEME_DIR . '/inc/enqueue.php';
    require_once NCU_THEME_DIR . '/inc/integrations.php';
    require_once NCU_THEME_DIR . '/inc/starter-content.php';
} else {
    /* Minimal public runtime: WordPress owns rendering; Theme supplies CSS only. */
    require_once NCU_THEME_DIR . '/inc/frontend-safe.php';
    /* Per-post display overrides (title/meta/comments) are honoured on the public site too. */
    require_once NCU_THEME_DIR . '/inc/post-display-settings.php';
    ncu_theme_maybe_load_header_footer_engine();
}
