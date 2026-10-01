<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }


function ncu_contrast_text_color( $hex ) {
    $hex = sanitize_hex_color( $hex );
    if ( ! $hex ) { return '#ffffff'; }
    $hex = ltrim( $hex, '#' );
    if ( 3 === strlen( $hex ) ) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
    $r = hexdec( substr( $hex, 0, 2 ) ); $g = hexdec( substr( $hex, 2, 2 ) ); $b = hexdec( substr( $hex, 4, 2 ) );
    $yiq = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
    return $yiq >= 150 ? '#111111' : '#ffffff';
}

function ncu_branding_image_url( $setting_key, $size = 'thumbnail', $fallback = '' ) {
    $s = function_exists( 'ncu_get_settings' ) ? ncu_get_settings() : array();
    $id = isset( $s[ $setting_key ] ) ? absint( $s[ $setting_key ] ) : 0;
    if ( $id ) {
        $url = wp_get_attachment_image_url( $id, $size );
        if ( $url ) { return $url; }
    }
    return $fallback;
}

function ncu_client_brand_name() {
    $s = ncu_get_settings();
    $name = isset( $s['client_brand_name'] ) ? trim( (string) $s['client_brand_name'] ) : '';
    return $name ? $name : get_bloginfo( 'name' );
}

function ncu_fallback_icon_url( $size = 'thumbnail' ) {
    $configured = ncu_branding_image_url( 'fallback_icon_id', $size, '' );
    if ( $configured ) { return $configured; }
    if ( has_site_icon() ) {
        $site_icon = get_site_icon_url( 'medium' === $size ? 256 : 96 );
        if ( $site_icon ) { return $site_icon; }
    }
    /* Never inject the 9Code product mark as a giant public content image. */
    return NCU_CORE_URL . 'assets/images/site-placeholder.svg';
}

function ncu_admin_brand_icon_url() {
    $url = ncu_branding_image_url( 'admin_brand_icon_id', 'thumbnail', '' );
    return $url ? $url : ncu_fallback_icon_url( 'thumbnail' );
}

function ncu_login_brand_logo_url() {
    $url = ncu_branding_image_url( 'login_logo_id', 'medium', '' );
    if ( $url ) { return $url; }
    $url = ncu_branding_image_url( 'client_logo_id', 'medium', '' );
    return $url ? $url : ncu_fallback_icon_url( 'medium' );
}

add_action( 'admin_bar_menu', 'ncu_white_label_admin_bar', 999 );
function ncu_white_label_admin_bar( $bar ) {
    if ( ! is_admin_bar_showing() ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) ) { return; }
    $bar->remove_node( 'wp-logo' );
}

add_action( 'admin_bar_menu', 'ncu_client_admin_bar_brand', 35 );
function ncu_client_admin_bar_brand( $bar ) {
    if ( ! is_admin_bar_showing() || ! current_user_can( 'read' ) ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) || empty( $s['admin_bar_brand_enabled'] ) ) { return; }
    $label = trim( (string) $s['admin_bar_label'] );
    if ( ! $label ) { $label = ncu_client_brand_name(); }
    $title = '<span class="ncu-adminbar-brand"><span>' . esc_html( $label ) . '</span></span>';
    $existing = $bar->get_node( 'site-name' );
    if ( $existing ) {
        $bar->add_node( array( 'id' => 'site-name', 'title' => $title, 'href' => $existing->href ? $existing->href : home_url( '/' ), 'meta' => is_array( $existing->meta ) ? $existing->meta : array() ) );
    } else {
        $bar->add_node( array( 'id' => 'ncu-client-brand', 'title' => $title, 'href' => home_url( '/' ), 'meta' => array( 'class' => 'ncu-client-brand-node' ) ) );
    }
}

add_filter( 'admin_footer_text', 'ncu_admin_footer_branding', 999 );
function ncu_admin_footer_branding( $text ) {
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) || empty( $s['admin_footer_branding'] ) ) { return $text; }
    $managed_name = trim( (string) $s['managed_by_name'] );
    $managed_url = esc_url( $s['managed_by_url'] );
    $owner_name = trim( (string) $s['theme_owner_name'] );
    $owner_url = esc_url( $s['theme_owner_url'] );
    $parts = array();
    if ( $managed_name ) {
        $parts[] = $managed_url ? 'Managed by <a href="' . $managed_url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $managed_name ) . '</a>' : 'Managed by ' . esc_html( $managed_name );
    }
    if ( $owner_name ) {
        $parts[] = $owner_url ? 'Nine Code · <a href="' . $owner_url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $owner_name ) . '</a>' : 'Nine Code · ' . esc_html( $owner_name );
    }
    return implode( ' &nbsp;·&nbsp; ', $parts );
}

