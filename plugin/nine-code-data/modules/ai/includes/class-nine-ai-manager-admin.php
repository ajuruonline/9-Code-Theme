<?php
if (!defined('ABSPATH')) { exit; }

class Nine_AI_Manager_Admin {
    private $manifest;
    private $importer;
    private $history;
    private $scanner;
    private $plugin_updater;

    public function __construct($manifest, $importer, $history, $scanner, $plugin_updater) {
        $this->manifest = $manifest;
        $this->importer = $importer;
        $this->history = $history;
        $this->scanner = $scanner;
        $this->plugin_updater = $plugin_updater;

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_post_nine_ai_stage', array($this, 'handle_stage'));
        add_action('admin_post_nine_ai_apply', array($this, 'handle_apply'));
        add_action('admin_post_nine_ai_refresh_scan', array($this, 'handle_refresh_scan'));
        add_action('admin_post_nine_ai_download_manifest', array($this, 'download_manifest'));
        add_action('admin_post_nine_ai_download_kit', array($this, 'download_ai_kit'));
        add_action('admin_post_nine_ai_download_example', array($this, 'download_example'));
        add_action('admin_post_nine_ai_build_plugin_pack', array($this, 'download_plugin_work_pack'));
        add_action('admin_post_nine_ai_stage_plugin_update', array($this, 'handle_stage_plugin_update'));
        add_action('admin_post_nine_ai_apply_plugin_update', array($this, 'handle_apply_plugin_update'));
        add_action('admin_post_nine_ai_rollback', array($this, 'handle_rollback'));
        add_action('admin_post_nine_ai_save_settings', array($this, 'save_settings'));
        add_action('admin_notices', array($this, 'notices'));
    }

