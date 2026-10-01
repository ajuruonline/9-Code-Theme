<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Universal WordPress / custom-post-type host compatibility.
 *
 * Principle: WordPress owns the request, a plugin owns its CPT/data/template,
 * and the Theme assists without replacing that ownership. No named CPT or
 * plugin allow-list is required.
 */

function ncu_theme_capture_selected_template( $template ) {
    $GLOBALS['ncu_theme_selected_template'] = is_string( $template ) ? $template : '';
    return $template;
}
add_filter( 'template_include', 'ncu_theme_capture_selected_template', PHP_INT_MAX );

function ncu_theme_normalize_path( $path ) {
    $path = wp_normalize_path( (string) $path );
    return untrailingslashit( $path );
}

function ncu_theme_path_is_within( $path, $directory ) {
    $path = ncu_theme_normalize_path( $path );
    $directory = trailingslashit( ncu_theme_normalize_path( $directory ) );
    return '' !== $path && '' !== $directory && 0 === strpos( $path . '/', $directory );
}

function ncu_theme_selected_template() {
    return isset( $GLOBALS['ncu_theme_selected_template'] ) && is_string( $GLOBALS['ncu_theme_selected_template'] )
        ? $GLOBALS['ncu_theme_selected_template']
        : '';
}

function ncu_theme_is_external_template() {
    $template = ncu_theme_selected_template();
    if ( '' === $template ) { return false; }

    $theme_dirs = array_filter( array_unique( array(
        function_exists( 'get_template_directory' ) ? get_template_directory() : '',
        function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() : '',
    ) ) );

    foreach ( $theme_dirs as $dir ) {
        if ( ncu_theme_path_is_within( $template, $dir ) ) { return false; }
    }
    return true;
}

function ncu_theme_request_post_type() {
    if ( ! function_exists( 'is_singular' ) || ! is_singular() ) { return ''; }
    $post_id = function_exists( 'get_queried_object_id' ) ? absint( get_queried_object_id() ) : 0;
    $post_type = $post_id && function_exists( 'get_post_type' ) ? get_post_type( $post_id ) : '';
    return is_string( $post_type ) ? sanitize_key( $post_type ) : '';
}

function ncu_theme_post_type_is_custom( $post_type ) {
    $post_type = sanitize_key( (string) $post_type );
    if ( '' === $post_type ) { return false; }
    return ! in_array( $post_type, array( 'post', 'page', 'attachment' ), true );
}

function ncu_theme_is_custom_post_type_request() {
    return ncu_theme_post_type_is_custom( ncu_theme_request_post_type() );
}

/**
 * Modes:
 * - theme: Theme owns public presentation.
 * - compatibility: Theme template is being used for a foreign CPT; keep the
 *   WordPress loop but avoid aggressive reinterpretation.
 * - plugin: an external template owns the complete public shell.
 */
function ncu_theme_host_mode() {
    $post_type = ncu_theme_request_post_type();
    $mode = 'theme';

    if ( ncu_theme_is_external_template() ) {
        $mode = 'plugin';
    } elseif ( ncu_theme_post_type_is_custom( $post_type ) ) {
        $mode = 'compatibility';
    }

    $context = array(
        'post_type'        => $post_type,
        'template'         => ncu_theme_selected_template(),
        'external_template'=> ncu_theme_is_external_template(),
        'is_custom_type'   => ncu_theme_post_type_is_custom( $post_type ),
    );
    $filtered = apply_filters( 'ninecode_theme_host_mode', $mode, $context );
    $filtered = is_scalar( $filtered ) ? sanitize_key( (string) $filtered ) : $mode;
    return in_array( $filtered, array( 'theme', 'compatibility', 'plugin' ), true ) ? $filtered : $mode;
}

function ncu_theme_should_enqueue_presentation_assets() {
    $allow = 'plugin' !== ncu_theme_host_mode();
    return (bool) apply_filters( 'ninecode_theme_enqueue_presentation_assets', $allow, ncu_theme_host_mode() );
}

function ncu_theme_allows_style_takeover() {
    $allow = 'theme' === ncu_theme_host_mode();
    return (bool) apply_filters( 'ninecode_theme_allow_style_takeover', $allow, ncu_theme_host_mode(), ncu_theme_request_post_type() );
}

function ncu_theme_default_surface_context() {
    $mode = ncu_theme_host_mode();
    $context = array(
        'owner'                        => 'wordpress',
        'owns_shell'                   => false,
        'suppress_theme_header'        => false,
        'suppress_theme_footer'        => false,
        'suppress_theme_quick_actions' => false,
        'inherit_design_tokens'        => true,
        'reason'                       => '',
    );

    if ( 'plugin' === $mode ) {
        $context['owner'] = 'plugin-template';
        $context['owns_shell'] = true;
        $context['suppress_theme_header'] = true;
        $context['suppress_theme_footer'] = true;
        $context['reason'] = 'External plugin-selected template owns this request.';
    } elseif ( 'compatibility' === $mode ) {
        $context['owner'] = 'wordpress-cpt';
        $context['reason'] = 'Custom post type uses Theme loop in compatibility mode.';
    }

    return $context;
}

add_filter( 'body_class', 'ncu_theme_host_compatibility_body_class_safe', 12 );
function ncu_theme_host_compatibility_body_class_safe( $classes ) {
    try { return ncu_theme_host_compatibility_body_class( $classes ); }
    catch ( \Throwable $e ) { error_log( '[9Code Theme ' . ( defined( 'NCU_THEME_VERSION' ) ? NCU_THEME_VERSION : '' ) . '] host body-class fallback: ' . $e->getMessage() ); return is_array( $classes ) ? $classes : array(); }
}
function ncu_theme_host_compatibility_body_class( $classes ) {
    $mode = ncu_theme_host_mode();
    $classes[] = 'ncu-host-' . sanitize_html_class( $mode );
    if ( 'compatibility' === $mode ) { $classes[] = 'ncu-cpt-compatibility'; }
    return $classes;
}