add_filter( 'update_footer', 'ncu_admin_version_footer', 999 );
function ncu_admin_version_footer( $text ) {
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) ) { return $text; }
    return 'Nine Code ' . esc_html( NCU_CORE_VERSION );
}

add_filter( 'admin_title', 'ncu_white_label_admin_title', 999 );
function ncu_white_label_admin_title( $admin_title ) {
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) ) { return $admin_title; }
    $admin_title = str_ireplace( array( ' &#8212; WordPress', ' — WordPress', ' - WordPress' ), '', $admin_title );
    return $admin_title;
}

add_action( 'wp_dashboard_setup', 'ncu_white_label_dashboard_widgets', 99 );
function ncu_white_label_dashboard_widgets() {
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) || empty( $s['hide_wp_dashboard_news'] ) ) { return; }
    remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
    remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
}

add_filter( 'login_headerurl', 'ncu_login_brand_url' );
function ncu_login_brand_url( $url ) {
    $s = ncu_get_settings();
    return ! empty( $s['login_branding_enabled'] ) ? home_url( '/' ) : $url;
}
add_filter( 'login_headertext', 'ncu_login_brand_text' );
function ncu_login_brand_text( $text ) {
    $s = ncu_get_settings();
    return ! empty( $s['login_branding_enabled'] ) ? ncu_client_brand_name() : $text;
}

add_action( 'login_enqueue_scripts', 'ncu_login_branding_css', 99 );
function ncu_login_branding_css() {
    $s = ncu_get_settings();
    if ( empty( $s['login_branding_enabled'] ) ) { return; }
    $logo = ncu_login_brand_logo_url();
    $accent = sanitize_hex_color( $s['accent_color'] );
    if ( ! $accent ) { $accent = '#000000'; }
    $accent_text = ncu_contrast_text_color( $accent );
    if ( ! empty( $s['admin_skin_enabled'] ) ) {
        $skin_mode = ncu_admin_skin_normalize_mode( isset( $s['admin_skin_mode'] ) ? $s['admin_skin_mode'] : 'white_future' );
        $skin = ncu_admin_skin_palette_tokens( $skin_mode );
        $canvas = sanitize_hex_color( $skin['canvas'] );
        $surface = sanitize_hex_color( $skin['surface'] );
        $text_color = sanitize_hex_color( $skin['text'] );
        $muted = sanitize_hex_color( $skin['muted'] );
        $border = sanitize_hex_color( $skin['border'] );
        $shell = sanitize_hex_color( $skin['shell'] );
        $shell_text = sanitize_hex_color( $skin['shell_text'] );
        $focus = sanitize_hex_color( $skin['focus'] );
        echo '<style id="ncu-login-branding">body.login{background:' . esc_attr( $canvas ) . ';color:' . esc_attr( $text_color ) . ';font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Roboto,Helvetica,Arial,sans-serif}body.login #login{padding-top:6vh}body.login h1 a{background-image:url(' . esc_url( $logo ) . ')!important;background-size:contain!important;background-position:center!important;width:82px!important;height:82px!important;margin-bottom:18px!important}body.login #loginform,body.login .message,body.login .notice{border-radius:10px;border:1px solid ' . esc_attr( $border ) . ';box-shadow:0 12px 34px rgba(15,23,42,.08)}body.login #loginform{background:' . esc_attr( $surface ) . '}body.login label{color:' . esc_attr( $text_color ) . '}body.login input[type=text],body.login input[type=password]{background:' . esc_attr( $surface ) . ';color:' . esc_attr( $text_color ) . ';border-color:' . esc_attr( $border ) . ';border-radius:6px;box-shadow:none}body.login input:focus{border-color:' . esc_attr( $focus ) . ';box-shadow:0 0 0 1px ' . esc_attr( $focus ) . '}body.login .button-primary{background:' . esc_attr( $shell ) . ';border-color:' . esc_attr( $shell ) . ';color:' . esc_attr( $shell_text ) . ';border-radius:6px;box-shadow:none}body.login #backtoblog a,body.login #nav a{color:' . esc_attr( $muted ) . '}.ncu-login-owner{text-align:center;margin:16px auto 0;font:600 10px/1.4 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;letter-spacing:.04em;color:' . esc_attr( $muted ) . '}.ncu-login-owner a{color:inherit}</style>';
    } else {
        echo '<style id="ncu-login-branding">body.login{background:#f4f5f7}body.login #login{padding-top:6vh}body.login h1 a{background-image:url(' . esc_url( $logo ) . ')!important;background-size:contain!important;background-position:center!important;width:96px!important;height:96px!important;margin-bottom:18px!important}body.login #loginform,body.login .message,body.login .notice{border-radius:14px;border:1px solid #dfe3e8;box-shadow:0 10px 35px rgba(0,0,0,.08)}body.login .button-primary{background:' . esc_attr( $accent ) . ';border-color:' . esc_attr( $accent ) . ';color:' . esc_attr( $accent_text ) . '}body.login #backtoblog a,body.login #nav a{color:#222}.ncu-login-owner{text-align:center;margin:16px auto 0;font-size:12px;color:#646970}.ncu-login-owner a{color:inherit}</style>';
    }
}

