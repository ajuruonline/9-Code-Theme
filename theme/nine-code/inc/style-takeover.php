<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ncu_theme_style_library_load() {
    static $library = null;
    if ( null !== $library ) { return $library; }
    $library = array( 'families'=>array(), 'fonts'=>array() );
    $file = NCU_THEME_DIR . '/assets/design/style-presets.json';
    if ( is_readable( $file ) ) {
        $decoded = json_decode( (string) file_get_contents( $file ), true );
        if ( is_array( $decoded ) ) { $library = $decoded; }
    }
    return $library;
}

function ncu_theme_style_index() {
    static $index = null;
    if ( null !== $index ) { return $index; }
    $index = array(); $library = ncu_theme_style_library_load();
    foreach ( isset( $library['families'] ) && is_array( $library['families'] ) ? $library['families'] : array() as $family ) {
        foreach ( isset( $family['variants'] ) && is_array( $family['variants'] ) ? $family['variants'] : array() as $variant ) {
            if ( empty( $variant['slug'] ) ) { continue; }
            $variant['family_slug'] = isset( $family['slug'] ) ? sanitize_key( $family['slug'] ) : '';
            $variant['family_label'] = isset( $family['label'] ) ? sanitize_text_field( $family['label'] ) : '';
            $index[ sanitize_key( $variant['slug'] ) ] = $variant;
        }
    }
    return $index;
}

function ncu_theme_style_font_stack( $key ) {
    $library = ncu_theme_style_library_load(); $fonts = isset( $library['fonts'] ) && is_array( $library['fonts'] ) ? $library['fonts'] : array();
    $key = sanitize_key( $key );
    return isset( $fonts[$key] ) && is_string( $fonts[$key] ) ? $fonts[$key] : '-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif';
}

