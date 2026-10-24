<?php
/**
 * Open Scholar Ultra Backup bootstrap.
 *
 * Ported from the strongest existing Nine backup engine: 9 Import, Export &
 * Backup Manager 2.2.0 (Bluehost/shared-host hardened). If its standalone
 * plugin is active, the theme yields to that copy to prevent duplicate jobs.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function osch_backup_external_engine_active() {
    return class_exists( 'Nine_Backup_Manager', false );
}

if ( ! osch_backup_external_engine_active() ) {
    if ( ! defined( 'NINE_BACKUP_VERSION' ) ) { define( 'NINE_BACKUP_VERSION', '2.2.0-osch.2' ); }
    if ( ! defined( 'NINE_BACKUP_FILE' ) ) { define( 'NINE_BACKUP_FILE', __FILE__ ); }
    if ( ! defined( 'NINE_BACKUP_DIR' ) ) { define( 'NINE_BACKUP_DIR', trailingslashit( NCU_CORE_DIR ) . 'ultra-backup/' ); }
    if ( ! defined( 'NINE_BACKUP_URL' ) ) { define( 'NINE_BACKUP_URL', trailingslashit( NCU_CORE_URL ) . 'ultra-backup/' ); }

    require_once NINE_BACKUP_DIR . 'includes/class-nine-backup-health.php';
    require_once NINE_BACKUP_DIR . 'includes/class-nine-backup-zip.php';
    require_once NINE_BACKUP_DIR . 'includes/class-nine-backup-xlsx.php';
    require_once NINE_BACKUP_DIR . 'includes/class-nine-backup-plugins.php';
    require_once NINE_BACKUP_DIR . 'includes/class-nine-backup-google-drive.php';
    require_once NINE_BACKUP_DIR . 'includes/class-nine-backup-exporter.php';
    require_once NINE_BACKUP_DIR . 'includes/class-nine-backup-importer.php';
    require_once NINE_BACKUP_DIR . 'includes/class-nine-backup-manager.php';

    Nine_Backup_Health::register_shutdown_monitor();
    Nine_Backup_Manager::instance();

    add_action( 'after_switch_theme', array( 'Nine_Backup_Manager', 'activate' ) );
    add_action( 'admin_init', 'osch_backup_maybe_activate', 6 );
    function osch_backup_maybe_activate() {
        if ( NINE_BACKUP_VERSION !== (string) get_option( 'nine_backup_version', '' ) ) { Nine_Backup_Manager::activate(); }
    }
}
