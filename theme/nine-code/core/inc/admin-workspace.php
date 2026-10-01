<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Nine Code Admin Workspace.
 *
 * This is a presentation layer over wp-admin. It does not modify WordPress
 * core files, permissions, routes, editor data or plugin layouts.
 */

function ncu_admin_skin_foreign_app_screen( $screen = null ) {
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    if ( 'elementor' === $action ) { return true; }
    $screen_id = is_object( $screen ) && isset( $screen->id ) ? strtolower( (string) $screen->id ) : '';
    $base = is_object( $screen ) && isset( $screen->base ) ? strtolower( (string) $screen->base ) : '';
    if ( false !== strpos( $screen_id, 'elementor' ) || false !== strpos( $base, 'elementor' ) ) { return true; }
    if ( false !== strpos( $screen_id, 'acf' ) || false !== strpos( $base, 'acf' ) ) { return true; }
    return (bool) apply_filters( 'ncu_admin_skin_foreign_app_screen', false, $screen, $action );
}

function ncu_admin_skin_is_active() {
    if ( ! is_admin() ) { return false; }
    if ( function_exists( 'is_network_admin' ) && is_network_admin() ) { return false; }
    if ( function_exists( 'is_user_admin' ) && is_user_admin() ) { return false; }
    if ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) { return false; }
    $s = ncu_get_settings();
    if ( empty( $s['admin_skin_enabled'] ) ) { return false; }

    // Per-request recovery hatch. Administrators can append ?ncu_admin_skin=off
    // to any wp-admin URL if another plugin's CSS collides with the skin.
    if ( current_user_can( 'manage_options' ) && isset( $_GET['ncu_admin_skin'] ) ) {
        $mode = sanitize_key( wp_unslash( $_GET['ncu_admin_skin'] ) );
        if ( 'off' === $mode ) { return false; }
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && 'customize' === $screen->base ) { return false; }
    if ( ncu_admin_skin_foreign_app_screen( $screen ) ) { return false; }
    if ( $screen && ! apply_filters( 'ncu_admin_skin_screen_allowed', true, $screen, $s ) ) { return false; }

    return (bool) apply_filters( 'ncu_admin_skin_active', true, $s );
}

function ncu_admin_skin_mode() {
    $s = ncu_get_settings();
    return ncu_admin_skin_normalize_mode( isset( $s['admin_skin_mode'] ) ? $s['admin_skin_mode'] : 'white_future' );
}

function ncu_admin_skin_density() {
    $s = ncu_get_settings();
    return isset( $s['admin_skin_density'] ) && 'comfortable' === $s['admin_skin_density'] ? 'comfortable' : 'compact';
}

add_filter( 'admin_body_class', 'ncu_admin_skin_body_class' );
function ncu_admin_skin_body_class( $classes ) {
    if ( ! ncu_admin_skin_is_active() ) { return $classes; }
    $s = ncu_get_settings();
    $classes .= ' ncu-admin-skin ncu-admin-skin--' . ncu_admin_skin_mode();
    $classes .= ' ncu-admin-density--' . ncu_admin_skin_density();
    if ( ! empty( $s['admin_skin_enhance_tables'] ) ) { $classes .= ' ncu-admin-enhance-tables'; }
    if ( ! empty( $s['admin_skin_enhance_modals'] ) ) { $classes .= ' ncu-admin-enhance-modals'; }
    if ( ! empty( $s['admin_skin_menu_enabled'] ) ) { $classes .= ' ncu-admin-menu-skin'; }
    if ( ! empty( $s['admin_skin_monochrome'] ) ) { $classes .= ' ncu-admin-monochrome'; }
    if ( ! empty( $s['admin_skin_editor_chrome'] ) ) { $classes .= ' ncu-admin-editor-chrome'; }
    return $classes;
}

/**
 * Critical left-navigation skin.
 *
 * v3.3 decouples the Admin Workspace from the legacy Branding toggle and
 * prints a very small, late, high-specificity shell rule so the WordPress
 * menu cannot quietly fall back to its stock colour scheme when another
 * plugin loads broad admin CSS after our stylesheet.
 */