add_action( 'admin_head', 'ncu_admin_app_branding_css', 2 );
function ncu_admin_app_branding_css() {
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) ) { return; }
    $accent = sanitize_hex_color( $s['accent_color'] );
    if ( ! $accent ) { $accent = '#000'; }
    $accent_text = ncu_contrast_text_color( $accent );
    $style = '<style id="ncu-admin-app-branding">';
    $style .= '#wpadminbar #wp-admin-bar-wp-logo{display:none!important}.ncu-adminbar-brand{display:inline-flex!important;align-items:center!important;max-width:180px!important}.ncu-adminbar-brand span{display:block!important;max-width:180px!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important}';
    if ( ! empty( $s['admin_style_enabled'] ) && empty( $s['admin_skin_enabled'] ) ) {
        $style .= 'body.wp-admin{background:#f5f6f7}#wpadminbar{background:#0d0f12}#adminmenuback,#adminmenuwrap,#adminmenu{background:#111317}#adminmenu .wp-has-current-submenu .wp-submenu,#adminmenu .wp-submenu{background:#090a0d}#adminmenu .wp-has-current-submenu .wp-submenu .wp-submenu-head,#adminmenu .wp-menu-arrow,#adminmenu .wp-menu-arrow div{background:#111317}#adminmenu .wp-has-current-submenu>a.wp-has-current-submenu,#adminmenu .current a.menu-top,#adminmenu .wp-menu-open>a.menu-top{background:' . esc_attr( $accent ) . ';color:' . esc_attr( $accent_text ) . '}#adminmenu a:hover,#adminmenu li.menu-top:hover,#adminmenu li.opensub>a.menu-top{color:#fff}.wrap h1,.wrap h2{letter-spacing:-.018em}.postbox,.stuffbox,#dashboard-widgets .postbox{border-radius:12px;overflow:hidden}.button,.button-primary,.button-secondary{border-radius:8px}';
    }
    /* Preserve editor navigation while visually replacing common WordPress glyphs. */
    $style .= '</style>';
    echo $style;
}

add_action( 'admin_head', 'ncu_admin_favicon' );
add_action( 'login_head', 'ncu_admin_favicon' );
function ncu_admin_favicon() {
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) ) { return; }
    echo '<link rel="icon" href="' . esc_url( ncu_fallback_icon_url( 'thumbnail' ) ) . '">';
}

/* Front-end fallback favicon when the site has not defined a native Site Icon. */
add_action( 'wp_head', 'ncu_frontend_fallback_favicon', 2 );
function ncu_frontend_fallback_favicon() {
    if ( has_site_icon() ) { return; }
    echo '<link rel="icon" href="' . esc_url( ncu_fallback_icon_url( 'thumbnail' ) ) . '">';
}

/* Remove visible/version generator branding without modifying WordPress core. */
add_action( 'init', 'ncu_remove_public_generator_branding' );
function ncu_remove_public_generator_branding() {
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) ) { return; }
    remove_action( 'wp_head', 'wp_generator' );
}
add_filter( 'the_generator', 'ncu_filter_generator_branding' );
function ncu_filter_generator_branding( $generator ) {
    $s = ncu_get_settings();
    return ! empty( $s['white_label_enabled'] ) ? '' : $generator;
}

