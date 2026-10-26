<?php
/** Open Scholar | full, capability-filtered application dashboard. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Legacy integration contract: plugins may mark themselves first-party. */
function ncu_osch_workspace_is_app( $slug, $label ) {
    $text = strtolower( wp_strip_all_tags( $label . ' ' . $slug ) );
    $match = (bool) preg_match( '/open[ _-]?scholar|open[ _-]?index|9code|nine[ _-]?(?:code|user|data|backup|security)|^9|e[-_ ]?(library|module|book|pdf|lecture|step|class|viewer)|journal|publication|conference|student|academy|vault|hyperlesson|power[ _-]?lecture/', $text );
    return (bool) apply_filters( 'open_scholar_workspace_is_app', $match, $slug, $label );
}

function ncu_osch_workspace_apps() {
    return isset( $GLOBALS['ncu_osch_workspace_apps'] ) ? $GLOBALS['ncu_osch_workspace_apps'] : array();
}

/** Use semantic icons for plugins that supply no recognizable Dashicon. */
function ncu_osch_workspace_menu_icon( $label, $slug ) {
    $text = strtolower( $label . ' ' . $slug );
    $map = array(
        '/backup|archive|restore|export/' => 'dashicons-database-export',
        '/vault|security|secure/' => 'dashicons-shield',
        '/library|ebook|e-book|textbook|books/' => 'dashicons-book-alt',
        '/publication|journal|article|proceeding/' => 'dashicons-media-document',
        '/video|lecture|e[- ]?class/' => 'dashicons-video-alt3',
        '/e[- ]?module|lesson|course|academy|learning/' => 'dashicons-welcome-learn-more',
        '/conference|event/' => 'dashicons-calendar-alt',
        '/student|user|people|profile|author/' => 'dashicons-groups',
        '/presentation|slides/' => 'dashicons-slides',
        '/index|search/' => 'dashicons-search',
        '/analytics|stats|report/' => 'dashicons-chart-bar',
        '/forms|survey/' => 'dashicons-feedback',
        '/media|image/' => 'dashicons-format-gallery',
        '/pages/' => 'dashicons-admin-page',
        '/posts|blog|news/' => 'dashicons-edit',
        '/plugins|apps/' => 'dashicons-admin-plugins',
        '/settings|options/' => 'dashicons-admin-settings',
        '/appearance|theme/' => 'dashicons-art',
        '/dashboard/' => 'dashicons-dashboard',
        '/open[ _-]?scholar|nine|9code/' => 'dashicons-welcome-learn-more',
    );
    foreach ( $map as $pattern => $icon ) { if ( preg_match( $pattern, $text ) ) { return $icon; } }
    return 'dashicons-admin-generic';
}

/**
 * Visible identity is functional: icons describe the application, colour separates
 * neighbouring apps, and initials remain a third cue for fast visual scanning.
 * Inline SVGs do not depend on the WordPress Dashicons font being loaded.
 * Only literal paths from this server-owned dictionary are ever output.
 */
