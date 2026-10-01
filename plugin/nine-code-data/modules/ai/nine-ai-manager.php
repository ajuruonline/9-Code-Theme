<?php
/**
 * Plugin Name: 9 AI Manager
 * Description: Human-controlled AI-to-WordPress operating hub. Freshly scans the site before export, gives AI a machine-readable instruction contract, previews structured AI packages, supports plugin AI work packs and approved plugin replacement with rollback.
 * Version: 2.0.0
 * Author: 9igeria Online Limited
 * Text Domain: nine-ai-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NINE_AI_MANAGER_VERSION', '2.0.0');
define('NINE_AI_MANAGER_FILE', __FILE__);
define('NINE_AI_MANAGER_DIR', plugin_dir_path(__FILE__));
define('NINE_AI_MANAGER_URL', plugin_dir_url(__FILE__));

require_once NINE_AI_MANAGER_DIR . 'includes/class-nine-ai-manager-scanner.php';
require_once NINE_AI_MANAGER_DIR . 'includes/class-nine-ai-manager-manifest.php';
require_once NINE_AI_MANAGER_DIR . 'includes/class-nine-ai-manager-importer.php';
require_once NINE_AI_MANAGER_DIR . 'includes/class-nine-ai-manager-history.php';
require_once NINE_AI_MANAGER_DIR . 'includes/class-nine-ai-manager-plugin-updater.php';
require_once NINE_AI_MANAGER_DIR . 'includes/class-nine-ai-manager-admin.php';
require_once NINE_AI_MANAGER_DIR . 'includes/class-nine-ai-manager.php';

register_activation_hook(__FILE__, array('Nine_AI_Manager', 'activate'));
register_deactivation_hook(__FILE__, array('Nine_AI_Manager', 'deactivate'));

Nine_AI_Manager::instance();