/* Retained only for downgrade compatibility; admin-menu.css is authoritative. */
function ncu_admin_skin_critical_menu_css() {
    if ( ! ncu_admin_skin_is_active() ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['admin_skin_menu_enabled'] ) ) { return; }
    echo '<style id="ncu-admin-critical-menu">'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenuback,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenuwrap,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu{background:var(--ncu-admin-shell,#070707)!important;color:var(--ncu-admin-shell-text,#fff)!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu{padding:8px 0 12px!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu>li.menu-top{margin:3px 7px!important;width:calc(100% - 14px)!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu>li.menu-top>a.menu-top{box-sizing:border-box!important;min-height:38px!important;border:1px solid transparent!important;border-radius:8px!important;background:transparent!important;color:#f5f5f5!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-menu-name,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu a{white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;line-height:1.25!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu>li.menu-top:hover>a.menu-top{border-color:var(--ncu-admin-shell-border,#333)!important;background:var(--ncu-admin-shell-hover,#171717)!important;color:var(--ncu-admin-shell-text,#fff)!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu>li.current>a.menu-top,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu>li.wp-has-current-submenu>a.wp-has-current-submenu{border-color:var(--ncu-admin-shell-active-bg,#fff)!important;background:var(--ncu-admin-shell-active-bg,#fff)!important;color:var(--ncu-admin-shell-active-text,#070707)!important;box-shadow:0 7px 20px rgba(0,0,0,.18)!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu>li.current>a.menu-top .wp-menu-name,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu>li.wp-has-current-submenu>a.wp-has-current-submenu .wp-menu-name{color:var(--ncu-admin-shell-active-text,#070707)!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu{background:var(--ncu-admin-shell-sub,#0d0d0d)!important;border:1px solid var(--ncu-admin-shell-border,#2b2b2b)!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu a{color:var(--ncu-admin-shell-muted,#cfcfcf)!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu a:hover,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu li.current a{background:var(--ncu-admin-shell-hover,#1c1c1c)!important;color:var(--ncu-admin-shell-text,#fff)!important}'
        . '</style>';
}

/*
 * Keep WordPress's hard-coded blue menu states out of the 9Code skin.
 * This deliberately runs after both WordPress core and the legacy Branding
 * CSS, because plugins frequently enqueue broad menu rules late in admin_head.
 */
/* Retained only for downgrade compatibility; admin-menu.css is authoritative. */
function ncu_admin_skin_menu_neutral_lock() {
    if ( ! ncu_admin_skin_is_active() ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['admin_skin_menu_enabled'] ) ) { return; }
    echo '<style id="ncu-admin-menu-neutral-lock">'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenuwrap{--wp-admin-theme-color:#4a4a4a!important;--wp-admin-theme-color-darker-10:#2c2c2c!important;--wp-admin-theme-color-darker-20:#151515!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenuback,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenuwrap{border-left:0!important;box-shadow:none!important}body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu:before,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenuwrap:before{display:none!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu .wp-submenu-head{background:var(--ncu-admin-shell-sub,#0d0d0d)!important;color:var(--ncu-admin-shell-text,#fff)!important;border-color:var(--ncu-admin-shell-border,#2b2b2b)!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu li.current>a,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu a:hover,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu a:focus{background:var(--ncu-admin-shell-hover,#1c1c1c)!important;color:var(--ncu-admin-shell-text,#fff)!important;box-shadow:inset 3px 0 0 var(--ncu-admin-shell-active-bg,#fff)!important;outline:2px solid var(--ncu-admin-shell-active-bg,#fff)!important;outline-offset:-2px!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-has-current-submenu .wp-menu-arrow,body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-has-current-submenu .wp-menu-arrow div{background:var(--ncu-admin-shell,#070707)!important}'
        . 'body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-has-current-submenu .wp-menu-arrow:after{border-right-color:var(--ncu-admin-shell,#070707)!important}'
        . '@media(min-width:783px){body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu>li.menu-top{margin:3px 8px!important;width:calc(100% - 16px)!important}body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu>li.menu-top>a.menu-top{display:flex!important;align-items:center!important;min-height:40px!important;padding:0 9px 0 0!important}body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-menu-image{display:grid!important;place-items:center!important;width:36px!important;min-width:36px!important;height:40px!important}body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-menu-name{padding:0!important;line-height:40px!important}body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu{box-sizing:border-box!important;margin:3px 8px 7px!important;padding:4px!important;width:calc(100% - 16px)!important}body.ncu-admin-skin.ncu-admin-menu-skin #adminmenu .wp-submenu a{min-height:34px!important;padding:8px 10px!important;line-height:18px!important}}'
        . '</style>';
}

add_action( 'admin_enqueue_scripts', 'ncu_admin_skin_assets', 999 );
function ncu_admin_skin_assets( $hook_suffix = '' ) {
    if ( ! ncu_admin_skin_is_active() ) { return; }
    $s = ncu_get_settings();
    wp_enqueue_style( 'ncu-admin-skin', NCU_CORE_URL . 'assets/css/admin-skin.css', array(), NCU_CORE_VERSION );
    if ( ! empty( $s['admin_skin_menu_enabled'] ) ) {
        wp_enqueue_style( 'ncu-admin-menu', NCU_CORE_URL . 'assets/css/admin-menu.css', array( 'ncu-admin-skin' ), NCU_CORE_VERSION );
    }
    wp_add_inline_style( 'ncu-admin-skin', ncu_admin_skin_runtime_css() );
    $optional_css = ncu_admin_workspace_optional_css();
    if ( $optional_css ) { wp_add_inline_style( 'ncu-admin-skin', $optional_css ); }
    $workspace_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $needs_script = ( ! function_exists( 'ncu_admin_screen_is_post_editor' ) || ! ncu_admin_screen_is_post_editor() ) && ( ! empty( $s['admin_skin_command_palette'] ) || 'nine-code-ultra-admin-workspace' === $workspace_page );
    if ( $needs_script ) {
        wp_enqueue_script( 'ncu-admin-skin', NCU_CORE_URL . 'assets/js/admin-skin.js', array(), NCU_CORE_VERSION, true );
    }
}

/**
 * Print the left-navigation interaction contract after every normal admin
 * stylesheet. The CSS file still owns the complete design; this late lock is
 * deliberately limited to states that WordPress colour schemes and hosting
 * dashboards commonly repaint with blue.
 *
 * The selectors do not depend on an admin body class because this function is
 * already gated by the saved 9Code skin settings. That makes the contract
 * survive custom admin shells which rebuild or filter the body class list.
 */
add_action( 'admin_footer', 'ncu_admin_menu_final_contract', PHP_INT_MAX );
function ncu_admin_menu_final_contract() {
    if ( ! ncu_admin_skin_is_active() ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['admin_skin_menu_enabled'] ) ) { return; }
    ?>
    <style id="ncu-admin-menu-final-contract">
        body #adminmenuback,
        body #adminmenuwrap,
        body #adminmenu {
            background: #080808 !important;
            background-image: none !important;
            border: 0 !important;
            box-shadow: none !important;
        }
        body #adminmenu::before,
        body #adminmenu::after,
        body #adminmenuback::before,
        body #adminmenuback::after,
        body #adminmenuwrap::before,
        body #adminmenuwrap::after {
            display: none !important;
            border: 0 !important;
            box-shadow: none !important;
        }
        body #adminmenu > li.menu-top > a.menu-top,
        body #adminmenu > li.menu-top > a.menu-top:visited {
            background: transparent !important;
            background-image: none !important;
            border-color: transparent !important;
            color: #f4f4f4 !important;
            box-shadow: none !important;
        }
        body #adminmenu > li.menu-top:hover > a.menu-top,
        body #adminmenu > li.opensub > a.menu-top,
        body #adminmenu > li > a.menu-top:hover,
        body #adminmenu > li > a.menu-top:focus,
        body #adminmenu > li > a.menu-top:focus-visible {
            background: #303030 !important;
            background-image: none !important;
            border-color: #696969 !important;
            color: #fff !important;
            outline: 1px solid #696969 !important;
            outline-offset: -1px !important;
            box-shadow: none !important;
        }
        body #adminmenu > li.current > a.menu-top,
        body #adminmenu > li.current > a.menu-top:hover,
        body #adminmenu > li.current > a.menu-top:focus,
        body #adminmenu > li.wp-has-current-submenu > a.wp-has-current-submenu,
        body #adminmenu > li.wp-has-current-submenu > a.wp-has-current-submenu:hover,
        body #adminmenu > li.wp-has-current-submenu > a.wp-has-current-submenu:focus,
        body #adminmenu > li.wp-menu-open > a.menu-top {
            background: #fff !important;
            background-image: none !important;
            border: 0 !important;
            color: #080808 !important;
            outline: 0 !important;
            box-shadow: none !important;
        }
        body #adminmenu > li.current > a.menu-top .wp-menu-name,
        body #adminmenu > li.current > a.menu-top .wp-menu-image::before,
        body #adminmenu > li.wp-has-current-submenu > a.wp-has-current-submenu .wp-menu-name,
        body #adminmenu > li.wp-has-current-submenu > a.wp-has-current-submenu .wp-menu-image::before,
        body #adminmenu > li.wp-menu-open > a.menu-top .wp-menu-name,
        body #adminmenu > li.wp-menu-open > a.menu-top .wp-menu-image::before {
            color: #080808 !important;
        }
        body #adminmenu .wp-submenu,
        body #adminmenu .wp-has-current-submenu .wp-submenu,
        body #adminmenu .wp-submenu .wp-submenu-head {
            background: #111 !important;
            background-image: none !important;
            border-color: #292929 !important;
            color: #cfcfcf !important;
            box-shadow: none !important;
        }
        body #adminmenu .wp-submenu li.current > a,
        body #adminmenu .wp-submenu a:hover,
        body #adminmenu .wp-submenu a:focus,
        body #adminmenu .wp-submenu a:focus-visible {
            background: #303030 !important;
            background-image: none !important;
            border-color: #696969 !important;
            color: #fff !important;
            outline: 1px solid #696969 !important;
            outline-offset: -1px !important;
            box-shadow: none !important;
        }
        body #adminmenu .wp-menu-arrow,
        body #adminmenu .wp-menu-arrow div,
        body #adminmenu .wp-menu-arrow::before,
        body #adminmenu .wp-menu-arrow::after {
            display: none !important;
            background: transparent !important;
            border-color: transparent !important;
            box-shadow: none !important;
        }
        @media (min-width: 783px) {
            body #adminmenu {
                padding: 4px 0 8px !important;
            }
            body #adminmenu > li.menu-top {
                margin: 1px 4px !important;
                width: calc(100% - 8px) !important;
            }
            body #adminmenu > li.menu-top > a.menu-top {
                display: flex !important;
                align-items: center !important;
                min-height: 34px !important;
                height: 34px !important;
                padding: 0 7px 0 0 !important;
                border-radius: 5px !important;
            }
            body #adminmenu .wp-menu-image {
                display: grid !important;
                place-items: center !important;
                flex: 0 0 32px !important;
                width: 32px !important;
                min-width: 32px !important;
                height: 34px !important;
                padding: 0 !important;
            }
            body #adminmenu .wp-menu-name {
                min-width: 0 !important;
                padding: 0 !important;
                font-size: 13px !important;
                line-height: 34px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            body #adminmenu .wp-submenu,
            body #adminmenu .wp-has-current-submenu .wp-submenu {
                margin: 2px 4px 4px !important;
                padding: 2px !important;
                width: calc(100% - 8px) !important;
            }
            body #adminmenu .wp-submenu a {
                min-height: 29px !important;
                margin: 0 !important;
                padding: 5px 8px !important;
                font-size: 12px !important;
                line-height: 19px !important;
            }
        }
    </style>
    <?php
}

