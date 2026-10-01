<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Nine Code Mobile Editor Workspace.
 *
 * Presentation-only editor layer. WordPress and provider plugins continue to
 * own every field, meta box, save callback and data record. 9Core keeps its
 * own settings/tools in one hamburger drawer and never repositions or hides
 * Post Content or provider meta boxes.
 */

function ncu_admin_screen_is_post_editor() {
    if ( ! is_admin() || ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) ) { return false; }
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    if ( 'elementor' === $action ) { return false; }
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    return (bool) ( $screen && 'post' === $screen->base );
}

function ncu_editor_workspace_is_post_editor() {
    return ncu_admin_screen_is_post_editor();
}

function ncu_editor_workspace_is_block_editor() {
    if ( ! ncu_editor_workspace_is_post_editor() ) { return false; }
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    return (bool) ( $screen && is_callable( array( $screen, 'is_block_editor' ) ) && $screen->is_block_editor() );
}

/**
 * Per-user presentation overrides for editor panels.
 * Values are booleans keyed by a stable panel identifier. They do not alter
 * add_meta_box(), plugin settings or stored post metadata.
 */
function ncu_editor_panel_visibility_map() {
    if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'get_user_meta' ) ) { return array(); }
    $user_id = (int) get_current_user_id();
    if ( $user_id <= 0 ) { return array(); }
    $saved = get_user_meta( $user_id, '_ncu_editor_panel_visibility', true );
    if ( ! is_array( $saved ) ) { return array(); }
    $clean = array();
    foreach ( $saved as $key => $value ) {
        $key = sanitize_text_field( (string) $key );
        if ( '' === $key || strlen( $key ) > 190 ) { continue; }
        $clean[ $key ] = ! empty( $value ) ? 1 : 0;
    }
    return $clean;
}

function ncu_editor_workspace_native_metabox_ids() {
    $ids = array(
        'submitdiv', 'postimagediv', 'categorydiv', 'tagsdiv-post_tag',
        'pageparentdiv', 'postexcerpt', 'trackbacksdiv', 'postcustom',
        'commentstatusdiv', 'commentsdiv', 'slugdiv', 'authordiv',
        'revisionsdiv', 'formatdiv', 'post-status-info',
    );
    return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) apply_filters( 'ncu_editor_native_metabox_ids', $ids ) ) ) ) );
}

add_filter( 'admin_body_class', 'ncu_editor_workspace_body_class', 50 );
function ncu_editor_workspace_body_class( $classes ) {
    if ( ! ncu_editor_workspace_is_post_editor() ) { return $classes; }
    $s = ncu_get_settings();
    $classes .= ' ncu-editor-workspace';
    if ( ! empty( $s['admin_editor_tools_drawer'] ) ) { $classes .= ' ncu-editor-tools-enabled'; }
    if ( ! empty( $s['admin_editor_high_contrast'] ) ) { $classes .= ' ncu-editor-high-contrast'; }
    return $classes;
}

add_action( 'admin_enqueue_scripts', 'ncu_editor_workspace_assets', 1200 );
function ncu_editor_workspace_assets() {
    if ( ! ncu_editor_workspace_is_post_editor() ) { return; }
    $s = ncu_get_settings();
    // 15.0.2: always load the tiny recovery layer on post editors. It removes
    // stale legacy overlays even when the hamburger/high-contrast options are off.
    wp_enqueue_style( 'ncu-editor-workspace', NCU_CORE_URL . 'assets/css/editor-workspace.css', array(), NCU_CORE_VERSION );
    wp_enqueue_script( 'ncu-editor-workspace', NCU_CORE_URL . 'assets/js/editor-workspace.js', array(), NCU_CORE_VERSION, true );
    if ( function_exists( 'wp_script_add_data' ) ) { wp_script_add_data( 'ncu-editor-workspace', 'strategy', 'defer' ); }

    $shortcuts = array();
    if ( current_user_can( 'edit_theme_options' ) ) {
        $shortcuts[] = array( 'label' => 'Nine Code', 'url' => admin_url( 'themes.php?page=ninecode-theme-display' ) );
        $shortcuts[] = array( 'label' => 'Appearance', 'url' => admin_url( 'themes.php' ) );
    }
    if ( current_user_can( 'manage_options' ) ) {
        $shortcuts[] = array( 'label' => '9Code Admin UI / Skin', 'url' => admin_url( 'admin.php?page=nine-code-ultra-admin-workspace' ) );
        $shortcuts[] = array( 'label' => 'WordPress Settings', 'url' => admin_url( 'options-general.php' ) );
    }
    if ( current_user_can( 'edit_posts' ) ) {
        if ( function_exists( 'ncu_core_data_manager_url' ) ) { $shortcuts[] = array( 'label' => 'Post Editor', 'url' => ncu_core_data_manager_url() ); }
        $shortcuts[] = array( 'label' => 'Category Manager', 'url' => admin_url( 'admin.php?page=nine-category-manager' ) );
        $shortcuts[] = array( 'label' => 'Quick Actions Settings', 'url' => admin_url( 'admin.php?page=ninecode-quick-actions' ) );
    }
    if ( current_user_can( 'upload_files' ) ) { $shortcuts[] = array( 'label' => 'Media Library', 'url' => admin_url( 'upload.php' ) ); }
    if ( current_user_can( 'activate_plugins' ) ) { $shortcuts[] = array( 'label' => 'Plugins', 'url' => function_exists( 'self_admin_url' ) ? self_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ) ); }
    $shortcuts[] = array( 'label' => 'View Site', 'url' => home_url( '/' ) );

    $ajax_url = function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '';
    $nonce = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'ncu_editor_panel_visibility' ) : '';

    wp_localize_script( 'ncu-editor-workspace', 'NCUEditorWorkspace', array(
        'enabled'          => true,
        'isBlockEditor'    => ncu_editor_workspace_is_block_editor(),
        'toolsDrawer'      => ! empty( $s['admin_editor_tools_drawer'] ),
        'focusPanels'      => false,
        'hidePluginPanels' => false,
        'breakpoint'       => (int) apply_filters( 'ncu_editor_workspace_breakpoint', 1180 ),
        'title'            => __( 'Editor Tools', 'nine-code' ),
        'shortcuts'        => apply_filters( 'ncu_editor_workspace_shortcuts', $shortcuts ),
    ) );
}

