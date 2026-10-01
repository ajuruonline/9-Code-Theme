<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nine_AI_Manager_Plugin_Updater {
    private $history;
    private $scanner;

    public function __construct($history, $scanner) {
        $this->history = $history;
        $this->scanner = $scanner;
    }

    public function stage_upload($file, $target_plugin) {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('nine_ai_plugin_no_file', __('No valid plugin ZIP was uploaded.', 'nine-code-data' ));
        }
        $target_plugin = plugin_basename((string) $target_plugin);
        if (!$target_plugin) {
            return new WP_Error('nine_ai_plugin_target', __('Choose the installed plugin this AI update is meant to replace.', 'nine-code-data' ));
        }
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed = get_plugins();
        if (!isset($installed[$target_plugin])) {
            return new WP_Error('nine_ai_plugin_missing', __('The selected target plugin is not installed.', 'nine-code-data' ));
        }
        $name = isset($file['name']) ? sanitize_file_name($file['name']) : 'plugin.zip';
        if ('zip' !== strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            return new WP_Error('nine_ai_plugin_type', __('AI plugin updates must be installable ZIP files.', 'nine-code-data' ));
        }
        $settings = get_option('nine_ai_manager_settings', array());
        $max_mb = isset($settings['max_plugin_mb']) ? max(1, min(200, absint($settings['max_plugin_mb']))) : 40;
        if (!empty($file['size']) && $file['size'] > $max_mb * 1024 * 1024) {
            return new WP_Error('nine_ai_plugin_large', sprintf(__('Plugin ZIP exceeds the %d MB limit.', 'nine-code-data' ), $max_mb));
        }
        if (!class_exists('ZipArchive')) {
            return new WP_Error('nine_ai_plugin_zip_support', __('ZIP support is not enabled on this server.', 'nine-code-data' ));
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('nine_ai_plugin_upload_dir', $uploads['error']);
        }
        $stage_dir = trailingslashit($uploads['basedir']) . 'nine-ai-manager/plugin-stages/' . wp_generate_uuid4();
        if (!wp_mkdir_p($stage_dir)) {
            return new WP_Error('nine_ai_plugin_stage_dir', __('Could not create the plugin staging directory.', 'nine-code-data' ));
        }
        $zip_path = trailingslashit($stage_dir) . 'update.zip';
        if (!copy($file['tmp_name'], $zip_path)) {
            $this->delete_directory($stage_dir);
            return new WP_Error('nine_ai_plugin_copy', __('Could not preserve the uploaded plugin ZIP for preview.', 'nine-code-data' ));
        }

        $inspection = $this->inspect_zip($zip_path);
        if (is_wp_error($inspection)) {
            $this->delete_directory($stage_dir);
            return $inspection;
        }
        $target_folder = dirname($target_plugin);
        $incoming_folder = dirname($inspection['plugin_file']);
        if ('.' !== $target_folder && $target_folder !== $incoming_folder) {
            $this->delete_directory($stage_dir);
            return new WP_Error(
                'nine_ai_plugin_slug_mismatch',
                sprintf(__('The ZIP uses plugin folder "%1$s" but the installed target uses "%2$s". Ask the AI to preserve the existing plugin slug/folder.', 'nine-code-data' ), $incoming_folder, $target_folder)
            );
        }
        if ('.' === $target_folder && '.' !== $incoming_folder) {
            $this->delete_directory($stage_dir);
            return new WP_Error('nine_ai_plugin_slug_mismatch', __('The AI update changed a single-file plugin into a folder plugin. Preserve the existing plugin path.', 'nine-code-data' ));
        }

        return array(
            'stage_dir' => $stage_dir,
            'zip_path' => $zip_path,
            'source_name' => $name,
            'target_plugin' => $target_plugin,
            'installed' => array(
                'name' => $installed[$target_plugin]['Name'],
                'version' => $installed[$target_plugin]['Version'],
            ),
            'incoming' => $inspection,
        );
    }

    public function apply($staged) {
        if (empty($staged['zip_path']) || !is_file($staged['zip_path']) || empty($staged['target_plugin'])) {
            return new WP_Error('nine_ai_plugin_stage_missing', __('The staged plugin update is missing or expired.', 'nine-code-data' ));
        }
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $target = plugin_basename($staged['target_plugin']);
        $installed = get_plugins();
        if (!isset($installed[$target])) {
            return new WP_Error('nine_ai_plugin_target_gone', __('The target plugin is no longer installed.', 'nine-code-data' ));
        }
        $was_active = is_plugin_active($target);
        $backup = $this->backup_plugin($target, $installed[$target]);
        if (is_wp_error($backup)) {
            return $backup;
        }

        $skin = new Automatic_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $result = $upgrader->install($staged['zip_path'], array('overwrite_package' => true));
        if (is_wp_error($result)) {
            return $result;
        }
        if (!$result) {
            return new WP_Error('nine_ai_plugin_install', __('WordPress did not complete the plugin replacement.', 'nine-code-data' ));
        }

        wp_clean_plugins_cache(true);
        $after = get_plugins();
        if (!isset($after[$target])) {
            self::restore_backup($backup['zip'], $target, $was_active);
            return new WP_Error('nine_ai_plugin_verify_missing', __('The updated plugin could not be found at its original path, so the backup was restored.', 'nine-code-data' ));
        }
        if ($was_active && !is_plugin_active($target)) {
            $activation = activate_plugin($target, '', is_multisite() && is_plugin_active_for_network($target), true);
            if (is_wp_error($activation)) {
                self::restore_backup($backup['zip'], $target, true);
                return new WP_Error('nine_ai_plugin_activation', __('The update installed but could not be reactivated, so the previous version was restored: ', 'nine-code-data' ) . $activation->get_error_message());
            }
        }

        $history_id = $this->history->add(array(
            'package_name' => sprintf('AI Plugin Update — %s', $after[$target]['Name']),
            'status' => 'applied',
            'summary' => array('applied' => 1, 'errors' => 0, 'route' => 'plugin_update'),
            'results' => array(array(
                'entity' => 'plugin_update',
                'name' => $after[$target]['Name'],
                'plugin_file' => $target,
                'from_version' => $installed[$target]['Version'],
                'to_version' => $after[$target]['Version'],
            )),
            'rollback' => array(array(
                'type' => 'plugin_restore',
                'plugin_file' => $target,
                'backup_zip' => $backup['zip'],
                'was_active' => $was_active ? 1 : 0,
            )),
        ));
        $this->scanner->scan(false);
        $this->delete_directory(dirname($staged['zip_path']));
        do_action('nine_ai_manager_after_plugin_update', $target, $installed[$target]['Version'], $after[$target]['Version'], $history_id);

        return array(
            'history_id' => $history_id,
            'plugin_file' => $target,
            'name' => $after[$target]['Name'],
            'from_version' => $installed[$target]['Version'],
            'to_version' => $after[$target]['Version'],
        );
    }

    public function build_source_pack($plugin_file, $manifest, $scan) {
        $plugin_file = plugin_basename((string) $plugin_file);
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        if (!$plugin_file || !isset($plugins[$plugin_file])) {
            return new WP_Error('nine_ai_pack_plugin', __('The selected plugin is not installed.', 'nine-code-data' ));
        }
        if (!class_exists('ZipArchive')) {
            return new WP_Error('nine_ai_pack_zip', __('ZIP support is required to build an AI plugin work pack.', 'nine-code-data' ));
        }
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('nine_ai_pack_upload', $uploads['error']);
        }
        $work = trailingslashit($uploads['basedir']) . 'nine-ai-manager/work-packs/' . wp_generate_uuid4();
        if (!wp_mkdir_p($work)) {
            return new WP_Error('nine_ai_pack_dir', __('Could not create the work-pack directory.', 'nine-code-data' ));
        }
        $zip_path = trailingslashit($work) . sanitize_file_name(dirname($plugin_file) . '-AI-WORK-PACK.zip');
        if ('.' === dirname($plugin_file)) {
            $zip_path = trailingslashit($work) . sanitize_file_name(basename($plugin_file, '.php') . '-AI-WORK-PACK.zip');
        }

        $plugin_record = null;
        foreach ((array) ($scan['plugins'] ?? array()) as $candidate) {
            if (!empty($candidate['file']) && $candidate['file'] === $plugin_file) {
                $plugin_record = $candidate;
                break;
            }
        }
        $instructions = $this->work_pack_instructions($plugin_file, $plugins[$plugin_file], $plugin_record, $manifest);
        file_put_contents(trailingslashit($work) . 'AI-INSTRUCTIONS.md', $instructions);
        file_put_contents(trailingslashit($work) . 'site-ai.json', wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents(trailingslashit($work) . 'plugin-contract.json', wp_json_encode($plugin_record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $source_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        $source_root = is_dir(dirname($source_path)) && '.' !== dirname($plugin_file) ? dirname($source_path) : $source_path;
        $source_prefix = 'plugin-source/' . ('.' === dirname($plugin_file) ? basename($plugin_file, '.php') : dirname($plugin_file));

        $zip = new ZipArchive();
        if (true !== $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            $this->delete_directory($work);
            return new WP_Error('nine_ai_pack_open', __('Could not create the AI work-pack ZIP.', 'nine-code-data' ));
        }
        $zip->addFile(trailingslashit($work) . 'AI-INSTRUCTIONS.md', 'AI-INSTRUCTIONS.md');
        $zip->addFile(trailingslashit($work) . 'site-ai.json', 'site-ai.json');
        $zip->addFile(trailingslashit($work) . 'plugin-contract.json', 'plugin-contract.json');
        if (is_file($source_root)) {
            $zip->addFile($source_root, $source_prefix . '/' . basename($source_root));
        } else {
            $this->zip_directory($zip, $source_root, $source_prefix);
        }
        $zip->close();
        return $zip_path;
    }

    private function work_pack_instructions($plugin_file, $headers, $record, $manifest) {
        $version = isset($headers['Version']) ? $headers['Version'] : '';
        $name = isset($headers['Name']) ? $headers['Name'] : $plugin_file;
        $text = "# 9 AI Manager — Plugin AI Work Pack\n\n";
        $text .= "You are updating the installed WordPress plugin **{$name}** version **{$version}**.\n\n";
        $text .= "## Required output\n\n";
        $text .= "Return an installable WordPress plugin ZIP that preserves the existing plugin folder and main plugin path: `{$plugin_file}`. Do not silently rename the plugin slug. Increment the plugin version. Preserve existing functionality unless the user's instruction explicitly changes it.\n\n";
        $text .= "Every 9 ecosystem plugin update must include a native 9 AI Manager integration contract using the `nine_ai_manager_integrations` filter. The contract must declare `plugin_file`, `plugin_slug`, label, description, schema and a safe callable handler for plugin-specific AI operations where meaningful. If a plugin-specific write cannot be made safe, declare read/inspection schema only and let 9 AI Manager use its core post/taxonomy/meta importer.\n\n";
        $text .= "Do not include API keys, passwords or private tokens in the AI contract. Any setting writes must be explicitly whitelisted and sanitised. Keep admin workflows mobile-first and previewable.\n\n";
        $text .= "The included `site-ai.json` is the fresh site contract. `plugin-contract.json` is the current automatic/native discovery record. The `plugin-source/` directory is the source you must modify.\n\n";
        $text .= "When finished, provide the installable ZIP. It will be uploaded to **9 AI Manager → AI → Plugin Update**, previewed and only applied after human approval.\n";
        return $text;
    }

    private function inspect_zip($zip_path) {
        $tmp = dirname($zip_path) . '/inspect';
        wp_mkdir_p($tmp);
        $zip = new ZipArchive();
        if (true !== $zip->open($zip_path)) {
            return new WP_Error('nine_ai_plugin_open', __('Could not open the plugin ZIP.', 'nine-code-data' ));
        }
        if ($zip->numFiles > 2500) {
            $zip->close();
            return new WP_Error('nine_ai_plugin_files', __('The plugin ZIP contains too many files.', 'nine-code-data' ));
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($this->unsafe_path($entry)) {
                $zip->close();
                return new WP_Error('nine_ai_plugin_path', __('The plugin ZIP contains an unsafe path.', 'nine-code-data' ));
            }
        }
        if (!$zip->extractTo($tmp)) {
            $zip->close();
            return new WP_Error('nine_ai_plugin_extract', __('Could not inspect the plugin ZIP.', 'nine-code-data' ));
        }
        $zip->close();
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $candidates = array();
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $candidate) {
            if (!$candidate->isFile() || 'php' !== strtolower($candidate->getExtension())) {
                continue;
            }
            $headers = get_plugin_data($candidate->getPathname(), false, false);
            if (!empty($headers['Name'])) {
                $relative = ltrim(str_replace(array($tmp, '\\'), array('', '/'), $candidate->getPathname()), '/');
                $candidates[] = array('relative' => $relative, 'headers' => $headers);
            }
        }
        $this->delete_directory($tmp);
        if (!$candidates) {
            return new WP_Error('nine_ai_plugin_header', __('No WordPress plugin header was found inside the ZIP.', 'nine-code-data' ));
        }
        usort($candidates, function($a, $b) {
            return substr_count($a['relative'], '/') <=> substr_count($b['relative'], '/');
        });
        $chosen = $candidates[0];
        return array(
            'plugin_file' => plugin_basename($chosen['relative']),
            'name' => $chosen['headers']['Name'],
            'version' => $chosen['headers']['Version'],
            'description' => wp_strip_all_tags($chosen['headers']['Description']),
        );
    }

    private function backup_plugin($plugin_file, $headers) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('nine_ai_backup_zip', __('ZIP support is required to back up the existing plugin before replacement.', 'nine-code-data' ));
        }
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('nine_ai_backup_dir', $uploads['error']);
        }
        $dir = trailingslashit($uploads['basedir']) . 'nine-ai-manager/plugin-backups';
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('nine_ai_backup_mkdir', __('Could not create the plugin backup directory.', 'nine-code-data' ));
        }
        $slug = '.' === dirname($plugin_file) ? basename($plugin_file, '.php') : dirname($plugin_file);
        $version = isset($headers['Version']) ? $headers['Version'] : 'unknown';
        $zip_path = trailingslashit($dir) . sanitize_file_name($slug . '-backup-v' . $version . '-' . gmdate('Ymd-His') . '.zip');
        $zip = new ZipArchive();
        if (true !== $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return new WP_Error('nine_ai_backup_open', __('Could not create the plugin rollback ZIP.', 'nine-code-data' ));
        }
        $source = WP_PLUGIN_DIR . '/' . $plugin_file;
        if ('.' === dirname($plugin_file)) {
            $zip->addFile($source, basename($plugin_file));
        } else {
            $source_dir = dirname($source);
            $this->zip_directory($zip, $source_dir, dirname($plugin_file));
        }
        $zip->close();
        return array('zip' => $zip_path);
    }

    public static function restore_backup($backup_zip, $plugin_file, $was_active = true) {
        if (!class_exists('ZipArchive') || !$backup_zip || !is_file($backup_zip)) {
            return new WP_Error('nine_ai_restore_backup', __('Plugin rollback ZIP is unavailable.', 'nine-code-data' ));
        }
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugin_file = plugin_basename($plugin_file);
        $folder = dirname($plugin_file);
        if ('.' === $folder) {
            $target = WP_PLUGIN_DIR . '/' . $plugin_file;
            if (is_file($target)) {
                @unlink($target);
            }
        } else {
            self::delete_directory_static(WP_PLUGIN_DIR . '/' . $folder);
        }
        $zip = new ZipArchive();
        if (true !== $zip->open($backup_zip)) {
            return new WP_Error('nine_ai_restore_open', __('Could not open the plugin rollback ZIP.', 'nine-code-data' ));
        }
        if (!$zip->extractTo(WP_PLUGIN_DIR)) {
            $zip->close();
            return new WP_Error('nine_ai_restore_extract', __('Could not restore the previous plugin files.', 'nine-code-data' ));
        }
        $zip->close();
        wp_clean_plugins_cache(true);
        if ($was_active && !is_plugin_active($plugin_file)) {
            $activation = activate_plugin($plugin_file, '', false, true);
            if (is_wp_error($activation)) {
                return $activation;
            }
        }
        return true;
    }

    private function zip_directory($zip, $source_dir, $prefix) {
        $source_dir = rtrim($source_dir, '/\\');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source_dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = ltrim(str_replace(array($source_dir, '\\'), array('', '/'), $file->getPathname()), '/');
            $zip->addFile($file->getPathname(), trim($prefix, '/') . '/' . $relative);
        }
    }

    private function unsafe_path($path) {
        $path = str_replace('\\', '/', (string) $path);
        return (bool) preg_match('#(^/|(^|/)\.\.(/|$)|^[A-Za-z]:/)#', $path);
    }

    private function delete_directory($dir) {
        self::delete_directory_static($dir);
    }

    private static function delete_directory_static($dir) {
        if (!$dir || !is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }
}