function ncu_admin_skin_runtime_css() {
    $s = ncu_get_settings();
    $radius = isset( $s['admin_skin_radius'] ) ? max( 4, min( 18, absint( $s['admin_skin_radius'] ) ) ) : 10;
    $shadow = isset( $s['admin_skin_shadow'] ) ? sanitize_key( $s['admin_skin_shadow'] ) : 'soft';
    $shadow_value = 'none';
    if ( 'soft' === $shadow ) { $shadow_value = '0 8px 24px rgba(15,23,42,.07)'; }
    if ( 'deep' === $shadow ) { $shadow_value = '0 14px 38px rgba(15,23,42,.12)'; }
    return 'body.ncu-admin-skin{--ncu-admin-radius:' . $radius . 'px;--ncu-admin-shadow:' . $shadow_value . '}';
}

function ncu_admin_screen_is_block_editor() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || ! is_callable( array( $screen, 'is_block_editor' ) ) ) { return false; }
    return (bool) $screen->is_block_editor();
}

add_action( 'in_admin_header', 'ncu_admin_workspace_context_bar', 20 );
function ncu_admin_workspace_context_bar() {
    if ( ! ncu_admin_skin_is_active() || ncu_admin_screen_is_block_editor() || ( function_exists( 'ncu_admin_screen_is_post_editor' ) && ncu_admin_screen_is_post_editor() ) ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['admin_skin_workspace_bar'] ) ) { return; }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && in_array( $screen->base, array( 'customize' ), true ) ) { return; }
    if ( apply_filters( 'ncu_admin_workspace_bar_suppressed', false, $screen ) ) { return; }

    $title = function_exists( 'get_admin_page_title' ) ? get_admin_page_title() : '';
    if ( ! $title && $screen && ! empty( $screen->title ) ) { $title = $screen->title; }
    $environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
    ?>
    <div class="ncu-workspace-context" role="region" aria-label="<?php esc_attr_e( '9 Code workspace context', 'nine-code' ); ?>">
        <div class="ncu-workspace-context__identity">
            <span><small>WORKSPACE</small><strong><?php echo esc_html( $title ? $title : ncu_client_brand_name() ); ?></strong></span>
        </div>
        <div class="ncu-workspace-context__actions">
            <span class="ncu-workspace-env" title="<?php esc_attr_e( 'Application environment', 'nine-code' ); ?>"><?php echo esc_html( strtoupper( $environment ) ); ?></span>
            <a class="ncu-workspace-action" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View site', 'nine-code' ); ?></a>
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <a class="ncu-workspace-action" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-doctor' ) ); ?>"><?php esc_html_e( 'Doctor', 'nine-code' ); ?></a>
            <?php endif; ?>
            <?php if ( ! empty( $s['admin_skin_command_palette'] ) ) : ?>
                <button class="ncu-workspace-action ncu-workspace-command" type="button" data-ncu-command-open aria-haspopup="dialog">⌘K</button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

