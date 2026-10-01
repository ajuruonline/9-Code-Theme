<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ncu_icon( $name, $label = '', $extra_class = '' ) {
    $paths = array(
        'home'          => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10.5V20h13v-9.5"/><path d="M9.5 20v-6h5v6"/>',
        'search'        => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'info'          => '<circle cx="12" cy="12" r="9"/><path d="M12 10.8V17"/><path d="M12 7.2h.01"/>',
        'mail'          => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4.5 7 7.5 6 7.5-6"/>',
        'book-open'     => '<path d="M3.5 5.5A3.5 3.5 0 0 1 7 4h5v16H7a3.5 3.5 0 0 0-3.5 1.5z"/><path d="M20.5 5.5A3.5 3.5 0 0 0 17 4h-5v16h5a3.5 3.5 0 0 1 3.5 1.5z"/>',
        'user'          => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'phone'         => '<path d="M7 3.5h3l1.1 4.4-2 1.3a14 14 0 0 0 5.7 5.7l1.3-2 4.4 1.1v3c0 1.9-1.6 3.5-3.5 3.5A13.5 13.5 0 0 1 3.5 7C3.5 5.1 5.1 3.5 7 3.5Z"/>',
        'whatsapp'      => '<path d="M20.2 11.8a8.2 8.2 0 0 1-12.1 7.3L3.5 20.5l1.3-4.4a8.2 8.2 0 1 1 15.4-4.3Z"/><path d="M8.2 8c.8 3.4 3 5.6 6.5 6.5l1.5-1.6 2 .7"/>',
        'external-link' => '<path d="M14 5h5v5"/><path d="m19 5-8 8"/><path d="M18 13v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'arrow-right'   => '<path d="M5 12h14"/><path d="m14 7 5 5-5 5"/>',
        'moon'          => '<path d="M20.5 14.2A8.4 8.4 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"/>',
        'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"/>',
        'menu'          => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'         => '<path d="m6 6 12 12M18 6 6 18"/>',
        'globe'         => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
        'sparkles'      => '<path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3Z"/><path d="m18 13 .7 2.3L21 16l-2.3.7L18 19l-.7-2.3L15 16l2.3-.7L18 13Z"/>',
        'settings'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.8 1.8 0 0 0 .4 2l.1.1-2.8 2.8-.1-.1a1.8 1.8 0 0 0-2-.4 1.8 1.8 0 0 0-1.1 1.6v.2H10V21a1.8 1.8 0 0 0-1.1-1.6 1.8 1.8 0 0 0-2 .4l-.1.1L4 17.1l.1-.1a1.8 1.8 0 0 0 .4-2A1.8 1.8 0 0 0 3 13.9h-.2V10H3a1.8 1.8 0 0 0 1.6-1.1 1.8 1.8 0 0 0-.4-2l-.1-.1L6.9 4l.1.1a1.8 1.8 0 0 0 2 .4A1.8 1.8 0 0 0 10 3h4a1.8 1.8 0 0 0 1.1 1.5 1.8 1.8 0 0 0 2-.4l.1-.1L20 6.8l-.1.1a1.8 1.8 0 0 0-.4 2A1.8 1.8 0 0 0 21 10h.2v4H21a1.8 1.8 0 0 0-1.6 1Z"/>',
        'heart-pulse'   => '<path d="M3 12h4l2-4 4 8 2-4h6"/><path d="M12 21C6.4 17.5 3 14.4 3 9.8A4.8 4.8 0 0 1 12 7a4.8 4.8 0 0 1 9 2.8c0 4.6-3.4 7.7-9 11.2Z"/>',
        'blocks'        => '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
        'layout'        => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M9 10h12"/>',
        /* v1 aliases kept for upgrade safety. */
        'book'          => '<path d="M4 5h8v15H7a3 3 0 0 0-3 1z"/><path d="M12 5h8v16a3 3 0 0 0-3-1h-5z"/>',
        'link'          => '<path d="M9 15 15 9"/><path d="M7 17.5 5.5 19a3.5 3.5 0 0 1-5-5l3.5-3.5a3.5 3.5 0 0 1 5 0" transform="translate(3)"/><path d="m17 6.5 1.5-1.5a3.5 3.5 0 1 1 5 5L20 13.5a3.5 3.5 0 0 1-5 0" transform="translate(-3)"/>',
        'arrow'         => '<path d="M5 12h14M14 7l5 5-5 5"/>',
    );
    $name = isset( $paths[ $name ] ) ? $name : 'globe';
    $aria = $label ? ' role="img" aria-label="' . esc_attr( $label ) . '"' : ' aria-hidden="true"';
    $class = 'ncu-icon' . ( $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );
    return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"' . $aria . '>' . $paths[ $name ] . '</svg>';
}