function ncu_osch_workspace_icon_key( $label, $slug ) {
    $name = strtolower( wp_strip_all_tags( $label . ' ' . $slug ) );
    $rules = array(
        '/^dashboard\b|index\.php/' => 'dashboard',
        '/e[-_ ]?library|shelf|library/' => 'library',
        '/e[-_ ]?book|textbook|books?\b/' => 'book',
        '/journal|publication|article|proceedings/' => 'journal',
        '/conference|event|schedule/' => 'calendar',
        '/student|academy|graduat/' => 'graduation',
        '/user|people|member|profile|author|staff/' => 'users',
        '/vault|secure|security|permission/' => 'shield',
        '/backup|restore|archive|export|import/' => 'database',
        '/module|lesson|course|learn|lecture|class/' => 'layers',
        '/pdf|reader|viewer|preview/' => 'eye',
        '/index|search|discover/' => 'search',
        '/video|stream|tube/' => 'video',
        '/upload\.php|media|image|gallery|photo/' => 'image',
        '/edit\.php|posts?|news|blog/' => 'newspaper',
        '/pages|page\.php/' => 'file',
        '/comments|messages|chat/' => 'messages',
        '/plugin|app store|extension/' => 'puzzle',
        '/analytics|stats|report|insight/' => 'chart',
        '/form|survey|quiz/' => 'clipboard',
        '/theme|appearance|design|branding/' => 'palette',
        '/settings|options|configuration/' => 'settings',
        '/tools|utilities|maintenance/' => 'wrench',
        '/finance|payment|fees|billing/' => 'wallet',
        '/website|domain|site|portal/' => 'globe',
        '/drive|cloud/' => 'cloud',
        '/download|file manager|document/' => 'folder',
        '/nine.?code|9code|open.?scholar/' => 'graduation',
    );
    foreach ( $rules as $expression => $icon ) {
        if ( preg_match( $expression, $name ) ) { return $icon; }
    }
    return 'app';
}

function ncu_osch_workspace_svg_icon( $key ) {
    $paths = array(
        'dashboard' => '<rect x="3" y="3" width="7" height="8"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="15" width="7" height="6"/>',
        'library' => '<path d="M4 4h6a3 3 0 0 1 3 3v14H7a3 3 0 0 0-3-3z"/><path d="M20 4h-4a3 3 0 0 0-3 3v14h4a3 3 0 0 1 3-3z"/><path d="M7 8h3M16 8h2M7 11h3M16 11h2"/>',
        'book' => '<rect x="5" y="3" width="14" height="18"/><path d="M8 3v18M11 9h5M11 13h5"/>',
        'journal' => '<path d="M7 3h9l4 4v14H7z"/><path d="M16 3v5h4M10 12h7M10 16h7M10 8h2"/><path d="M3 7v14"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16"/><path d="M7 3v4M17 3v4M3 10h18"/><path d="M8 14h3v3H8zM15 14h2"/>',
        'graduation' => '<path d="M2 9 12 4l10 5-10 5zM6 11v5c2 4 10 4 12 0v-5M22 9v7"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2z"/><path d="M17 6a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5"/>',
        'shield' => '<path d="m12 2 8 4v5c0 6-4 10-8 11C8 21 4 17 4 11V6z"/><path d="m9 12 2 2 4-4"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 4 16 4 16 0V5M4 12c0 4 16 4 16 0"/><path d="M10 18h4"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5zM3 12l9 5 9-5M3 16l9 5 9-5"/>',
        'eye' => '<path d="M2 12c2.5-4 5.8-6 10-6s7.5 2 10 6c-2.5 4-5.8 6-10 6s-7.5-2-10-6z"/><circle cx="12" cy="12" r="3"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/><path d="M8 10.5h5"/>',
        'video' => '<rect x="3" y="5" width="14" height="14"/><path d="m17 10 4-3v10l-4-3"/><path d="m9 9 4 3-4 3z"/>',
        'image' => '<rect x="3" y="3" width="18" height="18"/><circle cx="8" cy="8" r="2"/><path d="m3 18 6-6 4 4 3-3 5 5"/>',
        'newspaper' => '<path d="M5 3h14v17H5a3 3 0 0 1-3-3V7h3"/><path d="M9 7h6v4H9zM9 15h7M9 18h7"/>',
        'file' => '<path d="M6 2h8l5 5v15H6zM14 2v5h5M9 12h7M9 16h7"/>',
        'messages' => '<path d="M4 4h16v12H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
        'puzzle' => '<path d="M8 3h4v3a2 2 0 1 0 4 0V3h5v5h-3a2 2 0 1 0 0 4h3v9h-8v-3a2 2 0 1 0-4 0v3H3v-8h3a2 2 0 1 0 0-4H3V3h5z"/>',
        'chart' => '<path d="M3 3v18h18M7 17v-5M12 17V8M17 17V5"/>',
        'clipboard' => '<rect x="5" y="5" width="14" height="17"/><rect x="9" y="2" width="6" height="5"/><path d="m9 13 2 2 4-4M9 19h6"/>',
        'palette' => '<path d="M12 2a10 10 0 0 0 0 20h2a2 2 0 0 0 2-2c0-2 1-3 3-3a3 3 0 0 0 3-3c0-7-4-12-10-12z"/><circle cx="7" cy="11" r="1"/><circle cx="10" cy="6" r="1"/><circle cx="16" cy="8" r="1"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M10 2h4l1 3 3 1 3-1 2 4-2 2v3l2 2-2 4-3-1-3 1-1 3h-4l-1-3-3-1-3 1-2-4 2-2v-3L1 9l2-4 3 1 3-1z"/>',
        'wrench' => '<path d="M20 7a5 5 0 0 1-7 5L6 19a3 3 0 0 1-4-4l7-7a5 5 0 0 1 7-6l-3 3 4 4z"/>',
        'wallet' => '<rect x="3" y="5" width="18" height="16"/><path d="M3 9h18M15 14h6v4h-6a2 2 0 0 1 0-4z"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c-6 6-6 12 0 18M12 3c6 6 6 12 0 18"/>',
        'cloud' => '<path d="M6 19a5 5 0 0 1-1-10 7 7 0 0 1 13-1 5 5 0 0 1 0 11z"/><path d="M12 11v8m-3-3 3 3 3-3"/>',
        'folder' => '<path d="M3 6h7l2 3h9v11H3z"/><path d="M3 9V4h7l2 2"/>',
        'app' => '<rect x="3" y="3" width="8" height="8"/><rect x="13" y="3" width="8" height="8"/><rect x="3" y="13" width="8" height="8"/><path d="M17 13v8M13 17h8"/>',
    );
    $literal = isset( $paths[ $key ] ) ? $paths[ $key ] : $paths['app'];
    return '<svg class="osch-app-svg" data-osch-icon="' . esc_attr( $key ) . '" viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">' . $literal . '</svg>';
}