add_action( 'admin_bar_menu', 'ncu_admin_workspace_command_node', 998 );
function ncu_admin_workspace_command_node( $bar ) {
    if ( ! ncu_admin_skin_is_active() || ! current_user_can( 'read' ) || ( function_exists( 'ncu_admin_screen_is_post_editor' ) && ncu_admin_screen_is_post_editor() ) ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['admin_skin_command_palette'] ) ) { return; }
    $bar->add_node( array(
        'id'    => 'ncu-command-palette',
        'title' => '<span class="ncu-command-adminbar" data-ncu-command-open aria-label="' . esc_attr__( 'Open command palette', 'nine-code' ) . '">⌘K</span>',
        'href'  => '#ncu-command-palette',
        'meta'  => array( 'class' => 'ncu-command-adminbar-node' ),
    ) );
}

function ncu_admin_workspace_commands() {
    $commands = array();
    $add = static function( $label, $hint, $url, $group = 'Workspace' ) use ( &$commands ) {
        $commands[] = array( 'label' => $label, 'hint' => $hint, 'url' => $url, 'group' => $group );
    };

    $add( __( 'Dashboard', 'nine-code' ), 'dashboard', admin_url(), 'Workspace' );
    $add( __( 'View site', 'nine-code' ), 'site preview', home_url( '/' ), 'Workspace' );

    if ( current_user_can( 'edit_posts' ) ) {
        $add( __( 'Posts', 'nine-code' ), 'content posts', admin_url( 'edit.php' ), 'Content' );
        $add( __( 'New post', 'nine-code' ), 'create content', admin_url( 'post-new.php' ), 'Content' );
    }
    if ( current_user_can( 'edit_pages' ) ) {
        $add( __( 'Pages', 'nine-code' ), 'content pages', admin_url( 'edit.php?post_type=page' ), 'Content' );
        $add( __( 'New page', 'nine-code' ), 'create page', admin_url( 'post-new.php?post_type=page' ), 'Content' );
    }
    if ( current_user_can( 'upload_files' ) ) { $add( __( 'Media', 'nine-code' ), 'files library', admin_url( 'upload.php' ), 'Content' ); }
    if ( current_user_can( 'moderate_comments' ) ) { $add( __( 'Comments', 'nine-code' ), 'discussion', admin_url( 'edit-comments.php' ), 'Content' ); }

    if ( current_user_can( 'manage_options' ) ) {
        $add( __( 'Nine Code', 'nine-code' ), 'theme control center', admin_url( 'admin.php?page=nine-code-ultra' ), '9 Code' );
        $add( __( 'Admin Workspace', 'nine-code' ), 'admin skin interface', admin_url( 'admin.php?page=nine-code-ultra-admin-workspace' ), '9 Code' );
        $add( __( 'Design', 'nine-code' ), 'site design', admin_url( 'admin.php?page=nine-code-ultra-design' ), '9 Code' );
        $add( __( 'Style Takeover', 'nine-code' ), 'design authority', admin_url( 'admin.php?page=nine-code-ultra-style-takeover' ), '9 Code' );
        $add( __( 'Dark Mode', 'nine-code' ), 'dark appearance', admin_url( 'admin.php?page=nine-code-ultra-dark-mode' ), '9 Code' );
        $add( __( 'Branding & Ownership', 'nine-code' ), 'client branding', admin_url( 'admin.php?page=nine-code-ultra-branding' ), '9 Code' );
        $add( __( 'Builders', 'nine-code' ), 'rendering builders', admin_url( 'admin.php?page=nine-code-ultra-builders' ), '9 Code' );
        $add( __( 'Doctor', 'nine-code' ), 'diagnostics repair', admin_url( 'admin.php?page=nine-code-ultra-doctor' ), '9 Code' );
        $add( __( 'System Health', 'nine-code' ), 'health diagnostics', admin_url( 'admin.php?page=nine-code-ultra-health' ), '9 Code' );
    }
    if ( current_user_can( 'edit_theme_options' ) ) { $add( __( 'Appearance', 'nine-code' ), 'menus themes', admin_url( 'themes.php' ), 'System' ); }
    if ( current_user_can( 'activate_plugins' ) ) { $add( __( 'Plugins', 'nine-code' ), 'extensions', admin_url( 'plugins.php' ), 'System' ); }
    if ( current_user_can( 'list_users' ) ) { $add( __( 'Users', 'nine-code' ), 'accounts', admin_url( 'users.php' ), 'System' ); }
    if ( current_user_can( 'manage_options' ) ) { $add( __( 'Settings', 'nine-code' ), 'application settings', admin_url( 'options-general.php' ), 'System' ); }

    return apply_filters( 'ncu_admin_workspace_commands', $commands );
}

