<?php
/** Open Scholar application home and curated navigation. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** First-party applications can declare their menu slug explicitly. */
function osch_workspace_is_app( $slug, $label ) {
    $text = strtolower( wp_strip_all_tags( $label ) );
    $match = (bool) preg_match( '/^(?:open[ _-]?scholar|osch|nine|ncu|n9|9|conference|alp|power[-_]?lecture|e[-_]?lecture|e[-_]?library|e[-_]?learning|e[-_]?textbook|e[-_]?step|e[-_]?clas|e[-_]?presentation|e[-_]?lectura)/i', $slug );
    $match = $match || (bool) preg_match( '/^(?:9\b|9code\b|open\s+(?:scholar|index|navigator|publications|student|share|journal)|power lecture|e[- ]?(?:step|library|learning|clas|presentation|textbooks|lectura)|lecture notes|conference)/i', $text );
    return (bool) apply_filters( 'open_scholar_workspace_is_app', $match, $slug, $label );
}

function osch_workspace_apps() {
    return isset( $GLOBALS['osch_workspace_apps'] ) ? $GLOBALS['osch_workspace_apps'] : array();
}

add_action( 'admin_menu', 'osch_workspace_collect_apps', PHP_INT_MAX );
/* Keep essential WordPress administration available without making it compete
 * with the application library. It is one deliberately branded utility menu. */
add_action( 'admin_menu', 'osch_workspace_register_control_centre', 99990 );
function osch_workspace_register_control_centre() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    add_menu_page( 'Open Scholar Control Centre', 'Control Centre', 'manage_options', 'osch-system', 'osch_workspace_control_centre', 'dashicons-admin-generic', 99 );
    add_submenu_page( 'osch-system', 'Control Centre', 'Overview', 'manage_options', 'osch-system', 'osch_workspace_control_centre' );
    add_submenu_page( 'osch-system', 'Settings', 'Settings', 'manage_options', 'options-general.php' );
    add_submenu_page( 'osch-system', 'Appearance', 'Appearance', 'edit_theme_options', 'themes.php' );
    add_submenu_page( 'osch-system', 'Tools', 'Tools', 'edit_posts', 'tools.php' );
    add_submenu_page( 'osch-system', 'Plugins', 'Plugins', 'activate_plugins', 'plugins.php' );
    add_submenu_page( 'osch-system', 'Users', 'Users', 'list_users', 'users.php' );
    add_submenu_page( 'osch-system', 'Updates', 'Updates', 'update_core', 'update-core.php' );
}

function osch_workspace_control_centre() {
    $items = array(
        array( 'Settings', 'options-general.php', 'Site name, reading, permalinks and core settings.' ),
        array( 'Appearance', 'themes.php', 'Themes, navigation and visual system controls.' ),
        array( 'Tools', 'tools.php', 'Import, export and maintenance tools.' ),
        array( 'Plugins', 'plugins.php', 'Activate, update and manage installed applications.' ),
        array( 'Users', 'users.php', 'People, roles and access.' ),
        array( 'Updates', 'update-core.php', 'Core, theme and application updates.' ),
    );
    echo '<main class="osch-control-centre"><span class="osch-eyebrow">OPEN SCHOLAR · SYSTEM</span><h1>Control Centre</h1><p>Essential platform controls are collected here so the main navigation stays focused on your Open Scholar apps.</p><div class="osch-control-grid">';
    foreach ( $items as $item ) {
        echo '<a href="' . esc_url( admin_url( $item[1] ) ) . '"><strong>' . esc_html( $item[0] ) . '</strong><span>' . esc_html( $item[2] ) . '</span><b aria-hidden="true">↗</b></a>';
    }
    echo '</div></main>';
}

