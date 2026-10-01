<?php
/**
 * Upgrade path from the old three-package install
 * (9Code 15 Theme + 9Core 15 plugin + 9 Data Manager plugin).
 *
 * - Deactivates the retired 9Core plugin (its code now lives in this theme).
 * - Deactivates the retired "9 Data Manager" plugin once its replacement,
 *   Nine Code Data, is installed and active. No data is touched: option names,
 *   post types, meta keys and tables are unchanged.
 * - Copies theme mods (menus, logo, widgets) from the old theme folder.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ncu_legacy_core_plugin_file() { return 'nine-code-ultra-core/nine-code-ultra-core.php'; }
function ncu_legacy_data_plugin_file() { return 'nine10-data-edition/nine55-ultron-data.php'; }

function ncu_migrate_deactivate_legacy_plugins() {
    if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $core = ncu_legacy_core_plugin_file();
    if ( is_plugin_active( $core ) ) {
        deactivate_plugins( $core, true );
        set_transient( 'ncu_migrated_core_notice', 1, DAY_IN_SECONDS );
    }
    $data = ncu_legacy_data_plugin_file();
    if ( is_plugin_active( $data ) && is_plugin_active( 'nine-code-data/nine-code-data.php' ) ) {
        deactivate_plugins( $data, true );
        set_transient( 'ncu_migrated_data_notice', 1, DAY_IN_SECONDS );
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
        echo '<div class="notice notice-success is-dismissible"><p><strong>Nine Code:</strong> the old 9Core plugin was deactivated because its features are now built into the theme. You can delete it.</p></div>';
    }
    if ( get_transient( 'ncu_migrated_data_notice' ) ) {
        delete_transient( 'ncu_migrated_data_notice' );
        echo '<div class="notice notice-success is-dismissible"><p><strong>Nine Code:</strong> the old 9 Data Manager plugin was replaced by Nine Code Data. All your data is unchanged. You can delete the old plugin.</p></div>';
    }
} );