add_action( 'admin_footer', 'ncu_admin_workspace_command_palette', 99 );
function ncu_admin_workspace_command_palette() {
    if ( ! ncu_admin_skin_is_active() ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['admin_skin_command_palette'] ) ) { return; }
    $commands = ncu_admin_workspace_commands();
    ?>
    <div id="ncu-command-palette" class="ncu-command-palette" hidden aria-hidden="true">
        <button class="ncu-command-palette__backdrop" type="button" data-ncu-command-close aria-label="<?php esc_attr_e( 'Close command palette', 'nine-code' ); ?>"></button>
        <div class="ncu-command-palette__dialog" role="dialog" aria-modal="true" aria-labelledby="ncu-command-title">
            <div class="ncu-command-palette__head">
                <span class="ncu-command-palette__mark">9C</span>
                <div><strong id="ncu-command-title"><?php esc_html_e( 'Command palette', 'nine-code' ); ?></strong><small><?php esc_html_e( 'Navigate the 9 Code workspace', 'nine-code' ); ?></small></div>
                <kbd>ESC</kbd>
            </div>
            <label class="ncu-command-palette__search"><span class="screen-reader-text"><?php esc_html_e( 'Search commands', 'nine-code' ); ?></span><input type="search" data-ncu-command-search autocomplete="off" placeholder="Search commands…"></label>
            <div class="ncu-command-palette__results" data-ncu-command-results>
                <?php $last_group = ''; foreach ( $commands as $command ) : $group = sanitize_text_field( $command['group'] ); if ( $group !== $last_group ) : $last_group = $group; ?><div class="ncu-command-group" data-ncu-command-group><?php echo esc_html( strtoupper( $group ) ); ?></div><?php endif; ?>
                    <a class="ncu-command-item" href="<?php echo esc_url( $command['url'] ); ?>" data-ncu-command-item data-search="<?php echo esc_attr( strtolower( $command['label'] . ' ' . $command['hint'] . ' ' . $command['group'] ) ); ?>"><span><strong><?php echo esc_html( $command['label'] ); ?></strong><small><?php echo esc_html( $command['hint'] ); ?></small></span><kbd>↵</kbd></a>
                <?php endforeach; ?>
                <div class="ncu-command-empty" data-ncu-command-empty hidden><?php esc_html_e( 'No matching command', 'nine-code' ); ?></div>
            </div>
            <div class="ncu-command-palette__foot"><span><kbd>↑</kbd><kbd>↓</kbd> navigate</span><span><kbd>↵</kbd> open</span><span><kbd>ESC</kbd> close</span></div>
        </div>
    </div>
    <?php
}

