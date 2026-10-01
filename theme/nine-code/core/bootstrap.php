<?php
/**
 * Nine Code Core module.
 *
 * Shared infrastructure for the Nine Code theme: settings, Style Authority,
 * admin workspace/branding, editor tools, builders, Doctor and diagnostics.
 * Loaded from functions.php. The Data Manager is a separate plugin
 * (nine-code-data) so data, CPTs and shortcodes survive theme changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NCU_CORE_VERSION', NCU_THEME_VERSION );
define( 'NCU_CORE_RELEASE_SHOT', 'Native Editor Space Recovery' );
define( 'NCU_CORE_API_VERSION', 14 );
define( 'NINECODE_SUITE_CORE_VERSION', NCU_THEME_VERSION );
if ( ! defined( 'NINECODE_SUITE_CONTRACT_MAJOR' ) ) { define( 'NINECODE_SUITE_CONTRACT_MAJOR', 14 ); }
define( 'NCU_CORE_FILE', __FILE__ );
define( 'NCU_CORE_DIR', trailingslashit( NCU_THEME_DIR ) . 'core/' );
define( 'NCU_CORE_URL', trailingslashit( NCU_THEME_URI ) . 'core/' );

/** API 14 is the current suite contract. The compatibility function remains
 * tolerant of older API-2+ Themes during an upgrade window, while diagnostics
 * identify anything that has not yet joined API 14. */
function ncu_core_theme_api_compatible( $theme_api ) {
    $theme_api = (int) $theme_api;
    return $theme_api >= 2 && $theme_api <= (int) NCU_CORE_API_VERSION;
}

/**
 * The Data Manager is the separate nine-code-data plugin. Plugins load before
 * the theme, so its version constant is reliable here.
 */
function ncu_core_external_data_edition_active() {
    return defined( 'NINE55_ULTRON_DATA_VERSION' );
}

require_once NCU_CORE_DIR . 'inc/defaults.php';
require_once NCU_CORE_DIR . 'inc/responses.php';
require_once NCU_CORE_DIR . 'inc/style-takeover.php';
require_once NCU_CORE_DIR . 'inc/settings.php';
require_once NCU_CORE_DIR . 'inc/dark-mode.php';
require_once NCU_CORE_DIR . 'inc/branding.php';
require_once NCU_CORE_DIR . 'inc/admin-workspace.php';
require_once NCU_CORE_DIR . 'inc/editor-workspace.php';
require_once NCU_CORE_DIR . 'inc/admin-ui-controller.php';
require_once NCU_CORE_DIR . 'inc/frontend-edit.php';
require_once NCU_CORE_DIR . 'inc/notices.php';
require_once NCU_CORE_DIR . 'inc/profile.php';
require_once NCU_CORE_DIR . 'inc/backup.php';
require_once NCU_CORE_DIR . 'inc/integration-contracts.php';
require_once NCU_CORE_DIR . 'inc/diagnostics.php';
require_once NCU_CORE_DIR . 'inc/builders.php';
require_once NCU_CORE_DIR . 'inc/doctor.php';
require_once NCU_CORE_DIR . 'inc/shortcodes.php';
/** Seed defaults once per install/upgrade (theme activation or version bump). */
function ncu_core_activate() {
    if ( false === get_option( 'ncu_settings', false ) ) {
        add_option( 'ncu_settings', ncu_core_defaults(), '', false );
    }
    if ( false === get_option( 'ncu_builder_settings', false ) ) {
        add_option( 'ncu_builder_settings', ncu_builder_defaults(), '', false );
    }
    if ( function_exists( 'ncu13_ui_activation' ) ) { ncu13_ui_activation(); }
    update_option( 'ncu_core_version', NCU_CORE_VERSION, false );
}
add_action( 'after_switch_theme', 'ncu_core_activate' );
add_action( 'admin_init', 'ncu_core_maybe_upgrade', 5 );

function ncu_core_data_manager_url() {
    return admin_url( ncu_core_external_data_edition_active() ? 'admin.php?page=nine-post-manager' : 'admin.php?page=nine-code-ultra' );
}



