<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', 'ncu_enqueue_assets_safe' );
function ncu_enqueue_assets_safe() {
    try { ncu_enqueue_assets(); }
    catch ( \Throwable $e ) { error_log( '[9Code Theme ' . ( defined( 'NCU_THEME_VERSION' ) ? NCU_THEME_VERSION : '' ) . '] enqueue fallback: ' . $e->getMessage() ); }
}
function ncu_enqueue_assets() {
    if ( function_exists( 'ncu_theme_should_enqueue_presentation_assets' ) && ! ncu_theme_should_enqueue_presentation_assets() ) { return; }
    wp_enqueue_style( 'nine-code-ultra', NCU_THEME_URI . '/style.css', array(), NCU_THEME_VERSION );
    wp_enqueue_style( 'nine-code-ultra-main', NCU_THEME_URI . '/assets/css/main.css', array( 'nine-code-ultra' ), NCU_THEME_VERSION );
    $s = ncu_theme_settings();
    $builtin_header = (bool) apply_filters( 'ncu_render_builtin_header', 'ninecode' === ( isset( $s['header_owner_mode'] ) ? $s['header_owner_mode'] : 'external' ) && ! empty( $s['header_enabled'] ), $s );
    if ( $builtin_header ) {
        wp_enqueue_script( 'nine-code-ultra', NCU_THEME_URI . '/assets/js/theme.js', array(), NCU_THEME_VERSION, true );
        if ( function_exists( 'wp_script_add_data' ) ) { wp_script_add_data( 'nine-code-ultra', 'strategy', 'defer' ); }
        wp_localize_script( 'nine-code-ultra', 'NCUThemeRuntime', array(
            'restRoot' => esc_url_raw( rest_url( 'nine-code-ultra/v1/' ) ),
            'contextId' => is_singular() ? absint( get_queried_object_id() ) : 0,
            'strings' => array( 'loading' => __( 'Loading…', 'nine-code-ultra' ), 'error' => __( 'Unable to load this content. Please try again.', 'nine-code-ultra' ) ),
        ) );
    }

    $shadow = '0 18px 48px rgba(0,0,0,.12)';
    if ( 'none' === $s['shadow_strength'] ) { $shadow = 'none'; }
    if ( 'strong' === $s['shadow_strength'] ) { $shadow = '0 24px 70px rgba(0,0,0,.22)'; }
    $inline = ':root{' .
        '--ncu-accent:' . ncu_safe_color( $s['accent_color'], '#000000' ) . ';' .
        '--ncu-surface:' . ncu_safe_color( $s['surface_color'], '#ffffff' ) . ';' .
        '--ncu-text:' . ncu_safe_color( $s['text_color'], '#000000' ) . ';' .
        '--ncu-muted:' . ncu_safe_color( $s['muted_color'], '#5f6368' ) . ';' .
        '--ncu-header-bg:' . ncu_safe_color( $s['header_background_color'], '#ffffff' ) . ';' .
        '--ncu-header-icon:' . ncu_safe_color( $s['header_icon_color'], '#000000' ) . ';' .
        '--ncu-header-border:' . ncu_safe_color( $s['header_border_color'], '#e5e7eb' ) . ';' .
        '--ncu-footer-bg:' . ncu_safe_color( $s['footer_background_color'], '#ffffff' ) . ';' .
        '--ncu-footer-text:' . ncu_safe_color( $s['footer_text_color'], '#000000' ) . ';' .
        '--ncu-max:' . absint( $s['max_width'] ) . 'px;' .
        '--ncu-radius:' . absint( $s['radius'] ) . 'px;' .
        '--ncu-drawer-height:' . absint( $s['drawer_height'] ) . 'vh;' .
        '--ncu-icon-size:' . absint( $s['header_icon_size'] ) . 'px;' .
        '--ncu-icon-gap:' . absint( $s['header_icon_gap'] ) . 'px;' .
        '--ncu-shadow:' . $shadow . ';' .
    '}';


    if ( is_singular() ) {
        $post_id = get_queried_object_id();
        if ( $post_id && 'ai' === ncu_theme_get_render_mode( $post_id ) ) {
            $ai = ncu_theme_ai_config( $post_id );
            $width = isset( $ai['content_width'] ) ? absint( $ai['content_width'] ) : 0;
            $radius = isset( $ai['radius'] ) ? (int) $ai['radius'] : 0;
            $spacing = isset( $ai['spacing_scale'] ) ? (float) $ai['spacing_scale'] : 1;
            $font = isset( $ai['font_scale'] ) ? (float) $ai['font_scale'] : 1;
            $inline .= 'body.ncu-render-ai{' .
                '--ncu-ai-width:' . ( $width ? $width . 'px' : 'var(--ncu-max)' ) . ';' .
                '--ncu-ai-radius:' . ( $radius > 0 ? $radius . 'px' : 'var(--ncu-radius)' ) . ';' .
                '--ncu-ai-spacing:' . max( .65, min( 1.65, $spacing ) ) . ';' .
                '--ncu-ai-font:' . max( .8, min( 1.35, $font ) ) . ';' .
                ( ! empty( $ai['background'] ) ? '--ncu-ai-bg:' . ncu_safe_color( $ai['background'], 'var(--ncu-surface)' ) . ';' : '' ) .
                ( ! empty( $ai['text_color'] ) ? '--ncu-ai-text:' . ncu_safe_color( $ai['text_color'], 'var(--ncu-text)' ) . ';' : '' ) .
                ( ! empty( $ai['accent_color'] ) ? '--ncu-ai-accent:' . ncu_safe_color( $ai['accent_color'], 'var(--ncu-accent)' ) . ';' : '' ) .
            '}';
            if ( ! empty( $ai['page_css'] ) ) {
                $page_css = ncu_theme_core_compatible() && function_exists( 'ncu_sanitize_page_css' ) ? ncu_sanitize_page_css( $ai['page_css'] ) : ncu_theme_sanitize_page_css( $ai['page_css'] );
                if ( $page_css ) { $inline .= "\n" . $page_css; }
            }
        }
    }
    wp_add_inline_style( 'nine-code-ultra-main', $inline );
}

