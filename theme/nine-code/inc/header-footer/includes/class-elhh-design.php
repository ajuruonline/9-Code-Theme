<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Read-only design bridge.
 *
 * Authority order:
 * 1. Nine Code public design-token API.
 * 2. Header/Footer saved local fallback.
 *
 * No upstream option is copied or written. Derived values exist for the request only.
 */
final class ELHF_Design {
    private static $state = null;

    public static function state( $refresh = false ) {
        if ( ! $refresh && is_array( self::$state ) ) return self::$state;
        $state = [ 'source'=>'local', 'style'=>'academic', 'tokens'=>[], 'appearance_mode'=>'light', 'unified'=>false ];
        // Keep visual-authority selection extensible without coupling Header/Footer
        // to any discontinued provider. Supported core modes are Theme or local.
        $mode = sanitize_key( (string) apply_filters( 'ncu_header_footer_skin_mode', 'theme' ) );
        if ( ! in_array( $mode, [ 'theme', 'local' ], true ) ) $mode = 'theme';
        $theme_tokens = [];
        foreach ( [ 'ncu_theme_effective_design_tokens', 'ncu_get_effective_design_tokens' ] as $fn ) {
            if ( function_exists( $fn ) ) {
                $candidate = call_user_func( $fn );
                if ( is_array( $candidate ) && $candidate ) { $theme_tokens = $candidate; break; }
            }
        }
        if ( 'local' !== $mode && $theme_tokens ) {
            $state['source'] = 'theme';
            $state['style'] = sanitize_key( $theme_tokens['family_slug'] ?? 'academic' );
            $state['tokens'] = self::sanitize_tokens( [
                'accent'=>$theme_tokens['accent'] ?? $theme_tokens['primary'] ?? '',
                'accent_text'=>$theme_tokens['on_primary'] ?? '',
                'action'=>$theme_tokens['secondary'] ?? $theme_tokens['accent'] ?? '',
                'action_text'=>$theme_tokens['on_secondary'] ?? '',
                'link'=>$theme_tokens['link'] ?? $theme_tokens['primary'] ?? '',
                'border'=>$theme_tokens['border'] ?? '',
                'surface'=>$theme_tokens['surface'] ?? '',
                'background'=>$theme_tokens['surface_alt'] ?? '',
                'text'=>$theme_tokens['text'] ?? '',
                'muted'=>$theme_tokens['muted'] ?? '',
            ] );
        }
        $filtered = apply_filters( 'elhh_design_state', $state );
        if ( is_array( $filtered ) ) $state = wp_parse_args( $filtered, $state );
        $state['style'] = self::normalize_style( $state['style'] ?? 'academic' );
        $state['tokens'] = self::sanitize_tokens( $state['tokens'] ?? [] );
        return self::$state = $state;
    }

    private static function sanitize_tokens( $tokens ) {
        $out = [];
        foreach ( [ 'accent','accent_text','action','action_text','link','border','surface','background','text','muted','detail_surface','detail_background','detail_text','detail_muted' ] as $key ) {
            $value = sanitize_hex_color( $tokens[$key] ?? '' );
            if ( $value ) $out[$key] = $value;
        }
        if ( isset( $tokens['text_scale'] ) ) $out['text_scale'] = max( 80, min( 160, absint( $tokens['text_scale'] ) ) );
        if ( isset( $tokens['contrast_strength'] ) ) $out['contrast_strength'] = max( 0, min( 100, absint( $tokens['contrast_strength'] ) ) );
        return $out;
    }

    public static function source() { return self::state()['source']; }
    public static function style_slug() { return self::state()['style']; }

    public static function normalize_style( $style ) {
        $style = sanitize_key( (string) $style );
        $aliases = [
            '01'=>'academic','02'=>'minimal','03'=>'blog','04'=>'editorial','05'=>'presentation','06'=>'classroom',
            '07'=>'technical','08'=>'textbook','09'=>'magazine','10'=>'documentary','11'=>'executive','12'=>'study-guide',
        ];
        if ( isset( $aliases[$style] ) ) $style = $aliases[$style];
        $allowed = [ 'academic','magazine','blog','journal','executive','textbook','classroom','editorial','technical','minimal','documentary','presentation','study-guide','campus','research','library','studio','gallery','portal','civic','heritage','modernist','warm','monochrome','slate','glass','glossy','question','bold','neon','community','facebook','youtube','whatsapp','instagram','linkedin','x','netflix','tiktok','google','telegram','spotify','microsoft','custom' ];
        return in_array( $style, $allowed, true ) ? $style : 'academic';
    }

