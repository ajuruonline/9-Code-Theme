<?php
/**
 * Whole-site optimisation. One button applies a level of reversible settings.
 * Nothing here deletes content, settings, users or media. The only deletion is
 * expired transients, which are cache that WordPress rebuilds when needed.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const NCU_OSCH_OPTIMISE_OPTION = 'ncu_osch_optimise';

/** Levels are cumulative in meaning but each row lists every value it sets. */
function ncu_osch_optimise_levels() {
    return array(
        'safe'     => array(
            'label'            => 'Safe',
            'summary'          => 'No visible change. Keeps 10 revisions, autosaves every minute, and keeps the editor heartbeat at 30 seconds.',
            'revisions'        => 10,
            'autosave'         => 60,
            'heartbeat_admin'  => 30,
            'heartbeat_front'  => true,
            'oembed_discovery' => false,
            'rest_link'        => false,
            'dashicons_guest'  => true,
        ),
        'balanced' => array(
            'label'            => 'Balanced',
            'summary'          => 'Recommended. Keeps 5 revisions, autosaves every 2 minutes, and turns off the heartbeat on public pages. Removes oEmbed discovery links.',
            'revisions'        => 5,
            'autosave'         => 120,
            'heartbeat_admin'  => 60,
            'heartbeat_front'  => false,
            'oembed_discovery' => true,
            'rest_link'        => false,
            'dashicons_guest'  => true,
        ),
        'full'     => array(
            'label'            => 'Full',
            'summary'          => 'Keeps 3 revisions, autosaves every 3 minutes, and slows the editor heartbeat to 2 minutes. Also removes the REST API discovery link from the page head.',
            'revisions'        => 3,
            'autosave'         => 180,
            'heartbeat_admin'  => 120,
            'heartbeat_front'  => false,
            'oembed_discovery' => true,
            'rest_link'        => true,
            'dashicons_guest'  => true,
        ),
    );
}

/** The level in force for this request. 'off' when optimisation was restored. */
function ncu_osch_optimise_level() {
    static $level = null;
    if ( null === $level ) {
        $state = get_option( NCU_OSCH_OPTIMISE_OPTION, array() );
        $level = is_array( $state ) && ! empty( $state['level'] ) ? (string) $state['level'] : 'off';
    }
    return $level;
}

/** One setting for the active level, or the WordPress default when optimisation is off. */
function ncu_osch_optimise_setting( $key, $default ) {
    $levels = ncu_osch_optimise_levels();
    $level  = ncu_osch_optimise_level();
    return isset( $levels[ $level ][ $key ] ) ? $levels[ $level ][ $key ] : $default;
}

/* Always on, whenever a level is active: noise that no visitor or editor uses. */
add_action( 'after_setup_theme', 'ncu_osch_optimise_head', 20 );
function ncu_osch_optimise_head() {
    if ( 'off' === ncu_osch_optimise_level() ) { return; }
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_action( 'wp_head', 'wp_generator' );
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    if ( ! ncu_osch_optimise_setting( 'oembed_discovery', false ) ) { return; }
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    if ( ncu_osch_optimise_setting( 'rest_link', false ) ) { remove_action( 'wp_head', 'rest_output_link_wp_head' ); }
}

add_action( 'wp_enqueue_scripts', 'ncu_osch_optimise_front_assets', 100 );
function ncu_osch_optimise_front_assets() {
    if ( 'off' === ncu_osch_optimise_level() ) { return; }
    /* The embed script only matters when a visitor pastes an embed into a post, which WordPress handles on its own. */
    wp_dequeue_script( 'wp-embed' );
    if ( ! is_user_logged_in() && ncu_osch_optimise_setting( 'dashicons_guest', false ) ) {
        wp_dequeue_style( 'dashicons' );
    }
    if ( ! ncu_osch_optimise_setting( 'heartbeat_front', true ) && ! is_admin() ) {
        wp_deregister_script( 'heartbeat' );
    }
}

add_filter( 'wp_revisions_to_keep', 'ncu_osch_optimise_revisions', 10, 2 );
function ncu_osch_optimise_revisions( $num, $post ) {
    if ( 'off' === ncu_osch_optimise_level() ) { return $num; }
    $limit = (int) ncu_osch_optimise_setting( 'revisions', 0 );
    if ( $limit < 1 ) { return $num; }
    /* A site that switched revisions off (0) keeps them off; unlimited (-1 or true) gets the limit. */
    if ( is_int( $num ) && 0 === $num ) { return $num; }
    return ( is_int( $num ) && $num > 0 ) ? min( $num, $limit ) : $limit;
}

add_filter( 'autosave_interval', 'ncu_osch_optimise_autosave' );
function ncu_osch_optimise_autosave( $seconds ) {
    if ( 'off' === ncu_osch_optimise_level() ) { return $seconds; }
    return max( (int) $seconds, (int) ncu_osch_optimise_setting( 'autosave', 60 ) );
}

add_filter( 'heartbeat_settings', 'ncu_osch_optimise_heartbeat' );
function ncu_osch_optimise_heartbeat( $settings ) {
    if ( 'off' === ncu_osch_optimise_level() ) { return $settings; }
    $settings['interval'] = (int) ncu_osch_optimise_setting( 'heartbeat_admin', 15 );
    return $settings;
}

/**
 * Delete expired transients only. Live transients stay, so no in-progress
 * import, preview or login state is lost.
 */
function ncu_osch_optimise_clear_expired_transients() {
    global $wpdb;
    $prefix   = '_transient_timeout_';
    $expired  = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
        $wpdb->esc_like( $prefix ) . '%',
        time()
    ) );
    foreach ( $expired as $timeout_name ) {
        delete_transient( substr( $timeout_name, strlen( $prefix ) ) );
    }
    return count( $expired );
}