function ncu_osch_workspace_assign_visuals( &$apps ) {
    $palettes = array(
        array( '#14532d', '#e6f4e9', '#b8dfc3' ), // canonical Open Scholar green.
        array( '#0f766e', '#dcf5ef', '#a0ded1' ),
        array( '#1d4ed8', '#e7efff', '#b7cafa' ),
        array( '#7e22ce', '#f4e8fd', '#dfc0f3' ),
        array( '#92400e', '#fff3dd', '#edd19d' ),
        array( '#9d174d', '#fdeaf1', '#efb9d0' ),
        array( '#155e75', '#e4f4fb', '#b6dce8' ),
        array( '#3730a3', '#eeedff', '#c9c5fd' ),
        array( '#9a3412', '#fff0e9', '#f5c5b0' ),
        array( '#166534', '#e6f7e8', '#b6e4c3' ),
        array( '#6b21a8', '#f5edfc', '#dec8f3' ),
        array( '#854d0e', '#fef8e0', '#e9db9c' ),
        array( '#334155', '#edf2f8', '#cbd5e1' ),
        array( '#be123c', '#fff1f3', '#f4bdc9' ),
        array( '#0e7490', '#e6faff', '#b1e6f3' ),
        array( '#4338ca', '#eae9ff', '#c3c0fc' ),
    );
    $families = array(
        'library' => array('library','book','journal','folder'),
        'book' => array('book','library','file','journal'),
        'journal' => array('journal','newspaper','file','book'),
        'graduation' => array('graduation','layers','book','users'),
        'users' => array('users','graduation','messages','shield'),
        'calendar' => array('calendar','clipboard','messages'),
        'layers' => array('layers','graduation','book','video'),
        'file' => array('file','newspaper','folder','journal'),
        'image' => array('image','video','palette','file'),
        'app' => array('app','puzzle','folder','globe','settings','chart','file','clipboard'),
    );
    $used_icons = array(); $used_colours = array();
    foreach ( $apps as $slug => &$app ) {
        $base = ncu_osch_workspace_icon_key( $app['label'], $slug );
        $candidates = isset( $families[$base] ) ? $families[$base] : array( $base );
        $key = $base;
        foreach ( $candidates as $candidate ) {
            if ( empty( $used_icons[ $candidate ] ) ) { $key = $candidate; break; }
        }
        // Unrelated applications sharing the same generic menu icon still get
        // different pictograms wherever the available symbol catalogue permits.
        if ( ! empty( $used_icons[ $key ] ) ) {
            foreach ( array('app','folder','globe','chart','puzzle','clipboard','file','palette','cloud','wrench','shield','messages','video','wallet','settings','eye') as $candidate ) {
                if ( empty( $used_icons[ $candidate ] ) ) { $key = $candidate; break; }
            }
        }
        $used_icons[$key] = true;
        $tone = (int) ( sprintf( '%u', crc32( (string) $slug ) ) % count( $palettes ) );
        // Prevent identical colour on adjacent/early cards, not just a random hash.
        $attempts = 0;
        while ( isset( $used_colours[$tone] ) && $attempts++ < count( $palettes ) ) { $tone = ( $tone + 1 ) % count( $palettes ); }
        $used_colours[$tone] = true;
        $app['icon_key'] = $key;
        $app['tone'] = $palettes[$tone];
    }
    unset( $app );
}

