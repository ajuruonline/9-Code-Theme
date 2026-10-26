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
    wp_enqueue_style( 'open-scholar-popup', NCU_CORE_URL . 'assets/css/open-scholar-popup.css', array( 'open-scholar-admin' ), NCU_CORE_VERSION );
    // Final density layer: retain green brand while removing wasted margins throughout wp-admin.
    wp_enqueue_style( 'osch-compact-admin', NCU_THEME_URI . '/assets/css/open-scholar-compact.css', array( 'open-scholar-popup' ), NCU_THEME_VERSION );
    // Open Theme visual layer loads last so it can refine every surface above.
    wp_enqueue_style( 'open-theme-visual', NCU_THEME_URI . '/assets/css/open-theme.css', array( 'osch-compact-admin' ), NCU_THEME_VERSION );
    wp_enqueue_script( 'open-scholar-admin', NCU_CORE_URL . 'assets/js/open-scholar-admin.js', array(), NCU_CORE_VERSION, true );
    wp_enqueue_script( 'osch-sharp-borders', NCU_THEME_URI . '/assets/js/open-scholar-sharp-borders.js', array(), NCU_THEME_VERSION, true );
    // Prefer WordPress Site Icon; the Open Scholar academic cap is the fallback.
    $site_icon = get_site_icon_url( 96 );
    wp_localize_script( 'open-scholar-admin', 'oschWorkspaceMenuConfig', ncu_osch_workspace_menu_configuration( isset( $GLOBALS['ncu_osch_workspace_catalog'] ) ? $GLOBALS['ncu_osch_workspace_catalog'] : array() ) );
    wp_localize_script( 'open-scholar-admin', 'oschNavBrand', array( 'icon' => esc_url_raw( $site_icon ? $site_icon : ncu_open_scholar_logo_url() ), 'cap' => esc_url_raw( ncu_open_scholar_logo_url() ), 'name' => 'Open Scholar' ) );
}

add_filter( 'admin_body_class', function ( $classes ) {
    return $classes . ' ncu-admin-skin ncu-admin-skin--open_scholar ncu-admin-menu-skin ncu-admin-enhance-tables ncu-admin-enhance-modals ncu-admin-editor-chrome';
} );

add_action( 'login_enqueue_scripts', 'ncu_open_scholar_login_assets', PHP_INT_MAX );
function ncu_open_scholar_login_assets() {
    wp_enqueue_style( 'open-scholar-login', NCU_CORE_URL . 'assets/css/open-scholar-admin.css', array(), NCU_CORE_VERSION );
    wp_enqueue_style( 'osch-compact-login', NCU_THEME_URI . '/assets/css/open-scholar-compact.css', array( 'open-scholar-login' ), NCU_THEME_VERSION );
    wp_enqueue_script( 'osch-sharp-borders', NCU_THEME_URI . '/assets/js/open-scholar-sharp-borders.js', array(), NCU_THEME_VERSION, true );
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

/** Top Bar direct action: flush the WordPress object cache only.
 * Third-party page/CDN caches remain delegated to their own toolbar handlers.
 */
add_action( 'wp_ajax_osch_clear_object_cache', 'ncu_osch_clear_object_cache' );
function ncu_osch_clear_object_cache() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You cannot clear the site cache.', 'nine-code' ) ), 403 );
    }
    check_ajax_referer( 'osch_clear_object_cache', 'nonce' );
    if ( ! function_exists( 'wp_cache_flush' ) ) {
        wp_send_json_error( array( 'message' => __( 'Object cache flush is unavailable.', 'nine-code' ) ), 500 );
    }
    $result = wp_cache_flush();
    if ( false === $result ) {
        wp_send_json_error( array( 'message' => __( 'The object cache service did not confirm the flush.', 'nine-code' ) ), 500 );
    }
    wp_send_json_success( array( 'message' => __( 'WordPress object cache cleared. To clear page/CDN cache, use the caching plugin command below if available.', 'nine-code' ) ) );
}

// Apply the no-rounded-outline visual standard across theme-controlled frontend UI.
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'osch-compact-frontend', NCU_THEME_URI . '/assets/css/open-scholar-compact.css', array(), NCU_THEME_VERSION );
    wp_enqueue_script( 'osch-sharp-borders', NCU_THEME_URI . '/assets/js/open-scholar-sharp-borders.js', array(), NCU_THEME_VERSION, true );
}, 99 );