/** Persist one editor-panel presentation override for the current user. */
add_action( 'wp_ajax_ncu_save_editor_panel_visibility', 'ncu_ajax_save_editor_panel_visibility' );
function ncu_ajax_save_editor_panel_visibility() {
    if ( function_exists( 'check_ajax_referer' ) ) { check_ajax_referer( 'ncu_editor_panel_visibility', 'nonce' ); }
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 ); }
    $panel = isset( $_POST['panel'] ) ? sanitize_text_field( wp_unslash( $_POST['panel'] ) ) : '';
    $visible = ! empty( $_POST['visible'] ) ? 1 : 0;
    if ( '' === $panel || strlen( $panel ) > 190 ) { wp_send_json_error( array( 'message' => 'Invalid panel.' ), 400 ); }
    $map = ncu_editor_panel_visibility_map();
    $map[ $panel ] = $visible;
    update_user_meta( get_current_user_id(), '_ncu_editor_panel_visibility', $map );
    wp_send_json_success( array( 'panel' => $panel, 'visible' => $visible ) );
}

/** Reset the current user's per-panel overrides without affecting plugin data. */
add_action( 'admin_post_ncu_reset_editor_panel_visibility', 'ncu_reset_editor_panel_visibility' );
function ncu_reset_editor_panel_visibility() {
    if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code' ) ); }
    check_admin_referer( 'ncu_reset_editor_panel_visibility' );
    delete_user_meta( get_current_user_id(), '_ncu_editor_panel_visibility' );
    $return = wp_get_referer();
    if ( ! $return ) { $return = admin_url( 'themes.php?page=ninecode-theme-display' ); }
    wp_safe_redirect( add_query_arg( 'ncu_panels_reset', '1', $return ) );
    exit;
}

/**
 * Theme-menu bridge: Core owns editor behavior, Theme exposes the two master
 * presentation switches so the user does not need to hunt through plugins.
 */
add_action( 'ncu_theme_display_editor_controls', 'ncu_editor_workspace_theme_controls' );
function ncu_editor_workspace_theme_controls() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    ?>
    <hr>
    <section id="ncu-mobile-editor-controls" style="max-width:860px">
        <h2><?php esc_html_e( 'Mobile Editor Workspace', 'nine-code' ); ?></h2>
        <p><strong><?php esc_html_e( 'Native editor space is protected.', 'nine-code' ); ?></strong> <?php esc_html_e( 'Post Content and all available metadata/meta boxes remain in the normal WordPress editor flow. 9CODE settings, shortcuts and nonessential editor/plugin toolbar actions use the hamburger popover instead of covering the editor.', 'nine-code' ); ?></p>
        <p class="description"><?php esc_html_e( 'The former full-screen Edit Panels overlay and automatic plugin-meta-box hiding are retired in 15.0.2. The hamburger is non-modal: it has no full-screen shell, backdrop or body scroll lock.', 'nine-code' ); ?></p>
    </section>
    <?php
}

add_action( 'admin_post_ncu_save_mobile_editor_theme_controls', 'ncu_save_mobile_editor_theme_controls' );
function ncu_save_mobile_editor_theme_controls() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code' ) ); }
    check_admin_referer( 'ncu_save_mobile_editor_theme_controls' );
    $raw = isset( $_POST['ncu_mobile_editor'] ) && is_array( $_POST['ncu_mobile_editor'] ) ? wp_unslash( $_POST['ncu_mobile_editor'] ) : array();
    $raw['admin_editor_focus_panels'] = 0;
    $raw['admin_editor_hide_plugin_panels_mobile'] = 0;
    $keys = array( 'admin_editor_focus_panels', 'admin_editor_hide_plugin_panels_mobile' );
    ncu_save_settings_subset( $raw, $keys, $keys );
    wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-theme-display', 'updated' => '1', 'ncu_editor' => '1' ), admin_url( 'themes.php' ) ) );
    exit;
}