function ncu_media_setting_field( $label, $key, $s, $description = '', $fallback_url = '' ) {
    $id = isset( $s[ $key ] ) ? absint( $s[ $key ] ) : 0;
    $preview = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : $fallback_url;
    ?>
    <label class="ncu-media-setting ncu-span-2">
        <span><?php echo esc_html( $label ); ?></span>
        <input type="hidden" name="ncu[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $id ); ?>" data-ncu-media-input="<?php echo esc_attr( $key ); ?>">
        <span class="ncu-media-control">
            <span class="ncu-media-preview" data-ncu-media-preview="<?php echo esc_attr( $key ); ?>"><?php if ( $preview ) : ?><img src="<?php echo esc_url( $preview ); ?>" alt="" width="64" height="64"><?php endif; ?></span>
            <button class="button" type="button" data-ncu-media-choose="<?php echo esc_attr( $key ); ?>">Choose image</button>
            <button class="button-link-delete" type="button" data-ncu-media-clear="<?php echo esc_attr( $key ); ?>">Clear</button>
        </span>
        <?php if ( $description ) : ?><small><?php echo esc_html( $description ); ?></small><?php endif; ?>
    </label>
    <?php
}

function ncu_branding_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $s = ncu_get_settings();
    ?>
    <div class="wrap ncu-admin">
        <?php ncu_admin_header( 'Branding & Ownership', 'Use the CMS engine invisibly while the admin experience carries only the client brand and Nine Code ownership.' ); ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="ncu_save_settings">
            <input type="hidden" name="ncu_return_page" value="nine-code-ultra-branding">
            <?php wp_nonce_field( 'ncu_save_settings' ); ?>

            <section class="ncu-panel ncu-panel--padded">
                <h2>App branding</h2>
                <div class="ncu-checks">
                    <?php ncu_checkbox( 'white_label_enabled', 'Use 9 Code app-style white label', $s ); ?>
                    <?php ncu_checkbox( 'admin_style_enabled', 'Enable 9 Code/client white-label identity in admin', $s ); ?>
                    <?php ncu_checkbox( 'admin_bar_brand_enabled', 'Show client/site brand in the admin bar', $s ); ?>
                    <?php ncu_checkbox( 'login_branding_enabled', 'Brand the login screen', $s ); ?>
                    <?php ncu_checkbox( 'admin_footer_branding', 'Show management/theme ownership in admin footer', $s ); ?>
                    <?php ncu_checkbox( 'hide_wp_dashboard_news', 'Hide platform news/promotional dashboard widget', $s ); ?>
                </div>
                <p class="description">This white-labels normal admin chrome without editing platform core files or disabling updates, security, REST, Gutenberg or compatibility APIs. The full IDE-style interface is controlled independently under <strong>Admin UI / Skin</strong>; turning this branding option off no longer disables the Admin Workspace skin.</p>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Client / site identity</h2>
                <div class="ncu-grid">
                    <label><span>Client/site brand name</span><input type="text" name="ncu[client_brand_name]" value="<?php echo esc_attr( $s['client_brand_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><small>Blank uses the Site Title value.</small></label>
                    <label><span>Admin bar label</span><input type="text" name="ncu[admin_bar_label]" value="<?php echo esc_attr( $s['admin_bar_label'] ); ?>" placeholder="Use client/site name"></label>
                    <?php ncu_media_setting_field( 'Client/site logo', 'client_logo_id', $s, 'Used on the branded login screen when a dedicated login logo is not set.' ); ?>
                    <?php ncu_media_setting_field( 'Site fallback image', 'fallback_icon_id', $s, 'Used only when content has no explicit image or native Site Icon. The 9Code product mark is never inserted into public content.', NCU_CORE_URL . 'assets/images/site-placeholder.svg' ); ?>
                    <?php ncu_media_setting_field( 'Admin bar icon', 'admin_brand_icon_id', $s, 'Defaults to the fallback icon. Hard-limited to 20×20px so it can never cover wp-admin.' ); ?>
                    <?php ncu_media_setting_field( 'Login logo', 'login_logo_id', $s, 'Defaults to Client/site logo, then fallback icon.' ); ?>
                </div>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Managed by</h2>
                <p class="description">Use this for the organisation maintaining or operating the client site.</p>
                <div class="ncu-grid">
                    <label><span>Managed by name</span><input type="text" name="ncu[managed_by_name]" value="<?php echo esc_attr( $s['managed_by_name'] ); ?>"></label>
                    <label><span>Managed by URL</span><input type="url" name="ncu[managed_by_url]" value="<?php echo esc_attr( $s['managed_by_url'] ); ?>"></label>
                    <?php ncu_media_setting_field( 'Managed by logo', 'managed_by_logo_id', $s, 'Optional record/identity asset. It does not force a public footer.' ); ?>
                </div>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Theme ownership</h2>
                <p class="description">This identifies who owns/supports the Nine Code theme deployment. It is shown in the private admin attribution when enabled, not forced into the public footer.</p>
                <div class="ncu-grid">
                    <label><span>Theme owner name</span><input type="text" name="ncu[theme_owner_name]" value="<?php echo esc_attr( $s['theme_owner_name'] ); ?>"></label>
                    <label><span>Theme owner URL</span><input type="url" name="ncu[theme_owner_url]" value="<?php echo esc_attr( $s['theme_owner_url'] ); ?>"></label>
                    <?php ncu_media_setting_field( 'Theme owner logo', 'theme_owner_logo_id', $s, 'Optional private ownership/record asset.' ); ?>
                </div>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>What the white label changes</h2>
                <div class="ncu-audit-list"><p>Admin bar platform logo → replaced with client/site identity.</p><p>Login platform logo → client/site branding.</p><p>Admin footer platform attribution/version → Managed by + Nine Code ownership.</p><p>Dashboard platform news/promotions → hidden when selected.</p><p>Public generator branding → removed.</p><p>Core platform files and update mechanisms → untouched for stability and security.</p></div>
            </section>
            <p class="submit"><button class="button button-primary button-hero">Save branding</button></p>
        </form>
    </div>
    <?php
}