function ncu_theme_style_contrast_text( $hex ) {
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

function ncu_theme_effective_design_tokens() {
    $s = ncu_theme_settings();
    if ( ncu_theme_core_compatible() && function_exists( 'ncu_get_effective_design_tokens' ) ) { return ncu_get_effective_design_tokens( $s ); }
    $preset = isset($s['style_takeover_preset']) ? sanitize_key($s['style_takeover_preset']) : 'academic-reference';
    if ( 'custom-brand' === $preset ) {
        $t = array(
            'slug'=>'custom-brand','label'=>'Custom Site Brand','family_slug'=>'custom','family_label'=>'Custom Site Brand',
            'primary'=>ncu_safe_color($s['style_custom_primary'],'#B00000'),'secondary'=>ncu_safe_color($s['style_custom_secondary'],'#0B1F3A'),'accent'=>ncu_safe_color($s['style_custom_accent'],'#E53935'),
            'surface'=>ncu_safe_color($s['style_custom_surface'],'#FFFFFF'),'surface_alt'=>ncu_safe_color($s['style_custom_surface_alt'],'#F7F8FA'),'text'=>ncu_safe_color($s['style_custom_text'],'#111827'),'muted'=>ncu_safe_color($s['style_custom_muted'],'#667085'),'border'=>ncu_safe_color($s['style_custom_border'],'#E5E7EB'),
            'link'=>ncu_safe_color($s['style_custom_primary'],'#B00000'),'heading_font'=>sanitize_key($s['style_custom_heading_font']),'body_font'=>sanitize_key($s['style_custom_body_font']),'ui_font'=>sanitize_key($s['style_custom_ui_font']),'heading_weight'=>700,'body_weight'=>400,'ui_weight'=>600,'text_style'=>'custom','heading_tracking'=>'-0.018em','ui_tracking'=>'0em',
        );
    } else {
        $index = ncu_theme_style_index(); $t = isset($index[$preset]) ? $index[$preset] : (isset($index['academic-reference'])?$index['academic-reference']:array());
    }
    if ( empty($t) ) { $t=array('slug'=>'fallback','label'=>'Fallback','family_slug'=>'fallback','family_label'=>'Fallback','primary'=>'#000000','secondary'=>'#1F2937','accent'=>'#000000','surface'=>'#FFFFFF','surface_alt'=>'#F7F8FA','text'=>'#111827','muted'=>'#667085','border'=>'#E5E7EB','link'=>'#000000','heading_font'=>'system-sans','body_font'=>'system-sans','ui_font'=>'system-sans','heading_weight'=>700,'body_weight'=>400,'ui_weight'=>600,'text_style'=>'fallback','heading_tracking'=>'-0.018em','ui_tracking'=>'0em'); }
    $t['heading_font_stack']=ncu_theme_style_font_stack($t['heading_font']);$t['body_font_stack']=ncu_theme_style_font_stack($t['body_font']);$t['ui_font_stack']=ncu_theme_style_font_stack($t['ui_font']);$t['heading_tracking']=isset($t['heading_tracking'])?(string)$t['heading_tracking']:'-0.018em';$t['ui_tracking']=isset($t['ui_tracking'])?(string)$t['ui_tracking']:'0em';$t['on_primary']=ncu_theme_style_contrast_text($t['primary']);$t['on_secondary']=ncu_theme_style_contrast_text($t['secondary']);
    return $t;
}

function ncu_theme_style_dark_companion_tokens() {
    $t = ncu_theme_effective_design_tokens();
    list($h,$sat)=ncu_theme_hex_to_hsl($t['primary']); if($sat<10){ list($h,$sat)=ncu_theme_hex_to_hsl($t['accent']); } if($sat<10){$h=215;$sat=15;}
    $ss=max(8,min(28,(int)round($sat*.33)));$as=max(52,min(82,$sat));
    return array('bg'=>ncu_theme_hsl_to_hex($h,$ss,8),'surface'=>ncu_theme_hsl_to_hex($h,min(34,$ss+3),12),'raised'=>ncu_theme_hsl_to_hex($h,min(38,$ss+5),17),'border'=>ncu_theme_hsl_to_hex($h,min(35,$ss+3),27),'primary'=>ncu_theme_hsl_to_hex($h,$as,66),'accent'=>ncu_theme_hsl_to_hex($h,$as,66),'secondary'=>ncu_theme_hsl_to_hex(($h+8)%360,max(48,min(78,$sat)),70),'text'=>'#EDF1F4','muted'=>'#B7C0C8','shadow'=>'0 20px 58px rgba(0,0,0,.34)');
}

add_filter( 'body_class', 'ncu_theme_style_takeover_body_classes_safe', 50 );
function ncu_theme_style_takeover_body_classes_safe( $classes ) {
    try { return ncu_theme_style_takeover_body_classes( $classes ); }
    catch ( \Throwable $e ) { error_log( '[Nine Code ' . ( defined( 'NCU_THEME_VERSION' ) ? NCU_THEME_VERSION : '' ) . '] style body-class fallback: ' . $e->getMessage() ); return is_array( $classes ) ? $classes : array(); }
}
function ncu_theme_style_takeover_body_classes( $classes ) {
    if ( function_exists( 'ncu_theme_allows_style_takeover' ) && ! ncu_theme_allows_style_takeover() ) { return $classes; }
    $s=ncu_theme_settings();
    if ( ! empty($s['aggressive_style_takeover']) ) {
        $classes[]='ncu-style-takeover';$classes[]='ncu-style-' . sanitize_html_class($s['style_takeover_preset']);
        if(!empty($s['style_takeover_strict'])){$classes[]='ncu-takeover-strict';}
        if(!empty($s['style_takeover_respect_optout'])){$classes[]='ncu-takeover-respect-optout';}
    }
    return $classes;
}

add_action( 'wp_enqueue_scripts', 'ncu_theme_style_takeover_css_safe', 9999 );
function ncu_theme_style_takeover_css_safe() {
    try { ncu_theme_style_takeover_css(); }
    catch ( \Throwable $e ) { error_log( '[Nine Code ' . ( defined( 'NCU_THEME_VERSION' ) ? NCU_THEME_VERSION : '' ) . '] style takeover fallback: ' . $e->getMessage() ); }
}
function ncu_theme_style_hex_rgb_triplet( $hex ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) { return '0,0,0'; }
    return hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) );
}

