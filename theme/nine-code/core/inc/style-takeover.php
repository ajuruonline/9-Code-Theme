<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 9Core 15 v3 design authority.
 *
 * Plugin-local settings remain stored. Aggressive Takeover changes the final
 * presentation cascade only, so disabling the feature restores plugin styling.
 */
function ncu_style_library_load() {
    static $library = null;
    if ( null !== $library ) { return $library; }
    $file = NCU_CORE_DIR . 'assets/design/style-presets.json';
    $library = array( 'version' => 1, 'families' => array(), 'fonts' => array() );
    if ( ! is_readable( $file ) ) { return $library; }
    $decoded = json_decode( (string) file_get_contents( $file ), true );
    if ( is_array( $decoded ) && ! empty( $decoded['families'] ) && is_array( $decoded['families'] ) ) {
        $library = $decoded;
    }
    return $library;
}

function ncu_style_takeover_registry() {
    $library = ncu_style_library_load();
    return isset( $library['families'] ) && is_array( $library['families'] ) ? $library['families'] : array();
}

function ncu_style_takeover_index() {
    static $index = null;
    if ( null !== $index ) { return $index; }
    $index = array();
    foreach ( ncu_style_takeover_registry() as $family ) {
        if ( empty( $family['variants'] ) || ! is_array( $family['variants'] ) ) { continue; }
        foreach ( $family['variants'] as $variant ) {
            if ( empty( $variant['slug'] ) ) { continue; }
            $variant['family_slug'] = isset( $family['slug'] ) ? sanitize_key( $family['slug'] ) : '';
            $variant['family_label'] = isset( $family['label'] ) ? sanitize_text_field( $family['label'] ) : '';
            $index[ sanitize_key( $variant['slug'] ) ] = $variant;
        }
    }
    return $index;
}

function ncu_popular_site_templates() {
    $out = array();
    foreach ( ncu_style_takeover_registry() as $family ) {
        $slug = sanitize_key( $family['slug'] ?? '' );
        if ( ! $slug || empty( $family['variants'] ) || ! is_array( $family['variants'] ) ) { continue; }
        $out[ $slug ] = $family;
    }
    return $out;
}

function ncu_popular_site_default_skin( $family_slug ) {
    $family_slug = sanitize_key( $family_slug );
    $families = ncu_popular_site_templates();
    if ( empty( $families[ $family_slug ]['variants'] ) ) { return 'academic-reference'; }
    foreach ( $families[ $family_slug ]['variants'] as $variant ) {
        $slug = sanitize_key( $variant['slug'] ?? '' );
        if ( $slug && ( substr( $slug, -10 ) === '-reference' || 'academic-reference' === $slug ) ) { return $slug; }
    }
    return sanitize_key( $families[ $family_slug ]['variants'][0]['slug'] ?? 'academic-reference' );
}

function ncu_style_skin_family( $skin_slug ) {
    $skin_slug = sanitize_key( $skin_slug );
    if ( 'custom-brand' === $skin_slug ) { return 'custom'; }
    $index = ncu_style_takeover_index();
    return ! empty( $index[ $skin_slug ]['family_slug'] ) ? sanitize_key( $index[ $skin_slug ]['family_slug'] ) : '';
}

