<?php
/**
 * Open Scholar identity and compatibility boundary.
 *
 * Internal ncu_/nine-code identifiers intentionally remain stable so upgrades
 * retain options, permissions, URLs, hooks and Data Manager records.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ncu_open_scholar_logo_url() {
    return NCU_CORE_URL . 'assets/images/open-scholar-mark.svg';
}

add_action( 'admin_bar_menu', 'ncu_open_scholar_account_label', PHP_INT_MAX );
function ncu_open_scholar_account_label( $bar ) {
    if ( ! is_object( $bar ) ) { return; }
    $bar->remove_node( 'search' );
    $node = $bar->get_node( 'my-account' );
    if ( ! $node ) { return; }
    $user = wp_get_current_user();
    $label = $user && $user->exists() ? $user->display_name : __( 'Account', 'nine-code' );
    $avatar = get_avatar( $user->ID, 26 );
    $bar->add_node( array( 'id'=>'my-account', 'title'=>esc_html( $label ) . $avatar, 'href'=>$node->href, 'meta'=>is_array( $node->meta ) ? $node->meta : array() ) );
}

add_filter( 'gettext', 'ncu_open_scholar_visible_platform_terms', 20, 3 );
function ncu_open_scholar_visible_platform_terms( $translation, $text, $domain ) {
    $page_now = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
    if ( ! is_admin() && 'wp-login.php' !== $page_now ) { return $translation; }
    $map = array(
        'WordPress' => 'Open Scholar',
        'WordPress Events and News' => 'Open Scholar News',
        'About WordPress' => 'About Open Scholar',
        'WordPress.org' => 'Open-source platform',
    );
    return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
}

add_action( 'admin_enqueue_scripts', 'ncu_open_scholar_admin_assets', PHP_INT_MAX );
function ncu_open_scholar_admin_assets() {
    wp_enqueue_style( 'ncu-admin-skin', NCU_CORE_URL . 'assets/css/admin-skin.css', array(), NCU_CORE_VERSION );
    wp_enqueue_style( 'ncu-admin-menu', NCU_CORE_URL . 'assets/css/admin-menu.css', array( 'ncu-admin-skin' ), NCU_CORE_VERSION );
    wp_enqueue_style( 'open-scholar-admin', NCU_CORE_URL . 'assets/css/open-scholar-admin.css', array(), NCU_CORE_VERSION );
    wp_enqueue_script( 'open-scholar-admin', NCU_CORE_URL . 'assets/js/open-scholar-admin.js', array(), NCU_CORE_VERSION, true );
    $screen = get_current_screen();
    if ( $screen && 'post' === $screen->base && $screen->is_block_editor() ) {
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        $post_url = $post_id ? get_permalink( $post_id ) : '';
        wp_enqueue_style( 'ncu-editor-workspace', NCU_CORE_URL . 'assets/css/editor-workspace.css', array(), NCU_CORE_VERSION );
        wp_enqueue_script( 'ncu-editor-workspace', NCU_CORE_URL . 'assets/js/editor-workspace.js', array(), NCU_CORE_VERSION, true );
        wp_localize_script( 'ncu-editor-workspace', 'NCUEditorWorkspace', array(
            'enabled'=>true, 'isBlockEditor'=>true, 'toolsDrawer'=>true,
            'consolidateTopbar'=>true, 'title'=>'Open Scholar Editor',
            'shortcuts'=>array(
                array( 'label'=>$post_url ? 'View post' : 'View site', 'url'=>$post_url ?: home_url( '/' ) ),
                array( 'label'=>'Post settings', 'action'=>'post-settings' ),
                array( 'label'=>'Upload Plugin', 'url'=>self_admin_url( 'plugin-install.php?tab=upload' ) ),
            ),
        ) );
    }
}

add_filter( 'admin_body_class', function ( $classes ) {
    $screen = get_current_screen();
    if ( $screen && 'post' === $screen->base && $screen->is_block_editor() ) {
        $classes .= ' ncu-editor-workspace ncu-editor-tools-enabled ncu-editor-topbar-consolidated';
    }
    return $classes . ' ncu-admin-skin ncu-admin-skin--open_scholar ncu-admin-menu-skin ncu-admin-enhance-tables ncu-admin-enhance-modals ncu-admin-editor-chrome';
} );

add_action( 'login_enqueue_scripts', 'ncu_open_scholar_login_assets', PHP_INT_MAX );
function ncu_open_scholar_login_assets() {
    wp_enqueue_style( 'open-scholar-login', NCU_CORE_URL . 'assets/css/open-scholar-admin.css', array(), NCU_CORE_VERSION );
}

/** Apply the major-version policy once without overwriting client logos/content. */
add_action( 'admin_init', 'ncu_open_scholar_maybe_migrate', 6 );
function ncu_open_scholar_maybe_migrate() {
    if ( '20.0.0' === (string) get_option( 'ncu_open_scholar_migration', '' ) ) { return; }

    $saved = get_option( 'ncu_settings', array() );
    $saved = is_array( $saved ) ? $saved : array();
    $saved['white_label_enabled'] = 1;
    $saved['admin_style_enabled'] = 1;
    $saved['admin_skin_enabled'] = 1;
    $saved['admin_skin_menu_enabled'] = 1;
    $saved['admin_skin_monochrome'] = 0;
    $saved['admin_skin_mode'] = 'open_scholar';
    $saved['admin_bar_brand_enabled'] = 1;
    $saved['admin_bar_label'] = 'Open Scholar';
    $saved['login_branding_enabled'] = 1;
    $saved['admin_footer_branding'] = 1;
    $saved['hide_wp_dashboard_news'] = 0;
    $saved['header_enabled'] = 0;
    $saved['footer_enabled'] = 0;
    $saved['style_custom_primary'] = '#14532d';
    $saved['style_custom_secondary'] = '#123d2a';
    $saved['style_custom_accent'] = '#14532d';
    $saved['style_custom_surface_alt'] = '#f2f5f3';
    $saved['style_custom_text'] = '#191919';
    $saved['style_custom_muted'] = '#5e5e5e';
    $saved['style_custom_border'] = '#e0dfdc';
    $saved['accent_color'] = '#14532d';
    $saved['text_color'] = '#191919';
    $saved['muted_color'] = '#5e5e5e';
    update_option( 'ncu_settings', ncu_sanitize_settings( array_replace( ncu_core_defaults(), $saved ) ), false );

    /* Legacy presentation options are retired, not deleted. External plugins
     * can still migrate their own records without the theme rewriting them. */
    foreach ( array( 'n9lh8_settings', 'n9f_settings' ) as $option ) {
        $surface = get_option( $option, array() );
        if ( is_array( $surface ) ) { $surface['enabled'] = 'no'; update_option( $option, $surface, false ); }
    }
    update_option( 'ncu_open_scholar_migration', '20.0.0', false );
}

/** Public contract for Open Scholar-family plugins. */
function ncu_open_scholar_theme_contract() {
    return array(
        'id' => 'open-scholar-theme',
        'version' => NCU_THEME_VERSION,
        'palette' => array( 'accent'=>'#14532d', 'page'=>'#f2f5f3', 'card'=>'#ffffff', 'text'=>'#191919', 'muted'=>'#5e5e5e', 'border'=>'#e0dfdc', 'soft'=>'#e5f2eb' ),
        'header_owner' => 'plugin',
        'footer_owner' => 'plugin',
        'menu_policy' => 'open-scholar-apps-maintenance-in-launcher',
        'settings_policy' => 'closed-mobile-explained',
        'application_features' => 'plugins',
        'data_owner' => 'provider-plugin',
        'seo_fallback' => 'theme-yields-to-seo-plugin',
        'legacy_id' => '9code-theme',
    );
}

add_filter( 'ninecodepress_component_contracts', 'ncu_open_scholar_component_contract', 30 );
function ncu_open_scholar_component_contract( $contracts ) {
    $contracts = is_array( $contracts ) ? $contracts : array();
    $contracts['open-scholar-theme'] = ncu_open_scholar_theme_contract();
    return $contracts;
}
