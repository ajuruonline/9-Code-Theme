<?php
/**
 * Open Scholar Ultra Backup bootstrap.
 *
 * Ported from the strongest existing Nine backup engine: 9 Import, Export &
 * Backup Manager 2.2.0 (Bluehost/shared-host hardened). If its standalone
 * plugin is active, the theme yields to that copy to prevent duplicate jobs.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ncu_osch_backup_external_engine_active() {
    return class_exists( 'Nine_Backup_Manager', false );
}

if ( ! ncu_osch_backup_external_engine_active() ) {
    if ( ! defined( 'NINE_BACKUP_VERSION' ) ) { define( 'NINE_BACKUP_VERSION', '2.2.2-osch.5' ); }
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
    add_action( 'admin_init', 'ncu_osch_backup_maybe_activate', 6 );
    function ncu_osch_backup_maybe_activate() {
        if ( NINE_BACKUP_VERSION !== (string) get_option( 'nine_backup_version', '' ) ) { Nine_Backup_Manager::activate(); }
    }
}

// An independently installed backup plugin loads before the theme and can own
// the Nine_Backup_Manager class. Do not silently claim the bundled update runs.
if ( ncu_osch_backup_external_engine_active() ) {
    add_action( 'admin_notices', function () {
        if ( ! current_user_can('manage_options') ) { return; }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( ! $screen || (false===strpos((string)$screen->id,'nine-backup') && false===strpos((string)$screen->id,'nine-code-ultra')) ) { return; }
        echo '<div class="notice notice-warning"><p><strong>Open Scholar Data & Recovery:</strong> A separate backup plugin owns the backup engine, so the bundled theme version is not running. To use this theme update, first keep a copy of your existing backups, then deactivate (do not delete) the older standalone backup manager in <a href="'.esc_url(admin_url('plugins.php')).'">Plugins</a>. Its stored options and archives are not intentionally removed by deactivation.</p></div>';
    } );
}