function ncu_popular_site_profile( $family_slug ) {
    $profiles = array(
        'academic-red' => array( 'design_preset'=>'editorial', 'density'=>'comfortable', 'shadow_strength'=>'soft',   'radius'=>16, 'max_width'=>1180 ),
        'facebook'  => array( 'design_preset'=>'visual',    'density'=>'comfortable', 'shadow_strength'=>'soft',   'radius'=>16, 'max_width'=>1200 ),
        'youtube'   => array( 'design_preset'=>'visual',    'density'=>'compact',     'shadow_strength'=>'soft',   'radius'=>12, 'max_width'=>1280 ),
        'whatsapp'  => array( 'design_preset'=>'compact',   'density'=>'comfortable', 'shadow_strength'=>'soft',   'radius'=>18, 'max_width'=>1120 ),
        'instagram' => array( 'design_preset'=>'visual',    'density'=>'comfortable', 'shadow_strength'=>'soft',   'radius'=>22, 'max_width'=>1180 ),
        'linkedin'  => array( 'design_preset'=>'executive', 'density'=>'compact',     'shadow_strength'=>'soft',   'radius'=>10, 'max_width'=>1180 ),
        'x'         => array( 'design_preset'=>'minimal',   'density'=>'compact',     'shadow_strength'=>'none',   'radius'=>8,  'max_width'=>1120 ),
        'netflix'   => array( 'design_preset'=>'dark',      'density'=>'compact',     'shadow_strength'=>'strong', 'radius'=>6,  'max_width'=>1320 ),
        'tiktok'    => array( 'design_preset'=>'visual',    'density'=>'compact',     'shadow_strength'=>'strong', 'radius'=>12, 'max_width'=>1180 ),
        'google'    => array( 'design_preset'=>'minimal',   'density'=>'comfortable', 'shadow_strength'=>'soft',   'radius'=>12, 'max_width'=>1180 ),
        'telegram'  => array( 'design_preset'=>'compact',   'density'=>'comfortable', 'shadow_strength'=>'soft',   'radius'=>16, 'max_width'=>1120 ),
        'spotify'   => array( 'design_preset'=>'dark',      'density'=>'comfortable', 'shadow_strength'=>'strong', 'radius'=>18, 'max_width'=>1240 ),
        'microsoft' => array( 'design_preset'=>'technical', 'density'=>'compact',     'shadow_strength'=>'soft',   'radius'=>4,  'max_width'=>1240 ),
    );
    $family_slug = sanitize_key( $family_slug );
    return $profiles[ $family_slug ] ?? $profiles['academic-red'];
}

function ncu_style_takeover_preset_valid( $slug ) {
    $slug = sanitize_key( $slug );
    return 'custom-brand' === $slug || isset( ncu_style_takeover_index()[ $slug ] );
}

function ncu_style_takeover_is_active() {
    $s = ncu_get_settings();
    return ! empty( $s['aggressive_style_takeover'] );
}

/** Public token helper for companion plugins. */
function ncu_design_token( $key, $fallback = '' ) {
    $tokens = ncu_get_effective_design_tokens();
    $key = sanitize_key( $key );
    return array_key_exists( $key, $tokens ) ? $tokens[ $key ] : $fallback;
}

function ncu_style_font_stack( $key ) {
    $library = ncu_style_library_load();
    $fonts = isset( $library['fonts'] ) && is_array( $library['fonts'] ) ? $library['fonts'] : array();
    $key = sanitize_key( $key );
    if ( isset( $fonts[ $key ] ) && is_string( $fonts[ $key ] ) ) { return $fonts[ $key ]; }
    return '-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif';
}

function ncu_style_contrast_text( $hex ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( 6 !== strlen( $hex ) ) { return '#FFFFFF'; }
    $channels = array( hexdec( substr( $hex, 0, 2 ) ) / 255, hexdec( substr( $hex, 2, 2 ) ) / 255, hexdec( substr( $hex, 4, 2 ) ) / 255 );
    foreach ( $channels as &$channel ) { $channel = $channel <= 0.04045 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 ); }
    unset( $channel );
    $l = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    $white = 1.05 / ( $l + 0.05 );
    $black_l = 0.0;
    $black = ( $l + 0.05 ) / ( $black_l + 0.05 );
    return $black > $white ? '#000000' : '#FFFFFF';
}