function ncu_osch_workspace_initials( $label ) {
    $letters = preg_replace( '/[^a-zA-Z0-9]/', '', wp_strip_all_tags( $label ) );
    return strtoupper( substr( $letters, 0, 2 ) );
}

/** WordPress supplies routes and capabilities: do not maintain a stale static app list. */
function ncu_osch_workspace_menu_url( $slug ) {
    $slug = (string) $slug;
    // WordPress native pages, including query strings for custom post types.
    if ( preg_match( '~^[a-z0-9_/-]+\.php(?:\?.*)?$~i', $slug ) ) { return admin_url( $slug ); }
    return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
}

function ncu_osch_workspace_description( $label, $slug, $children = array() ) {
    $name = strtolower( $label . ' ' . $slug );
    $descriptions = array(
        '/^dashboard\b|index\.php/' => 'Your campus command centre, apps and current activity.',
        '/posts|edit\.php$/' => 'Create, edit and organise articles, updates and posts.',
        '/pages/' => 'Manage your website pages and permanent information.',
        '/media|upload\.php/' => 'Upload, find and reuse images, documents and videos.',
        '/comments/' => 'Review and moderate conversations and feedback.',
        '/appearance|themes\.php/' => 'Manage your institutional identity and site design.',
        '/plugins/' => 'View, activate and manage installed applications.',
        '/users|profile/' => 'Manage member accounts, roles and profiles.',
        '/tools/' => 'Import, export and maintain site information.',
        '/settings|options/' => 'Configure the site and administrative preferences.',
        '/library/' => 'Organise scholarly resources, collections and shelves.',
        '/journal|publication|proceedings/' => 'Manage academic publications and publication records.',
        '/conference/' => 'Manage academic conferences, sessions and materials.',
        '/student/' => 'Manage student-facing content and campus information.',
        '/index/' => 'Explore and manage indexed scholarly content.',
        '/module|course|lesson|lecture|academy/' => 'Develop and deliver structured learning materials.',
        '/backup|archive|export/' => 'Protect, export and recover your institution’s content.',
        '/vault|security/' => 'Manage protected content and access permissions.',
        '/viewer|pdf|ebook|e-book/' => 'Manage digital reading and document experiences.',
        '/forms|survey/' => 'Create and manage data collection forms.',
        '/analytics|statistics|report/' => 'Explore records, insights and institutional reports.',
    );
    foreach ( $descriptions as $pattern => $description ) { if ( preg_match( $pattern, $name ) ) { return apply_filters( 'osch_workspace_description', $description, $slug, $label ); } }
    $fallback = count( $children ) ? 'Open ' . strtolower( $label ) . ' and its ' . count( $children ) . ' available tools.' : 'Open ' . $label . ' to view its available features.';
    return apply_filters( 'osch_workspace_description', $fallback, $slug, $label );
}