function ncu_theme_style_takeover_css() {
    if ( function_exists( 'ncu_theme_allows_style_takeover' ) && ! ncu_theme_allows_style_takeover() ) { return; }
    if ( ! wp_style_is( 'nine-code-ultra-main', 'enqueued' ) ) { return; }
    $s = ncu_theme_settings();
    if ( empty( $s['aggressive_style_takeover'] ) ) { return; }

    /* A dedicated late handle is intentional. v3.0-v3.3 attached the takeover
     * to the main theme stylesheet; a plugin enqueued later could therefore win
     * the cascade even though the feature was called aggressive. */
    $handle = 'nine-code-ultra-style-authority';
    $deps   = array( 'nine-code-ultra-main' );
    global $wp_styles;
    if ( isset( $wp_styles->queue ) && is_array( $wp_styles->queue ) ) {
        foreach ( $wp_styles->queue as $queued ) {
            $queued = is_scalar( $queued ) ? (string) $queued : '';
            if ( $queued && $handle !== $queued && ! in_array( $queued, $deps, true ) ) { $deps[] = $queued; }
        }
    }
    wp_enqueue_style( $handle, NCU_THEME_URI . '/assets/css/style-authority.css', $deps, NCU_THEME_VERSION );

    $t = ncu_theme_effective_design_tokens();
    $css = ':root{' .
        '--ncu-design-primary:' . $t['primary'] . ';--ncu-design-primary-rgb:' . ncu_theme_style_hex_rgb_triplet( $t['primary'] ) . ';' .
        '--ncu-design-secondary:' . $t['secondary'] . ';--ncu-design-secondary-rgb:' . ncu_theme_style_hex_rgb_triplet( $t['secondary'] ) . ';' .
        '--ncu-design-accent:' . $t['accent'] . ';--ncu-design-accent-rgb:' . ncu_theme_style_hex_rgb_triplet( $t['accent'] ) . ';' .
        '--ncu-design-surface:' . $t['surface'] . ';--ncu-design-surface-alt:' . $t['surface_alt'] . ';--ncu-design-text:' . $t['text'] . ';--ncu-design-muted:' . $t['muted'] . ';--ncu-design-border:' . $t['border'] . ';--ncu-design-link:' . $t['link'] . ';--ncu-design-on-primary:' . $t['on_primary'] . ';--ncu-design-on-secondary:' . $t['on_secondary'] . ';' .
        '--ncu-design-font-heading:' . $t['heading_font_stack'] . ';--ncu-design-font-body:' . $t['body_font_stack'] . ';--ncu-design-font-ui:' . $t['ui_font_stack'] . ';--ncu-design-heading-weight:' . absint( isset( $t['heading_weight'] ) ? $t['heading_weight'] : 700 ) . ';--ncu-design-body-weight:' . absint( isset( $t['body_weight'] ) ? $t['body_weight'] : 400 ) . ';--ncu-design-ui-weight:' . absint( isset( $t['ui_weight'] ) ? $t['ui_weight'] : 600 ) . ';--ncu-design-heading-tracking:' . esc_attr( $t['heading_tracking'] ) . ';--ncu-design-ui-tracking:' . esc_attr( $t['ui_tracking'] ) . ';' .
        '--ncu-design-success:#198754;--ncu-design-warning:#B7791F;--ncu-design-danger:#C62828;--ncu-design-info:#0B74B8;}' . "\n";

    $dark = ncu_theme_style_dark_companion_tokens();
    $dark_on_primary = ncu_theme_style_contrast_text( $dark['accent'] );
    $dark_on_secondary = ncu_theme_style_contrast_text( $dark['secondary'] );
    $css .= 'html[data-ncu-mode="dark"] body.ncu-style-takeover{' .
        '--ncu-design-primary:' . $dark['accent'] . '!important;--ncu-design-primary-rgb:' . ncu_theme_style_hex_rgb_triplet( $dark['accent'] ) . '!important;--ncu-design-secondary:' . $dark['secondary'] . '!important;--ncu-design-secondary-rgb:' . ncu_theme_style_hex_rgb_triplet( $dark['secondary'] ) . '!important;--ncu-design-accent:' . $dark['accent'] . '!important;--ncu-design-accent-rgb:' . ncu_theme_style_hex_rgb_triplet( $dark['accent'] ) . '!important;--ncu-design-surface:' . $dark['bg'] . '!important;--ncu-design-surface-alt:' . $dark['surface'] . '!important;--ncu-design-text:' . $dark['text'] . '!important;--ncu-design-muted:' . $dark['muted'] . '!important;--ncu-design-border:' . $dark['border'] . '!important;--ncu-design-link:' . $dark['accent'] . '!important;--ncu-design-on-primary:' . $dark_on_primary . '!important;--ncu-design-on-secondary:' . $dark_on_secondary . '!important;' .
        '--wp--preset--color--primary:' . $dark['accent'] . '!important;--wp--preset--color--secondary:' . $dark['secondary'] . '!important;--wp--preset--color--contrast:' . $dark['text'] . '!important;--wp--preset--color--base:' . $dark['bg'] . '!important;' .
        '--e-global-color-primary:' . $dark['accent'] . '!important;--e-global-color-secondary:' . $dark['secondary'] . '!important;--e-global-color-text:' . $dark['text'] . '!important;--e-global-color-accent:' . $dark['accent'] . '!important;' .
        '--bs-primary:' . $dark['accent'] . '!important;--bs-primary-rgb:' . ncu_theme_style_hex_rgb_triplet( $dark['accent'] ) . '!important;--bs-secondary:' . $dark['secondary'] . '!important;--bs-secondary-rgb:' . ncu_theme_style_hex_rgb_triplet( $dark['secondary'] ) . '!important;--bs-body-color:' . $dark['text'] . '!important;--bs-body-bg:' . $dark['bg'] . '!important;}' . "\n";

    if ( ! empty( $s['style_takeover_colors'] ) ) {
        $css .= 'body.ncu-style-takeover{' .
            '--ncu-accent:var(--ncu-design-primary)!important;--ncu-surface:var(--ncu-design-surface)!important;--ncu-soft:var(--ncu-design-surface-alt)!important;--ncu-text:var(--ncu-design-text)!important;--ncu-muted:var(--ncu-design-muted)!important;--ncu-border:var(--ncu-design-border)!important;' .
            '--primary-color:var(--ncu-design-primary)!important;--primary:var(--ncu-design-primary)!important;--accent-color:var(--ncu-design-accent)!important;--accent:var(--ncu-design-accent)!important;--theme-color:var(--ncu-design-primary)!important;--brand-color:var(--ncu-design-primary)!important;--link-color:var(--ncu-design-link)!important;--text-color:var(--ncu-design-text)!important;--muted-color:var(--ncu-design-muted)!important;--background-color:var(--ncu-design-surface)!important;--surface-color:var(--ncu-design-surface)!important;--border-color:var(--ncu-design-border)!important;' .
            '--wp--preset--color--primary:var(--ncu-design-primary)!important;--wp--preset--color--secondary:var(--ncu-design-secondary)!important;--wp--preset--color--contrast:var(--ncu-design-text)!important;--wp--preset--color--base:var(--ncu-design-surface)!important;' .
            '--e-global-color-primary:var(--ncu-design-primary)!important;--e-global-color-secondary:var(--ncu-design-secondary)!important;--e-global-color-text:var(--ncu-design-text)!important;--e-global-color-accent:var(--ncu-design-accent)!important;' .
            '--bs-primary:var(--ncu-design-primary)!important;--bs-primary-rgb:var(--ncu-design-primary-rgb)!important;--bs-secondary:var(--ncu-design-secondary)!important;--bs-secondary-rgb:var(--ncu-design-secondary-rgb)!important;--bs-body-color:var(--ncu-design-text)!important;--bs-body-bg:var(--ncu-design-surface)!important;' .
            '--woocommerce:var(--ncu-design-primary)!important;--wc-primary:var(--ncu-design-primary)!important;--wc-secondary:var(--ncu-design-secondary)!important;--wc-content-bg:var(--ncu-design-surface)!important;' .
            '--mdc-theme-primary:var(--ncu-design-primary)!important;--mdc-theme-secondary:var(--ncu-design-secondary)!important;--md-sys-color-primary:var(--ncu-design-primary)!important;--md-sys-color-secondary:var(--ncu-design-secondary)!important;--md-sys-color-surface:var(--ncu-design-surface)!important;--md-sys-color-on-surface:var(--ncu-design-text)!important;' .
            '--ion-color-primary:var(--ncu-design-primary)!important;--swiper-theme-color:var(--ncu-design-primary)!important;--bb-primary-color:var(--ncu-design-primary)!important;}' . "\n";
        $css .= 'body.ncu-style-takeover{background-color:var(--ncu-design-surface)!important;color:var(--ncu-design-text)!important;}' .
            'body.ncu-style-takeover ::selection{background:var(--ncu-design-primary);color:var(--ncu-design-on-primary);}' . "\n";

        /* Standard cascade remains available when strict mode is disabled.
         * Strict mode emits its own opt-out-aware component selectors below. */
        if ( empty( $s['style_takeover_strict'] ) ) {
            $css .= 'body.ncu-style-takeover{background-color:var(--ncu-design-surface)!important;color:var(--ncu-design-text)!important;}' .
                'body.ncu-style-takeover :where(a){color:var(--ncu-design-link)!important;}' .
                'body.ncu-style-takeover :where(button,input[type="button"],input[type="submit"],input[type="reset"],.button,.btn,[class*="button"],[class*="cta"]){background-color:var(--ncu-design-primary)!important;border-color:var(--ncu-design-primary)!important;color:var(--ncu-design-on-primary)!important;}' .
                'body.ncu-style-takeover :where(input:not([type="button"]):not([type="submit"]):not([type="reset"]),select,textarea){background-color:var(--ncu-design-surface)!important;border-color:var(--ncu-design-border)!important;color:var(--ncu-design-text)!important;}' .
                'body.ncu-style-takeover :where([class*="card"],[class*="panel"],[class*="box"],[class*="modal"],[class*="drawer"],[class*="popover"],[class*="dropdown"]){border-color:var(--ncu-design-border)!important;}' .
                'body.ncu-style-takeover :where(.card,.panel,.box,.modal,.drawer,.popover,.dropdown,.wp-block-group.is-style-ncu-card){background-color:var(--ncu-design-surface-alt)!important;color:var(--ncu-design-text)!important;}' .
                'body.ncu-style-takeover :where(hr,.wp-block-separator){border-color:var(--ncu-design-border)!important;}' .
                'body.ncu-style-takeover :where(.has-text-color,[style*="color:"]):not(.success):not(.warning):not(.error):not(.danger):not(.info){color:var(--ncu-design-text)!important;}' .
                'body.ncu-style-takeover :where(.has-background,[style*="background-color:"]):not(.success):not(.warning):not(.error):not(.danger):not(.info){background-color:var(--ncu-design-surface-alt)!important;}' .
                'body.ncu-style-takeover :where(.has-primary-background-color){background-color:var(--ncu-design-primary)!important;color:var(--ncu-design-on-primary)!important;}' .
                'body.ncu-style-takeover :where(.has-secondary-background-color){background-color:var(--ncu-design-secondary)!important;color:var(--ncu-design-on-secondary)!important;}' .
                'body.ncu-style-takeover :where(.has-border-color,[style*="border-color:"]){border-color:var(--ncu-design-border)!important;}' .
                'body.ncu-style-takeover ::selection{background:var(--ncu-design-primary);color:var(--ncu-design-on-primary);}' . "\n";
        }
    }

    if ( ! empty( $s['style_takeover_typography'] ) && empty( $s['style_takeover_strict'] ) ) {
        $css .= 'body.ncu-style-takeover :where(p,li,dd,dt,label,input,select,textarea,button,a,blockquote,figcaption,summary,table,th,td,span):not([class*="dashicons"]):not([class*="fa-"]):not([class*="material-icons"]):not([class*="eicon"]):not([class*="icon-"]):not(.ab-icon){font-family:var(--ncu-design-font-body)!important;font-weight:var(--ncu-design-body-weight);}' .
            'body.ncu-style-takeover :where(h1,h2,h3,h4,h5,h6,.entry-title,[class*="heading"],[class*="title"]):not([class*="icon"]){font-family:var(--ncu-design-font-heading)!important;font-weight:var(--ncu-design-heading-weight)!important;letter-spacing:var(--ncu-design-heading-tracking)!important;}' .
            'body.ncu-style-takeover :where(button,input[type="button"],input[type="submit"],input[type="reset"],.button,.btn){font-family:var(--ncu-design-font-ui)!important;font-weight:var(--ncu-design-ui-weight)!important;letter-spacing:var(--ncu-design-ui-tracking)!important;}' . "\n";
    }
    if ( ! empty( $s['style_takeover_text_styles'] ) && empty( $s['style_takeover_strict'] ) ) {
        $css .= 'body.ncu-style-takeover :where(h1,h2,h3,h4,h5,h6,.entry-title,[class*="heading"]){color:var(--ncu-design-text)!important;}' .
            'body.ncu-style-takeover :where(p,li,dd,dt,label,blockquote,figcaption,table,th,td){color:var(--ncu-design-text)!important;}' .
            'body.ncu-style-takeover :where(.muted,.meta,.metadata,.caption,[class*="muted"],[class*="meta"]){color:var(--ncu-design-muted)!important;}' . "\n";
    }

    /* Strict compatibility is the v3.4 correction. The repeated class is legal CSS
     * and deliberately raises specificity without IDs or layout properties. */
    if ( ! empty( $s['style_takeover_strict'] ) ) {
        $scope = 'html body.ncu-style-takeover.ncu-takeover-strict.ncu-takeover-strict.ncu-takeover-strict';
        $preserve = ! empty( $s['style_takeover_respect_optout'] ) ? ':not(:where(.ncu-preserve-style,.ncu-preserve-style *,[data-ncu-style-preserve],[data-ncu-style-preserve] *,#wpadminbar,#wpadminbar *))' : ':not(:where(#wpadminbar,#wpadminbar *))';
        $any = $scope . ' *' . $preserve;

        if ( ! empty( $s['style_takeover_colors'] ) ) {
            /* Local custom-property declarations on plugin children beat inherited body
             * variables. Republish the bridge on descendants at strict specificity. */
            $css .= $any . '{' .
                '--primary-color:var(--ncu-design-primary)!important;--primary:var(--ncu-design-primary)!important;--accent-color:var(--ncu-design-accent)!important;--accent:var(--ncu-design-accent)!important;--theme-color:var(--ncu-design-primary)!important;--brand-color:var(--ncu-design-primary)!important;--link-color:var(--ncu-design-link)!important;--text-color:var(--ncu-design-text)!important;--muted-color:var(--ncu-design-muted)!important;--background-color:var(--ncu-design-surface)!important;--surface-color:var(--ncu-design-surface)!important;--border-color:var(--ncu-design-border)!important;' .
                '--wp--preset--color--primary:var(--ncu-design-primary)!important;--wp--preset--color--secondary:var(--ncu-design-secondary)!important;--wp--preset--color--contrast:var(--ncu-design-text)!important;--wp--preset--color--base:var(--ncu-design-surface)!important;' .
                '--e-global-color-primary:var(--ncu-design-primary)!important;--e-global-color-secondary:var(--ncu-design-secondary)!important;--e-global-color-text:var(--ncu-design-text)!important;--e-global-color-accent:var(--ncu-design-accent)!important;' .
                '--bs-primary:var(--ncu-design-primary)!important;--bs-primary-rgb:var(--ncu-design-primary-rgb)!important;--bs-secondary:var(--ncu-design-secondary)!important;--bs-secondary-rgb:var(--ncu-design-secondary-rgb)!important;--bs-body-color:var(--ncu-design-text)!important;--bs-body-bg:var(--ncu-design-surface)!important;' .
                '--woocommerce:var(--ncu-design-primary)!important;--wc-primary:var(--ncu-design-primary)!important;--wc-secondary:var(--ncu-design-secondary)!important;--wc-content-bg:var(--ncu-design-surface)!important;' .
                '--mdc-theme-primary:var(--ncu-design-primary)!important;--mdc-theme-secondary:var(--ncu-design-secondary)!important;--md-sys-color-primary:var(--ncu-design-primary)!important;--md-sys-color-secondary:var(--ncu-design-secondary)!important;--ion-color-primary:var(--ncu-design-primary)!important;--swiper-theme-color:var(--ncu-design-primary)!important;}' . "\n";

            $css .= $scope . ' :is(a)' . $preserve . '{color:var(--ncu-design-link)!important;}' .
                $scope . ' :is(button,input[type="button"],input[type="submit"],input[type="reset"],[role="button"],.button,.btn,[class*="button"],[class*="cta"])' . $preserve . '{background-color:var(--ncu-design-primary)!important;border-color:var(--ncu-design-primary)!important;color:var(--ncu-design-on-primary)!important;}' .
                $scope . ' :is(input:not([type="button"]):not([type="submit"]):not([type="reset"]),select,textarea)' . $preserve . '{background-color:var(--ncu-design-surface)!important;border-color:var(--ncu-design-border)!important;color:var(--ncu-design-text)!important;}' .
                $scope . ' :is(.card,.panel,.box,.modal,.drawer,.popover,.dropdown,[class*="card"],[class*="panel"],[class*="box"],[class*="modal"],[class*="drawer"],[class*="popover"],[class*="dropdown"])' . $preserve . '{border-color:var(--ncu-design-border)!important;}' .
                $scope . ' :is(.card,.panel,.box,.modal,.drawer,.popover,.dropdown)' . $preserve . '{background-color:var(--ncu-design-surface-alt)!important;color:var(--ncu-design-text)!important;}' .
                $scope . ' :is(hr,.wp-block-separator)' . $preserve . '{border-color:var(--ncu-design-border)!important;}' .
                $scope . ' :is(.has-text-color,[style*="color:"])' . $preserve . ':not(:is(.success,.warning,.error,.danger,.info,.is-success,.is-warning,.is-error,.is-danger,.is-info)){color:var(--ncu-design-text)!important;}' .
                $scope . ' :is(.has-background,[style*="background-color:"])' . $preserve . ':not(:is(.success,.warning,.error,.danger,.info,.is-success,.is-warning,.is-error,.is-danger,.is-info)){background-color:var(--ncu-design-surface-alt)!important;}' .
                $scope . ' :is(.has-border-color,[style*="border-color:"])' . $preserve . '{border-color:var(--ncu-design-border)!important;}' . "\n";
        }
        if ( ! empty( $s['style_takeover_typography'] ) ) {
            $css .= $scope . ' :is(p,li,dd,dt,label,input,select,textarea,a,blockquote,figcaption,summary,table,th,td,span,[class*="text"],[class*="label"],[class*="description"],[class*="caption"])' . $preserve . ':not(:is([class*="dashicons"],[class*="fa-"],[class*="material-icons"],[class*="eicon"],[class*="icon-"]))' . '{font-family:var(--ncu-design-font-body)!important;font-weight:var(--ncu-design-body-weight)!important;}' .
                $scope . ' :is(h1,h2,h3,h4,h5,h6,.entry-title,[class*="heading"],[class*="title"],[class*="headline"])' . $preserve . ':not([class*="icon"]){font-family:var(--ncu-design-font-heading)!important;font-weight:var(--ncu-design-heading-weight)!important;letter-spacing:var(--ncu-design-heading-tracking)!important;}' .
                $scope . ' :is(button,input[type="button"],input[type="submit"],input[type="reset"],[role="button"],.button,.btn,[class*="button"],[class*="cta"])' . $preserve . '{font-family:var(--ncu-design-font-ui)!important;font-weight:var(--ncu-design-ui-weight)!important;letter-spacing:var(--ncu-design-ui-tracking)!important;}' . "\n";
        }
        if ( ! empty( $s['style_takeover_text_styles'] ) ) {
            $css .= $scope . ' :is(h1,h2,h3,h4,h5,h6,.entry-title,[class*="heading"],[class*="title"],[class*="headline"])' . $preserve . '{color:var(--ncu-design-text)!important;}' .
                $scope . ' :is(p,li,dd,dt,label,blockquote,figcaption,table,th,td,[class*="description"],[class*="copy"],[class*="content-text"])' . $preserve . ':not(:is(.success,.warning,.error,.danger,.info,.is-success,.is-warning,.is-error,.is-danger,.is-info)){color:var(--ncu-design-text)!important;}' .
                $scope . ' :is(.muted,.meta,.metadata,.caption,[class*="muted"],[class*="meta"],[class*="secondary-text"])' . $preserve . '{color:var(--ncu-design-muted)!important;}' . "\n";
        }
    }

    if ( ! empty( $s['style_takeover_colors'] ) ) {
        $state_scope = ! empty( $s['style_takeover_strict'] ) ? 'html body.ncu-style-takeover.ncu-takeover-strict.ncu-takeover-strict.ncu-takeover-strict' : 'body.ncu-style-takeover';
        $css .= $state_scope . ' :is(.success,.is-success,[class*="alert-success"],[class*="notice-success"]) {--ncu-state-color:var(--ncu-design-success)!important;}' .
            $state_scope . ' :is(.warning,.is-warning,[class*="alert-warning"],[class*="notice-warning"]) {--ncu-state-color:var(--ncu-design-warning)!important;}' .
            $state_scope . ' :is(.error,.danger,.is-error,.is-danger,[class*="alert-danger"],[class*="alert-error"],[class*="notice-error"]) {--ncu-state-color:var(--ncu-design-danger)!important;}' .
            $state_scope . ' :is(.info,.is-info,[class*="alert-info"],[class*="notice-info"]) {--ncu-state-color:var(--ncu-design-info)!important;}' .
            $state_scope . ' :is(.success,.is-success,.warning,.is-warning,.error,.danger,.is-error,.is-danger,.info,.is-info,[class*="alert-success"],[class*="alert-warning"],[class*="alert-danger"],[class*="alert-error"],[class*="alert-info"]) :is(p,li,span,label,strong,em,a){color:inherit!important;}' . "\n";
    }

    /* Preserve media and technical content. No layout property is changed. */
    $css .= 'body.ncu-style-takeover :where(img,video,iframe,canvas,svg){filter:none!important;}' .
        'body.ncu-style-takeover :where(code,pre,kbd,samp){font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace!important;}' . "\n";

    $css = apply_filters( 'ncu_style_takeover_css', $css, $t, $s );
    wp_add_inline_style( $handle, $css );

    if ( ! empty( $s['style_takeover_strict'] ) && ! empty( $s['style_takeover_dynamic_bridge'] ) ) {
        wp_enqueue_script( 'nine-code-ultra-style-authority', NCU_THEME_URI . '/assets/js/style-authority.js', array(), NCU_THEME_VERSION, true );
        if ( function_exists( 'wp_script_add_data' ) ) { wp_script_add_data( 'nine-code-ultra-style-authority', 'strategy', 'defer' ); }
        $config = array(
            'respectOptOut' => ! empty( $s['style_takeover_respect_optout'] ),
            'colors'        => ! empty( $s['style_takeover_colors'] ),
            'typography'    => ! empty( $s['style_takeover_typography'] ),
            'textStyles'    => ! empty( $s['style_takeover_text_styles'] ),
        );
        $config = apply_filters( 'ncu_style_takeover_runtime_config', $config, $t, $s );
        wp_add_inline_script( 'nine-code-ultra-style-authority', 'window.NCUStyleAuthorityConfig=' . wp_json_encode( $config ) . ';', 'before' );
    }
}

