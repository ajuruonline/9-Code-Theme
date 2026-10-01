<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'ncu_core_admin_menu' );
function ncu_core_admin_menu() {
    $icon = 'dashicons-admin-generic';
    $cap  = current_user_can( 'manage_ninecode_data' ) ? 'manage_ninecode_data' : 'manage_options';
    add_menu_page(
        __( 'Nine Code', 'nine-code' ),
        __( 'Nine Code', 'nine-code' ),
        $cap,
        'nine-code-ultra',
        'ncu_core_dashboard_page',
        $icon,
        3
    );
    add_submenu_page( 'nine-code-ultra', __( 'Responses', 'nine-code' ), __( 'Responses', 'nine-code' ), 'edit_posts', 'nine-code-ultra-responses', 'ncu_core_responses_page' );

    /* Data Manager and Responses are the daily workspace, but all established
     * 9Core settings remain visible and directly accessible under Nine Code. */
    add_submenu_page( 'nine-code-ultra', __( 'Menu & UI', 'nine-code' ), __( 'Menu & UI', 'nine-code' ), 'manage_options', 'nine-code-ultra-menu-ui', 'ncu13_ui_render_settings_page' );
    add_submenu_page( 'nine-code-ultra', __( 'Design System', 'nine-code' ), __( 'Design System', 'nine-code' ), 'manage_options', 'nine-code-ultra-design', 'ncu_core_design_page' );
    add_submenu_page( 'nine-code-ultra', __( 'Style Authority', 'nine-code' ), __( 'Style Authority', 'nine-code' ), 'manage_options', 'nine-code-ultra-style-takeover', 'ncu_style_takeover_settings_page' );
    add_submenu_page( 'nine-code-ultra', __( 'Header & Footer', 'nine-code' ), __( 'Header & Footer', 'nine-code' ), 'manage_options', 'nine-code-ultra-header-footer', 'ncu_core_header_footer_page' );
    add_submenu_page( 'nine-code-ultra', __( 'Admin Workspace', 'nine-code' ), __( 'Admin Workspace', 'nine-code' ), 'manage_options', 'nine-code-ultra-admin-workspace', 'ncu_admin_workspace_settings_page' );
    add_submenu_page( 'nine-code-ultra', __( 'Admin Dark Mode', 'nine-code' ), __( 'Admin Dark Mode', 'nine-code' ), 'manage_options', 'nine-code-ultra-dark-mode', 'ncu_dark_mode_settings_page' );
    add_submenu_page( 'nine-code-ultra', __( 'Branding & Ownership', 'nine-code' ), __( 'Branding & Ownership', 'nine-code' ), 'manage_options', 'nine-code-ultra-branding', 'ncu_branding_settings_page' );
    add_submenu_page( 'nine-code-ultra', __( 'Builders', 'nine-code' ), __( 'Builders', 'nine-code' ), 'manage_options', 'nine-code-ultra-builders', 'ncu_core_builders_page' );
    add_submenu_page( 'nine-code-ultra', __( 'Backup & Recovery', 'nine-code' ), __( 'Backup & Recovery', 'nine-code' ), 'manage_options', 'nine-code-ultra-backup', 'ncu_core_backup_page' );
    add_submenu_page( 'nine-code-ultra', __( 'Doctor', 'nine-code' ), __( 'Doctor', 'nine-code' ), 'manage_options', 'nine-code-ultra-doctor', 'ncu_core_doctor_page' );
    add_submenu_page( 'nine-code-ultra', __( 'System Health', 'nine-code' ), __( 'System Health', 'nine-code' ), 'manage_options', 'nine-code-ultra-health', 'ncu_core_health_page' );

    /* Retired Front-End Edit remains routable for legacy bookmarks but stays
     * out of the visible menu because the feature itself is disabled. */
    add_submenu_page( null, __( 'Front-End Edit (disabled)', 'nine-code' ), __( 'Front-End Edit', 'nine-code' ), 'manage_options', 'nine-code-ultra-frontend-edit', 'ncu_frontend_edit_settings_page' );

    add_theme_page(
        __( 'Nine Code', 'nine-code' ),
        __( 'Nine Code', 'nine-code' ),
        'manage_options',
        'nine-code-ultra-appearance',
        'ncu_core_appearance_bridge_page'
    );
}

/** Keep the data-first routes first while preserving the complete settings menu. */
add_action( 'admin_menu', 'ncu_core_order_admin_submenu', 999 );
function ncu_core_order_admin_submenu() {
    global $submenu;
    if ( empty( $submenu['nine-code-ultra'] ) || ! is_array( $submenu['nine-code-ultra'] ) ) { return; }
    $order = array_flip( array(
        'nine-code-ultra',
        'nine-code-ultra-responses',
        'nine-code-ultra-menu-ui',
        'nine-code-ultra-design',
        'nine-code-ultra-style-takeover',
        'nine-code-ultra-header-footer',
        'nine-code-ultra-admin-workspace',
        'nine-code-ultra-dark-mode',
        'nine-code-ultra-branding',
        'nine-code-ultra-builders',
        'nine-code-ultra-backup',
        'nine-code-ultra-doctor',
        'nine-code-ultra-health',
    ) );
    usort( $submenu['nine-code-ultra'], static function ( $a, $b ) use ( $order ) {
        $a_slug = isset( $a[2] ) ? (string) $a[2] : '';
        $b_slug = isset( $b[2] ) ? (string) $b[2] : '';
        $a_rank = isset( $order[ $a_slug ] ) ? $order[ $a_slug ] : 999;
        $b_rank = isset( $order[ $b_slug ] ) ? $order[ $b_slug ] : 999;
        return $a_rank <=> $b_rank;
    } );
}

