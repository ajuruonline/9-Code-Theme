<?php
/** Open Scholar Workspace — site-level, presentation-only dashboard/menu controls. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ncu_osch_workspace_preferences() {
    $saved = get_option( 'ncu_osch_workspace_prefs', array() );
    return is_array( $saved ) ? $saved : array();
}
/** Dashboard settings submits through admin-post.php, where admin_menu never runs.
 * Cache only the server-generated, capability-filtered catalogue during the form view;
 * never use client-supplied slugs as a source of authority.
 */
function ncu_osch_workspace_catalog_for_save() {
    $current = isset( $GLOBALS['ncu_osch_workspace_catalog'] ) ? $GLOBALS['ncu_osch_workspace_catalog'] : array();
    if ( is_array( $current ) && $current ) { return $current; }
    $cached = get_transient( 'ncu_osch_workspace_catalog_' . get_current_user_id() );
    return is_array( $cached ) ? $cached : array();
}
function ncu_osch_workspace_preference( $slug ) {
    $all = ncu_osch_workspace_preferences();
    $row = isset( $all['apps'][ $slug ] ) ? $all['apps'][ $slug ] : array();
    return is_array( $row ) ? $row : array();
}
function ncu_osch_workspace_required_menu( $slug ) {
    // Do not allow institutions to accidentally hide their main navigation or this editor.
    return in_array( $slug, array( 'index.php', 'nine-code-ultra' ), true );
}
function ncu_osch_workspace_icons() {
    return array('auto','dashboard','library','book','journal','calendar','graduation','users','shield','database','layers','eye','search','video','image','newspaper','file','messages','puzzle','chart','clipboard','palette','settings','wrench','wallet','globe','cloud','folder','app');
}
function ncu_osch_workspace_form_id( $slug ) {
    return 'id_' . substr( hash( 'sha256', (string) $slug ), 0, 24 );
}
function ncu_osch_workspace_sanitize_submitted( $input, $catalog ) {
    $clean = array();
    $input = is_array( $input ) ? $input : array();
    foreach ( (array) $catalog as $slug => $app ) {
        // Never trust posted slugs/URLs or assign capabilities from client values.
        $id = ncu_osch_workspace_form_id( $slug );
        $row = isset( $input[ $id ] ) && is_array( $input[ $id ] ) ? $input[ $id ] : array();
        $required = ncu_osch_workspace_required_menu( $slug );
        $icon = isset( $row['icon'] ) && is_scalar( $row['icon'] ) ? (string) $row['icon'] : '';
        $clean_icon = in_array( $icon, ncu_osch_workspace_icons(), true ) && 'auto' !== $icon ? $icon : '';
        $children = array();
        foreach ( isset( $app['children'] ) ? (array) $app['children'] : array() as $child ) {
            if ( empty( $child['slug'] ) ) { continue; }
            $subslug = (string) $child['slug'];
            $subid = ncu_osch_workspace_form_id( $subslug );
            $sub = isset( $row['submenu'][ $subid ] ) && is_array( $row['submenu'][ $subid ] ) ? $row['submenu'][ $subid ] : array();
            $keep = in_array( $subslug, array( 'open-scholar-workspace', 'nine-code-ultra' ), true );
            $children[ $subslug ] = array(
                'visible' => $keep || ! empty( $sub['visible'] ) ? 1 : 0,
                'title' => substr( sanitize_text_field( isset( $sub['title'] ) && is_scalar( $sub['title'] ) ? $sub['title'] : '' ), 0, 80 ),
            );
        }
        $clean[ $slug ] = array(
            'dashboard' => $required || ! empty( $row['dashboard'] ) ? 1 : 0,
            'menu' => $required || ! empty( $row['menu'] ) ? 1 : 0,
            'title' => substr( sanitize_text_field( isset( $row['title'] ) && is_scalar( $row['title'] ) ? $row['title'] : '' ), 0, 80 ),
            'description' => substr( sanitize_text_field( isset( $row['description'] ) && is_scalar( $row['description'] ) ? $row['description'] : '' ), 0, 240 ),
            'icon' => $clean_icon,
            'order' => max( 0, min( 999, (int) ( isset( $row['order'] ) && is_scalar( $row['order'] ) ? $row['order'] : 0 ) ) ),
            'submenu' => $children,
        );
    }
    return $clean;
}
function ncu_osch_workspace_apply_preferences( &$apps ) {
    foreach ( $apps as $slug => &$app ) {
        $pref = ncu_osch_workspace_preference( $slug );
        if ( ! empty( $pref['title'] ) ) { $app['label'] = $pref['title']; }
        if ( ! empty( $pref['description'] ) ) { $app['description'] = $pref['description']; }
        if ( ! empty( $pref['icon'] ) && in_array( $pref['icon'], ncu_osch_workspace_icons(), true ) ) {
            $app['icon_key'] = $pref['icon'];
            $app['image'] = ''; // Explicit icon choice takes precedence over installed logo.
        }
        $app['workspace_order'] = isset( $pref['order'] ) ? absint( $pref['order'] ) : 500;
        if ( ! empty( $app['children'] ) && ! empty( $pref['submenu'] ) ) {
            foreach ( $app['children'] as $i => &$child ) {
                $row = isset( $pref['submenu'][ $child['slug'] ] ) ? $pref['submenu'][ $child['slug'] ] : array();
                if ( ! empty( $row['title'] ) ) { $child['label'] = $row['title']; }
                if ( isset( $row['visible'] ) && ! $row['visible'] ) { unset( $app['children'][$i] ); }
            }
            unset( $child );
            $app['children'] = array_values( $app['children'] );
        }
        if ( ! ncu_osch_workspace_required_menu( $slug ) && isset( $pref['dashboard'] ) && ! $pref['dashboard'] ) {
            unset( $apps[$slug] );
        }
    }
    unset( $app );
    uasort( $apps, static function( $a, $b ) {
        return ( $a['workspace_order'] <=> $b['workspace_order'] );
    } );
}
function ncu_osch_workspace_menu_configuration( $catalog ) {
    $result = array();
    foreach ( (array) $catalog as $slug => $app ) {
        $pref = ncu_osch_workspace_preference( $slug );
        $submenus = array();
        foreach ( isset( $app['children'] ) ? (array) $app['children'] : array() as $child ) {
            $subslug = isset( $child['slug'] ) ? $child['slug'] : '';
            $subpref = isset( $pref['submenu'][$subslug] ) ? $pref['submenu'][$subslug] : array();
            $submenus[] = array(
                'url' => $child['url'],
                'visible' => in_array( $subslug, array('open-scholar-workspace','nine-code-ultra'), true ) || ! isset( $subpref['visible'] ) || (bool) $subpref['visible'],
                'title' => isset( $subpref['title'] ) ? $subpref['title'] : '',
            );
        }
        $result[] = array(
            'url' => $app['url'],
            'menu' => ncu_osch_workspace_required_menu( $slug ) || ! isset( $pref['menu'] ) || (bool) $pref['menu'],
            'order' => isset( $pref['order'] ) ? (int) $pref['order'] : 500,
            'title' => isset( $pref['title'] ) ? $pref['title'] : '',
            'submenus' => $submenus,
        );
    }
    return $result;
}

