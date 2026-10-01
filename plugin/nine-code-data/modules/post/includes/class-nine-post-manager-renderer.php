<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 9 Post Editor — Instant ACF Render layer.
 *
 * This is deliberately a lightweight/fallback presentation layer. It does not alter
 * ACF field keys or content. More advanced 99 ACF Go / 99 Builder renderers can take over later.
 */
final class Nine_Post_Manager_Renderer {
    private static $instance = null;
    private $nonce_action = 'npm9_action';
    private $styles_option = 'npm9_render_styles';
    private $rendered_posts = [];

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'the_content', [ $this, 'filter_content' ], 18 );
        add_action( 'loop_end', [ $this, 'fallback_loop_render' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_shortcode( 'nine_post_manager_render', [ $this, 'shortcode_render' ] );

        $ajax = [
            'render_save'          => 'ajax_save_settings',
            'render_import_style'  => 'ajax_import_style',
            'render_export_style'  => 'ajax_export_style',
            'render_import_acf'    => 'ajax_import_acf',
            'render_quick_deploy'  => 'ajax_quick_deploy',
        ];
        foreach ( $ajax as $action => $method ) {
            add_action( 'wp_ajax_npm9_' . $action, [ $this, $method ] );
        }
    }

    public function register_assets() {
        wp_register_style( 'npm9-render', NPM9_URL . 'assets/render.css', [], NPM9_VERSION );
        wp_register_script( 'npm9-render', NPM9_URL . 'assets/render.js', [], NPM9_VERSION, true );
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id && '1' === get_post_meta( $post_id, '_npm9_render_enabled', true ) && ! $this->should_yield( $post_id ) ) {
                wp_enqueue_style( 'npm9-render' );
                wp_enqueue_script( 'npm9-render' );
            }
        }
    }

    public function built_in_styles() {
        return [
            'corporate' => [
                'label' => 'Corporate', 'description' => 'Strong professional cards with a restrained business hierarchy.',
                'section_style' => 'card', 'layout' => 'vertical', 'hero' => true,
                'vars' => [ 'primary'=>'#17345c','secondary'=>'#eef3f8','accent'=>'#9a2233','surface'=>'#ffffff','text'=>'#172033','muted'=>'#64748b','radius'=>'16px','shadow'=>'0 10px 30px rgba(15,23,42,.08)','max_width'=>'1180px' ],
            ],
            'academic' => [
                'label' => 'Academic', 'description' => 'Clear scholarly sections with conservative spacing and outlines.',
                'section_style' => 'outline', 'layout' => 'vertical', 'hero' => false,
                'vars' => [ 'primary'=>'#1f2937','secondary'=>'#f8fafc','accent'=>'#1d4ed8','surface'=>'#ffffff','text'=>'#20242c','muted'=>'#667085','radius'=>'10px','shadow'=>'none','max_width'=>'1080px' ],
            ],
            'executive' => [
                'label' => 'Executive', 'description' => 'Premium lead section with compact high-contrast information cards.',
                'section_style' => 'card', 'layout' => 'grid-2', 'hero' => true,
                'vars' => [ 'primary'=>'#111827','secondary'=>'#f3f4f6','accent'=>'#b7791f','surface'=>'#ffffff','text'=>'#111827','muted'=>'#6b7280','radius'=>'14px','shadow'=>'0 12px 34px rgba(17,24,39,.10)','max_width'=>'1180px' ],
            ],
            'editorial' => [
                'label' => 'Editorial', 'description' => 'Reading-first treatment with flatter sections and generous text measure.',
                'section_style' => 'flat', 'layout' => 'vertical', 'hero' => false,
                'vars' => [ 'primary'=>'#292524','secondary'=>'#fafaf9','accent'=>'#7c2d12','surface'=>'#fffdf8','text'=>'#292524','muted'=>'#78716c','radius'=>'4px','shadow'=>'none','max_width'=>'980px' ],
            ],
            'modern-cards' => [
                'label' => 'Modern Cards', 'description' => 'Responsive two-column cards for mixed information and media.',
                'section_style' => 'card', 'layout' => 'grid-2', 'hero' => false,
                'vars' => [ 'primary'=>'#0f172a','secondary'=>'#f1f5f9','accent'=>'#2563eb','surface'=>'#ffffff','text'=>'#1e293b','muted'=>'#64748b','radius'=>'20px','shadow'=>'0 8px 26px rgba(15,23,42,.08)','max_width'=>'1200px' ],
            ],
            'minimal' => [
                'label' => 'Minimal', 'description' => 'Almost unstyled theme-friendly output with only spacing and typography hierarchy.',
                'section_style' => 'flat', 'layout' => 'vertical', 'hero' => false,
                'vars' => [ 'primary'=>'currentColor','secondary'=>'transparent','accent'=>'currentColor','surface'=>'transparent','text'=>'inherit','muted'=>'inherit','radius'=>'0px','shadow'=>'none','max_width'=>'1120px' ],
            ],
            'soft-panels' => [
                'label' => 'Soft Panels', 'description' => 'Gentle background panels for information-heavy pages and forms.',
                'section_style' => 'soft', 'layout' => 'vertical', 'hero' => false,
                'vars' => [ 'primary'=>'#334155','secondary'=>'#f1f5f9','accent'=>'#0f766e','surface'=>'#ffffff','text'=>'#1e293b','muted'=>'#64748b','radius'=>'18px','shadow'=>'none','max_width'=>'1120px' ],
            ],
            'profile-focus' => [
                'label' => 'Profile Focus', 'description' => 'Image-led identity header followed by clear profile sections.',
                'section_style' => 'card', 'layout' => 'vertical', 'hero' => true, 'media_first' => true,
                'vars' => [ 'primary'=>'#312e81','secondary'=>'#f5f3ff','accent'=>'#7c3aed','surface'=>'#ffffff','text'=>'#27272a','muted'=>'#71717a','radius'=>'22px','shadow'=>'0 12px 34px rgba(49,46,129,.08)','max_width'=>'1100px' ],
            ],
            'showcase' => [
                'label' => 'Showcase', 'description' => 'Visual-first presentation that gives image and gallery fields more space.',
                'section_style' => 'card', 'layout' => 'grid-2', 'hero' => true,
                'vars' => [ 'primary'=>'#172554','secondary'=>'#eff6ff','accent'=>'#dc2626','surface'=>'#ffffff','text'=>'#172033','muted'=>'#64748b','radius'=>'18px','shadow'=>'0 10px 28px rgba(23,37,84,.09)','max_width'=>'1240px' ],
            ],
            'compact' => [
                'label' => 'Compact', 'description' => 'Dense mobile-friendly panels for fast operational pages and directories.',
                'section_style' => 'soft', 'layout' => 'inline', 'hero' => false,
                'vars' => [ 'primary'=>'#1f2937','secondary'=>'#f3f4f6','accent'=>'#0284c7','surface'=>'#ffffff','text'=>'#1f2937','muted'=>'#6b7280','radius'=>'10px','shadow'=>'none','max_width'=>'1180px' ],
            ],
        ];
    }

    private function bundled_go_styles() {
        $styles = [];
        $file = NPM9_DIR . 'presets/lecturer-profile-pro.99gostyle';
        if ( is_readable( $file ) ) {
            $data = json_decode( file_get_contents( $file ), true );
            $clean = $this->validate_go_style( $data );
            if ( ! is_wp_error( $clean ) ) $styles[ $clean['slug'] ] = $clean;
        }
        return $styles;
    }

    public function all_styles() {
        $styles = [];
        foreach ( $this->built_in_styles() as $slug => $style ) {
            $styles[ $slug ] = [
                'slug' => $slug,
                'label' => $style['label'],
                'description' => $style['description'],
                'source' => 'built-in',
            ];
        }
        foreach ( $this->bundled_go_styles() as $slug => $style ) {
            $styles[ $slug ] = [
                'slug' => $slug,
                'label' => sanitize_text_field( $style['label'] ?? $slug ),
                'description' => sanitize_text_field( $style['description'] ?? 'Bundled 99 ACF style' ),
                'source' => '99-go',
            ];
        }
        $custom = get_option( $this->styles_option, [] );
        if ( is_array( $custom ) ) {
            foreach ( $custom as $slug => $style ) {
                if ( ! is_array( $style ) ) continue;
                $styles[ $slug ] = [
                    'slug' => $slug,
                    'label' => sanitize_text_field( $style['label'] ?? $slug ),
                    'description' => sanitize_text_field( $style['description'] ?? 'Imported 99 ACF style' ),
                    'source' => 'imported',
                ];
            }
        }
        return array_values( $styles );
    }

    public function state_for_post( $post_id ) {
        $style = sanitize_key( get_post_meta( $post_id, '_npm9_render_style', true ) ?: 'corporate' );
        if ( ! $this->style_exists( $style ) ) $style = 'corporate';
        return [
            'enabled' => '1' === get_post_meta( $post_id, '_npm9_render_enabled', true ),
            'style' => $style,
            'mode' => $this->sanitize_mode( get_post_meta( $post_id, '_npm9_render_mode', true ) ?: 'after' ),
            'colors' => $this->get_color_overrides( $post_id ),
            'styles' => $this->all_styles(),
            'specialist' => $this->specialist_renderer_status( $post_id ),
            'acfAvailable' => function_exists( 'acf_get_field_groups' ),
            'acfGroupCount' => function_exists( 'acf_get_field_groups' ) ? count( (array) acf_get_field_groups( [ 'post_id'=>$post_id ] ) ) : 0,
        ];
    }

    private function style_exists( $slug ) {
        if ( isset( $this->built_in_styles()[ $slug ] ) ) return true;
        $custom = get_option( $this->styles_option, [] );
        if ( is_array( $custom ) && isset( $custom[ $slug ] ) ) return true;
        return isset( $this->bundled_go_styles()[ $slug ] );
    }

    private function specialist_renderer_status( $post_id ) {
        $out = [ 'active' => false, 'name' => '', 'message' => '' ];
        if ( class_exists( 'NNG_Goal_Plugin' ) ) {
            try {
                $plugin = NNG_Goal_Plugin::instance();
                if ( isset( $plugin->renderer ) && is_object( $plugin->renderer ) && method_exists( $plugin->renderer, 'find_goal_for_post' ) ) {
                    $goal_id = absint( $plugin->renderer->find_goal_for_post( $post_id ) );
                    if ( $goal_id ) {
                        return [ 'active'=>true, 'name'=>'99 ACF Go Builder', 'message'=>'A 99 ACF Go design is already allocated to this post. 9PM Instant Render will automatically yield to it to prevent duplicate output.' ];
                    }
                }
            } catch ( Throwable $e ) {}
        }
        return $out;
    }

    private function should_yield( $post_id ) {
        $status = $this->specialist_renderer_status( $post_id );
        return ! empty( $status['active'] );
    }

    public function filter_content( $content ) {
        if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) return $content;
        $post_id = get_the_ID();
        if ( ! $post_id || '1' !== get_post_meta( $post_id, '_npm9_render_enabled', true ) ) return $content;
        if ( $this->should_yield( $post_id ) ) return $content;
        $rendered = $this->render_post( $post_id );
        if ( '' === $rendered ) return $content;
        $this->rendered_posts[ $post_id ] = true;
        $mode = $this->sanitize_mode( get_post_meta( $post_id, '_npm9_render_mode', true ) ?: 'after' );
        if ( 'replace' === $mode ) return $rendered;
        if ( 'before' === $mode ) return $rendered . $content;
        return $content . $rendered;
    }

    public function shortcode_render( $atts = [] ) {
        $atts = shortcode_atts( [ 'id' => 0 ], (array) $atts, 'nine_post_manager_render' );
        $post_id = absint( $atts['id'] ?: get_the_ID() );
        if ( ! $post_id || '1' !== get_post_meta( $post_id, '_npm9_render_enabled', true ) || $this->should_yield( $post_id ) ) { return ''; }
        $rendered = $this->render_post( $post_id );
        if ( $rendered ) { $this->rendered_posts[ $post_id ] = true; }
        return $rendered;
    }

    public function fallback_loop_render( $query ) {
        if ( is_admin() || ! is_singular() || ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query() ) { return; }
        $post_id = absint( get_queried_object_id() );
        if ( ! $post_id || ! empty( $this->rendered_posts[ $post_id ] ) || '1' !== get_post_meta( $post_id, '_npm9_render_enabled', true ) || $this->should_yield( $post_id ) ) { return; }
        $rendered = $this->render_post( $post_id );
        if ( ! $rendered ) { return; }
        $this->rendered_posts[ $post_id ] = true;
        echo '<div class="npm9r-loop-fallback" data-npm9-render-fallback="1">' . $rendered . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_post( $post_id ) {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) || ! function_exists( 'get_field' ) ) return '';
        $style_slug = sanitize_key( get_post_meta( $post_id, '_npm9_render_style', true ) ?: 'corporate' );
        $style = $this->get_style_payload( $style_slug, $post_id );
        if ( ! $style ) return '';
        $layout = $this->layout_for_post( $post_id, $style_slug, $style );
        if ( ! $layout ) return '';
        $vars = $this->style_vars( $style_slug, $style, $post_id );
        wp_enqueue_style( 'npm9-render' );
        wp_enqueue_script( 'npm9-render' );
        ob_start();
        echo '<div class="npm9r-root npm9r-style-' . esc_attr( sanitize_html_class( $style_slug ) ) . '" style="' . esc_attr( $this->theme_vars_style( $vars ) ) . '">';
        foreach ( $layout as $box ) $this->render_box( $box, $post_id );
        echo '</div>';
        return ob_get_clean();
    }

    private function get_style_payload( $slug, $post_id = 0 ) {
        $built = $this->built_in_styles();
        if ( isset( $built[ $slug ] ) ) return $built[ $slug ];
        $custom = get_option( $this->styles_option, [] );
        if ( is_array( $custom ) && isset( $custom[ $slug ] ) && is_array( $custom[ $slug ] ) ) return $custom[ $slug ];
        $bundled = $this->bundled_go_styles();
        if ( isset( $bundled[ $slug ] ) ) return $bundled[ $slug ];
        return $built['corporate'];
    }

    private function style_vars( $slug, $style, $post_id ) {
        if ( isset( $this->built_in_styles()[ $slug ] ) ) $vars = (array) ( $style['vars'] ?? [] );
        else $vars = (array) ( $style['theme']['vars'] ?? [] );
        $defaults = $this->built_in_styles()['corporate']['vars'];
        $vars = array_merge( $defaults, $vars );
        foreach ( $this->get_color_overrides( $post_id ) as $key => $value ) if ( '' !== $value ) $vars[ $key ] = $value;
        return $vars;
    }

    private function get_color_overrides( $post_id ) {
        $raw = get_post_meta( $post_id, '_npm9_render_colors', true );
        if ( ! is_array( $raw ) ) $raw = [];
        $out = [];
        foreach ( [ 'primary','secondary','accent','surface','text','muted' ] as $key ) {
            $out[ $key ] = isset( $raw[ $key ] ) ? $this->sanitize_color( $raw[ $key ], true ) : '';
        }
        return $out;
    }

    private function theme_vars_style( $vars ) {
        $map = [ 'primary'=>'--npm9r-primary','secondary'=>'--npm9r-secondary','accent'=>'--npm9r-accent','surface'=>'--npm9r-surface','text'=>'--npm9r-text','muted'=>'--npm9r-muted','radius'=>'--npm9r-radius','shadow'=>'--npm9r-shadow','max_width'=>'--npm9r-max-width' ];
        $out = '';
        foreach ( $map as $key => $css ) if ( isset( $vars[ $key ] ) ) $out .= $css . ':' . $vars[ $key ] . ';';
        return $out;
    }

    private function layout_for_post( $post_id, $slug, $style ) {
        if ( isset( $this->built_in_styles()[ $slug ] ) ) return $this->automatic_layout( $post_id, $style );
        return $this->layout_from_go_style( $post_id, $style );
    }

    private function automatic_layout( $post_id, $style ) {
        $groups = acf_get_field_groups( [ 'post_id' => $post_id ] );
        $layout = [];
        $box_index = 0;
        foreach ( (array) $groups as $group ) {
            $fields = acf_get_fields( $group['key'] );
            if ( ! is_array( $fields ) ) continue;
            $sections = [];
            $current = [ 'title' => sanitize_text_field( $group['title'] ?? 'Information' ), 'fields' => [] ];
            foreach ( $fields as $field ) {
                if ( 'tab' === ( $field['type'] ?? '' ) ) {
                    if ( $current['fields'] ) $sections[] = $current;
                    $current = [ 'title'=>sanitize_text_field( $field['label'] ?? 'Section' ), 'fields'=>[] ];
                    continue;
                }
                if ( in_array( $field['type'] ?? '', [ 'message','accordion' ], true ) ) continue;
                $current['fields'][] = $this->normalized_field( $field );
            }
            if ( $current['fields'] ) $sections[] = $current;
            foreach ( $sections as $section ) {
                $box_index++;
                $fields = $section['fields'];
                $is_first = 1 === $box_index;
                $has_media = false;
                foreach ( $fields as $f ) if ( in_array( $f['type'], [ 'image','gallery' ], true ) ) { $has_media = true; break; }
                $box_type = 'clear';
                $box_style = sanitize_key( $style['section_style'] ?? 'card' );
                $container_layout = sanitize_key( $style['layout'] ?? 'vertical' );
                if ( $is_first && ! empty( $style['hero'] ) ) { $box_type = 'hero'; $box_style = 'feature'; }
                if ( $is_first && ! empty( $style['media_first'] ) && $has_media ) $container_layout = 'media-left';
                if ( $this->section_is_gallery_heavy( $fields ) ) { $box_type = 'gallery'; $container_layout = 'grid-2'; }
                $layout[] = [
                    'id'=>'auto_box_' . $box_index,
                    'title'=>$section['title'],
                    'type'=>$box_type,
                    'style'=>$box_style,
                    'containers'=>[[ 'id'=>'auto_container_' . $box_index, 'title'=>'', 'layout'=>$container_layout, 'style'=>'none', 'fields'=>$fields ]],
                ];
            }
        }
        return $layout;
    }

    private function section_is_gallery_heavy( $fields ) {
        foreach ( $fields as $f ) if ( 'gallery' === ( $f['type'] ?? '' ) ) return true;
        return false;
    }

    private function field_index_for_post( $post_id ) {
        $index = [];
        foreach ( (array) acf_get_field_groups( [ 'post_id'=>$post_id ] ) as $group ) {
            foreach ( (array) acf_get_fields( $group['key'] ) as $field ) {
                if ( empty( $field['name'] ) || in_array( $field['type'] ?? '', [ 'tab','message','accordion' ], true ) ) continue;
                $index[ sanitize_key( $field['name'] ) ] = $field;
            }
        }
        return $index;
    }

    private function layout_from_go_style( $post_id, $style ) {
        $index = $this->field_index_for_post( $post_id );
        if ( ! $index ) return [];
        $used = [];
        $layout = [];
        foreach ( (array) ( $style['boxes'] ?? [] ) as $bi => $box ) {
            if ( ! is_array( $box ) ) continue;
            $new = [
                'id'=>sanitize_key( $box['id'] ?? 'box_' . $bi ),
                'title'=>sanitize_text_field( $box['title'] ?? 'Section' ),
                'type'=>$this->allowed_box_type( sanitize_key( $box['type'] ?? 'clear' ) ),
                'style'=>$this->allowed_box_style( sanitize_key( $box['style'] ?? 'card' ) ),
                'containers'=>[],
            ];
            foreach ( (array) ( $box['containers'] ?? [] ) as $ci => $container ) {
                if ( ! is_array( $container ) ) continue;
                $c = [
                    'id'=>sanitize_key( $container['id'] ?? 'container_' . $bi . '_' . $ci ),
                    'title'=>sanitize_text_field( $container['title'] ?? '' ),
                    'layout'=>$this->allowed_layout( sanitize_key( $container['layout'] ?? 'vertical' ) ),
                    'style'=>$this->allowed_container_style( sanitize_key( $container['style'] ?? 'none' ) ),
                    'fields'=>[],
                ];
                foreach ( (array) ( $container['fields'] ?? [] ) as $spec ) {
                    $resolved = $this->resolve_style_field( $spec, $index );
                    if ( $resolved ) { $c['fields'][] = $resolved; $used[ $resolved['name'] ] = true; }
                }
                if ( $c['fields'] ) $new['containers'][] = $c;
            }
            if ( $new['containers'] ) $layout[] = $new;
        }
        if ( ! empty( $style['include_unmapped'] ) ) {
            $remaining = [];
            foreach ( $index as $name => $field ) if ( empty( $used[ $name ] ) ) $remaining[] = $this->normalized_field( $field );
            if ( $remaining ) $layout[] = [ 'id'=>'more_information','title'=>'More Information','type'=>'accordion','style'=>'soft','containers'=>[[ 'id'=>'more','title'=>'Additional Information','layout'=>'vertical','style'=>'none','fields'=>$remaining ]] ];
        }
        return $layout;
    }

    private function resolve_style_field( $spec, $index ) {
        $name = ''; $aliases = []; $display = 'auto';
        if ( is_string( $spec ) ) $name = sanitize_key( $spec );
        elseif ( is_array( $spec ) ) {
            $name = sanitize_key( $spec['name'] ?? '' );
            $aliases = array_map( 'sanitize_key', (array) ( $spec['aliases'] ?? [] ) );
            $display = sanitize_key( $spec['display'] ?? 'auto' );
        }
        foreach ( array_merge( [ $name ], $aliases ) as $candidate ) {
            if ( $candidate && isset( $index[ $candidate ] ) ) return $this->normalized_field( $index[ $candidate ], $display );
        }
        return null;
    }

    private function normalized_field( $field, $display = 'auto' ) {
        return [
            'key'=>sanitize_text_field( $field['key'] ?? '' ),
            'name'=>sanitize_key( $field['name'] ?? '' ),
            'label'=>sanitize_text_field( $field['label'] ?? ( $field['name'] ?? '' ) ),
            'type'=>sanitize_key( $field['type'] ?? 'text' ),
            'display'=>sanitize_key( $display ),
        ];
    }

    private function render_box( $box, $post_id ) {
        if ( ! is_array( $box ) ) return;
        $containers = (array) ( $box['containers'] ?? [] );
        $has = false;
        foreach ( $containers as $c ) if ( $this->container_has_content( $c, $post_id ) ) { $has = true; break; }
        if ( ! $has ) return;
        $type = $this->allowed_box_type( sanitize_key( $box['type'] ?? 'clear' ) );
        $style = $this->allowed_box_style( sanitize_key( $box['style'] ?? 'card' ) );
        $title = sanitize_text_field( $box['title'] ?? '' );
        echo '<section class="npm9r-box npm9r-box-type-' . esc_attr( $type ) . ' npm9r-box-' . esc_attr( $style ) . '">';
        if ( $title ) echo '<h2 class="npm9r-box-title">' . esc_html( $title ) . '</h2>';
        switch ( $type ) {
            case 'tabs': $this->render_tabs( $containers, $post_id, $box ); break;
            case 'accordion': $this->render_accordion( $containers, $post_id ); break;
            case 'carousel': $this->render_collection_box( $containers, $post_id, 'carousel' ); break;
            case 'gallery': $this->render_collection_box( $containers, $post_id, 'gallery' ); break;
            case 'grid': $this->render_collection_box( $containers, $post_id, 'grid' ); break;
            case 'text_list': $this->render_collection_box( $containers, $post_id, 'text_list' ); break;
            case 'timeline': $this->render_timeline( $containers, $post_id ); break;
            case 'chips': $this->render_chips_box( $containers, $post_id ); break;
            case 'stats': $this->render_stats_box( $containers, $post_id ); break;
            default: foreach ( $containers as $c ) $this->render_container( $c, $post_id ); break;
        }
        echo '</section>';
    }

    private function container_has_content( $container, $post_id ) {
        foreach ( (array) ( $container['fields'] ?? [] ) as $field ) if ( ! $this->is_empty_value( $this->field_value( $field, $post_id ) ) ) return true;
        return false;
    }

    private function render_tabs( $containers, $post_id, $box ) {
        $valid = array_values( array_filter( $this->interaction_items( $containers ), function( $c ) use ( $post_id ) { return $this->container_has_content( $c, $post_id ); } ) );
        if ( ! $valid ) return;
        $uid = 'npm9rtabs_' . substr( md5( wp_json_encode( $box ) . $post_id ), 0, 10 );
        echo '<div class="npm9r-tabs" data-npm9r-tabs><div class="npm9r-tab-buttons" role="tablist">';
        foreach ( $valid as $i => $c ) {
            $label = $c['title'] ?? $this->container_label( $c, $i );
            echo '<button type="button" class="npm9r-tab-button' . ( 0 === $i ? ' is-active' : '' ) . '" role="tab" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $uid . '_' . $i ) . '">' . esc_html( $label ) . '</button>';
        }
        echo '</div><div class="npm9r-tab-panels">';
        foreach ( $valid as $i => $c ) {
            echo '<div id="' . esc_attr( $uid . '_' . $i ) . '" class="npm9r-tab-panel' . ( 0 === $i ? ' is-active' : '' ) . '" role="tabpanel"' . ( 0 === $i ? '' : ' hidden' ) . '>';
            $this->render_container( $c, $post_id, true );
            echo '</div>';
        }
        echo '</div></div>';
    }

    private function interaction_items( $containers ) {
        $containers = array_values( (array) $containers );
        if ( 1 === count( $containers ) && count( (array) ( $containers[0]['fields'] ?? [] ) ) > 1 ) {
            $base = $containers[0]; $items = [];
            foreach ( (array) $base['fields'] as $i => $field ) $items[] = [ 'id'=>( $base['id'] ?? 'item' ) . '_' . $i, 'title'=>$field['label'] ?? '', 'layout'=>$base['layout'] ?? 'vertical', 'style'=>$base['style'] ?? 'none', 'fields'=>[ $field ] ];
            return $items;
        }
        return $containers;
    }

    private function render_accordion( $containers, $post_id ) {
        foreach ( $this->interaction_items( $containers ) as $i => $c ) {
            if ( ! $this->container_has_content( $c, $post_id ) ) continue;
            $label = $c['title'] ?? $this->container_label( $c, $i );
            echo '<details class="npm9r-accordion-item"' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $label ) . '</summary><div class="npm9r-accordion-body">';
            $this->render_container( $c, $post_id, true ); echo '</div></details>';
        }
    }

    private function container_label( $c, $i ) {
        $fields = (array) ( $c['fields'] ?? [] );
        return ! empty( $fields[0]['label'] ) ? $fields[0]['label'] : 'Section ' . ( $i + 1 );
    }

    private function render_collection_box( $containers, $post_id, $mode ) {
        $posts = []; $has_collection = false;
        foreach ( $containers as $c ) foreach ( (array) ( $c['fields'] ?? [] ) as $field ) {
            $value = $this->field_value( $field, $post_id );
            $found = $this->posts_from_field( $field, $value, $post_id, $mode );
            if ( $found ) { $has_collection = true; foreach ( $found as $p ) $posts[ $p->ID ] = $p; }
        }
        if ( $has_collection && $posts ) {
            echo '<div class="npm9r-post-collection npm9r-post-collection-' . esc_attr( $mode ) . '">';
            foreach ( $posts as $post ) $this->render_post_card( $post, $mode );
            echo '</div>'; return;
        }
        echo '<div class="npm9r-native-collection npm9r-native-collection-' . esc_attr( $mode ) . '">';
        foreach ( $containers as $c ) { if ( ! $this->container_has_content( $c, $post_id ) ) continue; echo '<div class="npm9r-native-item">'; $this->render_container( $c, $post_id, true ); echo '</div>'; }
        echo '</div>';
    }

    private function posts_from_field( $field, $value, $current_post_id, $mode ) {
        $type = $field['type'] ?? '';
        if ( $this->is_empty_value( $value ) ) return [];
        if ( in_array( $type, [ 'relationship','post_object' ], true ) ) {
            $items = is_array( $value ) ? $value : [ $value ]; $out = [];
            foreach ( $items as $item ) {
                $id = is_object( $item ) && isset( $item->ID ) ? absint( $item->ID ) : absint( $item );
                if ( $id && $id !== $current_post_id ) { $p = get_post( $id ); if ( $p && 'publish' === $p->post_status ) $out[] = $p; }
            }
            return $out;
        }
        if ( 'taxonomy' !== $type && 'category_posts' !== ( $field['display'] ?? '' ) ) return [];
        $terms = is_array( $value ) ? $value : [ $value ]; $ids = [];
        foreach ( $terms as $term ) {
            if ( is_object( $term ) && isset( $term->term_id ) ) $ids[] = absint( $term->term_id );
            elseif ( is_array( $term ) && isset( $term['term_id'] ) ) $ids[] = absint( $term['term_id'] );
            else $ids[] = absint( $term );
        }
        $ids = array_filter( $ids ); if ( ! $ids ) return [];
        $taxonomy = 'category';
        if ( function_exists( 'get_field_object' ) && ! empty( $field['key'] ) ) {
            $obj = get_field_object( $field['key'], $current_post_id, false, false );
            if ( is_array( $obj ) && ! empty( $obj['taxonomy'] ) ) $taxonomy = sanitize_key( $obj['taxonomy'] );
        }
        $args = [ 'post_type'=>'any','post_status'=>'publish','posts_per_page'=>( 'text_list' === $mode ? 30 : 16 ),'post__not_in'=>[ $current_post_id ],'ignore_sticky_posts'=>true ];
        if ( 'category' === $taxonomy ) $args['category__in'] = $ids;
        else $args['tax_query'] = [[ 'taxonomy'=>$taxonomy,'field'=>'term_id','terms'=>$ids ]];
        return get_posts( $args );
    }

    private function render_post_card( $post, $mode ) {
        $url = get_permalink( $post ); $title = get_the_title( $post );
        if ( 'text_list' === $mode ) { echo '<a class="npm9r-text-list-item" href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>'; return; }
        echo '<article class="npm9r-post-card"><a class="npm9r-post-card-link" href="' . esc_url( $url ) . '">';
        if ( has_post_thumbnail( $post ) ) echo get_the_post_thumbnail( $post, 'medium_large', [ 'loading'=>'lazy' ] );
        else echo '<span class="npm9r-post-placeholder" aria-hidden="true"></span>';
        echo '<span class="npm9r-post-card-title">' . esc_html( $title ) . '</span></a></article>';
    }

    private function render_timeline( $containers, $post_id ) {
        echo '<div class="npm9r-timeline">';
        foreach ( $containers as $c ) { if ( ! $this->container_has_content( $c, $post_id ) ) continue; echo '<div class="npm9r-timeline-item"><span class="npm9r-timeline-dot"></span><div>'; if ( ! empty( $c['title'] ) ) echo '<h3>' . esc_html( $c['title'] ) . '</h3>'; $this->render_container( $c, $post_id, true ); echo '</div></div>'; }
        echo '</div>';
    }

    private function render_chips_box( $containers, $post_id ) {
        echo '<div class="npm9r-box-chips">';
        foreach ( $containers as $c ) foreach ( (array) ( $c['fields'] ?? [] ) as $f ) {
            $value = $this->field_value( $f, $post_id ); $items = is_array( $value ) ? $value : [ $value ];
            foreach ( $items as $item ) { if ( is_object( $item ) && isset( $item->name ) ) $item = $item->name; elseif ( is_array( $item ) ) $item = $item['label'] ?? ( $item['name'] ?? '' ); if ( is_scalar( $item ) && '' !== (string) $item ) echo '<span>' . esc_html( (string) $item ) . '</span>'; }
        }
        echo '</div>';
    }

    private function render_stats_box( $containers, $post_id ) {
        echo '<div class="npm9r-stats">';
        foreach ( $containers as $c ) foreach ( (array) ( $c['fields'] ?? [] ) as $f ) { $value = $this->field_value( $f, $post_id ); if ( $this->is_empty_value( $value ) || ! is_scalar( $value ) ) continue; echo '<div class="npm9r-stat"><strong>' . esc_html( (string) $value ) . '</strong><span>' . esc_html( $f['label'] ?? '' ) . '</span></div>'; }
        echo '</div>';
    }

    private function render_container( $container, $post_id, $force = false ) {
        if ( ! is_array( $container ) || ( ! $force && ! $this->container_has_content( $container, $post_id ) ) ) return;
        $layout = $this->allowed_layout( sanitize_key( $container['layout'] ?? 'vertical' ) );
        $style = $this->allowed_container_style( sanitize_key( $container['style'] ?? 'none' ) );
        echo '<div class="npm9r-container npm9r-layout-' . esc_attr( $layout ) . ' npm9r-container-' . esc_attr( $style ) . '">';
        foreach ( (array) ( $container['fields'] ?? [] ) as $field ) $this->render_field( $field, $post_id );
        echo '</div>';
    }

    private function field_value( $field, $post_id ) {
        $selector = ! empty( $field['key'] ) ? $field['key'] : ( $field['name'] ?? '' );
        return $selector && function_exists( 'get_field' ) ? get_field( $selector, $post_id ) : null;
    }

    private function is_empty_value( $value ) { return null === $value || '' === $value || [] === $value || false === $value; }

    private function render_field( $field, $post_id ) {
        $value = $this->field_value( $field, $post_id ); if ( $this->is_empty_value( $value ) ) return;
        $type = sanitize_key( $field['type'] ?? 'text' ); $label = $field['label'] ?? ''; $name = sanitize_html_class( $field['name'] ?? '' );
        echo '<div class="npm9r-field npm9r-field-' . esc_attr( $type ) . ' npm9r-field-' . esc_attr( $name ) . '">';
        if ( $label && ! in_array( $type, [ 'image','gallery','wysiwyg','textarea' ], true ) ) echo '<div class="npm9r-field-label">' . esc_html( $label ) . '</div>';
        echo '<div class="npm9r-field-value">'; $this->render_value( $value, $type, $field, $post_id ); echo '</div></div>';
    }

    private function render_value( $value, $type, $field = [], $post_id = 0 ) {
        switch ( $type ) {
            case 'image':
                $id = is_array( $value ) && isset( $value['ID'] ) ? absint( $value['ID'] ) : ( is_numeric( $value ) ? absint( $value ) : 0 );
                if ( $id ) echo wp_get_attachment_image( $id, 'large', false, [ 'loading'=>'lazy' ] );
                elseif ( is_array( $value ) && ! empty( $value['url'] ) ) echo '<img loading="lazy" src="' . esc_url( $value['url'] ) . '" alt="' . esc_attr( $value['alt'] ?? '' ) . '">';
                elseif ( is_string( $value ) ) echo '<img loading="lazy" src="' . esc_url( $value ) . '" alt="">';
                break;
            case 'gallery':
                if ( is_array( $value ) ) { echo '<div class="npm9r-gallery">'; foreach ( $value as $img ) { $id = is_array( $img ) && isset( $img['ID'] ) ? absint( $img['ID'] ) : ( is_numeric( $img ) ? absint( $img ) : 0 ); if ( $id ) echo wp_get_attachment_image( $id, 'medium_large', false, [ 'loading'=>'lazy' ] ); elseif ( is_array( $img ) && ! empty( $img['url'] ) ) echo '<img loading="lazy" src="' . esc_url( $img['url'] ) . '" alt="">'; } echo '</div>'; }
                break;
            case 'wysiwyg':
                echo wp_kses_post( wpautop( do_shortcode( (string) $value ) ) ); break;
            case 'textarea':
                echo wp_kses_post( wpautop( esc_html( (string) $value ) ) ); break;
            case 'url':
                echo '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener">' . esc_html( $value ) . '</a>'; break;
            case 'email':
                $email = sanitize_email( $value ); echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>'; break;
            case 'file':
                $url = is_array( $value ) && ! empty( $value['url'] ) ? $value['url'] : ( is_string( $value ) ? $value : '' ); if ( $url ) echo '<a class="npm9r-action-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Open / Download</a>'; break;
            case 'checkbox': case 'select': case 'radio': case 'button_group': case 'taxonomy':
                $items = is_array( $value ) ? $value : [ $value ]; echo '<div class="npm9r-chips">'; foreach ( $items as $item ) { if ( is_object( $item ) && isset( $item->name ) ) $item = $item->name; elseif ( is_array( $item ) ) $item = $item['label'] ?? ( $item['name'] ?? '' ); if ( is_scalar( $item ) ) echo '<span>' . esc_html( (string) $item ) . '</span>'; } echo '</div>'; break;
            case 'relationship': case 'post_object':
                $items = is_array( $value ) ? $value : [ $value ]; echo '<ul class="npm9r-related">'; foreach ( $items as $item ) { $id = is_object( $item ) && isset( $item->ID ) ? absint( $item->ID ) : absint( $item ); if ( $id ) echo '<li><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></li>'; } echo '</ul>'; break;
            case 'repeater': case 'group': case 'flexible_content':
                $this->render_nested_value( $value ); break;
            case 'link':
                if ( is_array( $value ) && ! empty( $value['url'] ) ) { $target = '_blank' === ( $value['target'] ?? '' ) ? ' target="_blank" rel="noopener"' : ''; echo '<a class="npm9r-action-link" href="' . esc_url( $value['url'] ) . '"' . $target . '>' . esc_html( $value['title'] ?? 'Open link' ) . '</a>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $target is one of two fixed literals.
                break;
            default:
                if ( is_array( $value ) ) { $scalars = array_filter( $value, 'is_scalar' ); echo esc_html( implode( ', ', array_map( 'strval', $scalars ) ) ); }
                elseif ( is_object( $value ) ) echo esc_html( wp_json_encode( $value ) );
                else echo esc_html( (string) $value );
        }
    }

    private function render_nested_value( $value ) {
        if ( ! is_array( $value ) ) return;
        $rows = isset( $value[0] ) && is_array( $value[0] ) ? $value : [ $value ];
        echo '<div class="npm9r-nested">';
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) continue;
            echo '<div class="npm9r-nested-row">';
            foreach ( $row as $key => $val ) {
                if ( 'acf_fc_layout' === $key || $this->is_empty_value( $val ) ) continue;
                echo '<div class="npm9r-nested-value"><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</strong>';
                if ( is_scalar( $val ) ) echo '<span>' . esc_html( (string) $val ) . '</span>';
                elseif ( is_array( $val ) ) echo '<span>' . esc_html( wp_json_encode( $val ) ) . '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private function allowed_box_type( $type ) {
        return in_array( $type, [ 'clear','hero','tabs','accordion','grid','gallery','carousel','text_list','timeline','chips','stats','cta' ], true ) ? $type : 'clear';
    }
    private function allowed_box_style( $style ) { return in_array( $style, [ 'card','flat','outline','feature','soft' ], true ) ? $style : 'card'; }
    private function allowed_layout( $layout ) { return in_array( $layout, [ 'vertical','horizontal','media-left','media-right','split-left','split-right','equal-2','grid-2','grid-3','grid-4','inline' ], true ) ? $layout : 'vertical'; }
    private function allowed_container_style( $style ) { return in_array( $style, [ 'none','soft','outline','card','highlight' ], true ) ? $style : 'none'; }
    private function sanitize_mode( $mode ) { return in_array( $mode, [ 'replace','before','after' ], true ) ? $mode : 'after'; }

    private function sanitize_color( $value, $allow_empty = false ) {
        $value = trim( sanitize_text_field( (string) $value ) );
        if ( '' === $value && $allow_empty ) return '';
        if ( in_array( $value, [ 'currentColor','transparent','inherit' ], true ) ) return $value;
        return preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9., %]+\)|hsla?\([0-9., %a-zA-Z-]+\))$/', $value ) ? $value : '';
    }

    private function sanitize_style_value( $key, $value ) {
        $value = trim( sanitize_text_field( (string) $value ) );
        if ( in_array( $key, [ 'primary','secondary','accent','surface','text','muted' ], true ) ) return $this->sanitize_color( $value, true );
        if ( in_array( $key, [ 'radius','max_width' ], true ) ) return preg_match( '/^[0-9]+(?:\.[0-9]+)?(?:px|rem|em|%|vw|vh)$/', $value ) ? $value : '';
        if ( 'shadow' === $key ) { if ( 'none' === strtolower( $value ) ) return 'none'; return preg_match( '/^[0-9a-zA-Z#(),.%\s-]+$/', $value ) ? $value : ''; }
        return '';
    }

    private function validate_go_style( $data ) {
        if ( ! is_array( $data ) || '99-go-style' !== ( $data['format'] ?? '' ) || empty( $data['slug'] ) || empty( $data['label'] ) || empty( $data['boxes'] ) || ! is_array( $data['boxes'] ) ) return new WP_Error( 'bad_style', 'Invalid 99 Go Style package.' );
        $clean = [ 'format'=>'99-go-style','version'=>absint( $data['version'] ?? 2 ),'slug'=>sanitize_key( $data['slug'] ),'label'=>sanitize_text_field( $data['label'] ),'description'=>sanitize_textarea_field( $data['description'] ?? '' ),'goal_type'=>sanitize_key( $data['goal_type'] ?? 'generic' ),'recommended_post_type'=>sanitize_key( $data['recommended_post_type'] ?? '' ),'include_unmapped'=>!empty( $data['include_unmapped'] ),'boxes'=>[],'theme'=>[] ];
        foreach ( $data['boxes'] as $bi => $box ) {
            if ( ! is_array( $box ) ) continue;
            $cb = [ 'id'=>sanitize_key( $box['id'] ?? 'box_' . $bi ),'title'=>sanitize_text_field( $box['title'] ?? 'Section' ),'type'=>$this->allowed_box_type( sanitize_key( $box['type'] ?? 'clear' ) ),'style'=>$this->allowed_box_style( sanitize_key( $box['style'] ?? 'card' ) ),'containers'=>[] ];
            foreach ( (array) ( $box['containers'] ?? [] ) as $ci => $container ) {
                if ( ! is_array( $container ) ) continue;
                $cc = [ 'id'=>sanitize_key( $container['id'] ?? 'container_' . $bi . '_' . $ci ),'title'=>sanitize_text_field( $container['title'] ?? '' ),'layout'=>$this->allowed_layout( sanitize_key( $container['layout'] ?? 'vertical' ) ),'style'=>$this->allowed_container_style( sanitize_key( $container['style'] ?? 'none' ) ),'fields'=>[] ];
                foreach ( (array) ( $container['fields'] ?? [] ) as $spec ) {
                    if ( is_string( $spec ) ) $cc['fields'][] = sanitize_key( $spec );
                    elseif ( is_array( $spec ) ) $cc['fields'][] = [ 'name'=>sanitize_key( $spec['name'] ?? '' ),'aliases'=>array_values( array_filter( array_map( 'sanitize_key', (array) ( $spec['aliases'] ?? [] ) ) ) ),'display'=>sanitize_key( $spec['display'] ?? 'auto' ) ];
                }
                if ( $cc['fields'] ) $cb['containers'][] = $cc;
            }
            if ( $cb['containers'] ) $clean['boxes'][] = $cb;
        }
        if ( ! $clean['boxes'] ) return new WP_Error( 'bad_style', 'The style has no usable boxes or fields.' );
        if ( ! empty( $data['theme'] ) && is_array( $data['theme'] ) ) {
            $clean['theme']['label'] = sanitize_text_field( $data['theme']['label'] ?? $clean['label'] );
            $clean['theme']['vars'] = [];
            foreach ( (array) ( $data['theme']['vars'] ?? [] ) as $key => $value ) { $key = sanitize_key( $key ); $val = $this->sanitize_style_value( $key, $value ); if ( '' !== $val ) $clean['theme']['vars'][ $key ] = $val; }
        }
        return $clean;
    }

    private function verify_ajax( $post_id = 0, $manage = false ) {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        if ( $manage && ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message'=>'Administrator permission is required for ACF schemas and shared style libraries.' ], 403 );
        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error( [ 'message'=>'Permission denied.' ], 403 );
        if ( ! $post_id && ! $manage && ! current_user_can( 'edit_posts' ) ) wp_send_json_error( [ 'message'=>'Permission denied.' ], 403 );
    }

    public function package_for_post( $post_id ) {
        $state = $this->state_for_post( $post_id );
        $package = [
            'enabled' => ! empty( $state['enabled'] ),
            'style' => sanitize_key( $state['style'] ?? 'corporate' ),
            'mode' => $this->sanitize_mode( $state['mode'] ?? 'after' ),
            'colors' => (array) ( $state['colors'] ?? [] ),
        ];
        if ( ! isset( $this->built_in_styles()[ $package['style'] ] ) ) {
            $style = $this->get_style_payload( $package['style'], $post_id );
            if ( is_array( $style ) && '99-go-style' === ( $style['format'] ?? '' ) ) $package['style_package'] = $style;
        }
        return $package;
    }

    public function apply_package_to_post( $post_id, $package ) {
        if ( ! is_array( $package ) || ! current_user_can( 'edit_post', $post_id ) ) return false;
        $style = sanitize_key( $package['style'] ?? 'corporate' );
        if ( current_user_can( 'manage_options' ) && ! empty( $package['style_package'] ) && is_array( $package['style_package'] ) ) {
            $clean = $this->validate_go_style( $package['style_package'] );
            if ( ! is_wp_error( $clean ) ) {
                $styles = get_option( $this->styles_option, [] ); if ( ! is_array( $styles ) ) $styles = [];
                $styles[ $clean['slug'] ] = $clean; update_option( $this->styles_option, $styles, false );
                $style = $clean['slug'];
            }
        }
        if ( ! $this->style_exists( $style ) ) $style = 'corporate';
        $colors_in = is_array( $package['colors'] ?? null ) ? $package['colors'] : [];
        $colors = []; foreach ( [ 'primary','secondary','accent','surface','text','muted' ] as $key ) $colors[ $key ] = $this->sanitize_color( $colors_in[ $key ] ?? '', true );
        update_post_meta( $post_id, '_npm9_render_enabled', ! empty( $package['enabled'] ) ? '1' : '0' );
        update_post_meta( $post_id, '_npm9_render_style', $style );
        update_post_meta( $post_id, '_npm9_render_mode', $this->sanitize_mode( sanitize_key( $package['mode'] ?? 'after' ) ) );
        update_post_meta( $post_id, '_npm9_render_colors', $colors );
        return true;
    }

    public function ajax_save_settings() {
        $post_id = absint( $_POST['post_id'] ?? 0 ); $this->verify_ajax( $post_id );
        $enabled = ! empty( $_POST['enabled'] ) ? '1' : '0';
        $style = sanitize_key( wp_unslash( $_POST['style'] ?? 'corporate' ) ); if ( ! $this->style_exists( $style ) ) $style = 'corporate';
        $mode = $this->sanitize_mode( sanitize_key( wp_unslash( $_POST['mode'] ?? 'after' ) ) );
        $colors_in = isset( $_POST['colors'] ) ? json_decode( wp_unslash( $_POST['colors'] ), true ) : [];
        $colors = [];
        foreach ( [ 'primary','secondary','accent','surface','text','muted' ] as $key ) $colors[ $key ] = $this->sanitize_color( $colors_in[ $key ] ?? '', true );
        if ( class_exists( 'Nine_Post_Manager' ) ) { Nine_Post_Manager::instance()->create_recovery_snapshot( $post_id, 'Before presentation style change' ); }
        update_post_meta( $post_id, '_npm9_render_enabled', $enabled );
        update_post_meta( $post_id, '_npm9_render_style', $style );
        update_post_meta( $post_id, '_npm9_render_mode', $mode );
        update_post_meta( $post_id, '_npm9_render_colors', $colors );
        clean_post_cache( $post_id );
        wp_send_json_success( [ 'message'=>'Instant ACF Render settings saved.', 'state'=>$this->state_for_post( $post_id ) ] );
    }

    public function ajax_import_style() {
        $this->verify_ajax( 0, true );
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $data = json_decode( $content, true );
        $clean = $this->validate_go_style( $data );
        if ( is_wp_error( $clean ) ) wp_send_json_error( [ 'message'=>$clean->get_error_message() ] );
        $styles = get_option( $this->styles_option, [] ); if ( ! is_array( $styles ) ) $styles = [];
        $styles[ $clean['slug'] ] = $clean; update_option( $this->styles_option, $styles, false );
        wp_send_json_success( [ 'slug'=>$clean['slug'],'label'=>$clean['label'],'styles'=>$this->all_styles() ] );
    }

    public function ajax_export_style() {
        $post_id = absint( $_POST['post_id'] ?? 0 ); $this->verify_ajax( $post_id );
        $style = $this->exportable_style_for_post( $post_id );
        if ( is_wp_error( $style ) ) wp_send_json_error( [ 'message'=>$style->get_error_message() ] );
        $slug = sanitize_key( $style['slug'] ?? '9pm-style' );
        wp_send_json_success( [ 'filename'=>$slug . '.99gostyle','mime'=>'application/json','content'=>wp_json_encode( $style, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ] );
    }

    private function exportable_style_for_post( $post_id ) {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) return new WP_Error( 'acf_missing', 'ACF is not active.' );
        $slug = sanitize_key( get_post_meta( $post_id, '_npm9_render_style', true ) ?: 'corporate' );
        $payload = $this->get_style_payload( $slug, $post_id );
        if ( ! isset( $this->built_in_styles()[ $slug ] ) && isset( $payload['format'] ) ) {
            $out = $payload;
        } else {
            $layout = $this->automatic_layout( $post_id, $payload );
            $boxes = [];
            foreach ( $layout as $box ) {
                $cb = [ 'id'=>$box['id'],'title'=>$box['title'],'type'=>$box['type'],'style'=>$box['style'],'containers'=>[] ];
                foreach ( $box['containers'] as $container ) {
                    $cb['containers'][] = [ 'id'=>$container['id'],'title'=>$container['title'],'layout'=>$container['layout'],'style'=>$container['style'],'fields'=>array_map( static function( $f ){ return $f['name']; }, $container['fields'] ) ];
                }
                $boxes[] = $cb;
            }
            $out = [ 'format'=>'99-go-style','version'=>2,'slug'=>'9pm-' . $slug . '-' . sanitize_key( get_post_type( $post_id ) ),'label'=>'9PM ' . ( $payload['label'] ?? ucfirst( $slug ) ) . ' — ' . get_post_type( $post_id ),'description'=>'Exported from 9 Post Editor Instant ACF Render. Field names/keys are not changed.','goal_type'=>'generic','recommended_post_type'=>get_post_type( $post_id ),'include_unmapped'=>true,'boxes'=>$boxes,'theme'=>[ 'label'=>'9PM ' . ( $payload['label'] ?? ucfirst( $slug ) ), 'vars'=>$this->style_vars( $slug, $payload, $post_id ) ] ];
        }
        $out['format'] = '99-go-style'; $out['version'] = 2;
        if ( ! isset( $out['theme'] ) ) $out['theme'] = [ 'label'=>$out['label'] ?? '9PM Style', 'vars'=>$this->style_vars( $slug, $payload, $post_id ) ];
        else $out['theme']['vars'] = array_merge( (array) ( $out['theme']['vars'] ?? [] ), array_filter( $this->get_color_overrides( $post_id ) ) );
        return $out;
    }

    public function ajax_import_acf() {
        $this->verify_ajax( 0, true );
        $post_type = sanitize_key( wp_unslash( $_POST['post_type'] ?? 'post' ) );
        if ( ! post_type_exists( $post_type ) ) wp_send_json_error( [ 'message'=>'Selected post type does not exist.' ] );
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $result = $this->import_acf_groups( $content, $post_type );
        if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message'=>$result->get_error_message() ] );
        wp_send_json_success( $result );
    }

    private function import_acf_groups( $content, $post_type ) {
        if ( ! function_exists( 'acf_import_field_group' ) ) return new WP_Error( 'acf_import_unavailable', 'ACF import API is unavailable. Install/activate a current Advanced Custom Fields build before importing schemas.' );
        $data = json_decode( $content, true );
        if ( ! is_array( $data ) ) return new WP_Error( 'bad_json', 'The ACF file is not valid JSON.' );
        if ( isset( $data['acf_groups'] ) && is_array( $data['acf_groups'] ) ) $groups = $data['acf_groups'];
        elseif ( isset( $data['key'] ) && 0 === strpos( (string) $data['key'], 'group_' ) ) $groups = [ $data ];
        else $groups = $data;
        $imported = [];
        foreach ( $groups as $group ) {
            if ( ! is_array( $group ) || empty( $group['key'] ) || 0 !== strpos( (string) $group['key'], 'group_' ) || empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
            $group['location'] = [[ [ 'param'=>'post_type','operator'=>'==','value'=>$post_type ] ]];
            $group['active'] = true;
            $id = acf_import_field_group( $group );
            if ( $id ) $imported[] = [ 'key'=>sanitize_text_field( $group['key'] ),'title'=>sanitize_text_field( $group['title'] ?? $group['key'] ),'id'=>absint( $id ) ];
        }
        if ( ! $imported ) return new WP_Error( 'no_groups', 'No valid ACF field groups were found in the file.' );
        return [ 'message'=>count( $imported ) . ' ACF field group(s) imported and allocated to ' . $post_type . '.', 'groups'=>$imported ];
    }

    private function sanitize_deploy_value( $value ) {
        if ( is_array( $value ) ) { $out = []; foreach ( $value as $key => $item ) $out[ is_int( $key ) ? $key : sanitize_key( $key ) ] = $this->sanitize_deploy_value( $item ); return $out; }
        if ( is_bool( $value ) || is_numeric( $value ) || null === $value ) return $value;
        return wp_kses_post( (string) $value );
    }

    private function apply_deploy_values( $post_id, $values ) {
        if ( ! $values || ! is_array( $values ) || ! function_exists( 'update_field' ) ) return 0;
        $index = $this->field_index_for_post( $post_id ); $count = 0;
        foreach ( $values as $name => $value ) {
            $name = sanitize_key( $name ); if ( ! $name || ! isset( $index[ $name ] ) ) continue;
            $field = $index[ $name ]; $selector = ! empty( $field['key'] ) ? $field['key'] : $name;
            update_field( $selector, $this->sanitize_deploy_value( $value ), $post_id ); $count++;
        }
        return $count;
    }

    public function ajax_quick_deploy() {
        $this->verify_ajax( 0, true );
        $post_type = sanitize_key( wp_unslash( $_POST['post_type'] ?? 'page' ) );
        $pt = get_post_type_object( $post_type );
        if ( ! $pt || ! post_type_exists( $post_type ) || empty( $pt->show_ui ) ) wp_send_json_error( [ 'message'=>'Selected post type does not exist or is not editable.' ] );
        $create_cap = ! empty( $pt->cap->create_posts ) ? $pt->cap->create_posts : ( $pt->cap->edit_posts ?? 'edit_posts' );
        if ( ! current_user_can( $create_cap ) ) wp_send_json_error( [ 'message'=>'You cannot create this post type.' ], 403 );

        $content = isset( $_POST['acf_content'] ) ? wp_unslash( $_POST['acf_content'] ) : '';
        $deploy_data = json_decode( $content, true );
        if ( ! is_array( $deploy_data ) ) wp_send_json_error( [ 'message'=>'The deployment file is not valid JSON.' ] );

        $requested_title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $deploy_post = isset( $deploy_data['post'] ) && is_array( $deploy_data['post'] ) ? $deploy_data['post'] : [];
        $title = $requested_title ?: sanitize_text_field( $deploy_post['title'] ?? 'New ACF Page' );
        $status = sanitize_key( wp_unslash( $_POST['status'] ?? ( $deploy_post['status'] ?? 'draft' ) ) );
        if ( ! in_array( $status, [ 'draft','publish','pending','private' ], true ) ) $status = 'draft';
        if ( 'publish' === $status ) { $cap = $pt->cap->publish_posts ?? 'publish_posts'; if ( ! current_user_can( $cap ) ) $status = 'pending'; }
        $post_args = [ 'post_type'=>$post_type,'post_status'=>$status,'post_title'=>$title ];
        if ( isset( $deploy_post['excerpt'] ) ) $post_args['post_excerpt'] = wp_kses_post( (string) $deploy_post['excerpt'] );
        if ( isset( $deploy_post['content'] ) ) $post_args['post_content'] = current_user_can( 'unfiltered_html' ) ? (string) $deploy_post['content'] : wp_kses_post( (string) $deploy_post['content'] );
        if ( isset( $deploy_post['slug'] ) ) $post_args['post_name'] = sanitize_title( $deploy_post['slug'] );
        $post_id = wp_insert_post( wp_slash( $post_args ), true );
        if ( is_wp_error( $post_id ) ) wp_send_json_error( [ 'message'=>$post_id->get_error_message() ] );

        // Import the schema only after content creation permission and post insertion have succeeded.
        $import = $this->import_acf_groups( $content, $post_type );
        if ( is_wp_error( $import ) ) {
            wp_delete_post( $post_id, true );
            wp_send_json_error( [ 'message'=>$import->get_error_message() ] );
        }

        $values = [];
        if ( isset( $deploy_data['acf_values'] ) && is_array( $deploy_data['acf_values'] ) ) $values = $deploy_data['acf_values'];
        elseif ( isset( $deploy_data['values'] ) && is_array( $deploy_data['values'] ) ) $values = $deploy_data['values'];
        $populated = $this->apply_deploy_values( $post_id, $values );
        $style = sanitize_key( wp_unslash( $_POST['style'] ?? 'corporate' ) ); if ( ! $this->style_exists( $style ) ) $style = 'corporate';
        $colors_in = isset( $_POST['colors'] ) ? json_decode( wp_unslash( $_POST['colors'] ), true ) : [];
        $colors = []; foreach ( [ 'primary','secondary','accent','surface','text','muted' ] as $key ) $colors[ $key ] = $this->sanitize_color( $colors_in[ $key ] ?? '', true );
        update_post_meta( $post_id, '_npm9_render_enabled', '1' );
        update_post_meta( $post_id, '_npm9_render_style', $style );
        update_post_meta( $post_id, '_npm9_render_mode', 'replace' );
        update_post_meta( $post_id, '_npm9_render_colors', $colors );
        $view_url = class_exists( 'Nine_Post_Manager' ) ? Nine_Post_Manager::instance()->view_url_for_post( $post_id ) : ( 'publish' === get_post_status( $post_id ) ? get_permalink( $post_id ) : get_preview_post_link( $post_id ) );
        wp_send_json_success( [
            'message'=>'ACF structure imported and a render-ready post created.',
            'postId'=>$post_id,
            'managerUrl'=>admin_url( 'admin.php?page=nine-post-manager&post_id=' . $post_id ),
            'viewUrl'=>$view_url,
            'acf'=>$import,
            'populatedCount'=>$populated,
        ] );
    }
}