add_action( 'admin_enqueue_scripts', 'ncu_core_admin_assets' );
function ncu_core_admin_assets( $hook ) {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $is_ncu = false;
    if ( $screen && isset( $screen->id ) ) {
        $is_ncu = false !== strpos( (string) $screen->id, 'nine-code' );
    }
    if ( ! $is_ncu && 'appearance_page_nine-code-ultra-appearance' !== $hook ) {
        return;
    }
    wp_enqueue_style( 'ncu-core-admin', NCU_CORE_URL . 'assets/css/admin.css', array(), NCU_CORE_VERSION );
    wp_enqueue_media();
    wp_enqueue_script( 'ncu-core-admin', NCU_CORE_URL . 'assets/js/admin.js', array( 'jquery' ), NCU_CORE_VERSION, true );
}

function ncu_scalar( $value, $fallback = '' ) {
    return is_scalar( $value ) ? $value : $fallback;
}

function ncu_allowed_icons() {
    return array( 'home', 'search', 'info', 'mail', 'book-open', 'user', 'phone', 'whatsapp', 'external-link', 'arrow-right', 'globe', 'sparkles', 'settings', 'heart-pulse', 'blocks', 'layout', 'book', 'link', 'arrow', 'site' );
}

function ncu_sanitize_settings( $raw ) {
    $defaults = ncu_core_defaults();
    $out = $defaults;
    if ( ! is_array( $raw ) ) {
        return $out;
    }

    $presets = array( 'neutral', 'editorial', 'compact', 'executive', 'learning', 'newsroom', 'visual', 'minimal', 'technical', 'dark' );
    $density = array( 'compact', 'comfortable', 'spacious' );
    $shadows = array( 'none', 'soft', 'strong' );
    $admin_skin_modes = array_keys( ncu_admin_skin_mode_choices() );
    $admin_skin_densities = array( 'compact', 'comfortable' );
    $admin_skin_shadows = array( 'flat', 'soft', 'deep' );

    $preset = ncu_scalar( isset( $raw['design_preset'] ) ? $raw['design_preset'] : '', '' );
    $density_value = ncu_scalar( isset( $raw['density'] ) ? $raw['density'] : '', '' );
    $shadow_value = ncu_scalar( isset( $raw['shadow_strength'] ) ? $raw['shadow_strength'] : '', '' );
    $out['design_preset'] = in_array( $preset, $presets, true ) ? $preset : $defaults['design_preset'];
    $out['density'] = in_array( $density_value, $density, true ) ? $density_value : $defaults['density'];
    $out['shadow_strength'] = in_array( $shadow_value, $shadows, true ) ? $shadow_value : $defaults['shadow_strength'];

    $admin_skin_mode = ncu_admin_skin_normalize_mode( ncu_scalar( isset( $raw['admin_skin_mode'] ) ? $raw['admin_skin_mode'] : $defaults['admin_skin_mode'], $defaults['admin_skin_mode'] ) );
    $admin_skin_density = sanitize_key( ncu_scalar( isset( $raw['admin_skin_density'] ) ? $raw['admin_skin_density'] : $defaults['admin_skin_density'], $defaults['admin_skin_density'] ) );
    $admin_skin_shadow = sanitize_key( ncu_scalar( isset( $raw['admin_skin_shadow'] ) ? $raw['admin_skin_shadow'] : $defaults['admin_skin_shadow'], $defaults['admin_skin_shadow'] ) );
    $out['admin_skin_mode'] = in_array( $admin_skin_mode, $admin_skin_modes, true ) ? $admin_skin_mode : $defaults['admin_skin_mode'];
    $out['admin_skin_density'] = in_array( $admin_skin_density, $admin_skin_densities, true ) ? $admin_skin_density : $defaults['admin_skin_density'];
    $out['admin_skin_shadow'] = in_array( $admin_skin_shadow, $admin_skin_shadows, true ) ? $admin_skin_shadow : $defaults['admin_skin_shadow'];

    $style_preset = sanitize_key( ncu_scalar( isset( $raw['style_takeover_preset'] ) ? $raw['style_takeover_preset'] : '', '' ) );
    $out['style_takeover_preset'] = function_exists( 'ncu_style_takeover_preset_valid' ) && ncu_style_takeover_preset_valid( $style_preset ) ? $style_preset : $defaults['style_takeover_preset'];
    $popular_site = sanitize_key( ncu_scalar( isset( $raw['popular_site_template'] ) ? $raw['popular_site_template'] : $defaults['popular_site_template'], $defaults['popular_site_template'] ) );
    $popular_sites = function_exists( 'ncu_popular_site_templates' ) ? ncu_popular_site_templates() : array();
    if ( ! isset( $popular_sites[ $popular_site ] ) ) {
        $derived_family = function_exists( 'ncu_style_skin_family' ) ? ncu_style_skin_family( $out['style_takeover_preset'] ) : '';
        $popular_site = isset( $popular_sites[ $derived_family ] ) ? $derived_family : $defaults['popular_site_template'];
    }
    $out['popular_site_template'] = $popular_site;
    $selection_mode = sanitize_key( ncu_scalar( isset( $raw['template_selection_mode'] ) ? $raw['template_selection_mode'] : $defaults['template_selection_mode'], $defaults['template_selection_mode'] ) );
    $out['template_selection_mode'] = in_array( $selection_mode, array( 'popular','skin' ), true ) ? $selection_mode : $defaults['template_selection_mode'];
    $font_keys = array( 'system-sans', 'roboto', 'humanist-sans', 'neo-grotesk', 'geometric-sans', 'rounded-sans', 'academic-serif', 'editorial-serif' );
    foreach ( array( 'style_custom_heading_font', 'style_custom_body_font', 'style_custom_ui_font' ) as $font_key ) {
        $font_value = sanitize_key( ncu_scalar( isset( $raw[ $font_key ] ) ? $raw[ $font_key ] : $defaults[ $font_key ], $defaults[ $font_key ] ) );
        $out[ $font_key ] = in_array( $font_value, $font_keys, true ) ? $font_value : $defaults[ $font_key ];
    }


    $dark_defaults = array( 'dark', 'light', 'system' );
    $dark_default = ncu_scalar( isset( $raw['dark_mode_default'] ) ? $raw['dark_mode_default'] : '', '' );
    $out['dark_mode_default'] = in_array( $dark_default, $dark_defaults, true ) ? $dark_default : $defaults['dark_mode_default'];
    $dark_palette = sanitize_key( ncu_scalar( isset( $raw['dark_palette'] ) ? $raw['dark_palette'] : '', '' ) );
    $out['dark_palette'] = function_exists( 'ncu_dark_palette_is_valid' ) && ncu_dark_palette_is_valid( $dark_palette ) ? $dark_palette : $defaults['dark_palette'];
    $dark_override = ncu_scalar( isset( $raw['dark_accent_override'] ) ? $raw['dark_accent_override'] : '', '' );
    $out['dark_accent_override'] = $dark_override ? sanitize_hex_color( $dark_override ) : '';
    if ( $dark_override && empty( $out['dark_accent_override'] ) ) { $out['dark_accent_override'] = ''; }
    $dark_positions = array( 'lower_left', 'lower_right', 'middle_left', 'middle_right', 'upper_left', 'upper_right' );
    $dark_position = sanitize_key( ncu_scalar( isset( $raw['dark_toggle_position'] ) ? $raw['dark_toggle_position'] : $defaults['dark_toggle_position'], $defaults['dark_toggle_position'] ) );
    $out['dark_toggle_position'] = in_array( $dark_position, $dark_positions, true ) ? $dark_position : $defaults['dark_toggle_position'];

    $header_owner_mode = sanitize_key( ncu_scalar( isset( $raw['header_owner_mode'] ) ? $raw['header_owner_mode'] : $defaults['header_owner_mode'], $defaults['header_owner_mode'] ) );
    $out['header_owner_mode'] = in_array( $header_owner_mode, array( 'theme', 'external', 'ninecode' ), true ) ? $header_owner_mode : $defaults['header_owner_mode'];

    foreach ( array( 'accent_color', 'surface_color', 'text_color', 'muted_color', 'header_background_color', 'header_icon_color', 'header_border_color', 'footer_background_color', 'footer_text_color', 'style_custom_primary', 'style_custom_secondary', 'style_custom_accent', 'style_custom_surface', 'style_custom_surface_alt', 'style_custom_text', 'style_custom_muted', 'style_custom_border' ) as $color_key ) {
        $value = ncu_scalar( isset( $raw[ $color_key ] ) ? $raw[ $color_key ] : '', '' );
        $out[ $color_key ] = $value ? sanitize_hex_color( $value ) : $defaults[ $color_key ];
        if ( empty( $out[ $color_key ] ) ) {
            $out[ $color_key ] = $defaults[ $color_key ];
        }
    }

    $numeric = array(
        'max_width'        => array( 720, 1800 ),
        'radius'           => array( 0, 40 ),
        'drawer_height'    => array( 60, 100 ),
        'header_icon_size' => array( 16, 40 ),
        'header_icon_gap'  => array( 4, 32 ),
        'admin_skin_radius' => array( 4, 18 ),
    );
    foreach ( $numeric as $key => $range ) {
        $value = ncu_scalar( isset( $raw[ $key ] ) ? $raw[ $key ] : $defaults[ $key ], $defaults[ $key ] );
        $out[ $key ] = max( $range[0], min( $range[1], absint( $value ) ) );
    }
    $out['category_parent'] = absint( ncu_scalar( isset( $raw['category_parent'] ) ? $raw['category_parent'] : 0, 0 ) );

    foreach ( array( 'sticky_header', 'show_site_tagline', 'show_author_contact', 'elementor_hide_title', 'elementor_content_takeover', 'comments_enabled', 'category_search', 'doctor_frontend_enabled', 'header_enabled', 'footer_enabled', 'footer_use_managed_by', 'dark_mode_enabled', 'dark_toggle_enabled', 'dark_floating_toggle', 'dark_use_site_accent', 'white_label_enabled', 'admin_style_enabled', 'admin_skin_enabled', 'admin_skin_menu_enabled', 'admin_skin_monochrome', 'frontend_edit_enabled', 'frontend_edit_badges', 'frontend_edit_theme_regions', 'frontend_edit_content', 'frontend_edit_builder', 'frontend_edit_featured', 'admin_skin_workspace_bar', 'admin_skin_command_palette', 'admin_skin_editor_chrome', 'admin_editor_tools_drawer', 'admin_editor_high_contrast', 'admin_editor_focus_panels', 'admin_editor_hide_plugin_panels_mobile', 'admin_skin_enhance_tables', 'admin_skin_enhance_modals', 'admin_skin_hide_help_tabs', 'admin_skin_hide_screen_options', 'admin_bar_brand_enabled', 'login_branding_enabled', 'admin_footer_branding', 'hide_wp_dashboard_news', 'aggressive_style_takeover', 'style_takeover_colors', 'style_takeover_typography', 'style_takeover_text_styles', 'style_takeover_admin', 'style_takeover_respect_optout', 'style_takeover_strict', 'style_takeover_dynamic_bridge' ) as $boolean_key ) {
        $out[ $boolean_key ] = ! empty( $raw[ $boolean_key ] ) && ! is_array( $raw[ $boolean_key ] ) ? 1 : 0;
    }
    /* Popular Site is a full-site template; Skin is the lighter identity layer. */
    $intent = sanitize_key( ncu_scalar( isset( $raw['template_apply_intent'] ) ? $raw['template_apply_intent'] : '', '' ) );
    if ( ! in_array( $intent, array( 'popular','skin' ), true ) ) {
        $stored_before = get_option( 'ncu_settings', array() );
        $stored_before = is_array( $stored_before ) ? $stored_before : array();
        $old_popular = sanitize_key( $stored_before['popular_site_template'] ?? $defaults['popular_site_template'] );
        $old_skin = sanitize_key( $stored_before['style_takeover_preset'] ?? $defaults['style_takeover_preset'] );
        if ( $out['popular_site_template'] !== $old_popular && $out['style_takeover_preset'] === $old_skin ) $intent = 'popular';
        elseif ( $out['style_takeover_preset'] !== $old_skin ) $intent = 'skin';
    }
    if ( 'popular' === $intent ) {
        $out['template_selection_mode'] = 'popular';
        $out['style_takeover_preset'] = ncu_popular_site_default_skin( $out['popular_site_template'] );
        foreach ( ncu_popular_site_profile( $out['popular_site_template'] ) as $key => $value ) { $out[ $key ] = $value; }
        $out['aggressive_style_takeover'] = 1; $out['style_takeover_colors'] = 1; $out['style_takeover_typography'] = 1; $out['style_takeover_text_styles'] = 1; $out['style_takeover_strict'] = 1; $out['style_takeover_dynamic_bridge'] = 1;
    } elseif ( 'skin' === $intent ) {
        $out['template_selection_mode'] = 'skin';
        $family = ncu_style_skin_family( $out['style_takeover_preset'] );
        if ( isset( $popular_sites[ $family ] ) ) $out['popular_site_template'] = $family;
        $out['aggressive_style_takeover'] = 1; $out['style_takeover_colors'] = 1; $out['style_takeover_typography'] = 1; $out['style_takeover_text_styles'] = 1;
    }
    /* Admin Workspace is a separate black/white coding environment. When it is
     * active, site-style authority must not leak brand colours into wp-admin. */
    if ( ! empty( $out['admin_skin_enabled'] ) ) {
        $out['style_takeover_admin'] = 0;
    }

    foreach ( array( 'whatsapp_label', 'login_label', 'account_label', 'drawer_heading_menu', 'drawer_heading_categories', 'drawer_heading_quick', 'footer_text', 'client_brand_name', 'admin_bar_label', 'managed_by_name', 'theme_owner_name' ) as $text_key ) {
        $value = ncu_scalar( isset( $raw[ $text_key ] ) ? $raw[ $text_key ] : $defaults[ $text_key ], $defaults[ $text_key ] );
        $out[ $text_key ] = sanitize_text_field( $value );
    }

    $whatsapp = ncu_scalar( isset( $raw['whatsapp_number'] ) ? $raw['whatsapp_number'] : '', '' );
    $footer_url = ncu_scalar( isset( $raw['footer_url'] ) ? $raw['footer_url'] : '', '' );
    $footer_image_id = ncu_scalar( isset( $raw['footer_image_id'] ) ? $raw['footer_image_id'] : 0, 0 );
    $out['whatsapp_number'] = preg_replace( '/[^0-9+]/', '', sanitize_text_field( $whatsapp ) );
    $out['footer_url'] = esc_url_raw( $footer_url );
    $out['footer_image_id'] = absint( $footer_image_id );

    foreach ( array( 'managed_by_url', 'theme_owner_url' ) as $url_key ) {
        $url_value = ncu_scalar( isset( $raw[ $url_key ] ) ? $raw[ $url_key ] : $defaults[ $url_key ], $defaults[ $url_key ] );
        $out[ $url_key ] = esc_url_raw( $url_value );
    }
    foreach ( array( 'fallback_icon_id', 'client_logo_id', 'admin_brand_icon_id', 'login_logo_id', 'managed_by_logo_id', 'theme_owner_logo_id' ) as $media_key ) {
        $out[ $media_key ] = absint( ncu_scalar( isset( $raw[ $media_key ] ) ? $raw[ $media_key ] : 0, 0 ) );
    }

    $out['quick_actions'] = ncu_sanitize_action_rows( isset( $raw['quick_actions'] ) ? $raw['quick_actions'] : array(), 5, $defaults['quick_actions'], true );
    $out['bottom_actions'] = ncu_sanitize_action_rows( isset( $raw['bottom_actions'] ) ? $raw['bottom_actions'] : array(), 3, $defaults['bottom_actions'], false );

    return $out;
}

