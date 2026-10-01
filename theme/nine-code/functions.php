<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NCU_THEME_VERSION', '15.0.2' );
define( 'NCU_THEME_RELEASE_SHOT', 'Version 15 Editor Overlay Removal' );
define( 'NCU_THEME_API_VERSION', 14 );
define( 'NINECODE_SUITE_THEME_VERSION', '15.0.2' );
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

/* Safe on every request: defaults, native WordPress theme support and helpers. */
require_once NCU_THEME_DIR . '/inc/defaults.php';
require_once NCU_THEME_DIR . '/inc/setup.php';

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
    require_once NCU_THEME_DIR . '/inc/admin.php';
} else {
    /* Minimal public runtime: WordPress owns rendering; Theme supplies CSS only. */
    require_once NCU_THEME_DIR . '/inc/frontend-safe.php';
}