add_filter( 'block_editor_settings_all', 'ncu_theme_style_takeover_editor', 35, 2 );
function ncu_theme_style_takeover_editor( $settings, $context ) {
    $s=ncu_theme_settings(); if(empty($s['aggressive_style_takeover'])){return $settings;} $t=ncu_theme_effective_design_tokens();
    /* Public rendering is always the selected site design. Dark mode belongs
     * to the WordPress back end and must not silently recolour visitor pages. */
    $is_dark_preview = false;
    if ( $is_dark_preview ) {
        $d=ncu_theme_style_dark_companion_tokens();
        $primary=$d['accent'];$secondary=$d['secondary'];$accent=$d['accent'];$surface=$d['bg'];$surface_alt=$d['surface'];$text=$d['text'];$muted=$d['muted'];$border=$d['border'];
    } else {
        $primary=$t['primary'];$secondary=$t['secondary'];$accent=$t['accent'];$surface=$t['surface'];$surface_alt=$t['surface_alt'];$text=$t['text'];$muted=$t['muted'];$border=$t['border'];
    }
    $css='.editor-styles-wrapper{--ncu-design-primary:'.$primary.';--ncu-design-secondary:'.$secondary.';--ncu-design-accent:'.$accent.';--ncu-design-surface:'.$surface.';--ncu-design-surface-alt:'.$surface_alt.';--ncu-design-text:'.$text.';--ncu-design-muted:'.$muted.';--ncu-design-border:'.$border.';background:var(--ncu-design-surface);color:var(--ncu-design-text);font-family:'.ncu_theme_style_font_stack($t['body_font']).';}.editor-styles-wrapper h1,.editor-styles-wrapper h2,.editor-styles-wrapper h3,.editor-styles-wrapper h4,.editor-styles-wrapper h5,.editor-styles-wrapper h6{font-family:'.ncu_theme_style_font_stack($t['heading_font']).';font-weight:'.absint(isset($t['heading_weight'])?$t['heading_weight']:700).';letter-spacing:'.esc_attr($t['heading_tracking']).';color:var(--ncu-design-text)}.editor-styles-wrapper a{color:var(--ncu-design-primary)}';
    if(!isset($settings['styles'])||!is_array($settings['styles'])){$settings['styles']=array();}$settings['styles'][]=array('css'=>$css);return $settings;
}
