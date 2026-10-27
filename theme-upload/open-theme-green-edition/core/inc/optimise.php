<?php
/**
 * Whole-site optimisation.
 *
 * Automatic by default: a site that has never chosen a level runs the Balanced
 * settings and a daily maintenance job, with nothing to configure. Safe,
 * Balanced, Full and an advanced per-setting form remain for people who want
 * control. Every setting is reversible. Nothing here deletes content, settings,
 * users or media; maintenance removes only expired transients (cache that
 * WordPress rebuilds) and, if switched on, defragments database tables.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const NCU_OSCH_OPTIMISE_OPTION = 'ncu_osch_optimise';
const NCU_OSCH_OPTIMISE_LOG    = 'ncu_osch_optimise_log';
const NCU_OSCH_OPTIMISE_CRON   = 'ncu_osch_daily_maintenance';

/** Every setting a level can change, with the words shown in Advanced. */
function ncu_osch_optimise_fields() {
    return array(
        'emoji'           => array( 'type' => 'bool', 'label' => 'Remove emoji scripts', 'help' => 'Browsers show emoji on their own. Saves one script on every page.' ),
        'head_cleanup'    => array( 'type' => 'bool', 'label' => 'Tidy the page head', 'help' => 'Removes the WordPress version tag and old editor links (RSD, Windows Live Writer, shortlink).' ),
        'embed_script'    => array( 'type' => 'bool', 'label' => 'Remove the embed script on public pages', 'help' => 'Only used when other sites embed your posts in an iframe.' ),
        'dashicons_guest' => array( 'type' => 'bool', 'label' => 'Skip admin icons for visitors', 'help' => 'Visitors who are not logged in do not need the admin icon font.' ),
        'front_heartbeat' => array( 'type' => 'bool', 'label' => 'Turn off the heartbeat on public pages', 'help' => 'The heartbeat polls the server every few seconds. Public pages do not need it.' ),
        'oembed_links'    => array( 'type' => 'bool', 'label' => 'Remove oEmbed discovery links', 'help' => 'Other sites can still embed your posts by pasting the link.' ),
        'rest_link'       => array( 'type' => 'bool', 'label' => 'Remove the REST API link from the page head', 'help' => 'The API keeps working; only the advertisement in the head goes.' ),
        'quiet_screens'   => array( 'type' => 'bool', 'label' => 'Skip custom-field styles on Open Theme screens', 'help' => 'Dashboard, Dashboard & Menus, Quick Actions, Export & Import, Backup and Optimise Site do not show custom fields, so their field styles and scripts are not loaded there.' ),
        'command_palette' => array( 'type' => 'bool', 'label' => 'Keep the Ctrl+K command palette on admin screens', 'help' => 'WordPress loads its search palette on every admin screen. Off saves about 2 MB of script per page.' ),
        'self_pingbacks'  => array( 'type' => 'bool', 'label' => 'Stop pinging your own site', 'help' => 'Linking to your own post no longer creates a pingback comment.' ),
        'revisions'       => array( 'type' => 'int', 'min' => 0, 'max' => 100, 'label' => 'Revisions kept per item', 'help' => 'Only affects new saves; existing revisions are never deleted. 0 leaves the WordPress default.' ),
        'autosave'        => array( 'type' => 'int', 'min' => 60, 'max' => 600, 'label' => 'Autosave every (seconds)', 'help' => 'WordPress default is 60.' ),
        'heartbeat_admin' => array( 'type' => 'int', 'min' => 15, 'max' => 300, 'label' => 'Editor heartbeat every (seconds)', 'help' => 'Keeps edit locks and login checks working. WordPress default is 15 in the editor.' ),
    );
}