function ncu_sanitize_action_rows( $rows, $limit, $fallback, $allow_popup = false ) {
    $icons = ncu_allowed_icons();
    $clean = array();
    if ( ! is_array( $rows ) ) {
        return $fallback;
    }
    for ( $i = 0; $i < $limit; $i++ ) {
        $row = isset( $rows[ $i ] ) && is_array( $rows[ $i ] ) ? $rows[ $i ] : array();
        $label = ncu_scalar( isset( $row['label'] ) ? $row['label'] : '', '' );
        $url = ncu_scalar( isset( $row['url'] ) ? $row['url'] : '', '' );
        $icon_value = ncu_scalar( isset( $row['icon'] ) ? $row['icon'] : '', '' );
        $mode_value = ncu_scalar( isset( $row['mode'] ) ? $row['mode'] : '', '' );
        $content = ncu_scalar( isset( $row['content'] ) ? $row['content'] : '', '' );
        $icon = in_array( $icon_value, $icons, true ) ? $icon_value : 'globe';
        $mode = $allow_popup && 'popup' === $mode_value ? 'popup' : 'link';
        $clean[] = array(
            'label'   => sanitize_text_field( $label ),
            'url'     => esc_url_raw( $url ),
            'icon'    => $icon,
            'mode'    => $mode,
            'content' => $allow_popup ? ncu_sanitize_popup_content( $content ) : '',
        );
    }
    return $clean;
}

