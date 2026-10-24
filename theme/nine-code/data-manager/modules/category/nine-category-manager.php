<?php
/**
 * Plugin Name: 9 Category Manager
 * Description: Site-structure planning for taxonomies, authors, content types and native relationships, plus a text-only Post of Contents for Gutenberg, Elementor, shortcodes, and front-end editorial workflows.
 * Version: 4.0.2
 * Author: 9igeria Online
 * Text Domain: nine-category-manager
 * Requires at least: 6.3
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'NINECM_VERSION', '4.0.2' );
define( 'NINECM_FILE', __FILE__ );
define( 'NINECM_DIR', trailingslashit( dirname( __FILE__ ) ) );
define( 'NINECM_URL', NINE55_ULTRON_DATA_URL . 'modules/category/' );

require_once NINECM_DIR . 'includes/class-ninecm-infrastructure.php';
require_once NINECM_DIR . 'includes/class-ninecm-drift.php';
require_once NINECM_DIR . 'includes/class-ninecm-renderer.php';
require_once NINECM_DIR . 'includes/class-ninecm-rest.php';
require_once NINECM_DIR . 'includes/class-ninecm-export.php';
require_once NINECM_DIR . 'includes/class-ninecm-health.php';
require_once NINECM_DIR . 'includes/class-ninecm-abilities.php';
require_once NINECM_DIR . 'includes/class-ninecm-admin.php';
require_once NINECM_DIR . 'includes/class-ninecm-core.php';

NineCM_Core::instance();