function ncu_osch_optimise_levels() {
    $base = array( 'emoji' => true, 'head_cleanup' => true, 'embed_script' => true, 'dashicons_guest' => true, 'self_pingbacks' => true, 'command_palette' => false, 'quiet_screens' => true );
    return array(
        'auto'     => array(
            'label'    => 'Automatic',
            'summary'  => 'Recommended. Uses the Balanced settings and tidies the site every day. Nothing to set up.',
            'settings' => $base + array( 'front_heartbeat' => true, 'oembed_links' => true, 'rest_link' => false, 'revisions' => 10, 'autosave' => 120, 'heartbeat_admin' => 60 ),
        ),
        'safe'     => array(
            'label'    => 'Safe',
            'summary'  => 'No visible change. Keeps 25 revisions and the usual autosave; removes unused scripts and head tags.',
            'settings' => $base + array( 'front_heartbeat' => false, 'oembed_links' => false, 'rest_link' => false, 'revisions' => 25, 'autosave' => 60, 'heartbeat_admin' => 30 ),
        ),
        'balanced' => array(
            'label'    => 'Balanced',
            'summary'  => 'Keeps 10 revisions, autosaves every 2 minutes and turns off the heartbeat on public pages.',
            'settings' => $base + array( 'front_heartbeat' => true, 'oembed_links' => true, 'rest_link' => false, 'revisions' => 10, 'autosave' => 120, 'heartbeat_admin' => 60 ),
        ),
        'full'     => array(
            'label'    => 'Full',
            'summary'  => 'Keeps 5 revisions, autosaves every 3 minutes, slows the editor heartbeat to 2 minutes and removes the REST link from the head.',
            'settings' => $base + array( 'front_heartbeat' => true, 'oembed_links' => true, 'rest_link' => true, 'revisions' => 5, 'autosave' => 180, 'heartbeat_admin' => 120 ),
        ),
    );
}

/** Saved state with defaults. A site that never chose runs Automatic. */
function ncu_osch_optimise_state( $refresh = false ) {
    static $state = null;
    if ( null === $state || $refresh ) {
        $saved = get_option( NCU_OSCH_OPTIMISE_OPTION, array() );
        $state = wp_parse_args( is_array( $saved ) ? $saved : array(), array(
            'level'       => 'auto',
            'custom'      => array(),
            'maintenance' => true,
            'db_optimise' => false,
            'changed'     => 0,
            'by'          => '',
        ) );
    }
    return $state;
}

function ncu_osch_optimise_level() {
    $state = ncu_osch_optimise_state();
    return (string) $state['level'];
}

/** The settings in force for this request; empty when optimisation is off. */
function ncu_osch_optimise_settings() {
    static $settings = null;
    if ( null !== $settings ) { return $settings; }
    $state  = ncu_osch_optimise_state();
    $levels = ncu_osch_optimise_levels();
    if ( 'custom' === $state['level'] ) {
        $settings = ncu_osch_optimise_sanitize_settings( (array) $state['custom'] );
    } elseif ( isset( $levels[ $state['level'] ] ) ) {
        $settings = $levels[ $state['level'] ]['settings'];
    } else {
        $settings = array();
    }
    return $settings;
}