    public static function header_preset( $style = '' ) {
        $style = self::normalize_style( $style ?: self::style_slug() );
        $map = [
            'academic'=>'academic_journal','magazine'=>'author_spotlight','blog'=>'creator_studio','journal'=>'newspaper',
            'executive'=>'campus_portal','textbook'=>'paper_notebook','classroom'=>'classroom_console','editorial'=>'editorial_line',
            'technical'=>'research_lab','minimal'=>'minimal_white','documentary'=>'heritage_university','presentation'=>'bento_campus',
            'study-guide'=>'learning_path','campus'=>'campus_portal','research'=>'research_lab','library'=>'academic_journal',
'facebook'=>'rounded_cards','youtube'=>'creator_studio','whatsapp'=>'pill_control','instagram'=>'author_spotlight','linkedin'=>'dashboard','x'=>'command_bar','netflix'=>'dark_studio','tiktok'=>'neon_tech','google'=>'minimal_white','telegram'=>'mobile_app','spotify'=>'dark_studio','microsoft'=>'dashboard','custom'=>'nine_native',
            'studio'=>'creator_studio','gallery'=>'rounded_cards','portal'=>'dashboard','civic'=>'nine_native','heritage'=>'heritage_university',
            'modernist'=>'square_grid','warm'=>'soft_pastel','monochrome'=>'monochrome','slate'=>'command_bar','glass'=>'glass_lab',
            'glossy'=>'mobile_app','question'=>'learning_path','bold'=>'ribbon_academy','neon'=>'neon_tech','community'=>'pill_control',
        ];
        return $map[$style] ?? 'authorfest_core';
    }


    private static function contrast_fg( $hex ) {
        $hex = sanitize_hex_color( $hex );
        if ( ! $hex ) return '#111111';
        $raw = ltrim( $hex, '#' ); $l = 0; $parts=[];
        foreach ( [0,2,4] as $i ) { $v=hexdec(substr($raw,$i,2))/255; $parts[]=$v<=0.03928?$v/12.92:pow(($v+0.055)/1.055,2.4); }
        $l=.2126*$parts[0]+.7152*$parts[1]+.0722*$parts[2];
        return (1.05/($l+.05)) >= (($l+.05)/.055) ? '#FFFFFF' : '#111111';
    }

    public static function header_settings( $settings ) {
        $s = is_array( $settings ) ? $settings : [];
        $state = self::state();
        if ( 'local' === $state['source'] ) return $s;

        $s['preset'] = self::header_preset( $state['style'] );
        $t = $state['tokens'];
        if ( $t ) {
            $s['custom_palette'] = 'yes';
            $map = [
                'custom_header_bg'   => 'surface',
                'custom_header_fg'   => 'text',
                'custom_panel_bg'    => 'background',
                'custom_panel_fg'    => 'text',
                'custom_primary'     => 'accent',
                'custom_primary_fg'  => 'accent_text',
                'custom_accent'      => 'action',
                'custom_accent_fg'   => 'action_text',
                'custom_surface'     => 'surface',
                'custom_surface_fg'  => 'text',
                'custom_soft'        => 'background',
                'custom_soft_fg'     => 'text',
                'custom_text'        => 'text',
                'custom_text_fg'     => 'surface',
                'custom_border'      => 'border',
            ];
            foreach ( $map as $dest => $src ) if ( ! empty( $t[$src] ) ) $s[$dest] = $t[$src];
            if ( empty($t['background']) && !empty($t['surface']) ) $s['custom_panel_bg']=$t['surface'];
            if ( empty($t['surface']) && !empty($t['background']) ) $s['custom_surface']=$t['background'];
            if ( !empty($t['accent']) && empty($t['accent_text']) ) $s['custom_primary_fg']=self::contrast_fg($t['accent']);
            if ( !empty($t['action']) && empty($t['action_text']) ) $s['custom_accent_fg']=self::contrast_fg($t['action']);
            if ( !empty($s['custom_header_bg']) && empty($t['text']) ) $s['custom_header_fg']=self::contrast_fg($s['custom_header_bg']);
            if ( !empty($s['custom_panel_bg']) && empty($t['text']) ) $s['custom_panel_fg']=self::contrast_fg($s['custom_panel_bg']);
            // Keep the Header's backend-only dark-mode preference authoritative.
            $s['_upstream_appearance_mode'] = $state['appearance_mode'];
        }
        return apply_filters( 'elhh_effective_header_settings', $s, $state );
    }

    public static function footer_settings( $settings ) {
        $s = is_array( $settings ) ? $settings : [];
        $state = self::state();
        if ( 'local' === $state['source'] ) return $s;
        $t = $state['tokens'];
        if ( $t ) {
            $s['footer_color_mode'] = 'custom';
            $s['footer_auto_contrast'] = 'yes';
            if ( ! empty( $t['background'] ) ) $s['footer_bg'] = $t['background'];
            elseif ( ! empty( $t['surface'] ) ) $s['footer_bg'] = $t['surface'];
            if ( ! empty( $t['text'] ) ) $s['footer_text_color'] = $t['text'];
            if ( ! empty( $t['link'] ) ) $s['footer_link_color'] = $t['link'];
            elseif ( ! empty( $t['accent'] ) ) $s['footer_link_color'] = $t['accent'];
            if ( ! empty( $t['accent'] ) ) $s['footer_accent'] = $t['accent'];
            // Keep the management strip visually inside the same family.
            $s['footer_manager_color_mode'] = 'auto';
        }
        return apply_filters( 'elhh_effective_footer_settings', $s, $state );
    }

    public static function authority_label() {
        $source = self::source();
        if ( 'theme' === $source ) return 'Nine Code Skin';
        return 'Header & Footer saved fallback';
    }
}