/** Collect only apps WordPress has exposed to this user, including submenu rights. */
add_action( 'admin_menu', 'ncu_osch_workspace_collect_apps', PHP_INT_MAX );
function ncu_osch_workspace_collect_apps() {
    global $menu, $submenu;
    $apps = array();
    foreach ( (array) $menu as $key => $item ) {
        if ( empty( $item[2] ) || empty( $item[1] ) || ! current_user_can( $item[1] ) ) { continue; }
        $slug = (string) $item[2];
        if ( 0 === strpos( $slug, 'separator' ) ) { continue; }
        $label = trim( wp_strip_all_tags( (string) $item[0] ) );
        if ( '' === $label ) { continue; }
        $is_scholar = ncu_osch_workspace_is_app( $slug, $label );
        $native = (bool) preg_match( '~^(?:index|edit|upload|edit-comments|themes|plugins|users|tools|options-general|profile|nav-menus)\.php(?:\?.*)?$~', $slug );
        $group = $is_scholar ? 'scholar' : ( $native ? 'wordpress' : 'extensions' );
        $children = array();
        foreach ( isset( $submenu[ $slug ] ) ? (array) $submenu[ $slug ] : array() as $subitem ) {
            if ( empty( $subitem[2] ) || empty( $subitem[1] ) || ! current_user_can( $subitem[1] ) ) { continue; }
            $sublabel = trim( wp_strip_all_tags( (string) $subitem[0] ) );
            if ( '' === $sublabel ) { continue; }
            $subslug = (string) $subitem[2];
            if ( $subslug === $slug || $sublabel === $label ) { continue; }
            $children[] = array( 'label' => $sublabel, 'url' => ncu_osch_workspace_menu_url( $subslug ), 'slug' => $subslug );
        }
        $original_icon = isset( $item[6] ) ? (string) $item[6] : '';
        // Retain native WordPress/plugin icons, but use a meaningful fallback if empty/generic.
        $icon = preg_match( '/^dashicons-[a-z0-9-]+$/', $original_icon ) && ! in_array( $original_icon, array( 'dashicons-admin-generic', 'dashicons-admin-plugins' ), true )
            ? $original_icon : ncu_osch_workspace_menu_icon( $label, $slug );
        $image = preg_match( '~^https?://~i', $original_icon ) ? $original_icon : '';
        $apps[ $slug ] = array(
            'label' => $label,
            'url' => ncu_osch_workspace_menu_url( $slug ),
            'description' => ncu_osch_workspace_description( $label, $slug, $children ),
            'group' => $group,
            'icon' => $icon,
            'image' => $image,
            'children' => $children,
        );
    }
    // Move scholarship apps to the beginning without removing WordPress/native apps.
    uasort( $apps, function ( $a, $b ) {
        $order = array( 'scholar' => 0, 'extensions' => 1, 'wordpress' => 2 );
        return ( $order[ $a['group'] ] <=> $order[ $b['group'] ] );
    } );
    ncu_osch_workspace_assign_visuals( $apps );
    $GLOBALS['ncu_osch_workspace_catalog'] = $apps;
    ncu_osch_workspace_apply_preferences( $apps );
    $GLOBALS['ncu_osch_workspace_apps'] = $apps;
}

add_filter( 'admin_body_class', function ( $classes ) {
    $screen = get_current_screen();
    return $classes . ( $screen && 'dashboard' === $screen->id ? ' osch-workspace-home' : '' );
} );