function ncu_osch_optimise_setting( $key, $default = false ) {
    $settings = ncu_osch_optimise_settings();
    return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

function ncu_osch_optimise_sanitize_settings( $input ) {
    $clean = array();
    foreach ( ncu_osch_optimise_fields() as $key => $field ) {
        if ( 'bool' === $field['type'] ) {
            $clean[ $key ] = ! empty( $input[ $key ] );
        } else {
            $value = isset( $input[ $key ] ) ? (int) $input[ $key ] : $field['min'];
            $clean[ $key ] = max( $field['min'], min( $field['max'], $value ) );
        }
    }
    return $clean;
}

/* ---------------------------------------------------------------- Effects */

add_action( 'after_setup_theme', 'ncu_osch_optimise_head', 20 );
function ncu_osch_optimise_head() {
    if ( ncu_osch_optimise_setting( 'emoji' ) ) {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
    }
    if ( ncu_osch_optimise_setting( 'head_cleanup' ) ) {
        remove_action( 'wp_head', 'wp_generator' );
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
        remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    }
    if ( ncu_osch_optimise_setting( 'oembed_links' ) ) { remove_action( 'wp_head', 'wp_oembed_add_discovery_links' ); }
    if ( ncu_osch_optimise_setting( 'rest_link' ) ) { remove_action( 'wp_head', 'rest_output_link_wp_head' ); }
}

/* Open Theme's own screens never show custom fields, so Secure Custom Fields' global styles and scripts are skipped there. */
add_action( 'admin_enqueue_scripts', 'ncu_osch_optimise_quiet_screens', 9999 );
function ncu_osch_optimise_quiet_screens() {
    if ( ! ncu_osch_optimise_setting( 'quiet_screens' ) ) { return; }
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
    $own  = array( 'open-scholar-workspace', 'ninecode-quick-actions', 'osch-export-import', 'osch-backup-restore', 'nine-backup-manager', 'open-scholar-optimise', 'nine-code-ultra' );
    $home = isset( $GLOBALS['pagenow'] ) && 'index.php' === $GLOBALS['pagenow'];
    if ( ! $home && ! in_array( $page, $own, true ) ) { return; }
    foreach ( array( 'acf-global' ) as $handle ) { wp_dequeue_style( $handle ); }
    foreach ( array( 'acf-escaped-html-notice', 'acf', 'scf-commands-admin' ) as $handle ) { wp_dequeue_script( $handle ); }
}

/* The admin command palette is WordPress core's Ctrl+K search. Off means it is not loaded on admin screens. */
add_action( 'after_setup_theme', 'ncu_osch_optimise_admin_palette', 20 );
function ncu_osch_optimise_admin_palette() {
    if ( ncu_osch_optimise_setting( 'command_palette' ) ) { return; }
    remove_action( 'admin_enqueue_scripts', 'wp_enqueue_command_palette_assets' );
    /* Add-ons that extend the palette only make sense while it is loaded. */
    add_action( 'admin_print_scripts', 'ncu_osch_optimise_dequeue_palette_addons', 999 );
}

function ncu_osch_optimise_dequeue_palette_addons() {
    foreach ( array( 'wp-core-commands', 'wp-commands', 'scf-commands-admin' ) as $handle ) { wp_dequeue_script( $handle ); }
}

add_action( 'wp_enqueue_scripts', 'ncu_osch_optimise_front_assets', 100 );
function ncu_osch_optimise_front_assets() {
    if ( ncu_osch_optimise_setting( 'embed_script' ) ) { wp_dequeue_script( 'wp-embed' ); }
    if ( ncu_osch_optimise_setting( 'dashicons_guest' ) && ! is_user_logged_in() && ! ncu_osch_optimise_style_needed( 'dashicons' ) ) { wp_dequeue_style( 'dashicons' ); }
    if ( ncu_osch_optimise_setting( 'front_heartbeat' ) ) { wp_deregister_script( 'heartbeat' ); }
}

/**
 * True when a queued stylesheet still needs $handle, either directly or as a dependency.
 * WordPress re-adds dependencies when printing, so dequeuing alone does not remove them.
 */
function ncu_osch_optimise_style_needed( $handle ) {
    global $wp_styles;
    if ( ! $wp_styles instanceof WP_Styles ) { return true; }
    foreach ( (array) $wp_styles->queue as $queued ) {
        if ( $queued === $handle ) { return true; }
        $deps = isset( $wp_styles->registered[ $queued ]->deps ) ? (array) $wp_styles->registered[ $queued ]->deps : array();
        if ( in_array( $handle, $deps, true ) ) { return true; }
    }
    return false;
}

add_filter( 'wp_revisions_to_keep', 'ncu_osch_optimise_revisions', 10, 2 );
function ncu_osch_optimise_revisions( $num, $post ) {
    $limit = (int) ncu_osch_optimise_setting( 'revisions', 0 );
    if ( $limit < 1 ) { return $num; }
    /* A site that switched revisions off (0) keeps them off; unlimited (-1) gets the limit. */
    if ( is_int( $num ) && 0 === $num ) { return $num; }
    return ( is_int( $num ) && $num > 0 ) ? min( $num, $limit ) : $limit;
}

add_filter( 'autosave_interval', 'ncu_osch_optimise_autosave' );
function ncu_osch_optimise_autosave( $seconds ) {
    $wanted = (int) ncu_osch_optimise_setting( 'autosave', 0 );
    return $wanted > 0 ? $wanted : $seconds;
}

add_filter( 'heartbeat_settings', 'ncu_osch_optimise_heartbeat' );
function ncu_osch_optimise_heartbeat( $settings ) {
    $wanted = (int) ncu_osch_optimise_setting( 'heartbeat_admin', 0 );
    if ( $wanted > 0 ) { $settings['interval'] = $wanted; }
    return $settings;
}

add_action( 'pre_ping', 'ncu_osch_optimise_no_self_ping' );
function ncu_osch_optimise_no_self_ping( &$links ) {
    if ( ! ncu_osch_optimise_setting( 'self_pingbacks' ) ) { return; }
    $home = home_url();
    foreach ( $links as $index => $link ) {
        if ( 0 === strpos( $link, $home ) ) { unset( $links[ $index ] ); }
    }
}

/* ------------------------------------------------------------ Maintenance */

/** Expired transients only. Live ones stay, so no import, preview or login state is lost. */
function ncu_osch_optimise_clear_expired_transients() {
    global $wpdb;
    $count = 0;
    foreach ( array( '_transient_timeout_' => 'delete_transient', '_site_transient_timeout_' => 'delete_site_transient' ) as $prefix => $delete ) {
        if ( '_site_transient_timeout_' === $prefix && is_multisite() ) { continue; } // Network transients live in sitemeta.
        $expired = $wpdb->get_col( $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT 5000",
            $wpdb->esc_like( $prefix ) . '%',
            time()
        ) );
        foreach ( $expired as $timeout_name ) {
            $delete( substr( $timeout_name, strlen( $prefix ) ) );
        }
        $count += count( $expired );
    }
    return $count;
}

