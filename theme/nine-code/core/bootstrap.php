<?php
/** Open Scholar host infrastructure. Application features belong to plugins. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'NCU_CORE_VERSION', NCU_THEME_VERSION );
define( 'NCU_CORE_RELEASE_SHOT', 'Lean plugin host' );
define( 'NCU_CORE_API_VERSION', 14 );
define( 'NINECODE_SUITE_CORE_VERSION', NCU_THEME_VERSION );
if ( ! defined( 'NINECODE_SUITE_CONTRACT_MAJOR' ) ) { define( 'NINECODE_SUITE_CONTRACT_MAJOR', 14 ); }
define( 'NCU_CORE_FILE', __FILE__ );
define( 'NCU_CORE_DIR', trailingslashit( NCU_THEME_DIR ) . 'core/' );
define( 'NCU_CORE_URL', trailingslashit( NCU_THEME_URI ) . 'core/' );

function ncu_core_theme_api_compatible( $theme_api ) {
    $theme_api = (int) $theme_api;
    return $theme_api >= 2 && $theme_api <= (int) NCU_CORE_API_VERSION;
}

/** True only when a standalone Data Manager plugin has loaded itself. */
function ncu_core_external_data_edition_active() {
    return defined( 'NINE55_ULTRON_DATA_VERSION' );
}

require_once NCU_CORE_DIR . 'inc/defaults.php';
require_once NCU_CORE_DIR . 'inc/settings.php';
require_once NCU_CORE_DIR . 'inc/branding.php';
require_once NCU_CORE_DIR . 'inc/open-scholar.php';
require_once NCU_CORE_DIR . 'inc/app-workspace.php';
require_once NCU_CORE_DIR . 'inc/notices.php';
require_once NCU_CORE_DIR . 'inc/integration-contracts.php';
require_once NCU_CORE_DIR . 'inc/jetpack-hygiene.php';
require_once NCU_CORE_DIR . 'inc/seo.php';
require_once NCU_CORE_DIR . 'inc/ultra-backup.php';

/** Seed host defaults without claiming or altering plugin-owned data. */
function ncu_core_activate() {
    if ( false === get_option( 'ncu_settings', false ) ) {
        add_option( 'ncu_settings', ncu_core_defaults(), '', false );
    }
    update_option( 'ncu_core_version', NCU_CORE_VERSION, false );
}
add_action( 'after_switch_theme', 'ncu_core_activate' );
add_action( 'admin_init', 'ncu_core_maybe_upgrade', 5 );

function ncu_core_maybe_upgrade() {
    $installed = (string) get_option( 'ncu_core_version', '0' );
    if ( version_compare( $installed, NCU_CORE_VERSION, '>=' ) ) { return; }

    /* Preserve recognized host settings, force retired theme chrome off, and
     * leave every plugin option, post type, field and table untouched. */
    $saved = get_option( 'ncu_settings', array() );
    $saved = is_array( $saved ) ? $saved : array();
    $saved['header_enabled'] = 0;
    $saved['footer_enabled'] = 0;
    $saved['frontend_edit_enabled'] = 0;
    $saved['doctor_frontend_enabled'] = 0;
    $saved['hide_wp_dashboard_news'] = 0;
    update_option( 'ncu_settings', ncu_recursive_parse_args( $saved, ncu_core_defaults() ), false );
    update_option( 'ncu_core_version', NCU_CORE_VERSION, false );
}

/** Compatibility helper: plugins provide the actual editor route. */
function ncu_core_data_manager_url() {
    return admin_url( ncu_core_external_data_edition_active() ? 'admin.php?page=nine-post-manager' : 'admin.php?page=nine-code-ultra' );
}