function ncu_sanitize_popup_content( $content ) {
    if ( ! is_scalar( $content ) ) {
        return '';
    }
    $allowed = wp_kses_allowed_html( 'post' );
    $allowed['iframe'] = array(
        'src' => true, 'width' => true, 'height' => true, 'style' => true, 'title' => true,
        'loading' => true, 'allow' => true, 'allowfullscreen' => true, 'referrerpolicy' => true,
        'frameborder' => true, 'class' => true,
    );
    return wp_kses( (string) $content, $allowed );
}

/**
 * Save only a declared settings subset and verify the persisted values.
 * This keeps feature screens from rewriting unrelated settings and gives
 * a deterministic failure signal when an object-cache/database layer rejects
 * a change.
 */
function ncu_save_settings_subset( $raw, $keys, $boolean_keys = array() ) {
    $current = ncu_get_settings();
    $candidate = $current;
    $raw = is_array( $raw ) ? $raw : array();
    foreach ( $keys as $key ) {
        if ( array_key_exists( $key, $raw ) ) {
            $candidate[ $key ] = $raw[ $key ];
        } elseif ( in_array( $key, $boolean_keys, true ) ) {
            $candidate[ $key ] = 0;
        }
    }
    $clean = ncu_sanitize_settings( $candidate );
    update_option( 'ncu_settings', $clean, false );
    if ( function_exists( 'wp_cache_delete' ) ) {
        wp_cache_delete( 'ncu_settings', 'options' );
        wp_cache_delete( 'alloptions', 'options' );
    }
    $persisted = ncu_get_settings();
    $mismatch = array();
    foreach ( $keys as $key ) {
        $expected = isset( $clean[ $key ] ) ? $clean[ $key ] : null;
        $actual   = isset( $persisted[ $key ] ) ? $persisted[ $key ] : null;
        if ( $expected !== $actual ) {
            $mismatch[ $key ] = array( 'expected' => $expected, 'actual' => $actual );
        }
    }
    return array( 'settings' => $persisted, 'mismatch' => $mismatch );
}

