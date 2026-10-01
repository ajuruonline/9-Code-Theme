<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nine_AI_Manager_Importer {
    private $history;

    public function __construct($history) {
        $this->history = $history;
    }

    public function stage_upload($file) {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('nine_ai_no_file', __('No valid package file was uploaded.', 'nine-code-data' ));
        }

        $settings = get_option('nine_ai_manager_settings', array());
        $max_mb = isset($settings['max_package_mb']) ? max(1, min(100, absint($settings['max_package_mb']))) : 15;
        if (!empty($file['size']) && $file['size'] > ($max_mb * 1024 * 1024)) {
            return new WP_Error('nine_ai_too_large', sprintf(/* translators: %d: maximum package size in megabytes */ __( 'Package exceeds the %d MB limit.', 'nine-code-data' ), $max_mb));
        }

        $name = isset($file['name']) ? sanitize_file_name($file['name']) : 'package.json';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ('zip' === $ext) {
            return $this->stage_zip($file['tmp_name'], $name);
        }

        if (!in_array($ext, array('json', '9ai'), true) && substr($name, -9) !== '.9ai.json') {
            return new WP_Error('nine_ai_bad_type', __('Upload a .json, .9ai.json or .zip package.', 'nine-code-data' ));
        }

        $raw = file_get_contents($file['tmp_name']);
        return $this->stage_json($raw, $name, '');
    }

    private function stage_zip($tmp_file, $name) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('nine_ai_no_zip', __('This server does not have ZIP support enabled.', 'nine-code-data' ));
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('nine_ai_upload_dir', $uploads['error']);
        }

        $base = trailingslashit($uploads['basedir']) . 'nine-ai-manager/packages/' . wp_generate_uuid4();
        if (!wp_mkdir_p($base)) {
            return new WP_Error('nine_ai_mkdir', __('Could not create a temporary package directory.', 'nine-code-data' ));
        }

        $zip = new ZipArchive();
        $opened = $zip->open($tmp_file);
        if (true !== $opened) {
            return new WP_Error('nine_ai_zip_open', __('Could not open the ZIP package.', 'nine-code-data' ));
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($this->unsafe_zip_path($entry)) {
                $zip->close();
                $this->delete_directory($base);
                return new WP_Error('nine_ai_zip_path', __('The ZIP contains an unsafe path and was rejected.', 'nine-code-data' ));
            }
        }

        if (!$zip->extractTo($base)) {
            $zip->close();
            $this->delete_directory($base);
            return new WP_Error('nine_ai_zip_extract', __('Could not extract the ZIP package.', 'nine-code-data' ));
        }
        $zip->close();

        $manifest_file = '';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $candidate) {
            if (!$candidate->isFile()) {
                continue;
            }
            $filename = strtolower($candidate->getFilename());
            if ('package.json' === $filename || substr($filename, -9) === '.9ai.json') {
                $manifest_file = $candidate->getPathname();
                break;
            }
        }
        if (!$manifest_file) {
            foreach ($iterator as $candidate) {
                if ($candidate->isFile() && 'json' === strtolower($candidate->getExtension())) {
                    $manifest_file = $candidate->getPathname();
                    break;
                }
            }
        }

        if (!$manifest_file || !is_readable($manifest_file)) {
            $this->delete_directory($base);
            return new WP_Error('nine_ai_zip_manifest', __('No package.json or .9ai.json manifest was found inside the ZIP.', 'nine-code-data' ));
        }

        $raw = file_get_contents($manifest_file);
        $staged = $this->stage_json($raw, $name, $base);
        if (is_wp_error($staged)) {
            $this->delete_directory($base);
        }
        return $staged;
    }

    private function unsafe_zip_path($path) {
        $path = str_replace('\\', '/', (string) $path);
        return (bool) preg_match('#(^/|(^|/)\.\.(/|$)|^[A-Za-z]:/)#', $path);
    }

    private function stage_json($raw, $name, $asset_dir) {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return new WP_Error('nine_ai_json', __('The package is not valid JSON.', 'nine-code-data' ));
        }

        $validation = $this->validate_package($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        return array(
            'source_name' => $name,
            'package' => $data,
            'asset_dir' => $asset_dir,
            'preview' => $this->build_preview($data),
        );
    }

    public function validate_package($package) {
        $format = isset($package['format']) ? (string) $package['format'] : '';
        if (!in_array($format, array('nine-ai-package/v1', 'nine-ai-package/v2'), true)) {
            return new WP_Error('nine_ai_format', __('Package format must be nine-ai-package/v2 (v1 remains accepted for older work).', 'nine-code-data' ));
        }
        if ('nine-ai-package/v2' === $format) {
            $target = isset($package['target']) && is_array($package['target']) ? $package['target'] : array();
            $expected = !empty($target['site_fingerprint']) ? sanitize_text_field($target['site_fingerprint']) : '';
            $scan = get_option(Nine_AI_Manager_Scanner::OPTION, array());
            $actual = is_array($scan) && !empty($scan['site_fingerprint']) ? (string) $scan['site_fingerprint'] : '';
            if ($expected && $actual && !hash_equals($actual, $expected)) {
                return new WP_Error('nine_ai_stale_site_file', __('This AI package was prepared from a different or older Site AI File. Download a fresh AI instruction file and ask the AI to rebuild the package.', 'nine-code-data' ));
            }
        }
        if (empty($package['items']) || !is_array($package['items'])) {
            return new WP_Error('nine_ai_items', __('The package must contain an items array.', 'nine-code-data' ));
        }
        if (count($package['items']) > 250) {
            return new WP_Error('nine_ai_too_many', __('A single package may contain at most 250 items.', 'nine-code-data' ));
        }

        $allowed = array('post', 'page', 'cpt', 'landing_page', 'term', 'category', 'tag', 'media', 'menu', 'option', 'plugin_settings', 'user', 'integration');
        foreach ($package['items'] as $i => $item) {
            if (!is_array($item) || empty($item['entity']) || !in_array($item['entity'], $allowed, true)) {
                return new WP_Error('nine_ai_entity', sprintf(/* translators: %d: item number in the package */ __( 'Item %d has an unsupported entity type.', 'nine-code-data' ), $i + 1));
            }
        }
        return true;
    }

    public function build_preview($package) {
        $preview = array();
        foreach ($package['items'] as $index => $item) {
            $entity = $item['entity'];
            $operation = !empty($item['operation']) ? $item['operation'] : 'create';
            $target = '';
            if (in_array($entity, array('post', 'page', 'cpt', 'landing_page'), true)) {
                $target = !empty($item['post_type']) ? $item['post_type'] : ('page' === $entity || 'landing_page' === $entity ? 'page' : 'post');
            } elseif (in_array($entity, array('term', 'category', 'tag'), true)) {
                $target = !empty($item['taxonomy']) ? $item['taxonomy'] : ('category' === $entity ? 'category' : ('tag' === $entity ? 'post_tag' : 'category'));
            } elseif ('integration' === $entity) {
                $target = isset($item['integration']) ? $item['integration'] : '';
            } elseif ('option' === $entity) {
                $target = isset($item['key']) ? $item['key'] : '';
            } elseif ('plugin_settings' === $entity) {
                $target = isset($item['plugin_file']) ? $item['plugin_file'] : (isset($item['plugin']) ? $item['plugin'] : 'plugin settings');
            } elseif ('user' === $entity) {
                $target = isset($item['user_login']) ? $item['user_login'] : (isset($item['email']) ? $item['email'] : 'user');
            } elseif ('media' === $entity) {
                $target = isset($item['title']) ? $item['title'] : (isset($item['asset']) ? $item['asset'] : 'media');
            } elseif ('menu' === $entity) {
                $target = isset($item['name']) ? $item['name'] : 'Menu';
            }

            $preview[] = array(
                'number' => $index + 1,
                'entity' => $entity,
                'operation' => $operation,
                'target' => $target,
                'label' => isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : (isset($item['slug']) ? $item['slug'] : $target)),
            );
        }
        return $preview;
    }

    public function apply_package($staged, $route_filter = '') {
        if (empty($staged['package']) || !is_array($staged['package'])) {
            return new WP_Error('nine_ai_stage_missing', __('The staged package is missing.', 'nine-code-data' ));
        }

        $package = $staged['package'];
        $validation = $this->validate_package($package);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $results = array();
        $rollback = array();
        $errors = array();
        $context = array(
            'asset_dir' => isset($staged['asset_dir']) ? $staged['asset_dir'] : '',
            'package' => $package,
        );

        foreach ($package['items'] as $index => $item) {
            if ($route_filter && !$this->item_matches_route($item, $route_filter)) {
                continue;
            }
            $result = $this->apply_item($item, $context);
            if (is_wp_error($result)) {
                $errors[] = array('item' => $index + 1, 'message' => $result->get_error_message());
                continue;
            }
            $results[] = $result['result'];
            if (!empty($result['rollback'])) {
                $rollback[] = $result['rollback'];
            }
        }

        if (!$results && $errors) {
            return new WP_Error('nine_ai_apply_failed', implode(' | ', wp_list_pluck($errors, 'message')));
        }

        $history_id = $this->history->add(array(
            'package_name' => !empty($package['package_name']) ? sanitize_text_field($package['package_name']) : 'AI Package',
            'status' => $errors ? 'applied_with_errors' : 'applied',
            'summary' => array(
                'applied' => count($results),
                'errors' => count($errors),
                'route' => $route_filter ? $route_filter : 'all',
            ),
            'results' => $results,
            'errors' => $errors,
            'rollback' => $rollback,
        ));

        do_action('nine_ai_manager_after_package_apply', $package, $results, $errors, $history_id);

        return array(
            'history_id' => $history_id,
            'results' => $results,
            'errors' => $errors,
        );
    }

    private function item_matches_route($item, $route) {
        $entity = isset($item['entity']) ? $item['entity'] : '';
        if ('post' === $route) {
            return in_array($entity, array('post', 'cpt'), true) && ('page' !== (isset($item['post_type']) ? $item['post_type'] : 'post'));
        }
        if ('page' === $route) {
            return 'page' === $entity || ('cpt' === $entity && !empty($item['post_type']) && 'page' === $item['post_type']);
        }
        if ('category' === $route) {
            return in_array($entity, array('term', 'category', 'tag'), true);
        }
        if ('landing_page' === $route) {
            return 'landing_page' === $entity;
        }
        if ('settings' === $route) {
            return in_array($entity, array('option', 'plugin_settings', 'integration'), true);
        }
        if ('user' === $route) {
            return 'user' === $entity;
        }
        return true;
    }

    private function apply_item($item, $context) {
        $entity = $item['entity'];
        if (in_array($entity, array('post', 'page', 'cpt', 'landing_page'), true)) {
            return $this->apply_content($item, $context);
        }
        if (in_array($entity, array('term', 'category', 'tag'), true)) {
            return $this->apply_term($item);
        }
        if ('menu' === $entity) {
            return $this->apply_menu($item);
        }
        if ('option' === $entity) {
            return $this->apply_option($item);
        }
        if ('plugin_settings' === $entity) {
            return $this->apply_plugin_settings($item);
        }
        if ('user' === $entity) {
            return $this->apply_user($item);
        }
        if ('media' === $entity) {
            return $this->apply_media_item($item, $context);
        }
        if ('integration' === $entity) {
            return $this->apply_integration($item, $context);
        }
        return new WP_Error('nine_ai_unknown_entity', __('Unsupported entity.', 'nine-code-data' ));
    }

    private function apply_content($item, $context) {
        $entity = $item['entity'];
        $post_type = !empty($item['post_type']) ? sanitize_key($item['post_type']) : (in_array($entity, array('page', 'landing_page'), true) ? 'page' : 'post');
        if (!post_type_exists($post_type)) {
            return new WP_Error('nine_ai_post_type', sprintf(/* translators: %s: post type slug */ __( 'Post type "%s" does not exist.', 'nine-code-data' ), $post_type));
        }

        $operation = !empty($item['operation']) ? sanitize_key($item['operation']) : 'create';
        $existing_id = $this->resolve_existing_post($item, $post_type, $operation);
        $snapshot = $existing_id ? $this->snapshot_post($existing_id) : null;

        $settings = get_option('nine_ai_manager_settings', array());
        $default_status = !empty($settings['default_post_status']) ? $settings['default_post_status'] : 'draft';
        $postarr = array('post_type' => $post_type);

        if ($existing_id) {
            $postarr['ID'] = $existing_id;
        }
        if (array_key_exists('title', $item)) {
            $postarr['post_title'] = sanitize_text_field($item['title']);
        }
        if (array_key_exists('slug', $item)) {
            $postarr['post_name'] = sanitize_title($item['slug']);
        }
        if (array_key_exists('content', $item)) {
            $postarr['post_content'] = $this->sanitize_content($item['content']);
        }
        if (array_key_exists('excerpt', $item)) {
            $postarr['post_excerpt'] = wp_kses_post($item['excerpt']);
        }
        if (array_key_exists('status', $item)) {
            $postarr['post_status'] = $this->sanitize_post_status($item['status']);
        } elseif (!$existing_id) {
            $postarr['post_status'] = $this->sanitize_post_status($default_status);
        }
        if (!empty($item['author_id']) && get_user_by('id', absint($item['author_id']))) {
            $postarr['post_author'] = absint($item['author_id']);
        }
        if (isset($item['parent_id'])) {
            $postarr['post_parent'] = absint($item['parent_id']);
        }
        if (!empty($item['template'])) {
            $postarr['page_template'] = sanitize_text_field($item['template']);
        }

        if (!$existing_id && empty($postarr['post_title'])) {
            return new WP_Error('nine_ai_title_required', __('A title is required when creating content.', 'nine-code-data' ));
        }

        $post_id = wp_insert_post(wp_slash($postarr), true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!empty($item['terms']) && is_array($item['terms'])) {
            $this->apply_post_terms($post_id, $post_type, $item['terms']);
        }
        if (!empty($item['meta']) && is_array($item['meta'])) {
            foreach ($item['meta'] as $key => $value) {
                $key = sanitize_key($key);
                if ($key) {
                    update_post_meta($post_id, $key, $this->sanitize_meta_value($value));
                }
            }
        }
        if (!empty($item['acf']) && is_array($item['acf'])) {
            foreach ($item['acf'] as $field => $value) {
                $field = sanitize_key($field);
                if (!$field) {
                    continue;
                }
                if (function_exists('update_field')) {
                    update_field($field, $value, $post_id);
                } else {
                    update_post_meta($post_id, $field, $this->sanitize_meta_value($value));
                }
            }
        }

        if ('landing_page' === $entity) {
            update_post_meta($post_id, '_nine_ai_landing_page', 1);
            if (isset($item['landing_page'])) {
                update_post_meta($post_id, '_nine_ai_landing_config', $this->sanitize_meta_value($item['landing_page']));
            }
        }

        if (!empty($item['featured_media']) && is_array($item['featured_media'])) {
            $attachment_id = $this->resolve_media($item['featured_media'], $post_id, $context);
            if (!is_wp_error($attachment_id) && $attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        do_action('nine_ai_manager_after_content_item', $post_id, $item, $context);

        return array(
            'result' => array(
                'entity' => $entity,
                'operation' => $existing_id ? 'update' : 'create',
                'id' => (int) $post_id,
                'post_type' => $post_type,
                'title' => get_the_title($post_id),
                'edit_url' => get_edit_post_link($post_id, 'raw'),
            ),
            'rollback' => $existing_id
                ? array('type' => 'post_restore', 'post_id' => (int) $post_id, 'snapshot' => $snapshot)
                : array('type' => 'post_created', 'post_id' => (int) $post_id),
        );
    }

    private function resolve_existing_post($item, $post_type, $operation) {
        if ('create' === $operation) {
            return 0;
        }
        if (!empty($item['id'])) {
            $post = get_post(absint($item['id']));
            if ($post && $post_type === $post->post_type) {
                return (int) $post->ID;
            }
        }
        if (!empty($item['slug'])) {
            $post = get_page_by_path(sanitize_title($item['slug']), OBJECT, $post_type);
            if ($post) {
                return (int) $post->ID;
            }
        }
        return 0;
    }

    private function snapshot_post($post_id) {
        $post = get_post($post_id, ARRAY_A);
        $meta = get_post_meta($post_id);
        $tax = array();
        foreach (get_object_taxonomies($post['post_type']) as $taxonomy) {
            $ids = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
            $tax[$taxonomy] = is_wp_error($ids) ? array() : array_map('intval', $ids);
        }
        return array('post' => $post, 'meta' => $meta, 'terms' => $tax);
    }

    private function apply_post_terms($post_id, $post_type, $terms) {
        foreach ($terms as $taxonomy => $values) {
            $taxonomy = sanitize_key($taxonomy);
            if (!taxonomy_exists($taxonomy) || !in_array($post_type, get_taxonomy($taxonomy)->object_type, true)) {
                continue;
            }
            $ids = array();
            foreach ((array) $values as $value) {
                if (is_numeric($value) && term_exists(absint($value), $taxonomy)) {
                    $ids[] = absint($value);
                    continue;
                }
                if (is_array($value)) {
                    $name = !empty($value['name']) ? sanitize_text_field($value['name']) : '';
                    $slug = !empty($value['slug']) ? sanitize_title($value['slug']) : sanitize_title($name);
                } else {
                    $name = sanitize_text_field($value);
                    $slug = sanitize_title($name);
                }
                if (!$name && !$slug) {
                    continue;
                }
                $existing = $slug ? get_term_by('slug', $slug, $taxonomy) : false;
                if (!$existing && $name) {
                    $existing = get_term_by('name', $name, $taxonomy);
                }
                if ($existing) {
                    $ids[] = (int) $existing->term_id;
                } elseif ($name) {
                    $created = wp_insert_term($name, $taxonomy, array('slug' => $slug));
                    if (!is_wp_error($created)) {
                        $ids[] = (int) $created['term_id'];
                    }
                }
            }
            wp_set_object_terms($post_id, $ids, $taxonomy, false);
        }
    }

    private function apply_term($item) {
        $entity = $item['entity'];
        $taxonomy = !empty($item['taxonomy']) ? sanitize_key($item['taxonomy']) : ('category' === $entity ? 'category' : ('tag' === $entity ? 'post_tag' : 'category'));
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('nine_ai_taxonomy', sprintf(/* translators: %s: taxonomy slug */ __( 'Taxonomy "%s" does not exist.', 'nine-code-data' ), $taxonomy));
        }

        $operation = !empty($item['operation']) ? sanitize_key($item['operation']) : 'create';
        $existing = false;
        if ('create' !== $operation) {
            if (!empty($item['id'])) {
                $existing = get_term(absint($item['id']), $taxonomy);
                if (is_wp_error($existing)) {
                    $existing = false;
                }
            }
            if (!$existing && !empty($item['slug'])) {
                $existing = get_term_by('slug', sanitize_title($item['slug']), $taxonomy);
            }
        }

        $name = !empty($item['name']) ? sanitize_text_field($item['name']) : (!empty($item['title']) ? sanitize_text_field($item['title']) : '');
        $args = array();
        if (isset($item['slug'])) {
            $args['slug'] = sanitize_title($item['slug']);
        }
        if (isset($item['description'])) {
            $args['description'] = wp_kses_post($item['description']);
        }
        if (isset($item['parent'])) {
            $args['parent'] = absint($item['parent']);
        }

        if ($existing) {
            $snapshot = array(
                'term_id' => (int) $existing->term_id,
                'taxonomy' => $taxonomy,
                'name' => $existing->name,
                'slug' => $existing->slug,
                'description' => $existing->description,
                'parent' => (int) $existing->parent,
            );
            if ($name) {
                $args['name'] = $name;
            }
            $updated = wp_update_term($existing->term_id, $taxonomy, $args);
            if (is_wp_error($updated)) {
                return $updated;
            }
            $term_id = (int) $existing->term_id;
            $rollback = array('type' => 'term_restore', 'snapshot' => $snapshot);
            $op = 'update';
        } else {
            if (!$name) {
                return new WP_Error('nine_ai_term_name', __('A term name is required.', 'nine-code-data' ));
            }
            $created = wp_insert_term($name, $taxonomy, $args);
            if (is_wp_error($created)) {
                return $created;
            }
            $term_id = (int) $created['term_id'];
            $rollback = array('type' => 'term_created', 'term_id' => $term_id, 'taxonomy' => $taxonomy);
            $op = 'create';
        }

        return array(
            'result' => array('entity' => $entity, 'operation' => $op, 'id' => $term_id, 'taxonomy' => $taxonomy, 'name' => get_term($term_id, $taxonomy)->name),
            'rollback' => $rollback,
        );
    }

    private function apply_menu($item) {
        $settings = get_option('nine_ai_manager_settings', array());
        if (empty($settings['allow_menu_changes'])) {
            return new WP_Error('nine_ai_menu_disabled', __('Menu changes are disabled in 9 AI Manager settings.', 'nine-code-data' ));
        }
        $name = !empty($item['name']) ? sanitize_text_field($item['name']) : '';
        if (!$name) {
            return new WP_Error('nine_ai_menu_name', __('A menu name is required.', 'nine-code-data' ));
        }

        $existing = wp_get_nav_menu_object($name);
        $created_menu = false;
        if ($existing) {
            $menu_id = (int) $existing->term_id;
        } else {
            $menu_id = wp_create_nav_menu($name);
            if (is_wp_error($menu_id)) {
                return $menu_id;
            }
            $created_menu = true;
        }

        $created_items = array();
        foreach ((array) (isset($item['items']) ? $item['items'] : array()) as $menu_item) {
            if (!is_array($menu_item) || empty($menu_item['title'])) {
                continue;
            }
            $args = array(
                'menu-item-title' => sanitize_text_field($menu_item['title']),
                'menu-item-status' => 'publish',
                'menu-item-position' => !empty($menu_item['order']) ? absint($menu_item['order']) : 0,
            );
            if (!empty($menu_item['object_id']) && !empty($menu_item['object'])) {
                $args['menu-item-object-id'] = absint($menu_item['object_id']);
                $args['menu-item-object'] = sanitize_key($menu_item['object']);
                $args['menu-item-type'] = !empty($menu_item['type']) ? sanitize_key($menu_item['type']) : 'post_type';
            } else {
                $args['menu-item-url'] = !empty($menu_item['url']) ? esc_url_raw($menu_item['url']) : home_url('/');
                $args['menu-item-type'] = 'custom';
            }
            if (!empty($menu_item['parent'])) {
                $args['menu-item-parent-id'] = absint($menu_item['parent']);
            }
            $item_id = wp_update_nav_menu_item($menu_id, 0, $args);
            if (!is_wp_error($item_id)) {
                $created_items[] = (int) $item_id;
            }
        }

        if (!empty($item['location'])) {
            $location = sanitize_key($item['location']);
            $registered = get_registered_nav_menus();
            if (isset($registered[$location])) {
                $locations = get_theme_mod('nav_menu_locations', array());
                $locations[$location] = $menu_id;
                set_theme_mod('nav_menu_locations', $locations);
            }
        }

        return array(
            'result' => array('entity' => 'menu', 'operation' => $created_menu ? 'create' : 'append', 'id' => $menu_id, 'name' => $name, 'items_created' => count($created_items)),
            'rollback' => array('type' => 'menu_change', 'menu_id' => $menu_id, 'created_menu' => $created_menu, 'created_items' => $created_items),
        );
    }

    private function apply_option($item) {
        $settings = get_option('nine_ai_manager_settings', array());
        if (empty($settings['allow_options'])) {
            return new WP_Error('nine_ai_options_disabled', __('Option changes are disabled. Enable them explicitly in 9 AI Manager settings.', 'nine-code-data' ));
        }
        $key = !empty($item['key']) ? sanitize_key($item['key']) : '';
        if (!$key) {
            return new WP_Error('nine_ai_option_key', __('An option key is required.', 'nine-code-data' ));
        }
        $allowed = apply_filters('nine_ai_manager_allow_option_key', (0 === strpos($key, 'nine_') || 0 === strpos($key, '9_')), $key, $item);
        if (!$allowed) {
            return new WP_Error('nine_ai_option_not_allowed', sprintf(/* translators: %s: option name */ __( 'Option "%s" is not allowed by the current safety rules.', 'nine-code-data' ), $key));
        }
        $exists = false !== get_option($key, false);
        $previous = get_option($key, null);
        $value = isset($item['value']) ? $this->sanitize_meta_value($item['value']) : null;
        update_option($key, $value, false);

        return array(
            'result' => array('entity' => 'option', 'operation' => $exists ? 'update' : 'create', 'key' => $key),
            'rollback' => array('type' => 'option_restore', 'key' => $key, 'existed' => $exists, 'value' => $previous),
        );
    }

    private function apply_plugin_settings($item) {
        $settings = get_option('nine_ai_manager_settings', array());
        if (empty($settings['allow_options'])) {
            return new WP_Error('nine_ai_plugin_settings_disabled', __('Plugin-setting changes are disabled. Enable approved option changes in 9 AI Manager settings.', 'nine-code-data' ));
        }
        $plugin_file = !empty($item['plugin_file']) ? plugin_basename($item['plugin_file']) : '';
        $values = isset($item['settings']) && is_array($item['settings']) ? $item['settings'] : array();
        if (!$plugin_file || !$values) {
            return new WP_Error('nine_ai_plugin_settings_payload', __('plugin_settings requires plugin_file and a settings object.', 'nine-code-data' ));
        }
        $scan = get_option(Nine_AI_Manager_Scanner::OPTION, array());
        $contract = null;
        foreach ((array) (isset($scan['plugins']) ? $scan['plugins'] : array()) as $plugin) {
            if (!empty($plugin['file']) && $plugin['file'] === $plugin_file) {
                $contract = $plugin;
                break;
            }
        }
        if (!$contract) {
            return new WP_Error('nine_ai_plugin_settings_unknown', __('The target plugin is not present in the latest AI readiness scan.', 'nine-code-data' ));
        }
        $allowed = array_values(array_unique(array_merge(
            (array) (isset($contract['detected']['option_keys']) ? $contract['detected']['option_keys'] : array()),
            (array) (isset($contract['detected']['settings']) ? $contract['detected']['settings'] : array())
        )));
        if (!$allowed) {
            return new WP_Error('nine_ai_plugin_settings_none', __('No safe literal option keys were detected for this plugin. Use a native integration instead.', 'nine-code-data' ));
        }
        $rollback = array();
        $changed = array();
        foreach ($values as $key => $value) {
            $key = sanitize_key($key);
            if (!$key || !in_array($key, $allowed, true)) {
                return new WP_Error('nine_ai_plugin_setting_not_allowed', sprintf(/* translators: %s: setting key */ __( 'Setting "%s" is not in the plugin contract and was rejected.', 'nine-code-data' ), $key));
            }
            $exists = false !== get_option($key, false);
            $previous = get_option($key, null);
            update_option($key, $this->sanitize_meta_value($value), false);
            $rollback[] = array('type' => 'option_restore', 'key' => $key, 'existed' => $exists, 'value' => $previous);
            $changed[] = $key;
        }
        return array(
            'result' => array('entity' => 'plugin_settings', 'operation' => 'update', 'plugin_file' => $plugin_file, 'keys' => $changed),
            'rollback' => $rollback,
        );
    }

    private function apply_user($item) {
        $settings = get_option('nine_ai_manager_settings', array());
        if (empty($settings['allow_users'])) {
            return new WP_Error('nine_ai_users_disabled', __('AI user changes are disabled in 9 AI Manager settings.', 'nine-code-data' ));
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $operation = !empty($item['operation']) ? sanitize_key($item['operation']) : 'create';
        $user_id = !empty($item['id']) ? absint($item['id']) : 0;
        if (!$user_id && !empty($item['email'])) {
            $existing = get_user_by('email', sanitize_email($item['email']));
            if ($existing && in_array($operation, array('update', 'upsert'), true)) {
                $user_id = (int) $existing->ID;
            }
        }
        $snapshot = $user_id ? get_userdata($user_id) : null;
        $data = array();
        if ($user_id) {
            $data['ID'] = $user_id;
        } else {
            $login = !empty($item['user_login']) ? sanitize_user($item['user_login'], true) : '';
            $email = !empty($item['email']) ? sanitize_email($item['email']) : '';
            if (!$login || !$email || !is_email($email)) {
                return new WP_Error('nine_ai_user_required', __('Creating a user requires a valid user_login and email.', 'nine-code-data' ));
            }
            $data['user_login'] = $login;
            $data['user_email'] = $email;
            $data['user_pass'] = wp_generate_password(28, true, true);
        }
        foreach (array('display_name', 'first_name', 'last_name', 'description') as $field) {
            if (isset($item[$field])) {
                $data[$field] = sanitize_text_field($item[$field]);
            }
        }
        if (!empty($item['email']) && is_email($item['email'])) {
            $data['user_email'] = sanitize_email($item['email']);
        }
        if (!empty($item['role'])) {
            $roles = wp_roles()->roles;
            $role = sanitize_key($item['role']);
            if (isset($roles[$role])) {
                $data['role'] = $role;
            }
        }
        $saved = wp_insert_user($data);
        if (is_wp_error($saved)) {
            return $saved;
        }
        if (!$snapshot) {
            $rb = array('type' => 'user_created', 'user_id' => (int) $saved);
        } else {
            $rb = array('type' => 'user_restore', 'user_id' => (int) $saved, 'snapshot' => array(
                'user_email' => $snapshot->user_email,
                'display_name' => $snapshot->display_name,
                'first_name' => get_user_meta($snapshot->ID, 'first_name', true),
                'last_name' => get_user_meta($snapshot->ID, 'last_name', true),
                'description' => get_user_meta($snapshot->ID, 'description', true),
                'roles' => (array) $snapshot->roles,
            ));
        }
        return array(
            'result' => array('entity' => 'user', 'operation' => $snapshot ? 'update' : 'create', 'id' => (int) $saved, 'name' => !empty($data['display_name']) ? $data['display_name'] : (isset($data['user_login']) ? $data['user_login'] : 'User')),
            'rollback' => $rb,
        );
    }

    private function apply_media_item($item, $context) {
        $media = array();
        foreach (array('attachment_id', 'asset', 'url', 'alt') as $key) {
            if (isset($item[$key])) {
                $media[$key] = $item[$key];
            }
        }
        if (!$media && !empty($item['media']) && is_array($item['media'])) {
            $media = $item['media'];
        }
        $id = $this->resolve_media($media, 0, $context);
        if (is_wp_error($id)) {
            return $id;
        }
        if (!$id) {
            return new WP_Error('nine_ai_media_empty', __('Media item requires attachment_id, a bundled asset, or a URL.', 'nine-code-data' ));
        }
        if (!empty($item['title'])) {
            wp_update_post(array('ID' => $id, 'post_title' => sanitize_text_field($item['title'])));
        }
        return array(
            'result' => array('entity' => 'media', 'operation' => 'create', 'id' => (int) $id, 'title' => get_the_title($id)),
            'rollback' => empty($media['attachment_id']) ? array('type' => 'media_created', 'attachment_id' => (int) $id) : array(),
        );
    }

    private function apply_integration($item, $context) {
        $key = !empty($item['integration']) ? sanitize_key($item['integration']) : '';
        $integrations = apply_filters('nine_ai_manager_integrations', array());
        if (!$key || empty($integrations[$key]) || empty($integrations[$key]['handler']) || !is_callable($integrations[$key]['handler'])) {
            return new WP_Error('nine_ai_integration_missing', sprintf(/* translators: %s: integration key */ __( 'Integration "%s" is not registered on this site.', 'nine-code-data' ), $key));
        }

        $response = call_user_func($integrations[$key]['handler'], $item, $context);
        if (is_wp_error($response)) {
            return $response;
        }
        if (!is_array($response)) {
            $response = array('message' => __('Integration completed.', 'nine-code-data' ));
        }
        return array(
            'result' => array_merge(array('entity' => 'integration', 'integration' => $key, 'operation' => 'apply'), $response),
            'rollback' => !empty($response['rollback']) ? $response['rollback'] : array(),
        );
    }

    private function resolve_media($media, $post_id, $context) {
        if (!empty($media['attachment_id'])) {
            $id = absint($media['attachment_id']);
            return 'attachment' === get_post_type($id) ? $id : new WP_Error('nine_ai_media_id', __('Featured-media attachment ID does not exist.', 'nine-code-data' ));
        }

        if (!empty($media['asset']) && !empty($context['asset_dir'])) {
            $relative = ltrim(str_replace('\\', '/', $media['asset']), '/');
            if (false !== strpos($relative, '..')) {
                return new WP_Error('nine_ai_asset_path', __('Unsafe asset path.', 'nine-code-data' ));
            }
            $base = realpath($context['asset_dir']);
            $path = realpath(trailingslashit($context['asset_dir']) . $relative);
            if (!$base || !$path || 0 !== strpos($path, $base) || !is_file($path)) {
                return new WP_Error('nine_ai_asset_missing', __('Bundled media asset was not found.', 'nine-code-data' ));
            }
            $id = $this->sideload_local_file($path, $post_id);
            if (!is_wp_error($id) && !empty($media['alt'])) {
                update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($media['alt']));
            }
            return $id;
        }

        if (!empty($media['url'])) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $id = media_sideload_image(esc_url_raw($media['url']), $post_id, null, 'id');
            if (!is_wp_error($id) && !empty($media['alt'])) {
                update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($media['alt']));
            }
            return $id;
        }
        return 0;
    }

    private function sideload_local_file($path, $post_id) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = wp_tempnam(basename($path));
        if (!$tmp || !copy($path, $tmp)) {
            return new WP_Error('nine_ai_asset_copy', __('Could not prepare the bundled media file.', 'nine-code-data' ));
        }
        $file_array = array('name' => sanitize_file_name(basename($path)), 'tmp_name' => $tmp);
        $id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($id) && file_exists($tmp)) {
            wp_delete_file($tmp);
        }
        return $id;
    }

    public function rollback($history_id) {
        $entry = $this->history->get($history_id);
        if (!$entry) {
            return new WP_Error('nine_ai_history_missing', __('History entry not found.', 'nine-code-data' ));
        }
        if ('rolled_back' === $entry['status']) {
            return new WP_Error('nine_ai_already_rolled_back', __('This package has already been rolled back.', 'nine-code-data' ));
        }

        $actions = !empty($entry['rollback']) && is_array($entry['rollback']) ? array_reverse($entry['rollback']) : array();
        $done = 0;
        foreach ($actions as $action) {
            if (empty($action['type'])) {
                continue;
            }
            switch ($action['type']) {
                case 'post_created':
                    if (!empty($action['post_id']) && get_post($action['post_id'])) {
                        wp_trash_post(absint($action['post_id']));
                        $done++;
                    }
                    break;
                case 'post_restore':
                    if (!empty($action['post_id']) && !empty($action['snapshot'])) {
                        $this->restore_post(absint($action['post_id']), $action['snapshot']);
                        $done++;
                    }
                    break;
                case 'term_created':
                    if (!empty($action['term_id']) && !empty($action['taxonomy'])) {
                        wp_delete_term(absint($action['term_id']), sanitize_key($action['taxonomy']));
                        $done++;
                    }
                    break;
                case 'term_restore':
                    if (!empty($action['snapshot'])) {
                        $s = $action['snapshot'];
                        wp_update_term(absint($s['term_id']), sanitize_key($s['taxonomy']), array(
                            'name' => $s['name'], 'slug' => $s['slug'], 'description' => $s['description'], 'parent' => absint($s['parent'])
                        ));
                        $done++;
                    }
                    break;
                case 'menu_change':
                    foreach ((array) (isset($action['created_items']) ? $action['created_items'] : array()) as $item_id) {
                        wp_delete_post(absint($item_id), true);
                    }
                    if (!empty($action['created_menu']) && !empty($action['menu_id'])) {
                        wp_delete_nav_menu(absint($action['menu_id']));
                    }
                    $done++;
                    break;
                case 'media_created':
                    if (!empty($action['attachment_id'])) {
                        wp_delete_attachment(absint($action['attachment_id']), true);
                        $done++;
                    }
                    break;
                case 'user_created':
                    if (!empty($action['user_id'])) {
                        require_once ABSPATH . 'wp-admin/includes/user.php';
                        wp_delete_user(absint($action['user_id']));
                        $done++;
                    }
                    break;
                case 'user_restore':
                    if (!empty($action['user_id']) && !empty($action['snapshot'])) {
                        $uid = absint($action['user_id']);
                        $snap = $action['snapshot'];
                        $data = array('ID' => $uid);
                        foreach (array('user_email','display_name','first_name','last_name','description') as $field) {
                            if (array_key_exists($field, $snap)) {
                                $data[$field] = $snap[$field];
                            }
                        }
                        wp_update_user($data);
                        if (!empty($snap['roles'])) {
                            $u = get_user_by('id', $uid);
                            if ($u) {
                                $u->set_role(reset($snap['roles']));
                            }
                        }
                        $done++;
                    }
                    break;
                case 'plugin_restore':
                    if (!empty($action['backup_zip']) && !empty($action['plugin_file']) && class_exists('Nine_AI_Manager_Plugin_Updater')) {
                        $restored = Nine_AI_Manager_Plugin_Updater::restore_backup($action['backup_zip'], $action['plugin_file'], !empty($action['was_active']));
                        if (!is_wp_error($restored)) {
                            $done++;
                        }
                    }
                    break;
                case 'option_restore':
                    if (!empty($action['key'])) {
                        if (!empty($action['existed'])) {
                            update_option($action['key'], $action['value'], false);
                        } else {
                            delete_option($action['key']);
                        }
                        $done++;
                    }
                    break;
            }
        }

        $this->history->mark_rolled_back($history_id);
        do_action('nine_ai_manager_after_rollback', $entry, $done);
        return $done;
    }

    private function restore_post($post_id, $snapshot) {
        if (empty($snapshot['post']) || !get_post($post_id)) {
            return;
        }
        $post = $snapshot['post'];
        $allowed = array('post_author','post_date','post_date_gmt','post_content','post_title','post_excerpt','post_status','comment_status','ping_status','post_password','post_name','to_ping','pinged','post_modified','post_modified_gmt','post_content_filtered','post_parent','menu_order','post_mime_type');
        $restore = array('ID' => $post_id);
        foreach ($allowed as $field) {
            if (array_key_exists($field, $post)) {
                $restore[$field] = $post[$field];
            }
        }
        wp_update_post(wp_slash($restore));

        $current_meta = array_keys(get_post_meta($post_id));
        foreach ($current_meta as $key) {
            delete_post_meta($post_id, $key);
        }
        foreach ((array) $snapshot['meta'] as $key => $values) {
            foreach ((array) $values as $value) {
                add_post_meta($post_id, $key, maybe_unserialize($value));
            }
        }
        foreach ((array) $snapshot['terms'] as $taxonomy => $ids) {
            if (taxonomy_exists($taxonomy)) {
                wp_set_object_terms($post_id, array_map('intval', (array) $ids), $taxonomy, false);
            }
        }
    }

    private function sanitize_post_status($status) {
        $status = sanitize_key($status);
        return in_array($status, array('draft', 'publish', 'pending', 'private'), true) ? $status : 'draft';
    }

    private function sanitize_content($content) {
        $content = is_string($content) ? $content : '';
        return current_user_can('unfiltered_html') ? $content : wp_kses_post($content);
    }

    private function sanitize_meta_value($value) {
        if (is_array($value)) {
            $out = array();
            foreach ($value as $k => $v) {
                $out[is_string($k) ? sanitize_key($k) : $k] = $this->sanitize_meta_value($v);
            }
            return $out;
        }
        if (is_bool($value) || is_numeric($value) || null === $value) {
            return $value;
        }
        return is_string($value) ? wp_kses_post($value) : sanitize_text_field((string) $value);
    }

    private function delete_directory($dir) {
        if (!$dir || !is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : wp_delete_file($item->getPathname()); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removes this plugin's own staging directory.
        }
        @rmdir($dir); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removes this plugin's own staging directory.
    }
}