/** Defragment WordPress's own tables. Off unless switched on in Advanced; at most once a week. */
function ncu_osch_optimise_tables( $force = false ) {
    global $wpdb;
    $last = (int) get_option( 'ncu_osch_optimise_tables_at', 0 );
    if ( ! $force && $last > time() - WEEK_IN_SECONDS ) { return 0; }
    $done = 0;
    foreach ( $wpdb->tables( 'blog' ) as $table ) {
        $wpdb->query( "OPTIMIZE TABLE `" . esc_sql( $table ) . "`" ); // phpcs:ignore WordPress.DB -- table names come from $wpdb.
        $done++;
    }
    update_option( 'ncu_osch_optimise_tables_at', time(), false );
    return $done;
}

function ncu_osch_optimise_maintenance( $trigger = 'daily' ) {
    $state  = ncu_osch_optimise_state();
    $result = array(
        'time'       => time(),
        'trigger'    => $trigger,
        'transients' => ncu_osch_optimise_clear_expired_transients(),
        'tables'     => ! empty( $state['db_optimise'] ) ? ncu_osch_optimise_tables( 'manual' === $trigger ) : 0,
    );
    if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) { wp_cache_flush_group( 'ncu_osch' ); }
    $log = get_option( NCU_OSCH_OPTIMISE_LOG, array() );
    $log = is_array( $log ) ? $log : array();
    array_unshift( $log, $result );
    update_option( NCU_OSCH_OPTIMISE_LOG, array_slice( $log, 0, 10 ), false );
    return $result;
}

add_action( NCU_OSCH_OPTIMISE_CRON, 'ncu_osch_optimise_cron' );
function ncu_osch_optimise_cron() {
    $state = ncu_osch_optimise_state();
    if ( 'off' === $state['level'] || empty( $state['maintenance'] ) ) { return; }
    ncu_osch_optimise_maintenance( 'daily' );
}

/** Keep the daily job in step with the settings. Checked in wp-admin only, so public pages pay nothing. */
add_action( 'admin_init', 'ncu_osch_optimise_schedule' );
function ncu_osch_optimise_schedule() {
    $state  = ncu_osch_optimise_state();
    $wanted = 'off' !== $state['level'] && ! empty( $state['maintenance'] );
    $next   = wp_next_scheduled( NCU_OSCH_OPTIMISE_CRON );
    if ( $wanted && ! $next ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', NCU_OSCH_OPTIMISE_CRON );
    } elseif ( ! $wanted && $next ) {
        wp_clear_scheduled_hook( NCU_OSCH_OPTIMISE_CRON );
    }
}

/* The job belongs to this theme; switching away stops it. */
add_action( 'switch_theme', 'ncu_osch_optimise_unschedule' );
function ncu_osch_optimise_unschedule() {
    wp_clear_scheduled_hook( NCU_OSCH_OPTIMISE_CRON );
}

/* ------------------------------------------------------------ Site health */