function ncu_safe_color( $value, $fallback ) {
    $color = sanitize_hex_color( (string) $value );
    return $color ? $color : $fallback;
}

add_filter( 'body_class', 'ncu_body_classes_safe' );
function ncu_body_classes_safe( $classes ) {
    try { return ncu_body_classes( $classes ); }
    catch ( \Throwable $e ) { error_log( '[9Code Theme ' . ( defined( 'NCU_THEME_VERSION' ) ? NCU_THEME_VERSION : '' ) . '] body-class fallback: ' . $e->getMessage() ); return is_array( $classes ) ? $classes : array(); }
}
function ncu_body_classes( $classes ) {
    $s = ncu_theme_settings();
    $classes[] = 'ncu-preset-' . sanitize_html_class( $s['design_preset'] );
    $classes[] = 'ncu-density-' . sanitize_html_class( $s['density'] );
    if ( ! empty( $s['popular_site_template'] ) ) { $classes[] = 'ncu-popular-site-' . sanitize_html_class( $s['popular_site_template'] ); }
    if ( ! empty( $s['style_takeover_preset'] ) ) { $classes[] = 'ncu-skin-' . sanitize_html_class( $s['style_takeover_preset'] ); }
    if ( ! empty( $s['sticky_header'] ) ) { $classes[] = 'ncu-sticky-header'; }
    if ( is_active_sidebar( 'sidebar-1' ) ) { $classes[] = 'ncu-has-sidebar'; }
    if ( is_singular() ) {
        $post_id = get_queried_object_id();
        $mode = $post_id ? ncu_theme_get_render_mode( $post_id ) : 'auto';
        $classes[] = 'ncu-render-' . sanitize_html_class( $mode );
        if ( 'ai' === $mode ) {
            $ai = ncu_theme_ai_config( $post_id );
            if ( ! empty( $ai['preset'] ) && 'inherit' !== $ai['preset'] ) { $classes[] = 'ncu-ai-preset-' . sanitize_html_class( $ai['preset'] ); }
        }
    }
    return $classes;
}