function ncu_redirect_after_settings_save( $return_page, $result, $success_message ) {
    if ( ! empty( $result['mismatch'] ) ) {
        if ( function_exists( 'ncu_notice_push' ) ) {
            ncu_notice_push( 'Nine Code could not verify one or more saved settings. Use System Health/Doctor before retrying.', 'error', 'settings-save-verify-failed' );
        }
    } elseif ( function_exists( 'ncu_notice_push' ) ) {
        ncu_notice_push( $success_message, 'success', 'settings-saved' );
    }
    wp_safe_redirect( add_query_arg( array( 'page' => sanitize_key( $return_page ) ), admin_url( 'admin.php' ) ) );
    exit;
}

add_action( 'admin_post_ncu_save_settings', 'ncu_core_save_settings' );
function ncu_core_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to change these settings.', 'nine-code' ) );
    }
    check_admin_referer( 'ncu_save_settings' );
    $raw = isset( $_POST['ncu'] ) && is_array( $_POST['ncu'] ) ? wp_unslash( $_POST['ncu'] ) : array();
    $current = ncu_get_settings();
    $merged = array_replace_recursive( $current, $raw );
    update_option( 'ncu_settings', ncu_sanitize_settings( $merged ), false );
    $return_page = isset( $_POST['ncu_return_page'] ) ? sanitize_key( wp_unslash( $_POST['ncu_return_page'] ) ) : 'nine-code-ultra';
    if ( function_exists( 'ncu_notice_push' ) ) { ncu_notice_push( 'Nine Code settings saved.', 'success', 'settings-saved' ); }
    wp_safe_redirect( add_query_arg( array( 'page' => $return_page ), admin_url( 'admin.php' ) ) );
    exit;
}

