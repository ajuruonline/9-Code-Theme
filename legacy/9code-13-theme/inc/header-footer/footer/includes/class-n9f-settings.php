<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ELHF_F_Settings {
    private static $instance = null;
    private static $settings_cache = null;
    private static $defaults_cache = null;
    private $admin_categories = null;
    private $admin_menus = null;
    private $admin_elementor_templates = null;
    private $admin_blocks = null;
    public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }

    private function __construct() {
        add_action( 'admin_init', [ $this, 'register' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'admin_notices', [ $this, 'legacy_notice' ] );
    }

    public static function default_footer_nav_builder() {
        $base = function( $uid, $label, $icon ) {
            return [ 'uid'=>$uid, 'enabled'=>'yes', 'label'=>$label, 'icon'=>$icon, 'icon_url'=>'', 'source'=>'none', 'nav_menu_id'=>0, 'post_type'=>'', 'taxonomy'=>'', 'limit'=>12, 'links'=>[] ];
        };
        return [
            $base( 'lecturers', 'Lecturers', 'lecturer' ),
            $base( 'courses', 'Courses', 'course' ),
            $base( 'flyers', 'Flyers', 'flyer' ),
            $base( 'workshops', 'Workshops', 'workshop' ),
            $base( 'real-lectures', 'Real Lectures', 'lecture' ),
            $base( 'live-lectures', 'Live Lectures', 'live' ),
        ];
    }

    public static function defaults() {
        if ( null !== self::$defaults_cache ) return self::$defaults_cache;
        $site = get_bloginfo( 'name' );
        self::$defaults_cache = [
            'settings_version' => '1.7.0',
            'enabled' => '',
            'footer_mode' => 'native',
            'elementor_footer_template_id' => 0,
            'suppress_theme_footer' => 'yes',
            'theme_footer_selectors' => 'footer.site-footer, #colophon, .site-footer, .elementor-location-footer',

            'footer_logo_url' => '',
            'footer_description' => get_bloginfo( 'description' ),
            'footer_show_menu' => '',
            'regular_menu_id' => 0,
            'regular_menu_label' => 'Menu',
            'footer_nav_builder' => self::default_footer_nav_builder(),

            'footer_show_categories' => '',
            'category_label' => 'Categories',
            'category_query_mode' => 'all',
            'category_parent' => 0,
            'include_parent' => '',
            'manual_categories' => [],
            'exclude_categories' => [],
            'category_depth' => 2,
            'hide_empty' => 'yes',
            'category_orderby' => 'name',
            'category_order' => 'ASC',

            'footer_show_quick' => '',
            'quick_label' => 'Quick Links',

            'footer_show_modules' => '',

            'footer_manager_enabled' => 'yes',
            'footer_manager_icon_url' => '',
            'footer_manager_icon_dashicon' => 'admin-site-alt3',
            'footer_manager_prefix' => 'Website application managed by',
            'footer_manager_name' => '9igeria Online Limited',
            'footer_manager_url' => '',
            'footer_manager_new_tab' => '',
            'footer_manager_image_override_url' => '',
            'footer_manager_icon_size' => 30,
            'footer_manager_text_size' => 13,
            'footer_manager_text_weight' => 500,
            'footer_manager_name_weight' => 700,
            'footer_manager_align' => 'center',
            'footer_manager_color_mode' => 'auto',
            'footer_manager_auto_contrast' => 'yes',
            'footer_manager_bg' => '#F7F7F8',
            'footer_manager_text_color' => '#4B5563',
            'footer_append_endcap_to_elementor' => 'yes',

            'footer_copyright' => '© ' . date( 'Y' ) . ' ' . $site,
            'footer_back_to_top' => 'yes',

            'footer_color_mode' => 'auto',
            'footer_auto_contrast' => 'yes',
            'footer_bg' => '#121416',
            'footer_text_color' => '#FFFFFF',
            'footer_link_color' => '#FFFFFF',
            'footer_accent' => '#D62828',
            'footer_max_width' => 1280,
            'footer_padding_top' => 36,
            'footer_padding_bottom' => 20,
            'footer_logo_width' => 210,
            'footer_column_gap' => 30,
        ] + self::repeater_defaults();
        return self::$defaults_cache;
    }

    private static function repeater_defaults() {
        $out = [];
        for ( $i = 1; $i <= 8; $i++ ) {
            $out["quick_{$i}_enabled"] = '';
            $out["quick_{$i}_label"] = [ 1 => 'About', 2 => 'Contact', 3 => 'Privacy' ][$i] ?? '';
            $out["quick_{$i}_url"] = '';
        }
        for ( $i = 1; $i <= 4; $i++ ) {
            $out["footer_slot_{$i}_enabled"] = '';
            $out["footer_slot_{$i}_title"] = '';
            $out["footer_slot_{$i}_source"] = 'shortcode';
            $out["footer_slot_{$i}_position"] = 'top';
            $out["footer_slot_{$i}_width"] = 'full';
            $out["footer_slot_{$i}_color_behavior"] = 'inherit';
            $out["footer_slot_{$i}_content"] = '';
            $out["footer_slot_{$i}_embed_html"] = '';
            $out["footer_slot_{$i}_embed_height"] = 320;
            $out["footer_slot_{$i}_image_url"] = '';
            $out["footer_slot_{$i}_image_link"] = '';
            $out["footer_slot_{$i}_image_alt"] = '';
            $out["footer_slot_{$i}_image_fit"] = 'contain';
            $out["footer_slot_{$i}_template"] = 0;
            $out["footer_slot_{$i}_block"] = 0;
            $out["footer_slot_{$i}_integration"] = '';
        }
        return $out;
    }

    public static function all( $refresh = false ) {
        if ( ! $refresh && null !== self::$settings_cache ) return self::$settings_cache;
        $stored = get_option( 'n9f_settings', [] );
        $stored = is_array( $stored ) ? $stored : [];
        $version = $stored['settings_version'] ?? '1.0.0';
        if ( version_compare( $version, '1.1.0', '<' ) ) {
            if ( empty( $stored['footer_manager_image_override_url'] ) && ! empty( $stored['footer_manager_logo_url'] ) ) {
                $stored['footer_manager_image_override_url'] = esc_url_raw( $stored['footer_manager_logo_url'] );
            }
            $stored['settings_version'] = '1.1.0';
            update_option( 'n9f_settings', $stored );
        }
        if ( version_compare( $stored['settings_version'] ?? '1.1.0', '1.2.0', '<' ) ) {
            $stored['settings_version'] = '1.2.0';
            update_option( 'n9f_settings', $stored );
        }
        if ( version_compare( $stored['settings_version'] ?? '1.2.0', '1.3.1', '<' ) ) {
            // Old v1.2 defaults forced a navy/white palette. If the site still
            // has those untouched values, migrate it to the unified theme mode.
            $old_default_colors = [ 'footer_bg'=>'#0B1F3A', 'footer_text_color'=>'#FFFFFF', 'footer_link_color'=>'#FFFFFF' ];
            $customized = false;
            foreach ( $old_default_colors as $k => $v ) {
                if ( isset( $stored[$k] ) && strtoupper( (string) $stored[$k] ) !== strtoupper( $v ) ) { $customized = true; break; }
            }
            $stored['footer_color_mode'] = $customized ? 'custom' : 'auto';
            $manager_custom = ( isset($stored['footer_manager_bg']) && strtoupper((string)$stored['footer_manager_bg']) !== '#F7F7F8' ) || ( isset($stored['footer_manager_text_color']) && strtoupper((string)$stored['footer_manager_text_color']) !== '#4B5563' );
            $stored['footer_manager_color_mode'] = $manager_custom ? 'custom' : 'auto';
            for ( $i=1; $i<=4; $i++ ) {
                if ( empty( $stored["footer_slot_{$i}_position"] ) ) $stored["footer_slot_{$i}_position"] = 'top';
                if ( empty( $stored["footer_slot_{$i}_color_behavior"] ) ) $stored["footer_slot_{$i}_color_behavior"] = 'inherit';
            }
            $stored['settings_version'] = '1.3.1';
            update_option( 'n9f_settings', $stored );
        }
        if ( version_compare( $stored['settings_version'] ?? '1.3.1', '1.3.2', '<' ) ) {
            // v1.3.2 changes Automatic mode from transparent/inherited to a
            // safe dark footer shell with automatic contrast protection.
            if ( ! isset( $stored['footer_auto_contrast'] ) ) $stored['footer_auto_contrast'] = 'yes';
            if ( ! isset( $stored['footer_manager_auto_contrast'] ) ) $stored['footer_manager_auto_contrast'] = 'yes';
            $stored['settings_version'] = '1.3.2';
            update_option( 'n9f_settings', $stored );
        }
        if ( version_compare( $stored['settings_version'] ?? '1.3.2', '1.4.0', '<' ) ) {
            // Historical version step retained only for deterministic upgrades.
            // The discontinued companion layer is no longer created or restored.
            $stored['settings_version'] = '1.4.0';
            update_option( 'n9f_settings', $stored );
        }
        if ( version_compare( $stored['settings_version'] ?? '1.4.0', '1.5.0', '<' ) ) {
            // v1.5 makes footer navigation explicitly administrator-selected.
            // Six preferred slots are placed in the editor, but no content is
            // inferred from menus, taxonomies or learning post types.
            if ( empty( $stored['footer_nav_builder'] ) || ! is_array( $stored['footer_nav_builder'] ) ) {
                $stored['footer_nav_builder'] = self::default_footer_nav_builder();
            }
            $stored['settings_version'] = '1.5.0';
            update_option( 'n9f_settings', $stored );
        }
        if ( version_compare( $stored['settings_version'] ?? '1.5.0', '1.6.0', '<' ) ) {
            // v1.6 removes the discontinued companion layer and introduces
            // an explicit OFF-by-default Theme footer master switch.
            foreach ( array_keys( $stored ) as $key ) {
                if ( 0 === strpos( (string) $key, 'learning_' ) ) unset( $stored[$key] );
            }
            $stored['enabled'] = '';
            $stored['settings_version'] = '1.6.0';
            update_option( 'n9f_settings', $stored );
        }
        if ( version_compare( $stored['settings_version'] ?? '1.6.0', '1.7.0', '<' ) ) {
            // v1.7 repairs the master switch contract. One upgrade reset to OFF
            // prevents legacy or imported state from silently keeping Theme chrome alive.
            $stored['enabled'] = '';
            $stored['settings_version'] = '1.7.0';
            update_option( 'n9f_settings', $stored );
        }
        self::$settings_cache = wp_parse_args( $stored, self::defaults() );
        return self::$settings_cache;
    }

    public static function is_enabled() { $s = self::all(); return 'yes' === (string) ( $s['enabled'] ?? '' ); }

    public static function activate() {
        $existing = get_option( 'n9f_settings', null );
        if ( ! is_array( $existing ) || empty( $existing ) ) {
            $legacy = get_option( 'baehf_settings', [] );
            if ( is_array( $legacy ) && $legacy ) {
                $import = wp_parse_args( $legacy, self::defaults() );
                // v1.1 separates the management icon from the image override.
                if ( ! empty( $legacy['footer_manager_logo_url'] ) && empty( $legacy['footer_manager_image_override_url'] ) ) {
                    $import['footer_manager_image_override_url'] = esc_url_raw( $legacy['footer_manager_logo_url'] );
                }
                $import['enabled'] = '';
                $import['settings_version'] = '1.7.0';
                update_option( 'n9f_settings', $import );
            } else {
                update_option( 'n9f_settings', self::defaults() );
            }
        }
    }

    public function register() {
        register_setting( 'n9f_group', 'n9f_settings', [ $this, 'sanitize' ] );
    }

    public function sanitize( $in ) {
        $old = self::all();
        $in = is_array( $in ) ? $in : [];
        $out = $old;
        $out['settings_version'] = '1.7.0';

        $checks = [
            'enabled','suppress_theme_footer','footer_show_menu','footer_show_categories','include_parent','hide_empty','footer_show_quick','footer_show_modules',
            'footer_manager_enabled','footer_manager_new_tab','footer_append_endcap_to_elementor','footer_back_to_top','footer_auto_contrast','footer_manager_auto_contrast'
        ];
        for ( $i = 1; $i <= 8; $i++ ) $checks[] = "quick_{$i}_enabled";
        for ( $i = 1; $i <= 4; $i++ ) $checks[] = "footer_slot_{$i}_enabled";
        foreach ( $checks as $k ) $out[$k] = isset( $in[$k] ) && 'yes' === $in[$k] ? 'yes' : '';

        $out['footer_mode'] = in_array( $in['footer_mode'] ?? 'native', [ 'native','elementor' ], true ) ? $in['footer_mode'] : 'native';
        $out['footer_color_mode'] = in_array( $in['footer_color_mode'] ?? 'auto', [ 'auto','custom' ], true ) ? $in['footer_color_mode'] : 'auto';
        $out['footer_manager_color_mode'] = in_array( $in['footer_manager_color_mode'] ?? 'auto', [ 'auto','custom' ], true ) ? $in['footer_manager_color_mode'] : 'auto';
        $out['elementor_footer_template_id'] = absint( $in['elementor_footer_template_id'] ?? 0 );
        $out['regular_menu_id'] = absint( $in['regular_menu_id'] ?? 0 );

        $out['footer_nav_builder'] = $this->sanitize_footer_nav_builder( $in['footer_nav_builder'] ?? ( $old['footer_nav_builder'] ?? self::default_footer_nav_builder() ) );
        $out['category_parent'] = absint( $in['category_parent'] ?? 0 );
        $out['category_depth'] = max( 1, min( 3, absint( $in['category_depth'] ?? 2 ) ) );
        $out['category_query_mode'] = in_array( $in['category_query_mode'] ?? 'all', [ 'all','top_level','children_of','manual' ], true ) ? $in['category_query_mode'] : 'all';
        $out['category_orderby'] = in_array( $in['category_orderby'] ?? 'name', [ 'name','count','id','slug' ], true ) ? $in['category_orderby'] : 'name';
        $out['category_order'] = 'DESC' === ( $in['category_order'] ?? 'ASC' ) ? 'DESC' : 'ASC';
        $out['manual_categories'] = array_values( array_filter( array_map( 'absint', (array) ( $in['manual_categories'] ?? [] ) ) ) );
        $out['exclude_categories'] = array_values( array_filter( array_map( 'absint', (array) ( $in['exclude_categories'] ?? [] ) ) ) );

        $texts = [
            'regular_menu_label','category_label','quick_label','footer_manager_prefix','footer_manager_name','footer_copyright','footer_manager_icon_dashicon'
        ];
        foreach ( $texts as $k ) $out[$k] = sanitize_text_field( $in[$k] ?? '' );
        $out['footer_description'] = sanitize_textarea_field( $in['footer_description'] ?? '' );
        $out['theme_footer_selectors'] = sanitize_textarea_field( $in['theme_footer_selectors'] ?? '' );

        $urls = [ 'footer_logo_url','footer_manager_icon_url','footer_manager_url','footer_manager_image_override_url' ];
        foreach ( $urls as $k ) $out[$k] = esc_url_raw( $in[$k] ?? '' );

        $out['footer_manager_align'] = in_array( $in['footer_manager_align'] ?? 'center', [ 'left','center','right' ], true ) ? $in['footer_manager_align'] : 'center';
        foreach ( [ 'footer_bg','footer_text_color','footer_link_color','footer_accent','footer_manager_bg','footer_manager_text_color' ] as $k ) {
            $out[$k] = sanitize_hex_color( $in[$k] ?? '' ) ?: ( self::defaults()[$k] ?? '#000000' );
        }
        foreach ( [
            'footer_manager_icon_size' => [ 12, 100 ], 'footer_manager_text_size' => [ 9, 28 ], 'footer_manager_text_weight' => [ 300, 900 ], 'footer_manager_name_weight' => [ 300, 900 ],
            'footer_max_width' => [ 600, 2400 ], 'footer_padding_top' => [ 0, 160 ], 'footer_padding_bottom' => [ 0, 160 ], 'footer_logo_width' => [ 40, 600 ], 'footer_column_gap' => [ 0, 100 ]
        ] as $k => $range ) {
            $v = absint( $in[$k] ?? self::defaults()[$k] );
            $out[$k] = max( $range[0], min( $range[1], $v ) );
        }

        for ( $i = 1; $i <= 8; $i++ ) {
            $out["quick_{$i}_label"] = sanitize_text_field( $in["quick_{$i}_label"] ?? '' );
            $out["quick_{$i}_url"] = esc_url_raw( $in["quick_{$i}_url"] ?? '' );
        }
        for ( $i = 1; $i <= 4; $i++ ) {
            $out["footer_slot_{$i}_title"] = sanitize_text_field( $in["footer_slot_{$i}_title"] ?? '' );
            $out["footer_slot_{$i}_source"] = in_array( $in["footer_slot_{$i}_source"] ?? 'shortcode', [ 'shortcode','embed','image','elementor','block','widget_area','integration' ], true ) ? $in["footer_slot_{$i}_source"] : 'shortcode';
            $out["footer_slot_{$i}_position"] = in_array( $in["footer_slot_{$i}_position"] ?? 'top', [ 'top','below_grid','before_endcap' ], true ) ? $in["footer_slot_{$i}_position"] : 'top';
            $out["footer_slot_{$i}_width"] = in_array( $in["footer_slot_{$i}_width"] ?? 'full', [ 'full','half','third' ], true ) ? $in["footer_slot_{$i}_width"] : 'full';
            $out["footer_slot_{$i}_color_behavior"] = in_array( $in["footer_slot_{$i}_color_behavior"] ?? 'inherit', [ 'inherit','preserve' ], true ) ? $in["footer_slot_{$i}_color_behavior"] : 'inherit';
            $out["footer_slot_{$i}_content"] = wp_kses_post( $in["footer_slot_{$i}_content"] ?? '' );
            $allowed_embed = [ 'iframe' => [ 'src'=>true,'title'=>true,'width'=>true,'height'=>true,'frameborder'=>true,'allow'=>true,'allowfullscreen'=>true,'loading'=>true,'referrerpolicy'=>true,'class'=>true,'style'=>true,'sandbox'=>true ], 'div'=>[ 'class'=>true ], 'p'=>[ 'class'=>true ], 'a'=>[ 'href'=>true,'target'=>true,'rel'=>true,'class'=>true ] ];
            $out["footer_slot_{$i}_embed_html"] = wp_kses( $in["footer_slot_{$i}_embed_html"] ?? '', $allowed_embed );
            $out["footer_slot_{$i}_embed_height"] = max( 120, min( 1200, absint( $in["footer_slot_{$i}_embed_height"] ?? 320 ) ) );
            $out["footer_slot_{$i}_image_url"] = esc_url_raw( $in["footer_slot_{$i}_image_url"] ?? '' );
            $out["footer_slot_{$i}_image_link"] = esc_url_raw( $in["footer_slot_{$i}_image_link"] ?? '' );
            $out["footer_slot_{$i}_image_alt"] = sanitize_text_field( $in["footer_slot_{$i}_image_alt"] ?? '' );
            $out["footer_slot_{$i}_image_fit"] = in_array( $in["footer_slot_{$i}_image_fit"] ?? 'contain', [ 'contain','cover','natural' ], true ) ? $in["footer_slot_{$i}_image_fit"] : 'contain';
            $out["footer_slot_{$i}_template"] = absint( $in["footer_slot_{$i}_template"] ?? 0 );
            $out["footer_slot_{$i}_block"] = absint( $in["footer_slot_{$i}_block"] ?? 0 );
            $out["footer_slot_{$i}_integration"] = sanitize_key( $in["footer_slot_{$i}_integration"] ?? '' );
        }
        self::$settings_cache = $out;
        return $out;
    }

    public function menu() {}

    public function admin_assets( $hook ) {
        if ( 'toplevel_page_elearning-click-header-footer' !== $hook ) return;
        wp_enqueue_media();
        wp_enqueue_style( 'n9f-admin', ELHF_F_URL . 'assets/css/admin.css', [], ELHF_F_VERSION );
        wp_enqueue_script( 'n9f-admin', ELHF_F_URL . 'assets/js/admin.js', [ 'jquery' ], ELHF_F_VERSION, true );
    }

    public function legacy_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( defined( 'BAEHF_FILE' ) || class_exists( 'BAEHF_Plugin' ) ) {
            echo '<div class="notice notice-warning"><p><strong>9 Footer:</strong> the older combined 9 Header & Footer plugin appears active. Deactivate the combined plugin to avoid duplicate footer output.</p></div>';
        }
    }

    private function check( $name, $s, $label ) {
        printf( '<label class="n9f-check"><input type="checkbox" name="n9f_settings[%1$s]" value="yes" %2$s> <span>%3$s</span></label>', esc_attr( $name ), checked( 'yes', $s[$name] ?? '', false ), esc_html( $label ) );
    }

    private function field( $label, $html, $help = '' ) {
        echo '<div class="n9f-field"><label class="n9f-field__label">' . esc_html( $label ) . '</label><div class="n9f-field__control">' . $html;
        if ( $help ) echo '<p class="description">' . esc_html( $help ) . '</p>';
        echo '</div></div>';
    }

    private function media( $name, $value, $button = 'Choose image' ) {
        $id = 'n9f-' . sanitize_html_class( $name );
        echo '<div class="n9f-media"><input id="' . esc_attr( $id ) . '" class="regular-text n9f-media-url" type="url" name="n9f_settings[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '"><button type="button" class="button n9f-media-button" data-target="' . esc_attr( $id ) . '">' . esc_html( $button ) . '</button><button type="button" class="button-link-delete n9f-media-clear" data-target="' . esc_attr( $id ) . '">Clear</button></div>';
    }

    private function select( $name, $value, $options ) {
        $html = '<select name="n9f_settings[' . esc_attr( $name ) . ']">';
        foreach ( $options as $k => $label ) $html .= '<option value="' . esc_attr( $k ) . '" ' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $label ) . '</option>';
        return $html . '</select>';
    }

    private function admin_categories() {
        if ( null === $this->admin_categories ) {
            $this->admin_categories = get_categories( [ 'hide_empty'=>false, 'orderby'=>'name', 'order'=>'ASC' ] );
            if ( ! is_array( $this->admin_categories ) ) $this->admin_categories = [];
        }
        return $this->admin_categories;
    }

    private function admin_menus() {
        if ( null === $this->admin_menus ) {
            $this->admin_menus = wp_get_nav_menus();
            if ( ! is_array( $this->admin_menus ) ) $this->admin_menus = [];
        }
        return $this->admin_menus;
    }

    private function admin_elementor_templates() {
        if ( null === $this->admin_elementor_templates ) {
            $this->admin_elementor_templates = post_type_exists( 'elementor_library' ) ? get_posts( [ 'post_type'=>'elementor_library', 'post_status'=>'publish', 'numberposts'=>100, 'orderby'=>'title', 'order'=>'ASC', 'no_found_rows'=>true ] ) : [];
        }
        return $this->admin_elementor_templates;
    }

    private function admin_blocks() {
        if ( null === $this->admin_blocks ) {
            $this->admin_blocks = post_type_exists( 'wp_block' ) ? get_posts( [ 'post_type'=>'wp_block', 'post_status'=>'publish', 'numberposts'=>100, 'orderby'=>'title', 'order'=>'ASC', 'no_found_rows'=>true ] ) : [];
        }
        return $this->admin_blocks;
    }

    private function terms_multi( $name, $selected_ids ) {
        $terms = $this->admin_categories();
        $html = '<select class="n9f-multi" multiple size="8" name="n9f_settings[' . esc_attr( $name ) . '][]">';
        foreach ( $terms as $term ) $html .= '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( in_array( (int) $term->term_id, array_map( 'intval', (array) $selected_ids ), true ), true, false ) . '>' . esc_html( $term->name ) . '</option>';
        return $html . '</select>';
    }

    private function menu_options( $selected_id ) {
        $menus = $this->admin_menus();
        $html = '<select name="n9f_settings[regular_menu_id]"><option value="0">— Select menu —</option>';
        foreach ( $menus as $menu ) $html .= '<option value="' . esc_attr( $menu->term_id ) . '" ' . selected( (int) $selected_id, (int) $menu->term_id, false ) . '>' . esc_html( $menu->name ) . '</option>';
        return $html . '</select>';
    }

    private static function footer_nav_source_options() {
        return [ 'none'=>'Not configured — show nothing', 'wp_menu'=>'Selected WordPress menu', 'post_type'=>'Selected post type', 'taxonomy'=>'Selected taxonomy', 'manual_links'=>'Manual links' ];
    }

    private static function footer_nav_icon_options() {
        return [ 'none'=>'No icon', 'lecturer'=>'Lecturer/person', 'course'=>'Course/book', 'flyer'=>'Flyer/document', 'workshop'=>'Workshop/calendar', 'lecture'=>'Real lecture/play', 'live'=>'Live lecture/broadcast', 'menu'=>'Menu/list', 'grid'=>'Grid', 'link'=>'Link' ];
    }

    private function footer_nav_post_types() {
        $out = [];
        foreach ( (array) get_post_types( [ 'public'=>true ], 'objects' ) as $pt ) {
            if ( ! is_object($pt) || empty($pt->name) || 'attachment' === $pt->name ) continue;
            $out[$pt->name] = ( ! empty($pt->labels->singular_name) ? $pt->labels->singular_name : $pt->name ) . ' (' . $pt->name . ')';
        }
        asort($out,SORT_NATURAL|SORT_FLAG_CASE); return $out;
    }

    private function footer_nav_taxonomies() {
        $out = [];
        foreach ( (array) get_taxonomies( [ 'public'=>true ], 'objects' ) as $tax ) {
            if ( ! is_object($tax) || empty($tax->name) ) continue;
            $out[$tax->name] = ( ! empty($tax->labels->singular_name) ? $tax->labels->singular_name : $tax->name ) . ' (' . $tax->name . ')';
        }
        asort($out,SORT_NATURAL|SORT_FLAG_CASE); return $out;
    }

    private function footer_nav_select_html( $name, $selected, $options ) {
        $html='<select name="n9f_settings[footer_nav_builder]['.esc_attr($name).']">';
        foreach($options as $k=>$label)$html.='<option value="'.esc_attr($k).'" '.selected((string)$selected,(string)$k,false).'>'.esc_html($label).'</option>';
        return $html.'</select>';
    }

    private function footer_nav_builder_ui( $s ) {
        $rows=(array)($s['footer_nav_builder']??self::default_footer_nav_builder());
        $menus=$this->admin_menus(); $pts=$this->footer_nav_post_types(); $taxes=$this->footer_nav_taxonomies();
        echo '<div class="n9f-repeater-grid n9f-nav-builder">';
        foreach($rows as $i=>$r){
            $base='n9f_settings[footer_nav_builder]['.$i.']';
            echo '<div class="n9f-mini-card n9f-nav-builder__card"><strong>'.esc_html($r['label']??('Slot '.($i+1))).'</strong>';
            echo '<input type="hidden" name="'.esc_attr($base.'[uid]').'" value="'.esc_attr($r['uid']??('slot-'.$i)).'">';
            echo '<label class="n9f-check"><input type="checkbox" name="'.esc_attr($base.'[enabled]').'" value="yes" '.checked('yes',$r['enabled']??'',false).'> <span>Enable slot</span></label>';
            echo '<label>Label<input type="text" name="'.esc_attr($base.'[label]').'" value="'.esc_attr($r['label']??'').'"></label>';
            echo '<label>Icon<select name="'.esc_attr($base.'[icon]').'">'; foreach(self::footer_nav_icon_options() as $k=>$lab)echo '<option value="'.esc_attr($k).'" '.selected($r['icon']??'none',$k,false).'>'.esc_html($lab).'</option>'; echo '</select></label>';
            echo '<label>Custom icon URL<input type="url" name="'.esc_attr($base.'[icon_url]').'" value="'.esc_attr($r['icon_url']??'').'" placeholder="Optional image URL"></label>';
            echo '<label>Content source<select name="'.esc_attr($base.'[source]').'">'; foreach(self::footer_nav_source_options() as $k=>$lab)echo '<option value="'.esc_attr($k).'" '.selected($r['source']??'none',$k,false).'>'.esc_html($lab).'</option>'; echo '</select><small>Nothing renders until a source is selected.</small></label>';
            echo '<label>WordPress menu<select name="'.esc_attr($base.'[nav_menu_id]').'"><option value="0">— Select menu —</option>'; foreach($menus as $menu)echo '<option value="'.absint($menu->term_id).'" '.selected(absint($r['nav_menu_id']??0),absint($menu->term_id),false).'>'.esc_html($menu->name).'</option>'; echo '</select></label>';
            echo '<label>Post type<select name="'.esc_attr($base.'[post_type]').'"><option value="">— Select post type —</option>'; foreach($pts as $k=>$lab)echo '<option value="'.esc_attr($k).'" '.selected($r['post_type']??'',$k,false).'>'.esc_html($lab).'</option>'; echo '</select></label>';
            echo '<label>Taxonomy<select name="'.esc_attr($base.'[taxonomy]').'"><option value="">— Select taxonomy —</option>'; foreach($taxes as $k=>$lab)echo '<option value="'.esc_attr($k).'" '.selected($r['taxonomy']??'',$k,false).'>'.esc_html($lab).'</option>'; echo '</select></label>';
            echo '<label>Maximum items<input type="number" min="1" max="50" name="'.esc_attr($base.'[limit]').'" value="'.absint($r['limit']??12).'"></label>';
            echo '<div><b>Manual links</b>';
            for($j=0;$j<4;$j++){ $ln=(array)($r['links'][$j]??[]); echo '<div class="n9f-inline"><input type="text" name="'.esc_attr($base.'[links]['.$j.'][text]').'" value="'.esc_attr($ln['text']??'').'" placeholder="Link label"><input type="url" name="'.esc_attr($base.'[links]['.$j.'][url]').'" value="'.esc_attr($ln['url']??'').'" placeholder="https://…"></div>'; }
            echo '</div></div>';
        }
        echo '</div>';
    }

    private function sanitize_footer_nav_builder( $rows ) {
        $out=[]; $sources=array_keys(self::footer_nav_source_options()); $icons=array_keys(self::footer_nav_icon_options());
        foreach(array_slice((array)$rows,0,10) as $i=>$r){ if(!is_array($r))continue; $source=sanitize_key($r['source']??'none'); if(!in_array($source,$sources,true))$source='none'; $icon=sanitize_key($r['icon']??'none'); if(!in_array($icon,$icons,true))$icon='none'; $links=[]; foreach(array_slice((array)($r['links']??[]),0,8) as $ln){ if(!is_array($ln))continue; $text=sanitize_text_field($ln['text']??''); $url=esc_url_raw($ln['url']??''); if($text||$url)$links[]=['text'=>$text,'url'=>$url]; }
            $out[]=['uid'=>sanitize_key($r['uid']??('slot-'.$i)),'enabled'=>isset($r['enabled'])?'yes':'','label'=>sanitize_text_field($r['label']??''),'icon'=>$icon,'icon_url'=>esc_url_raw($r['icon_url']??''),'source'=>$source,'nav_menu_id'=>absint($r['nav_menu_id']??0),'post_type'=>sanitize_key($r['post_type']??''),'taxonomy'=>sanitize_key($r['taxonomy']??''),'limit'=>max(1,min(50,absint($r['limit']??12))),'links'=>$links];
        }
        return $out?:self::default_footer_nav_builder();
    }

    private function elementor_templates( $selected ) {
        $html = '<select name="n9f_settings[elementor_footer_template_id]"><option value="0">Select template</option>';
        $posts = $this->admin_elementor_templates();
        foreach ( $posts as $post ) $html .= '<option value="' . esc_attr( $post->ID ) . '" ' . selected( (int) $selected, (int) $post->ID, false ) . '>' . esc_html( $post->post_title ) . '</option>';
        return $html . '</select>';
    }

    private function blocks( $selected ) {
        $html = '<select name="n9f_settings[__BLOCK_NAME__]"><option value="0">Select reusable block</option>';
        $posts = $this->admin_blocks();
        foreach ( $posts as $post ) $html .= '<option value="' . esc_attr( $post->ID ) . '" ' . selected( (int) $selected, (int) $post->ID, false ) . '>' . esc_html( $post->post_title ) . '</option>';
        return $html . '</select>';
    }

    private function section_open( $id, $title, $summary, $open = false ) {
        echo '<details class="n9f-admin-tab" id="n9f-tab-' . esc_attr( $id ) . '" ' . ( $open ? 'open' : '' ) . '><summary><span><strong>' . esc_html( $title ) . '</strong><small>' . esc_html( $summary ) . '</small></span><span class="n9f-tab-chevron" aria-hidden="true">⌄</span></summary><div class="n9f-tab-body">';
    }
    private function section_close() { echo '</div></details>'; }

    public function page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $s = self::all();
        $defaults = self::defaults();
        settings_errors();
        $tabs = [
            'sitewide' => 'Site-wide', 'identity' => 'Identity', 'navigation' => 'Navigation', 'learning' => 'Learning', 'modules' => 'Embeds / Modules', 'manager' => 'Managed by', 'copyright' => 'Copyright', 'design' => 'Design', 'advanced' => 'Advanced'
        ];
        ?>
        <div class="wrap n9f-admin-wrap">
            <div class="n9f-admin-heading"><div><h1>9 Footer</h1><p>WordPress-native footer controls. Elementor is optional.</p></div><button form="n9f-settings-form" type="submit" class="button button-primary n9f-save-top">Save Footer</button></div>
            <nav class="n9f-quick-tabs" aria-label="Footer settings sections">
                <?php foreach ( $tabs as $id => $label ): ?><button type="button" data-n9f-jump="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></button><?php endforeach; ?>
            </nav>
            <div class="n9f-settings-search"><label class="screen-reader-text" for="n9f-settings-search">Find a footer setting</label><input id="n9f-settings-search" type="search" placeholder="Find a footer setting…" autocomplete="off"><button type="button" class="button" data-n9f-search-clear>Clear</button></div>

            <form id="n9f-settings-form" method="post" action="options.php">
                <?php settings_fields( 'n9f_group' ); ?>
                <div class="elhh-master-switch"><div><strong>Footer</strong><span>OFF means no Theme footer output, including Elementor/global-copy rendering.</span></div><label class="elhh-switch"><input type="hidden" name="n9f_settings[enabled]" value=""><input type="checkbox" name="n9f_settings[enabled]" value="yes" <?php checked('yes',$s['enabled']??''); ?>><span aria-hidden="true"></span><b><?php echo 'yes'===($s['enabled']??'')?'ON':'OFF'; ?></b></label></div>

                <?php $this->section_open( 'sitewide', 'Site-wide Footer', 'Choose Native 9 or an optional Elementor override.', true ); ?>
                    <?php $this->field( 'Footer source', $this->select( 'footer_mode', $s['footer_mode'], [ 'native' => '9 Footer — WordPress Native (default)', 'elementor' => 'Elementor Template Override' ] ) ); ?>
                    <?php $this->field( 'Elementor footer template', $this->elementor_templates( $s['elementor_footer_template_id'] ), 'Used only when Footer source is Elementor Template Override.' ); ?>
                    <?php ob_start(); $this->check( 'suppress_theme_footer', $s, 'Hide the theme/other footer while 9 Footer is active' ); $h = ob_get_clean(); $this->field( 'Theme footer', $h ); ?>
                <?php $this->section_close(); ?>

                <?php $this->section_open( 'identity', 'Footer Identity', 'Logo, description and basic footer identity.' ); ?>
                    <?php ob_start(); $this->media( 'footer_logo_url', $s['footer_logo_url'], 'Choose footer logo' ); $h=ob_get_clean(); $this->field( 'Footer logo', $h ); ?>
                    <?php $this->field( 'Footer description', '<textarea rows="3" name="n9f_settings[footer_description]">' . esc_textarea( $s['footer_description'] ) . '</textarea>' ); ?>
                <?php $this->section_close(); ?>

                <?php $this->section_open( 'navigation', 'Footer Navigation', 'Select exactly what appears in each footer menu slot.' ); ?>
                    <div class="n9f-callout"><strong>Selection-controlled footer</strong><span>Lecturers, Courses, Flyers, Workshops, Real Lectures and Live Lectures are placed as editable slots. A slot stays invisible until you choose a WordPress menu, post type, taxonomy or manual links. Labels and icons are independent of the content source.</span></div>
                    <?php $this->footer_nav_builder_ui( $s ); ?>
                    <details class="n9f-subtab"><summary>Legacy footer navigation settings — preserved only</summary><div class="n9f-subtab-body"><p class="description">Older Regular Menu, Categories and Quick Links settings are kept in the database for rollback compatibility, but v1.5 navigation is rendered only from the selection-controlled slots above.</p></div></details>
                <?php $this->section_close(); ?>

                <?php $this->section_open( 'modules', 'Embeds, Media & Footer Modules', 'Place Google embeds, images, Featured Media, Category/Post Manager, templates, blocks, widgets and future 9 plugins anywhere in the footer structure.' ); ?>
                    <?php ob_start(); $this->check( 'footer_show_modules', $s, 'Enable footer module area' ); $h=ob_get_clean(); $this->field( 'Module area', $h, 'All module slots are optional.' ); ?>
                    <div class="n9f-callout"><strong>Embed-friendly footer space</strong><span>Use any slot for Google Maps/Forms/Drive iframe code, an image/banner, shortcode, block, widget area, Elementor template or 9-plugin output. Choose where that slot appears in the footer.</span></div>
                    <?php for($i=1;$i<=4;$i++): ?>
                        <details class="n9f-subtab"><summary>Module Slot <?php echo $i; ?></summary><div class="n9f-subtab-body">
                            <?php ob_start(); $this->check("footer_slot_{$i}_enabled",$s,'Enable this slot'); $h=ob_get_clean(); $this->field( 'Status', $h ); ?>
                            <?php $this->field( 'Optional heading', '<input type="text" name="n9f_settings[footer_slot_' . $i . '_title]" value="' . esc_attr($s["footer_slot_{$i}_title"]) . '">' ); ?>
                            <?php $this->field( 'Position', $this->select( "footer_slot_{$i}_position", $s["footer_slot_{$i}_position"], [ 'top'=>'Above footer columns','below_grid'=>'Below footer columns','before_endcap'=>'Before management / copyright' ] ) ); ?>
                            <?php $this->field( 'Source', $this->select( "footer_slot_{$i}_source", $s["footer_slot_{$i}_source"], [ 'shortcode'=>'Shortcode / embeddable URL','embed'=>'Google / iframe embed code','image'=>'Image / banner','elementor'=>'Elementor saved template','block'=>'Reusable WordPress block','widget_area'=>'WordPress widget area','integration'=>'9 Suite / plugin integration' ] ) ); ?>
                            <?php $this->field( 'Width', $this->select( "footer_slot_{$i}_width", $s["footer_slot_{$i}_width"], [ 'full'=>'Full width','half'=>'Half width','third'=>'One-third' ] ) ); ?>
                            <?php $this->field( 'Colour behaviour', $this->select( "footer_slot_{$i}_color_behavior", $s["footer_slot_{$i}_color_behavior"], [ 'inherit'=>'Blend with footer / theme colours','preserve'=>'Preserve widget/embed colours' ] ), 'Use Preserve for a third-party widget that already has its own card/background design.' ); ?>
                            <div data-n9f-source-only="shortcode"><?php $this->field( 'Shortcode / URL', '<textarea rows="3" name="n9f_settings[footer_slot_' . $i . '_content]">' . esc_textarea($s["footer_slot_{$i}_content"]) . '</textarea>', 'Accepts a shortcode or a single embeddable URL.' ); ?></div>
                            <div data-n9f-source-only="embed"><?php $this->field( 'Embed code', '<textarea rows="5" name="n9f_settings[footer_slot_' . $i . '_embed_html]" placeholder="Paste Google Maps, Google Forms, Google Drive or another iframe embed code">' . esc_textarea($s["footer_slot_{$i}_embed_html"]) . '</textarea>', 'Safe iframe embeds are allowed; scripts are not.' ); ?><?php $this->field( 'Embed minimum height', '<input type="number" min="120" max="1200" name="n9f_settings[footer_slot_' . $i . '_embed_height]" value="' . esc_attr($s["footer_slot_{$i}_embed_height"]) . '"> px' ); ?></div>
                            <div data-n9f-source-only="image"><?php ob_start(); $this->media( "footer_slot_{$i}_image_url", $s["footer_slot_{$i}_image_url"], 'Choose image' ); $img=ob_get_clean(); $this->field( 'Image', $img ); ?><?php $this->field( 'Image link', '<input type="url" name="n9f_settings[footer_slot_' . $i . '_image_link]" value="' . esc_attr($s["footer_slot_{$i}_image_link"]) . '" placeholder="https://…">' ); ?><?php $this->field( 'Image alt text', '<input type="text" name="n9f_settings[footer_slot_' . $i . '_image_alt]" value="' . esc_attr($s["footer_slot_{$i}_image_alt"]) . '">' ); ?><?php $this->field( 'Image fit', $this->select( "footer_slot_{$i}_image_fit", $s["footer_slot_{$i}_image_fit"], [ 'contain'=>'Contain','cover'=>'Cover width','natural'=>'Natural size' ] ) ); ?></div>
                            <div data-n9f-source-only="elementor"><?php $this->field( 'Elementor template ID', '<input type="number" min="0" name="n9f_settings[footer_slot_' . $i . '_template]" value="' . esc_attr($s["footer_slot_{$i}_template"]) . '">', 'Use an Elementor Saved Template ID.' ); ?></div>
                            <div data-n9f-source-only="block"><?php $blockhtml = str_replace('__BLOCK_NAME__', 'footer_slot_' . $i . '_block', $this->blocks($s["footer_slot_{$i}_block"])); $this->field( 'Reusable block', $blockhtml ); ?></div>
                            <div data-n9f-source-only="integration"><?php $this->field( '9 integration key', '<input type="text" name="n9f_settings[footer_slot_' . $i . '_integration]" value="' . esc_attr($s["footer_slot_{$i}_integration"]) . '" placeholder="featured-media">', 'For a 9 plugin that integrates through the 9 Footer hook.' ); ?></div>
                            <div data-n9f-source-only="widget_area"><div class="n9f-callout"><strong>Widget Area</strong><span>Use Appearance → Widgets → 9 Footer Slot <?php echo $i; ?>.</span></div></div>
                        </div></details>
                    <?php endfor; ?>
                <?php $this->section_close(); ?>

                <?php $this->section_open( 'manager', 'Website Management Attribution', 'Icon + text by default; separate Image Override when you want a full custom mark.' ); ?>
                    <?php ob_start(); $this->check( 'footer_manager_enabled', $s, 'Show website management attribution' ); $h=ob_get_clean(); $this->field( 'Display', $h ); ?>
                    <div class="n9f-callout"><strong>Default rendering</strong><span>Icon → “Website application managed by” → “9igeria Online Limited”</span><small>The icon does not replace the text. The Image Override below is a separate field and replaces the entire default treatment only when supplied.</small></div>
                    <?php ob_start(); $this->media( 'footer_manager_icon_url', $s['footer_manager_icon_url'], 'Choose management icon' ); $h=ob_get_clean(); $this->field( 'Management icon', $h, 'Small icon displayed to the left of the management text. PNG/WebP or an allowed SVG from your Media Library.' ); ?>
                    <?php $this->field( 'Fallback Dashicon', '<input type="text" name="n9f_settings[footer_manager_icon_dashicon]" value="' . esc_attr($s['footer_manager_icon_dashicon']) . '" placeholder="admin-site-alt3">', 'Used only when no uploaded icon is selected.' ); ?>
                    <?php $this->field( 'Prefix text', '<input type="text" name="n9f_settings[footer_manager_prefix]" value="' . esc_attr($s['footer_manager_prefix']) . '">' ); ?>
                    <?php $this->field( 'Manager name', '<input type="text" name="n9f_settings[footer_manager_name]" value="' . esc_attr($s['footer_manager_name']) . '">' ); ?>
                    <?php $this->field( 'Manager link', '<input type="url" name="n9f_settings[footer_manager_url]" value="' . esc_attr($s['footer_manager_url']) . '" placeholder="https://…">', 'Clicking the attribution opens this programmable link.' ); ?>
                    <?php ob_start(); $this->check( 'footer_manager_new_tab', $s, 'Open manager link in a new tab' ); $h=ob_get_clean(); $this->field( 'Link behaviour', $h ); ?>
                    <hr>
                    <?php ob_start(); $this->media( 'footer_manager_image_override_url', $s['footer_manager_image_override_url'], 'Choose full image override' ); $h=ob_get_clean(); $this->field( 'Image Override', $h, 'Optional full-width/one-line management image. When supplied, this image replaces the icon + text treatment. It is intentionally separate from Management icon.' ); ?>
                    <div class="n9f-attribution-preview <?php echo 'auto' === ($s['footer_manager_color_mode'] ?? 'auto') ? 'n9f-attribution-preview--auto' : ''; ?>" style="--n9f-preview-bg:<?php echo esc_attr($s['footer_manager_bg']); ?>;--n9f-preview-text:<?php echo esc_attr($s['footer_manager_text_color']); ?>">
                        <span class="n9f-preview-label">Preview</span>
                        <div class="n9f-preview-default">
                            <?php if($s['footer_manager_icon_url']): ?><img src="<?php echo esc_url($s['footer_manager_icon_url']); ?>" alt=""><?php else: ?><span class="dashicons dashicons-<?php echo esc_attr(sanitize_html_class($s['footer_manager_icon_dashicon'])); ?>"></span><?php endif; ?>
                            <span><?php echo esc_html($s['footer_manager_prefix']); ?> <strong><?php echo esc_html($s['footer_manager_name']); ?></strong></span>
                        </div>
                        <?php if($s['footer_manager_image_override_url']): ?><div class="n9f-preview-override"><span>Image Override active:</span><img src="<?php echo esc_url($s['footer_manager_image_override_url']); ?>" alt=""></div><?php endif; ?>
                    </div>
                <?php $this->section_close(); ?>

                <?php $this->section_open( 'copyright', 'Copyright & Footer End', 'Copyright stays separate from the website manager attribution.' ); ?>
                    <?php $this->field( 'Copyright line', '<input type="text" name="n9f_settings[footer_copyright]" value="' . esc_attr($s['footer_copyright']) . '">', 'This identifies the site copyright holder; it is not the website manager line.' ); ?>
                    <?php ob_start(); $this->check( 'footer_back_to_top', $s, 'Show Back to Top link' ); $h=ob_get_clean(); $this->field( 'Back to Top', $h ); ?>
                    <?php ob_start(); $this->check( 'footer_append_endcap_to_elementor', $s, 'Append management attribution + copyright below an Elementor Footer Override' ); $h=ob_get_clean(); $this->field( 'Elementor consistency', $h ); ?>
                <?php $this->section_close(); ?>

                <?php $this->section_open( 'design', 'Footer Design', 'Safe dark footer by default, with automatic contrast protection whenever colours change.' ); ?>
                    <?php $this->field( 'Colour scheme', $this->select( 'footer_color_mode', $s['footer_color_mode'], [ 'auto'=>'Automatic Safe Scheme — dark by default + PHP/theme integration','custom'=>'Custom footer background' ] ), 'Automatic Safe Scheme uses a dark footer by default. A 9/PHP/theme plugin may supply another background/accent, but 9 Footer automatically corrects text/link colours for readability.' ); ?>
                    <?php ob_start(); $this->check( 'footer_auto_contrast', $s, 'Automatically choose readable text and link colours for the footer background' ); $h=ob_get_clean(); $this->field( 'Contrast protection', $h, 'Recommended ON. When enabled, changing the background can never leave dark text on a dark footer or light text on a light footer.' ); ?>
                    <div data-n9f-color-mode="custom"><?php $this->field( 'Footer background', '<input type="color" name="n9f_settings[footer_bg]" value="' . esc_attr($s['footer_bg']) . '">', 'Choose any background. With Contrast protection ON, text and links adjust automatically.' ); ?>
                    <div data-n9f-manual-footer-colors><?php $this->field( 'Manual text colour', '<input type="color" name="n9f_settings[footer_text_color]" value="' . esc_attr($s['footer_text_color']) . '">', 'Used only when Contrast protection is OFF.' ); ?>
                    <?php $this->field( 'Manual link colour', '<input type="color" name="n9f_settings[footer_link_color]" value="' . esc_attr($s['footer_link_color']) . '">', 'Used only when Contrast protection is OFF.' ); ?></div>
                    <?php $this->field( 'Accent colour', '<input type="color" name="n9f_settings[footer_accent]" value="' . esc_attr($s['footer_accent']) . '">', 'If this is too close to the background, 9 Footer substitutes a readable interaction colour.' ); ?></div>
                    <?php $this->field( 'Content max width', '<input type="number" min="600" max="2400" name="n9f_settings[footer_max_width]" value="' . esc_attr($s['footer_max_width']) . '"> px' ); ?>
                    <?php $this->field( 'Top padding', '<input type="number" min="0" max="160" name="n9f_settings[footer_padding_top]" value="' . esc_attr($s['footer_padding_top']) . '"> px' ); ?>
                    <?php $this->field( 'Bottom padding', '<input type="number" min="0" max="160" name="n9f_settings[footer_padding_bottom]" value="' . esc_attr($s['footer_padding_bottom']) . '"> px' ); ?>
                    <?php $this->field( 'Logo width', '<input type="number" min="40" max="600" name="n9f_settings[footer_logo_width]" value="' . esc_attr($s['footer_logo_width']) . '"> px' ); ?>
                    <?php $this->field( 'Column gap', '<input type="number" min="0" max="100" name="n9f_settings[footer_column_gap]" value="' . esc_attr($s['footer_column_gap']) . '"> px' ); ?>
                    <hr>
                    <?php $this->field( 'Management colour scheme', $this->select( 'footer_manager_color_mode', $s['footer_manager_color_mode'], [ 'auto'=>'Automatic — use footer colours','custom'=>'Custom management strip background' ] ) ); ?>
                    <div data-n9f-manager-color-mode="custom"><?php ob_start(); $this->check( 'footer_manager_auto_contrast', $s, 'Automatically choose readable management text' ); $h=ob_get_clean(); $this->field( 'Manager contrast protection', $h ); ?>
                    <?php $this->field( 'Manager strip background', '<input type="color" name="n9f_settings[footer_manager_bg]" value="' . esc_attr($s['footer_manager_bg']) . '">' ); ?>
                    <div data-n9f-manual-manager-color><?php $this->field( 'Manual manager text colour', '<input type="color" name="n9f_settings[footer_manager_text_color]" value="' . esc_attr($s['footer_manager_text_color']) . '">', 'Used only when Manager contrast protection is OFF.' ); ?></div></div>
                    <?php $this->field( 'Manager icon size', '<input type="number" min="12" max="100" name="n9f_settings[footer_manager_icon_size]" value="' . esc_attr($s['footer_manager_icon_size']) . '"> px' ); ?>
                    <?php $this->field( 'Manager text size', '<input type="number" min="9" max="28" name="n9f_settings[footer_manager_text_size]" value="' . esc_attr($s['footer_manager_text_size']) . '"> px' ); ?>
                    <?php $this->field( 'Prefix weight', '<input type="number" step="100" min="300" max="900" name="n9f_settings[footer_manager_text_weight]" value="' . esc_attr($s['footer_manager_text_weight']) . '">' ); ?>
                    <?php $this->field( 'Manager name weight', '<input type="number" step="100" min="300" max="900" name="n9f_settings[footer_manager_name_weight]" value="' . esc_attr($s['footer_manager_name_weight']) . '">' ); ?>
                    <?php $this->field( 'Attribution alignment', $this->select( 'footer_manager_align', $s['footer_manager_align'], [ 'left'=>'Left','center'=>'Centre','right'=>'Right' ] ) ); ?>
                <?php $this->section_close(); ?>

                <?php $this->section_open( 'advanced', 'Advanced / Compatibility', 'Only use these when a theme or builder needs special handling.' ); ?>
                    <?php $this->field( 'Theme footer selectors', '<textarea rows="4" name="n9f_settings[theme_footer_selectors]">' . esc_textarea($s['theme_footer_selectors']) . '</textarea>', 'CSS selectors hidden when theme footer suppression is enabled.' ); ?>
                    <div class="n9f-callout"><strong>Unified styling</strong><span>Automatic Safe Scheme keeps the footer dark/readable by default. A PHP theme/core plugin can provide background/text/link/accent tokens through the 9 Footer integration filter, and 9 Footer still enforces readable contrast. Use Custom when you want to choose the footer background yourself.</span></div><div class="n9f-callout"><strong>Safe fallback</strong><span>If an Elementor footer override cannot render, 9 Footer falls back to the Native 9 footer.</span></div>
                <?php $this->section_close(); ?>

                <div class="n9f-bottom-save"><button type="submit" class="button button-primary button-large">Save Footer</button></div>
            </form>
            <div class="n9f-mobile-save"><button form="n9f-settings-form" type="submit" class="button button-primary">Save Footer</button></div>
        </div>
        <?php
    }

    private function terms_single_html( $name, $selected ) {
        $terms = $this->admin_categories();
        $html = '<select name="n9f_settings[' . esc_attr($name) . ']"><option value="0">Select category</option>';
        foreach ( $terms as $term ) $html .= '<option value="' . esc_attr($term->term_id) . '" ' . selected( (int)$selected, (int)$term->term_id, false ) . '>' . esc_html($term->name) . '</option>';
        return $html . '</select>';
    }
}
