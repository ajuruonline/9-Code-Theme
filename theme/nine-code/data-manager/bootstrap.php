<?php
/**
 * Open Scholar Data Manager: the editorial and data workspace bundled with Open Scholar Theme.
 *
 * Until 17.1.0 this shipped as the separate "Nine Code Data" plugin. It now loads from
 * functions.php on every request, so installing or updating the theme installs Data Manager too.
 * Option names, meta keys, post types, taxonomies, REST routes and hooks are unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * A still-active plugin copy (the separate "Nine Code Data" plugin or the older "9 Data Manager")
 * defines these constants. Plugins load before the theme, so that copy keeps running for this
 * request. inc/migrate-legacy.php deactivates it on the next admin request, and this bundled copy
 * takes over from then on.
 */
if ( defined( 'NINE55_ULTRON_DATA_VERSION' ) ) { return; }

define( 'NINE55_ULTRON_DATA_VERSION', '19.0.0' );
define( 'NINE55_ULTRON_DATA_FILE', __FILE__ );
define( 'NINE55_ULTRON_DATA_DIR', trailingslashit( dirname( __FILE__ ) ) );
define( 'NINE55_ULTRON_DATA_URL', trailingslashit( get_template_directory_uri() ) . 'data-manager/' );

require_once NINE55_ULTRON_DATA_DIR . 'includes/bootstrap.php';
