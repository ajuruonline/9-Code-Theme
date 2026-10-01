<?php
/**
 * Plugin Name: 9Code ACF Data Engine
 * Plugin URI:  https://9igeria.com/
 * Description: Mobile-first data management for ACF, 9 plugin fields, safe WordPress post meta and taxonomies, with Excel/CSV/AI round-trip editing, versions and recovery.
 * Version:     0.16.0
 * Author:      9igeria Online Limited
 * Text Domain: ninecode-acf-data-engine
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NINECODE_ACF_DATA_ENGINE_VERSION', '0.16.0' );
define( 'NINECODE_ACF_DATA_ENGINE_FILE', __FILE__ );
define( 'NINECODE_ACF_DATA_ENGINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'NINECODE_ACF_DATA_ENGINE_URL', plugin_dir_url( __FILE__ ) );

require_once NINECODE_ACF_DATA_ENGINE_DIR . 'includes/class-ninecode-scope-lock.php';
require_once NINECODE_ACF_DATA_ENGINE_DIR . 'includes/class-ninecode-data-exporter.php';
require_once NINECODE_ACF_DATA_ENGINE_DIR . 'includes/class-ninecode-data-importer.php';
require_once NINECODE_ACF_DATA_ENGINE_DIR . 'includes/class-ninecode-excel.php';
require_once NINECODE_ACF_DATA_ENGINE_DIR . 'includes/class-ninecode-data-version-manager.php';
require_once NINECODE_ACF_DATA_ENGINE_DIR . 'includes/class-ninecode-acf-data-engine.php';

register_activation_hook( __FILE__, array( 'NineCode_ACF_Data_Engine', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NineCode_ACF_Data_Engine', 'deactivate' ) );

NineCode_ACF_Data_Engine::instance();
