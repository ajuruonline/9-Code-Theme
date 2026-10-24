<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds a fresh, non-secret inventory of the WordPress installation for AI work.
 * The scan is intentionally bounded: it inventories declarations and option/meta
 * keys without exporting option values, passwords, tokens or arbitrary database data.
 */
class Nine_AI_Manager_Scanner {
    const OPTION = 'nine_ai_manager_last_scan';

    public function scan($check_updates = true) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ($check_updates) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
            if (function_exists('wp_update_plugins')) {
                wp_update_plugins();
            }
            if (function_exists('wp_update_themes')) {
                wp_update_themes();
            }
        }

        $plugins = $this->scan_plugins();
        $themes  = $this->scan_themes();
        $core    = $this->scan_core_update();

        $native_integrations = apply_filters('nine_ai_manager_integrations', array());
        $native_keys = array_keys(is_array($native_integrations) ? $native_integrations : array());

        $native_count = 0;
        $bridge_count = 0;
        $auto_count   = 0;
        $nine_count   = 0;
        $update_count = 0;
        foreach ($plugins as &$plugin) {
            if (!empty($plugin['nine_ecosystem'])) {
                $nine_count++;
            }
            if (!empty($plugin['update']['available'])) {
                $update_count++;
            }
            $matched = $this->match_native_integrations($plugin, $native_integrations);
            $mode = 'automatic';
            if ($matched) {
                $bridge_only = true;
                foreach ($matched as $matched_key) {
                    if (empty($native_integrations[$matched_key]['bridge_generated'])) {
                        $bridge_only = false;
                        break;
                    }
                }
                $mode = $bridge_only ? 'bridge' : 'native';
            }
            $plugin['ai_integration'] = array(
                'mode' => $mode,
                'native_keys' => $matched,
                'source_declares_bridge' => !empty($plugin['detected']['nine_ai_bridge']),
                'communication_ready' => true,
            );
            if ('native' === $mode) {
                $native_count++;
            } elseif ('bridge' === $mode) {
                $bridge_count++;
            } else {
                $auto_count++;
            }
        }
        unset($plugin);

        $basis = array(
            'wordpress_version' => get_bloginfo('version'),
            'plugins' => array_map(function($p) {
                return array(
                    'file' => $p['file'],
                    'version' => $p['version'],
                    'active' => $p['active'],
                    'update' => $p['update'],
                    'detected' => $p['detected'],
                    'ai_integration' => $p['ai_integration'],
                );
            }, $plugins),
            'themes' => $themes,
            'native_integration_keys' => $native_keys,
        );
        $fingerprint = hash('sha256', wp_json_encode($basis));

        $scan = array(
            'format' => 'nine-ai-readiness-scan/v2',
            'scan_id' => wp_generate_uuid4(),
            'generated_at' => current_time('c'),
            'site_fingerprint' => $fingerprint,
            'wordpress' => array(
                'version' => get_bloginfo('version'),
                'update' => $core,
            ),
            'plugins' => $plugins,
            'themes' => $themes,
            'summary' => array(
                'plugins_installed' => count($plugins),
                'nine_plugins_detected' => $nine_count,
                'plugin_updates_available' => $update_count,
                'native_ai_integrations' => $native_count,
                'bridge_ai_integrations' => $bridge_count,
                'automatic_ai_integrations' => $auto_count,
                'native_integration_keys' => $native_keys,
            ),
        );

        update_option(self::OPTION, $scan, false);
        return $scan;
    }

    public function get_last_scan($refresh_if_missing = true) {
        $scan = get_option(self::OPTION, array());
        if ((!is_array($scan) || empty($scan['site_fingerprint'])) && $refresh_if_missing) {
            return $this->scan(false);
        }
        return is_array($scan) ? $scan : array();
    }

    public function find_plugin($plugin_file) {
        $plugin_file = plugin_basename((string) $plugin_file);
        $scan = $this->get_last_scan();
        foreach ((array) ($scan['plugins'] ?? array()) as $plugin) {
            if (!empty($plugin['file']) && $plugin['file'] === $plugin_file) {
                return $plugin;
            }
        }
        return null;
    }

    private function scan_plugins() {
        $all = get_plugins();
        $active = (array) get_option('active_plugins', array());
        if (is_multisite()) {
            $network = (array) get_site_option('active_sitewide_plugins', array());
            $active = array_values(array_unique(array_merge($active, array_keys($network))));
        }
        $updates = get_site_transient('update_plugins');
        $responses = is_object($updates) && isset($updates->response) ? (array) $updates->response : array();

        $out = array();
        foreach ($all as $file => $headers) {
            $name = isset($headers['Name']) ? $headers['Name'] : $file;
            $slug = dirname($file);
            if ('.' === $slug) {
                $slug = basename($file, '.php');
            }
            $update = array('available' => false, 'new_version' => '');
            if (isset($responses[$file])) {
                $u = $responses[$file];
                $update = array(
                    'available' => true,
                    'new_version' => isset($u->new_version) ? (string) $u->new_version : '',
                );
            }
            $detected = $this->inspect_plugin_source($file);
            $out[] = array(
                'file' => $file,
                'slug' => sanitize_key(str_replace(array('/', '\\'), '-', $slug)),
                'name' => $name,
                'version' => isset($headers['Version']) ? (string) $headers['Version'] : '',
                'description' => isset($headers['Description']) ? wp_strip_all_tags($headers['Description']) : '',
                'active' => in_array($file, $active, true),
                'nine_ecosystem' => $this->looks_like_nine_plugin($name, $file),
                'update' => $update,
                'detected' => $detected,
            );
        }
        usort($out, function($a, $b) {
            if ($a['nine_ecosystem'] !== $b['nine_ecosystem']) {
                return $a['nine_ecosystem'] ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        return $out;
    }

    private function scan_themes() {
        $themes = wp_get_themes();
        $current = get_stylesheet();
        $updates = get_site_transient('update_themes');
        $responses = is_object($updates) && isset($updates->response) ? (array) $updates->response : array();
        $out = array();
        foreach ($themes as $slug => $theme) {
            $update = array('available' => false, 'new_version' => '');
            if (isset($responses[$slug])) {
                $u = (array) $responses[$slug];
                $update = array(
                    'available' => true,
                    'new_version' => isset($u['new_version']) ? (string) $u['new_version'] : '',
                );
            }
            $out[] = array(
                'slug' => $slug,
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'active' => ($slug === $current),
                'update' => $update,
            );
        }
        return $out;
    }

    private function scan_core_update() {
        $transient = get_site_transient('update_core');
        $result = array('available' => false, 'new_version' => '');
        if (!is_object($transient) || empty($transient->updates) || !is_array($transient->updates)) {
            return $result;
        }
        foreach ($transient->updates as $update) {
            if (is_object($update) && isset($update->response) && 'upgrade' === $update->response) {
                $result['available'] = true;
                $result['new_version'] = isset($update->current) ? (string) $update->current : '';
                break;
            }
        }
        return $result;
    }

    private function match_native_integrations($plugin, $integrations) {
        $matches = array();
        foreach ((array) $integrations as $key => $integration) {
            if (!is_array($integration)) {
                continue;
            }
            $plugin_file = isset($integration['plugin_file']) ? plugin_basename($integration['plugin_file']) : '';
            $plugin_slug = isset($integration['plugin_slug']) ? sanitize_key($integration['plugin_slug']) : '';
            if (($plugin_file && $plugin_file === $plugin['file']) || ($plugin_slug && $plugin_slug === $plugin['slug'])) {
                $matches[] = sanitize_key($key);
            }
        }
        return array_values(array_unique($matches));
    }

    private function looks_like_nine_plugin($name, $file) {
        $hay = strtolower(trim($name . ' ' . $file));
        return (bool) preg_match('/(^|[\s\/_-])(9|99|nine|9igeria|9igerian|be-aware)([\s\/_-]|$)/i', $hay);
    }

    private function inspect_plugin_source($plugin_file) {
        $base_file = WP_PLUGIN_DIR . '/' . ltrim($plugin_file, '/');
        $dir = dirname($base_file);
        if (!is_file($base_file) || !is_dir($dir)) {
            return $this->empty_detected();
        }

        $files = array($base_file);
        if (dirname($plugin_file) !== '.') {
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($iterator as $candidate) {
                    if (count($files) >= 80) {
                        break;
                    }
                    if (!$candidate->isFile()) {
                        continue;
                    }
                    $ext = strtolower($candidate->getExtension());
                    if (in_array($ext, array('php', 'js'), true)) {
                        $files[] = $candidate->getPathname();
                    }
                }
            } catch (Exception $e) {
                // Bounded best-effort discovery only.
            }
        }

        $aggregate = '';
        $bytes = 0;
        foreach (array_values(array_unique($files)) as $path) {
            if ($bytes >= 2 * 1024 * 1024) {
                break;
            }
            $size = @filesize($path);
            if (false === $size || $size > 512 * 1024) {
                continue;
            }
            $text = @file_get_contents($path);
            if (!is_string($text)) {
                continue;
            }
            $remaining = (2 * 1024 * 1024) - $bytes;
            $text = substr($text, 0, $remaining);
            $aggregate .= "\n" . $text;
            $bytes += strlen($text);
        }

        return array(
            'post_types' => $this->regex_literals($aggregate, '/register_post_type\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i'),
            'taxonomies' => $this->regex_literals($aggregate, '/register_taxonomy\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i'),
            'option_keys' => $this->regex_literals($aggregate, '/(?:get|update|add|delete)_option\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i', 120),
            'settings' => $this->regex_literals($aggregate, '/register_setting\s*\(\s*[\'\"][^\'\"]+[\'\"]\s*,\s*[\'\"]([^\'\"]+)[\'\"]/i', 120),
            'meta_keys' => $this->regex_literals($aggregate, '/(?:get|update|add|delete)_post_meta\s*\([^,]+,\s*[\'\"]([^\'\"]+)[\'\"]/i', 160),
            'shortcodes' => $this->regex_literals($aggregate, '/add_shortcode\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i'),
            'blocks' => $this->regex_literals($aggregate, '/register_block_type\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i'),
            'rest_namespaces' => $this->regex_literals($aggregate, '/register_rest_route\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i'),
            'elementor_widgets' => $this->regex_literals($aggregate, '/class\s+([A-Za-z0-9_]+)\s+extends\s+(?:\\\\)?Elementor\\\\Widget_Base/i'),
            'nine_ai_bridge' => (false !== strpos($aggregate, 'nine_ai_manager_integrations') || false !== strpos($aggregate, 'nine_ai_manager_ready')),
            'files_scanned' => count($files),
            'bytes_scanned' => $bytes,
        );
    }

    private function empty_detected() {
        return array(
            'post_types' => array(), 'taxonomies' => array(), 'option_keys' => array(),
            'settings' => array(), 'meta_keys' => array(), 'shortcodes' => array(),
            'blocks' => array(), 'rest_namespaces' => array(), 'elementor_widgets' => array(),
            'nine_ai_bridge' => false, 'files_scanned' => 0, 'bytes_scanned' => 0,
        );
    }

    private function regex_literals($text, $pattern, $limit = 80) {
        if (!$text || !preg_match_all($pattern, $text, $matches)) {
            return array();
        }
        $values = array();
        foreach ((array) $matches[1] as $value) {
            $value = trim((string) $value);
            if ('' === $value || strlen($value) > 190) {
                continue;
            }
            $values[] = $value;
            if (count($values) >= $limit) {
                break;
            }
        }
        $values = array_values(array_unique($values));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        return $values;
    }
}