add_action( 'wp_dashboard_setup', 'ncu_replace_welcome_panel_branding', 1 );
function ncu_replace_welcome_panel_branding() {
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) ) { return; }
    remove_action( 'welcome_panel', 'wp_welcome_panel' );
    add_action( 'welcome_panel', 'ncu_client_welcome_panel' );
}

function ncu_client_welcome_panel() {
    if ( ! current_user_can( 'read' ) ) { return; }
    $name = ncu_client_brand_name();
    $icon = ncu_admin_brand_icon_url();
    ?>
    <div class="welcome-panel-content ncu-client-welcome">
        <div class="ncu-client-welcome__brand"><img src="<?php echo esc_url( $icon ); ?>" alt="" width="42" height="42"><div><small>9 CODE APPLICATION</small><h2><?php echo esc_html( $name ); ?></h2><p>Workspace ready.</p></div></div>
        <div class="ncu-client-welcome__meta"><code><?php echo esc_html( strtoupper( function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production' ) ); ?></code><code>CORE <?php echo esc_html( NCU_CORE_VERSION ); ?></code><code>API <?php echo esc_html( NCU_CORE_API_VERSION ); ?></code></div>
        <?php if ( current_user_can( 'manage_options' ) ) : ?><p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra' ) ); ?>">Open Nine Code</a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-admin-workspace' ) ); ?>">Admin Workspace</a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-doctor' ) ); ?>">Doctor</a></p><?php endif; ?>
    </div>
    <?php
}

add_filter( 'update_right_now_text', 'ncu_white_label_right_now_text', 99 );
function ncu_white_label_right_now_text( $text ) {
    $s = ncu_get_settings();
    if ( empty( $s['white_label_enabled'] ) ) { return $text; }
    return sprintf( 'Nine Code application engine · %s', esc_html( ncu_client_brand_name() ) );
}

add_filter( 'login_title', 'ncu_white_label_login_title', 99, 2 );
function ncu_white_label_login_title( $login_title, $title ) {
    $s = ncu_get_settings();
    if ( empty( $s['login_branding_enabled'] ) ) { return $login_title; }
    return esc_html( $title . ' ‹ ' . ncu_client_brand_name() );
}

add_action( 'login_footer', 'ncu_login_owner_attribution', 20 );
function ncu_login_owner_attribution() {
    $s = ncu_get_settings();
    if ( empty( $s['login_branding_enabled'] ) || empty( $s['theme_owner_name'] ) ) { return; }
    $name = esc_html( $s['theme_owner_name'] );
    $url = ! empty( $s['theme_owner_url'] ) ? esc_url( $s['theme_owner_url'] ) : '';
    echo '<p class="ncu-login-owner">Nine Code · ' . ( $url ? '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $name . '</a>' : $name ) . '</p>';
}
