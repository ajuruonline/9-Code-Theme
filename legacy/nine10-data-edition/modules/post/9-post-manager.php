<?php
/**
 * Plugin Name: 9 Post Editor — Native Front-End Workspace
 * Description: WordPress-native mobile post workspace with full-screen front-end editing, lightweight site fields, automatic post/plugin field discovery, 9CF AI workflows, bite-sized post/category backup and restore with optional media, and optional legacy ACF compatibility.
 * Version: 4.0.3
 * Author: Studio 9
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: nine-post-manager
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: false
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'NPM9_VERSION', '4.0.3' );
define( 'NPM9_FILE', __FILE__ );
define( 'NPM9_DIR', plugin_dir_path( __FILE__ ) );
define( 'NPM9_URL', plugin_dir_url( __FILE__ ) );

// Core v4 runtime: WordPress-native and ACF-independent.
require_once NPM9_DIR . 'includes/class-nine-post-manager.php';
require_once NPM9_DIR . 'includes/class-nine-post-manager-fields.php';
require_once NPM9_DIR . 'includes/class-nine-post-manager-ninecf.php';
require_once NPM9_DIR . 'includes/class-nine-post-manager-backup.php';
require_once NPM9_DIR . 'includes/class-nine-post-manager-design.php';

add_action( 'plugins_loaded', static function () {
    Nine_Post_Manager::instance();
    Nine_Post_Manager_Fields::instance();
    Nine_Post_Manager_9CF::instance();
    Nine_Post_Manager_Backup::instance();
    Nine_Post_Manager_Design::instance();

    // The old Instant ACF renderer is compatibility-only and loads only on sites that still use ACF.
    if ( function_exists( 'acf_get_field_groups' ) || class_exists( 'ACF' ) ) {
        require_once NPM9_DIR . 'includes/class-nine-post-manager-renderer.php';
        Nine_Post_Manager_Renderer::instance();
    }
}, 20 );