/** Dashboard is the landing screen; existing core widgets/settings remain saved. */
add_action( 'admin_notices', 'ncu_osch_workspace_home' );
function ncu_osch_workspace_home() {
    $screen = get_current_screen();
    if ( ! $screen || 'dashboard' !== $screen->id ) { return; }
    $apps = ncu_osch_workspace_apps();
    $name = wp_get_current_user()->display_name;
    $recent = current_user_can( 'edit_posts' ) ? get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 4, 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
    $updates = current_user_can( 'update_plugins' ) ? get_site_transient( 'update_plugins' ) : false;
    $update_count = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ? count( $updates->response ) : 0;
    $group_labels = array( 'scholar'=>'Open Scholar suite', 'extensions'=>'Installed applications', 'wordpress'=>'WordPress essentials' );
    ?>
    <main class="osch-workspace" aria-label="Open Scholar dashboard">
      <header class="osch-workspace-hero">
        <div class="osch-workspace-hero-top"><div class="osch-workspace-identity"><img src="<?php echo esc_url( ncu_open_scholar_logo_url() ); ?>" alt="" width="48" height="48"><span>OPEN SCHOLAR <small>THE FUTURE OF EDUCATION</small></span></div><span class="osch-workspace-status"><i aria-hidden="true"></i> Your workspace</span></div>
        <div class="osch-workspace-hero-body"><span class="osch-hero-kicker">INSTITUTIONAL DIGITAL WORKSPACE <span aria-hidden="true">/ 01</span></span><h1>Welcome to your<br><em>digital campus.</em></h1><p>Welcome, <?php echo esc_html( $name ); ?>. Your academic applications, essential tools and latest site activity—connected in one place.</p></div>
        <div class="osch-workspace-actions"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span> View your site <span aria-hidden="true">↗</span></a><?php if ( current_user_can( 'manage_options' ) ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=open-scholar-workspace' ) ); ?>"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span> Dashboard &amp; Menus</a><?php endif; ?><?php if ( current_user_can( 'install_plugins' ) ) : ?><a href="<?php echo esc_url( self_admin_url( 'plugin-install.php?tab=upload' ) ); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Install app <span aria-hidden="true">↗</span></a><?php endif; ?></div>
      </header>
      <section class="osch-latest" aria-labelledby="osch-latest-title"><div class="osch-latest-heading"><div><span class="osch-eyebrow">LIVE FROM YOUR SITE</span><h2 id="osch-latest-title">Latest information</h2></div><span class="osch-live-stamp">Updated on page load</span></div>
        <div class="osch-latest-grid"><div class="osch-latest-panel"><h3><span class="dashicons dashicons-admin-post" aria-hidden="true"></span> Recent activity</h3>
          <?php if ( $recent ) : ?><ul><?php foreach ( $recent as $post ) : ?><li><a href="<?php echo esc_url( current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID ) : get_permalink( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a><time datetime="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>"><?php echo esc_html( get_the_date( get_option( 'date_format' ), $post ) ); ?></time></li><?php endforeach; ?></ul><?php else : ?><p>No recent published posts are available to display.</p><?php endif; ?>
          <?php if ( current_user_can( 'edit_posts' ) ) : ?><a class="osch-latest-more" href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">View all posts <span aria-hidden="true">↗</span></a><?php endif; ?></div>
          <div class="osch-latest-panel osch-latest-panel-tools"><h3><span class="dashicons dashicons-update" aria-hidden="true"></span> Workspace updates</h3><?php if ( current_user_can( 'update_plugins' ) ) : ?><p><strong><?php echo esc_html( (string) $update_count ); ?></strong> plugin updates <?php echo $update_count ? 'available' : 'reported'; ?> in WordPress.</p><a class="osch-latest-more" href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>">Review updates <span aria-hidden="true">↗</span></a><?php else : ?><p>Use the app directory below to access the tools available to your account.</p><?php endif; ?></div></div>
      </section>
      <section class="osch-apps" aria-labelledby="osch-apps-title"><div class="osch-apps-heading"><div><span class="osch-eyebrow">YOUR CONNECTED APPLICATIONS</span><h2 id="osch-apps-title">App directory <small><?php echo esc_html( (string) count( $apps ) ); ?> available</small></h2><p>Browse applications and expand a card to access its submenus.</p></div><label class="osch-app-search"><span class="screen-reader-text">Search applications and their tools</span><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" id="osch-app-search" placeholder="Search apps and tools…" autocomplete="off"></label></div>
        <div class="osch-app-filters" role="group" aria-label="Filter applications"><button type="button" class="is-active" data-osch-group="all" aria-pressed="true">All apps</button><button type="button" data-osch-group="scholar" aria-pressed="false">Open Scholar</button><button type="button" data-osch-group="extensions" aria-pressed="false">Other apps</button><button type="button" data-osch-group="wordpress" aria-pressed="false">WordPress</button></div>
        <div class="osch-app-grid"><?php foreach ( $apps as $slug => $app ) : ?>
          <article class="osch-app-tile" style="--osch-app-ink:<?php echo esc_attr( $app['tone'][0] ); ?>;--osch-app-bg:<?php echo esc_attr( $app['tone'][1] ); ?>;--osch-app-edge:<?php echo esc_attr( $app['tone'][2] ); ?>" data-osch-group="<?php echo esc_attr( $app['group'] ); ?>" data-osch-app="<?php echo esc_attr( strtolower( $app['label'] . ' ' . $app['description'] . ' ' . implode( ' ', array_column( $app['children'], 'label' ) ) ) ); ?>">
            <div class="osch-app-card-head"><span class="osch-app-symbol" aria-hidden="true"><?php if ( $app['image'] ) : ?><img src="<?php echo esc_url( $app['image'] ); ?>" alt="" loading="lazy"><?php else : ?><?php echo ncu_osch_workspace_svg_icon( $app['icon_key'] ); ?><?php endif; ?><span class="osch-app-monogram"><?php echo esc_html( ncu_osch_workspace_initials( $app['label'] ) ); ?></span></span><span class="osch-app-type"><?php echo esc_html( $group_labels[ $app['group'] ] ); ?></span></div>
            <a class="osch-app-title" href="<?php echo esc_url( $app['url'] ); ?>"><h3><?php echo esc_html( $app['label'] ); ?></h3><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></a>
            <p class="osch-app-desc"><?php echo esc_html( $app['description'] ); ?></p>
            <?php if ( $app['children'] ) : ?><details class="osch-app-submenus"><summary>Browse <?php echo esc_html( (string) count( $app['children'] ) ); ?> tools <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary><ul><?php foreach ( $app['children'] as $child ) : ?><li><a href="<?php echo esc_url( $child['url'] ); ?>"><?php echo esc_html( $child['label'] ); ?><span aria-hidden="true">↗</span></a></li><?php endforeach; ?></ul></details><?php else : ?><span class="osch-app-no-submenu">Open application to get started</span><?php endif; ?>
          </article>
        <?php endforeach; ?></div><p id="osch-app-empty" role="status" hidden>No matching apps or tools. Try another search or filter.</p></section>
      <footer class="osch-workspace-footer">OPEN SCHOLAR <span>Future-ready education, thoughtfully connected.</span></footer>
    </main>
    <?php
}

/* Keep WordPress bar integration compatible with the 20.2.1 Top Bar popup. */
add_action( 'admin_bar_menu', function ( $bar ) {
    if ( ! is_admin() ) { return; }
    foreach ( (array) $bar->get_nodes() as $node ) {
        if ( in_array( $node->parent, array( false, '', 'top-secondary' ), true ) && ! in_array( $node->id, array( 'menu-toggle', 'my-account', 'top-secondary' ), true ) ) {
            $bar->remove_node( $node->id );
        }
    }
    $bar->add_node( array( 'id'=>'osch-workspace', 'title'=>'Open Scholar', 'href'=>admin_url(), 'meta'=>array( 'class'=>'osch-bar-brand' ) ) );
}, PHP_INT_MAX );