function ncu_admin_workspace_optional_css() {
    if ( ! ncu_admin_skin_is_active() ) { return ''; }
    $s = ncu_get_settings();
    $css = '';
    if ( ! empty( $s['admin_skin_hide_help_tabs'] ) ) { $css .= '#contextual-help-link-wrap{display:none!important;}'; }
    if ( ! empty( $s['admin_skin_hide_screen_options'] ) ) { $css .= '#screen-options-link-wrap{display:none!important;}'; }
    return $css;
}

add_action( 'admin_post_ncu_save_admin_workspace', 'ncu_save_admin_workspace' );
function ncu_save_admin_workspace() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to change the Admin Workspace.', 'nine-code' ) );
    }
    check_admin_referer( 'ncu_save_admin_workspace' );
    $raw = isset( $_POST['ncu'] ) && is_array( $_POST['ncu'] ) ? wp_unslash( $_POST['ncu'] ) : array();
    $keys = array(
        'admin_skin_enabled', 'admin_skin_menu_enabled', 'admin_skin_monochrome',
        'admin_skin_workspace_bar', 'admin_skin_command_palette', 'admin_skin_editor_chrome',
        'admin_editor_tools_drawer', 'admin_editor_high_contrast', 'admin_skin_enhance_tables', 'admin_skin_enhance_modals', 'admin_skin_hide_help_tabs',
        'admin_skin_hide_screen_options', 'admin_skin_mode', 'admin_skin_density',
        'admin_skin_radius', 'admin_skin_shadow',
    );
    $booleans = array(
        'admin_skin_enabled', 'admin_skin_menu_enabled', 'admin_skin_monochrome',
        'admin_skin_workspace_bar', 'admin_skin_command_palette', 'admin_skin_editor_chrome',
        'admin_editor_tools_drawer', 'admin_editor_high_contrast', 'admin_skin_enhance_tables', 'admin_skin_enhance_modals', 'admin_skin_hide_help_tabs',
        'admin_skin_hide_screen_options',
    );
    $result = ncu_save_settings_subset( $raw, $keys, $booleans );
    ncu_redirect_after_settings_save( 'nine-code-ultra-admin-workspace', $result, '9 Code Admin Workspace saved and verified.' );
}