add_action( 'admin_post_ncu_reset_settings', 'ncu_core_reset_settings' );
function ncu_core_reset_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to reset these settings.', 'nine-code' ) );
    }
    check_admin_referer( 'ncu_reset_settings' );
    update_option( 'ncu_settings', ncu_core_defaults(), false );
    update_option( 'ncu_builder_settings', ncu_builder_defaults(), false );
    if ( function_exists( 'ncu_notice_push' ) ) { ncu_notice_push( 'Nine Code settings reset to safe defaults.', 'info', 'settings-reset' ); }
    wp_safe_redirect( add_query_arg( array( 'page' => 'nine-code-ultra-backup' ), admin_url( 'admin.php' ) ) );
    exit;
}

function ncu_admin_header( $title, $description = '' ) {
    ?>
    <div class="ncu-admin__hero">
        <div class="ncu-admin__brandline">
            <div><h1><?php echo esc_html( $title ); ?></h1><?php if ( $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?></div>
        </div>
        <span class="ncu-badge">v<?php echo esc_html( NCU_CORE_VERSION ); ?></span>
    </div>
    <?php
}

function ncu_core_dashboard_page() {
    if ( ! current_user_can( 'manage_ninecode_data' ) && ! current_user_can( 'manage_options' ) ) { return; }
    if ( ncu_core_external_data_edition_active() ) {
        echo '<div class="wrap ncu-admin">';
        ncu_admin_header( 'Nine Code', 'Shared 9CodePress infrastructure. Edition 9.10 Data owns data, AI, post and taxonomy management.' );
        echo '<section class="ncu-panel ncu-panel--padded"><h2>Edition 9.10 ownership</h2><p>9 Data is active and is the authoritative data-management surface. Core has automatically disabled its legacy embedded data engine to prevent duplicate menus, imports, versions and AI workflows.</p><p><a class="button button-primary button-hero" href="' . esc_url( admin_url( 'admin.php?page=nine10-data-edition' ) ) . '">Open 9 Data Manager</a></p></section></div>';
        return;
    }
    if ( function_exists( 'nce_ai_editor_page' ) ) {
        nce_ai_editor_page();
        return;
    }
    echo '<div class="wrap"><h1>Legacy Data Fallback</h1><p>Install 9.10 Data Edition for the current data, AI, post and taxonomy workspace.</p></div>';
}

function ncu_dashboard_card( $title, $text, $page, $dashicon ) {
    ?><a class="ncu-dashboard-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page ) ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $dashicon ); ?>"></span><h2><?php echo esc_html( $title ); ?></h2><p><?php echo esc_html( $text ); ?></p><strong>Open →</strong></a><?php
}

function ncu_core_design_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $s = ncu_get_settings();
    $presets = array( 'neutral' => '9code Black & White', 'editorial' => 'Editorial', 'compact' => 'Compact', 'executive' => 'Executive', 'learning' => 'Learning', 'newsroom' => 'Newsroom', 'visual' => 'Visual', 'minimal' => 'Minimal', 'technical' => 'Technical', 'dark' => 'Dark Premium' );
    ?>
    <div class="wrap ncu-admin"><?php ncu_admin_header( 'Design', 'Global defaults inherited by Auto Builder and optionally by AI Builder.' ); ?>
    <?php ncu_settings_notice(); ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ncu_save_settings"><input type="hidden" name="ncu_return_page" value="nine-code-ultra-design"><?php wp_nonce_field( 'ncu_save_settings' ); ?>
        <section class="ncu-panel ncu-panel--padded"><h2>Design system</h2><div class="ncu-grid">
            <label><span>Design preset</span><select name="ncu[design_preset]"><?php foreach ( $presets as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['design_preset'], $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><span>Density</span><select name="ncu[density]"><option value="compact" <?php selected( $s['density'], 'compact' ); ?>>Compact</option><option value="comfortable" <?php selected( $s['density'], 'comfortable' ); ?>>Comfortable</option><option value="spacious" <?php selected( $s['density'], 'spacious' ); ?>>Spacious</option></select></label>
            <?php ncu_color_field( 'Accent', 'accent_color', $s ); ?><?php ncu_color_field( 'Surface', 'surface_color', $s ); ?><?php ncu_color_field( 'Text', 'text_color', $s ); ?><?php ncu_color_field( 'Muted text', 'muted_color', $s ); ?>
            <label><span>Maximum content width (px)</span><input type="number" min="720" max="1800" name="ncu[max_width]" value="<?php echo esc_attr( $s['max_width'] ); ?>"></label>
            <label><span>Corner radius (px)</span><input type="number" min="0" max="40" name="ncu[radius]" value="<?php echo esc_attr( $s['radius'] ); ?>"></label>
            <label><span>Shadow</span><select name="ncu[shadow_strength]"><option value="none" <?php selected( $s['shadow_strength'], 'none' ); ?>>None</option><option value="soft" <?php selected( $s['shadow_strength'], 'soft' ); ?>>Soft</option><option value="strong" <?php selected( $s['shadow_strength'], 'strong' ); ?>>Strong</option></select></label>
        </div></section>
        <p class="submit"><button class="button button-primary button-hero">Save design</button></p>
    </form></div><?php
}

function ncu_core_header_footer_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( function_exists( 'ncu_theme_header_footer_admin_page' ) ) { ncu_theme_header_footer_admin_page(); return; }
    echo '<div class="wrap ncu-admin">';
    ncu_admin_header( 'Header & Footer', 'Theme-owned Header & Footer controls require the matching Nine Code 14.1.0+.' );
    echo '<section class="ncu-panel ncu-panel--padded"><h2>Theme integration required</h2><p>The former Core built-in Header/Footer renderer is retired. Activate the matching Theme; Core remains the settings/control plane and the Theme remains the single public renderer.</p></section></div>';
}