function osch_workspace_collect_apps() {
    global $menu;
    $apps = array();
    foreach ( (array) $menu as $item ) {
        if ( empty( $item[2] ) || empty( $item[1] ) || ! current_user_can( $item[1] ) ) { continue; }
        $slug = (string) $item[2];
        if ( ! osch_workspace_is_app( $slug, (string) $item[0] ) ) { continue; }
        $url = false !== strpos( $slug, '.php' ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
        $apps[ $slug ] = array( 'label'=>trim( wp_strip_all_tags( $item[0] ) ), 'url'=>$url );
    }
    $GLOBALS['osch_workspace_apps'] = $apps;
    /* Remove navigation entries only; the launcher retains maintenance routes. */
    foreach ( (array) $menu as $key=>$item ) {
        if ( empty( $item[2] ) ) { continue; }
        $slug = (string) $item[2];
        if ( 'index.php' === $slug ) { $menu[$key][0] = 'Workspace'; continue; }
        if ( 'osch-system' === $slug ) { continue; }
        if ( ! isset( $apps[ $slug ] ) ) { unset( $menu[$key] ); }
    }
}

add_filter( 'admin_body_class', function ( $classes ) {
    $screen = get_current_screen();
    return $classes . ( $screen && 'dashboard' === $screen->id ? ' osch-workspace-home' : '' );
} );

/** Render outside the stock widget container so saved widget settings survive. */
add_action( 'admin_notices', 'osch_workspace_home' );
function osch_workspace_home() {
    $screen = get_current_screen();
    if ( ! $screen || 'dashboard' !== $screen->id ) { return; }
    $apps = osch_workspace_apps();
    $name = wp_get_current_user()->display_name;
    ?>
    <main class="osch-workspace" aria-label="Open Scholar workspace">
      <header class="osch-workspace-hero">
        <div class="osch-workspace-identity"><img src="<?php echo esc_url( ncu_open_scholar_logo_url() ); ?>" alt="" width="48" height="48"><span>OPEN SCHOLAR <small>YOUR CONNECTED CAMPUS</small></span></div>
        <span class="osch-workspace-status"><i aria-hidden="true"></i> Workspace connected</span>
        <h1>Your ideas.<br>Your entire ecosystem.</h1>
        <p>Welcome, <?php echo esc_html( $name ); ?>. Open an app and keep building.</p>
        <div class="osch-workspace-actions"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">View your site <span aria-hidden="true">↗</span></a><?php if ( current_user_can( 'install_plugins' ) ) : ?><a href="<?php echo esc_url( self_admin_url( 'plugin-install.php?tab=upload' ) ); ?>">Upload an app <span aria-hidden="true">＋</span></a><?php endif; ?></div>
      </header>
      <section class="osch-apps" aria-labelledby="osch-apps-title"><div class="osch-apps-heading"><div><span class="osch-eyebrow">ONE WORKSPACE · YOUR APPLICATIONS</span><h2 id="osch-apps-title">App library</h2></div><label class="osch-app-search"><span class="screen-reader-text">Find an Open Scholar app</span><input type="search" id="osch-app-search" placeholder="Find an app…"></label></div>
      <div class="osch-app-grid"><?php $number = 0; foreach ( $apps as $slug=>$app ) : $number++; ?>
        <a class="osch-app-tile" href="<?php echo esc_url( $app['url'] ); ?>" data-osch-app="<?php echo esc_attr( strtolower( $app['label'] ) ); ?>"><span class="osch-app-symbol" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/></svg></span><span class="osch-app-number"><?php echo esc_html( str_pad( (string) $number, 2, '0', STR_PAD_LEFT ) ); ?></span><h3><?php echo esc_html( $app['label'] ); ?></h3><span class="osch-app-open">Open application <span aria-hidden="true">↗</span></span></a>
      <?php endforeach; ?></div><p id="osch-app-empty" hidden>No matching apps. Try another name.</p></section>
      <footer class="osch-workspace-footer">OPEN SCHOLAR <span>Your institution. Your software.</span></footer>
    </main>
    <?php
}

add_action( 'admin_bar_menu', function ( $bar ) {
    if ( ! is_admin() ) { return; }
    foreach ( (array) $bar->get_nodes() as $node ) {
        if ( in_array( $node->parent, array( false, '', 'top-secondary' ), true ) && ! in_array( $node->id, array( 'menu-toggle', 'my-account', 'top-secondary' ), true ) ) {
            $bar->remove_node( $node->id );
        }
    }
    $bar->add_node( array( 'id'=>'osch-workspace', 'title'=>'Open Scholar', 'href'=>admin_url(), 'meta'=>array( 'class'=>'osch-bar-brand' ) ) );
}, PHP_INT_MAX );
