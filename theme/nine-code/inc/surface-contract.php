<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Generic host-surface contract.
 *
 * Specialist plugins can claim only their own request through the shared
 * filter. The Theme no longer contains named Mason/9Page special cases.
 */
function ninecode_theme_surface_context( $context = array() ) {
    $context = is_array( $context ) ? $context : array();

    if ( function_exists( 'ninecodepress_surface_context' ) ) {
        return ninecodepress_surface_context( $context );
    }

    $defaults = function_exists( 'ncu_theme_default_surface_context' )
        ? ncu_theme_default_surface_context()
        : array(
            'owner'                        => 'wordpress',
            'owns_shell'                   => false,
            'suppress_theme_header'        => false,
            'suppress_theme_footer'        => false,
            'suppress_theme_quick_actions' => false,
            'inherit_design_tokens'        => true,
            'reason'                       => '',
        );

    $resolved = apply_filters( 'ninecodepress_surface_context', array_merge( $defaults, $context ) );
    return is_array( $resolved ) ? $resolved : $defaults;
}

function ninecode_theme_surface_suppresses( $surface ) {
    $context = ninecode_theme_surface_context();
    $key = 'suppress_theme_' . sanitize_key( $surface );
    return ! empty( $context[ $key ] );
}