add_action( 'admin_post_ncu_osch_optimise_run', 'ncu_osch_optimise_run' );
function ncu_osch_optimise_run() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You are not allowed to optimise this site.', 'nine-code' ) ); }
    check_admin_referer( 'ncu_osch_optimise_run' );

    $mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized on this line.
    $levels = ncu_osch_optimise_levels();
    $auto   = 'auto' === $mode;
    $level  = $auto ? 'balanced' : $mode;
    $cleaned = 0;

    if ( 'restore' === $mode ) {
        $level = 'off';
    } elseif ( ! isset( $levels[ $level ] ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=open-scholar-optimise&ncu_optimised=error' ) );
        exit;
    } else {
        $cleaned = ncu_osch_optimise_clear_expired_transients();
    }

    update_option( NCU_OSCH_OPTIMISE_OPTION, array(
        'level'   => $level,
        'changed' => time(),
        'by'      => $auto ? 'auto' : 'manual',
    ), false );

    wp_safe_redirect( add_query_arg( array(
        'page'          => 'open-scholar-optimise',
        'ncu_optimised' => $level,
        'cleaned'       => $cleaned,
    ), admin_url( 'admin.php' ) ) );
    exit;
}

add_action( 'admin_menu', 'ncu_osch_optimise_menu', 31 );
function ncu_osch_optimise_menu() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    add_submenu_page( 'nine-code-ultra', __( 'Optimise Site', 'nine-code' ), __( 'Optimise Site', 'nine-code' ), 'manage_options', 'open-scholar-optimise', 'ncu_osch_optimise_page' );
}

function ncu_osch_optimise_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $state   = get_option( NCU_OSCH_OPTIMISE_OPTION, array() );
    $current = is_array( $state ) && ! empty( $state['level'] ) ? $state['level'] : 'off';
    $levels  = ncu_osch_optimise_levels();
    $notice  = isset( $_GET['ncu_optimised'] ) ? sanitize_key( wp_unslash( $_GET['ncu_optimised'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
    $cleaned = isset( $_GET['cleaned'] ) ? absint( $_GET['cleaned'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
    $when    = ! empty( $state['changed'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $state['changed'] ) : '';
    $action  = admin_url( 'admin-post.php' );
    ?>
    <div class="wrap ncu-admin" data-ninecode-settings-root>
        <?php ncu_admin_header( 'Optimise Site', 'One button tunes the whole site. Every setting is reversible, and no content, user or media is deleted.' ); ?>

        <?php if ( 'error' === $notice ) : ?>
            <div class="notice notice-error"><p>That optimisation level does not exist. Nothing was changed.</p></div>
        <?php elseif ( $notice ) : ?>
            <div class="notice notice-success"><p>
                <?php
                if ( 'off' === $notice ) {
                    echo 'Optimisation is turned off. WordPress defaults are back.';
                } else {
                    printf( 'Optimisation applied: %1$s. %2$d expired cache entries were removed.', esc_html( ucfirst( $notice ) ), (int) $cleaned );
                }
                ?>
            </p></div>
        <?php endif; ?>

        <section class="osch-settings-panel" aria-labelledby="osch-opt-auto">
            <h2 id="osch-opt-auto">Optimise whole site</h2>
            <p class="description">Applies Balanced and clears expired cache. This is the simplest choice.</p>
            <form method="post" action="<?php echo esc_url( $action ); ?>">
                <?php wp_nonce_field( 'ncu_osch_optimise_run' ); ?>
                <input type="hidden" name="action" value="ncu_osch_optimise_run">
                <button type="submit" name="mode" value="auto" class="button button-primary">Optimise whole site</button>
            </form>
        </section>

        <section class="osch-settings-panel" aria-labelledby="osch-opt-levels">
            <h2 id="osch-opt-levels">Choose a level</h2>
            <p class="description">Each level includes the head clean-up and the removal of unused emoji scripts. Pick the level that fits how much you want to change.</p>
            <div class="osch-opt-levels">
                <?php foreach ( $levels as $key => $level ) : ?>
                    <form method="post" action="<?php echo esc_url( $action ); ?>" class="osch-opt-level<?php echo $current === $key ? ' is-current' : ''; ?>">
                        <?php wp_nonce_field( 'ncu_osch_optimise_run' ); ?>
                        <input type="hidden" name="action" value="ncu_osch_optimise_run">
                        <strong><?php echo esc_html( $level['label'] ); ?></strong>
                        <span class="description"><?php echo esc_html( $level['summary'] ); ?></span>
                        <button type="submit" name="mode" value="<?php echo esc_attr( $key ); ?>" class="button">Apply <?php echo esc_html( $key ); ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="osch-settings-panel" aria-labelledby="osch-opt-state">
            <h2 id="osch-opt-state">Current state</h2>
            <p>
                <?php
                if ( 'off' === $current || ! isset( $levels[ $current ] ) ) {
                    echo 'Optimisation is off. WordPress defaults are in use.';
                } else {
                    printf( 'Level: %1$s.', esc_html( $levels[ $current ]['label'] ) );
                    if ( $when ) { printf( ' Set %1$s (%2$s).', esc_html( $when ), esc_html( ! empty( $state['by'] ) && 'auto' === $state['by'] ? 'automatically' : 'by hand' ) ); }
                }
                ?>
            </p>
            <form method="post" action="<?php echo esc_url( $action ); ?>">
                <?php wp_nonce_field( 'ncu_osch_optimise_run' ); ?>
                <input type="hidden" name="action" value="ncu_osch_optimise_run">
                <button type="submit" name="mode" value="restore" class="button">Restore WordPress defaults</button>
            </form>
        </section>
    </div>
    <?php
}
