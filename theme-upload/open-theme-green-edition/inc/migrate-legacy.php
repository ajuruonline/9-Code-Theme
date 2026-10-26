<?php
/**
 * Upgrade path from the old three-package install
 * (9Code 15 Theme + 9Core 15 plugin + 9 Data Manager plugin).
 *
 * - Deactivates the retired 9Core plugin (its code now lives in this theme).
 * - Leaves every data/application plugin active. Since 20.0.0 the theme is a
 *   host only; Data Manager and all business workflows belong to plugins.
 * - Copies theme mods (menus, logo, widgets) from the old theme folder.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ncu_legacy_core_plugin_file() { return 'nine-code-ultra-core/nine-code-ultra-core.php'; }
function ncu_migrate_deactivate_legacy_plugins() {
    if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $core = ncu_legacy_core_plugin_file();
    if ( is_plugin_active( $core ) ) {
        deactivate_plugins( $core, true );
        set_transient( 'ncu_migrated_core_notice', 1, DAY_IN_SECONDS );
    }
}
add_action( 'after_switch_theme', 'ncu_migrate_deactivate_legacy_plugins', 1 );
add_action( 'admin_init', function () {
    if ( current_user_can( 'activate_plugins' ) ) { ncu_migrate_deactivate_legacy_plugins(); }
}, 1 );

/** Copy theme mods from the previous theme folder the first time this theme is activated. */
function ncu_migrate_theme_mods() {
    if ( get_option( 'ncu_theme_mods_migrated' ) ) { return; }
    $old = get_option( 'theme_mods_9code-13-theme' );
    $new = get_theme_mods();
    if ( is_array( $old ) && $old && false === get_option( 'ncu_theme_display_defaults', false ) ) {
        /* Upgraded site: keep the old behaviour (plugins supplied the title). */
        add_option( 'ncu_theme_display_defaults', array( 'show_title' => 0, 'show_meta' => 0 ), '', false );
    }
    if ( is_array( $old ) && $old && empty( $new ) ) {
        foreach ( $old as $key => $value ) { if ( 'autosave_draft_ids' !== $key ) { set_theme_mod( $key, $value ); } }
    }
    update_option( 'ncu_theme_mods_migrated', 1, false );
}
add_action( 'after_switch_theme', 'ncu_migrate_theme_mods', 5 );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( get_transient( 'ncu_migrated_core_notice' ) ) {
        delete_transient( 'ncu_migrated_core_notice' );
        echo '<div class="notice notice-success is-dismissible"><p><strong>Open Scholar:</strong> the legacy Core plugin was deactivated because its features are now built into the theme. You can delete it.</p></div>';
    }
} );