/** Plain-language checks. Each row: [status good|warn, title, explanation]. */
function ncu_osch_optimise_health() {
    global $wpdb;
    $rows = array();

    $autoload_values = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : array( 'yes' );
    $placeholders    = implode( ',', array_fill( 0, count( $autoload_values ), '%s' ) );
    $autoload        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ($placeholders)", $autoload_values ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders -- placeholders built above.
    $rows[] = $autoload < 800 * KB_IN_BYTES
        ? array( 'good', 'Settings loaded on every page: ' . size_format( $autoload ), 'Small enough. WordPress reads these on every request.' )
        : array( 'warn', 'Settings loaded on every page: ' . size_format( $autoload ), 'Large. A plugin may be storing big data in always-loaded options. Removing unused plugins usually fixes this.' );

    $expired = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
    $rows[] = $expired < 200
        ? array( 'good', 'Expired cache entries: ' . number_format_i18n( $expired ), 'Daily maintenance removes these.' )
        : array( 'warn', 'Expired cache entries: ' . number_format_i18n( $expired ), 'Press "Run maintenance now" to remove them.' );

    $revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
    $rows[] = array( 'good', 'Saved revisions: ' . number_format_i18n( $revisions ), 'Kept as your editing history. Optimisation never deletes them; it only limits how many new ones are kept.' );

    $rows[] = wp_using_ext_object_cache()
        ? array( 'good', 'Object cache: on', 'Database results are kept in memory between requests.' )
        : array( 'warn', 'Object cache: off', 'Ask your host to turn on Redis or Memcached and add its object-cache plugin. This is the biggest speed-up for the admin.' );

    $rows[] = ( defined( 'WP_CACHE' ) && WP_CACHE )
        ? array( 'good', 'Page cache: on', 'Public pages are served from a cache.' )
        : array( 'warn', 'Page cache: not detected', 'A page-cache plugin or your host\'s cache makes public pages much faster for visitors.' );

    $rows[] = version_compare( PHP_VERSION, '8.1', '>=' )
        ? array( 'good', 'PHP ' . PHP_VERSION, 'Current and fast.' )
        : array( 'warn', 'PHP ' . PHP_VERSION, 'Ask your host for PHP 8.2 or newer; it is noticeably faster.' );

    $plugins = count( (array) get_option( 'active_plugins', array() ) );
    $rows[] = $plugins <= 30
        ? array( 'good', 'Active plugins: ' . $plugins, 'Each plugin adds work to every page. Deactivate the ones you no longer use.' )
        : array( 'warn', 'Active plugins: ' . $plugins, 'Many plugins are active. Each adds work to every page; deactivate the ones you no longer use.' );

    return $rows;
}

/* ------------------------------------------------------------ Admin screen */

add_action( 'admin_post_ncu_osch_optimise_run', 'ncu_osch_optimise_run' );
function ncu_osch_optimise_run() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You are not allowed to optimise this site.', 'nine-code' ) ); }
    check_admin_referer( 'ncu_osch_optimise_run' );

    $mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
    $state  = ncu_osch_optimise_state();
    $levels = ncu_osch_optimise_levels();
    $notice = $mode;
    $result = null;

    if ( 'restore' === $mode ) {
        $state['level'] = 'off';
    } elseif ( 'maintenance' === $mode ) {
        $result = ncu_osch_optimise_maintenance( 'manual' );
    } elseif ( 'advanced' === $mode ) {
        $state['level']       = 'custom';
        $state['custom']      = ncu_osch_optimise_sanitize_settings( isset( $_POST['opt'] ) ? (array) wp_unslash( $_POST['opt'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field.
        $state['maintenance'] = ! empty( $_POST['maintenance'] );
        $state['db_optimise'] = ! empty( $_POST['db_optimise'] );
    } elseif ( isset( $levels[ $mode ] ) ) {
        $state['level'] = $mode;
        if ( 'auto' === $mode ) { $state['maintenance'] = true; }
        $result = ncu_osch_optimise_maintenance( 'level' );
    } else {
        $notice = 'error';
    }

    if ( 'error' !== $notice && 'maintenance' !== $mode ) {
        $state['changed'] = time();
        $state['by']      = get_current_user_id();
        update_option( NCU_OSCH_OPTIMISE_OPTION, $state, false );
        ncu_osch_optimise_state( true );
        ncu_osch_optimise_schedule();
    }

    wp_safe_redirect( add_query_arg( array(
        'page'          => 'open-scholar-optimise',
        'ncu_optimised' => $notice,
        'cleaned'       => $result ? (int) $result['transients'] : 0,
        'tables'        => $result ? (int) $result['tables'] : 0,
    ), admin_url( 'admin.php' ) ) );
    exit;
}

add_action( 'admin_menu', 'ncu_osch_optimise_menu', 31 );
function ncu_osch_optimise_menu() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    add_submenu_page( 'nine-code-ultra', __( 'Optimise Site', 'nine-code' ), __( 'Optimise Site', 'nine-code' ), 'manage_options', 'open-scholar-optimise', 'ncu_osch_optimise_page' );
}

function ncu_osch_optimise_form_open() {
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'ncu_osch_optimise_run' );
    echo '<input type="hidden" name="action" value="ncu_osch_optimise_run">';
}

