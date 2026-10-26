<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** One-shot, per-user notice queue. Notices disappear after one rendered request. */
function ncu_notice_push( $message, $type = 'success', $key = '' ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) { return; }
    $allowed = array( 'success', 'info', 'warning', 'error' );
    $type = in_array( $type, $allowed, true ) ? $type : 'info';
    $queue = get_user_meta( $user_id, '_ncu_notice_queue', true );
    $queue = is_array( $queue ) ? $queue : array();
    $queue[] = array(
        'message' => sanitize_text_field( $message ),
        'type'    => $type,
        'key'     => sanitize_key( $key ? $key : wp_generate_uuid4() ),
    );
    update_user_meta( $user_id, '_ncu_notice_queue', array_slice( $queue, -10 ) );
}

add_action( 'admin_notices', 'ncu_notice_render_queue', 1 );
function ncu_notice_render_queue() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) { return; }
    $queue = get_user_meta( $user_id, '_ncu_notice_queue', true );
    if ( ! is_array( $queue ) || ! $queue ) { return; }
    delete_user_meta( $user_id, '_ncu_notice_queue' );
    foreach ( $queue as $notice ) {
        $type = isset( $notice['type'] ) ? sanitize_key( $notice['type'] ) : 'info';
        $message = isset( $notice['message'] ) ? (string) $notice['message'] : '';
        if ( '' === $message ) { continue; }
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible ncu-one-shot-notice"><p>' . esc_html( $message ) . '</p></div>';
    }
}

add_action( 'admin_post_ncu_clear_notices', 'ncu_clear_notices_handler' );
function ncu_clear_notices_handler() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code' ) ); }
    check_admin_referer( 'ncu_clear_notices' );
    delete_user_meta( get_current_user_id(), '_ncu_notice_queue' );
    delete_user_meta( get_current_user_id(), '_ncu_dismissed_notices' );
    ncu_notice_push( 'Nine Code notices cleared.', 'success', 'notices-cleared' );
    wp_safe_redirect( admin_url( 'admin.php?page=nine-code-ultra' ) );
    exit;
}

/* Keep old query-string notices from sticking around after refresh. */
add_action( 'admin_init', 'ncu_cleanup_legacy_notice_query_args', 99 );
function ncu_cleanup_legacy_notice_query_args() {
    if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) { return; }
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( 0 !== strpos( $page, 'nine-code' ) ) { return; }
    if ( empty( $_GET['updated'] ) && empty( $_GET['reset'] ) && empty( $_GET['imported'] ) ) { return; }
    $type = ! empty( $_GET['reset'] ) ? 'info' : 'success';
    $message = ! empty( $_GET['reset'] ) ? 'Nine Code settings reset.' : ( ! empty( $_GET['imported'] ) ? 'Nine Code settings imported.' : 'Nine Code settings saved.' );
    ncu_notice_push( $message, $type, 'legacy-result' );
    $url = remove_query_arg( array( 'updated', 'reset', 'imported' ) );
    wp_safe_redirect( $url );
    exit;
}