add_action( 'admin_menu', 'ncu_osch_workspace_settings_menu', 25 );
function ncu_osch_workspace_settings_menu() {
    add_submenu_page( 'nine-code-ultra', 'Dashboard & Menus', 'Dashboard & Menus', 'manage_options', 'open-scholar-workspace', 'ncu_osch_workspace_settings_page' );
}
add_action( 'admin_post_ncu_osch_workspace_save', 'ncu_osch_workspace_save' );
function ncu_osch_workspace_save() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'ncu_osch_workspace_save' );
    if ( ! empty( $_POST['reset'] ) ) {
        delete_option( 'ncu_osch_workspace_prefs' );
    } else {
        // Read real WordPress menu registry, never accept unregistered app IDs.
        $catalog = ncu_osch_workspace_catalog_for_save();
        if ( ! $catalog ) { wp_die( 'Application registry unavailable. No settings were changed.', '', array( 'response' => 503 ) ); }
        $posted = isset( $_POST['apps'] ) ? wp_unslash( $_POST['apps'] ) : array();
        $clean = ncu_osch_workspace_sanitize_submitted( $posted, $catalog );
        update_option( 'ncu_osch_workspace_prefs', array( 'apps' => $clean ), false );
    }
    wp_safe_redirect( add_query_arg( 'workspace_saved', 1, admin_url( 'admin.php?page=open-scholar-workspace' ) ) );
    exit;
}
function ncu_osch_workspace_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $catalog = isset( $GLOBALS['ncu_osch_workspace_catalog'] ) ? $GLOBALS['ncu_osch_workspace_catalog'] : array();
    if ( $catalog ) { set_transient( 'ncu_osch_workspace_catalog_' . get_current_user_id(), $catalog, 30 * MINUTE_IN_SECONDS ); }
    ?>
    <div class="wrap osch-workspace-settings">
      <h1>Dashboard &amp; Menus</h1>
      <p>Configure which applications appear on this university's Dashboard and Open Scholar menu. Rename cards, descriptions and submenu labels. These controls do not change user permissions or uninstall apps.</p>
      <?php if ( isset( $_GET['workspace_saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Workspace settings saved.</p></div><?php endif; ?>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ncu_osch_workspace_save">
        <?php wp_nonce_field( 'ncu_osch_workspace_save' ); ?>
        <div class="osch-settings-toolbar"><strong><?php echo esc_html( count( $catalog ) ); ?> registered applications</strong><button type="submit" class="button button-primary">Save dashboard &amp; menus</button><a class="button" href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>">View Dashboard</a></div>
        <div class="osch-settings-rows">
        <?php foreach ( $catalog as $slug => $app ) :
            $pref = ncu_osch_workspace_preference( $slug ); $required = ncu_osch_workspace_required_menu( $slug );
            $base = 'apps[' . ncu_osch_workspace_form_id( $slug ) . ']';
            $show_dash = $required || ! isset($pref['dashboard']) || (bool)$pref['dashboard'];
            $show_menu = $required || ! isset($pref['menu']) || (bool)$pref['menu'];
        ?>
        <details class="osch-settings-app">
          <summary><span class="dashicons <?php echo esc_attr( isset($app['icon']) ? $app['icon'] : 'dashicons-admin-plugins' ); ?>" aria-hidden="true"></span><strong><?php echo esc_html( $app['label'] ); ?></strong><small><?php echo esc_html( $slug ); ?></small><span class="osch-settings-expand">Configure</span></summary>
          <div class="osch-settings-fields">
            <div class="osch-settings-display"><label><input type="checkbox" name="<?php echo esc_attr($base); ?>[dashboard]" value="1" <?php checked($show_dash); ?> <?php disabled($required); ?>> Dashboard card</label><label><input type="checkbox" name="<?php echo esc_attr($base); ?>[menu]" value="1" <?php checked($show_menu); ?> <?php disabled($required); ?>> Side menu</label><?php if ($required): ?><span class="description">Always available</span><input type="hidden" name="<?php echo esc_attr($base); ?>[dashboard]" value="1"><input type="hidden" name="<?php echo esc_attr($base); ?>[menu]" value="1"><?php endif; ?></div>
            <label>Display title <input type="text" name="<?php echo esc_attr($base); ?>[title]" value="<?php echo esc_attr(isset($pref['title']) ? $pref['title'] : ''); ?>" maxlength="80" placeholder="<?php echo esc_attr($app['label']); ?>"></label>
            <label>Description <input type="text" name="<?php echo esc_attr($base); ?>[description]" value="<?php echo esc_attr(isset($pref['description']) ? $pref['description'] : ''); ?>" maxlength="240" placeholder="<?php echo esc_attr($app['description']); ?>"></label>
            <div class="osch-settings-display"><label>Icon <select name="<?php echo esc_attr($base); ?>[icon]"><option value="">Automatic</option><?php foreach(ncu_osch_workspace_icons() as $icon): if('auto'===$icon) continue; ?><option value="<?php echo esc_attr($icon); ?>" <?php selected(isset($pref['icon'])?$pref['icon']:'',$icon); ?>><?php echo esc_html(ucwords(str_replace('-',' ',$icon))); ?></option><?php endforeach; ?></select></label><label>Dashboard position <input type="number" min="0" max="999" name="<?php echo esc_attr($base); ?>[order]" value="<?php echo esc_attr(isset($pref['order'])?$pref['order']:500); ?>" style="width:78px"></label></div>
            <?php if (!empty($app['children'])): ?><details class="osch-settings-children"><summary>Submenus (<?php echo esc_html(count($app['children'])); ?>)</summary>
              <?php foreach($app['children'] as $sub): $ss=$sub['slug']; $sp=isset($pref['submenu'][$ss])?$pref['submenu'][$ss]:array(); $must=in_array($ss,array('open-scholar-workspace','nine-code-ultra'),true); ?>
              <div class="osch-settings-subrow"><label><input type="checkbox" name="<?php echo esc_attr($base); ?>[submenu][<?php echo esc_attr(ncu_osch_workspace_form_id($ss)); ?>][visible]" value="1" <?php checked($must||!isset($sp['visible'])||(bool)$sp['visible']); ?> <?php disabled($must); ?>> Show</label><label><span class="screen-reader-text">Rename <?php echo esc_html($sub['label']); ?></span><input type="text" maxlength="80" placeholder="<?php echo esc_attr($sub['label']); ?>" value="<?php echo esc_attr(isset($sp['title'])?$sp['title']:''); ?>" name="<?php echo esc_attr($base); ?>[submenu][<?php echo esc_attr(ncu_osch_workspace_form_id($ss)); ?>][title]"></label></div>
              <?php endforeach; ?></details><?php endif; ?>
          </div>
        </details>
        <?php endforeach; ?>
        </div>
        <div class="osch-settings-toolbar"><button type="submit" class="button button-primary">Save dashboard &amp; menus</button><button type="submit" name="reset" value="1" class="button" onclick="return confirm('Restore automatic display, names and sorting?');">Restore defaults</button></div>
      </form>
    </div>
    <?php
}
