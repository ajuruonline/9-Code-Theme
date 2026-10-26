<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Lightweight diagnostics and error log for hosting/runtime problems. */
class Nine_Backup_Health {
    private static $current_operation = '';
    public static function log_dir() {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'nine-backup-manager/logs';
    }

    public static function ensure_log_dir() {
        $dir = self::log_dir();
        if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
        if ( is_dir( $dir ) && ! file_exists( trailingslashit( $dir ) . 'index.html' ) ) {
            @file_put_contents( trailingslashit( $dir ) . 'index.html', '' );
        }
        return $dir;
    }

    public static function log( $context, $message, $data = array() ) {
        $dir = self::ensure_log_dir();
        if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) { return; }
        $file = trailingslashit( $dir ) . 'nine-backup-' . gmdate( 'Y-m-d' ) . '.log';
        $line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] [' . sanitize_key( $context ) . '] ' . wp_strip_all_tags( (string) $message );
        if ( $data ) {
            $encoded = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            if ( $encoded ) { $line .= ' ' . $encoded; }
        }
        @file_put_contents( $file, $line . PHP_EOL, FILE_APPEND | LOCK_EX );
    }

    public static function latest_log() {
        $dir = self::ensure_log_dir();
        $files = glob( trailingslashit( $dir ) . '*.log' ) ?: array();
        if ( ! $files ) { return ''; }
        usort( $files, function( $a, $b ) { return filemtime( $b ) <=> filemtime( $a ); } );
        return $files[0];
    }

    public static function register_shutdown_monitor() {
        register_shutdown_function( function() {
            if ( ! self::$current_operation ) { return; }
            $error = error_get_last();
            $fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
            if ( $error && in_array( $error['type'], $fatal_types, true ) ) {
                self::log( 'fatal_shutdown', $error['message'] ?? 'Fatal PHP shutdown', array( 'operation'=>self::$current_operation, 'file'=>$error['file'] ?? '', 'line'=>$error['line'] ?? 0 ) );
                update_option( 'nine_backup_last_operation', array(
                    'operation'=>self::$current_operation,
                    'status'=>'fatal',
                    'time'=>time(),
                    'message'=>$error['message'] ?? 'Fatal PHP shutdown',
                ), false );
            }
        } );
    }

    public static function operation_start( $operation, array $context = array() ) {
        self::$current_operation = sanitize_key( $operation );
        update_option( 'nine_backup_last_operation', array(
            'operation'=>self::$current_operation,
            'status'=>'running',
            'time'=>time(),
            'context'=>$context,
        ), false );
    }

    public static function operation_finish( $operation = '' ) {
        $op = $operation ? sanitize_key($operation) : self::$current_operation;
        update_option( 'nine_backup_last_operation', array(
            'operation'=>$op,
            'status'=>'completed',
            'time'=>time(),
        ), false );
        self::$current_operation = '';
    }

    public static function operation_fail( $message = '' ) {
        $op = self::$current_operation ?: 'operation';
        update_option( 'nine_backup_last_operation', array(
            'operation'=>$op,
            'status'=>'error',
            'time'=>time(),
            'message'=>wp_strip_all_tags((string)$message),
        ), false );
        self::$current_operation = '';
    }

    public static function last_operation() {
        $op = get_option( 'nine_backup_last_operation', array() );
        return is_array($op) ? $op : array();
    }

    public static function diagnostics() {
        $upload = wp_upload_dir();
        $base = trailingslashit( $upload['basedir'] ) . 'nine-backup-manager';
        $archive = $base . '/archives';
        $tmp = $base . '/tmp';
        foreach ( array( $base, $archive, $tmp, self::log_dir() ) as $dir ) { if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); } }

        $rows = array();
        $rows[] = self::row( 'PHP version', version_compare( PHP_VERSION, '7.4', '>=' ), PHP_VERSION, 'PHP 7.4 or newer is required.' );
        $wp_version = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '';
        $rows[] = self::row( 'WordPress version', ! $wp_version || version_compare( $wp_version, '6.2', '>=' ), $wp_version ?: 'Unknown', 'WordPress 6.2 or newer is recommended.' );
        $rows[] = self::row( 'Uploads directory', empty( $upload['error'] ) && is_dir( $upload['basedir'] ), empty( $upload['error'] ) ? $upload['basedir'] : $upload['error'], 'WordPress must have a usable uploads directory.' );
        $rows[] = self::row( 'Backup workspace writable', is_dir( $base ) && is_writable( $base ), $base, 'PHP must be able to write the protected Ultra Backup workspace.' );
        $rows[] = self::row( 'Archive folder writable', is_dir( $archive ) && is_writable( $archive ), $archive, 'Required to create local backup packages.' );
        $rows[] = self::row( 'Temporary folder writable', is_dir( $tmp ) && is_writable( $tmp ), $tmp, 'Required for export/import staging.' );
        $rows[] = self::row( 'Plugin directory readable', defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR ) && is_readable( WP_PLUGIN_DIR ), defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : 'Not defined', 'Required to back up plugin files.' );
        $plugin_write = defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR ) && is_writable( WP_PLUGIN_DIR );
        $rows[] = self::row( 'Plugin directory writable', $plugin_write, $plugin_write ? 'Direct plugin import supported' : 'Read-only or filesystem credentials may be required', 'Only required when installing/updating plugin code through this manager.', true );

        $ziparchive = class_exists( 'ZipArchive' );
        $pcl = defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/class-pclzip.php' );
        $rows[] = self::row( 'ZIP creation engine', $ziparchive || function_exists( 'hash_file' ), $ziparchive ? 'ZipArchive' : 'Nine streaming ZIP fallback', 'A ZIP creation engine is required.' );
        $rows[] = self::row( 'ZIP extraction engine', $ziparchive || $pcl, $ziparchive ? 'ZipArchive' : ( $pcl ? 'WordPress PclZip' : 'Unavailable' ), 'A ZIP extraction engine is required for imports.' );

        $free = @disk_free_space( $upload['basedir'] );
        $free_ok = false === $free || $free >= 100 * 1024 * 1024;
        $rows[] = self::row( 'Free disk space', $free_ok, false === $free ? 'Unknown' : size_format( $free ), 'At least 100 MB free is recommended; large backups may require much more.', true );
        $rows[] = self::row( 'PHP memory limit', true, ini_get( 'memory_limit' ) ?: 'Unknown', 'Large complete-site exports may need 256 MB or more.', true );
        $rows[] = self::row( 'PHP max execution time', true, ( ini_get( 'max_execution_time' ) ?: '0' ) . ' seconds', 'The plugin requests unlimited time for manual export/import, but some hosts enforce a hard limit.', true );
        $rows[] = self::row( 'Upload max filesize', true, ini_get( 'upload_max_filesize' ) ?: 'Unknown', 'Large imports can use the cPanel/server-path importer instead.', true );
        $rows[] = self::row( 'POST max size', true, ini_get( 'post_max_size' ) ?: 'Unknown', 'Must exceed the uploaded ZIP size for browser imports.', true );
        $rows[] = self::row( 'WP-Cron', ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ), ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'Disabled' : 'Available', 'Scheduled profiles need WordPress Cron or a real server cron.', true );
        $tmp_dir = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
        $rows[] = self::row( 'PHP temporary directory', $tmp_dir && is_dir($tmp_dir) && is_writable($tmp_dir), $tmp_dir ?: 'Unknown', 'Browser uploads and some WordPress filesystem operations need a writable temporary directory.', true );
        $rows[] = self::row( 'PHP max input time', true, ( ini_get('max_input_time') ?: '0' ) . ' seconds', 'Very large browser uploads can be terminated while PHP is still receiving the request.', true );
        $rows[] = self::row( 'WordPress calculated upload limit', true, function_exists('wp_max_upload_size') ? size_format(wp_max_upload_size()) : 'Unknown', 'Use cPanel/server-path import when a package exceeds this limit.', true );
        $rows[] = self::row( 'WordPress file modifications', ! ( defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS ), ( defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS ) ? 'DISALLOW_FILE_MODS=true' : 'Allowed', 'Plugin code import cannot work when WordPress file modifications are disabled.', true );
        $rows[] = self::row( 'Filesystem method', true, defined('FS_METHOD') ? FS_METHOD : 'Auto-detect', 'Direct plugin replacement works best when WordPress can use its direct filesystem method.', true );
        $safe_settings = get_option('nine_backup_settings', array());
        $safe_mode = ! array_key_exists('shared_host_safe_mode',$safe_settings) || !empty($safe_settings['shared_host_safe_mode']);
        $rows[] = self::row( 'Shared-hosting safe mode', $safe_mode, $safe_mode ? 'Enabled' : 'Disabled', 'Keep this enabled on Bluehost shared hosting so large backups are split into short requests.', true );
        $last = self::last_operation();
        if ( $last ) {
            $status = (string)($last['status'] ?? 'unknown');
            $age = max(0,time()-(int)($last['time'] ?? time()));
            $stale_running = 'running' === $status && $age > 90;
            $value = ($last['operation'] ?? 'operation') . ' · ' . $status . ' · ' . $age . 's ago';
            $problem = $stale_running || in_array($status,array('fatal','error'),true);
            $help = $stale_running ? 'The host probably terminated the previous request before PHP could finish it.' : ( in_array($status,array('fatal','error'),true) ? (string)($last['message'] ?? 'The previous operation ended with an error.') : 'Useful when diagnosing server-side termination.' );
            $rows[] = self::row( 'Last Backup Manager operation', ! $problem, $value, $help, true );
        }
        return $rows;
    }

    private static function row( $name, $pass, $value, $help = '', $warning_only = false ) {
        return array( 'name'=>$name, 'pass'=>(bool)$pass, 'value'=>$value, 'help'=>$help, 'warning_only'=>(bool)$warning_only );
    }

    public static function hard_failures() {
        $failures = array();
        foreach ( self::diagnostics() as $row ) {
            if ( empty( $row['pass'] ) && empty( $row['warning_only'] ) ) { $failures[] = $row; }
        }
        return $failures;
    }
}
