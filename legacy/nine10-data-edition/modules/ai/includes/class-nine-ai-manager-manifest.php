<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nine_AI_Manager_Manifest {
    private $scanner;

    public function __construct($scanner = null) {
        $this->scanner = $scanner instanceof Nine_AI_Manager_Scanner ? $scanner : new Nine_AI_Manager_Scanner();
    }

    public function get_manifest($fresh_scan = false) {
        $scan = $fresh_scan ? $this->scanner->scan(true) : $this->scanner->get_last_scan(true);
        $manifest = array(
            'format' => 'nine-ai-site-manifest/v2',
            'generated_at' => current_time('c'),
            'scan_id' => isset($scan['scan_id']) ? $scan['scan_id'] : '',
            'site_fingerprint' => isset($scan['site_fingerprint']) ? $scan['site_fingerprint'] : '',
            'site' => array(
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'locale' => get_locale(),
                'timezone' => wp_timezone_string(),
                'wordpress_version' => get_bloginfo('version'),
                'active_theme' => get_stylesheet(),
            ),
            'workflow' => array(
                'principle' => 'Human-controlled AI workflow. AI prepares files; the administrator previews them; WordPress changes only after explicit approval.',
                'site_package_formats' => array('.9ai.json', '.json', '.zip'),
                'plugin_update_format' => 'installable WordPress plugin ZIP preserving the installed plugin folder/main file',
                'package_schema' => $this->get_package_schema(),
                'routes' => array(
                    'AI → Post', 'AI → Page', 'AI → Category', 'AI → Landing Page',
                    'AI → Site / Plugin Settings', 'AI → User', 'AI → Plugin Update', 'AI → Anything'
                ),
            ),
            'readiness_scan' => $this->public_scan($scan),
            'post_types' => $this->get_post_types(),
            'taxonomies' => $this->get_taxonomies(),
            'acf' => $this->get_acf_schema(),
            'registered_meta' => $this->get_registered_meta(),
            'menus' => $this->get_menu_locations(),
            'gutenberg_blocks' => $this->get_blocks(),
            'shortcodes' => $this->get_shortcodes(),
            'integrations' => $this->get_integrations(),
            'plugin_contracts' => $this->get_plugin_contracts($scan),
            'supported_entities' => array(
                'post', 'page', 'cpt', 'landing_page', 'term', 'category', 'tag',
                'media', 'menu', 'option', 'plugin_settings', 'user', 'integration'
            ),
            'instructions_for_ai' => $this->instructions_for_ai(),
        );
        $manifest = apply_filters('nine_ai_manager_site_manifest', $manifest, $scan);
        $manifest['manifest_hash'] = hash('sha256', wp_json_encode($manifest));
        return $manifest;
    }

    public function get_package_schema() {
        return array(
            'format' => 'nine-ai-package/v2',
            'target' => array(
                'site_fingerprint' => 'copy from this Site AI File',
                'manifest_hash' => 'copy from this Site AI File',
            ),
            'package_name' => 'Human-readable job name',
            'notes' => 'Explain important assumptions and what the administrator should review.',
            'items' => array(
                array(
                    'entity' => 'post|page|cpt|landing_page|term|category|tag|media|menu|option|plugin_settings|user|integration',
                    'operation' => 'create|update|upsert',
                    'id' => 'optional existing object ID',
                    'post_type' => 'post/page/CPT when relevant',
                    'title' => 'content title',
                    'slug' => 'optional stable slug',
                    'status' => 'draft|pending|publish|private',
                    'content' => 'WordPress/Gutenberg HTML or block content',
                    'excerpt' => 'optional excerpt',
                    'author_id' => 'optional user ID',
                    'parent_id' => 'optional page/CPT parent',
                    'terms' => array('category' => array('Existing or requested term name')),
                    'meta' => array('registered_or_known_meta_key' => 'value'),
                    'acf' => array('existing_acf_field_name_or_key' => 'value'),
                    'featured_media' => array(
                        'attachment_id' => 123,
                        'asset' => 'assets/example.jpg',
                        'url' => 'https://example.com/example.jpg',
                        'alt' => 'Accessible alternative text',
                    ),
                    'integration' => 'native integration key when entity=integration',
                    'payload' => array('integration-specific' => 'data'),
                ),
            ),
        );
    }

    private function instructions_for_ai() {
        return array(
            'Read this complete file before responding to a WordPress work request.',
            'Treat site_fingerprint and manifest_hash as the identity/version of the site contract. Echo both under target in nine-ai-package/v2.',
            'For content/site changes return a downloadable nine-ai-package/v2 JSON or a ZIP containing package.json plus assets/.',
            'Prefer draft status unless the human explicitly asks to publish.',
            'Use existing post types, taxonomies, ACF fields, registered meta and native integration keys from this file. Do not invent technical field names when a site field already exists.',
            'A plugin marked bridge or automatic is still communicable: use its detected post types/taxonomies/meta through core entities and its detected option keys only through entity=plugin_settings when the user asks for a settings change.',
            'A plugin marked native should use its integration schema/handler for plugin-specific operations when that is more precise than generic WordPress fields.',
            'For a plugin code update, do not return a nine-ai-package. Modify the supplied plugin source and return an installable plugin ZIP preserving the current plugin folder/main file, increasing the version, and preserving/adding its native nine_ai_manager_integrations contract.',
            'Never put API keys, passwords, salts, private tokens, cookies or other secrets into an AI package or integration contract.',
            'Keep each package idempotent where practical: prefer upsert with stable IDs/slugs, and state assumptions in notes.',
        );
    }

    private function public_scan($scan) {
        return array(
            'format' => isset($scan['format']) ? $scan['format'] : '',
            'scan_id' => isset($scan['scan_id']) ? $scan['scan_id'] : '',
            'generated_at' => isset($scan['generated_at']) ? $scan['generated_at'] : '',
            'site_fingerprint' => isset($scan['site_fingerprint']) ? $scan['site_fingerprint'] : '',
            'wordpress' => isset($scan['wordpress']) ? $scan['wordpress'] : array(),
            'themes' => isset($scan['themes']) ? $scan['themes'] : array(),
            'summary' => isset($scan['summary']) ? $scan['summary'] : array(),
        );
    }

    private function get_plugin_contracts($scan) {
        $out = array();
        foreach ((array) ($scan['plugins'] ?? array()) as $plugin) {
            if (empty($plugin['active']) && empty($plugin['nine_ecosystem'])) {
                continue;
            }
            $out[] = array(
                'file' => $plugin['file'],
                'slug' => $plugin['slug'],
                'name' => $plugin['name'],
                'version' => $plugin['version'],
                'active' => !empty($plugin['active']),
                'nine_ecosystem' => !empty($plugin['nine_ecosystem']),
                'update' => $plugin['update'],
                'ai_integration' => $plugin['ai_integration'],
                'detected' => $plugin['detected'],
            );
        }
        return $out;
    }

    private function get_post_types() {
        $objects = get_post_types(array('show_ui' => true), 'objects');
        $result = array();
        foreach ($objects as $name => $obj) {
            if (in_array($name, array('attachment', 'revision', 'nav_menu_item'), true)) {
                continue;
            }
            $result[$name] = array(
                'label' => $obj->labels->name,
                'singular_label' => $obj->labels->singular_name,
                'hierarchical' => (bool) $obj->hierarchical,
                'supports' => array_keys(get_all_post_type_supports($name)),
                'taxonomies' => get_object_taxonomies($name),
                'rest_base' => !empty($obj->show_in_rest) ? ($obj->rest_base ? $obj->rest_base : $name) : '',
            );
        }
        return $result;
    }

    private function get_taxonomies() {
        $objects = get_taxonomies(array('show_ui' => true), 'objects');
        $result = array();
        foreach ($objects as $name => $obj) {
            if (in_array($name, array('nav_menu', 'link_category', 'post_format'), true)) {
                continue;
            }
            $result[$name] = array(
                'label' => $obj->labels->name,
                'singular_label' => $obj->labels->singular_name,
                'hierarchical' => (bool) $obj->hierarchical,
                'object_types' => array_values((array) $obj->object_type),
                'existing_terms' => $this->get_term_summary($name),
            );
        }
        return $result;
    }

    private function get_term_summary($taxonomy) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 350, 'orderby' => 'name', 'order' => 'ASC'));
        if (is_wp_error($terms)) {
            return array();
        }
        $out = array();
        foreach ($terms as $term) {
            $out[] = array('id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'parent' => (int) $term->parent);
        }
        return $out;
    }

    private function get_acf_schema() {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return array('available' => false, 'message' => 'ACF API not detected. Named values can still be handled as post meta when appropriate.', 'groups' => array());
        }
        $groups = array();
        foreach ((array) acf_get_field_groups() as $group) {
            $fields = array();
            foreach ((array) acf_get_fields($group['key']) as $field) {
                $fields[] = $this->simplify_acf_field($field);
            }
            $groups[] = array(
                'key' => isset($group['key']) ? $group['key'] : '',
                'title' => isset($group['title']) ? $group['title'] : '',
                'location' => isset($group['location']) ? $group['location'] : array(),
                'fields' => $fields,
            );
        }
        return array('available' => true, 'groups' => $groups);
    }

    private function simplify_acf_field($field) {
        $simple = array(
            'key' => isset($field['key']) ? $field['key'] : '',
            'name' => isset($field['name']) ? $field['name'] : '',
            'label' => isset($field['label']) ? $field['label'] : '',
            'type' => isset($field['type']) ? $field['type'] : '',
            'required' => !empty($field['required']),
        );
        foreach (array('choices', 'default_value', 'min', 'max', 'mime_types', 'return_format') as $key) {
            if (isset($field[$key]) && '' !== $field[$key] && null !== $field[$key]) {
                $simple[$key] = $field[$key];
            }
        }
        if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
            $simple['sub_fields'] = array_map(array($this, 'simplify_acf_field'), $field['sub_fields']);
        }
        return $simple;
    }

    private function get_registered_meta() {
        $out = array();
        if (!function_exists('get_registered_meta_keys')) {
            return $out;
        }
        foreach (array_keys(get_post_types(array('show_ui' => true), 'names')) as $post_type) {
            $keys = get_registered_meta_keys('post', $post_type);
            if ($keys) {
                $out[$post_type] = array_keys($keys);
            }
        }
        return $out;
    }

    private function get_menu_locations() {
        $registered = get_registered_nav_menus();
        $locations = get_nav_menu_locations();
        $result = array();
        foreach ($registered as $slug => $label) {
            $result[$slug] = array('label' => $label, 'assigned_menu_id' => isset($locations[$slug]) ? (int) $locations[$slug] : 0);
        }
        return $result;
    }

    private function get_blocks() {
        if (!class_exists('WP_Block_Type_Registry')) {
            return array();
        }
        $types = WP_Block_Type_Registry::get_instance()->get_all_registered();
        $out = array();
        foreach ($types as $name => $type) {
            if (0 === strpos($name, 'core/')) {
                continue;
            }
            $out[$name] = array(
                'title' => isset($type->title) ? $type->title : $name,
                'attributes' => isset($type->attributes) && is_array($type->attributes) ? array_keys($type->attributes) : array(),
            );
        }
        return $out;
    }

    private function get_shortcodes() {
        global $shortcode_tags;
        $keys = is_array($shortcode_tags) ? array_keys($shortcode_tags) : array();
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($keys);
    }

    public function get_integrations() {
        $integrations = apply_filters('nine_ai_manager_integrations', array());
        $out = array();
        foreach ((array) $integrations as $key => $integration) {
            if (!is_array($integration)) {
                continue;
            }
            $out[$key] = array(
                'label' => isset($integration['label']) ? $integration['label'] : $key,
                'description' => isset($integration['description']) ? $integration['description'] : '',
                'plugin_file' => isset($integration['plugin_file']) ? plugin_basename($integration['plugin_file']) : '',
                'plugin_slug' => isset($integration['plugin_slug']) ? sanitize_key($integration['plugin_slug']) : '',
                'capabilities' => isset($integration['capabilities']) ? array_values((array) $integration['capabilities']) : array(),
                'schema' => isset($integration['schema']) ? $integration['schema'] : array(),
                'instructions' => isset($integration['instructions']) ? $integration['instructions'] : array(),
            );
        }
        return $out;
    }
}