function ncu_style_custom_tokens( $s ) {
    return array(
        'slug'           => 'custom-brand',
        'label'          => 'Custom Site Brand',
        'family_slug'    => 'custom',
        'family_label'   => 'Custom Site Brand',
        'primary'        => sanitize_hex_color( $s['style_custom_primary'] ) ?: '#B00000',
        'secondary'      => sanitize_hex_color( $s['style_custom_secondary'] ) ?: '#0B1F3A',
        'accent'         => sanitize_hex_color( $s['style_custom_accent'] ) ?: '#E53935',
        'surface'        => sanitize_hex_color( $s['style_custom_surface'] ) ?: '#FFFFFF',
        'surface_alt'    => sanitize_hex_color( $s['style_custom_surface_alt'] ) ?: '#F7F8FA',
        'text'           => sanitize_hex_color( $s['style_custom_text'] ) ?: '#111827',
        'muted'          => sanitize_hex_color( $s['style_custom_muted'] ) ?: '#667085',
        'border'         => sanitize_hex_color( $s['style_custom_border'] ) ?: '#E5E7EB',
        'link'           => sanitize_hex_color( $s['style_custom_primary'] ) ?: '#B00000',
        'heading_font'   => sanitize_key( $s['style_custom_heading_font'] ),
        'body_font'      => sanitize_key( $s['style_custom_body_font'] ),
        'ui_font'        => sanitize_key( $s['style_custom_ui_font'] ),
        'heading_weight' => 700,
        'body_weight'    => 400,
        'ui_weight'      => 600,
        'text_style'     => 'custom',
        'heading_tracking'=> '-0.018em',
        'ui_tracking'    => '0em',
    );
}

function ncu_get_effective_design_tokens( $settings = null ) {
    $s = is_array( $settings ) ? $settings : ncu_get_settings();
    $preset = isset( $s['style_takeover_preset'] ) ? sanitize_key( $s['style_takeover_preset'] ) : 'academic-reference';
    if ( 'custom-brand' === $preset ) {
        $tokens = ncu_style_custom_tokens( $s );
    } else {
        $index = ncu_style_takeover_index();
        $tokens = isset( $index[ $preset ] ) ? $index[ $preset ] : ( isset( $index['academic-reference'] ) ? $index['academic-reference'] : array() );
    }
    if ( empty( $tokens ) ) {
        $tokens = array(
            'slug'=>'fallback','label'=>'Fallback','family_slug'=>'fallback','family_label'=>'Fallback','primary'=>'#000000','secondary'=>'#1F2937','accent'=>'#000000','surface'=>'#FFFFFF','surface_alt'=>'#F7F8FA','text'=>'#111827','muted'=>'#667085','border'=>'#E5E7EB','link'=>'#000000','heading_font'=>'system-sans','body_font'=>'system-sans','ui_font'=>'system-sans','heading_weight'=>700,'body_weight'=>400,'ui_weight'=>600,'text_style'=>'fallback','heading_tracking'=>'-0.018em','ui_tracking'=>'0em',
        );
    }
    $tokens['heading_font_stack'] = ncu_style_font_stack( isset( $tokens['heading_font'] ) ? $tokens['heading_font'] : 'system-sans' );
    $tokens['body_font_stack']    = ncu_style_font_stack( isset( $tokens['body_font'] ) ? $tokens['body_font'] : 'system-sans' );
    $tokens['ui_font_stack']      = ncu_style_font_stack( isset( $tokens['ui_font'] ) ? $tokens['ui_font'] : 'system-sans' );
    $tokens['heading_tracking']   = isset( $tokens['heading_tracking'] ) ? (string) $tokens['heading_tracking'] : '-0.018em';
    $tokens['ui_tracking']        = isset( $tokens['ui_tracking'] ) ? (string) $tokens['ui_tracking'] : '0em';
    $tokens['on_primary']         = ncu_style_contrast_text( $tokens['primary'] );
    $tokens['on_secondary']       = ncu_style_contrast_text( $tokens['secondary'] );
    return apply_filters( 'ncu_design_tokens', $tokens, $s );
}

