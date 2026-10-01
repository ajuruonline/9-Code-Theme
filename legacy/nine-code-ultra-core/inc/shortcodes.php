<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'nine_year', 'ncu_shortcode_year' );
function ncu_shortcode_year() {
    return esc_html( wp_date( 'Y' ) );
}

add_shortcode( 'nine_author_contact', 'ncu_shortcode_author_contact' );
function ncu_shortcode_author_contact() {
    if ( ! is_singular() ) {
        return '';
    }
    $post = get_queried_object();
    if ( ! $post || empty( $post->post_author ) ) {
        return '';
    }
    $author_id = (int) $post->post_author;
    $email = get_user_meta( $author_id, 'ncu_public_email', true );
    $whatsapp = get_user_meta( $author_id, 'ncu_whatsapp', true );
    $email = is_scalar( $email ) ? (string) $email : '';
    $whatsapp = is_scalar( $whatsapp ) ? (string) $whatsapp : '';
    $links = array();
    if ( $whatsapp ) {
        $links[] = '<a href="' . esc_url( 'https://wa.me/' . preg_replace( '/\D+/', '', $whatsapp ) ) . '" rel="noopener noreferrer">WhatsApp</a>';
    }
    if ( $email ) {
        $links[] = '<a href="' . esc_url( 'mailto:' . sanitize_email( $email ) ) . '">Email</a>';
    }
    if ( empty( $links ) ) {
        return '';
    }
    return '<span class="ncu-author-contact-shortcode">' . implode( ' <span aria-hidden="true">·</span> ', $links ) . '</span>';
}
