<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NCU_THEME_VERSION', '4.0.0' );
define( 'NCU_THEME_RELEASE_SHOT', 'Lean plugin host' );
define( 'OPEN_SCHOLAR_THEME_VERSION', NCU_THEME_VERSION );
define( 'NCU_THEME_API_VERSION', 14 );
define( 'NINECODE_SUITE_THEME_VERSION', NCU_THEME_VERSION );
if ( ! defined( 'NINECODE_SUITE_CONTRACT_MAJOR' ) ) { define( 'NINECODE_SUITE_CONTRACT_MAJOR', 14 ); }
define( 'NCU_THEME_DIR', get_template_directory() );
define( 'NCU_THEME_URI', get_template_directory_uri() );

/* Stable host foundation. Business data and application features belong to plugins. */
require_once NCU_THEME_DIR . '/inc/defaults.php';
require_once NCU_THEME_DIR . '/inc/setup.php';
require_once NCU_THEME_DIR . '/inc/migrate-legacy.php';

/*
 * The host module is deliberately small: branding, mobile administration,
 * SEO/social metadata, Jetpack UI restraint and plugin communication.
 */
if ( ! function_exists( 'ncu_core_defaults' ) ) {
    require_once NCU_THEME_DIR . '/core/bootstrap.php';
}

/* One public renderer: native content plus design tokens. Plugins own app shells. */
require_once NCU_THEME_DIR . '/inc/frontend-safe.php';

/* The host launcher includes plugin upload and site preview on mobile. */
require_once NCU_THEME_DIR . '/inc/block-edition/bootstrap.php';
