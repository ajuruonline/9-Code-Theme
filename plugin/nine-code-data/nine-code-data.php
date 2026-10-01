<?php
/**
 * Plugin Name: Nine Code Data
 * Plugin URI: https://9igeria.online/
 * Description: Nine Code editorial and data workspace: Post Editor, Category Manager, Post Creator, Form Manager and scoped Data Backup. Keeps your data, forms and shortcodes working even if the theme changes.
 * Version: 10.0.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: 9igeria Online Ltd
 * Author URI: https://9igeria.online/
 * License: GPL-2.0-or-later
 * Text Domain: nine-code-data
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * Upgrade safety: the previous "9 Data Manager" plugin (folder nine10-data-edition)
 * declares the same classes/functions, and WordPress loads plugins in
 * alphabetical order, so this plugin would load first. If the old plugin is
 * active (checked from the active-plugins list, which works in either load
 * order), do not load; retire the old plugin on the next admin request and take
 * over after that. No data is touched either way.
 */
$nine_code_data_legacy = 'nine10-data-edition/nine55-ultron-data.php';
$nine_code_data_legacy_active = defined( 'NINE55_ULTRON_DATA_VERSION' )
    || in_array( $nine_code_data_legacy, (array) get_option( 'active_plugins', array() ), true )
    || ( is_multisite() && isset( get_site_option( 'active_sitewide_plugins', array() )[ $nine_code_data_legacy ] ) );
if ( $nine_code_data_legacy_active ) {
    add_action( 'admin_init', function () use ( $nine_code_data_legacy ) {
        if ( ! current_user_can( 'activate_plugins' ) ) { return; }
        if ( ! function_exists( 'deactivate_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        if ( is_plugin_active( $nine_code_data_legacy ) ) {
            deactivate_plugins( $nine_code_data_legacy, true );
            set_transient( 'ncu_migrated_data_notice', 1, DAY_IN_SECONDS );
            wp_safe_redirect( remove_query_arg( 'nc_retire' ) );
            exit;
        }
    }, 1 );
    unset( $nine_code_data_legacy, $nine_code_data_legacy_active );
    return;
}
unset( $nine_code_data_legacy, $nine_code_data_legacy_active );

define( 'NINE55_ULTRON_DATA_VERSION', '10.0.0' );
define( 'NINE55_ULTRON_DATA_FILE', __FILE__ );
define( 'NINE55_ULTRON_DATA_DIR', plugin_dir_path( __FILE__ ) );
define( 'NINE55_ULTRON_DATA_URL', plugin_dir_url( __FILE__ ) );

require_once NINE55_ULTRON_DATA_DIR . 'includes/bootstrap.php';
