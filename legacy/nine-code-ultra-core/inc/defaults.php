<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Admin Workspace skin catalog.
 *
 * Names are visual-reference labels only. 9Core 15 does not load or copy
 * third-party brand assets; every skin is implemented locally with 9 Code UI.
 */
function ncu_admin_skin_mode_choices() {
    return array(
        'github' => array(
            'label' => 'GitHub',
            'description' => 'Repository-style workspace: cool canvas, dark code shell, compact bordered modules.',
        ),
        'future' => array(
            'label' => 'Future Workspace',
            'description' => 'Full dark coding cockpit with layered graphite surfaces, grid depth and crisp controls.',
        ),
        'chatgpt' => array(
            'label' => 'ChatGPT',
            'description' => 'Quiet warm workspace with soft panels, generous breathing room and minimal chrome.',
        ),
        'gemini' => array(
            'label' => 'Gemini',
            'description' => 'Airy cool workspace with clean cards, soft elevation and restrained intelligent focus cues.',
        ),
        'white_future' => array(
            'label' => 'White Future',
            'description' => 'White IDE canvas with black shell, dark typography, fine lines and outlined technical boxes.',
        ),
    );
}

function ncu_admin_skin_normalize_mode( $mode ) {
    $mode = sanitize_key( (string) $mode );
    $legacy = array(
        'studio' => 'white_future',
        'night'  => 'github',
        'system' => 'white_future',
    );
    if ( isset( $legacy[ $mode ] ) ) {
        $mode = $legacy[ $mode ];
    }
    $choices = ncu_admin_skin_mode_choices();
    return isset( $choices[ $mode ] ) ? $mode : 'white_future';
}

function ncu_admin_skin_mode_label( $mode ) {
    $mode = ncu_admin_skin_normalize_mode( $mode );
    $choices = ncu_admin_skin_mode_choices();
    return $choices[ $mode ]['label'];
}

function ncu_admin_skin_palette_tokens( $mode ) {
    $mode = ncu_admin_skin_normalize_mode( $mode );
    $palettes = array(
        'github' => array( 'canvas'=>'#f6f8fa', 'surface'=>'#ffffff', 'surface_2'=>'#f6f8fa', 'text'=>'#1f2328', 'muted'=>'#59636e', 'border'=>'#d0d7de', 'shell'=>'#0d1117', 'shell_2'=>'#161b22', 'shell_text'=>'#f0f6fc', 'focus'=>'#1f2328' ),
        'future' => array( 'canvas'=>'#090a0c', 'surface'=>'#111318', 'surface_2'=>'#0d0f13', 'text'=>'#f4f6f8', 'muted'=>'#a1a8b3', 'border'=>'#2a2e36', 'shell'=>'#050506', 'shell_2'=>'#0d0f12', 'shell_text'=>'#f8fafc', 'focus'=>'#ffffff' ),
        'chatgpt' => array( 'canvas'=>'#f7f7f5', 'surface'=>'#ffffff', 'surface_2'=>'#f1f1ef', 'text'=>'#242421', 'muted'=>'#6b6b66', 'border'=>'#e2e2de', 'shell'=>'#171717', 'shell_2'=>'#242424', 'shell_text'=>'#f7f7f5', 'focus'=>'#242421' ),
        'gemini' => array( 'canvas'=>'#f7f7f7', 'surface'=>'#ffffff', 'surface_2'=>'#efefef', 'text'=>'#202124', 'muted'=>'#5f6368', 'border'=>'#d8d8d8', 'shell'=>'#1f2125', 'shell_2'=>'#2a2d32', 'shell_text'=>'#f8f9fa', 'focus'=>'#111111' ),
        'white_future' => array( 'canvas'=>'#ffffff', 'surface'=>'#ffffff', 'surface_2'=>'#f7f7f7', 'text'=>'#0a0a0a', 'muted'=>'#5c5c5c', 'border'=>'#d9d9d9', 'shell'=>'#070707', 'shell_2'=>'#141414', 'shell_text'=>'#ffffff', 'focus'=>'#000000' ),
    );
    return $palettes[ $mode ];
}

