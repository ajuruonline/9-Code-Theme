<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Nine Code.1 public performance layer.
 * Heavy drawer content is rendered only after a visitor asks for it.
 */
add_action( 'rest_api_init', 'ncu_register_public_ui_routes' );
function ncu_register_public_ui_routes() {
    register_rest_route( 'nine-code-ultra/v1', '/drawer/categories', array(
        'methods' => 'GET',
        'callback' => 'ncu_rest_drawer_categories',
        'permission_callback' => '__return_true',
    ) );
}

function ncu_rest_drawer_categories( WP_REST_Request $request ) {
    $s = ncu_theme_settings();
    $limit = (int) apply_filters( 'ncu_drawer_category_limit', 250 );
    $limit = max( 25, min( 1000, $limit ) );
    $parent = absint( isset( $s['category_parent'] ) ? $s['category_parent'] : 0 );
    $last_changed = function_exists( 'wp_cache_get_last_changed' ) ? wp_cache_get_last_changed( 'terms' ) : '';
    $cache_key = 'ncu_drawer_categories_' . md5( $parent . '|' . $limit . '|' . $last_changed );
    $html = get_transient( $cache_key );
    if ( false === $html ) {
        $args = array(
            'title_li' => '',
            'show_count' => false,
            'hierarchical' => true,
            'hide_empty' => true,
            'child_of' => $parent,
            'echo' => false,
            'number' => $limit,
        );
        $html = wp_list_categories( apply_filters( 'ncu_drawer_category_args', $args, $s ) );
        if ( ! $html ) {
            $html = '<li class="ncu-category-empty">' . esc_html__( 'No categories available.', 'nine-code' ) . '</li>';
        }
        set_transient( $cache_key, $html, 6 * HOUR_IN_SECONDS );
    }
    return rest_ensure_response( array( 'html' => $html ) );
}

function ncu_lazy_popup_url( $index, $context_id = 0 ) {
    return add_query_arg( array(
        'action' => 'ncu_lazy_popup',
        'index' => absint( $index ),
        'context' => absint( $context_id ),
    ), admin_url( 'admin-post.php' ) );
}

add_action( 'admin_post_nopriv_ncu_lazy_popup', 'ncu_render_lazy_popup_document' );
add_action( 'admin_post_ncu_lazy_popup', 'ncu_render_lazy_popup_document' );
function ncu_render_lazy_popup_document() {
    $s = ncu_theme_settings();
    $index = isset( $_GET['index'] ) ? absint( $_GET['index'] ) : -1;
    $actions = isset( $s['quick_actions'] ) && is_array( $s['quick_actions'] ) ? $s['quick_actions'] : array();
    if ( $index < 0 || ! isset( $actions[ $index ] ) ) { status_header( 404 ); exit; }
    $action = $actions[ $index ];
    $mode = isset( $action['mode'] ) ? sanitize_key( $action['mode'] ) : 'link';
    $content = isset( $action['content'] ) ? trim( (string) $action['content'] ) : '';
    if ( 'popup' !== $mode || '' === $content ) { status_header( 404 ); exit; }

    $context_id = isset( $_GET['context'] ) ? absint( $_GET['context'] ) : 0;
    $old_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
    if ( $context_id ) {
        $context_post = get_post( $context_id );
        if ( $context_post && 'publish' === get_post_status( $context_post ) ) {
            $GLOBALS['post'] = $context_post;
            setup_postdata( $context_post );
        }
    }

    /* Render before wp_head() so shortcode-specific styles/scripts can enqueue
     * and be printed inside this isolated, on-demand frame. */
    $html = do_shortcode( $content );
    nocache_headers();
    ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php wp_head(); ?>
<style>html,body{margin:0!important;padding:0!important;background:transparent!important}body{min-width:0!important}.ncu-lazy-popup-document{padding:4px;overflow-wrap:anywhere}.ncu-lazy-popup-document img,.ncu-lazy-popup-document video,.ncu-lazy-popup-document iframe{max-width:100%;height:auto}.ncu-lazy-popup-document iframe{width:100%;min-height:360px}</style>
</head>
<body class="ncu-lazy-popup-frame"><main class="ncu-lazy-popup-document"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></main><?php wp_footer(); ?></body>
</html>
    <?php
    if ( $context_id ) {
        wp_reset_postdata();
        if ( $old_post ) { $GLOBALS['post'] = $old_post; }
    }
    exit;
}