function ncu_core_backup_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    ?>
    <div class="wrap ncu-admin"><?php ncu_admin_header( 'Backup & Recovery', 'Keep the theme recoverable without depending on another workflow plugin.' ); ?>
    <section class="ncu-panel ncu-panel--padded"><h2>Export</h2><p>Download a compact JSON backup of Nine Code settings.</p><p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ncu_export_settings' ), 'ncu_export_settings' ) ); ?>">Export settings</a></p></section>
    <section class="ncu-panel ncu-panel--padded"><h2>Import</h2><form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ncu_import_settings"><?php wp_nonce_field( 'ncu_import_settings' ); ?><input type="file" name="ncu_backup" accept="application/json,.json" required> <button class="button button-secondary">Validate & import</button></form></section>
    <section class="ncu-panel ncu-panel--padded"><h2>Reset</h2><p>Returns theme and builder configuration to the v3.2 safe defaults. Aggressive Style Takeover remains off until you enable it.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Reset every Nine Code setting to its safe default?');"><input type="hidden" name="action" value="ncu_reset_settings"><?php wp_nonce_field( 'ncu_reset_settings' ); ?><button class="button">Reset defaults</button></form></section></div>
    <?php
}

function ncu_core_health_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $theme = wp_get_theme();
    $template = $theme->get_template();
    $theme_api = defined( 'NCU_THEME_API_VERSION' ) ? NCU_THEME_API_VERSION : 0;
    $s = ncu_get_settings();
    $https_ok = 'https' === wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );
    $debug_display = defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : false;
    $checks = array(
        array( 'Theme', (bool) array_intersect( array( 'nine-code-ultra', '10-code-theme', '9code-12-theme' ), array( $template, $theme->get_stylesheet() ) ), $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ),
        array( 'Core API', (int) NCU_CORE_API_VERSION > 0, 'API ' . NCU_CORE_API_VERSION ),
        array( 'Theme/Core API', $theme_api && (int) $theme_api === (int) NCU_CORE_API_VERSION, $theme_api ? 'Theme API ' . $theme_api . ' · Core API ' . NCU_CORE_API_VERSION : 'Theme API not loaded' ),
        array( 'PHP', version_compare( PHP_VERSION, '7.4', '>=' ), PHP_VERSION ),
        array( 'CMS Engine', version_compare( get_bloginfo( 'version' ), '6.6', '>=' ), get_bloginfo( 'version' ) ),
        array( 'Permalinks', '' !== (string) get_option( 'permalink_structure' ), get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Plain' ),
        array( 'HTTPS', $https_ok, $https_ok ? 'Secure site URL' : 'Site URL is not HTTPS' ),
        array( 'Dark Mode', ! empty( $s['dark_mode_enabled'] ), ! empty( $s['dark_mode_enabled'] ) ? ucfirst( $s['dark_mode_default'] ) . ' · ' . ( ! empty( $s['aggressive_style_takeover'] ) ? 'matched style companion' : $s['dark_palette'] ) : 'Disabled' ),
        array( 'Style Authority', true, ! empty( $s['aggressive_style_takeover'] ) ? 'Aggressive · ' . $s['style_takeover_preset'] . ' · ' . ( ! empty( $s['style_takeover_strict'] ) ? 'Strict' : 'Standard' ) . ( ! empty( $s['style_takeover_dynamic_bridge'] ) ? ' + Dynamic bridge' : '' ) : 'Normal theme mode · takeover off' ),
        array( 'Style Library', function_exists( 'ncu_style_takeover_index' ) && count( ncu_style_takeover_index() ) >= 130, function_exists( 'ncu_style_takeover_index' ) ? count( ncu_style_takeover_index() ) . ' presets available' : 'Style library unavailable' ),
        array( 'App Branding', ! empty( $s['white_label_enabled'] ), ! empty( $s['white_label_enabled'] ) ? 'Client + 9 Code branding active' : 'Standard platform chrome visible' ),
        array( 'Admin Workspace', ! empty( $s['admin_skin_enabled'] ), ! empty( $s['admin_skin_enabled'] ) ? ncu_admin_skin_mode_label( $s['admin_skin_mode'] ) . ' · ' . ucfirst( $s['admin_skin_density'] ) . ' · Ultron skin active' : 'Skin disabled · standard admin layout' ),
        array( 'Built-in Footer', empty( $s['footer_enabled'] ), empty( $s['footer_enabled'] ) ? 'Off · duplicate-footer safe' : 'Enabled · external footer ownership still takes priority' ),
        array( 'Debug Display', ! $debug_display, $debug_display ? 'Visible debug output is enabled' : 'Errors are not exposed to public visitors' ),
    );
    ?>
    <div class="wrap ncu-admin"><?php ncu_admin_header( 'System Health', 'Pre-deployment checks for the minimal Nine Code stack.' ); ?><div class="ncu-health-list">
    <?php foreach ( $checks as $check ) : ?><div class="ncu-health-row"><span class="ncu-health-dot <?php echo $check[1] ? 'is-good' : 'is-warn'; ?>"></span><strong><?php echo esc_html( $check[0] ); ?></strong><span><?php echo esc_html( $check[2] ); ?></span></div><?php endforeach; ?>
    </div><section class="ncu-panel ncu-panel--padded"><h2>Diagnostics</h2><p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ncu_export_diagnostics' ), 'ncu_export_diagnostics' ) ); ?>">Download system diagnostics</a></p><p class="description">For a specific page, use <strong>Nine Code → Doctor</strong> or the Doctor icon in the editor/front end.</p></section></div>
    <?php
}