    public function admin_menu() {
        add_menu_page(__('9 AI Manager', 'nine-code-data' ), __('9 AI Manager', 'nine-code-data' ), 'manage_options', 'nine-ai-manager', array($this,'render_dashboard'), 'dashicons-superhero-alt', 3);
        add_submenu_page('nine-ai-manager', __('AI Dashboard', 'nine-code-data' ), __('AI Dashboard', 'nine-code-data' ), 'manage_options', 'nine-ai-manager', array($this,'render_dashboard'));
        add_submenu_page('nine-ai-manager', __('AI → Anything', 'nine-code-data' ), __('AI → Anything', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-import', array($this,'render_import'));
        add_submenu_page('nine-ai-manager', __('Post Creator', 'nine-code-data' ), __('Post Creator', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-post', array($this,'render_import'));
        add_submenu_page('nine-ai-manager', __('AI → Page', 'nine-code-data' ), __('AI → Page', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-page', array($this,'render_import'));
        add_submenu_page('nine-ai-manager', __('AI → Category', 'nine-code-data' ), __('AI → Category', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-category', array($this,'render_import'));
        add_submenu_page('nine-ai-manager', __('AI → Landing Page', 'nine-code-data' ), __('AI → Landing Page', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-landing', array($this,'render_import'));
        add_submenu_page('nine-ai-manager', __('AI → Site / Plugin Settings', 'nine-code-data' ), __('AI → Site / Plugin Settings', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-settings-import', array($this,'render_import'));
        add_submenu_page('nine-ai-manager', __('AI → User', 'nine-code-data' ), __('AI → User', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-user', array($this,'render_import'));
        add_submenu_page('nine-ai-manager', __('AI → Plugin Update', 'nine-code-data' ), __('AI → Plugin Update', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-plugin-update', array($this,'render_plugin_update'));
        add_submenu_page('nine-ai-manager', __('Site AI File', 'nine-code-data' ), __('Site AI File', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-manifest', array($this,'render_manifest'));
        add_submenu_page('nine-ai-manager', __('Plugin Integration', 'nine-code-data' ), __('Plugin Integration', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-integrations', array($this,'render_integrations'));
        add_submenu_page('nine-ai-manager', __('AI History', 'nine-code-data' ), __('AI History', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-history', array($this,'render_history'));
        add_submenu_page('nine-ai-manager', __('Settings', 'nine-code-data' ), __('Settings', 'nine-code-data' ), 'manage_options', 'nine-ai-manager-settings', array($this,'render_settings'));
    }

    public function enqueue_assets($hook) {
        if (false === strpos($hook, 'nine-ai-manager')) { return; }
        wp_enqueue_style('nine-ai-manager-admin', NINE_AI_MANAGER_URL . 'assets/admin.css', array(), NINE_AI_MANAGER_VERSION);
        wp_enqueue_script('nine-ai-manager-admin', NINE_AI_MANAGER_URL . 'assets/admin.js', array(), NINE_AI_MANAGER_VERSION, true);
    }

    private function require_admin() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to use 9 AI Manager.', 'nine-code-data' )); }
    }

    public function notices() {
        if (empty($_GET['nine_ai_notice'])) { return; }
        $message = sanitize_text_field(wp_unslash($_GET['nine_ai_notice']));
        $type = !empty($_GET['nine_ai_type']) ? sanitize_key($_GET['nine_ai_type']) : 'success';
        if (!in_array($type, array('success','warning','error','info'), true)) { $type='info'; }
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    public function render_dashboard() {
        $this->require_admin();
        $scan = $this->scanner->get_last_scan(true);
        $manifest = $this->manifest->get_manifest(false);
        $summary = isset($scan['summary']) ? $scan['summary'] : array();
        $history = $this->history->all();
        ?>
        <div class="wrap nine-ai-wrap">
            <div class="nine-ai-hero">
                <div><h1>9 AI Manager</h1><p><?php esc_html_e('The visible communication layer between your AI workspace and WordPress. The site explains itself to AI; AI returns a file; you preview and approve the change.', 'nine-code-data' ); ?></p></div>
                <div class="nine-ai-hero-actions">
                    <a class="button button-primary button-hero" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nine_ai_download_kit'),'nine_ai_download_kit')); ?>"><?php esc_html_e('Check Updates + Download AI File', 'nine-code-data' ); ?></a>
                    <a class="button button-hero" href="<?php echo esc_url(admin_url('admin.php?page=nine-ai-manager-import')); ?>"><?php esc_html_e('Upload AI Package', 'nine-code-data' ); ?></a>
                </div>
            </div>
            <div class="nine-ai-grid nine-ai-grid-4">
                <?php $this->dashboard_card('Post Creator','Posts/CPTs, Gutenberg, ACF, taxonomy, meta and media.','nine-ai-manager-post'); ?>
                <?php $this->dashboard_card('AI → Page','Pages, hierarchy, fields and content.','nine-ai-manager-page'); ?>
                <?php $this->dashboard_card('AI → Category','Categories, tags and custom taxonomies.','nine-ai-manager-category'); ?>
                <?php $this->dashboard_card('AI → Plugin Update','Give AI the current plugin source, then preview its returned ZIP.','nine-ai-manager-plugin-update'); ?>
            </div>
            <div class="nine-ai-grid nine-ai-grid-3">
                <div class="nine-ai-card"><h2><?php esc_html_e('AI readiness', 'nine-code-data' ); ?></h2><div class="nine-ai-big-number"><?php echo esc_html(isset($summary['plugins_installed']) ? $summary['plugins_installed'] : 0); ?></div><p><?php echo esc_html(sprintf('%d source-native · %d universal-bridge · %d automatic', isset($summary['native_ai_integrations'])?$summary['native_ai_integrations']:0, isset($summary['bridge_ai_integrations'])?$summary['bridge_ai_integrations']:0, isset($summary['automatic_ai_integrations'])?$summary['automatic_ai_integrations']:0)); ?></p></div>
                <div class="nine-ai-card"><h2><?php esc_html_e('Updates visible', 'nine-code-data' ); ?></h2><div class="nine-ai-big-number"><?php echo esc_html(isset($summary['plugin_updates_available']) ? $summary['plugin_updates_available'] : 0); ?></div><p><?php esc_html_e('Every AI-file download performs a fresh WordPress plugin/theme update check before the contract is generated.', 'nine-code-data' ); ?></p></div>
                <div class="nine-ai-card"><h2><?php esc_html_e('Human control', 'nine-code-data' ); ?></h2><div class="nine-ai-big-number"><?php echo esc_html(count($history)); ?></div><p><?php esc_html_e('Recorded AI transactions. Content defaults to draft; plugin code is backed up before replacement.', 'nine-code-data' ); ?></p></div>
            </div>
            <div class="nine-ai-card nine-ai-workflow"><h2><?php esc_html_e('One workflow for the entire website', 'nine-code-data' ); ?></h2><ol>
                <li><strong>1.</strong><?php esc_html_e(' Click Check Updates + Download AI File. 9 AI Manager refreshes update information and inventories the live site.', 'nine-code-data' ); ?></li>
                <li><strong>2.</strong><?php esc_html_e(' Give that file to ChatGPT/Gemini with the human instruction and source material.', 'nine-code-data' ); ?></li>
                <li><strong>3.</strong><?php esc_html_e(' For site/content work, AI returns nine-ai-package/v2. For plugin code work, download a Plugin AI Work Pack and AI returns an installable plugin ZIP.', 'nine-code-data' ); ?></li>
                <li><strong>4.</strong><?php esc_html_e(' Upload the result to the relevant AI → screen. Review the preview.', 'nine-code-data' ); ?></li>
                <li><strong>5.</strong><?php esc_html_e(' Apply only when satisfied. History and supported rollback information are retained.', 'nine-code-data' ); ?></li>
            </ol></div>
            <details class="nine-ai-details"><summary><?php esc_html_e('Current contract identity', 'nine-code-data' ); ?></summary><pre><?php echo esc_html(wp_json_encode(array('site_fingerprint'=>$manifest['site_fingerprint'],'manifest_hash'=>$manifest['manifest_hash'],'scan_id'=>$manifest['scan_id']), JSON_PRETTY_PRINT)); ?></pre></details>
        </div><?php
    }

    private function dashboard_card($title,$text,$page) { ?>
        <a class="nine-ai-card nine-ai-action-card" href="<?php echo esc_url(admin_url('admin.php?page='.$page)); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span><h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($text); ?></p></a><?php
    }

    public function render_import() {
        $this->require_admin();
        $page = !empty($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'nine-ai-manager-import';
        $route = $this->route_from_page($page);
        $stage_token = !empty($_GET['stage']) ? sanitize_text_field(wp_unslash($_GET['stage'])) : '';
        $staged = $stage_token ? get_transient($this->transient_key($stage_token)) : false;
        ?>
        <div class="wrap nine-ai-wrap"><div class="nine-ai-hero"><div><h1><?php echo esc_html($this->route_label($route)); ?></h1><p><?php echo esc_html($this->route_description($route)); ?></p></div><div class="nine-ai-hero-actions"><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nine_ai_download_kit'),'nine_ai_download_kit')); ?>"><?php esc_html_e('Download Fresh AI File', 'nine-code-data' ); ?></a><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nine_ai_download_example'),'nine_ai_download_example')); ?>"><?php esc_html_e('Example Package', 'nine-code-data' ); ?></a></div></div>
        <?php if ($staged && !empty($staged['user_id']) && (int)$staged['user_id']===get_current_user_id()): ?>
            <?php $this->render_package_preview($staged,$stage_token,$route); ?>
        <?php else: ?>
            <div class="nine-ai-card"><h2><?php esc_html_e('Upload AI result', 'nine-code-data' ); ?></h2><p class="nine-ai-lead"><?php esc_html_e('Accepted: .9ai.json, .json or ZIP containing package.json and optional assets/. Uploading only stages the work; it does not change WordPress.', 'nine-code-data' ); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="nine_ai_stage"><input type="hidden" name="route" value="<?php echo esc_attr($route); ?>"><?php wp_nonce_field('nine_ai_stage_'.$route); ?>
                    <input type="file" name="nine_ai_package" accept=".json,.zip,.9ai.json,application/json,application/zip" required>
                    <button type="submit" class="button button-primary button-hero"><?php esc_html_e('Upload & Preview', 'nine-code-data' ); ?></button>
                </form>
            </div>
        <?php endif; ?>
        </div><?php
    }

    private function render_package_preview($staged,$token,$route) {
        $package = isset($staged['package']) ? $staged['package'] : array();
        $preview = isset($staged['preview']) ? $staged['preview'] : array();
        ?>
        <div class="nine-ai-card"><div class="nine-ai-preview-head"><div><h2><?php esc_html_e('Preview before WordPress changes', 'nine-code-data' ); ?></h2><p><strong><?php echo esc_html(isset($package['package_name'])?$package['package_name']:$staged['source_name']); ?></strong></p><p><?php echo esc_html(isset($package['notes'])?$package['notes']:''); ?></p></div><span class="nine-ai-badge"><?php echo esc_html(count($preview).' item(s)'); ?></span></div>
            <div class="nine-ai-table-wrap"><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e('Entity', 'nine-code-data' ); ?></th><th><?php esc_html_e('Operation', 'nine-code-data' ); ?></th><th><?php esc_html_e('Target', 'nine-code-data' ); ?></th><th><?php esc_html_e('Label', 'nine-code-data' ); ?></th></tr></thead><tbody>
            <?php foreach($preview as $row): ?><tr><td><?php echo esc_html($row['number']); ?></td><td><?php echo esc_html($row['entity']); ?></td><td><?php echo esc_html($row['operation']); ?></td><td><?php echo esc_html($row['target']); ?></td><td><?php echo esc_html($row['label']); ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <details class="nine-ai-details"><summary><?php esc_html_e('Inspect raw AI package', 'nine-code-data' ); ?></summary><pre><?php echo esc_html(wp_json_encode($package, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); ?></pre></details>
            <div class="nine-ai-approval"><h3><?php esc_html_e('Human approval required', 'nine-code-data' ); ?></h3><p><?php esc_html_e('Apply only after the preview matches the instruction you gave the AI.', 'nine-code-data' ); ?></p><div class="nine-ai-button-row">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nine_ai_apply"><input type="hidden" name="stage" value="<?php echo esc_attr($token); ?>"><input type="hidden" name="route" value="<?php echo esc_attr($route); ?>"><?php wp_nonce_field('nine_ai_apply_'.$token); ?><button class="button button-primary button-hero" type="submit"><?php esc_html_e('Apply Package to WordPress', 'nine-code-data' ); ?></button></form>
                <a class="button button-hero" href="<?php echo esc_url(admin_url('admin.php?page='.$this->page_from_route($route))); ?>"><?php esc_html_e('Cancel Preview', 'nine-code-data' ); ?></a>
            </div></div>
        </div><?php
    }

    public function render_manifest() {
        $this->require_admin();
        $scan = $this->scanner->get_last_scan(true);
        $manifest = $this->manifest->get_manifest(false);
        $s = isset($scan['summary'])?$scan['summary']:array();
        ?>
        <div class="wrap nine-ai-wrap"><div class="nine-ai-hero"><div><h1><?php esc_html_e('Site AI File', 'nine-code-data' ); ?></h1><p><?php esc_html_e('This is the current machine-readable description of your WordPress site. The preferred download performs a fresh update check first, then rebuilds the file from the live installation.', 'nine-code-data' ); ?></p></div><div class="nine-ai-hero-actions"><a class="button button-primary button-hero" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nine_ai_download_kit'),'nine_ai_download_kit')); ?>"><?php esc_html_e('Check Updates + Download AI Instruction File', 'nine-code-data' ); ?></a><a class="button button-hero" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nine_ai_refresh_scan'),'nine_ai_refresh_scan')); ?>"><?php esc_html_e('Refresh Scan Only', 'nine-code-data' ); ?></a></div></div>
            <div class="nine-ai-grid nine-ai-grid-4">
                <div class="nine-ai-card"><h2><?php esc_html_e('Installed plugins', 'nine-code-data' ); ?></h2><div class="nine-ai-big-number"><?php echo esc_html(isset($s['plugins_installed'])?$s['plugins_installed']:0); ?></div></div>
                <div class="nine-ai-card"><h2><?php esc_html_e('9 ecosystem', 'nine-code-data' ); ?></h2><div class="nine-ai-big-number"><?php echo esc_html(isset($s['nine_plugins_detected'])?$s['nine_plugins_detected']:0); ?></div></div>
                <div class="nine-ai-card"><h2><?php esc_html_e('Native AI', 'nine-code-data' ); ?></h2><div class="nine-ai-big-number"><?php echo esc_html(isset($s['native_ai_integrations'])?$s['native_ai_integrations']:0); ?></div></div>
                <div class="nine-ai-card"><h2><?php esc_html_e('Bridge / automatic AI', 'nine-code-data' ); ?></h2><div class="nine-ai-big-number"><?php echo esc_html((isset($s['bridge_ai_integrations'])?$s['bridge_ai_integrations']:0)+(isset($s['automatic_ai_integrations'])?$s['automatic_ai_integrations']:0)); ?></div></div>
            </div>
            <div class="nine-ai-card"><h2><?php esc_html_e('Why this file remains current', 'nine-code-data' ); ?></h2><p><?php esc_html_e('Native-ready plugins declare their exact 9 AI contract. Older plugins are still inventoried automatically from WordPress registrations and bounded source discovery, including post types, taxonomies, meta/settings keys, shortcodes, blocks, REST namespaces and Elementor classes.', 'nine-code-data' ); ?></p><p><strong><?php esc_html_e('Site fingerprint:', 'nine-code-data' ); ?></strong> <code><?php echo esc_html($manifest['site_fingerprint']); ?></code></p><p><strong><?php esc_html_e('Manifest hash:', 'nine-code-data' ); ?></strong> <code><?php echo esc_html($manifest['manifest_hash']); ?></code></p><p><strong><?php esc_html_e('Last scan:', 'nine-code-data' ); ?></strong> <?php echo esc_html(isset($scan['generated_at'])?$scan['generated_at']:''); ?></p><div class="nine-ai-button-row"><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nine_ai_download_manifest'),'nine_ai_download_manifest')); ?>"><?php esc_html_e('Download Raw Manifest JSON', 'nine-code-data' ); ?></a><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-ai-manager-integrations')); ?>"><?php esc_html_e('Inspect Plugin Integrations', 'nine-code-data' ); ?></a></div></div>
            <details class="nine-ai-details"><summary><?php esc_html_e('Preview current manifest', 'nine-code-data' ); ?></summary><pre><?php echo esc_html(wp_json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); ?></pre></details>
        </div><?php
    }

    public function render_integrations() {
        $this->require_admin();
        $scan=$this->scanner->get_last_scan(true);
        ?>
        <div class="wrap nine-ai-wrap"><div class="nine-ai-hero"><div><h1><?php esc_html_e('Plugin Integration', 'nine-code-data' ); ?></h1><p><?php esc_html_e('Every installed plugin is communicable. Native means the plugin explicitly declares a 9 AI contract; Automatic means 9 AI Manager derives a bounded contract from the installed plugin until its next native-ready release.', 'nine-code-data' ); ?></p></div><div class="nine-ai-hero-actions"><a class="button button-primary button-hero" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nine_ai_refresh_scan'),'nine_ai_refresh_scan')); ?>"><?php esc_html_e('Refresh Integrations', 'nine-code-data' ); ?></a></div></div>
        <div class="nine-ai-card"><div class="nine-ai-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Plugin', 'nine-code-data' ); ?></th><th><?php esc_html_e('Version', 'nine-code-data' ); ?></th><th><?php esc_html_e('State', 'nine-code-data' ); ?></th><th><?php esc_html_e('AI mode', 'nine-code-data' ); ?></th><th><?php esc_html_e('Detected capabilities', 'nine-code-data' ); ?></th><th><?php esc_html_e('Plugin AI work pack', 'nine-code-data' ); ?></th></tr></thead><tbody>
        <?php foreach((array)($scan['plugins']??array()) as $p): $mode=isset($p['ai_integration']['mode'])?$p['ai_integration']['mode']:'automatic'; $det=(array)($p['detected']??array()); $caps=array(); foreach(array('post_types','taxonomies','meta_keys','option_keys','shortcodes','blocks','rest_namespaces','elementor_widgets') as $k){ if(!empty($det[$k])){$caps[]=$k.':'.count((array)$det[$k]);}} ?>
            <tr><td><strong><?php echo esc_html($p['name']); ?></strong><br><code><?php echo esc_html($p['file']); ?></code></td><td><?php echo esc_html($p['version']); ?><?php if(!empty($p['update']['available'])):?><br><span class="nine-ai-status-update"><?php echo esc_html('Update → '.$p['update']['new_version']); ?></span><?php endif;?></td><td><?php echo !empty($p['active'])?esc_html__('Active', 'nine-code-data' ):esc_html__('Inactive', 'nine-code-data' ); ?></td><td><span class="nine-ai-badge nine-ai-mode-<?php echo esc_attr($mode); ?>"><?php echo esc_html(ucfirst($mode)); ?></span></td><td><?php echo esc_html($caps?implode(' · ',$caps):'WordPress/plugin inventory'); ?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nine_ai_build_plugin_pack"><input type="hidden" name="plugin_file" value="<?php echo esc_attr($p['file']); ?>"><?php wp_nonce_field('nine_ai_build_plugin_pack_'.$p['file']); ?><button class="button" type="submit"><?php esc_html_e('Download for AI', 'nine-code-data' ); ?></button></form></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div></div><?php
    }

    public function render_plugin_update() {
        $this->require_admin();
        if(!function_exists('get_plugins')){require_once ABSPATH.'wp-admin/includes/plugin.php';}
        $plugins=get_plugins();
        $token=!empty($_GET['plugin_stage'])?sanitize_text_field(wp_unslash($_GET['plugin_stage'])):'';
        $staged=$token?get_transient($this->plugin_transient_key($token)):false;
        ?>
        <div class="wrap nine-ai-wrap"><div class="nine-ai-hero"><div><h1><?php esc_html_e('AI → Plugin Update', 'nine-code-data' ); ?></h1><p><?php esc_html_e('For code changes, first download the current plugin as an AI Work Pack. Give that ZIP plus your instruction to AI. AI returns an installable plugin ZIP. This screen verifies the target path, previews the version change, backs up the installed plugin and applies only after approval.', 'nine-code-data' ); ?></p></div><div class="nine-ai-hero-actions"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nine-ai-manager-integrations')); ?>"><?php esc_html_e('Download Plugin AI Work Pack', 'nine-code-data' ); ?></a></div></div>
        <?php if($staged && !empty($staged['user_id']) && (int)$staged['user_id']===get_current_user_id()): ?>
            <div class="nine-ai-card"><h2><?php esc_html_e('Plugin update preview', 'nine-code-data' ); ?></h2><div class="nine-ai-grid nine-ai-grid-3"><div><strong><?php esc_html_e('Installed', 'nine-code-data' ); ?></strong><p><?php echo esc_html($staged['installed']['name'].' '.$staged['installed']['version']); ?></p></div><div><strong><?php esc_html_e('AI ZIP', 'nine-code-data' ); ?></strong><p><?php echo esc_html($staged['incoming']['name'].' '.$staged['incoming']['version']); ?></p></div><div><strong><?php esc_html_e('Path', 'nine-code-data' ); ?></strong><p><code><?php echo esc_html($staged['target_plugin']); ?></code></p></div></div><div class="nine-ai-approval"><h3><?php esc_html_e('Backup + explicit approval', 'nine-code-data' ); ?></h3><p><?php esc_html_e('9 AI Manager will create a rollback ZIP of the installed plugin before WordPress replaces it. The incoming ZIP must keep the same plugin folder/main file.', 'nine-code-data' ); ?></p><div class="nine-ai-button-row"><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nine_ai_apply_plugin_update"><input type="hidden" name="plugin_stage" value="<?php echo esc_attr($token); ?>"><?php wp_nonce_field('nine_ai_apply_plugin_update_'.$token); ?><button class="button button-primary button-hero" type="submit"><?php esc_html_e('Apply AI Plugin Update', 'nine-code-data' ); ?></button></form><a class="button button-hero" href="<?php echo esc_url(admin_url('admin.php?page=nine-ai-manager-plugin-update')); ?>"><?php esc_html_e('Cancel', 'nine-code-data' ); ?></a></div></div></div>
        <?php else: ?>
            <div class="nine-ai-card"><h2><?php esc_html_e('Upload AI-updated plugin ZIP', 'nine-code-data' ); ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data"><input type="hidden" name="action" value="nine_ai_stage_plugin_update"><?php wp_nonce_field('nine_ai_stage_plugin_update'); ?><div class="nine-ai-field"><label for="nine_ai_plugin_target"><strong><?php esc_html_e('Installed plugin being updated', 'nine-code-data' ); ?></strong></label><select id="nine_ai_plugin_target" name="target_plugin" required><option value=""><?php esc_html_e('Select plugin…', 'nine-code-data' ); ?></option><?php foreach($plugins as $file=>$h):?><option value="<?php echo esc_attr($file); ?>"><?php echo esc_html($h['Name'].' — '.$h['Version']); ?></option><?php endforeach;?></select></div><input type="file" name="nine_ai_plugin_zip" accept=".zip,application/zip" required><button class="button button-primary button-hero" type="submit"><?php esc_html_e('Upload & Verify Plugin ZIP', 'nine-code-data' ); ?></button></form></div>
        <?php endif; ?>
        </div><?php
    }

    public function render_history() {
        $this->require_admin(); $items=$this->history->all(); ?>
        <div class="wrap nine-ai-wrap"><div class="nine-ai-hero"><div><h1><?php esc_html_e('AI History', 'nine-code-data' ); ?></h1><p><?php esc_html_e('Visible record of content/site packages and AI plugin updates applied through this manager.', 'nine-code-data' ); ?></p></div></div><div class="nine-ai-history-list">
        <?php if(!$items):?><div class="nine-ai-card"><p><?php esc_html_e('No AI packages have been applied yet.', 'nine-code-data' ); ?></p></div><?php endif;?>
        <?php foreach($items as $entry): $summary=(array)($entry['summary']??array()); ?><details class="nine-ai-history-item"><summary><strong><?php echo esc_html($entry['package_name']); ?></strong><span><?php echo esc_html($entry['created_at']); ?></span><span class="nine-ai-badge"><?php echo esc_html($entry['status']); ?></span></summary><div class="nine-ai-history-body"><p><?php echo esc_html(sprintf('%d applied · %d errors · route %s',isset($summary['applied'])?$summary['applied']:0,isset($summary['errors'])?$summary['errors']:0,isset($summary['route'])?$summary['route']:'all')); ?></p><?php if(!empty($entry['errors'])):?><div class="nine-ai-error-box"><pre><?php echo esc_html(wp_json_encode($entry['errors'],JSON_PRETTY_PRINT)); ?></pre></div><?php endif;?><details class="nine-ai-details"><summary><?php esc_html_e('Results / rollback record', 'nine-code-data' ); ?></summary><pre><?php echo esc_html(wp_json_encode(array('results'=>$entry['results']??array(),'rollback'=>$entry['rollback']??array()),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); ?></pre></details><?php if('rolled_back'!==$entry['status'] && !empty($entry['rollback'])):?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px"><input type="hidden" name="action" value="nine_ai_rollback"><input type="hidden" name="history_id" value="<?php echo esc_attr($entry['id']); ?>"><?php wp_nonce_field('nine_ai_rollback_'.$entry['id']); ?><button class="button" type="submit"><?php esc_html_e('Rollback Supported Changes', 'nine-code-data' ); ?></button></form><?php endif;?></div></details><?php endforeach;?>
        </div></div><?php
    }

    public function render_settings() {
        $this->require_admin(); $s=get_option('nine_ai_manager_settings',array()); ?>
        <div class="wrap nine-ai-wrap"><div class="nine-ai-hero"><div><h1><?php esc_html_e('9 AI Manager Settings', 'nine-code-data' ); ?></h1><p><?php esc_html_e('Conservative defaults keep AI work visible and reversible.', 'nine-code-data' ); ?></p></div></div><div class="nine-ai-card nine-ai-settings"><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nine_ai_save_settings"><?php wp_nonce_field('nine_ai_save_settings'); ?>
        <div class="nine-ai-field"><label><input type="checkbox" name="allow_menu_changes" value="1" <?php checked(!empty($s['allow_menu_changes'])); ?>> <strong><?php esc_html_e('Allow AI packages to create/update menus', 'nine-code-data' ); ?></strong></label></div>
        <div class="nine-ai-field"><label><input type="checkbox" name="allow_options" value="1" <?php checked(!empty($s['allow_options'])); ?>> <strong><?php esc_html_e('Allow whitelisted site/plugin settings changes', 'nine-code-data' ); ?></strong></label><small><?php esc_html_e('OFF by default. Plugin settings are restricted to option keys discovered for the selected plugin or explicitly allowed by a native integration.', 'nine-code-data' ); ?></small></div>
        <div class="nine-ai-field"><label><input type="checkbox" name="allow_users" value="1" <?php checked(!empty($s['allow_users'])); ?>> <strong><?php esc_html_e('Allow AI packages to create/update users', 'nine-code-data' ); ?></strong></label><small><?php esc_html_e('OFF by default. Password values supplied by AI are ignored.', 'nine-code-data' ); ?></small></div>
        <div class="nine-ai-field"><label for="default_post_status"><strong><?php esc_html_e('Default content status', 'nine-code-data' ); ?></strong></label><select id="default_post_status" name="default_post_status"><?php foreach(array('draft'=>'Draft','pending'=>'Pending','publish'=>'Publish','private'=>'Private') as $v=>$l):?><option value="<?php echo esc_attr($v); ?>" <?php selected(isset($s['default_post_status'])?$s['default_post_status']:'draft',$v); ?>><?php echo esc_html($l); ?></option><?php endforeach;?></select></div>
        <div class="nine-ai-field"><label><strong><?php esc_html_e('History records', 'nine-code-data' ); ?></strong></label><input type="number" name="history_limit" min="10" max="200" value="<?php echo esc_attr(isset($s['history_limit'])?$s['history_limit']:75); ?>"></div>
        <div class="nine-ai-field"><label><strong><?php esc_html_e('Maximum site package MB', 'nine-code-data' ); ?></strong></label><input type="number" name="max_package_mb" min="1" max="100" value="<?php echo esc_attr(isset($s['max_package_mb'])?$s['max_package_mb']:25); ?>"></div>
        <div class="nine-ai-field"><label><strong><?php esc_html_e('Maximum AI plugin ZIP MB', 'nine-code-data' ); ?></strong></label><input type="number" name="max_plugin_mb" min="1" max="200" value="<?php echo esc_attr(isset($s['max_plugin_mb'])?$s['max_plugin_mb']:40); ?>"></div>
        <button class="button button-primary button-hero" type="submit"><?php esc_html_e('Save Settings', 'nine-code-data' ); ?></button></form></div></div><?php
    }

    public function handle_stage() {
        $this->require_admin(); $route=!empty($_POST['route'])?sanitize_key(wp_unslash($_POST['route'])):'all'; check_admin_referer('nine_ai_stage_'.$route);
        if(empty($_FILES['nine_ai_package'])){$this->redirect_route($route,'',__('Choose an AI package file.', 'nine-code-data' ),'error');}
        $staged=$this->importer->stage_upload($_FILES['nine_ai_package']);
        if(is_wp_error($staged)){$this->redirect_route($route,'',$staged->get_error_message(),'error');}
        $token=wp_generate_password(24,false,false); $staged['user_id']=get_current_user_id(); set_transient($this->transient_key($token),$staged,HOUR_IN_SECONDS);
        $this->redirect_route($route,$token,__('Package staged. Review the preview before applying.', 'nine-code-data' ),'info');
    }

    public function handle_apply() {
        $this->require_admin(); $token=!empty($_POST['stage'])?sanitize_text_field(wp_unslash($_POST['stage'])):''; $route=!empty($_POST['route'])?sanitize_key(wp_unslash($_POST['route'])):'all'; check_admin_referer('nine_ai_apply_'.$token);
        $key=$this->transient_key($token); $staged=get_transient($key); if(!$staged||empty($staged['user_id'])||(int)$staged['user_id']!==get_current_user_id()){$this->redirect_route($route,'',__('The preview session expired. Upload the AI package again.', 'nine-code-data' ),'error');}
        $result=$this->importer->apply_package($staged,'all'===$route?'':$route); if(is_wp_error($result)){$this->redirect_route($route,$token,$result->get_error_message(),'error');}
        delete_transient($key); $message=sprintf(__('AI package applied: %d successful item(s), %d error(s).', 'nine-code-data' ),count($result['results']),count($result['errors'])); wp_safe_redirect(add_query_arg(array('page'=>'nine-ai-manager-history','nine_ai_notice'=>$message,'nine_ai_type'=>count($result['errors'])?'warning':'success'),admin_url('admin.php'))); exit;
    }

    public function handle_refresh_scan() {
        $this->require_admin(); check_admin_referer('nine_ai_refresh_scan'); $scan=$this->scanner->scan(true); $message=sprintf(__('Fresh site scan completed: %d installed plugins, %d update(s) visible, %d native AI integration(s).', 'nine-code-data' ),$scan['summary']['plugins_installed'],$scan['summary']['plugin_updates_available'],$scan['summary']['native_ai_integrations']); wp_safe_redirect(add_query_arg(array('page'=>'nine-ai-manager-manifest','nine_ai_notice'=>$message,'nine_ai_type'=>'success'),admin_url('admin.php'))); exit;
    }

    public function handle_stage_plugin_update() {
        $this->require_admin(); check_admin_referer('nine_ai_stage_plugin_update'); $target=!empty($_POST['target_plugin'])?wp_unslash($_POST['target_plugin']):''; if(empty($_FILES['nine_ai_plugin_zip'])){$this->redirect_plugin_update('',__('Choose the plugin ZIP returned by AI.', 'nine-code-data' ),'error');}
        $staged=$this->plugin_updater->stage_upload($_FILES['nine_ai_plugin_zip'],$target); if(is_wp_error($staged)){$this->redirect_plugin_update('',$staged->get_error_message(),'error');}
        $token=wp_generate_password(24,false,false); $staged['user_id']=get_current_user_id(); set_transient($this->plugin_transient_key($token),$staged,HOUR_IN_SECONDS); $this->redirect_plugin_update($token,__('Plugin ZIP verified. Review the version/path before applying.', 'nine-code-data' ),'info');
    }

    public function handle_apply_plugin_update() {
        $this->require_admin(); $token=!empty($_POST['plugin_stage'])?sanitize_text_field(wp_unslash($_POST['plugin_stage'])):''; check_admin_referer('nine_ai_apply_plugin_update_'.$token); $key=$this->plugin_transient_key($token); $staged=get_transient($key); if(!$staged||empty($staged['user_id'])||(int)$staged['user_id']!==get_current_user_id()){$this->redirect_plugin_update('',__('The plugin preview expired. Upload the ZIP again.', 'nine-code-data' ),'error');}
        $result=$this->plugin_updater->apply($staged); if(is_wp_error($result)){$this->redirect_plugin_update($token,$result->get_error_message(),'error');} delete_transient($key); $message=sprintf(__('Plugin updated through 9 AI Manager: %s %s → %s.', 'nine-code-data' ),$result['name'],$result['from_version'],$result['to_version']); wp_safe_redirect(add_query_arg(array('page'=>'nine-ai-manager-history','nine_ai_notice'=>$message,'nine_ai_type'=>'success'),admin_url('admin.php'))); exit;
    }

    public function handle_rollback() {
        $this->require_admin(); $id=!empty($_POST['history_id'])?sanitize_text_field(wp_unslash($_POST['history_id'])):''; check_admin_referer('nine_ai_rollback_'.$id); $result=$this->importer->rollback($id); $message=is_wp_error($result)?$result->get_error_message():sprintf(__('Rollback completed for %d supported change group(s).', 'nine-code-data' ),$result); wp_safe_redirect(add_query_arg(array('page'=>'nine-ai-manager-history','nine_ai_notice'=>$message,'nine_ai_type'=>is_wp_error($result)?'error':'success'),admin_url('admin.php'))); exit;
    }

    public function save_settings() {
        $this->require_admin(); check_admin_referer('nine_ai_save_settings'); $status=!empty($_POST['default_post_status'])?sanitize_key(wp_unslash($_POST['default_post_status'])):'draft'; if(!in_array($status,array('draft','pending','publish','private'),true)){$status='draft';}
        $settings=array('allow_options'=>!empty($_POST['allow_options'])?1:0,'allow_menu_changes'=>!empty($_POST['allow_menu_changes'])?1:0,'allow_users'=>!empty($_POST['allow_users'])?1:0,'default_post_status'=>$status,'history_limit'=>isset($_POST['history_limit'])?max(10,min(200,absint($_POST['history_limit']))):75,'max_package_mb'=>isset($_POST['max_package_mb'])?max(1,min(100,absint($_POST['max_package_mb']))):25,'max_plugin_mb'=>isset($_POST['max_plugin_mb'])?max(1,min(200,absint($_POST['max_plugin_mb']))):40,'show_non_nine_plugins'=>1); update_option('nine_ai_manager_settings',$settings,false); wp_safe_redirect(add_query_arg(array('page'=>'nine-ai-manager-settings','nine_ai_notice'=>__('Settings saved.', 'nine-code-data' ),'nine_ai_type'=>'success'),admin_url('admin.php'))); exit;
    }

    public function download_manifest() {
        $this->require_admin(); check_admin_referer('nine_ai_download_manifest'); $manifest=$this->manifest->get_manifest(true); $this->send_download_headers('nine-ai-site-manifest-v2.json','application/json; charset=utf-8'); echo wp_json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit;
    }

    public function download_ai_kit() {
        $this->require_admin(); check_admin_referer('nine_ai_download_kit');
        $manifest=$this->manifest->get_manifest(true);
        $instruction=array(
            'format'=>'nine-ai-instruction-file/v2',
            'generated_at'=>current_time('c'),
            'purpose'=>'Human-controlled AI ↔ WordPress communication contract. Read the entire site_manifest before doing work.',
            'human_workflow'=>array('The human gives this file plus an instruction/source material to AI.','For site/content/configuration work return nine-ai-package/v2 JSON or ZIP.','For plugin code work the human should use a Plugin AI Work Pack; return an installable plugin ZIP preserving its path.','The human previews and approves all changes in 9 AI Manager.'),
            'required_ai_behavior'=>array('Use the exact site_fingerprint and manifest_hash under target.','Do not invent field/meta/taxonomy/integration names when the manifest supplies them.','Prefer draft for content unless publication is explicitly requested.','Never include secrets, API keys, passwords, salts, private tokens or cookies.','Use native integration contracts when present; automatic plugin contracts remain valid for generic post/taxonomy/meta/settings communication.'),
            'required_output'=>$this->manifest->get_package_schema(),
            'site_manifest'=>$manifest,
        );
        update_option('nine_ai_manager_last_export',array('generated_at'=>current_time('c'),'scan_id'=>$manifest['scan_id'],'site_fingerprint'=>$manifest['site_fingerprint'],'manifest_hash'=>$manifest['manifest_hash']),false);
        $name='9-AI-Site-Instruction-' . gmdate('Y-m-d-His') . '.9ai-site.json'; $this->send_download_headers($name,'application/json; charset=utf-8'); echo wp_json_encode($instruction,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit;
    }

    public function download_example() {
        $this->require_admin(); check_admin_referer('nine_ai_download_example'); $this->send_download_headers('example-v2.9ai.json','application/json; charset=utf-8'); echo wp_json_encode($this->example_package(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit;
    }

    public function download_plugin_work_pack() {
        $this->require_admin(); $plugin_file=!empty($_POST['plugin_file'])?plugin_basename(wp_unslash($_POST['plugin_file'])):''; check_admin_referer('nine_ai_build_plugin_pack_'.$plugin_file); $manifest=$this->manifest->get_manifest(true); $scan=$this->scanner->get_last_scan(false); $path=$this->plugin_updater->build_source_pack($plugin_file,$manifest,$scan); if(is_wp_error($path)){wp_die(esc_html($path->get_error_message()));} if(!is_file($path)){wp_die(esc_html__('Could not build the plugin work pack.', 'nine-code-data' ));} $this->send_download_headers(basename($path),'application/zip'); header('Content-Length: '.filesize($path)); readfile($path); @unlink($path); @rmdir(dirname($path)); exit;
    }

    private function example_package() {
        $manifest=$this->manifest->get_manifest(false); return array('format'=>'nine-ai-package/v2','target'=>array('site_fingerprint'=>$manifest['site_fingerprint'],'manifest_hash'=>$manifest['manifest_hash']),'package_name'=>'Lecturer Management Article','notes'=>'Create as draft for human review.','items'=>array(array('entity'=>'post','operation'=>'upsert','post_type'=>'post','title'=>'Lecturer Management in the AI Era','slug'=>'lecturer-management-ai-era','status'=>'draft','excerpt'=>'A practical overview of managing lecturers with modern digital and AI-assisted workflows.','content'=>'<!-- wp:heading --><h2 class="wp-block-heading">Introduction</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Replace this sample with the finished AI-created content.</p><!-- /wp:paragraph -->','terms'=>array('category'=>array('Lecturer Management')),'acf'=>array(),'meta'=>array('_nine_ai_source'=>'9 AI Manager'))));
    }

    private function send_download_headers($filename,$content_type){ nocache_headers(); header('Content-Type: '.$content_type); header('Content-Disposition: attachment; filename="'.sanitize_file_name($filename).'"'); header('X-Content-Type-Options: nosniff'); }
    private function redirect_route($route,$stage='',$notice='',$type='success'){ $args=array('page'=>$this->page_from_route($route)); if($stage){$args['stage']=$stage;} if($notice){$args['nine_ai_notice']=$notice;$args['nine_ai_type']=$type;} wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit; }
    private function redirect_plugin_update($stage='',$notice='',$type='success'){ $args=array('page'=>'nine-ai-manager-plugin-update'); if($stage){$args['plugin_stage']=$stage;} if($notice){$args['nine_ai_notice']=$notice;$args['nine_ai_type']=$type;} wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit; }
    private function route_from_page($page){$map=array('nine-ai-manager-post'=>'post','nine-ai-manager-page'=>'page','nine-ai-manager-category'=>'category','nine-ai-manager-landing'=>'landing_page','nine-ai-manager-settings-import'=>'settings','nine-ai-manager-user'=>'user','nine-ai-manager-import'=>'all'); return isset($map[$page])?$map[$page]:'all';}
    private function page_from_route($route){$map=array('post'=>'nine-ai-manager-post','page'=>'nine-ai-manager-page','category'=>'nine-ai-manager-category','landing_page'=>'nine-ai-manager-landing','settings'=>'nine-ai-manager-settings-import','user'=>'nine-ai-manager-user','all'=>'nine-ai-manager-import'); return isset($map[$route])?$map[$route]:'nine-ai-manager-import';}
    private function route_label($route){$map=array('post'=>'Post Creator','page'=>'AI → Page','category'=>'AI → Category','landing_page'=>'AI → Landing Page','settings'=>'AI → Site / Plugin Settings','user'=>'AI → User','all'=>'AI → Anything'); return isset($map[$route])?$map[$route]:'AI → Anything';}
    private function route_description($route){$map=array('post'=>'Upload AI-prepared posts/CPT content, Gutenberg blocks, ACF/meta, taxonomy and media.','page'=>'Create or update WordPress pages and hierarchy from an approved AI package.','category'=>'Create/update categories, tags and custom taxonomy structures.','landing_page'=>'Create a base page and pass structured payloads to a native landing-page integration when one is installed.','settings'=>'Apply only explicitly enabled, discovered/whitelisted site or plugin settings and native integration operations.','user'=>'Create/update WordPress users only when User operations are enabled in Settings. AI-supplied passwords are never trusted.','all'=>'Use one package containing any supported mix of WordPress objects and registered plugin integrations.'); return isset($map[$route])?$map[$route]:$map['all'];}
    private function transient_key($token){return 'nine_ai_stage_'.substr(hash('sha256',(string)$token),0,32);}
    private function plugin_transient_key($token){return 'nine_ai_plugin_stage_'.substr(hash('sha256',(string)$token),0,28);}
}
