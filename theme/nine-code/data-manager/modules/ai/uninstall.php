<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
// Deliberately preserve settings/history unless the site owner removes them manually.