function ncu_osch_optimise_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $state    = ncu_osch_optimise_state( true );
    $current  = $state['level'];
    $levels   = ncu_osch_optimise_levels();
    $fields   = ncu_osch_optimise_fields();
    $values   = 'custom' === $current ? ncu_osch_optimise_sanitize_settings( (array) $state['custom'] ) : ( isset( $levels[ $current ] ) ? $levels[ $current ]['settings'] : $levels['auto']['settings'] );
    $log      = get_option( NCU_OSCH_OPTIMISE_LOG, array() );
    $next     = wp_next_scheduled( NCU_OSCH_OPTIMISE_CRON );
    $format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
    $notice   = isset( $_GET['ncu_optimised'] ) ? sanitize_key( wp_unslash( $_GET['ncu_optimised'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
    $cleaned  = isset( $_GET['cleaned'] ) ? absint( $_GET['cleaned'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
    $tables   = isset( $_GET['tables'] ) ? absint( $_GET['tables'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
    $names    = array( 'off' => 'Off (WordPress defaults)', 'custom' => 'Advanced (your own settings)' );
    foreach ( $levels as $key => $level ) { $names[ $key ] = $level['label']; }
    ?>
    <div class="wrap ncu-admin osch-optimise" data-ninecode-settings-root>
        <?php ncu_admin_header( 'Optimise Site', 'Optimisation runs on its own. Every setting is reversible, and no content, user or media is ever deleted.' ); ?>

        <?php if ( 'error' === $notice ) : ?>
            <div class="notice notice-error"><p>That choice does not exist. Nothing was changed.</p></div>
        <?php elseif ( 'restore' === $notice ) : ?>
            <div class="notice notice-success"><p>Optimisation is off. WordPress defaults are back, and the daily maintenance has stopped.</p></div>
        <?php elseif ( 'advanced' === $notice ) : ?>
            <div class="notice notice-success"><p>Your advanced settings are saved and in use.</p></div>
        <?php elseif ( $notice ) : ?>
            <div class="notice notice-success"><p><?php
                echo esc_html( 'maintenance' === $notice ? 'Maintenance finished.' : 'Now using: ' . ( isset( $names[ $notice ] ) ? $names[ $notice ] : $notice ) . '.' );
                echo ' ' . esc_html( sprintf( '%d expired cache entries removed.', $cleaned ) );
                if ( $tables ) { echo ' ' . esc_html( sprintf( '%d database tables tidied.', $tables ) ); }
            ?></p></div>
        <?php endif; ?>

        <section class="osch-settings-panel osch-opt-status<?php echo 'off' === $current ? ' is-off' : ''; ?>" aria-labelledby="osch-opt-status">
            <h2 id="osch-opt-status"><span class="osch-step">1/5</span> Status</h2>
            <p><strong><?php echo esc_html( 'off' === $current ? 'Optimisation is off.' : 'Optimisation is on: ' . $names[ $current ] . '.' ); ?></strong>
            <?php if ( 'off' !== $current && ! empty( $state['maintenance'] ) && $next ) : ?>
                Next daily maintenance: <?php echo esc_html( wp_date( $format, $next ) ); ?>.
            <?php elseif ( 'off' !== $current ) : ?>
                Daily maintenance is switched off in Advanced.
            <?php endif; ?></p>
            <div class="osch-opt-actions">
                <?php if ( 'auto' !== $current ) : ncu_osch_optimise_form_open(); ?>
                    <button type="submit" name="mode" value="auto" class="button button-primary">Turn on automatic optimisation</button></form>
                <?php endif; ?>
                <?php ncu_osch_optimise_form_open(); ?>
                    <button type="submit" name="mode" value="maintenance" class="button<?php echo 'auto' === $current ? ' button-primary' : ''; ?>">Run maintenance now</button></form>
            </div>
        </section>

        <section class="osch-settings-panel" aria-labelledby="osch-opt-levels">
            <h2 id="osch-opt-levels"><span class="osch-step">2/5</span> Choose a level</h2>
            <p class="description">Automatic suits almost every site. The other levels are there if you want a lighter or stronger touch.</p>
            <div class="osch-opt-levels">
                <?php foreach ( $levels as $key => $level ) : ?>
                    <div class="osch-opt-level<?php echo $current === $key ? ' is-current' : ''; ?>">
                        <strong><?php echo esc_html( $level['label'] ); ?><?php echo $current === $key ? ' <em>· in use</em>' : ''; ?></strong>
                        <span class="description"><?php echo esc_html( $level['summary'] ); ?></span>
                        <?php if ( $current !== $key ) : ncu_osch_optimise_form_open(); ?>
                            <button type="submit" name="mode" value="<?php echo esc_attr( $key ); ?>" class="button">Use <?php echo esc_html( strtolower( $level['label'] ) ); ?></button></form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <details class="osch-settings-panel osch-opt-advanced"<?php echo 'custom' === $current ? ' open' : ''; ?>>
            <summary><h2><span class="osch-step">3/5</span> Advanced controls</h2></summary>
            <p class="description">Starts from the level in use. Saving switches the site to your own settings.</p>
            <?php ncu_osch_optimise_form_open(); ?>
                <div class="osch-opt-fields">
                    <?php foreach ( $fields as $key => $field ) : ?>
                        <?php if ( 'bool' === $field['type'] ) : ?>
                            <label class="osch-opt-check"><input type="checkbox" name="opt[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $values[ $key ] ) ); ?>> <span><b><?php echo esc_html( $field['label'] ); ?></b><i><?php echo esc_html( $field['help'] ); ?></i></span></label>
                        <?php else : ?>
                            <label class="osch-opt-number"><b><?php echo esc_html( $field['label'] ); ?></b><input type="number" name="opt[<?php echo esc_attr( $key ); ?>]" min="<?php echo esc_attr( $field['min'] ); ?>" max="<?php echo esc_attr( $field['max'] ); ?>" value="<?php echo esc_attr( isset( $values[ $key ] ) ? $values[ $key ] : $field['min'] ); ?>"><i><?php echo esc_html( $field['help'] ); ?></i></label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label class="osch-opt-check"><input type="checkbox" name="maintenance" value="1" <?php checked( ! empty( $state['maintenance'] ) ); ?>> <span><b>Daily maintenance</b><i>Removes expired cache entries once a day.</i></span></label>
                    <label class="osch-opt-check"><input type="checkbox" name="db_optimise" value="1" <?php checked( ! empty( $state['db_optimise'] ) ); ?>> <span><b>Weekly database tidy</b><i>Defragments WordPress tables once a week. Can take a minute on very large sites; best left off on busy shared hosting.</i></span></label>
                </div>
                <button type="submit" name="mode" value="advanced" class="button button-primary">Save advanced settings</button>
            </form>
        </details>

        <section class="osch-settings-panel" aria-labelledby="osch-opt-health">
            <h2 id="osch-opt-health"><span class="osch-step">4/5</span> Site health</h2>
            <ul class="osch-opt-health">
                <?php foreach ( ncu_osch_optimise_health() as $row ) : ?>
                    <li class="is-<?php echo esc_attr( $row[0] ); ?>"><span class="osch-opt-dot" aria-hidden="true"></span><span><b><?php echo esc_html( $row[1] ); ?></b><i><?php echo esc_html( $row[2] ); ?></i></span></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="osch-settings-panel" aria-labelledby="osch-opt-log">
            <h2 id="osch-opt-log"><span class="osch-step">5/5</span> Maintenance history</h2>
            <?php if ( empty( $log ) || ! is_array( $log ) ) : ?>
                <p class="description">No maintenance has run yet.</p>
            <?php else : ?>
                <ul class="osch-opt-history">
                    <?php foreach ( $log as $entry ) : ?>
                        <li><b><?php echo esc_html( wp_date( $format, (int) $entry['time'] ) ); ?></b> · <?php echo esc_html( 'manual' === $entry['trigger'] ? 'by hand' : ( 'level' === $entry['trigger'] ? 'level changed' : 'automatic' ) ); ?> · <?php echo esc_html( sprintf( '%d expired cache entries removed', (int) $entry['transients'] ) ); ?><?php echo ! empty( $entry['tables'] ) ? esc_html( sprintf( ', %d tables tidied', (int) $entry['tables'] ) ) : ''; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ( 'off' !== $current ) : ncu_osch_optimise_form_open(); ?>
                <button type="submit" name="mode" value="restore" class="button">Turn optimisation off (WordPress defaults)</button></form>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
