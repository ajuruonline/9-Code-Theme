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
/** Roles that may see an app card ('apps') or quick action ('qa'). No entry = unchanged default. */
function ncu_visibility_roles( $scope, $key ) {
    $map = get_option( 'ncu_role_visibility', array() );
    return isset( $map[ $scope ][ $key ] ) && is_array( $map[ $scope ][ $key ] ) ? $map[ $scope ][ $key ] : null;
}

function ncu_visibility_allows( $scope, $key ) {
    $roles = ncu_visibility_roles( $scope, $key );
    if ( null === $roles || empty( $roles ) ) { return true; }
    $user = wp_get_current_user();
    if ( ! $user->exists() || in_array( 'administrator', (array) $user->roles, true ) ) { return true; }
    return (bool) array_intersect( (array) $user->roles, $roles );
}

/** Pinned posts for the dashboard, in saved order. Only posts the current user can edit are shown. */
function ncu_osch_workspace_pinned_posts() {
    $ids = get_option( 'ncu_osch_pinned_posts', array() );
    $out = array();
    foreach ( (array) $ids as $id ) {
        $post = get_post( (int) $id );
        if ( $post && current_user_can( 'edit_post', $post->ID ) ) { $out[] = $post; }
    }
    return $out;
}

function ncu_osch_workspace_save() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Insufficient permissions.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'ncu_osch_workspace_save' );
    if ( ! empty( $_POST['reset'] ) ) {
        delete_option( 'ncu_osch_workspace_prefs' );
        delete_option( 'ncu_osch_pinned_posts' );
        delete_option( 'ncu_role_visibility' );
    } else {
        // Read real WordPress menu registry, never accept unregistered app IDs.
        $catalog = ncu_osch_workspace_catalog_for_save();
        if ( ! $catalog ) { wp_die( 'Application registry unavailable. No settings were changed.', '', array( 'response' => 503 ) ); }
        $posted = isset( $_POST['apps'] ) ? wp_unslash( $_POST['apps'] ) : array();
        $clean = ncu_osch_workspace_sanitize_submitted( $posted, $catalog );
        update_option( 'ncu_osch_workspace_prefs', array( 'apps' => $clean ), false );
        $pinned = array();
        foreach ( isset( $_POST['pinned'] ) ? (array) wp_unslash( $_POST['pinned'] ) : array() as $pid ) {
            $pid = absint( $pid );
            if ( $pid && ! in_array( $pid, $pinned, true ) && get_post( $pid ) && current_user_can( 'edit_post', $pid ) ) { $pinned[] = $pid; }
        }
        update_option( 'ncu_osch_pinned_posts', array_slice( $pinned, 0, 20 ), false );
        $vis = get_option( 'ncu_role_visibility', array() );
        $roles_all = array_keys( wp_roles()->get_names() );
        $posted_vis = isset( $_POST['visibility']['apps'] ) ? (array) wp_unslash( $_POST['visibility']['apps'] ) : array();
        $vis['apps'] = array();
        foreach ( $catalog as $slug => $app ) {
            $picked = isset( $posted_vis[ $slug ] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) $posted_vis[ $slug ] ), $roles_all ) ) : array();
            if ( $picked && count( $picked ) < count( $roles_all ) ) { $vis['apps'][ $slug ] = $picked; }
        }
        update_option( 'ncu_role_visibility', $vis, false );
    }
    wp_safe_redirect( add_query_arg( 'workspace_saved', 1, admin_url( 'admin.php?page=open-scholar-workspace' ) ) );
    exit;
}
function ncu_osch_workspace_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $catalog = isset( $GLOBALS['ncu_osch_workspace_catalog'] ) ? $GLOBALS['ncu_osch_workspace_catalog'] : array();
    if ( $catalog ) { set_transient( 'ncu_osch_workspace_catalog_' . get_current_user_id(), $catalog, 30 * MINUTE_IN_SECONDS ); }
    $catalog = (array) $catalog;
    uasort( $catalog, function ( $a, $b ) use ( $catalog ) {
        $oa = ncu_osch_workspace_preference( array_search( $a, $catalog, true ) );
        $ob = ncu_osch_workspace_preference( array_search( $b, $catalog, true ) );
        return ( isset( $oa['order'] ) ? (int) $oa['order'] : 500 ) <=> ( isset( $ob['order'] ) ? (int) $ob['order'] : 500 );
    } );
    $pinned_posts = ncu_osch_workspace_pinned_posts();
    $pinned_ids = wp_list_pluck( $pinned_posts, 'ID' );
    $pin_choices = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 100, 'post__not_in' => $pinned_ids ?: array( 0 ), 'orderby' => 'date', 'order' => 'DESC' ) );
    ?>
    <div class="wrap osch-workspace-settings">
      <h1>Dashboard &amp; Menus</h1>
      <p>Configure which applications appear on this university's Dashboard and Open Scholar menu. Rename cards, descriptions and submenu labels. These controls do not change user permissions or uninstall apps.</p>
      <?php if ( isset( $_GET['workspace_saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Workspace settings saved.</p></div><?php endif; ?>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ncu_osch_workspace_save">
        <?php wp_nonce_field( 'ncu_osch_workspace_save' ); ?>
        <div class="osch-settings-toolbar"><strong><?php echo esc_html( count( $catalog ) ); ?> registered applications</strong><button type="submit" class="button button-primary">Save dashboard &amp; menus</button><a class="button" href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>">View Dashboard</a></div>
        <section class="osch-settings-panel" aria-labelledby="osch-visibility-title">
          <h2 id="osch-visibility-title">Who sees what</h2>
          <p class="osch-settings-hint">Untick a role to hide a card from it. Administrators always see every card.</p>
          <div class="osch-vis-scroll"><table class="osch-vis-table"><thead><tr><th scope="col">Card</th><?php foreach ( wp_roles()->get_names() as $rk => $rl ) : ?><th scope="col"><?php echo esc_html( translate_user_role( $rl ) ); ?></th><?php endforeach; ?></tr></thead><tbody>
          <?php foreach ( $catalog as $slug => $app ) : $allowed = ncu_visibility_roles( 'apps', $slug ); ?>
            <tr><th scope="row"><?php echo esc_html( $app['label'] ); ?></th><?php foreach ( wp_roles()->get_names() as $rk => $rl ) : ?><td><label class="osch-vis-cell"><span class="screen-reader-text"><?php echo esc_html( $app['label'] . ' — ' . translate_user_role( $rl ) ); ?></span><input type="checkbox" name="visibility[apps][<?php echo esc_attr( $slug ); ?>][]" value="<?php echo esc_attr( $rk ); ?>" <?php checked( null === $allowed || in_array( $rk, $allowed, true ) ); ?>></label></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        </section>
        <section class="osch-settings-panel" aria-labelledby="osch-reorder-title">
          <h2 id="osch-reorder-title">Reorder dashboard</h2>
          <p class="osch-settings-hint">Use the arrows to move a card up or down, then save.</p>
          <ol class="osch-reorder-list" data-osch-reorder>
          <?php foreach ( $catalog as $slug => $app ) : $fid = ncu_osch_workspace_form_id( $slug ); ?>
            <li data-osch-reorder-item="<?php echo esc_attr( $fid ); ?>"><span class="dashicons <?php echo esc_attr( isset( $app['icon'] ) ? $app['icon'] : 'dashicons-admin-plugins' ); ?>" aria-hidden="true"></span><span class="osch-reorder-name"><?php echo esc_html( $app['label'] ); ?></span><button type="button" data-osch-move="-1" aria-label="Move up"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button><button type="button" data-osch-move="1" aria-label="Move down"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button></li>
          <?php endforeach; ?>
          </ol>
        </section>
        <section class="osch-settings-panel" aria-labelledby="osch-pinned-title">
          <h2 id="osch-pinned-title">Pinned posts</h2>
          <p class="osch-settings-hint">Pinned posts appear at the top of the dashboard, in this order.</p>
          <ol class="osch-pinned-list" data-osch-pinned>
          <?php foreach ( $pinned_posts as $pp ) : ?>
            <li data-osch-pin="<?php echo esc_attr( (string) $pp->ID ); ?>"><input type="hidden" name="pinned[]" value="<?php echo esc_attr( (string) $pp->ID ); ?>"><span class="osch-pin-name"><?php echo esc_html( get_the_title( $pp ) ); ?></span><button type="button" data-osch-pin-move="-1" aria-label="Move up"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button><button type="button" data-osch-pin-move="1" aria-label="Move down"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button><button type="button" data-osch-pin-remove aria-label="Unpin"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></li>
          <?php endforeach; ?>
          </ol>
          <div class="osch-pin-add"><select data-osch-pin-select aria-label="Choose a post to pin"><option value="">Choose a post…</option><?php foreach ( $pin_choices as $pc ) : ?><option value="<?php echo esc_attr( (string) $pc->ID ); ?>"><?php echo esc_html( get_the_title( $pc ) . ' · ' . get_post_type_object( $pc->post_type )->labels->singular_name ); ?></option><?php endforeach; ?></select><button type="button" class="button" data-osch-pin-add>Pin post</button></div>
        </section>
        <div class="osch-settings-rows">
        <?php foreach ( $catalog as $slug => $app ) :
            $pref = ncu_osch_workspace_preference( $slug ); $required = ncu_osch_workspace_required_menu( $slug );
            $base = 'apps[' . ncu_osch_workspace_form_id( $slug ) . ']';
            $show_dash = $required || ! isset($pref['dashboard']) || (bool)$pref['dashboard'];
            $show_menu = $required || ! isset($pref['menu']) || (bool)$pref['menu'];
        ?>
        <details class="osch-settings-app" data-osch-app="<?php echo esc_attr( ncu_osch_workspace_form_id( $slug ) ); ?>">
          <input type="hidden" name="<?php echo esc_attr( $base ); ?>[order]" value="<?php echo esc_attr( (string) ( ( array_search( $slug, array_keys( $catalog ), true ) + 1 ) * 10 ) ); ?>" class="osch-order-input">
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
    <script>
    (function(){
      var root=document.querySelector('.osch-workspace-settings'); if(!root){return;}
      function renumber(){
        var rows=root.querySelector('.osch-settings-rows'); var list=root.querySelector('[data-osch-reorder]');
        if(!rows||!list){return;}
        Array.prototype.forEach.call(list.querySelectorAll('[data-osch-reorder-item]'),function(li,i){
          var id=li.getAttribute('data-osch-reorder-item');
          var app=rows.querySelector('[data-osch-app="'+id+'"]');
          if(app){var inp=app.querySelector('.osch-order-input'); if(inp){inp.value=String((i+1)*10);} rows.appendChild(app);}
        });
      }
      root.addEventListener('click',function(e){
        var mv=e.target.closest('[data-osch-move]');
        if(mv){var li=mv.closest('li'); var dir=parseInt(mv.getAttribute('data-osch-move'),10); var sib=dir<0?li.previousElementSibling:li.nextElementSibling; if(sib){dir<0?li.parentNode.insertBefore(li,sib):li.parentNode.insertBefore(sib,li); renumber();} return;}
        var pm=e.target.closest('[data-osch-pin-move]');
        if(pm){var pl=pm.closest('li'); var pd=parseInt(pm.getAttribute('data-osch-pin-move'),10); var ps=pd<0?pl.previousElementSibling:pl.nextElementSibling; if(ps){pd<0?pl.parentNode.insertBefore(pl,ps):pl.parentNode.insertBefore(ps,pl);} return;}
        var pr=e.target.closest('[data-osch-pin-remove]');
        if(pr){pr.closest('li').remove(); return;}
        var pa=e.target.closest('[data-osch-pin-add]');
        if(pa){var sel=root.querySelector('[data-osch-pin-select]'); if(!sel||!sel.value){return;} var opt=sel.options[sel.selectedIndex]; var li=document.createElement('li'); li.setAttribute('data-osch-pin',sel.value); var hid=document.createElement('input'); hid.type='hidden'; hid.name='pinned[]'; hid.value=sel.value; var nm=document.createElement('span'); nm.className='osch-pin-name'; nm.textContent=opt.textContent; li.appendChild(hid); li.appendChild(nm); var mk=function(t,label,icon){var b=document.createElement('button'); b.type='button'; b.setAttribute('aria-label',label); b.innerHTML=icon; return b;}; var up=mk('u','Move up','<i class="fa-solid fa-chevron-up" aria-hidden="true"></i>'); up.setAttribute('data-osch-pin-move','-1'); var dn=mk('d','Move down','<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>'); dn.setAttribute('data-osch-pin-move','1'); var rm=mk('x','Unpin','<i class="fa-solid fa-xmark" aria-hidden="true"></i>'); rm.setAttribute('data-osch-pin-remove',''); li.appendChild(up); li.appendChild(dn); li.appendChild(rm); root.querySelector('[data-osch-pinned]').appendChild(li); opt.remove(); sel.value=''; }
      });
      renumber();
    }());
    </script>
    <?php
}
