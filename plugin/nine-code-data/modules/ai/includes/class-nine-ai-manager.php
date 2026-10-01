<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nine_AI_Manager {
    private static $instance = null;

    public $scanner;
    public $manifest;
    public $history;
    public $importer;
    public $plugin_updater;
    public $admin;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->history = new Nine_AI_Manager_History();
        $this->scanner = new Nine_AI_Manager_Scanner();
        $this->manifest = new Nine_AI_Manager_Manifest($this->scanner);
        $this->importer = new Nine_AI_Manager_Importer($this->history);
        $this->plugin_updater = new Nine_AI_Manager_Plugin_Updater($this->history, $this->scanner);

        if (is_admin()) {
            $this->admin = new Nine_AI_Manager_Admin(
                $this->manifest,
                $this->importer,
                $this->history,
                $this->scanner,
                $this->plugin_updater
            );
        }

        add_action('init', array($this, 'announce_ready'), 999);
        add_filter('nine_ai_manager_allow_option_key', array($this, 'allow_ecosystem_option_prefixes'), 10, 3);
        add_filter('nine_ai_manager_integrations', array($this, 'register_self_integration'), 10, 1);
    }

    public static function activate() {
        $defaults = array(
            'allow_options' => 0,
            'allow_menu_changes' => 1,
            'allow_users' => 0,
            'default_post_status' => 'draft',
            'history_limit' => 75,
            'max_package_mb' => 25,
            'max_plugin_mb' => 40,
            'show_non_nine_plugins_in_ai_file' => 1,
        );
        $current = get_option('nine_ai_manager_settings', array());
        update_option('nine_ai_manager_settings', wp_parse_args($current, $defaults), false);
        delete_option(Nine_AI_Manager_Scanner::OPTION);
    }

    public static function deactivate() {
        // Non-destructive by design. History, rollback files and settings remain available.
    }

    public function announce_ready() {
        /**
         * Native 9 AI integration contract:
         *
         * add_filter('nine_ai_manager_integrations', function($integrations) {
         *   $integrations['my_plugin'] = array(
         *      'plugin_file' => plugin_basename(MY_PLUGIN_FILE),
         *      'plugin_slug' => 'my-plugin',
         *      'label' => 'My Plugin',
         *      'description' => 'What AI can configure here.',
         *      'capabilities' => array('read', 'configure'),
         *      'schema' => array(...),
         *      'instructions' => array(...),
         *      'handler' => callable,
         *   );
         *   return $integrations;
         * });
         */
        do_action('nine_ai_manager_ready', $this);
    }

    public function register_self_integration($integrations) {
        $integrations['nine_ai_manager'] = array(
            'plugin_file' => plugin_basename(NINE_AI_MANAGER_FILE),
            'plugin_slug' => 'nine-ai-manager',
            'label' => '9 AI Manager',
            'description' => 'The protocol hub for fresh site contracts, preview-controlled AI packages and AI plugin work packs.',
            'capabilities' => array('read', 'describe', 'site-contract', 'package-import', 'plugin-work-pack', 'plugin-update'),
            'schema' => array(
                'site_instruction_format' => 'nine-ai-instruction-file/v2',
                'site_package_format' => 'nine-ai-package/v2',
                'site_manifest_format' => 'nine-ai-site-manifest/v2',
                'plugin_update_format' => 'installable WordPress ZIP preserving plugin folder/main file',
            ),
            'instructions' => array('Use the fresh Site AI File before preparing WordPress work.', 'Never bypass the human preview/approval workflow.'),
            'handler' => array($this, 'self_integration_handler'),
        );
        return $integrations;
    }

    public function self_integration_handler($item, $context) {
        return array(
            'message' => '9 AI Manager protocol hub is available.',
            'version' => NINE_AI_MANAGER_VERSION,
            'formats' => array('nine-ai-instruction-file/v2', 'nine-ai-package/v2', 'nine-ai-site-manifest/v2'),
        );
    }

    public function allow_ecosystem_option_prefixes($allowed, $key, $item) {
        if ($allowed) {
            return true;
        }
        $prefixes = array('nine_', 'nine-', '9_', '9-', '99_', '99-', 'be_aware_', 'be-aware-', 'onlin9_');
        foreach ($prefixes as $prefix) {
            if (0 === strpos((string) $key, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