function ncu_core_appearance_bridge_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    ?><div class="wrap ncu-admin"><?php ncu_admin_header( 'Nine Code', 'Theme functions have moved to a dedicated top-level dashboard so they are always easy to find.' ); ?><p><a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra' ) ); ?>">Open Nine Code Dashboard</a></p></div><?php
}

function ncu_settings_notice() { /* v2.1 uses the one-shot notice queue in inc/notices.php. */ }

function ncu_color_field( $label, $key, $s ) {
    ?><label><span><?php echo esc_html( $label ); ?></span><input type="color" name="ncu[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ $key ] ); ?>"></label><?php
}

function ncu_checkbox( $key, $label, $s ) {
    ?><label><input type="hidden" name="ncu[<?php echo esc_attr( $key ); ?>]" value="0"><input type="checkbox" name="ncu[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label><?php
}

function ncu_render_action_rows( $name, $rows, $count, $icons, $allow_popup = false ) {
    for ( $i = 0; $i < $count; $i++ ) {
        $row = isset( $rows[ $i ] ) ? $rows[ $i ] : array( 'label' => '', 'url' => '', 'icon' => 'globe', 'mode' => 'link', 'content' => '' );
        $mode = isset( $row['mode'] ) ? $row['mode'] : 'link';
        ?>
        <div class="ncu-action-card"><div class="ncu-action-row">
            <label><span>Label</span><input type="text" name="ncu[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>"></label>
            <label><span>URL</span><input type="text" name="ncu[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][url]" value="<?php echo esc_attr( $row['url'] ); ?>" placeholder="https://… or /relative-path/"></label>
            <label><span>Icon</span><select name="ncu[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][icon]"><?php foreach ( $icons as $icon ) : ?><option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $row['icon'], $icon ); ?>><?php echo esc_html( ucwords( str_replace( '-', ' ', $icon ) ) ); ?></option><?php endforeach; ?></select></label>
            <?php if ( $allow_popup ) : ?><label><span>Action</span><select name="ncu[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][mode]"><option value="link" <?php selected( $mode, 'link' ); ?>>Open link</option><option value="popup" <?php selected( $mode, 'popup' ); ?>>Open popup</option></select></label><?php endif; ?>
        </div><?php if ( $allow_popup ) : ?><details class="ncu-popup-editor"><summary>Popup content for <?php echo esc_html( $row['label'] ? $row['label'] : 'action ' . ( $i + 1 ) ); ?></summary><p class="description">Used only when Action = Open popup. Shortcodes are rendered. Trusted iframe embed code is preserved.</p><?php wp_editor( isset( $row['content'] ) ? $row['content'] : '', 'ncu_popup_' . $i, array( 'textarea_name' => 'ncu[' . $name . '][' . $i . '][content]', 'textarea_rows' => 7, 'media_buttons' => true, 'teeny' => false ) ); ?></details><?php endif; ?></div>
        <?php
    }
}
