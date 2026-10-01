<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ELHF_F_Renderer {
    private static $instance = null;
    private $term_sets = [];
    public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }
    private function __construct() {}

    private function s( $settings = null ) { return ELHF_Design::footer_settings(wp_parse_args( is_array($settings) ? $settings : [], ELHF_F_Settings::all() )); }

    private function normalize_hex( $color, $fallback = '' ) {
        $color = sanitize_hex_color( $color );
        return $color ?: $fallback;
    }

    private function color_luminance( $hex ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( 3 === strlen( $hex ) ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if ( 6 !== strlen( $hex ) ) return 0;
        $rgb = [ hexdec(substr($hex,0,2))/255, hexdec(substr($hex,2,2))/255, hexdec(substr($hex,4,2))/255 ];
        foreach ( $rgb as &$v ) $v = $v <= 0.04045 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
        return 0.2126*$rgb[0] + 0.7152*$rgb[1] + 0.0722*$rgb[2];
    }

    private function contrast_ratio( $a, $b ) {
        $l1 = $this->color_luminance( $a ); $l2 = $this->color_luminance( $b );
        $light = max( $l1, $l2 ); $dark = min( $l1, $l2 );
        return ( $light + 0.05 ) / ( $dark + 0.05 );
    }

    private function readable_text_for( $background ) {
        $light = '#F8FAFC'; $dark = '#111827';
        return $this->contrast_ratio( $light, $background ) >= $this->contrast_ratio( $dark, $background ) ? $light : $dark;
    }

    private function ensure_contrast( $candidate, $background, $minimum = 4.5 ) {
        $candidate = $this->normalize_hex( $candidate );
        if ( $candidate && $this->contrast_ratio( $candidate, $background ) >= $minimum ) return $candidate;
        return $this->readable_text_for( $background );
    }

    private function resolved_footer_colors( $s ) {
        $mode = $s['footer_color_mode'] ?? 'auto';
        $auto_contrast = 'yes' === ( $s['footer_auto_contrast'] ?? 'yes' );
        $fallback_bg = '#121416';
        $fallback_accent = $this->normalize_hex( $s['footer_accent'] ?? '', '#D62828' );

        if ( 'custom' === $mode ) {
            $bg = $this->normalize_hex( $s['footer_bg'] ?? '', $fallback_bg );
            $text = $this->normalize_hex( $s['footer_text_color'] ?? '', '#FFFFFF' );
            $link = $this->normalize_hex( $s['footer_link_color'] ?? '', $text );
            $accent = $fallback_accent;
        } else {
            /**
             * Unified 9/PHP/theme integration. Providers may supply background,
             * text, link and accent. A dark footer is still the safe fallback.
             */
            $tokens = apply_filters( 'n9f_footer_auto_colors', [], $s );
            $tokens = is_array( $tokens ) ? $tokens : [];
            $bg = $this->normalize_hex( $tokens['background'] ?? '', $fallback_bg );
            $text = $this->normalize_hex( $tokens['text'] ?? '', '#FFFFFF' );
            $link = $this->normalize_hex( $tokens['link'] ?? '', $text );
            $accent = $this->normalize_hex( $tokens['accent'] ?? '', $fallback_accent );
        }

        if ( $auto_contrast ) {
            $text = $this->ensure_contrast( $text, $bg, 4.5 );
            $link = $this->ensure_contrast( $link, $bg, 4.5 );
            // Hover/accent text needs at least UI-level contrast; if not, use the readable foreground.
            $accent = $this->ensure_contrast( $accent, $bg, 3.0 );
        }
        return [ 'background'=>$bg, 'text'=>$text, 'link'=>$link, 'accent'=>$accent ];
    }

    private function full_logo_url( $s ) {
        if ( ! empty( $s['footer_logo_url'] ) ) return $s['footer_logo_url'];
        $id = get_theme_mod( 'custom_logo' );
        if ( $id ) {
            $src = wp_get_attachment_image_url( $id, 'full' );
            if ( $src ) return $src;
        }
        return get_site_icon_url( 192 ) ?: '';
    }

    private function resolve_menu_id( $s ) {
        $id = absint( $s['regular_menu_id'] ?? 0 );
        return ( $id && wp_get_nav_menu_object( $id ) ) ? $id : 0;
    }

    private function render_regular_menu( $s ) {
        $id = $this->resolve_menu_id( $s );
        if ( ! $id ) return false;
        $html = wp_nav_menu( [ 'menu'=>$id, 'container'=>false, 'menu_class'=>'n9f-wp-menu', 'fallback_cb'=>false, 'depth'=>4, 'echo'=>false ] );
        if ( ! $html ) return false;
        echo $html;
        return true;
    }

    private function excluded_ids( $s ) { return array_values( array_filter( array_map( 'absint', (array)($s['exclude_categories'] ?? []) ) ) ); }

    private function category_context( $s ) {
        $key = md5( wp_json_encode( [
            'hide_empty' => 'yes' === ( $s['hide_empty'] ?? 'yes' ),
            'orderby' => $s['category_orderby'] ?? 'name',
            'order' => $s['category_order'] ?? 'ASC',
            'exclude' => $this->excluded_ids( $s ),
        ] ) );
        if ( isset( $this->term_sets[$key] ) ) return $this->term_sets[$key];

        $args = [
            'taxonomy' => 'category',
            'hide_empty' => 'yes' === ( $s['hide_empty'] ?? 'yes' ),
            'orderby' => in_array( $s['category_orderby'] ?? 'name', [ 'name','count','id','slug' ], true ) ? $s['category_orderby'] : 'name',
            'order' => 'DESC' === ( $s['category_order'] ?? 'ASC' ) ? 'DESC' : 'ASC',
        ];
        $excluded = $this->excluded_ids( $s );
        if ( $excluded ) $args['exclude'] = $excluded;
        $terms = get_terms( $args );
        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) $terms = [];

        $by_id = [];
        $children = [];
        foreach ( $terms as $term ) {
            $by_id[(int)$term->term_id] = $term;
            $children[(int)$term->parent][] = $term;
        }
        return $this->term_sets[$key] = [ 'by_id'=>$by_id, 'children'=>$children ];
    }

    private function category_roots( $s, $ctx ) {
        $mode = $s['category_query_mode'] ?? 'all';
        if ( 'manual' === $mode ) {
            $roots = [];
            foreach ( (array)($s['manual_categories'] ?? []) as $id ) {
                $id = absint( $id );
                if ( $id && isset( $ctx['by_id'][$id] ) ) $roots[] = $ctx['by_id'][$id];
            }
            return $roots;
        }
        if ( 'children_of' === $mode ) {
            $parent = absint( $s['category_parent'] ?? 0 );
            if ( ! $parent ) return [];
            if ( 'yes' === ( $s['include_parent'] ?? '' ) ) return isset( $ctx['by_id'][$parent] ) ? [ $ctx['by_id'][$parent] ] : [];
            return $ctx['children'][$parent] ?? [];
        }
        return $ctx['children'][0] ?? [];
    }

    private function category_tree( $terms, $ctx, $depth, $max ) {
        if ( ! $terms || $depth > $max ) return;
        echo '<ul class="n9f-cat-level n9f-cat-level-' . absint( $depth ) . '">';
        foreach ( $terms as $term ) {
            echo '<li><a href="' . esc_url( get_category_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
            if ( $depth < $max ) $this->category_tree( $ctx['children'][(int)$term->term_id] ?? [], $ctx, $depth + 1, $max );
            echo '</li>';
        }
        echo '</ul>';
    }

    private function render_categories( $s ) {
        $ctx = $this->category_context( $s );
        $roots = $this->category_roots( $s, $ctx );
        if ( ! $roots ) return false;
        $max = 'top_level' === ( $s['category_query_mode'] ?? 'all' ) ? 1 : max( 1, min( 3, absint( $s['category_depth'] ?? 2 ) ) );
        $this->category_tree( $roots, $ctx, 1, $max );
        return true;
    }

    private function render_quick_links( $s ) {
        $items = [];
        for ( $i=1; $i<=8; $i++ ) {
            if ( 'yes' !== ( $s["quick_{$i}_enabled"] ?? '' ) || empty( $s["quick_{$i}_label"] ) ) continue;
            $items[] = [ 'label'=>$s["quick_{$i}_label"], 'url'=>$s["quick_{$i}_url"] ?? '' ];
        }
        if ( ! $items ) return false;
        echo '<ul class="n9f-quick-links">';
        foreach ( $items as $item ) {
            echo '<li>';
            if ( $item['url'] ) echo '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
            else echo '<span class="n9f-quick-link--inactive">' . esc_html( $item['label'] ) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
        return true;
    }


    private function footer_nav_icon( $type, $url='' ) {
        if($url)return '<img class="n9f-footer-nav__custom-icon" src="'.esc_url($url).'" alt="">';
        $icons=[
            'lecturer'=>'<circle cx="9" cy="8" r="3"/><path d="M3.5 19c.7-3.5 2.5-5.2 5.5-5.2s4.8 1.7 5.5 5.2M15 6h6M18 3v6"/>',
            'course'=>'<path d="M5 4h14v10H5zM8 8h8M8 11h5"/>',
            'flyer'=>'<path d="M6 3h9l3 3v15H6zM15 3v4h4M9 11h6M9 14h6M9 17h4"/>',
            'workshop'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 14h3M13 14h3M8 18h3"/>',
            'lecture'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/>',
            'live'=>'<circle cx="12" cy="12" r="2"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.8 4.8a10 10 0 0 0 0 14.4M19.2 4.8a10 10 0 0 1 0 14.4"/>',
            'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>','grid'=>'<rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><rect x="14" y="14" width="6" height="6"/>','link'=>'<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>'
        ];
        if('none'===$type||empty($icons[$type]))return '';
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$icons[$type].'</g></svg>';
    }

    private function footer_nav_row_configured($row){
        if('yes'!==($row['enabled']??''))return false; $source=$row['source']??'none';
        if('wp_menu'===$source)return absint($row['nav_menu_id']??0)>0;
        if('post_type'===$source)return !empty($row['post_type']);
        if('taxonomy'===$source)return !empty($row['taxonomy']);
        if('manual_links'===$source)return !empty($row['links']);
        return false;
    }

    private function render_footer_nav_body($row){
        $source=$row['source']??'none'; $limit=max(1,min(50,absint($row['limit']??12)));
        if('wp_menu'===$source){$id=absint($row['nav_menu_id']??0); if(!$id||!wp_get_nav_menu_object($id))return false; $html=wp_nav_menu(['menu'=>$id,'container'=>false,'menu_class'=>'n9f-wp-menu','fallback_cb'=>false,'depth'=>4,'echo'=>false]); if(!$html)return false; echo $html; return true;}
        if('post_type'===$source){$pt=sanitize_key($row['post_type']??''); if(!$pt||!post_type_exists($pt))return false; $posts=get_posts(['post_type'=>$pt,'post_status'=>'publish','numberposts'=>$limit,'orderby'=>'date','order'=>'DESC','no_found_rows'=>true]); if(!$posts)return false; echo '<ul class="n9f-wp-menu n9f-footer-source-list">'; foreach($posts as $post)echo '<li><a href="'.esc_url(get_permalink($post)).'">'.esc_html(get_the_title($post)).'</a></li>'; echo '</ul>'; return true;}
        if('taxonomy'===$source){$tax=sanitize_key($row['taxonomy']??''); if(!$tax||!taxonomy_exists($tax))return false; $terms=get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'number'=>$limit,'orderby'=>'name','order'=>'ASC']); if(is_wp_error($terms)||!$terms)return false; echo '<ul class="n9f-wp-menu n9f-footer-source-list">'; foreach($terms as $term){$url=get_term_link($term,$tax); if(is_wp_error($url))continue; echo '<li><a href="'.esc_url($url).'">'.esc_html($term->name).'</a></li>'; } echo '</ul>'; return true;}
        if('manual_links'===$source){$links=(array)($row['links']??[]); if(!$links)return false; echo '<ul class="n9f-wp-menu n9f-footer-source-list">'; $has=false; foreach($links as $ln){$text=trim((string)($ln['text']??''));$url=trim((string)($ln['url']??'')); if(!$text&&!$url)continue; $has=true; echo '<li>'; if($url)echo '<a href="'.esc_url($url).'">'.esc_html($text?:$url).'</a>'; else echo '<span>'.esc_html($text).'</span>'; echo '</li>'; } echo '</ul>'; return $has;}
        return false;
    }

    private function render_footer_nav_builder($s){
        foreach((array)($s['footer_nav_builder']??[]) as $row){
            if(!$this->footer_nav_row_configured($row))continue;
            ob_start();$has=$this->render_footer_nav_body($row);$body=ob_get_clean(); if(!$has||''===trim($body))continue;
            $label=trim((string)($row['label']??'')); if(!$label)$label='Menu'; $icon=$this->footer_nav_icon($row['icon']??'none',$row['icon_url']??'');
            echo '<div class="n9f-footer__column n9f-footer__column--selected"><h4>'.($icon?'<span class="n9f-footer-nav__icon">'.$icon.'</span>':'').'<span>'.esc_html($label).'</span></h4>'.$body.'</div>';
        }
    }

    private function render_module( $s, $i, $position = 'top' ) {
        if ( 'yes' !== ( $s["footer_slot_{$i}_enabled"] ?? '' ) ) return;
        if ( ( $s["footer_slot_{$i}_position"] ?? 'top' ) !== $position ) return;

        $source = $s["footer_slot_{$i}_source"] ?? 'shortcode';
        $width = in_array( $s["footer_slot_{$i}_width"] ?? 'full', [ 'full','half','third' ], true ) ? $s["footer_slot_{$i}_width"] : 'full';
        $title = $s["footer_slot_{$i}_title"] ?? '';
        $key = $s["footer_slot_{$i}_integration"] ?? '';
        $color_behavior = 'preserve' === ( $s["footer_slot_{$i}_color_behavior"] ?? 'inherit' ) ? 'preserve' : 'inherit';

        ob_start();
        $custom = apply_filters( 'n9f_footer_slot_output', '', $key, $i, $s );
        if ( is_string( $custom ) && '' !== trim( $custom ) ) {
            echo $custom;
        } elseif ( 'embed' === $source ) {
            $embed = trim( (string) ( $s["footer_slot_{$i}_embed_html"] ?? '' ) );
            if ( $embed ) {
                echo '<div class="n9f-footer-embed" style="--n9f-embed-height:' . absint( $s["footer_slot_{$i}_embed_height"] ?? 320 ) . 'px">' . $embed . '</div>';
            }
        } elseif ( 'image' === $source ) {
            $url = trim( (string) ( $s["footer_slot_{$i}_image_url"] ?? '' ) );
            if ( $url ) {
                $fit = in_array( $s["footer_slot_{$i}_image_fit"] ?? 'contain', [ 'contain','cover','natural' ], true ) ? $s["footer_slot_{$i}_image_fit"] : 'contain';
                $img = '<img class="n9f-footer-media n9f-footer-media--' . esc_attr( $fit ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $s["footer_slot_{$i}_image_alt"] ?? '' ) . '" loading="lazy" decoding="async">';
                $link = trim( (string) ( $s["footer_slot_{$i}_image_link"] ?? '' ) );
                echo $link ? '<a class="n9f-footer-media-link" href="' . esc_url( $link ) . '">' . $img . '</a>' : $img;
            }
        } elseif ( 'elementor' === $source ) {
            $id = absint( $s["footer_slot_{$i}_template"] ?? 0 );
            if ( $id && did_action( 'elementor/loaded' ) && class_exists( '\\Elementor\\Plugin' ) ) {
                try { echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id, true ); } catch ( \Throwable $e ) {}
            }
        } elseif ( 'block' === $source ) {
            $id = absint( $s["footer_slot_{$i}_block"] ?? 0 );
            $post = $id ? get_post( $id ) : null;
            if ( $post && 'wp_block' === $post->post_type && 'publish' === $post->post_status ) echo do_blocks( $post->post_content );
        } elseif ( 'widget_area' === $source ) {
            $sidebar = 'n9f-footer-slot-' . $i;
            if ( is_active_sidebar( $sidebar ) ) dynamic_sidebar( $sidebar );
        } elseif ( 'integration' === $source ) {
            do_action( 'n9f_footer_slot', $key, $i, $s );
        } else {
            $content = trim( (string) ( $s["footer_slot_{$i}_content"] ?? '' ) );
            if ( $content ) {
                if ( preg_match( '#^https?://[^\\s]+$#i', $content ) && function_exists( 'wp_oembed_get' ) ) {
                    $embed = wp_oembed_get( $content );
                    echo $embed ?: '<a href="' . esc_url( $content ) . '">' . esc_html( $content ) . '</a>';
                } else {
                    echo do_shortcode( $content );
                }
            }
        }
        $body = trim( ob_get_clean() );
        if ( '' === $body ) return;

        echo '<section class="n9f-footer-module n9f-footer-module--' . esc_attr( $width ) . ' n9f-footer-module--colors-' . esc_attr( $color_behavior ) . '" data-n9f-footer-slot="' . esc_attr( $i ) . '">';
        if ( $title ) echo '<h3>' . esc_html( $title ) . '</h3>';
        echo '<div class="n9f-footer-module__content">' . $body . '</div></section>';
    }

    private function render_modules( $s, $position = 'top' ) {
        if ( 'yes' !== ( $s['footer_show_modules'] ?? '' ) ) return;
        $enabled = false;
        for ( $i = 1; $i <= 4; $i++ ) {
            if ( 'yes' === ( $s["footer_slot_{$i}_enabled"] ?? '' ) && ( $s["footer_slot_{$i}_position"] ?? 'top' ) === $position ) { $enabled = true; break; }
        }
        if ( ! $enabled ) return;
        echo '<div class="n9f-footer-modules n9f-footer-modules--' . esc_attr( $position ) . '">';
        for ( $i = 1; $i <= 4; $i++ ) $this->render_module( $s, $i, $position );
        echo '</div>';
    }

    public function footer_color_class( $s ) {
        return 'custom' === ( $s['footer_color_mode'] ?? 'auto' ) ? 'n9f-footer--colors-custom' : 'n9f-footer--colors-auto';
    }

    public function footer_style_vars( $s ) {
        $vars = '--n9f-max:' . absint($s['footer_max_width']) . 'px;--n9f-pt:' . absint($s['footer_padding_top']) . 'px;--n9f-pb:' . absint($s['footer_padding_bottom']) . 'px;--n9f-logo:' . absint($s['footer_logo_width']) . 'px;--n9f-gap:' . absint($s['footer_column_gap']) . 'px;';
        $colors = $this->resolved_footer_colors( $s );
        $vars .= '--n9f-bg:' . esc_attr($colors['background']) . ';--n9f-text:' . esc_attr($colors['text']) . ';--n9f-link:' . esc_attr($colors['link']) . ';--n9f-accent:' . esc_attr($colors['accent']) . ';';
        // Auto and Custom now use the same safe variables; the class remains useful for admin/debugging and future styling.
        $vars .= '--n9f-auto-bg:' . esc_attr($colors['background']) . ';--n9f-auto-text:' . esc_attr($colors['text']) . ';--n9f-auto-link:' . esc_attr($colors['link']) . ';--n9f-auto-accent:' . esc_attr($colors['accent']) . ';';
        return $vars;
    }

    public function render_manager_attribution( $settings=null ) {
        $s=$this->s($settings); if('yes'!==($s['footer_manager_enabled']??''))return;
        $url=trim((string)($s['footer_manager_url']??''));$target='yes'===($s['footer_manager_new_tab']??'')?' target="_blank" rel="noopener noreferrer"':'';
        $label=trim(($s['footer_manager_prefix']??'').' '.($s['footer_manager_name']??''));
        $manager_mode = 'custom' === ( $s['footer_manager_color_mode'] ?? 'auto' ) ? 'custom' : 'auto';
        $manager_style = '--n9f-manager-align:'.esc_attr($s['footer_manager_align']).';--n9f-manager-icon:'.absint($s['footer_manager_icon_size']).'px;--n9f-manager-size:'.absint($s['footer_manager_text_size']).'px;--n9f-manager-prefix-weight:'.absint($s['footer_manager_text_weight']).';--n9f-manager-name-weight:'.absint($s['footer_manager_name_weight']).';';
        if ( 'custom' === $manager_mode ) {
            $manager_bg = $this->normalize_hex( $s['footer_manager_bg'] ?? '', '#F7F7F8' );
            $manager_text = $this->normalize_hex( $s['footer_manager_text_color'] ?? '', '#4B5563' );
            if ( 'yes' === ( $s['footer_manager_auto_contrast'] ?? 'yes' ) ) $manager_text = $this->ensure_contrast( $manager_text, $manager_bg, 4.5 );
            $manager_style .= '--n9f-manager-bg:'.esc_attr($manager_bg).';--n9f-manager-text:'.esc_attr($manager_text).';';
        }
        echo '<div class="n9f-footer__manager n9f-footer__manager--colors-'.esc_attr($manager_mode).'" style="'.$manager_style.'">';
        $open=$url?'<a class="n9f-footer__manager-link" href="'.esc_url($url).'"'.$target.' aria-label="'.esc_attr($label).'">':'<div class="n9f-footer__manager-link">';$close=$url?'</a>':'</div>';echo $open;
        if(!empty($s['footer_manager_image_override_url'])){echo '<img class="n9f-footer__manager-override" src="'.esc_url($s['footer_manager_image_override_url']).'" alt="'.esc_attr($label).'" loading="lazy" decoding="async">';}
        else{
            echo '<span class="n9f-footer__manager-icon" aria-hidden="true">';
            if(!empty($s['footer_manager_icon_url']))echo '<img src="'.esc_url($s['footer_manager_icon_url']).'" alt="" loading="lazy" decoding="async">';
            else echo '<span class="dashicons dashicons-'.esc_attr(sanitize_html_class($s['footer_manager_icon_dashicon']??'admin-site-alt3')).'"></span>';
            echo '</span><span class="n9f-footer__manager-text"><span class="n9f-footer__manager-prefix">'.esc_html($s['footer_manager_prefix']).'</span> <strong>'.esc_html($s['footer_manager_name']).'</strong></span>';
        }
        echo $close.'</div>';
    }

    public function render_endcap( $settings=null ) {
        $s=$this->s($settings); echo '<div class="n9f-footer-endcap">';$this->render_manager_attribution($s);echo '<div class="n9f-footer__bottom"><span>'.esc_html($s['footer_copyright']).'</span>';if('yes'===($s['footer_back_to_top']??''))echo '<a href="#" class="n9f-back-top">Back to top ↑</a>';echo '</div></div>';
    }

    public function render_footer( $settings=null,$instance='native' ) {
        if ( ! ELHF_F_Settings::is_enabled() ) return;
        $s = $this->s($settings);
        $logo = $this->full_logo_url($s);
        $classes = 'n9f-footer elhh-footer elhh-style-' . ELHF_Design::style_slug() . ' ' . $this->footer_color_class($s);
        echo '<footer class="' . esc_attr($classes) . '" data-n9f-footer style="' . esc_attr($this->footer_style_vars($s)) . '">';
        echo '<div class="n9f-footer__inner">';

        $this->render_modules($s, 'top');
        echo '<div class="n9f-footer__grid">';
        echo '<div class="n9f-footer__brand">';
        if($logo) echo '<a href="'.esc_url(home_url('/')).'"><img src="'.esc_url($logo).'" alt="'.esc_attr(get_bloginfo('name')).'" loading="lazy" decoding="async"></a>';
        if(!empty($s['footer_description'])) echo '<p>'.nl2br(esc_html($s['footer_description'])).'</p>';
        echo '</div>';

        $this->render_footer_nav_builder($s);
        echo '</div>';

        $this->render_modules($s, 'below_grid');
        $this->render_modules($s, 'before_endcap');
        $this->render_endcap($s);
        echo '</div></footer>';
    }

}
