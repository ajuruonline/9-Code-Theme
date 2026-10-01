<?php
/**
 * Plugin Name: 9Core 15
 * Plugin URI: https://9igeria.online/
 * Description: Shared infrastructure/control plane for 9CodePress Edition 9.10. 9 Data owns data/AI/post/category/tag workflows when installed; Core keeps a legacy fallback data engine only for upgrade continuity.
 * Version: 15.0.2
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: 9igeria Online Ltd
 * Author URI: https://9igeria.online/
 * Text Domain: nine-code-ultra-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NCU_CORE_VERSION', '15.0.2' );
define( 'NCU_CORE_RELEASE_SHOT', 'Native Editor Space Recovery' );
define( 'NCU_CORE_RELEASE_SHOT_SUMMARY', 'Restore native Post Content and metadata editing, retire the blocking full-screen focus layer, and keep 9CODE settings inside one hamburger drawer.' );
define( 'NCU_CORE_API_VERSION', 14 );
define( 'NINECODE_SUITE_CORE_VERSION', '15.0.2' );
if ( ! defined( 'NINECODE_SUITE_CONTRACT_MAJOR' ) ) { define( 'NINECODE_SUITE_CONTRACT_MAJOR', 14 ); }
define( 'NCU_CORE_FILE', __FILE__ );
define( 'NCU_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'NCU_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'NCU_DATA_ENGINE_VERSION', '0.17.0' );
define( 'NCU_DATA_ENGINE_DIR', NCU_CORE_DIR . 'inc/data-engine/' );
define( 'NCU_DATA_ENGINE_URL', NCU_CORE_URL . 'assets/data-engine/' );

/** API 14 is the current suite contract. The compatibility function remains
 * tolerant of older API-2+ Themes during an upgrade window, while diagnostics
 * identify anything that has not yet joined API 14. */
function ncu_core_theme_api_compatible( $theme_api ) {
    $theme_api = (int) $theme_api;
    return $theme_api >= 2 && $theme_api <= (int) NCU_CORE_API_VERSION;
}

/**
 * Edition 9.10 ownership guard.
 *
 * 9 Data Manager is the authoritative owner of Post Editor, Category Manager,
 * Post Creator, Form Manager and Data Backup. Core keeps its older embedded data engine only as
 * a compatibility fallback when 9 Data is not active. Detection happens from
 * WordPress' active-plugin registry so it is safe regardless of plugin load
 * order and also covers network activation.
 */
function ncu_core_external_data_edition_active() {
    $targets = array(
        'nine10-data-edition/nine55-ultron-data.php',
        'nine55-ultron-data/nine55-ultron-data.php',
    );
    $active = (array) get_option( 'active_plugins', array() );
    foreach ( $targets as $target ) {
        if ( in_array( $target, $active, true ) ) { return true; }
    }
    if ( is_multisite() ) {
        $network = (array) get_site_option( 'active_sitewide_plugins', array() );
        foreach ( $targets as $target ) {
            if ( isset( $network[ $target ] ) ) { return true; }
        }
    }
    return defined( 'NINE55_ULTRON_DATA_VERSION' ) && version_compare( NINE55_ULTRON_DATA_VERSION, '9.10.0', '>=' );
}

function ncu_core_uses_legacy_data_fallback() {
    return ! ncu_core_external_data_edition_active();
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
/* Edition 9.10: 9 Data owns the active data stack. These classes remain only
 * as a reversible legacy fallback for sites upgrading Core before installing
 * 9 Data. */
if ( ncu_core_uses_legacy_data_fallback() ) {
    require_once NCU_DATA_ENGINE_DIR . 'class-ninecode-scope-lock.php';
    require_once NCU_DATA_ENGINE_DIR . 'class-ninecode-data-exporter.php';
    require_once NCU_DATA_ENGINE_DIR . 'class-ninecode-data-importer.php';
    require_once NCU_DATA_ENGINE_DIR . 'class-ninecode-excel.php';
    require_once NCU_DATA_ENGINE_DIR . 'class-ninecode-data-version-manager.php';
    require_once NCU_DATA_ENGINE_DIR . 'class-ninecode-acf-data-engine.php';
    require_once NCU_DATA_ENGINE_DIR . 'ai-data-workspace.php';
}

register_activation_hook( __FILE__, 'ncu_core_activate' );
function ncu_core_activate() {
    global $wp_version;

    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( '9Core 15 requires PHP 7.4 or newer.', 'nine-code-ultra-core' ) );
    }

    if ( isset( $wp_version ) && version_compare( $wp_version, '6.6', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( '9Core 15 requires WordPress 6.6 or newer.', 'nine-code-ultra-core' ) );
    }

    if ( false === get_option( 'ncu_settings', false ) ) {
        add_option( 'ncu_settings', ncu_core_defaults(), '', false );
    }
    if ( false === get_option( 'ncu_builder_settings', false ) ) {
        add_option( 'ncu_builder_settings', ncu_builder_defaults(), '', false );
    }
    if ( ncu_core_uses_legacy_data_fallback() && class_exists( 'NCU_Data_ACF_Data_Engine' ) ) { NCU_Data_ACF_Data_Engine::activate(); }
    update_option( 'ncu_core_version', NCU_CORE_VERSION, false );
}