function ncu_core_defaults() {
    return array(
        'design_preset'              => 'neutral',
        'aggressive_style_takeover'   => 1,
        'style_takeover_preset'       => 'academic-reference',
        'popular_site_template'      => 'academic-red',
        'template_selection_mode'    => 'skin',
        'style_takeover_colors'       => 1,
        'style_takeover_typography'   => 1,
        'style_takeover_text_styles'  => 1,
        'style_takeover_admin'        => 0,
        'style_takeover_respect_optout'=> 1,
        'style_takeover_strict'        => 1,
        'style_takeover_dynamic_bridge'=> 1,
        'style_custom_primary'        => '#B00000',
        'style_custom_secondary'      => '#0B1F3A',
        'style_custom_accent'         => '#E53935',
        'style_custom_surface'        => '#FFFFFF',
        'style_custom_surface_alt'    => '#F7F8FA',
        'style_custom_text'           => '#111827',
        'style_custom_muted'          => '#667085',
        'style_custom_border'         => '#E5E7EB',
        'style_custom_heading_font'   => 'system-sans',
        'style_custom_body_font'      => 'system-sans',
        'style_custom_ui_font'        => 'system-sans',
        'accent_color'               => '#000000',
        'surface_color'              => '#ffffff',
        'text_color'                 => '#000000',
        'muted_color'                => '#5f6368',
        'header_background_color'    => '#ffffff',
        'header_icon_color'          => '#000000',
        'header_border_color'        => '#e5e7eb',
        'footer_background_color'    => '#ffffff',
        'footer_text_color'          => '#000000',
        /* External builders own public headers by default. This prevents a
         * second 9Code surface being rendered under a header plugin. */
        'header_owner_mode'          => 'theme',
        'header_enabled'             => 0,
        'footer_enabled'             => 0,
        'footer_use_managed_by'      => 1,
        'dark_mode_enabled'          => 1,
        'dark_mode_default'          => 'dark',
        'dark_palette'               => 'auto',
        'dark_accent_override'       => '',
        'dark_use_site_accent'        => 1,
        'dark_toggle_enabled'        => 1,
        'dark_floating_toggle'       => 1,
        'dark_toggle_position'       => 'lower_left',
        'fallback_icon_id'           => 0,
        'client_brand_name'          => '',
        'client_logo_id'             => 0,
        'admin_brand_icon_id'        => 0,
        'login_logo_id'              => 0,
        'white_label_enabled'        => 1,
        'admin_style_enabled'        => 1,
        'admin_skin_enabled'         => 1,
        'admin_skin_menu_enabled'    => 1,
        'admin_skin_monochrome'      => 1,
        'frontend_edit_enabled'      => 0,
        'frontend_edit_badges'       => 0,
        'frontend_edit_theme_regions'=> 0,
        'frontend_edit_content'      => 0,
        'frontend_edit_builder'      => 0,
        'frontend_edit_featured'     => 0,
        'admin_skin_mode'            => 'white_future',
        'admin_skin_density'         => 'compact',
        'admin_skin_workspace_bar'   => 0,
        'admin_skin_command_palette' => 1,
        'admin_skin_editor_chrome'   => 1,
        'admin_editor_tools_drawer'   => 1,
        'admin_editor_high_contrast' => 1,
        'admin_editor_focus_panels'  => 0,
        'admin_editor_hide_plugin_panels_mobile' => 0,
        'admin_skin_enhance_tables'  => 1,
        'admin_skin_enhance_modals'  => 1,
        'admin_skin_hide_help_tabs'  => 0,
        'admin_skin_hide_screen_options' => 0,
        'admin_skin_radius'          => 10,
        'admin_skin_shadow'          => 'soft',
        'admin_bar_brand_enabled'    => 0,
        'admin_bar_label'            => '',
        'login_branding_enabled'     => 1,
        'admin_footer_branding'      => 1,
        'hide_wp_dashboard_news'     => 1,
        'managed_by_name'            => '9igeria Online Ltd.',
        'managed_by_url'             => 'https://9igeria.online/',
        'managed_by_logo_id'         => 0,
        'theme_owner_name'           => '9igeria Online Ltd.',
        'theme_owner_url'            => 'https://9igeria.online/',
        'theme_owner_logo_id'        => 0,
        'max_width'                  => 1180,
        'radius'                     => 16,
        'density'                    => 'comfortable',
        'shadow_strength'            => 'soft',
        'sticky_header'              => 1,
        'show_site_tagline'          => 1,
        'show_author_contact'        => 1,
        'elementor_hide_title'       => 1,
        'elementor_content_takeover' => 0,
        'comments_enabled'           => 0,
        'doctor_frontend_enabled'    => 0,
        'drawer_height'              => 92,
        'category_parent'            => 0,
        'category_search'            => 1,
        'header_icon_size'           => 22,
        'header_icon_gap'            => 10,
        'whatsapp_number'            => '',
        'whatsapp_label'             => 'WhatsApp',
        'login_label'                => 'Login',
        'account_label'              => 'My Account',
        'drawer_heading_menu'        => 'Menu',
        'drawer_heading_categories'  => 'Categories',
        'drawer_heading_quick'       => 'Quick Links',
        'footer_text'                => 'Website application managed by 9igeria Online Ltd.',
        'footer_url'                 => 'https://9igeria.online/',
        'footer_image_id'            => 0,
        'quick_actions'              => array(
            array( 'label' => 'Home', 'url' => '/', 'icon' => 'home', 'mode' => 'link', 'content' => '' ),
            array( 'label' => 'Search', 'url' => '/?s=', 'icon' => 'search', 'mode' => 'link', 'content' => '' ),
            array( 'label' => 'About', 'url' => '/about/', 'icon' => 'info', 'mode' => 'link', 'content' => '' ),
            array( 'label' => 'Contact', 'url' => '/contact/', 'icon' => 'mail', 'mode' => 'link', 'content' => '' ),
            array( 'label' => 'Latest', 'url' => '/blog/', 'icon' => 'book-open', 'mode' => 'link', 'content' => '' ),
        ),
        'bottom_actions'             => array(
            array( 'label' => 'Home', 'url' => '/', 'icon' => 'home' ),
            array( 'label' => 'Contact', 'url' => '/contact/', 'icon' => 'mail' ),
            array( 'label' => 'Search', 'url' => '/?s=', 'icon' => 'search' ),
        ),
    );
}

function ncu_get_settings() {
    $saved = get_option( 'ncu_settings', array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }
    return ncu_recursive_parse_args( $saved, ncu_core_defaults() );
}

function ncu_recursive_parse_args( $args, $defaults ) {
    $result = $defaults;
    if ( ! is_array( $args ) ) {
        return $result;
    }
    foreach ( $args as $key => $value ) {
        if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) ) {
            if ( is_array( $value ) ) {
                $result[ $key ] = ncu_recursive_parse_args( $value, $defaults[ $key ] );
            }
        } elseif ( array_key_exists( $key, $defaults ) && ! is_array( $value ) ) {
            $result[ $key ] = $value;
        }
    }
    return $result;
}
