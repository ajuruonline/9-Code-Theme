<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nine_Backup_Manager {
    private static $instance;
    const CAP = 'manage_options';
    const CRON_HOOK = 'nine_backup_run_scheduled_profiles';

    public static function instance() {
        if ( ! self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'wp_ajax_nine_backup_search', array( $this, 'ajax_search' ) );
        add_action( 'wp_ajax_nine_backup_safe_step', array( $this, 'ajax_safe_step' ) );
        add_action( 'wp_ajax_nine_backup_probe', array( $this, 'ajax_probe' ) );
        add_action( 'admin_post_nine_backup_safe_step_page', array( $this, 'handle_safe_step_manual' ) );
        add_action( 'admin_post_nine_backup_safe_step_manual', array( $this, 'handle_safe_step_manual' ) );
        add_action( 'admin_post_nine_backup_export', array( $this, 'handle_export' ) );
        add_action( 'admin_post_nine_backup_quick_export', array( $this, 'handle_quick_export' ) );
        add_action( 'admin_post_nine_backup_quick_backup', array( $this, 'handle_quick_backup' ) );
        add_action( 'admin_post_nine_backup_drive_upload_latest', array( $this, 'handle_drive_upload_latest' ) );
        add_action( 'admin_post_nine_backup_upload_import', array( $this, 'handle_upload_import' ) );
        add_action( 'admin_post_nine_backup_server_import', array( $this, 'handle_server_import' ) );
        add_action( 'admin_post_nine_backup_run_import', array( $this, 'handle_run_import' ) );
        add_action( 'admin_post_nine_backup_download_archive', array( $this, 'download_archive' ) );
        add_action( 'admin_post_nine_backup_delete_archive', array( $this, 'delete_archive' ) );
        add_action( 'admin_post_nine_backup_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_nine_backup_google_drive_connect', array( $this, 'google_drive_connect' ) );
        add_action( 'admin_post_nine_backup_google_drive_callback', array( $this, 'google_drive_callback' ) );
        add_action( 'admin_post_nine_backup_google_drive_disconnect', array( $this, 'google_drive_disconnect' ) );
        add_action( 'admin_post_nine_backup_host_selftest', array( $this, 'handle_host_selftest' ) );
        add_action( 'admin_post_nine_backup_delete_profile', array( $this, 'delete_profile' ) );
        add_action( 'admin_post_nine_backup_run_profile', array( $this, 'run_profile_now' ) );
        add_action( self::CRON_HOOK, array( $this, 'run_scheduled_profiles' ) );
        add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
    }

    public static function activate() {
        Nine_Backup_Exporter::ensure_dirs();
        Nine_Backup_Health::ensure_log_dir();
        update_option( 'nine_backup_version', NINE_BACKUP_VERSION, false );
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) { wp_unschedule_event( $timestamp, self::CRON_HOOK ); }
    }

    public function cron_schedules( $schedules ) { return $schedules; }

    public function admin_menu() {
        // Keep the existing route/slug for backwards compatibility.
        add_submenu_page(
            'nine-code-ultra',
            'Open Scholar Data & Recovery',
            'Data & Recovery',
            self::CAP,
            'nine-backup-manager',
            array( $this, 'render_page' )
        );
        // Distinct app entries, with different symbols, for a dashboard-first institution.
        add_menu_page('Open Scholar Export & Import', 'Export & Import', self::CAP,
            'osch-export-import', array($this,'render_page'), 'dashicons-migrate', 57.11);
        add_menu_page('Open Scholar Backup & Restore', 'Backup & Restore', self::CAP,
            'osch-backup-restore', array($this,'render_page'), 'dashicons-backup', 57.12);
    }

    public function enqueue( $hook ) {
        if ( false === strpos( $hook, 'nine-backup-manager' ) && false === strpos( $hook, 'osch-export-import' ) && false === strpos( $hook, 'osch-backup-restore' ) ) { return; }
        wp_enqueue_style( 'nine-backup-admin', NINE_BACKUP_URL . 'assets/admin.css', array(), NINE_BACKUP_VERSION );
        wp_enqueue_script( 'nine-backup-admin', NINE_BACKUP_URL . 'assets/admin.js', array( 'jquery' ), NINE_BACKUP_VERSION, true );
        wp_localize_script( 'nine-backup-admin', 'NineBackup', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'nine_backup_search' ),
            'safeNonce' => wp_create_nonce( 'nine_backup_safe_step' ),
        ) );
    }

    private function guard() {
        if ( ! current_user_can( self::CAP ) ) { wp_die( 'You do not have permission to use Open Scholar Ultra Backup.' ); }
    }

    public function render_page() {
        $this->guard();
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $default_tab = 'osch-export-import' === $page ? 'transfer' : ('osch-backup-restore' === $page ? 'local' : 'dashboard');
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash($_GET['tab']) ) : $default_tab;
        $allowed = array( 'dashboard','transfer','local','google','export','import','archives','profiles','settings','diagnostics' );
        if ( ! in_array( $tab, $allowed, true ) ) { $tab = 'dashboard'; }
        ?>
        <div class="wrap nine-backup-wrap ncu-admin" data-ninecode-settings-root>
            <div class="nine-backup-titlebar">
                <div><h1>Open Scholar Data & Recovery</h1><p>Independent workflows: direct Export & Import, local WordPress backups, and off-site Google Drive backup.</p></div>
                <div class="nine-badge">Open Scholar</div>
            </div>
            <?php $this->notices(); ?>
            <nav class="nine-recovery-primary" aria-label="Open Scholar's three data recovery workflows">
                <?php foreach ( array( 'transfer'=>'Export & Import', 'local'=>'Server Backup', 'google'=>'Google Drive Backup' ) as $key=>$label ) : ?>
                    <a class="nine-recovery-option <?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page='.('transfer' === $key ? 'osch-export-import' : 'osch-backup-restore').('google' === $key ? '&tab=google' : ''))); ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <details class="nine-recovery-secondary" <?php echo in_array($tab,array('dashboard','transfer','local','google'),true) ? '' : 'open'; ?>>
                <summary>Advanced tools, previous downloads and diagnostics</summary>
                <nav class="nav-tab-wrapper" aria-label="More data recovery tools">
                    <?php foreach ( array( 'dashboard'=>'Overview','export'=>'Advanced Export','import'=>'Import Package','archives'=>'Downloads','profiles'=>'Schedules','settings'=>'Settings','diagnostics'=>'Diagnostics' ) as $key=>$label ) : ?>
                        <a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=nine-backup-manager&tab='.$key)); ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </nav>
            </details>
            <div class="nine-panel">
                <?php
                switch ( $tab ) {
                    case 'transfer': $this->render_transfer_hub(); break;
                    case 'local': $this->render_local_hub(); break;
                    case 'google': $this->render_drive_hub(); break;
                    case 'export': $this->render_export(); break;
                    case 'import': $this->render_import(); break;
                    case 'archives': $this->render_archives(); break;
                    case 'profiles': $this->render_profiles(); break;
                    case 'settings': $this->render_settings(); break;
                    case 'diagnostics': $this->render_diagnostics(); break;
                    default: $this->render_dashboard();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function notices() {
        if ( empty( $_GET['nine_notice'] ) ) { return; }
        $code = sanitize_key( $_GET['nine_notice'] );
        $messages = array(
            'exported' => array( 'success', 'Server backup package created. Check archive contents and download an off-site copy.' ),
            'imported' => array( 'success', 'Import completed. Review the import log below.' ),
            'deleted' => array( 'success', 'Local archive deleted. Google Drive copy is preserved.' ),
            'drive_uploaded' => array( 'success', 'Latest local archive uploaded to Google Drive.' ),
            'deleted_remote_pending' => array( 'warning', 'Local archive deleted. Its Google Drive copy could not be removed; reconnect Drive and remove it there if required.' ),
            'settings' => array( 'success', 'Settings saved.' ),
            'drive_connected' => array( 'success', 'Google Drive is connected. New backups will be uploaded automatically.' ),
            'drive_disconnected' => array( 'success', 'Google Drive has been disconnected. Local backups remain untouched.' ),
            'profile_deleted' => array( 'success', 'Scheduled profile deleted.' ),
            'profile_run' => array( 'success', 'Backup profile completed.' ),
            'plugins_staged' => array( 'success', 'Plugin packages were installed/updated. WordPress has reloaded; continue to import settings and records.' ),
            'safe_manual_ok' => array( 'info', 'Backup part processed. Continue manually or check your completed packages below.' ),
            'safe_manual_fail' => array( 'warning', 'This backup part failed; review Diagnostics and retry. Existing completed packages remain available.' ),
            'safe_started' => array( 'success', 'Shared-hosting safe backup started. Keep this page open while the small backup parts are processed.' ),
            'error' => array( 'error', isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : 'The operation could not be completed.' ),
        );
        if ( isset( $messages[ $code ] ) ) {
            echo '<div class="notice notice-' . esc_attr( $messages[$code][0] ) . ' is-dismissible"><p>' . esc_html( $messages[$code][1] ) . '</p></div>';
        }
    }

    private function render_dashboard() {
        $archives = $this->archive_files();
        $profiles = get_option( 'nine_backup_profiles', array() );
        $settings = get_option( 'nine_backup_settings', array() );
        $acf = function_exists( 'acf_get_field_groups' );
        $elementor = did_action( 'elementor/loaded' ) || post_type_exists( 'elementor_library' );
        $plugins = Nine_Backup_Plugins::installed_plugins();
        $health_failures = Nine_Backup_Health::hard_failures();
        ?>
        <?php if ( $health_failures ) : ?>
            <div class="notice notice-error inline"><p><strong>Backup Manager needs attention before a backup can run.</strong> Open the Diagnostics tab for the exact hosting/filesystem problem.</p></div>
        <?php endif; ?>
        <div class="nine-grid four">
            <div class="nine-card"><strong><?php echo count( $archives ); ?></strong><span>Local backup packages</span></div>
            <div class="nine-card"><strong><?php echo count( $profiles ); ?></strong><span>Reusable/scheduled profiles</span></div>
            <div class="nine-card"><strong><?php echo ! empty( $settings['drive_sync_path'] ) ? 'Ready' : 'Optional'; ?></strong><span>Google Drive sync-folder mode</span></div>
            <div class="nine-card"><strong><?php echo count($plugins); ?></strong><span>Installed plugins available for transfer</span></div>
        </div>
        <h2>What Ultra Backup is for</h2>
        <p>This theme service creates <strong>selective, named, portable records packages</strong>. You can preserve one page, one person’s records, one course, one category tree, one ACF structure, one Elementor template set, a custom plugin table, or a larger site snapshot without restoring an entire WordPress installation.</p>
        <div class="nine-grid two">
            <div class="nine-box"><h3>Machine-restorable package</h3><p>JSON data, relationship metadata, optional media files, Elementor JSON snapshots and a manifest designed for another Open Scholar installation.</p></div>
            <div class="nine-box"><h3>Human-readable companion</h3><p>Each data section also gets XLSX and CSV reports for Excel, searching, sorting, AI analysis, audit requests and handoff.</p></div>
            <div class="nine-box"><h3>Narrow or broad</h3><p>Use the record finder for exact items, or bulk-select posts, users, terms, media, Elementor templates, ACF field groups and menus.</p></div>
            <div class="nine-box"><h3>Restore safety</h3><p>Imports are previewed first and support update, skip or duplicate conflict handling. Plugin replacements receive rollback ZIPs and staged reloads.</p></div>
            <div class="nine-box"><h3>Plugin portability</h3><p>Move plugin code, settings, detected plugin tables, declared dependencies and detectable plugin-owned CPT/taxonomy records independently or together.</p></div>
            <div class="nine-box"><h3>Context remapping</h3><p>Source-domain URLs and source database prefixes are remapped for the destination site during import.</p></div>
        </div>
        <h2>Environment</h2>
        <table class="widefat striped"><tbody>
            <tr><td>WordPress</td><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
            <tr><td>ZIP engine</td><td><?php echo class_exists( 'ZipArchive' ) ? 'ZipArchive available' : 'Compatibility fallback active'; ?></td></tr>
            <tr><td>Elementor</td><td><?php echo $elementor ? 'Detected' : 'Not detected'; ?></td></tr>
            <tr><td>ACF</td><td><?php echo $acf ? 'Detected' : 'Not detected'; ?></td></tr>
        </tbody></table>
        <?php
    }


    /** Human-oriented transfer workflow; independent of disaster recovery. */
    private function render_transfer_hub() {
        $choices = array(
            'all_portable' => array('All Export — complete portable site', 'Records, users, media, plugin packages, detected settings and data. Use for migration.'),
            'all_records'  => array('All Records — posts, users and more', 'Every supported WordPress record, including media and relationships.'),
            'posts'        => array('Export Posts', 'Posts, pages and registered custom post types.'),
            'users'        => array('Export Users', 'User accounts, profiles and roles (never passwords or active sessions).'),
            'media'        => array('Export Media', 'Media records and their original files.'),
            'terms'        => array('Export Categories & Tags', 'Taxonomies, terms and hierarchies.'),
            'menus'        => array('Export Menus', 'Navigation menus and menu relationships.'),
            'site_settings'=> array('Export Site Settings', 'Portable site and theme settings, without secrets by default.'),
        );
        ?>
        <h2>Export &amp; Import — your records, portable</h2>
        <p><strong>Export</strong> downloads a portable ZIP to your device; it is not an ongoing backup. <strong>Import</strong> uploads a compatible ZIP, verifies its contents and previews conflicts before importing. These operations are independent of the server/Google Drive recovery backups.</p>
        <div class="nine-transfer-grid">
        <?php foreach ($choices as $kind=>$item) : ?>
          <div class="nine-box nine-transfer-card">
            <h3><?php echo esc_html($item[0]); ?></h3>
            <p><?php echo esc_html($item[1]); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="nine_backup_quick_export">
              <input type="hidden" name="export_kind" value="<?php echo esc_attr($kind); ?>">
              <?php wp_nonce_field('nine_backup_quick_export'); ?>
              <button class="button button-primary" type="submit">Download ZIP</button>
            </form>
          </div>
        <?php endforeach; ?>
        </div>
        <div class="nine-box nine-import-callout">
          <h3>All Import — upload and preview records</h3>
          <p>Import packages exported here, either to the same installation or to another Open Scholar/WordPress installation.</p>
          <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="nine_backup_upload_import">
            <?php wp_nonce_field('nine_backup_upload_import'); ?>
            <input type="file" name="package" accept=".zip,application/zip" required>
            <button class="button button-primary" type="submit">Upload &amp; Preview</button>
          </form>
          <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-backup-manager&tab=import')); ?>">Conflict handling &amp; import history</a>
          <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-backup-manager&tab=export')); ?>">Advanced selective export</a></p>
        </div>
        <p class="description"><strong>Privacy:</strong> Exports need administrator permission and a CSRF check. Passwords, password hashes, API keys and tokens are never exported. ZIPs are not encrypted and may contain private user information. Transfer only through trusted storage. Large sites may need the Advanced Export safe-mode workflow instead of one single download.</p>
        <p class="description"><strong>WordPress interoperability:</strong> Standard WordPress XML is separately available through <a href="<?php echo esc_url(admin_url('export.php')); ?>">Tools → Export</a> and <a href="<?php echo esc_url(admin_url('import.php')); ?>">Tools → Import</a>. The ZIP importer here accepts this engine’s ZIP format, not arbitrary third-party ZIPs.</p>
        <?php
    }

    /** Database/site snapshots are a separate recovery product, not portable record export. */
    private function render_local_hub() {
        ?>
        <h2>Backup &amp; Restore — recovery copies</h2>
        <p>Backups are stored in a protected server directory. A <strong>database/site snapshot</strong> contains SQL and <code>wp-content</code>; it is intended for disaster recovery, not the record importer above. WordPress core and server configuration may require separate restoration.</p>
        <div class="nine-grid two">
          <div class="nine-box"><h3>Backup on this server</h3><p>Save a database SQL + <code>wp-content</code> recovery ZIP to the server.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="nine_backup_quick_backup"><input type="hidden" name="backup_target" value="server"><input type="hidden" name="backup_type" value="snapshot">
              <?php wp_nonce_field('nine_backup_quick_backup'); ?>
              <button type="submit" class="button button-primary">Create server backup</button>
            </form>
          </div>
          <div class="nine-box"><h3>Complete portable copy on server</h3><p>Save transferable records and plugin bundles for a less destructive migration or staged restore.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="nine_backup_quick_backup"><input type="hidden" name="backup_target" value="server"><input type="hidden" name="backup_type" value="portable">
              <?php wp_nonce_field('nine_backup_quick_backup'); ?>
              <button type="submit" class="button">Create portable server copy</button>
            </form>
          </div>
        </div>
        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-backup-manager&tab=archives')); ?>">Stored backups &amp; downloads</a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-backup-manager&tab=profiles')); ?>">Automatic schedules</a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-backup-manager&tab=diagnostics')); ?>">Run host diagnostics</a></p>
        <p class="description"><strong>Restore path:</strong> Portable records go through Import after preview. SQL + <code>wp-content</code> snapshots must be recovered by a qualified administrator using server/database restoration procedures; this theme does not silently overwrite a live database.</p>
        <?php
    }

    private function render_drive_hub() {
        $connected = class_exists('Nine_Backup_Google_Drive') && Nine_Backup_Google_Drive::connected();
        $settings = get_option('nine_backup_settings',array());
        $files = $this->archive_files();
        ?>
        <h2>Google Drive Backup — off-site recovery</h2>
        <p><strong>Google Drive:</strong> <?php echo $connected ? 'Connected' : 'Not connected'; ?>.
        <?php echo !empty($settings['google_drive_enabled']) ? 'Automatic upload of completed backups is enabled.' : 'Automatic upload is currently off.'; ?></p>
        <p>Use a separate off-site copy in the institution’s own Google Drive, not a temporary export. Uploaded backups stay on the local server until removed under retention settings.</p>
        <?php if ($connected) : ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="nine_backup_quick_backup"><input type="hidden" name="backup_target" value="drive"><input type="hidden" name="backup_type" value="snapshot">
            <?php wp_nonce_field('nine_backup_quick_backup'); ?>
            <button class="button button-primary" type="submit">Create backup on Google Drive</button>
          </form>
          <?php if ($files) : ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="nine_backup_drive_upload_latest">
            <?php wp_nonce_field('nine_backup_drive_upload_latest'); ?>
            <button type="submit" class="button">Upload latest local backup to Drive</button>
          </form>
          <?php endif; ?>
        <?php else : ?><p>Connect Google Drive in Settings before creating an off-site backup. The system will not claim an upload has succeeded without an actual Google Drive response.</p><?php endif; ?>
        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-backup-manager&tab=settings')); ?>">Connect Google Drive / configure OAuth</a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-backup-manager&tab=profiles')); ?>">Backup schedules</a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-backup-manager&tab=archives')); ?>">Local and Drive archive status</a></p>
        <p class="description">Connection requires Google OAuth with <code>drive.file</code> permission; retain private sharing. An off-site backup must be tested with a separate recovery installation.</p>
        <?php
    }

    private function stream_protected_zip( $path, $remove_after_download = false ) {
        $real = realpath($path);
        $dir = realpath(Nine_Backup_Exporter::archive_dir());
        if ( !$dir || !$real || 0!==strpos($real,trailingslashit($dir)) || !is_file($real) || 'zip'!==strtolower(pathinfo($real,PATHINFO_EXTENSION)) ) {
            wp_die('Export package does not exist or is outside the protected archive directory.');
        }
        while (ob_get_level()) { ob_end_clean(); }
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.str_replace('"','',basename($real)).'"');
        header('Content-Length: '.filesize($real));
        if ( $remove_after_download ) {
            // Remove confidential transfer archives even if a browser disconnects.
            register_shutdown_function(function () use ($real) { if (is_file($real)) { @unlink($real); } });
        }
        $stream = fopen($real,'rb');
        if ( $stream ) { fpassthru($stream); fclose($stream); }
        if ( $remove_after_download ) { @unlink($real); } // Transfer is not a retained server backup.
        exit;
    }

    /** Direct transfer for administrators; records are never retained as server backups. */
    public function handle_quick_export() {
        $this->guard(); check_admin_referer('nine_backup_quick_export');
        $kind = sanitize_key(wp_unslash($_POST['export_kind'] ?? ''));
        $allowed = array('posts', 'users', 'media', 'terms', 'menus', 'site_settings', 'all_records', 'all_portable');
        if (!in_array($kind, $allowed, true)) { wp_die('Unsupported export type.', '', array('response'=>400)); }
        if (function_exists('wp_raise_memory_limit')) { wp_raise_memory_limit('admin'); }
        @set_time_limit(0);
        $bulk = $kind === 'all_records' ? array('full_records') : ($kind === 'all_portable' ? array('full_portability') : array($kind));
        $config = array(
            'name'=>'Open Scholar Transfer '.str_replace('_',' ',ucwords($kind,'_')).' '.wp_date('Y-m-d H-i-s'),
            'bulk'=>$bulk,
            'include_dependencies'=>in_array($kind,array('all_records','all_portable'),true),
            'include_user_content'=>false,
            'include_media_files'=>in_array($kind,array('media','all_records','all_portable'),true),
            'include_sensitive_settings'=>false,
            'plugin_include_files'=>true,
            'plugin_include_settings'=>true,
            'plugin_include_tables'=>true,
            'plugin_include_records'=>true,
            'copy_to_drive_folder'=>false,
            'source'=>'direct-download',
        );
        // Whole-site and media transfers run as a resumable chunked job on shared hosting, so one request
        // never has to build the whole package. The finished ZIP appears under Local archives.
        $settings = get_option('nine_backup_settings', array());
        $safe_mode = ! array_key_exists('shared_host_safe_mode', $settings) || ! empty($settings['shared_host_safe_mode']);
        if ($safe_mode && in_array($kind, array('media','all_records','all_portable'), true)) {
            $job = $this->create_safe_job($config);
            if (is_wp_error($job)) { $this->redirect_error($job->get_error_message(),'transfer'); }
            wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab=archives&nine_notice=safe_started')); exit;
        }
        try {
            $result = (new Nine_Backup_Exporter($config))->create_package();
        } catch (Throwable $error) {
            Nine_Backup_Health::log('quick-export', $error->getMessage());
            $this->redirect_error('Export failed: '.$error->getMessage(),'transfer');
        }
        if (is_wp_error($result)) { $this->redirect_error($result->get_error_message(),'transfer'); }
        if (empty($result['path'])) { $this->redirect_error('No downloadable export package was created.','transfer'); }
        $this->stream_protected_zip($result['path'], true);
    }

    /** Dedicated snapshot/portable backup route; never used by the transfer ZIP action. */
    public function handle_quick_backup() {
        $this->guard(); check_admin_referer('nine_backup_quick_backup');
        $target = sanitize_key(wp_unslash($_POST['backup_target'] ?? ''));
        $type = sanitize_key(wp_unslash($_POST['backup_type'] ?? ''));
        if (!in_array($target,array('server','drive'),true) || !in_array($type,array('snapshot','portable'),true)) {
            wp_die('Unsupported backup option.', '', array('response'=>400));
        }
        if ('drive' === $target && (!class_exists('Nine_Backup_Google_Drive') || !Nine_Backup_Google_Drive::connected())) {
            $this->redirect_error('Connect Google Drive before creating an off-site backup.','google');
        }
        if (function_exists('wp_raise_memory_limit')) { wp_raise_memory_limit('admin'); }
        @set_time_limit(0);
        Nine_Backup_Health::operation_start('quick-backup',array('target'=>$target,'type'=>$type));
        $config = array(
            'name'=>'Open Scholar '.('snapshot' === $type ? 'Database and Files' : 'Portable Site').' '.wp_date('Y-m-d H-i-s'),
            'bulk'=>array('snapshot' === $type ? 'disaster_snapshot' : 'full_portability'),
            'include_media_files'=>true,
            'include_sensitive_settings'=>false,
            'copy_to_drive_folder'=>true,
            'source'=>'quick-backup',
        );
        try {
            $result = (new Nine_Backup_Exporter($config))->create_package();
            if (is_wp_error($result)) { $this->redirect_error($result->get_error_message(), 'local'); }
            if (empty($result['path']) || !is_file($result['path'])) { $this->redirect_error('Backup archive was not created.', 'local'); }
            if ('drive' === $target) {
                // Honour automatic Drive copy, but never upload the same package twice.
                $already = Nine_Backup_Google_Drive::file(basename($result['path']));
                if (empty($already['id'])) {
                    $remote = Nine_Backup_Google_Drive::upload($result['path']);
                    if (is_wp_error($remote)) {
                        Nine_Backup_Health::log('quick-backup-drive', $remote->get_error_message());
                        $this->redirect_error('Server backup saved, but Google Drive upload failed: '.$remote->get_error_message(), 'google');
                    }
                }
            }
            set_transient('nine_backup_last_export_'.get_current_user_id(), $result, HOUR_IN_SECONDS);
            Nine_Backup_Health::operation_finish('quick-backup');
            wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab='.('drive' === $target ? 'google' : 'archives').'&nine_notice='.('drive' === $target ? 'drive_uploaded' : 'exported')));
            exit;
        } catch (Throwable $error) {
            Nine_Backup_Health::log('quick-backup', $error->getMessage());
            $this->redirect_error('Backup failed: '.$error->getMessage(), $target === 'drive' ? 'google' : 'local');
        }
    }

    public function handle_drive_upload_latest() {
        $this->guard(); check_admin_referer('nine_backup_drive_upload_latest');
        if (!class_exists('Nine_Backup_Google_Drive') || !Nine_Backup_Google_Drive::connected()) { $this->redirect_error('Connect Google Drive before attempting an upload.','google'); }
        $files = $this->archive_files();
        if (!$files) { $this->redirect_error('No local backup package exists to upload.','google'); }
        $result = Nine_Backup_Google_Drive::upload($files[0]);
        if (is_wp_error($result)) { $this->redirect_error($result->get_error_message(),'google'); }
        wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab=google&nine_notice=drive_uploaded')); exit;
    }

    private function render_export() {
        $post_types = get_post_types( array( 'show_ui'=>true ), 'objects' );
        unset( $post_types['attachment'], $post_types['elementor_library'], $post_types['acf-field-group'], $post_types['acf-field'] );
        $taxonomies = get_taxonomies( array( 'show_ui'=>true ), 'objects' );
        $wp_roles = function_exists( 'wp_roles' ) ? wp_roles() : null;
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nine-export-form">
            <input type="hidden" name="action" value="nine_backup_export">
            <?php wp_nonce_field( 'nine_backup_export' ); ?>

            <details class="nine-accordion" data-ninecode-settings-section data-osch-keep-open open><summary><strong>1. Name and purpose</strong></summary><div class="nine-section-inner">
                <input class="regular-text" type="text" name="backup_name" required value="<?php echo esc_attr( 'Open Scholar Backup ' . wp_date( 'Y-m-d' ) ); ?>" placeholder="e.g. Entrepreneurship 102 + required plugins — August 2026">
                <p class="description">Use a human name. It appears in the ZIP filename, manifest, Drive folder and reports.</p>
            </div></details>

            <div class="nine-quick-backup"><strong>Quick test (preselected)</strong><label class="block"><input type="checkbox" name="bulk[]" value="site_settings" checked> Site settings (small, quick backup test)</label><p class="description">This checks that ZIP export works; it does not save your posts, plugins or complete site. For a full backup choose <strong>Complete Portable Site</strong> in Section 3. For a custom backup deselect the quick test.</p></div>
            <details class="nine-accordion" data-ninecode-settings-section><summary><strong>2. Exact selection — one record, plugin, template or person</strong></summary><div class="nine-section-inner">
                <p>Use the finder when the backup should contain only a precise item or small set.</p>
                <div class="nine-finder-row">
                    <select id="nine-resource-kind">
                        <option value="post">Posts / Pages / Custom Content</option>
                        <option value="plugin">Plugins</option>
                        <option value="user">Users</option>
                        <option value="term">Categories / Tags / Terms</option>
                        <option value="elementor">Elementor Templates</option>
                        <option value="acf">ACF Field Groups</option>
                        <option value="menu">Menus</option>
                        <option value="media">Media</option>
                    </select>
                    <input type="search" id="nine-resource-search" placeholder="Type a title, plugin, name, email, slug or ID">
                    <button type="button" class="button" id="nine-resource-search-btn">Find</button>
                </div>
                <div id="nine-resource-results"></div>
                <h3>Selected items</h3>
                <div id="nine-selected-list" class="nine-selected-list"><em>Nothing selected yet.</em></div>
                <textarea name="tokens" id="nine-selected-tokens" class="large-text code" rows="4" placeholder="Examples: post:123, user:5, term:9, plugin:nine-post-manager/nine-post-manager.php"></textarea>
            </div></details>

            <details class="nine-accordion" data-ninecode-settings-section><summary><strong>3. Broad backup presets</strong></summary><div class="nine-section-inner">
                <div class="nine-check-grid">
                    <?php foreach ( array(
                        'full_portability'=>'COMPLETE PORTABLE SITE (portable records + installed plugin packages/settings)',
                        'full_records'=>'COMPLETE RECORDS BACKUP (all portable WordPress records)',
                        'nine_plugins'=>'ALL NINE PLUGINS (files + detected settings/tables)',
                        'plugins'=>'ALL INSTALLED PLUGINS (files + detected settings/tables)',
                        'disaster_snapshot'=>'DISASTER SNAPSHOT (database SQL + wp-content ZIP)',
                        'posts'=>'Posts / Pages / Custom Post Types',
                        'users'=>'Users',
                        'terms'=>'Categories / Tags / Taxonomies',
                        'media'=>'Media Library',
                        'elementor'=>'Elementor Template Library',
                        'acf'=>'ACF Field Groups',
                        'menus'=>'WordPress Menus',
                        'theme_settings'=>'Theme / Widget / Header-Footer Settings',
                        'site_settings'=>'Basic Site Settings'
                    ) as $value=>$label ) : ?>
                        <label><input type="checkbox" name="bulk[]" value="<?php echo esc_attr( $value ); ?>"> <?php echo esc_html( $label ); ?></label>
                    <?php endforeach; ?>
                </div>
                <p class="description"><strong>Complete Portable Site</strong> is for cloning/moving a working Nine setup without making the destructive disaster snapshot your normal migration format.</p>
                <details><summary>Limit bulk records by post type, taxonomy, role or date</summary>
                    <div class="nine-grid two compact">
                        <div><h4>Post types</h4><?php foreach ( $post_types as $pt ) : ?><label class="block"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr($pt->name); ?>"> <?php echo esc_html($pt->labels->singular_name . ' (' . $pt->name . ')'); ?></label><?php endforeach; ?></div>
                        <div><h4>Taxonomies</h4><?php foreach ( $taxonomies as $tx ) : ?><label class="block"><input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($tx->name); ?>"> <?php echo esc_html($tx->labels->singular_name . ' (' . $tx->name . ')'); ?></label><?php endforeach; ?></div>
                        <div><h4>User roles</h4><?php foreach ( ( $wp_roles ? $wp_roles->roles : array() ) as $key=>$role ) : ?><label class="block"><input type="checkbox" name="roles[]" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($role['name']); ?></label><?php endforeach; ?></div>
                        <div><h4>Content filters</h4><label>From <input type="date" name="date_from"></label><br><label>To <input type="date" name="date_to"></label><br><label>Keyword <input type="text" name="keyword"></label></div>
                    </div>
                </details>
            </div></details>

            <details class="nine-accordion" data-ninecode-settings-section><summary><strong>4. Plugin transfer controls</strong></summary><div class="nine-section-inner">
                <p>A selected plugin can be exported as <strong>code only</strong>, <strong>settings/data only</strong>, or a complete transfer bundle.</p>
                <label class="block"><input type="checkbox" name="plugin_include_files" value="1" checked> Include plugin files as a separate installable/restorable ZIP inside the package</label>
                <label class="block"><input type="checkbox" name="plugin_include_settings" value="1" checked> Include detected plugin settings/options</label>
                <label class="block"><input type="checkbox" name="plugin_include_tables" value="1" checked> Include detected plugin database tables and their rows</label>
                <label class="block"><input type="checkbox" name="plugin_include_records" value="1" checked> Include plugin-owned custom post types/taxonomies when they can be detected or registered</label>
                <label class="block"><input type="checkbox" name="plugin_include_dependencies" value="1" checked> Include installed plugins declared in the plugin's <code>Requires Plugins</code> dependency header</label>
                <p class="description">The manager scans plugin PHP for literal WordPress option calls and plugin table declarations, also checks slug-based option prefixes, and supports an explicit <code>nine_backup_plugin_registry</code> filter for exact Nine-plugin declarations. Sensitive option names are listed but redacted unless you explicitly include them.</p>
            </div></details>

            <details class="nine-accordion" data-ninecode-settings-section><summary><strong>5. Relationships, records and advanced settings</strong></summary><div class="nine-section-inner">
                <label class="block"><input type="checkbox" name="include_dependencies" value="1" checked> Include dependencies (authors, assigned terms, featured media and parent terms)</label>
                <label class="block"><input type="checkbox" name="include_term_children" value="1" checked> When selecting a category/term, include its child terms</label>
                <label class="block"><input type="checkbox" name="include_term_content" value="1"> When selecting a category/term, also include content assigned to it</label>
                <label class="block"><input type="checkbox" name="include_user_content" value="1"> When selecting a user, also include content authored by that user</label>
                <label class="block"><input type="checkbox" name="include_media_files" value="1"> Include physical media files when selected/referenced</label>
                <label class="block"><input type="checkbox" name="include_nine_options" value="1"> Include all options beginning with <code>nine_</code>, <code>9_</code> or <code>n9_</code> (suite-wide settings)</label>
                <p><label><strong>Specific WordPress option names</strong><br><textarea class="large-text code" name="option_names" rows="3" placeholder="One per line, e.g. theme_mods_mytheme"></textarea></label></p>
                <p><label><strong>Specific custom database tables</strong><br><textarea class="large-text code" name="table_names" rows="3" placeholder="One full table name per line, e.g. wp_nine_submissions"></textarea></label></p>
            </div></details>

            <details class="nine-accordion" data-ninecode-settings-section><summary><strong>6. Save this as a reusable or scheduled profile</strong></summary><div class="nine-section-inner">
                <label>Profile name <input type="text" name="profile_name" placeholder="Optional"></label>
                <label>Schedule <select name="profile_schedule"><option value="none">Do not schedule</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label>
                <p class="description">Scheduled packages are stored in Archives and copied to the configured Drive sync folder when available.</p>
            </div></details>
            <div id="osch-backup-feedback" class="osch-backup-feedback" role="status" aria-live="polite" hidden><span class="osch-backup-spinner" aria-hidden="true"></span><span data-backup-feedback-text></span></div>
            <?php submit_button( 'Create Open Scholar Backup', 'primary large' ); ?>
        </form>
        <?php
    }

    private function render_import() {
        $saved = get_transient( 'nine_backup_preview_' . get_current_user_id() );
        if ( $saved && ! empty($saved['plugins_staged']) && empty($saved['post_stage_refreshed']) && ! empty($saved['path']) && file_exists($saved['path']) ) {
            $fresh = (new Nine_Backup_Importer($saved['path']))->preview();
            if ( ! is_wp_error($fresh) ) {
                $saved['preview'] = $fresh;
                $saved['post_stage_refreshed'] = true;
                set_transient( 'nine_backup_preview_' . get_current_user_id(), $saved, 2 * HOUR_IN_SECONDS );
            }
        }
        $preview = $saved;
        $result = get_transient( 'nine_backup_import_result_' . get_current_user_id() );
        if ( $result ) { delete_transient( 'nine_backup_import_result_' . get_current_user_id() ); }
        ?>
        <h2>Import a 9 Backup package</h2>
        <p>Upload a Nine package. It is extracted to a temporary directory, checked against its manifest/integrity list, and previewed before writes.</p>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="nine_backup_upload_import">
            <?php wp_nonce_field( 'nine_backup_upload_import' ); ?>
            <input type="file" name="package" accept=".zip,application/zip" required>
            <?php submit_button( 'Upload & Preview', 'secondary', 'submit', false ); ?>
        </form>
        <details class="nine-accordion" data-ninecode-settings-section><summary><strong>Large package / cPanel import</strong></summary><div class="nine-section-inner">
            <p>If WordPress upload limits block a large ZIP, upload it to the server with cPanel/File Manager, then enter its path below. The path must be inside the configured server import root.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="nine_backup_server_import">
                <?php wp_nonce_field( 'nine_backup_server_import' ); ?>
                <input class="large-text code" type="text" name="server_package_path" placeholder="e.g. wp-content/uploads/transfers/my-package.zip" required>
                <?php submit_button( 'Load from Server & Preview', 'secondary', 'submit', false ); ?>
            </form>
        </div></details>
        <?php if ( $preview && ! empty( $preview['preview'] ) ) : $p = $preview['preview'];
            $choices = (array) ( $preview['choices'] ?? array() );
            $chosen_strategy = $choices['strategy'] ?? 'update';
            $chosen_plugin_strategy = $choices['plugin_strategy'] ?? 'install_update';
            $chosen_activation = $choices['activation_policy'] ?? 'preserve';
            $has_plugin_bundles = false;
            foreach ( (array) ( $p['compatibility']['plugins'] ?? array() ) as $plugin_row ) { if ( ! empty( $plugin_row['has_bundle'] ) ) { $has_plugin_bundles = true; break; } }
        ?>
            <hr><h2>Preview: <?php echo esc_html( $p['manifest']['backup_name'] ?? 'Backup package' ); ?></h2>
            <p><strong>Type:</strong> <?php echo esc_html( $p['manifest']['package_type'] ?? 'legacy/selective' ); ?> &nbsp; <strong>Source:</strong> <?php echo esc_html( $p['manifest']['source_site'] ?? '' ); ?> &nbsp; <strong>Created:</strong> <?php echo esc_html( $p['manifest']['created_at'] ?? '' ); ?></p>
            <table class="widefat striped"><thead><tr><th>Section</th><th>Records</th></tr></thead><tbody>
                <?php foreach ( $p['counts'] as $key=>$count ) : ?><tr><td><?php echo esc_html( ucwords( str_replace('_',' ', $key) ) ); ?></td><td><?php echo (int)$count; ?></td></tr><?php endforeach; ?>
            </tbody></table>

            <?php if ( ! empty( $p['compatibility']['plugins'] ) ) : ?>
                <h3>Plugin transfer preflight</h3>
                <div class="nine-table-scroll"><table class="widefat striped"><thead><tr><th>Plugin</th><th>Package</th><th>Destination</th><th>Compatibility</th><th>Dependencies</th></tr></thead><tbody>
                    <?php foreach ( $p['compatibility']['plugins'] as $row ) : ?>
                        <tr><td><?php echo esc_html($row['name']); ?><br><code><?php echo esc_html($row['plugin_file']); ?></code></td><td><?php echo esc_html($row['package_version'] ?: '—'); ?><?php echo !empty($row['has_bundle']) ? '<br>Code included' : '<br>Settings/metadata only'; ?></td><td><?php echo !empty($row['installed']) ? 'Installed v'.esc_html($row['current_version']) : 'Not installed'; ?><?php echo !empty($row['active']) ? '<br>Active' : ''; ?></td><td><?php echo (!empty($row['php_compatible']) && !empty($row['wp_compatible'])) ? 'Pass' : 'Check WP/PHP requirements'; ?></td><td><?php $deps=implode(', ', (array)($row['requires_plugins'] ?? array())); echo esc_html($deps ?: '—'); if(!empty($row['missing_dependencies'])){echo '<br><strong>Missing: '.esc_html(implode(', ',$row['missing_dependencies'])).'</strong>';} ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>

            <?php if ( ! empty( $preview['plugins_staged'] ) ) : ?>
                <div class="notice notice-success inline"><p><strong>Plugin stage complete.</strong> WordPress has now reloaded with the selected plugin activation policy. Continue below to restore settings and records.</p></div>
            <?php endif; ?>
            <?php if ( ! empty( $p['compatibility']['acf_required'] ) && empty( $p['compatibility']['acf_available'] ) ) : ?><div class="notice notice-warning inline"><p>This package contains ACF field groups but ACF is not active. Those groups will be skipped unless ACF is installed/activated first.</p></div><?php endif; ?>
            <?php if ( ! empty( $p['compatibility']['elementor_required'] ) && empty( $p['compatibility']['elementor_available'] ) ) : ?><div class="notice notice-warning inline"><p>This package contains Elementor templates but Elementor is not detected. Those template records will be skipped unless Elementor is available.</p></div><?php endif; ?>
            <?php if ( ! empty( $p['manifest']['config']['disaster_snapshot'] ) ) : ?><div class="notice notice-info inline"><p>The Disaster Snapshot files are preserved in the package but the selective importer does not automatically execute the raw SQL/wp-content restore.</p></div><?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nine-import-form">
                <input type="hidden" name="action" value="nine_backup_run_import">
                <?php wp_nonce_field( 'nine_backup_run_import' ); ?>
                <details class="nine-accordion" data-ninecode-settings-section><summary><strong>Record/settings conflict rule</strong></summary><div class="nine-section-inner">
                    <label class="block"><input type="radio" name="strategy" value="update" <?php checked($chosen_strategy,'update'); ?>> <strong>Update</strong> matching records/settings; create missing records</label>
                    <label class="block"><input type="radio" name="strategy" value="skip" <?php checked($chosen_strategy,'skip'); ?>> <strong>Skip</strong> matching records/settings that already exist</label>
                    <label class="block"><input type="radio" name="strategy" value="duplicate" <?php checked($chosen_strategy,'duplicate'); ?>> <strong>Duplicate</strong> where possible instead of replacing</label>
                </div></details>
                <?php if ( $has_plugin_bundles && empty( $preview['plugins_staged'] ) ) : ?>
                    <details class="nine-accordion" data-ninecode-settings-section><summary><strong>Plugin code handling</strong></summary><div class="nine-section-inner">
                        <label class="block"><input type="radio" name="plugin_strategy" value="install_update" <?php checked($chosen_plugin_strategy,'install_update'); ?>> <strong>Install / update</strong> plugin code. Existing plugin gets a rollback ZIP first.</label>
                        <label class="block"><input type="radio" name="plugin_strategy" value="skip_existing" <?php checked($chosen_plugin_strategy,'skip_existing'); ?>> Install only plugins that are missing; keep existing copies.</label>
                        <label class="block"><input type="radio" name="plugin_strategy" value="package_only" <?php checked($chosen_plugin_strategy,'package_only'); ?>> Do not install plugin code; import only compatible settings/records.</label>
                        <h4>Activation after plugin transfer</h4>
                        <label class="block"><input type="radio" name="activation_policy" value="preserve" <?php checked($chosen_activation,'preserve'); ?>> Preserve source active/inactive state <strong>(recommended for site transfer)</strong></label>
                        <label class="block"><input type="radio" name="activation_policy" value="inactive" <?php checked($chosen_activation,'inactive'); ?>> Keep transferred plugins inactive</label>
                        <label class="block"><input type="radio" name="activation_policy" value="activate" <?php checked($chosen_activation,'activate'); ?>> Activate all transferred plugins</label>
                        <p class="description">Plugin code is staged in a separate request before content restoration. This gives WordPress a clean reload so newly transferred plugins can register their custom post types and taxonomies.</p>
                    </div></details>
                <?php else : ?>
                    <input type="hidden" name="plugin_strategy" value="<?php echo esc_attr($chosen_plugin_strategy); ?>">
                    <input type="hidden" name="activation_policy" value="<?php echo esc_attr($chosen_activation); ?>">
                <?php endif; ?>
                <?php $button = ( $has_plugin_bundles && empty($preview['plugins_staged']) && 'package_only' !== $chosen_plugin_strategy ) ? 'Stage Plugins & Continue' : ( !empty($preview['plugins_staged']) ? 'Continue: Import Settings & Records' : 'Confirm Import' ); ?>
                <?php submit_button( $button, 'primary' ); ?>
            </form>
        <?php endif; ?>
        <?php if ( $result ) : ?>
            <hr><h2>Latest import log</h2>
            <div class="nine-table-scroll"><table class="widefat striped"><thead><tr><th>Type</th><th>Status</th><th>Record</th><th>New ID / Detail</th></tr></thead><tbody>
                <?php foreach ( $result['log'] ?? array() as $line ) : ?><tr><td><?php echo esc_html($line['type'] ?? ''); ?></td><td><?php echo esc_html($line['status'] ?? ''); ?></td><td><?php echo esc_html($line['message'] ?? ''); ?></td><td><?php echo esc_html( isset($line['new_id']) ? $line['new_id'] : (isset($line['rows']) ? $line['rows'].' rows' : '') ); ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif;
    }

    private function render_archives() {
        $last_export = get_transient('nine_backup_last_export_' . get_current_user_id());
        if (is_array($last_export) && !empty($last_export['drive_copy']) && 'error' === ($last_export['drive_copy']['status'] ?? '')) {
            echo '<div class="notice notice-warning inline"><p><strong>Local backup exists, but off-site upload failed.</strong> Check Google Drive status, OAuth connection and Diagnostics, then use Google Drive Backup → Upload latest local backup.</p></div>';
        }
        $job = $this->get_safe_job();
        if ( $job && empty( $job['finished'] ) ) {
            $current = min( (int)($job['current'] ?? 0), (int)($job['total'] ?? 0) );
            $total = max( 1, (int)($job['total'] ?? 1) );
            $percent = (int) floor( ( $current / $total ) * 100 );
            echo '<div class="notice notice-info inline"><p><strong>Shared-hosting safe backup is running.</strong> Bluehost and similar shared hosts can terminate long PHP requests, so this backup is being split into small independent requests.</p></div>';
            echo '<div id="nine-safe-job-progress" data-current="' . esc_attr($current) . '" data-total="' . esc_attr($total) . '">';
            echo '<h2>Backup progress</h2><div class="nine-progress-track"><div class="nine-progress-bar" style="width:' . esc_attr($percent) . '%"></div></div>';
            echo '<p class="nine-safe-status" role="status" aria-live="polite">Part ' . esc_html($current) . ' of ' . esc_html($total) . ' completed.</p>';
            echo '<p class="description">Each completed part is saved independently. If automatic progress stops, use the button below to complete one part at a time without AJAX.</p>';
            echo '<form id="nine-backup-manual-step" class="nine-backup-manual-step" method="post" action="' . esc_url( admin_url('admin-post.php') ) . '">';
            echo '<input type="hidden" name="action" value="nine_backup_safe_step_manual">';
            wp_nonce_field('nine_backup_safe_step_manual');
            echo '<button type="submit" class="button button-secondary">Continue next part manually</button><span class="description"> Alternative for hosts that block admin-ajax.php</span></form>';
            echo '</div><hr>';
        } elseif ( $job && ! empty( $job['finished'] ) ) {
            $errors = (array)($job['errors'] ?? array());
            if ( $errors ) {
                echo '<div class="notice notice-warning inline"><p><strong>Safe backup completed with ' . count($errors) . ' failed part(s).</strong> Successful parts are in Archives. Open Diagnostics for host errors.</p></div>';
            } else {
                echo '<div class="notice notice-success inline"><p><strong>Shared-hosting safe backup completed.</strong> All parts are available below.</p></div>';
            }
            $this->delete_safe_job();
        }
        $files = $this->archive_files();
        echo '<h2>Local archives</h2><p>These are independent ZIP packages. Download portable ZIPs for offline storage and transfer. Store them only in a private or encrypted location. Downloads require admin password confirmation; ZIP content itself is not encrypted.</p><p><strong>Server/cPanel archive folder:</strong> <code>' . esc_html( Nine_Backup_Exporter::archive_dir() ) . '</code></p>';
        if ( ! $files ) { echo '<p>No archives yet.</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Package</th><th>Created</th><th>Size</th><th>Google Drive</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $files as $file ) {
            $download_form = '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="nine-archive-download-form" style="display:inline-block"><input type="hidden" name="action" value="nine_backup_download_archive"><input type="hidden" name="file" value="'.esc_attr(basename($file)).'">'.wp_nonce_field('nine_backup_archive_'.basename($file),'_wpnonce',true,false).'<button class="button button-primary" type="submit">Download ZIP</button></form>';
            $delete = wp_nonce_url( admin_url( 'admin-post.php?action=nine_backup_delete_archive&file=' . rawurlencode( basename( $file ) ) ), 'nine_backup_archive_' . basename( $file ) );
            $remote = class_exists('Nine_Backup_Google_Drive') ? Nine_Backup_Google_Drive::file(basename($file)) : array();
            $drive_label = !empty($remote['id']) ? (!empty($remote['url']) ? '<a href="'.esc_url($remote['url']).'" target="_blank" rel="noopener">Uploaded ↗</a>' : 'Uploaded') : 'Local only';
            echo '<tr><td><code>' . esc_html( basename( $file ) ) . '</code></td><td>' . esc_html( wp_date( 'Y-m-d H:i:s', filemtime($file) ) ) . '</td><td>' . esc_html( size_format( filesize($file) ) ) . '</td><td>' . $drive_label . '</td><td>' . $download_form . ' <a class="button" onclick="return confirm(\'Delete this local archive? Google Drive copy will be preserved.\')" href="' . esc_url($delete) . '">Delete</a></td></tr>';
        }
        echo '</tbody></table>';
    }

    private function render_profiles() {
        $profiles = get_option( 'nine_backup_profiles', array() );
        echo '<h2>Reusable and scheduled backup profiles</h2><p>Create a profile from the Create Backup tab. Scheduled profiles run through WordPress Cron and preserve the same selection logic.</p>';
        if ( ! $profiles ) { echo '<p>No profiles saved yet.</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Schedule</th><th>Last run</th><th>Next eligible run</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $profiles as $id=>$profile ) {
            $del = wp_nonce_url( admin_url( 'admin-post.php?action=nine_backup_delete_profile&id=' . rawurlencode($id) ), 'nine_backup_profile_' . $id );
            $run = wp_nonce_url( admin_url( 'admin-post.php?action=nine_backup_run_profile&id=' . rawurlencode($id) ), 'nine_backup_profile_' . $id );
            echo '<tr><td>' . esc_html($profile['name']) . '</td><td>' . esc_html($profile['schedule']) . '</td><td>' . esc_html( !empty($profile['last_run']) ? wp_date('Y-m-d H:i', $profile['last_run']) : 'Never' ) . '</td><td>' . esc_html( !empty($profile['next_run']) ? wp_date('Y-m-d H:i', $profile['next_run']) : 'Manual only' ) . '</td><td><a class="button button-primary" href="' . esc_url($run) . '">Run now</a> <a class="button" onclick="return confirm(\'Delete this profile?\')" href="' . esc_url($del) . '">Delete</a></td></tr>';
        }
        echo '</tbody></table>';
    }

    private function render_settings() {
        $settings = get_option( 'nine_backup_settings', array() );
        $drive = $settings['drive_sync_path'] ?? '';
        $retention = isset( $settings['archive_retention'] ) ? absint( $settings['archive_retention'] ) : 30;
        $group = ! array_key_exists( 'drive_group_by_type', $settings ) || ! empty( $settings['drive_group_by_type'] );
        $server_root = $settings['server_import_root'] ?? ABSPATH;
        $safe_mode = ! array_key_exists( 'shared_host_safe_mode', $settings ) || ! empty( $settings['shared_host_safe_mode'] );
        $google = class_exists( 'Nine_Backup_Google_Drive' ) ? Nine_Backup_Google_Drive::settings() : array();
        $google_ready = class_exists( 'Nine_Backup_Google_Drive' ) && Nine_Backup_Google_Drive::configured();
        $google_connected = class_exists( 'Nine_Backup_Google_Drive' ) && Nine_Backup_Google_Drive::connected();
        ?>
        <h2>Google Drive, offline storage and retention</h2>
        <p>Choose either a direct Google account connection, a server folder synchronised by Google Drive, or both. Every completed package stays local until you choose to delete it.</p>
        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <input type="hidden" name="action" value="nine_backup_save_settings">
            <?php wp_nonce_field( 'nine_backup_save_settings' ); ?>
            <table class="form-table"><tbody>
                <tr><th>Direct Google Drive connection</th><td>
                    <label class="block"><input type="checkbox" name="google_drive_enabled" value="1" <?php checked(!empty($settings['google_drive_enabled'])); ?> <?php disabled(!$google_connected); ?>> Upload each completed backup to Google Drive</label>
                    <?php if ( $google_connected ) : ?><p class="description"><strong>Connected.</strong> The account token is stored on this site only. <?php if (!empty($google['folder_id'])) : ?>New packages use the selected Drive folder.<?php else : ?>New packages go to the connected account’s Drive root.<?php endif; ?></p><?php else : ?><p class="description">Not connected yet. Add your Google Cloud OAuth details below, save, then connect the account.</p><?php endif; ?>
                </td></tr>
                <tr><th><label for="google_drive_client_id">Google OAuth Client ID</label></th><td><input class="large-text code" id="google_drive_client_id" name="google_drive_client_id" value="<?php echo esc_attr($google['client_id'] ?? ''); ?>" autocomplete="off"><p class="description">Create a Google Cloud <em>Web application</em> OAuth client with the redirect URL shown below.</p></td></tr>
                <tr><th><label for="google_drive_client_secret">Google OAuth Client Secret</label></th><td><input class="large-text code" id="google_drive_client_secret" name="google_drive_client_secret" value="" placeholder="Leave blank to keep the saved secret" autocomplete="new-password"><p class="description">Never displayed again after saving.</p></td></tr>
                <tr><th><label for="google_drive_folder_id">Google Drive Folder ID</label></th><td><input class="large-text code" id="google_drive_folder_id" name="google_drive_folder_id" value="<?php echo esc_attr($google['folder_id'] ?? ''); ?>" placeholder="Optional: use Drive root when blank"><p class="description">Optional destination folder ID from its Google Drive URL.</p></td></tr>
                <tr><th>Google redirect URL</th><td><code><?php echo esc_html( class_exists('Nine_Backup_Google_Drive') ? Nine_Backup_Google_Drive::redirect_uri() : '' ); ?></code><p class="description">Copy this exact URL into Google Cloud Console → Authorized redirect URIs.</p></td></tr>
                <tr><th><label for="drive_sync_path">Drive sync folder path</label></th><td><input class="large-text code" id="drive_sync_path" name="drive_sync_path" value="<?php echo esc_attr($drive); ?>" placeholder="e.g. /mnt/google-drive/9igeria-backups"><p class="description">Leave blank where the server has no Drive-mounted/synchronised path.</p></td></tr>
                <tr><th>Drive organisation</th><td><label><input type="checkbox" name="drive_group_by_type" value="1" <?php checked($group); ?>> Group packages by purpose/type</label><p class="description"><code>Drive Root / source-site / plugin-transfer|selective-records|complete-records|full-portability|disaster / YYYY / MM / package.zip</code></p></td></tr>
                <tr><th><label for="archive_retention">Local archive retention</label></th><td><input type="number" min="0" max="1000" id="archive_retention" name="archive_retention" value="<?php echo esc_attr($retention); ?>"> packages<p class="description">Keep the newest N local packages. Use 0 for unlimited. Drive/offline copies are not deleted.</p></td></tr>
                <tr><th><label for="server_import_root">cPanel/server import root</label></th><td><input class="large-text code" id="server_import_root" name="server_import_root" value="<?php echo esc_attr($server_root); ?>"><p class="description">Large-package server imports are restricted to this directory and its children. Default: the WordPress installation root.</p></td></tr>
                <tr><th>Shared-hosting safe mode</th><td><label><input type="checkbox" name="shared_host_safe_mode" value="1" <?php checked($safe_mode); ?>> Process manual backups in small sequential requests</label><p class="description"><strong>Recommended for Bluehost shared hosting.</strong> Prevents one long export request from being killed by server execution/resource limits. Large selections are split into independent portable parts.</p></td></tr>
            </tbody></table>
            <?php submit_button( 'Save Settings' ); ?>
        </form>
        <?php if ( $google_ready && ! $google_connected ) : ?><p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=nine_backup_google_drive_connect'), 'nine_backup_google_drive_connect' ) ); ?>">Connect Google Drive</a></p><?php elseif ( $google_connected ) : ?><p><a class="button" onclick="return confirm('Disconnect Google Drive? Existing local and remote backup files will not be deleted.');" href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=nine_backup_google_drive_disconnect'), 'nine_backup_google_drive_disconnect' ) ); ?>">Disconnect Google Drive</a></p><?php endif; ?>
        <h3>Plugin rollback storage</h3>
        <p>Before an imported plugin replaces an existing copy, the current plugin is saved in <code>nine-backup-manager/plugin-rollbacks</code>. This is independent from the normal archive retention count.</p>
        <?php
    }

    private function render_diagnostics() {
        $rows = Nine_Backup_Health::diagnostics();
        $latest = Nine_Backup_Health::latest_log();
        echo '<h2>Open Scholar Ultra Backup Diagnostics</h2>';
        echo '<p>This page checks the host capabilities the plugin actually needs. Red items block core backup/import functions; amber items may only affect large backups or plugin-code installation.</p>';
        $selftest = get_transient( 'nine_backup_host_selftest_' . get_current_user_id() );
        $test_url = wp_nonce_url( admin_url('admin-post.php?action=nine_backup_host_selftest'), 'nine_backup_host_selftest' );
        echo '<p><a class="button button-primary" href="' . esc_url($test_url) . '">Run Bluehost / Shared-Host Compatibility Test</a></p>';
        if ( is_array($selftest) ) {
            echo '<div class="nine-table-scroll"><table class="widefat striped"><thead><tr><th>Live test</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
            foreach($selftest as $test){echo '<tr><td><strong>'.esc_html($test['name']??'Test').'</strong></td><td>'.esc_html(!empty($test['pass'])?'PASS':'FAIL').'</td><td>'.esc_html($test['detail']??'').'</td></tr>';}
            echo '</tbody></table></div><br>';
        }
        echo '<div class="nine-table-scroll"><table class="widefat striped"><thead><tr><th>Check</th><th>Status</th><th>Detected</th><th>Meaning</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $status = ! empty($row['pass']) ? 'PASS' : ( ! empty($row['warning_only']) ? 'WARNING' : 'FAIL' );
            echo '<tr><td><strong>' . esc_html($row['name']) . '</strong></td><td>' . esc_html($status) . '</td><td><code>' . esc_html((string)$row['value']) . '</code></td><td>' . esc_html($row['help']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<h3>Latest error log</h3>';
        if ( $latest && is_file($latest) ) {
            $content = @file_get_contents($latest);
            if ( false !== $content ) {
                $lines = preg_split('/\R/', trim($content));
                $lines = array_slice($lines, -80);
                echo '<pre class="nine-log">' . esc_html(implode("\n",$lines)) . '</pre>';
            } else { echo '<p>Log exists but could not be read.</p>'; }
        } else {
            echo '<p>No Backup Manager errors have been logged yet.</p>';
        }
        echo '<p><strong>Important:</strong> plugin-code imports need the plugins directory to be writable by PHP. Settings/data imports do not require plugin-directory write access when the destination plugin is already installed.</p>';
    }

    public function ajax_search() {
        $this->guard();
        check_ajax_referer( 'nine_backup_search', 'nonce' );
        $kind = sanitize_key( $_POST['kind'] ?? 'post' );
        $q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
        $results = array();
        switch ( $kind ) {
            case 'plugin':
                foreach ( Nine_Backup_Plugins::installed_plugins() as $file=>$data ) {
                    $hay = ($data['Name'] ?? '') . ' ' . $file . ' ' . ($data['Description'] ?? '');
                    if ( $q && false === stripos( $hay, $q ) ) { continue; }
                    $active = is_plugin_active( $file ) ? 'Active' : 'Inactive';
                    $results[] = array( 'token'=>'plugin:'.$file, 'label'=>$data['Name'] ?? $file, 'detail'=>'v'.($data['Version'] ?? '?').' · '.$active.' · '.$file );
                    if ( count($results) >= 30 ) { break; }
                }
                break;
            case 'user':
                $args = array( 'number'=>30, 'orderby'=>'display_name', 'order'=>'ASC' );
                if ( $q ) { $args['search'] = '*' . $q . '*'; $args['search_columns'] = array( 'user_login','user_email','display_name' ); }
                foreach ( (new WP_User_Query($args))->get_results() as $u ) { $results[] = array( 'token'=>'user:'.$u->ID, 'label'=>$u->display_name ?: $u->user_login, 'detail'=>$u->user_email . ' · ID ' . $u->ID ); }
                break;
            case 'term':
                $args = array( 'taxonomy'=>get_taxonomies(array('show_ui'=>true),'names'), 'hide_empty'=>false, 'number'=>30 );
                if ( $q ) { $args['search'] = $q; }
                $terms = get_terms($args);
                if ( ! is_wp_error($terms) ) { foreach ($terms as $t) { $results[] = array('token'=>'term:'.$t->term_id,'label'=>$t->name,'detail'=>$t->taxonomy.' · '.$t->slug.' · ID '.$t->term_id); } }
                break;
            case 'elementor':
                $args = array('post_type'=>'elementor_library','post_status'=>'any','posts_per_page'=>30,'orderby'=>'title','order'=>'ASC'); if($q){$args['s']=$q;}
                foreach(get_posts($args) as $p){$results[]=array('token'=>'elementor:'.$p->ID,'label'=>$p->post_title ?: '(Untitled template)','detail'=>(get_post_meta($p->ID,'_elementor_template_type',true) ?: 'template').' · ID '.$p->ID);}
                break;
            case 'acf':
                if ( function_exists('acf_get_field_groups') ) { foreach(acf_get_field_groups() as $g){ if($q && false===stripos(($g['title']??'').' '.($g['key']??''),$q)){continue;} $results[]=array('token'=>'acf:'.($g['ID']??0),'label'=>$g['title']??'Field Group','detail'=>($g['key']??'').' · ID '.($g['ID']??0)); if(count($results)>=30)break; } }
                break;
            case 'menu':
                foreach(wp_get_nav_menus() as $m){if($q && false===stripos($m->name.' '.$m->slug,$q)){continue;}$results[]=array('token'=>'menu:'.$m->term_id,'label'=>$m->name,'detail'=>$m->slug.' · ID '.$m->term_id);if(count($results)>=30)break;}
                break;
            case 'media':
                $args=array('post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>30,'orderby'=>'date','order'=>'DESC');if($q){$args['s']=$q;}
                foreach(get_posts($args) as $p){$results[]=array('token'=>'media:'.$p->ID,'label'=>$p->post_title ?: basename(get_attached_file($p->ID)),'detail'=>get_post_mime_type($p->ID).' · ID '.$p->ID);}
                break;
            default:
                $types = get_post_types(array('show_ui'=>true),'names'); unset($types['attachment'],$types['acf-field'],$types['acf-field-group'],$types['elementor_library']);
                $args=array('post_type'=>array_values($types),'post_status'=>'any','posts_per_page'=>30,'orderby'=>'modified','order'=>'DESC');if($q){ if(ctype_digit($q)){$args['p']=(int)$q;}else{$args['s']=$q;} }
                foreach(get_posts($args) as $p){$results[]=array('token'=>'post:'.$p->ID,'label'=>$p->post_title ?: '(Untitled)','detail'=>$p->post_type.' · '.$p->post_status.' · ID '.$p->ID);}
        }
        wp_send_json_success( $results );
    }

    public function handle_export() {
        $this->guard(); check_admin_referer( 'nine_backup_export' ); @set_time_limit(0);
        if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }
        try {
            $config = $this->config_from_request( $_POST );
            if ( empty( $config['tokens'] ) && empty( $config['bulk'] ) && empty( $config['option_names'] ) && empty( $config['table_names'] ) && empty( $config['include_nine_options'] ) ) {
                $this->redirect_error( 'Select at least one exact record, bulk section, option or table.' );
            }
            $settings = get_option( 'nine_backup_settings', array() );
            $safe_mode = ! array_key_exists( 'shared_host_safe_mode', $settings ) || ! empty( $settings['shared_host_safe_mode'] );
            if ( $safe_mode && 'scheduled-profile' !== ( $config['source'] ?? '' ) ) {
                $job = $this->create_safe_job( $config );
                if ( is_wp_error( $job ) ) { $this->redirect_error( $job->get_error_message() ); }
                wp_safe_redirect( admin_url('admin.php?page=nine-backup-manager&tab=archives&nine_notice=safe_started') ); exit;
            }
            Nine_Backup_Health::operation_start( 'manual-export', array('name'=>$config['name'] ?? '') );
            $exporter = new Nine_Backup_Exporter( $config );
            $result = $exporter->create_package();
            if ( is_wp_error($result) ) { Nine_Backup_Health::operation_finish('manual-export'); $this->redirect_error( $result->get_error_message() ); }
            Nine_Backup_Health::operation_finish('manual-export');
            set_transient( 'nine_backup_last_export_' . get_current_user_id(), $result, HOUR_IN_SECONDS );
            if ( ! empty($_POST['profile_name']) ) { $this->save_profile_from_config( $config, sanitize_text_field(wp_unslash($_POST['profile_name'])), sanitize_key($_POST['profile_schedule'] ?? 'none') ); }
            wp_safe_redirect( admin_url('admin.php?page=nine-backup-manager&tab=archives&nine_notice=exported') ); exit;
        } catch ( Throwable $e ) {
            Nine_Backup_Health::log( 'handle_export', $e->getMessage() );
            $this->redirect_error( 'Export failed: ' . $e->getMessage() );
        }
    }

    private function config_from_request( $request ) {
        $lines = function($value){ $parts=preg_split('/[\r\n,]+/', (string)wp_unslash($value)); return array_values(array_filter(array_map('trim',$parts))); };
        return array(
            'name'=>sanitize_text_field(wp_unslash($request['backup_name'] ?? 'Manual Backup')),
            'tokens'=>$lines($request['tokens'] ?? ''),
            'bulk'=>array_map('sanitize_key',(array)($request['bulk'] ?? array())),
            'post_types'=>array_map('sanitize_key',(array)($request['post_types'] ?? array())),
            'taxonomies'=>array_map('sanitize_key',(array)($request['taxonomies'] ?? array())),
            'roles'=>array_map('sanitize_key',(array)($request['roles'] ?? array())),
            'date_from'=>sanitize_text_field($request['date_from'] ?? ''),
            'date_to'=>sanitize_text_field($request['date_to'] ?? ''),
            'keyword'=>sanitize_text_field(wp_unslash($request['keyword'] ?? '')),
            'include_dependencies'=>!empty($request['include_dependencies']),
            'include_term_children'=>!empty($request['include_term_children']),
            'include_term_content'=>!empty($request['include_term_content']),
            'include_user_content'=>!empty($request['include_user_content']),
            'include_media_files'=>!empty($request['include_media_files']),
            'include_nine_options'=>!empty($request['include_nine_options']),
            'plugin_include_files'=>!empty($request['plugin_include_files']),
            'plugin_include_settings'=>!empty($request['plugin_include_settings']),
            'plugin_include_tables'=>!empty($request['plugin_include_tables']),
            'plugin_include_records'=>!empty($request['plugin_include_records']),
            'plugin_include_dependencies'=>!empty($request['plugin_include_dependencies']),
            'include_sensitive_settings'=>false,
            'plugin_files'=>array(),
            'option_names'=>$lines($request['option_names'] ?? ''),
            'table_names'=>$lines($request['table_names'] ?? ''),
            'copy_to_drive_folder'=>true,
            'source'=>'manual',
        );
    }

    private function safe_job_key() { return 'nine_backup_safe_job_' . get_current_user_id(); }

    private function get_safe_job() {
        $job = get_option( $this->safe_job_key(), array() );
        return is_array( $job ) && $job ? $job : array();
    }

    private function save_safe_job( array $job ) {
        update_option( $this->safe_job_key(), $job, false );
    }

    private function delete_safe_job() { delete_option( $this->safe_job_key() ); }

    /**
     * Break a potentially large export into small, independently valid packages.
     * This is deliberately request-oriented for Bluehost/shared hosting: one AJAX request = one small package.
     */
    private function create_safe_job( array $config ) {
        $tasks = array();
        $base = $config;
        $base['bulk'] = array();
        $base['tokens'] = array();
        $base['option_names'] = array();
        $base['table_names'] = array();
        $base['include_nine_options'] = false;
        $base['copy_to_drive_folder'] = true;
        $base['source'] = 'shared-host-safe';
        $name = $config['name'] ?: 'Backup';

        $add_chunks = function( $tokens, $size, $label ) use ( &$tasks, $base, $name ) {
            $chunks = array_chunk( array_values( array_unique( array_filter( $tokens ) ) ), max(1,(int)$size) );
            foreach ( $chunks as $i=>$chunk ) {
                $cfg = $base;
                $cfg['tokens'] = $chunk;
                $cfg['name'] = $name . ' — ' . $label . ' ' . ( $i + 1 );
                $tasks[] = $cfg;
            }
        };

        $plugin_tokens = array(); $other_tokens = array();
        foreach ( (array)$config['tokens'] as $token ) {
            if ( 0 === strpos( $token, 'plugin:' ) ) { $plugin_tokens[] = $token; }
            else { $other_tokens[] = $token; }
        }
        $add_chunks( $other_tokens, 20, 'Selected Records' );
        $add_chunks( $plugin_tokens, 1, 'Plugin' );

        $bulk = (array)$config['bulk'];
        if ( in_array('full_portability',$bulk,true) ) { $bulk = array_values(array_unique(array_merge($bulk,array('full_records','plugins')))); }
        if ( in_array('full_records',$bulk,true) ) { $bulk = array_values(array_unique(array_merge($bulk,array('posts','users','terms','media','elementor','acf','menus','theme_settings','site_settings')))); }

        if ( in_array('posts',$bulk,true) ) {
            $post_types = (array)$config['post_types'];
            if ( !$post_types ) {
                $post_types = get_post_types(array('show_ui'=>true),'names');
                foreach(array('wp_template','wp_template_part','wp_global_styles','wp_navigation') as $pt){ if(post_type_exists($pt)){$post_types[]=$pt;} }
                $post_types = array_values(array_diff(array_unique($post_types),array('attachment','elementor_library','acf-field','acf-field-group')));
            }
            $args=array('post_type'=>$post_types,'post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids','orderby'=>'ID','order'=>'ASC','no_found_rows'=>true,'suppress_filters'=>false);
            if(!empty($config['keyword'])){$args['s']=$config['keyword'];}
            if(!empty($config['date_from'])||!empty($config['date_to'])){$dq=array('inclusive'=>true);if(!empty($config['date_from'])){$dq['after']=$config['date_from'];}if(!empty($config['date_to'])){$dq['before']=$config['date_to'].' 23:59:59';}$args['date_query']=array($dq);}
            $add_chunks(array_map(function($id){return 'post:'.$id;},get_posts($args)),20,'Posts');
        }
        if ( in_array('users',$bulk,true) ) {
            $args=array('fields'=>'ID'); if(!empty($config['roles'])){$args['role__in']=$config['roles'];}
            $ids=(new WP_User_Query($args))->get_results();
            $add_chunks(array_map(function($id){return 'user:'.$id;},$ids),50,'Users');
        }
        if ( in_array('terms',$bulk,true) ) {
            $tax=(array)$config['taxonomies']; if(!$tax){$tax=get_taxonomies(array('public'=>true),'names');}
            $terms=get_terms(array('taxonomy'=>$tax,'hide_empty'=>false)); $tokens=array();
            if(!is_wp_error($terms)){foreach($terms as $t){$tokens[]='term:'.$t->term_id;}}
            $add_chunks($tokens,50,'Terms');
        }
        if ( in_array('media',$bulk,true) ) {
            $ids=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
            $add_chunks(array_map(function($id){return 'media:'.$id;},$ids),10,'Media');
        }
        if ( in_array('elementor',$bulk,true) ) {
            $ids=get_posts(array('post_type'=>'elementor_library','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
            $add_chunks(array_map(function($id){return 'elementor:'.$id;},$ids),5,'Elementor');
        }
        if ( in_array('acf',$bulk,true) && function_exists('acf_get_field_groups') ) {
            $tokens=array(); foreach(acf_get_field_groups() as $g){if(!empty($g['ID'])){$tokens[]='acf:'.$g['ID'];}}
            $add_chunks($tokens,10,'ACF');
        }
        if ( in_array('menus',$bulk,true) ) {
            $tokens=array(); foreach(wp_get_nav_menus() as $m){$tokens[]='menu:'.$m->term_id;}
            $add_chunks($tokens,10,'Menus');
        }
        if ( in_array('plugins',$bulk,true) || in_array('nine_plugins',$bulk,true) ) {
            $resolve=$config; $resolve['bulk']=in_array('plugins',$bulk,true)?array('plugins'):array('nine_plugins'); $resolve['plugin_files']=array();
            $files=Nine_Backup_Plugins::resolve_selected_files($resolve);
            $add_chunks(array_map(function($f){return 'plugin:'.$f;},$files),1,'Plugin');
        }
        foreach ( array('theme_settings'=>'Theme Settings','site_settings'=>'Site Settings','disaster_snapshot'=>'Disaster Snapshot') as $flag=>$label ) {
            if(in_array($flag,$bulk,true)){$cfg=$base;$cfg['bulk']=array($flag);$cfg['name']=$name.' — '.$label;$tasks[]=$cfg;}
        }
        if ( !empty($config['include_nine_options']) || in_array('full_records',$bulk,true) ) {
            $cfg=$base;$cfg['include_nine_options']=true;$cfg['name']=$name.' — Nine Settings';$tasks[]=$cfg;
        }
        if ( !empty($config['option_names']) || !empty($config['table_names']) ) {
            $cfg=$base;$cfg['option_names']=$config['option_names'];$cfg['table_names']=$config['table_names'];$cfg['name']=$name.' — Selected Options and Tables';$tasks[]=$cfg;
        }
        if ( !$tasks ) { return new WP_Error('nine_safe_no_tasks','Nothing could be resolved for the safe backup job.'); }
        foreach($tasks as $i=>&$task){$task['name']=$task['name'].' [Part '.($i+1).']';} unset($task);
        $job=array('created'=>time(),'current'=>0,'total'=>count($tasks),'tasks'=>$tasks,'results'=>array(),'errors'=>array(),'finished'=>false,'retry'=>array());
        $this->save_safe_job($job);
        return $job;
    }

    /** Minimal probe to distinguish an AJAX-blocking host from an export error. */
    public function ajax_probe() {
        $this->guard(); check_ajax_referer( 'nine_backup_safe_step', 'nonce' );
        wp_send_json_success( array( 'alive'=>true, 'php'=>PHP_VERSION, 'time'=>time() ) );
    }

    /** Run one chunk of a resumable backup in either AJAX or ordinary page mode. */
    private function process_safe_step() {
        $this->guard();
        if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }
        @set_time_limit( 45 );
        $job = $this->get_safe_job();
        if ( ! $job ) { return new WP_Error('nine_safe_missing','No pending backup job exists. Create a new backup.'); }
        $current = (int)($job['current'] ?? 0);
        $total = (int)($job['total'] ?? 0);
        if ( $current >= $total ) {
            $job['finished']=true; $this->save_safe_job($job);
            return array('done'=>true,'current'=>$current,'total'=>$total,'errors'=>count((array)$job['errors']));
        }
        $task=$job['tasks'][$current] ?? array();
        Nine_Backup_Health::operation_start('safe-export-part',array('part'=>$current+1,'total'=>$total,'name'=>$task['name']??''));
        try {
            $exporter=new Nine_Backup_Exporter($task);
            $result=$exporter->create_package();
            if (is_wp_error($result)) {
                $job['errors'][]=array('part'=>$current+1,'message'=>$result->get_error_message(),'name'=>$task['name']??'');
                Nine_Backup_Health::log('safe_export_part',$result->get_error_message(),array('part'=>$current+1,'name'=>$task['name']??''));
            } else { $job['results'][]=$result; }
            $job['current']=$current+1;
            if($job['current'] >= $total){$job['finished']=true;}
            $this->save_safe_job($job);
            Nine_Backup_Health::operation_finish('safe-export-part');
            return array('done'=>!empty($job['finished']),'current'=>$job['current'],'total'=>$total,'errors'=>count((array)$job['errors']),'last'=>is_wp_error($result)?$result->get_error_message():basename($result['archive']??''));
        } catch (Throwable $e) {
            Nine_Backup_Health::log('safe_export_exception',$e->getMessage(),array('part'=>$current+1));
            $retries=(int)($job['retry'][$current]??0);
            $job['retry'][$current]=$retries+1;
            if($retries>=1){
                $job['errors'][]=array('part'=>$current+1,'message'=>$e->getMessage(),'name'=>$task['name']??'');
                $job['current']=$current+1;
                if ($job['current'] >= $total) { $job['finished']=true; }
            }
            $this->save_safe_job($job);
            Nine_Backup_Health::operation_finish('safe-export-part');
            return new WP_Error('nine_safe_part_failed',$e->getMessage(),array('retry'=>$retries<1,'current'=>$job['current'],'total'=>$total));
        }
    }

    public function ajax_safe_step() {
        $this->guard(); check_ajax_referer('nine_backup_safe_step','nonce');
        $result=$this->process_safe_step();
        if (is_wp_error($result)) {
            $data=(array)$result->get_error_data();
            $data['message']=$result->get_error_message();
            wp_send_json_error($data,500);
        }
        wp_send_json_success($result);
    }

    /** Form POST fallback: bypass admin-ajax.php completely; one part per click. */
    public function handle_safe_step_manual() {
        $this->guard(); check_admin_referer('nine_backup_safe_step_manual');
        $result=$this->process_safe_step();
        $notice=is_wp_error($result)?'safe_manual_fail':'safe_manual_ok';
        $url=add_query_arg(array('page'=>'nine-backup-manager','tab'=>'archives','manual'=>'1','nine_notice'=>$notice),admin_url('admin.php'));
        wp_safe_redirect($url); exit;
    }

    public function handle_upload_import() {
        $this->guard(); check_admin_referer( 'nine_backup_upload_import' );
        try {
            Nine_Backup_Health::operation_start('upload-import-preview');
            if ( empty($_FILES['package']['tmp_name']) || ! is_uploaded_file($_FILES['package']['tmp_name']) ) { $this->redirect_error('Choose a ZIP package to import.', 'import'); }
            $original = sanitize_file_name( wp_unslash( $_FILES['package']['name'] ?? 'backup.zip' ) );
            if ( 'zip' !== strtolower( pathinfo( $original, PATHINFO_EXTENSION ) ) ) { $this->redirect_error('The import package must be a .zip file.', 'import'); }
            $size = isset($_FILES['package']['size']) ? (int)$_FILES['package']['size'] : 0;
            if ( $size <= 0 ) { $this->redirect_error('The uploaded ZIP is empty.', 'import'); }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            // Some hosts report ZIPs as application/x-zip-compressed or octet-stream. We validate
            // the package ourselves by safe extraction + manifest/integrity checks after upload.
            $uploaded = wp_handle_upload( $_FILES['package'], array( 'test_form'=>false, 'test_type'=>false ) );
            if ( !empty($uploaded['error']) ) { $this->redirect_error($uploaded['error'], 'import'); }
            $uploaded_path = $uploaded['file'];
            Nine_Backup_Exporter::ensure_dirs();
            $upload_info = wp_upload_dir();
            $protected_tmp = trailingslashit( $upload_info['basedir'] ) . 'nine-backup-manager/tmp';
            if ( ! wp_mkdir_p($protected_tmp) && ! is_dir($protected_tmp) ) { @unlink($uploaded_path); $this->redirect_error('Could not create the protected import workspace.', 'import'); }
            $path = trailingslashit($protected_tmp) . 'browser-import-' . strtolower(wp_generate_password(16,false,false)) . '.zip';
            $moved = @rename( $uploaded_path, $path );
            if ( ! $moved ) { $moved = @copy( $uploaded_path, $path ); if ( $moved ) { @unlink($uploaded_path); } }
            if ( ! $moved || ! is_file($path) ) { @unlink($uploaded_path); $this->redirect_error('The ZIP uploaded, but it could not be moved into the protected Nine import workspace.', 'import'); }
            $importer = new Nine_Backup_Importer( $path );
            $preview = $importer->preview();
            if ( is_wp_error($preview) ) { @unlink($path); $this->redirect_error($preview->get_error_message(), 'import'); }
            set_transient( 'nine_backup_preview_' . get_current_user_id(), array('path'=>$path,'preview'=>$preview), 2 * HOUR_IN_SECONDS );
            Nine_Backup_Health::operation_finish('upload-import-preview');
            wp_safe_redirect( admin_url('admin.php?page=nine-backup-manager&tab=import') ); exit;
        } catch ( Throwable $e ) {
            Nine_Backup_Health::log( 'upload_import', $e->getMessage() );
            $this->redirect_error( 'Import upload failed: ' . $e->getMessage(), 'import' );
        }
    }

    public function handle_server_import() {
        $this->guard(); check_admin_referer( 'nine_backup_server_import' );
        try {
            $input = trim( wp_unslash( $_POST['server_package_path'] ?? '' ) );
            if ( ! $input ) { $this->redirect_error( 'Enter the server path to a 9 Backup ZIP.', 'import' ); }
            $settings = get_option( 'nine_backup_settings', array() );
            $root_setting = trim( (string) ( $settings['server_import_root'] ?? ABSPATH ) );
            $root = realpath( $root_setting );
            if ( ! $root || ! is_dir($root) ) { $this->redirect_error( 'The configured server import root does not exist.', 'import' ); }
            $candidate = $input;
            if ( ! preg_match( '#^(?:[A-Za-z]:[\\/]|/)#', $candidate ) ) { $candidate = trailingslashit($root) . ltrim($candidate,"/\\"); }
            $source = realpath( $candidate );
            $root_norm = rtrim( str_replace('\\','/',$root), '/' ) . '/';
            $source_norm = $source ? str_replace('\\','/',$source) : '';
            if ( ! $source || ! is_file($source) || 0 !== strpos($source_norm, $root_norm) || 'zip' !== strtolower(pathinfo($source,PATHINFO_EXTENSION)) ) {
                $this->redirect_error( 'The selected file is not an allowed ZIP inside the configured server import root.', 'import' );
            }
            $upload = wp_upload_dir();
            $tmp_dir = trailingslashit($upload['basedir']) . 'nine-backup-manager/tmp';
            if ( ! wp_mkdir_p($tmp_dir) && ! is_dir($tmp_dir) ) { $this->redirect_error( 'Could not create the protected import workspace.', 'import' ); }
            $dest = trailingslashit($tmp_dir) . 'server-import-' . wp_generate_password(8,false,false) . '.zip';
            if ( ! @copy($source,$dest) ) { $this->redirect_error( 'Could not copy the server package into the protected import workspace.', 'import' ); }
            $importer = new Nine_Backup_Importer( $dest );
            $preview = $importer->preview();
            if ( is_wp_error($preview) ) { @unlink($dest); $this->redirect_error($preview->get_error_message(), 'import'); }
            set_transient( 'nine_backup_preview_' . get_current_user_id(), array('path'=>$dest,'preview'=>$preview,'server_source'=>$source), 2 * HOUR_IN_SECONDS );
            wp_safe_redirect( admin_url('admin.php?page=nine-backup-manager&tab=import') ); exit;
        } catch ( Throwable $e ) {
            Nine_Backup_Health::log( 'server_import', $e->getMessage() );
            $this->redirect_error( 'Server import failed: ' . $e->getMessage(), 'import' );
        }
    }

    public function handle_run_import() {
        $this->guard(); check_admin_referer( 'nine_backup_run_import' ); @set_time_limit(0);
        if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }
        try {
            Nine_Backup_Health::operation_start('run-import');
            $key = 'nine_backup_preview_' . get_current_user_id();
            $saved = get_transient( $key );
            if ( !$saved || empty($saved['path']) || !file_exists($saved['path']) ) { $this->redirect_error('The import preview expired. Upload the package again.', 'import'); }
            $strategy = sanitize_key($_POST['strategy'] ?? ($saved['choices']['strategy'] ?? 'update'));
            $plugin_strategy = sanitize_key($_POST['plugin_strategy'] ?? ($saved['choices']['plugin_strategy'] ?? 'install_update'));
            $activation_policy = sanitize_key($_POST['activation_policy'] ?? ($saved['choices']['activation_policy'] ?? 'preserve'));
            $strategy = in_array($strategy,array('update','skip','duplicate'),true)?$strategy:'update';
            $plugin_strategy = in_array($plugin_strategy,array('install_update','skip_existing','package_only'),true)?$plugin_strategy:'install_update';
            $activation_policy = in_array($activation_policy,array('preserve','inactive','activate'),true)?$activation_policy:'preserve';
            $saved['choices'] = array('strategy'=>$strategy,'plugin_strategy'=>$plugin_strategy,'activation_policy'=>$activation_policy);

            $has_plugin_bundles = false;
            foreach ( (array) ( $saved['preview']['compatibility']['plugins'] ?? array() ) as $row ) { if ( ! empty($row['has_bundle']) ) { $has_plugin_bundles=true; break; } }
            if ( $has_plugin_bundles && empty($saved['plugins_staged']) && 'package_only' !== $plugin_strategy ) {
                $importer = new Nine_Backup_Importer( $saved['path'] );
                $stage = $importer->stage_plugins( $plugin_strategy, $activation_policy );
                if ( is_wp_error($stage) ) { $this->redirect_error($stage->get_error_message(), 'import'); }
                $saved['plugins_staged'] = true;
                $saved['stage_log'] = (array)($stage['log'] ?? array());
                set_transient( $key, $saved, 2 * HOUR_IN_SECONDS );
                wp_safe_redirect( admin_url('admin.php?page=nine-backup-manager&tab=import&nine_notice=plugins_staged') ); exit;
            }

            $importer = new Nine_Backup_Importer( $saved['path'] );
            $result = $importer->import( $strategy, 'package_only', $activation_policy, false );
            if ( is_wp_error($result) ) { $this->redirect_error($result->get_error_message(), 'import'); }
            if ( ! empty($saved['stage_log']) ) { $result['log'] = array_merge( $saved['stage_log'], (array)($result['log'] ?? array()) ); }
            @unlink($saved['path']); delete_transient( $key );
            set_transient( 'nine_backup_import_result_' . get_current_user_id(), $result, HOUR_IN_SECONDS );
            Nine_Backup_Health::operation_finish('run-import');
            wp_safe_redirect( admin_url('admin.php?page=nine-backup-manager&tab=import&nine_notice=imported') ); exit;
        } catch ( Throwable $e ) {
            Nine_Backup_Health::log( 'run_import', $e->getMessage() );
            $this->redirect_error( 'Import failed: ' . $e->getMessage(), 'import' );
        }
    }

    public function download_archive() {
        $this->guard(); $file = sanitize_file_name(wp_unslash($_POST['file'] ?? '')); check_admin_referer('nine_backup_archive_'.$file);
        $path = trailingslashit(Nine_Backup_Exporter::archive_dir()) . $file;
        if ( !file_exists($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'zip' ) { wp_die('Archive not found.'); }
        $this->stream_protected_zip($path);
    }

    public function delete_archive() {
        $this->guard(); $file=sanitize_file_name(wp_unslash($_GET['file']??'')); check_admin_referer('nine_backup_archive_'.$file); $path=trailingslashit(Nine_Backup_Exporter::archive_dir()).$file;
        if(file_exists($path) && !@unlink($path)){ $this->redirect_error('Could not delete the local archive file.', 'archives'); }
        // Local deletion NEVER removes the independent off-site disaster copy.
        $notice = 'deleted';
        wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab=archives&nine_notice='.$notice)); exit;
    }

    public function handle_host_selftest() {
        $this->guard(); check_admin_referer('nine_backup_host_selftest');
        $tests=array();
        $add=function($name,$pass,$detail='')use(&$tests){$tests[]=array('name'=>$name,'pass'=>(bool)$pass,'detail'=>$detail);};
        Nine_Backup_Exporter::ensure_dirs();
        $upload=wp_upload_dir();
        $tmp=trailingslashit($upload['basedir']).'nine-backup-manager/tmp/selftest-'.wp_generate_password(8,false,false);
        $made=wp_mkdir_p($tmp) || is_dir($tmp); $add('Create protected workspace',$made,$tmp);
        $source=$tmp.'/source.txt'; $write=$made && false!==@file_put_contents($source,"9 Backup Bluehost self-test\n"); $add('Write backup file',$write,$write?'File written successfully':'PHP could not write into the backup workspace');
        $zip=$tmp.'/test.zip'; $zipok=false; if($write){$z=Nine_Backup_Zip::create_named_files(array(array('path'=>$source,'name'=>'source.txt')),$zip);$zipok=!is_wp_error($z)&&is_file($zip)&&filesize($zip)>20;} $add('Create ZIP',$zipok,$zipok?'ZIP created':'ZIP engine could not create an archive');
        $extract=$tmp.'/extract'; wp_mkdir_p($extract); $extractok=false; if($zipok){$z=Nine_Backup_Zip::extract($zip,$extract);$extractok=!is_wp_error($z)&&is_file($extract.'/source.txt');} $add('Extract ZIP',$extractok,$extractok?'ZIP extraction works':'ZIP extraction failed');
        $option_key='nine_backup_selftest_'.wp_generate_password(6,false,false); $dbok=update_option($option_key,'ok',false) || 'ok'===get_option($option_key); $read=get_option($option_key); delete_option($option_key); $add('WordPress database write',$dbok&&'ok'===$read,$dbok?'Options table write/read works':'WordPress could not persist the test option');
        require_once ABSPATH.'wp-admin/includes/file.php'; global $wp_filesystem; $fsok=function_exists('WP_Filesystem')&&WP_Filesystem()&&is_object($wp_filesystem); $add('WordPress Filesystem API',$fsok,$fsok?'Filesystem API initialized':'Filesystem credentials/direct access unavailable');
        $pluginwrite=false; $plugin_detail='Not tested';
        if($fsok && defined('WP_PLUGIN_DIR')){ $probe=trailingslashit(WP_PLUGIN_DIR).'.nine-backup-write-test-'.wp_generate_password(6,false,false).'.tmp'; $pluginwrite=$wp_filesystem->put_contents($probe,'test',defined('FS_CHMOD_FILE')?FS_CHMOD_FILE:false); if($pluginwrite){$wp_filesystem->delete($probe);$plugin_detail='Temporary plugin-directory write succeeded';}else{$plugin_detail='Blocked: WordPress cannot write to wp-content/plugins. On Bluehost use Files & Access → Fix File Ownership.';} }
        $add('Plugin directory write',$pluginwrite,$plugin_detail);
        $adminpost=true; $add('WordPress admin-post request',$adminpost,'This test reached admin-post.php successfully; a server rule did not block this request.');
        if(is_dir($tmp)){
            Nine_Backup_Health::log('host_selftest','Completed host compatibility test',array('results'=>$tests));
            foreach(array($extract.'/source.txt',$zip,$source) as $f){if(is_file($f)){@unlink($f);}}
            if(is_dir($extract)){@rmdir($extract);} @rmdir($tmp);
        }
        set_transient('nine_backup_host_selftest_'.get_current_user_id(),$tests,30*MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab=diagnostics')); exit;
    }

    public function save_settings() {
        $this->guard(); check_admin_referer('nine_backup_save_settings');
        $path = trim(wp_unslash($_POST['drive_sync_path'] ?? ''));
        $retention = min( 1000, absint( $_POST['archive_retention'] ?? 30 ) );
        $server_root = trim( wp_unslash( $_POST['server_import_root'] ?? ABSPATH ) );
        if ( ! $server_root ) { $server_root = ABSPATH; }
        $old = get_option('nine_backup_google_drive', array());
        $google = array(
            'client_id'=>sanitize_text_field(wp_unslash($_POST['google_drive_client_id'] ?? ($old['client_id'] ?? ''))),
            'client_secret'=>trim(wp_unslash($_POST['google_drive_client_secret'] ?? '')),
            'folder_id'=>sanitize_text_field(wp_unslash($_POST['google_drive_folder_id'] ?? ($old['folder_id'] ?? ''))),
            'refresh_token'=>$old['refresh_token'] ?? '', 'access_token'=>$old['access_token'] ?? '', 'expires_at'=>$old['expires_at'] ?? 0,
        );
        if ( '' === $google['client_secret'] ) { $google['client_secret'] = $old['client_secret'] ?? ''; }
        if ( $google['client_id'] !== ($old['client_id'] ?? '') || $google['client_secret'] !== ($old['client_secret'] ?? '') ) { $google['refresh_token']=''; $google['access_token']=''; $google['expires_at']=0; }
        update_option('nine_backup_google_drive', $google, false);
        update_option('nine_backup_settings', array(
            'drive_sync_path'=>$path,
            'drive_group_by_type'=>!empty($_POST['drive_group_by_type']),
            'google_drive_enabled'=>!empty($_POST['google_drive_enabled']) && class_exists('Nine_Backup_Google_Drive') && Nine_Backup_Google_Drive::connected(),
            'archive_retention'=>$retention,
            'server_import_root'=>$server_root,
            'shared_host_safe_mode'=>!empty($_POST['shared_host_safe_mode']),
        ), false);
        wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab=settings&nine_notice=settings')); exit;
    }

    public function google_drive_connect() {
        $this->guard(); check_admin_referer('nine_backup_google_drive_connect');
        if ( ! class_exists('Nine_Backup_Google_Drive') || ! Nine_Backup_Google_Drive::configured() ) { $this->redirect_error('Save a Google OAuth Client ID and Client Secret before connecting.', 'settings'); }
        wp_redirect( Nine_Backup_Google_Drive::authorize_url() ); exit;
    }
    public function google_drive_callback() {
        $this->guard();
        if ( ! empty($_GET['error']) ) { $this->redirect_error('Google Drive connection was cancelled: ' . sanitize_text_field(wp_unslash($_GET['error'])), 'settings'); }
        $result = class_exists('Nine_Backup_Google_Drive') ? Nine_Backup_Google_Drive::finish_authorization( sanitize_text_field(wp_unslash($_GET['code'] ?? '')), sanitize_text_field(wp_unslash($_GET['state'] ?? '')) ) : new WP_Error('nine_drive_missing','Google Drive support is unavailable.');
        if ( is_wp_error($result) ) { $this->redirect_error($result->get_error_message(), 'settings'); }
        $settings=get_option('nine_backup_settings',array()); $settings['google_drive_enabled']=true; update_option('nine_backup_settings',$settings,false);
        wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab=settings&nine_notice=drive_connected')); exit;
    }
    public function google_drive_disconnect() {
        $this->guard(); check_admin_referer('nine_backup_google_drive_disconnect'); if(class_exists('Nine_Backup_Google_Drive')){Nine_Backup_Google_Drive::disconnect();} $settings=get_option('nine_backup_settings',array()); $settings['google_drive_enabled']=false; update_option('nine_backup_settings',$settings,false); wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab=settings&nine_notice=drive_disconnected')); exit;
    }

    private function save_profile_from_config( $config, $name, $schedule ) {
        $profiles = get_option('nine_backup_profiles', array());
        $id = 'profile_' . strtolower(wp_generate_password(8,false,false));
        $schedule = in_array($schedule,array('none','daily','weekly','monthly'),true)?$schedule:'none';
        $config['name'] = $name;
        $config['source'] = 'scheduled-profile';
        $profiles[$id] = array('name'=>$name,'schedule'=>$schedule,'config'=>$config,'last_run'=>0,'next_run'=>$schedule==='none'?0:time()+$this->schedule_interval($schedule));
        update_option('nine_backup_profiles',$profiles,false);
    }

    public function delete_profile() {
        $this->guard(); $id=sanitize_key($_GET['id']??''); check_admin_referer('nine_backup_profile_'.$id); $profiles=get_option('nine_backup_profiles',array()); unset($profiles[$id]); update_option('nine_backup_profiles',$profiles,false); wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab=profiles&nine_notice=profile_deleted')); exit;
    }

    public function run_profile_now() {
        $this->guard(); $id=sanitize_key($_GET['id']??''); check_admin_referer('nine_backup_profile_'.$id); $profiles=get_option('nine_backup_profiles',array()); if(empty($profiles[$id])){$this->redirect_error('Profile not found.','profiles');} $res=$this->execute_profile($id,$profiles[$id]); if(is_wp_error($res)){$this->redirect_error($res->get_error_message(),'profiles');} wp_safe_redirect(admin_url('admin.php?page=nine-backup-manager&tab=profiles&nine_notice=profile_run')); exit;
    }

    public function run_scheduled_profiles() {
        $profiles=get_option('nine_backup_profiles',array()); $changed=false; $now=time();
        foreach($profiles as $id=>$profile){ if(empty($profile['schedule'])||$profile['schedule']==='none')continue; if(!empty($profile['next_run']) && $profile['next_run']>$now)continue; $result=$this->execute_profile($id,$profile,false); $profiles=get_option('nine_backup_profiles',array()); $changed=true; }
        if($changed){ update_option('nine_backup_profiles',$profiles,false); }
    }

    private function execute_profile( $id, $profile, $persist=true ) {
        @set_time_limit(0); $config=$profile['config']; $config['name']=$profile['name']; $config['source']='scheduled-profile'; $exporter=new Nine_Backup_Exporter($config); $result=$exporter->create_package();
        if(is_wp_error($result))return $result;
        $profiles=get_option('nine_backup_profiles',array()); if(isset($profiles[$id])){ $profiles[$id]['last_run']=time(); $profiles[$id]['next_run']=$profiles[$id]['schedule']==='none'?0:time()+$this->schedule_interval($profiles[$id]['schedule']); update_option('nine_backup_profiles',$profiles,false); }
        return $result;
    }

    private function schedule_interval($schedule){ if($schedule==='weekly')return WEEK_IN_SECONDS; if($schedule==='monthly')return 30*DAY_IN_SECONDS; return DAY_IN_SECONDS; }

    private function archive_files() { Nine_Backup_Exporter::ensure_dirs(); $files=glob(trailingslashit(Nine_Backup_Exporter::archive_dir()).'*.zip')?:array(); usort($files,function($a,$b){return filemtime($b)<=>filemtime($a);}); return $files; }

    private function redirect_error( $message, $tab='export' ) { Nine_Backup_Health::operation_fail($message); Nine_Backup_Health::log( 'ui_error', $message, array('tab'=>$tab) ); wp_safe_redirect( add_query_arg(array('page'=>'nine-backup-manager','tab'=>$tab,'nine_notice'=>'error','message'=>$message), admin_url('admin.php')) ); exit; }
}