add_action( 'plugins_loaded', 'ncu_core_boot' );
add_action( 'admin_init', 'ncu_core_maybe_upgrade', 5 );
function ncu_core_boot() {
    load_plugin_textdomain( 'nine-code-ultra-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    if ( ncu_core_uses_legacy_data_fallback() ) { ncu_core_data_engine_boot(); }
}

/**
 * 13.3: Core is the single authoritative data engine.
 *
 * Older standalone Data Engine builds may remain installed during migration,
 * but their callbacks are detached before WordPress reaches init/admin-post so
 * they cannot create a second import history, menu or rollback pipeline.
 */
function ncu_core_data_engine_boot() {
    if ( ! ncu_core_uses_legacy_data_fallback() || ! class_exists( 'NCU_Data_ACF_Data_Engine' ) ) { return; }
    ncu_core_quarantine_legacy_data_engine();
    NCU_Data_ACF_Data_Engine::activate();
    $engine = NCU_Data_ACF_Data_Engine::instance();
    $engine->maybe_upgrade();
}

function ncu_core_quarantine_legacy_data_engine() {
    if ( ! class_exists( 'NineCode_ACF_Data_Engine' ) ) { return; }
    global $wp_filter;
    $tags = array(
        'plugins_loaded', 'init', 'admin_menu', 'admin_enqueue_scripts', 'admin_notices',
        'acf/load_field_group', 'acf/save_post', 'elementor/dynamic_tags/register',
        'admin_post_ninecode_save_term_data', 'admin_post_ninecode_save_data_view',
        'admin_post_ninecode_delete_data_view', 'admin_post_ninecode_save_terms',
        'admin_post_ninecode_save_plugin_meta', 'admin_post_ninecode_bulk_category_update',
        'admin_post_ninecode_save_allocations', 'admin_post_ninecode_save_registry',
        'admin_post_ninecode_delete_registry', 'admin_post_ninecode_acf_export_record',
        'admin_post_ninecode_acf_export_collection', 'admin_post_ninecode_acf_export_schema',
        'admin_post_ninecode_acf_export_backup', 'admin_post_ninecode_acf_import',
        'admin_post_ninecode_acf_apply_stage', 'admin_post_ninecode_acf_restore_backup',
        'admin_post_ninecode_acf_undo_import', 'admin_post_ninecode_acf_restore_version',
    );
    foreach ( $tags as $tag ) {
        if ( empty( $wp_filter[ $tag ] ) || ! is_object( $wp_filter[ $tag ] ) || empty( $wp_filter[ $tag ]->callbacks ) ) { continue; }
        foreach ( $wp_filter[ $tag ]->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                $function = $callback['function'] ?? null;
                if ( ! is_array( $function ) || empty( $function[0] ) ) { continue; }
                $owner = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
                if ( 'NineCode_ACF_Data_Engine' === $owner ) {
                    remove_filter( $tag, $function, $priority );
                }
            }
        }
    }
}

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
    if ( ncu_core_uses_legacy_data_fallback() && class_exists( 'NCU_Data_ACF_Data_Engine' ) ) { NCU_Data_ACF_Data_Engine::activate(); }
    update_option( 'ncu_core_version', NCU_CORE_VERSION, false );
}
