<?php
/**
 * Keep Jetpack's connection, app, sync, stats and background services while
 * removing its promotional/dashboard/editor takeover from daily workflows.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

foreach ( array(
    'jetpack_show_promotions',
    'jetpack_show_recommendations',
    'jetpack_just_in_time_msgs',
    'jetpack_show_dashboard_widget',
    'jetpack_gutenberg',
    'jetpack_enable_open_graph',
) as $filter ) {
    add_filter( $filter, '__return_false', PHP_INT_MAX );
}
add_filter( 'jetpack_disable_twitter_cards', '__return_true', PHP_INT_MAX );

add_action( 'wp_dashboard_setup', 'ncu_osch_jetpack_remove_dashboard_widgets', PHP_INT_MAX );
function ncu_osch_jetpack_remove_dashboard_widgets() {
    foreach ( array( 'jetpack_summary_widget', 'jetpack_dashboard_widget' ) as $id ) {
        remove_meta_box( $id, 'dashboard', 'normal' );
        remove_meta_box( $id, 'dashboard', 'side' );
    }
}

add_action( 'admin_bar_menu', 'ncu_osch_jetpack_clean_admin_bar', PHP_INT_MAX );
function ncu_osch_jetpack_clean_admin_bar( $bar ) {
    if ( ! is_object( $bar ) ) { return; }
    foreach ( array( 'jetpack', 'wpcom-logo', 'notes', 'reader' ) as $id ) { $bar->remove_node( $id ); }
}

add_action( 'admin_menu', 'ncu_osch_jetpack_settings_link', 30 );
function ncu_osch_jetpack_settings_link() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    add_submenu_page( 'nine-code-ultra', __( 'Jetpack Settings', 'nine-code' ), __( 'Jetpack Settings', 'nine-code' ), 'manage_options', 'open-scholar-jetpack', 'ncu_osch_jetpack_settings_page' );
}

add_action( 'admin_menu', 'ncu_osch_jetpack_hide_invasive_menus', PHP_INT_MAX );
function ncu_osch_jetpack_hide_invasive_menus() {
    /* Jetpack remains active. Only its duplicated navigation is removed. */
    foreach ( array( 'jetpack', 'jetpack_modules', 'jetpack-search', 'jetpack-protect', 'stats' ) as $slug ) { remove_menu_page( $slug ); }
}

function ncu_osch_jetpack_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $active = defined( 'JETPACK__VERSION' ) || class_exists( 'Jetpack', false );
    $url = admin_url( 'admin.php?page=jetpack#/settings' );
    ?>
    <div class="wrap ncu-admin" data-ninecode-settings-root>
        <?php ncu_admin_header( 'Jetpack Settings', 'Jetpack stays available for its app connection and background services without filling daily Open Scholar screens.' ); ?>
        <details class="ncu-panel ncu-panel--padded" data-ninecode-settings-section>
            <summary>Connection and background services</summary>
            <div class="ninecode-settings-body"><p><?php echo $active ? 'Jetpack is active. Sync, the mobile app connection, stats and explicitly enabled background modules continue to work.' : 'Jetpack is not currently active.'; ?></p><?php if ( $active ) : ?><p><a class="button button-primary" href="<?php echo esc_url( $url ); ?>">Open Jetpack settings</a></p><?php endif; ?></div>
        </details>
        <details class="ncu-panel ncu-panel--padded" data-ninecode-settings-section>
            <summary>Interface policy</summary>
            <div class="ninecode-settings-body"><p>Jetpack promotions, recommendations, dashboard cards, admin-bar shortcuts, Gutenberg blocks and duplicate social metadata are suppressed. No Jetpack module is deactivated by this theme.</p></div>
        </details>
    </div>
    <?php
}

add_action( 'admin_head', 'ncu_osch_jetpack_quiet_css', PHP_INT_MAX );
function ncu_osch_jetpack_quiet_css() {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display routing only.
    if ( 'open-scholar-jetpack' === $page || 0 === strpos( $page, 'jetpack' ) ) { return; }
    echo '<style id="open-scholar-jetpack-quiet">.jp-admin-notice,.jetpack-admin-notice,.dops-notice.is-jetpack,.components-notice[data-id*="jetpack"],.edit-post-header [aria-label*="Jetpack"],.editor-header [aria-label*="Jetpack"]{display:none!important}</style>';
}