function ncu_core_maybe_upgrade() {
    $installed = (string) get_option( 'ncu_core_version', '0' );
    if ( version_compare( $installed, NCU_CORE_VERSION, '>=' ) ) {
        return;
    }

    $saved = get_option( 'ncu_settings', array() );
    $saved = is_array( $saved ) ? $saved : array();

    /* v3.2: map the three legacy Admin Workspace modes into the five-skin system. */
    if ( isset( $saved['admin_skin_mode'] ) ) {
        $saved['admin_skin_mode'] = ncu_admin_skin_normalize_mode( $saved['admin_skin_mode'] );
    }

    /* Migrate untouched v1 colour defaults to the v2 black/white commercial baseline. */
    if ( isset( $saved['accent_color'], $saved['text_color'], $saved['muted_color'], $saved['surface_color'] )
        && '#155eef' === strtolower( (string) $saved['accent_color'] )
        && '#101828' === strtolower( (string) $saved['text_color'] )
        && '#667085' === strtolower( (string) $saved['muted_color'] )
        && '#ffffff' === strtolower( (string) $saved['surface_color'] ) ) {
        $saved['accent_color'] = '#000000';
        $saved['text_color']   = '#000000';
        $saved['muted_color']  = '#5f6368';
    }
    if ( empty( $saved['footer_url'] ) && isset( $saved['footer_text'] ) && 'Website application managed by 9igeria Online Limited.' === (string) $saved['footer_text'] ) {
        $saved['footer_text'] = 'Website application managed by 9igeria Online Ltd.';
        $saved['footer_url']  = 'https://9igeria.online/';
    }

    /* v3.3: Admin Workspace is authoritative. A legacy branding toggle must no longer silently disable the skin. */
    if ( ! array_key_exists( 'admin_skin_enabled', $saved ) ) { $saved['admin_skin_enabled'] = 1; }
    if ( ! array_key_exists( 'admin_skin_menu_enabled', $saved ) ) { $saved['admin_skin_menu_enabled'] = 1; }
    if ( ! array_key_exists( 'frontend_edit_enabled', $saved ) ) { $saved['frontend_edit_enabled'] = 0; }

    /* v3.4.1 stabilization: the requested front-end appearance control was meant
     * to be floating/lower-left by default, but earlier packages stored it OFF
     * and had no position key. Migrate only installations that predate the
     * position setting so an explicit v3.4.1+ administrator choice is preserved. */
    if ( ! array_key_exists( 'dark_toggle_position', $saved ) ) {
        $saved['dark_toggle_position'] = 'lower_left';
        if ( ! empty( $saved['dark_mode_enabled'] ) && ! empty( $saved['dark_toggle_enabled'] ) ) {
            $saved['dark_floating_toggle'] = 1;
        }
    }
    if ( ! array_key_exists( 'admin_skin_monochrome', $saved ) ) { $saved['admin_skin_monochrome'] = 1; }

    /* 12.0.4: retire every public diagnostic/edit overlay on upgrade. Older
     * releases stored these as enabled, so defaults alone cannot clean an
     * existing site. Backend Doctor and Gutenberg tools remain available. */
    if ( version_compare( $installed, '12.0.4', '<' ) ) {
        foreach ( array( 'doctor_frontend_enabled', 'frontend_edit_enabled', 'frontend_edit_badges', 'frontend_edit_theme_regions', 'frontend_edit_content', 'frontend_edit_builder', 'frontend_edit_featured' ) as $public_control_key ) {
            $saved[ $public_control_key ] = 0;
        }
    }


    /* 13.6: Theme owns public Header/Footer and Theme Skin is the default visual authority. */
    if ( version_compare( $installed, '13.6.0', '<' ) ) {
        $skin = isset( $saved['style_takeover_preset'] ) ? sanitize_key( $saved['style_takeover_preset'] ) : 'academic-reference';
        $family = function_exists( 'ncu_style_skin_family' ) ? ncu_style_skin_family( $skin ) : '';
        if ( ! array_key_exists( 'popular_site_template', $saved ) ) $saved['popular_site_template'] = $family ?: 'academic-red';
        if ( ! array_key_exists( 'template_selection_mode', $saved ) ) $saved['template_selection_mode'] = 'skin';
        $saved['aggressive_style_takeover'] = 1;
        $saved['style_takeover_colors'] = 1;
        $saved['style_takeover_typography'] = 1;
        $saved['style_takeover_text_styles'] = 1;
        $saved['header_owner_mode'] = 'theme';
        $saved['header_enabled'] = 0;
        $saved['footer_enabled'] = 0;
    }

    /* 14.1.6: discontinued presenter-specific design vocabulary is removed.
     * Header/Footer are explicit opt-in presentation chrome across the suite. */
    if ( version_compare( $installed, '14.1.6', '<' ) ) {
        if ( empty( $saved['style_takeover_preset'] ) || ( function_exists( 'ncu_style_takeover_preset_valid' ) && ! ncu_style_takeover_preset_valid( $saved['style_takeover_preset'] ) ) ) {
            $saved['style_takeover_preset'] = 'academic-reference';
        }
        $family = function_exists( 'ncu_style_skin_family' ) ? ncu_style_skin_family( $saved['style_takeover_preset'] ) : '';
        if ( empty( $family ) ) $family = 'academic-red';
        $saved['popular_site_template'] = $family;
        $saved['header_enabled'] = 0;
        $saved['footer_enabled'] = 0;
    }

    /* 14.1.7: retire nonessential image-based wp-admin branding and workspace strip.
     * Existing installations must be cleaned up too; defaults alone do not affect saved options. */
    if ( version_compare( $installed, '14.1.7', '<' ) ) {
        $saved['admin_skin_workspace_bar'] = 0;
        $saved['admin_bar_brand_enabled'] = 0;
    }

    /* 14.2.3: the first focus-panel release could remain invisible on an existing
     * installation if an older saved option explicitly kept the new controls off.
     * The user requested Full Screen editing as the compact-editor default, so turn
     * the presentation feature on once during this upgrade. This changes no plugin
     * data, meta boxes or save callbacks. */
    if ( version_compare( $installed, '14.2.3', '<' ) ) {
        $saved['admin_editor_tools_drawer'] = 1;
        $saved['admin_editor_high_contrast'] = 1;
        $saved['admin_editor_focus_panels'] = 1;
        $saved['admin_editor_hide_plugin_panels_mobile'] = 1;
    }

    /* 15.0.0: recover the compact full-screen editor presentation after the
     * white-screen regression. This changes presentation defaults only; live
     * post content, plugin meta boxes, save callbacks and metadata ownership
     * remain with WordPress/provider plugins. */
    if ( version_compare( $installed, '15.0.0', '<' ) ) {
        $saved['admin_editor_tools_drawer'] = 1;
        $saved['admin_editor_high_contrast'] = 1;
        $saved['admin_editor_focus_panels'] = 1;
        $saved['admin_editor_hide_plugin_panels_mobile'] = 1;
    }


    /* 15.0.1: Native Editor Space Recovery. The prior focus-panel experiment could
     * still hide or cover real Post Content/meta boxes on some mobile editors.
     * Retire that presentation mode for every existing installation. Keep only
     * the compact Editor Tools hamburger; WordPress/provider fields stay in the
     * normal document flow and remain directly editable. */
    if ( version_compare( $installed, '15.0.1', '<' ) ) {
        $saved['admin_editor_tools_drawer'] = 1;
        $saved['admin_editor_focus_panels'] = 0;
        $saved['admin_editor_hide_plugin_panels_mobile'] = 0;
    }


    /* 15.0.2: Editor Overlay Removal. The hamburger remains, but every
     * viewport-sized editor shell/backdrop and body scroll lock is retired.
     * High-contrast editor repaint is also turned off once on upgrade so the
     * WordPress editor returns to its native visual surface. */
    if ( version_compare( $installed, '15.0.2', '<' ) ) {
        $saved['admin_editor_tools_drawer'] = 1;
        $saved['admin_editor_high_contrast'] = 0;
        $saved['admin_editor_focus_panels'] = 0;
        $saved['admin_editor_hide_plugin_panels_mobile'] = 0;
    }

    /* 13.0: keep public developer overlays retired and migrate the former standalone UI controller. */
    foreach ( array( 'doctor_frontend_enabled', 'frontend_edit_enabled', 'frontend_edit_badges', 'frontend_edit_theme_regions', 'frontend_edit_content', 'frontend_edit_builder', 'frontend_edit_featured' ) as $public_control_key ) {
        $saved[ $public_control_key ] = 0;
    }
    if ( function_exists( 'ncu13_ui_migrate_legacy_settings' ) ) { ncu13_ui_migrate_legacy_settings(); }

    if ( ! empty( $saved['admin_skin_enabled'] ) ) { $saved['style_takeover_admin'] = 0; }

    $saved = ncu_recursive_parse_args( $saved, ncu_core_defaults() );
    update_option( 'ncu_settings', ncu_sanitize_settings( $saved ), false );

    $builders = get_option( 'ncu_builder_settings', array() );
    $builders = is_array( $builders ) ? $builders : array();
    update_option( 'ncu_builder_settings', ncu_sanitize_builder_settings( array_replace_recursive( ncu_builder_defaults(), $builders ) ), false );
    update_option( 'ncu_core_version', NCU_CORE_VERSION, false );
}
