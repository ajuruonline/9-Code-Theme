<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ncu_theme_get_render_mode( $post_id ) {
    if ( ncu_theme_core_compatible() && function_exists( 'ncu_get_post_render_mode' ) ) { return ncu_get_post_render_mode( $post_id ); }
    $post_id = absint( $post_id );
    $mode = sanitize_key( (string) get_post_meta( $post_id, 'ncu_render_mode', true ) );
    if ( in_array( $mode, array( 'auto', 'ai', 'gutenberg', 'elementor' ), true ) ) { return $mode; }
    $mode = 'auto';
    $filtered = apply_filters( 'ncu_default_render_mode', $mode, $post_id, get_post_type( $post_id ) );
    $filtered = is_scalar( $filtered ) ? sanitize_key( (string) $filtered ) : $mode;
    return in_array( $filtered, array( 'auto', 'ai', 'gutenberg', 'elementor' ), true ) ? $filtered : $mode;
}

function ncu_theme_sanitize_page_css( $css ) {
    $css = wp_strip_all_tags( (string) $css );
    $css = substr( $css, 0, 20000 );
    $patterns = array(
        '/@import\s+[^;]+;?/i', '/@charset\s+[^;]+;?/i', '/expression\s*\(/i',
        '/javascript\s*:/i', '/vbscript\s*:/i', '/-moz-binding\s*:/i', '/behavior\s*:/i',
        '/url\s*\([^)]*\)/i',
    );
    return trim( preg_replace( $patterns, '', $css ) );
}

function ncu_theme_ai_config( $post_id ) {
    if ( ncu_theme_core_compatible() && function_exists( 'ncu_get_ai_config' ) ) { return ncu_get_ai_config( $post_id ); }
    return array( 'preset' => 'inherit', 'content_width' => 0, 'radius' => 0, 'spacing_scale' => '1', 'font_scale' => '1', 'background' => '', 'text_color' => '', 'accent_color' => '', 'hidden_sections' => '', 'section_order' => '', 'page_css' => '' );
}

function ncu_elementor_runtime_available( $post_id = 0 ) {
    if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance->frontend ) ) { return false; }
    if ( $post_id && 'elementor' !== ncu_theme_get_render_mode( $post_id ) && ! get_post_meta( $post_id, '_elementor_edit_mode', true ) ) { return false; }
    return method_exists( \Elementor\Plugin::$instance->frontend, 'get_builder_content_for_display' );
}

function ncu_builder_full_width( $post_id ) {
    $post_id = absint( $post_id );
    $full = false;
    try {
        $mode = $post_id ? ncu_theme_get_render_mode( $post_id ) : 'auto';
        $full = 'elementor' === $mode && ncu_elementor_runtime_available( $post_id );
        $post_type = $post_id ? get_post_type( $post_id ) : '';
        if ( function_exists( 'ncu_theme_post_type_is_custom' ) && ncu_theme_post_type_is_custom( $post_type ) ) {
            $compat = function_exists( 'ncu_theme_host_mode' ) ? 'compatibility' === ncu_theme_host_mode() : true;
            if ( $compat ) {
                $full = (bool) apply_filters( 'ncu_theme_cpt_compatibility_full_width', true, $post_id, $post_type );
            }
        }
        return (bool) apply_filters( 'ncu_builder_full_width', $full, $post_id, $mode );
    } catch ( \Throwable $e ) {
        error_log( '[Nine Code ' . NCU_THEME_VERSION . '] Nine Code render filter fallback (ncu_builder_full_width): ' . $e->getMessage() );
        return (bool) $full;
    }
}

function ncu_native_content_html( $post_id, $ai = false ) {
    $post_id = absint( $post_id );
    $raw = (string) get_post_field( 'post_content', $post_id );

    /*
     * AI-capable engines may supply their own non-destructive base presentation.
     * Returning null leaves Nine Code to use the native WordPress post content.
     */
    if ( $ai ) {
        $external = apply_filters( 'ncu_ai_builder_base_html', null, $post_id, ncu_theme_ai_config( $post_id ) );
        if ( is_string( $external ) ) {
            return (string) apply_filters( 'ncu_builder_render_html', $external, $post_id, 'ai' );
        }
        if ( ncu_theme_core_compatible() && function_exists( 'ncu_ai_restructure_raw' ) ) {
            $raw = ncu_ai_restructure_raw( $raw, $post_id );
        }
    }

    /*
     * Deliberately render the saved WordPress content without the site-wide
     * the_content wrapper filters. This keeps Gutenberg/AI as genuine alternate
     * presentation routes when an engine normally replaces the_content in Auto mode.
     * Dynamic blocks and shortcodes inside the content still execute normally.
     */
    $has_blocks = has_blocks( $raw );
    $content = do_blocks( $raw );
    $content = wptexturize( $content );
    $content = convert_smilies( $content );
    if ( ! $has_blocks ) { $content = wpautop( $content ); }
    $content = shortcode_unautop( $content );
    $content = do_shortcode( $content );
    if ( function_exists( 'wp_filter_content_tags' ) ) { $content = wp_filter_content_tags( $content ); }

    return (string) apply_filters( 'ncu_builder_render_html', $content, $post_id, $ai ? 'ai' : 'gutenberg' );
}

function ncu_output_post_content( $post_id = 0 ) {
    $post_id = $post_id ? absint( $post_id ) : get_the_ID();
    try {
        $mode = ncu_theme_get_render_mode( $post_id );
        if ( 'elementor' === $mode ) {
            if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->frontend ) && method_exists( \Elementor\Plugin::$instance->frontend, 'get_builder_content_for_display' ) ) {
                $html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id, true );
                if ( is_string( $html ) && '' !== trim( $html ) ) { echo $html; return; }
            }
            the_content();
            return;
        }
        if ( 'gutenberg' === $mode ) { echo ncu_native_content_html( $post_id, false ); return; }
        if ( 'ai' === $mode ) { echo ncu_native_content_html( $post_id, true ); return; }

        /* Future engines may claim Auto Builder output without replacing WordPress data. */
        $auto_html = apply_filters( 'ncu_auto_builder_html', null, $post_id );
        if ( is_string( $auto_html ) ) { echo $auto_html; return; }
        the_content();
    } catch ( \Throwable $e ) {
        error_log( '[Nine Code ' . NCU_THEME_VERSION . '] content renderer fallback for post ' . intval( $post_id ) . ': ' . $e->getMessage() );
        $raw = (string) get_post_field( 'post_content', $post_id );
        echo wp_kses_post( $raw );
    }
}

function ncu_builder_article_class( $post_id ) {
    try { $mode = ncu_theme_get_render_mode( $post_id ); }
    catch ( \Throwable $e ) { error_log( '[Nine Code ' . NCU_THEME_VERSION . '] render-mode class fallback: ' . $e->getMessage() ); $mode = 'auto'; }
    $classes = array( 'ncu-render-shell', 'ncu-render-' . sanitize_html_class( $mode ) );
    if ( 'ai' === $mode ) { $classes[] = 'ncu-ai-shell'; }
    return implode( ' ', $classes );
}
