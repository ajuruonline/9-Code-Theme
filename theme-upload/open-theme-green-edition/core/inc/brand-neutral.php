<?php
/**
 * Open Theme never names the software underneath it in the screens people use.
 *
 * - Visible text on admin and login pages: the word "WordPress" becomes "Advanced",
 *   and the "Howdy" greeting becomes "Hello". Only text between tags changes, so
 *   links, attributes, scripts and styles keep working.
 * - The WordPress logo, version footer, generator tag and core update notice are removed.
 * - Pages that are not HTML (downloads, JSON, REST, AJAX) are never touched.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_init', 'ncu_brand_neutral_start' );
add_action( 'login_init', 'ncu_brand_neutral_start' );
function ncu_brand_neutral_start() {
    if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return; }
    ob_start( 'ncu_brand_neutral_html' );
}

/** Replace names in text nodes of an HTML document. Tags, scripts and styles pass through unchanged. */
function ncu_brand_neutral_html( $html ) {
    if ( ! is_string( $html ) || false === strpos( $html, '<html' ) ) { return $html; }
    /* Fast path: most pages have none of the words, so no splitting is needed. */
    if ( false === stripos( $html, 'wordpress' ) && false === strpos( $html, 'Howdy,' ) ) { return $html; }
    $parts = preg_split( '~(<script\b.*?</script>|<style\b.*?</style>|<[^>]+>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
    if ( ! is_array( $parts ) ) { return $html; }
    foreach ( $parts as $i => $part ) {
        if ( '' === $part || '<' === $part[0] ) { continue; }
        $parts[ $i ] = str_replace( array( 'WordPress.org', 'WordPress', 'Howdy,' ), array( 'Advanced', 'Advanced', 'Hello,' ), $part );
    }
    return implode( '', $parts );
}

add_filter( 'admin_footer_text', '__return_empty_string', 999 );
add_filter( 'update_footer', '__return_empty_string', 999 );
add_filter( 'the_generator', '__return_empty_string' );
remove_action( 'wp_head', 'wp_generator' );

add_action( 'admin_bar_menu', 'ncu_brand_neutral_admin_bar', 999 );
function ncu_brand_neutral_admin_bar( $bar ) {
    $bar->remove_node( 'wp-logo' );
}

add_action( 'admin_menu', 'ncu_brand_neutral_admin_notices' );
function ncu_brand_neutral_admin_notices() {
    remove_action( 'admin_notices', 'update_nag', 3 );
    remove_action( 'network_admin_notices', 'update_nag', 3 );
}

/* The login page shows the site's own name, not the WordPress logo. */
add_filter( 'login_headerurl', 'home_url' );
add_filter( 'login_headertext', function () { return get_bloginfo( 'name' ); } );
add_action( 'login_head', 'ncu_brand_neutral_login_css' );
function ncu_brand_neutral_login_css() {
    echo '<style>.login h1 a{background-image:none!important;width:auto!important;height:auto!important;text-indent:0!important;font-size:22px;font-weight:700;color:#14532d!important;line-height:1.2;display:block;text-align:center;overflow:visible!important}</style>';
}
