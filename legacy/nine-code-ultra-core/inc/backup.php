<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_post_ncu_export_settings', 'ncu_export_settings' );
function ncu_export_settings() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code-ultra-core' ) ); }
    check_admin_referer( 'ncu_export_settings' );
    $payload = array(
        'format'           => '9-code-ultra-settings',
        'schema'           => 3,
        'core_version'     => NCU_CORE_VERSION,
        'exported_at'      => gmdate( 'c' ),
        'settings'         => ncu_get_settings(),
        'builder_settings' => function_exists( 'ncu_get_builder_settings' ) ? ncu_get_builder_settings() : array(),
    );
    ncu_send_json_download( '9-code-ultra-settings-' . gmdate( 'Ymd-His' ) . '.json', $payload );
}

add_action( 'admin_post_ncu_import_settings', 'ncu_import_settings' );
function ncu_import_settings() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code-ultra-core' ) ); }
    check_admin_referer( 'ncu_import_settings' );
    if ( empty( $_FILES['ncu_backup']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ncu_backup']['tmp_name'] ) ) { wp_die( esc_html__( 'No valid backup file was uploaded.', 'nine-code-ultra-core' ) ); }
    if ( ! empty( $_FILES['ncu_backup']['size'] ) && (int) $_FILES['ncu_backup']['size'] > 1048576 ) { wp_die( esc_html__( 'Backup file is too large.', 'nine-code-ultra-core' ) ); }
    $json = file_get_contents( $_FILES['ncu_backup']['tmp_name'] );
    $data = json_decode( $json, true );
    $schema = isset( $data['schema'] ) ? (int) $data['schema'] : 0;
    if ( ! is_array( $data ) || '9-code-ultra-settings' !== ( isset( $data['format'] ) ? $data['format'] : '' ) || ! in_array( $schema, array( 1, 2, 3 ), true ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
        wp_die( esc_html__( 'This is not a valid 9Core 15 settings backup.', 'nine-code-ultra-core' ) );
    }
    update_option( 'ncu_settings', ncu_sanitize_settings( array_replace_recursive( ncu_core_defaults(), $data['settings'] ) ), false );
    if ( $schema >= 2 && ! empty( $data['builder_settings'] ) && is_array( $data['builder_settings'] ) && function_exists( 'ncu_sanitize_builder_settings' ) ) {
        update_option( 'ncu_builder_settings', ncu_sanitize_builder_settings( $data['builder_settings'] ), false );
    }
    if ( function_exists( 'ncu_notice_push' ) ) { ncu_notice_push( '9Core 15 settings imported and validated.', 'success', 'settings-imported' ); }
    wp_safe_redirect( add_query_arg( array( 'page' => 'nine-code-ultra-backup' ), admin_url( 'admin.php' ) ) );
    exit;
}

function ncu_send_json_download( $filename, $payload ) {
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    exit;
}

/** v3 portable style-only package for rapid client-site reuse. */
function ncu_style_pack_keys() {
    return array(
        'aggressive_style_takeover','style_takeover_preset','style_takeover_colors','style_takeover_typography','style_takeover_text_styles','style_takeover_admin','style_takeover_respect_optout','style_takeover_strict','style_takeover_dynamic_bridge',
        'style_custom_primary','style_custom_secondary','style_custom_accent','style_custom_surface','style_custom_surface_alt','style_custom_text','style_custom_muted','style_custom_border','style_custom_heading_font','style_custom_body_font','style_custom_ui_font',
        'dark_mode_enabled','dark_mode_default','dark_toggle_enabled','dark_floating_toggle','dark_toggle_position',
    );
}

add_action( 'admin_post_ncu_export_style_pack', 'ncu_export_style_pack' );
function ncu_export_style_pack() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code-ultra-core' ) ); }
    check_admin_referer( 'ncu_export_style_pack' );
    $settings = ncu_get_settings(); $style = array();
    foreach ( ncu_style_pack_keys() as $key ) { if ( array_key_exists( $key, $settings ) ) { $style[ $key ] = $settings[ $key ]; } }
    $tokens = function_exists( 'ncu_get_effective_design_tokens' ) ? ncu_get_effective_design_tokens( $settings ) : array();
    $payload = array(
        'format'       => '9-code-ultra-style-pack',
        'schema'       => 1,
        'core_version' => NCU_CORE_VERSION,
        'exported_at'  => gmdate( 'c' ),
        'label'        => isset( $tokens['family_label'], $tokens['label'] ) ? $tokens['family_label'] . ' — ' . $tokens['label'] : '9Core 15 Style',
        'style'        => $style,
    );
    ncu_send_json_download( '9-code-ultra-style-' . sanitize_title( $payload['label'] ) . '-' . gmdate( 'Ymd-His' ) . '.9style.json', $payload );
}

add_action( 'admin_post_ncu_import_style_pack', 'ncu_import_style_pack' );
function ncu_import_style_pack() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code-ultra-core' ) ); }
    check_admin_referer( 'ncu_import_style_pack' );
    if ( empty( $_FILES['ncu_style_pack']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ncu_style_pack']['tmp_name'] ) ) { wp_die( esc_html__( 'No valid style package was uploaded.', 'nine-code-ultra-core' ) ); }
    if ( ! empty( $_FILES['ncu_style_pack']['size'] ) && (int) $_FILES['ncu_style_pack']['size'] > 262144 ) { wp_die( esc_html__( 'Style package is too large.', 'nine-code-ultra-core' ) ); }
    $data = json_decode( (string) file_get_contents( $_FILES['ncu_style_pack']['tmp_name'] ), true );
    if ( ! is_array( $data ) || '9-code-ultra-style-pack' !== ( isset( $data['format'] ) ? $data['format'] : '' ) || 1 !== (int) ( isset( $data['schema'] ) ? $data['schema'] : 0 ) || empty( $data['style'] ) || ! is_array( $data['style'] ) ) {
        wp_die( esc_html__( 'This is not a valid 9Core 15 style package.', 'nine-code-ultra-core' ) );
    }
    $allowed = array_flip( ncu_style_pack_keys() );
    $incoming = array_intersect_key( $data['style'], $allowed );
    $merged = array_replace_recursive( ncu_get_settings(), $incoming );
    update_option( 'ncu_settings', ncu_sanitize_settings( $merged ), false );
    if ( function_exists( 'ncu_notice_push' ) ) { ncu_notice_push( 'Style package imported and validated. No plugin layout or content setting was changed.', 'success', 'style-imported' ); }
    wp_safe_redirect( admin_url( 'admin.php?page=nine-code-ultra-style-takeover' ) );
    exit;
}
