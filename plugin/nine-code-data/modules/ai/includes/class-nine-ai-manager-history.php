<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nine_AI_Manager_History {
    const OPTION = 'nine_ai_manager_history';

    public function all() {
        $items = get_option(self::OPTION, array());
        return is_array($items) ? $items : array();
    }

    public function add($entry) {
        $settings = get_option('nine_ai_manager_settings', array());
        $limit = isset($settings['history_limit']) ? max(10, min(200, absint($settings['history_limit']))) : 50;

        $entry = wp_parse_args($entry, array(
            'id' => wp_generate_uuid4(),
            'created_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'package_name' => 'AI Package',
            'status' => 'applied',
            'summary' => array(),
            'results' => array(),
            'rollback' => array(),
        ));

        $items = $this->all();
        array_unshift($items, $entry);
        $items = array_slice($items, 0, $limit);
        update_option(self::OPTION, $items, false);
        return $entry['id'];
    }

    public function get($id) {
        foreach ($this->all() as $entry) {
            if (!empty($entry['id']) && hash_equals((string) $entry['id'], (string) $id)) {
                return $entry;
            }
        }
        return null;
    }

    public function mark_rolled_back($id) {
        $items = $this->all();
        foreach ($items as &$entry) {
            if (!empty($entry['id']) && hash_equals((string) $entry['id'], (string) $id)) {
                $entry['status'] = 'rolled_back';
                $entry['rolled_back_at'] = current_time('mysql');
                break;
            }
        }
        unset($entry);
        update_option(self::OPTION, $items, false);
    }

    public function clear() {
        delete_option(self::OPTION);
    }
}