function ncu_theme_client_logo_url() {
    $s = ncu_theme_settings();
    $id = isset( $s['client_logo_id'] ) ? absint( $s['client_logo_id'] ) : 0;
    if ( $id ) { $url = wp_get_attachment_image_url( $id, 'medium' ); if ( $url ) { return $url; } }
    return '';
}

function ncu_default_brand_icon_url() {
    $s = ncu_theme_settings();
    $id = isset( $s['fallback_icon_id'] ) ? absint( $s['fallback_icon_id'] ) : 0;
    if ( $id ) { $url = wp_get_attachment_image_url( $id, 'thumbnail' ); if ( $url ) { return $url; } }
    if ( has_site_icon() ) {
        $site_icon = get_site_icon_url( 96 );
        if ( $site_icon ) { return $site_icon; }
    }
    return NCU_THEME_URI . '/assets/images/site-placeholder.svg';
}

function ncu_action_icon( $name ) {
    if ( 'site' === $name || 'brand' === $name ) {
        if ( has_site_icon() ) { return '<img class="ncu-action-site-icon" src="' . esc_url( get_site_icon_url( 64 ) ) . '" alt="">'; }
        return '<img class="ncu-action-site-icon" src="' . esc_url( ncu_default_brand_icon_url() ) . '" alt="">';
    }
    return ncu_icon( $name );
}

function ncu_resolve_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) { return ''; }
    if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) { return home_url( $url ); }
    return $url;
}

function ncu_get_author_contact() {
    if ( ! is_singular() ) { return array(); }
    $post = get_queried_object();
    if ( ! $post || empty( $post->post_author ) ) { return array(); }
    $author_id = (int) $post->post_author;
    $items = array();
    $whatsapp = is_scalar( get_user_meta( $author_id, 'ncu_whatsapp', true ) ) ? (string) get_user_meta( $author_id, 'ncu_whatsapp', true ) : '';
    $email = is_scalar( get_user_meta( $author_id, 'ncu_public_email', true ) ) ? (string) get_user_meta( $author_id, 'ncu_public_email', true ) : '';
    $phone = is_scalar( get_user_meta( $author_id, 'ncu_phone', true ) ) ? (string) get_user_meta( $author_id, 'ncu_phone', true ) : '';
    if ( $whatsapp ) { $items[] = array( 'label' => 'WhatsApp', 'url' => 'https://wa.me/' . preg_replace( '/\D+/', '', $whatsapp ), 'icon' => 'whatsapp' ); }
    if ( $email ) { $items[] = array( 'label' => 'Email', 'url' => 'mailto:' . sanitize_email( $email ), 'icon' => 'mail' ); }
    if ( $phone ) { $items[] = array( 'label' => 'Phone', 'url' => 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ), 'icon' => 'phone' ); }
    if ( empty( $items ) ) { return array(); }
    return array( 'label' => __( 'Contact author', 'nine-code-ultra' ), 'name' => get_the_author_meta( 'display_name', $author_id ), 'items' => $items );
}

function ncu_should_show_native_title() {
    if ( ! is_singular() ) { return true; }
    $s = ncu_theme_settings();
    $post_id = get_queried_object_id();
    $default = true;
    if ( ! empty( $s['elementor_hide_title'] ) ) {
        if ( $post_id && 'elementor' === ncu_theme_get_render_mode( $post_id ) && ncu_elementor_runtime_available( $post_id ) ) { $default = false; }
        if ( $post_id && get_post_meta( $post_id, '_elementor_edit_mode', true ) && ncu_elementor_runtime_available( $post_id ) ) { $default = false; }
    }
    return function_exists( 'ncu_should_show_post_feature' ) ? ncu_should_show_post_feature( $post_id, 'title', $default ) : $default;
}

function ncu_posted_on() { echo '<span class="posted-on">' . esc_html( get_the_date() ) . '</span>'; }
function ncu_posted_by() { echo '<span class="byline">' . esc_html__( 'By', 'nine-code-ultra' ) . ' <a href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>'; }