function ncu_style_hex_to_hsl( $hex ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( 6 !== strlen( $hex ) ) { return array( 215, 20, 50 ); }
    $r = hexdec( substr( $hex, 0, 2 ) ) / 255; $g = hexdec( substr( $hex, 2, 2 ) ) / 255; $b = hexdec( substr( $hex, 4, 2 ) ) / 255;
    $max = max( $r, $g, $b ); $min = min( $r, $g, $b ); $l = ( $max + $min ) / 2; $h = 0; $s = 0;
    if ( $max !== $min ) {
        $d = $max - $min; $s = $l > .5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
        if ( $max === $r ) { $h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 ); }
        elseif ( $max === $g ) { $h = ( $b - $r ) / $d + 2; }
        else { $h = ( $r - $g ) / $d + 4; }
        $h /= 6;
    }
    return array( (int) round( $h * 360 ), (int) round( $s * 100 ), (int) round( $l * 100 ) );
}

function ncu_style_hsl_to_hex( $h, $s, $l ) {
    $h = fmod( (float) $h + 360, 360 ) / 360; $s = max( 0, min( 100, (float) $s ) ) / 100; $l = max( 0, min( 100, (float) $l ) ) / 100;
    if ( 0 == $s ) { $r = $g = $b = $l; }
    else {
        $q = $l < .5 ? $l * ( 1 + $s ) : $l + $s - $l * $s; $p = 2 * $l - $q;
        $hue = function( $p1, $q1, $t ) { if ( $t < 0 ) { $t += 1; } if ( $t > 1 ) { $t -= 1; } if ( $t < 1/6 ) { return $p1 + ( $q1 - $p1 ) * 6 * $t; } if ( $t < 1/2 ) { return $q1; } if ( $t < 2/3 ) { return $p1 + ( $q1 - $p1 ) * ( 2/3 - $t ) * 6; } return $p1; };
        $r = $hue( $p, $q, $h + 1/3 ); $g = $hue( $p, $q, $h ); $b = $hue( $p, $q, $h - 1/3 );
    }
    return sprintf( '#%02x%02x%02x', round( $r * 255 ), round( $g * 255 ), round( $b * 255 ) );
}

/** A dark companion is generated from the selected preset, not from generic black. */
function ncu_style_dark_companion_tokens( $tokens = null ) {
    $t = is_array( $tokens ) ? $tokens : ncu_get_effective_design_tokens();
    list( $h, $sat ) = ncu_style_hex_to_hsl( $t['primary'] );
    if ( $sat < 10 ) { list( $h, $sat ) = ncu_style_hex_to_hsl( $t['accent'] ); }
    if ( $sat < 10 ) { $h = 215; $sat = 15; }
    $surface_sat = max( 8, min( 28, (int) round( $sat * .33 ) ) );
    $accent_sat = max( 52, min( 82, $sat ) );
    $dark = array(
        'bg'      => ncu_style_hsl_to_hex( $h, $surface_sat, 8 ),
        'surface' => ncu_style_hsl_to_hex( $h, min( 34, $surface_sat + 3 ), 12 ),
        'raised'  => ncu_style_hsl_to_hex( $h, min( 38, $surface_sat + 5 ), 17 ),
        'border'  => ncu_style_hsl_to_hex( $h, min( 35, $surface_sat + 3 ), 27 ),
        'primary' => ncu_style_hsl_to_hex( $h, $accent_sat, 66 ),
        'accent'  => ncu_style_hsl_to_hex( ( $h + 8 ) % 360, max( 48, min( 78, $sat ) ), 70 ),
        'text'    => '#EDF1F4',
        'muted'   => '#B7C0C8',
        'shadow'  => '0 20px 58px rgba(0,0,0,.34)',
    );
    return apply_filters( 'ncu_design_dark_tokens', $dark, $t );
}