add_action( 'admin_post_ncu_force_admin_skin', 'ncu_force_admin_skin' );
function ncu_force_admin_skin() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code' ) ); }
    check_admin_referer( 'ncu_force_admin_skin' );
    $s = ncu_get_settings();
    $s['admin_skin_enabled'] = 1;
    $s['admin_skin_menu_enabled'] = 1;
    $s['admin_skin_monochrome'] = 1;
    update_option( 'ncu_settings', ncu_sanitize_settings( $s ), false );
    if ( function_exists( 'ncu_notice_push' ) ) { ncu_notice_push( 'Full 9 Code Admin UI enabled.', 'success', 'admin-skin-forced' ); }
    wp_safe_redirect( admin_url( 'admin.php?page=nine-code-ultra-admin-workspace' ) );
    exit;
}

function ncu_admin_workspace_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $s = ncu_get_settings();
    $safe_url = add_query_arg( 'ncu_admin_skin', 'off', admin_url( 'admin.php?page=nine-code-ultra-admin-workspace' ) );
    ?>
    <div class="wrap ncu-admin">
        <?php ncu_admin_header( 'Admin UI / Skin', 'Ultron admin skin: five lightweight 9 Code workspace personalities over the full application administration.' ); ?>
        <section class="ncu-panel ncu-panel--padded"><h2>Admin UI status</h2><p><strong>Workspace skin:</strong> <?php echo ! empty( $s['admin_skin_enabled'] ) ? 'ON' : 'OFF'; ?> · <strong>Left menu skin:</strong> <?php echo ! empty( $s['admin_skin_menu_enabled'] ) ? 'ON' : 'OFF'; ?> · <strong>Selected:</strong> <?php echo esc_html( ncu_admin_skin_mode_label( $s['admin_skin_mode'] ) ); ?> · <strong>Monochrome lock:</strong> <?php echo ! empty( $s['admin_skin_monochrome'] ) ? 'ON' : 'OFF'; ?></p><p class="description">v3.3 fixes the hidden dependency that could make the Admin Workspace appear enabled while the separate Branding switch silently prevented it from loading. Admin UI / Skin is now authoritative.</p><?php if ( empty( $s['admin_skin_enabled'] ) || empty( $s['admin_skin_menu_enabled'] ) ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ncu_force_admin_skin"><?php wp_nonce_field( 'ncu_force_admin_skin' ); ?><button class="button button-primary">Enable full 9 Code Admin UI now</button></form><?php endif; ?></section>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="ncu_save_admin_workspace"><?php wp_nonce_field( 'ncu_save_admin_workspace' ); ?>
            <section class="ncu-panel ncu-panel--padded">
                <h2>9 Code Admin Skin</h2>
                <div class="ncu-checks">
                    <?php ncu_checkbox( 'admin_skin_enabled', 'Enable the 9 Code Admin Workspace skin', $s ); ?>
                    <?php ncu_checkbox( 'admin_skin_menu_enabled', 'Skin the full WordPress left admin menu as 9 Code', $s ); ?>
                    <?php ncu_checkbox( 'admin_skin_monochrome', 'Lock WordPress/admin interaction accents to the selected 9 Code monochrome skin', $s ); ?>
                    <?php ncu_checkbox( 'admin_skin_workspace_bar', 'Show compact workspace context bar', $s ); ?>
                    <?php ncu_checkbox( 'admin_skin_command_palette', 'Enable Command Palette (Ctrl/Cmd + K)', $s ); ?>
                    <?php ncu_checkbox( 'admin_skin_editor_chrome', 'Skin Block Editor chrome without altering page content', $s ); ?>
                    <?php ncu_checkbox( 'admin_editor_tools_drawer', 'Mobile/tablet Editor Tools hamburger — keep Save/Update visible and move nonessential toolbar/plugin actions into a compact non-modal popover', $s ); ?>
                    <?php ncu_checkbox( 'admin_editor_high_contrast', 'High-contrast editor chrome — stronger headings, labels, fields and focus states without restyling published content', $s ); ?>
                    <p class="description"><strong>Native editor protection:</strong> Post Content and metadata/meta boxes are never hidden or moved into a 9CODE full-screen overlay. Use the Editor Tools hamburger for 9CODE settings and shortcuts.</p>
                    <?php ncu_checkbox( 'admin_skin_enhance_tables', 'Enhance list tables and data screens', $s ); ?>
                    <?php ncu_checkbox( 'admin_skin_enhance_modals', 'Skin media and core modal surfaces', $s ); ?>
                </div>
                <p class="description">This is the master Admin Workspace switch. It no longer depends on the separate Branding setting. When enabled, the left WordPress menu, top chrome and supported admin surfaces receive the selected 9 Code skin while routes, permissions, plugin data and editor content remain untouched.</p>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Workspace appearance</h2>
                <div class="ncu-admin-skin-preview-grid" data-ncu-skin-previews>
                    <?php foreach ( ncu_admin_skin_mode_choices() as $skin_key => $skin ) : ?>
                        <label class="ncu-admin-skin-preview <?php echo $skin_key === $s['admin_skin_mode'] ? 'is-selected' : ''; ?>" data-ncu-skin-card="<?php echo esc_attr( $skin_key ); ?>">
                            <input type="radio" name="ncu[admin_skin_mode]" value="<?php echo esc_attr( $skin_key ); ?>" <?php checked( $s['admin_skin_mode'], $skin_key ); ?>>
                            <span class="ncu-admin-skin-preview__frame is-<?php echo esc_attr( $skin_key ); ?>"><i></i><b></b><em></em><span class="ncu-admin-skin-preview__detail"></span></span>
                            <strong><?php echo esc_html( $skin['label'] ); ?></strong>
                            <small><?php echo esc_html( $skin['description'] ); ?></small>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description"><strong>Live preview:</strong> selecting a skin previews its shell on this screen. Save Changes to make it site-wide for administrators.</p>
                <div class="ncu-grid">
                    <label><span>Density</span><select name="ncu[admin_skin_density]"><option value="compact" <?php selected( $s['admin_skin_density'], 'compact' ); ?>>Compact IDE</option><option value="comfortable" <?php selected( $s['admin_skin_density'], 'comfortable' ); ?>>Comfortable</option></select></label>
                    <label><span>Panel corner radius</span><input type="number" min="4" max="18" name="ncu[admin_skin_radius]" value="<?php echo esc_attr( $s['admin_skin_radius'] ); ?>"></label>
                    <label><span>Panel shadow</span><select name="ncu[admin_skin_shadow]"><option value="flat" <?php selected( $s['admin_skin_shadow'], 'flat' ); ?>>Flat / outlined</option><option value="soft" <?php selected( $s['admin_skin_shadow'], 'soft' ); ?>>Soft</option><option value="deep" <?php selected( $s['admin_skin_shadow'], 'deep' ); ?>>Elevated</option></select></label>
                </div>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Advanced chrome</h2>
                <div class="ncu-checks">
                    <?php ncu_checkbox( 'admin_skin_hide_help_tabs', 'Hide Help tab in normal admin screens', $s ); ?>
                    <?php ncu_checkbox( 'admin_skin_hide_screen_options', 'Hide Screen Options tab in normal admin screens', $s ); ?>
                </div>
                <p class="description">These are OFF by default because Help and Screen Options are useful administrative controls. Hiding them changes only visibility, not capability.</p>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Compatibility & recovery boundary</h2>
                <p>The Customizer, iframe requests, Network Admin and User Admin stay outside the skin by default. Specialist plugins can opt individual screens out with the <code>ncu_admin_skin_screen_allowed</code> filter.</p>
                <p>If a third-party plugin ever clashes visually with the skin, append <code>?ncu_admin_skin=off</code> to that wp-admin URL. This disables the skin for that request without deactivating Core or changing saved settings.</p>
                <p><a class="button" href="<?php echo esc_url( $safe_url ); ?>">Preview this screen with skin bypassed</a></p>
            </section>
            <p class="submit"><button class="button button-primary button-hero">Save Admin Workspace</button></p>
        </form>
    </div>
    <?php
}