function ncu_style_takeover_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $s = ncu_get_settings(); $active = ncu_get_effective_design_tokens( $s ); $dark = ncu_style_dark_companion_tokens( $active );
    ?>
    <div class="wrap ncu-admin ncu-style-authority-page">
        <?php ncu_admin_header( 'Templates · Skins & Popular Sites', 'Centralise site colour sheets, typography and text styling. Plugin layouts and functional behaviour remain plugin-owned.' ); ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="ncu_save_settings"><input type="hidden" name="ncu_return_page" value="nine-code-ultra-style-takeover"><?php wp_nonce_field( 'ncu_save_settings' ); ?>
            <section class="ncu-panel ncu-panel--padded ncu-style-authority-summary">
                <div><h2>Theme Design Authority</h2><p><strong>Aggressive Style Takeover</strong> does not delete or rewrite plugin design settings. It wins the final presentation cascade for the properties you enable. Switch it off and the plugin's own saved colours/fonts return.</p></div>
                <div class="ncu-style-live-chip"><span style="background:<?php echo esc_attr( $active['primary'] ); ?>"></span><strong><?php echo esc_html( $active['family_label'] . ' · ' . $active['label'] ); ?></strong></div>
                <div class="ncu-checks">
                    <?php ncu_checkbox( 'aggressive_style_takeover', 'Enable Aggressive Style Takeover', $s ); ?>
                    <?php ncu_checkbox( 'style_takeover_colors', 'Take over colour sheets', $s ); ?>
                    <?php ncu_checkbox( 'style_takeover_typography', 'Take over font families and weights', $s ); ?>
                    <?php ncu_checkbox( 'style_takeover_text_styles', 'Take over text/link/button colour semantics', $s ); ?>
                    <?php if ( ! empty( $s['admin_skin_enabled'] ) ) : ?>
                        <input type="hidden" name="ncu[style_takeover_admin]" value="0">
                        <p class="description"><strong>Admin visual authority:</strong> 9 Code Admin Workspace is active, so site-brand Style Takeover is intentionally kept out of wp-admin. This prevents site colours/WordPress blue accents from contaminating the black-and-white coding workspace.</p>
                    <?php else : ?>
                        <?php ncu_checkbox( 'style_takeover_admin', 'Use the selected site brand in the legacy 9 Code admin shell', $s ); ?>
                    <?php endif; ?>
                    <?php ncu_checkbox( 'style_takeover_respect_optout', 'Allow explicit preserve-style escape hatch for specialist components', $s ); ?>
                    <?php ncu_checkbox( 'style_takeover_strict', 'Strict plugin compatibility layer', $s ); ?>
                    <?php ncu_checkbox( 'style_takeover_dynamic_bridge', 'Bridge dynamic and inline plugin styles', $s ); ?>
                    <p class="description">Strict compatibility raises only colour/font/text authority above ordinary plugin selectors and republishes common variables locally. The dynamic bridge handles late-rendered components and inline <code>!important</code> colour/font declarations without changing plugin layout, spacing, positioning or behaviour.</p>
                </div>
                <p class="description">The takeover does not change grid, width, positioning, spacing, animation, media sizing, image treatment or plugin function. New 9-compatible plugins should consume the live design-token API; aggressive CSS exists mainly to bring independent/legacy plugins into the same visual system.</p>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <div class="ncu-style-library-heading"><div><h2>Templates</h2><p>Use <strong>Popular Sites</strong> when you want the whole site archetype to change, including its Skin, density, radius, width, shadow and Header/Footer personality. Use <strong>Skins</strong> when you only want a fast visual identity change inside the current site structure.</p></div><label class="ncu-style-search"><span>Find a style</span><input type="search" id="ncu-style-library-search" placeholder="Academic, Facebook, YouTube, green, purple..."></label></div>
                                <input type="hidden" id="ncu-template-selection-mode" name="ncu[template_selection_mode]" value="<?php echo esc_attr( $s['template_selection_mode'] ?? 'skin' ); ?>">
                <input type="hidden" id="ncu-template-apply-intent" name="ncu[template_apply_intent]" value="">
                <div class="ncu-template-layer">
                    <div class="ncu-style-library-heading"><div><h3>Popular Sites</h3><p>Full-site templates. Choosing one applies its reference Skin and matching structural personality so the whole site is immediately recognisable.</p></div></div>
                    <div class="ncu-style-card-grid ncu-popular-site-grid">
                    <?php foreach ( ncu_popular_site_templates() as $family_slug => $family ) :
                        $default_skin = ncu_popular_site_default_skin( $family_slug );
                        $index = ncu_style_takeover_index();
                        $sample = $index[ $default_skin ] ?? array(); ?>
                        <label class="ncu-style-card ncu-popular-site-card <?php echo ( $s['popular_site_template'] ?? 'academic-red' ) === $family_slug ? 'is-selected' : ''; ?>" data-ncu-popular-site-card>
                            <input type="radio" name="ncu[popular_site_template]" value="<?php echo esc_attr( $family_slug ); ?>" data-ncu-popular-site-input data-default-skin="<?php echo esc_attr( $default_skin ); ?>" <?php checked( $s['popular_site_template'] ?? 'academic-red', $family_slug ); ?>>
                            <span class="ncu-style-card__title"><strong><?php echo esc_html( $family['label'] ); ?></strong><small>Full-site template · applies Skin</small></span>
                            <span class="ncu-style-swatches"><i style="background:<?php echo esc_attr($sample['primary'] ?? '#000'); ?>"></i><i style="background:<?php echo esc_attr($sample['secondary'] ?? '#222'); ?>"></i><i style="background:<?php echo esc_attr($sample['accent'] ?? '#666'); ?>"></i><i style="background:<?php echo esc_attr($sample['surface_alt'] ?? '#eee'); ?>"></i></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="ncu-template-layer"><div class="ncu-style-library-heading"><div><h3>Skins</h3><p>Each Popular Site has ten Skin varieties. A Skin changes site identity quickly while preserving the current structural template.</p></div></div></div>
                <div class="ncu-style-current-preview">
                    <div class="ncu-style-preview-light" style="--p:<?php echo esc_attr($active['primary']); ?>;--s:<?php echo esc_attr($active['secondary']); ?>;--a:<?php echo esc_attr($active['accent']); ?>;--bg:<?php echo esc_attr($active['surface']); ?>;--soft:<?php echo esc_attr($active['surface_alt']); ?>;--text:<?php echo esc_attr($active['text']); ?>"><b>Light companion</b><span>Heading, body and action hierarchy</span><em>Action</em></div>
                    <div class="ncu-style-preview-light is-dark" style="--p:<?php echo esc_attr($dark['primary']); ?>;--s:<?php echo esc_attr($active['secondary']); ?>;--a:<?php echo esc_attr($dark['accent']); ?>;--bg:<?php echo esc_attr($dark['bg']); ?>;--soft:<?php echo esc_attr($dark['surface']); ?>;--text:<?php echo esc_attr($dark['text']); ?>"><b>Matched dark companion</b><span>Dim tonal surfaces, not pitch black</span><em>Action</em></div>
                </div>

                <label class="ncu-style-card ncu-style-card--custom <?php echo 'custom-brand' === $s['style_takeover_preset'] ? 'is-selected' : ''; ?>" data-ncu-style-card data-search="custom site brand">
                    <input type="radio" name="ncu[style_takeover_preset]" value="custom-brand" <?php checked( $s['style_takeover_preset'], 'custom-brand' ); ?>><span class="ncu-style-card__title"><strong>Custom Site Brand</strong><small>Your own reusable client palette</small></span>
                    <span class="ncu-style-swatches"><i style="background:<?php echo esc_attr($s['style_custom_primary']); ?>"></i><i style="background:<?php echo esc_attr($s['style_custom_secondary']); ?>"></i><i style="background:<?php echo esc_attr($s['style_custom_accent']); ?>"></i><i style="background:<?php echo esc_attr($s['style_custom_surface_alt']); ?>"></i></span>
                </label>

                <?php foreach ( ncu_style_takeover_registry() as $family ) : ?>
                    <details class="ncu-style-family" data-ncu-style-family <?php echo ( isset( $family['slug'] ) && ( sanitize_key( $family['slug'] ) === $active['family_slug'] || ( 'custom-brand' === $s['style_takeover_preset'] && 'academic-red' === sanitize_key( $family['slug'] ) ) ) ) ? 'open' : ''; ?>><summary><span><strong><?php echo esc_html( $family['label'] ); ?></strong><small><?php echo esc_html( $family['description'] ); ?></small></span><b>10 variants</b></summary><div class="ncu-style-card-grid">
                        <?php foreach ( $family['variants'] as $variant ) : $slug = sanitize_key( $variant['slug'] ); $vDark = ncu_style_dark_companion_tokens( $variant ); ?>
                            <label class="ncu-style-card <?php echo $s['style_takeover_preset'] === $slug ? 'is-selected' : ''; ?>" data-ncu-style-card data-search="<?php echo esc_attr( strtolower( $family['label'] . ' ' . $variant['label'] . ' ' . $slug ) ); ?>">
                                <input type="radio" name="ncu[style_takeover_preset]" value="<?php echo esc_attr( $slug ); ?>" data-ncu-skin-input data-family="<?php echo esc_attr( sanitize_key( $family['slug'] ?? '' ) ); ?>" <?php checked( $s['style_takeover_preset'], $slug ); ?>>
                                <span class="ncu-style-card__title"><strong><?php echo esc_html( $variant['label'] ); ?></strong><small><?php echo esc_html( $family['label'] ); ?></small></span>
                                <span class="ncu-style-swatches"><i style="background:<?php echo esc_attr($variant['primary']); ?>"></i><i style="background:<?php echo esc_attr($variant['secondary']); ?>"></i><i style="background:<?php echo esc_attr($variant['accent']); ?>"></i><i style="background:<?php echo esc_attr($variant['surface_alt']); ?>"></i></span>
                                <span class="ncu-style-dark-strip" title="Matched dark companion"><i style="background:<?php echo esc_attr($vDark['bg']); ?>"></i><i style="background:<?php echo esc_attr($vDark['surface']); ?>"></i><i style="background:<?php echo esc_attr($vDark['primary']); ?>"></i></span>
                            </label>
                        <?php endforeach; ?>
                    </div></details>
                <?php endforeach; ?>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Custom Site Brand</h2><p>Use this instead of a reference family when a client has an established identity. Change it once here and every token-aware surface follows it.</p>
                <div class="ncu-grid ncu-style-custom-grid">
                    <?php foreach ( array('style_custom_primary'=>'Primary','style_custom_secondary'=>'Secondary','style_custom_accent'=>'Accent','style_custom_surface'=>'Surface','style_custom_surface_alt'=>'Soft surface','style_custom_text'=>'Text','style_custom_muted'=>'Muted text','style_custom_border'=>'Border') as $key=>$label ) : ?>
                        <label><span><?php echo esc_html($label); ?></span><input type="color" name="ncu[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($s[$key]); ?>"></label>
                    <?php endforeach; ?>
                    <?php $font_choices = array('system-sans'=>'System / Segoe / Inter','roboto'=>'Roboto-compatible stack','humanist-sans'=>'Humanist Sans','neo-grotesk'=>'Neo Grotesk','geometric-sans'=>'Geometric Sans','rounded-sans'=>'Rounded Sans','academic-serif'=>'Academic Serif','editorial-serif'=>'Editorial Serif'); ?>
                    <?php foreach ( array('style_custom_heading_font'=>'Heading font','style_custom_body_font'=>'Body font','style_custom_ui_font'=>'UI/button font') as $key=>$label ) : ?>
                        <label><span><?php echo esc_html($label); ?></span><select name="ncu[<?php echo esc_attr($key); ?>]"><?php foreach($font_choices as $fk=>$fl): ?><option value="<?php echo esc_attr($fk); ?>" <?php selected($s[$key],$fk); ?>><?php echo esc_html($fl); ?></option><?php endforeach; ?></select></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Portable Style Pack</h2>
                <p>Move only the active design authority settings to another 9Core 15 site without copying client content, branding identity, builders or plugin configuration.</p>
                <div class="ncu-tools">
                    <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ncu_export_style_pack' ), 'ncu_export_style_pack' ) ); ?>">Export current .9style package</a>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="ncu_import_style_pack"><?php wp_nonce_field( 'ncu_import_style_pack' ); ?>
                        <input type="file" name="ncu_style_pack" accept="application/json,.json,.9style" required> <button class="button">Validate & import style</button>
                    </form>
                </div>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Compatibility contract</h2>
                <div class="ncu-audit-list">
                    <p><strong>Preferred path:</strong> plugins read the live <code>ncu_get_effective_design_tokens()</code> API or the <code>--ncu-design-*</code> CSS variables.</p>
                    <p><strong>Aggressive path:</strong> common plugin colour variables, headings, copy, links, controls, cards and panels are remapped late in the cascade with colour/font-only rules.</p>
                    <p><strong>Layout protection:</strong> no takeover rule changes display, position, grid/flex, dimensions, margins, padding, transforms or animation.</p>
                    <p><strong>Specialist escape hatch:</strong> add <code>data-ncu-style-preserve</code> or <code>.ncu-preserve-style</code> to a component that must retain specialist colouring, for example a chart legend or status heatmap.</p>
                    <p><strong>Dark-mode pairing:</strong> every preset generates its own tonal dark companion from the selected hue family, so dark mode remains visually related to the light design.</p>
                </div>
            </section>
                        <script>
            document.addEventListener('DOMContentLoaded',function(){
                var mode=document.getElementById('ncu-template-selection-mode');
                var intent=document.getElementById('ncu-template-apply-intent');
                document.querySelectorAll('[data-ncu-popular-site-input]').forEach(function(input){input.addEventListener('change',function(){if(!this.checked)return;if(mode)mode.value='popular';if(intent)intent.value='popular';var skin=this.getAttribute('data-default-skin');var skinInput=document.querySelector('[data-ncu-skin-input][value="'+skin+'"]');if(skinInput)skinInput.checked=true;document.querySelectorAll('[data-ncu-style-family]').forEach(function(d){var radio=d.querySelector('[data-ncu-skin-input][data-family="'+input.value+'"]');if(radio)d.open=true;});});});
                document.querySelectorAll('[data-ncu-skin-input]').forEach(function(input){input.addEventListener('change',function(){if(!this.checked)return;if(mode)mode.value='skin';if(intent)intent.value='skin';var family=this.getAttribute('data-family');var familyInput=document.querySelector('[data-ncu-popular-site-input][value="'+family+'"]');if(familyInput)familyInput.checked=true;});});
            });
            </script>
<p class="submit"><button class="button button-primary button-hero">Save Style Takeover</button></p>
        </form>
    </div>
    <?php
}

add_action( 'admin_head', 'ncu_style_takeover_admin_accent', 30 );
function ncu_style_takeover_admin_accent() {
    if ( ! function_exists( 'ncu_get_settings' ) ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['aggressive_style_takeover'] ) || empty( $s['style_takeover_admin'] ) || empty( $s['admin_style_enabled'] ) || ! empty( $s['admin_skin_enabled'] ) ) { return; }
    $t = ncu_get_effective_design_tokens( $s );
    echo '<style id="ncu-style-admin-accent">:root{--ncu-admin-accent:' . esc_attr($t['primary']) . ';--ncu-admin-accent-2:' . esc_attr($t['secondary']) . ';}body[class*="toplevel_page_nine-code-ultra"] .button-primary,body[class*="nine-code-ultra"] .button-primary{background:' . esc_attr($t['primary']) . '!important;border-color:' . esc_attr($t['primary']) . '!important;color:' . esc_attr($t['on_primary']) . '!important}#wpadminbar #wp-admin-bar-ncu-client-brand>.ab-item{border-bottom-color:' . esc_attr($t['primary']) . '!important}</style>';
}
