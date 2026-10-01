<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function ncu_builder_defaults() {
    return array(
        'global_mode' => 'auto',
        'post_types'  => array(),
        'ai_defaults' => array(
            'preset'          => 'inherit',
            'content_width'   => 0,
            'radius'          => -1,
            'spacing_scale'   => '1',
            'font_scale'      => '1',
            'background'      => '',
            'text_color'      => '',
            'accent_color'    => '',
        ),
    );
}

function ncu_get_builder_settings() {
    $saved = get_option( 'ncu_builder_settings', array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }
    return array_replace_recursive( ncu_builder_defaults(), $saved );
}

function ncu_sanitize_builder_settings( $raw ) {
    $defaults = ncu_builder_defaults();
    $out = $defaults;
    if ( ! is_array( $raw ) ) {
        return $out;
    }
    $modes = array( 'auto', 'ai', 'gutenberg', 'elementor' );
    $mode = isset( $raw['global_mode'] ) && is_scalar( $raw['global_mode'] ) ? sanitize_key( (string) $raw['global_mode'] ) : 'auto';
    $out['global_mode'] = in_array( $mode, $modes, true ) ? $mode : 'auto';

    if ( ! empty( $raw['post_types'] ) && is_array( $raw['post_types'] ) ) {
        foreach ( $raw['post_types'] as $post_type => $pt_mode ) {
            $post_type = sanitize_key( $post_type );
            $pt_mode = is_scalar( $pt_mode ) ? sanitize_key( (string) $pt_mode ) : '';
            if ( post_type_exists( $post_type ) && in_array( $pt_mode, $modes, true ) ) {
                $out['post_types'][ $post_type ] = $pt_mode;
            }
        }
    }

    $ai = isset( $raw['ai_defaults'] ) && is_array( $raw['ai_defaults'] ) ? $raw['ai_defaults'] : array();
    $presets = array( 'inherit', 'neutral', 'editorial', 'compact', 'executive', 'learning', 'newsroom', 'visual', 'minimal', 'technical', 'dark' );
    $preset = isset( $ai['preset'] ) && is_scalar( $ai['preset'] ) ? sanitize_key( (string) $ai['preset'] ) : 'inherit';
    $out['ai_defaults']['preset'] = in_array( $preset, $presets, true ) ? $preset : 'inherit';
    $out['ai_defaults']['content_width'] = isset( $ai['content_width'] ) ? max( 0, min( 1800, absint( $ai['content_width'] ) ) ) : 0;
    $radius = isset( $ai['radius'] ) && is_scalar( $ai['radius'] ) ? (int) $ai['radius'] : -1;
    $out['ai_defaults']['radius'] = max( -1, min( 40, $radius ) );
    $out['ai_defaults']['spacing_scale'] = ncu_builder_float_string( isset( $ai['spacing_scale'] ) ? $ai['spacing_scale'] : '1', 0.65, 1.65, 1 );
    $out['ai_defaults']['font_scale'] = ncu_builder_float_string( isset( $ai['font_scale'] ) ? $ai['font_scale'] : '1', 0.8, 1.35, 1 );
    foreach ( array( 'background', 'text_color', 'accent_color' ) as $color_key ) {
        $value = isset( $ai[ $color_key ] ) && is_scalar( $ai[ $color_key ] ) ? sanitize_hex_color( (string) $ai[ $color_key ] ) : '';
        $out['ai_defaults'][ $color_key ] = $value ? $value : '';
    }
    return $out;
}

function ncu_builder_float_string( $value, $min, $max, $fallback ) {
    $number = is_scalar( $value ) ? (float) $value : (float) $fallback;
    $number = max( $min, min( $max, $number ) );
    return rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
}

add_action( 'admin_post_ncu_save_builders', 'ncu_save_builder_settings' );
function ncu_save_builder_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'nine-code' ) );
    }
    check_admin_referer( 'ncu_save_builders' );
    $raw = isset( $_POST['ncu_builders'] ) && is_array( $_POST['ncu_builders'] ) ? wp_unslash( $_POST['ncu_builders'] ) : array();
    update_option( 'ncu_builder_settings', ncu_sanitize_builder_settings( $raw ), false );
    if ( function_exists( 'ncu_notice_push' ) ) { ncu_notice_push( 'Builder defaults saved.', 'success', 'builders-saved' ); }
    wp_safe_redirect( add_query_arg( array( 'page' => 'nine-code-ultra-builders' ), admin_url( 'admin.php' ) ) );
    exit;
}

function ncu_core_builders_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $settings = ncu_get_builder_settings();
    $types = get_post_types( array( 'show_ui' => true ), 'objects' );
    unset( $types['attachment'] );
    $presets = array( 'inherit' => 'Inherit theme design', 'neutral' => '9code Black & White', 'editorial' => 'Editorial', 'compact' => 'Compact', 'executive' => 'Executive', 'learning' => 'Learning', 'newsroom' => 'Newsroom', 'visual' => 'Visual', 'minimal' => 'Minimal', 'technical' => 'Technical', 'dark' => 'Dark Premium' );
    ?>
    <div class="wrap ncu-admin"><?php ncu_admin_header( 'Builders', 'One source of truth; four presentation routes. Auto Builder remains the default.' ); ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ncu_save_builders"><?php wp_nonce_field( 'ncu_save_builders' ); ?>
        <section class="ncu-panel ncu-panel--padded"><h2>Global renderer</h2><p>Used when a page/post has no explicit per-item override.</p><div class="ncu-grid"><label><span>Default renderer</span><?php ncu_builder_mode_select( 'ncu_builders[global_mode]', $settings['global_mode'] ); ?></label></div></section>
        <section class="ncu-panel ncu-panel--padded"><h2>Per post-type defaults</h2><p>Auto is recommended. Individual posts can still override this from the editor toolbar.</p><div class="ncu-builder-type-grid">
        <?php foreach ( $types as $post_type => $obj ) : $value = isset( $settings['post_types'][ $post_type ] ) ? $settings['post_types'][ $post_type ] : $settings['global_mode']; ?><label><span><?php echo esc_html( $obj->labels->singular_name ); ?> <code><?php echo esc_html( $post_type ); ?></code></span><?php ncu_builder_mode_select( 'ncu_builders[post_types][' . $post_type . ']', $value ); ?></label><?php endforeach; ?>
        </div></section>
        <section class="ncu-panel ncu-panel--padded"><h2>AI Builder global defaults</h2><p>AI Builder is a presentation correction layer. It does not duplicate or replace the post's content/data.</p><div class="ncu-grid">
            <label><span>AI design preset</span><select name="ncu_builders[ai_defaults][preset]"><?php foreach ( $presets as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['ai_defaults']['preset'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><span>Content width (0 = inherit)</span><input type="number" min="0" max="1800" name="ncu_builders[ai_defaults][content_width]" value="<?php echo esc_attr( $settings['ai_defaults']['content_width'] ); ?>"></label>
            <label><span>Radius (-1 = inherit)</span><input type="number" min="-1" max="40" name="ncu_builders[ai_defaults][radius]" value="<?php echo esc_attr( $settings['ai_defaults']['radius'] ); ?>"></label>
            <label><span>Spacing scale</span><input type="number" step="0.05" min="0.65" max="1.65" name="ncu_builders[ai_defaults][spacing_scale]" value="<?php echo esc_attr( $settings['ai_defaults']['spacing_scale'] ); ?>"></label>
            <label><span>Font scale</span><input type="number" step="0.05" min="0.8" max="1.35" name="ncu_builders[ai_defaults][font_scale]" value="<?php echo esc_attr( $settings['ai_defaults']['font_scale'] ); ?>"></label>
            <label><span>Background override</span><input type="text" name="ncu_builders[ai_defaults][background]" value="<?php echo esc_attr( $settings['ai_defaults']['background'] ); ?>" placeholder="inherit or #ffffff"><small>Leave blank to inherit.</small></label>
            <label><span>Text override</span><input type="text" name="ncu_builders[ai_defaults][text_color]" value="<?php echo esc_attr( $settings['ai_defaults']['text_color'] ); ?>" placeholder="inherit or #000000"></label>
            <label><span>Accent override</span><input type="text" name="ncu_builders[ai_defaults][accent_color]" value="<?php echo esc_attr( $settings['ai_defaults']['accent_color'] ); ?>" placeholder="inherit or #000000"></label>
        </div></section>
        <section class="ncu-panel ncu-panel--padded"><h2>Builder behavior</h2><div class="ncu-builder-explain"><div><strong>Auto Builder</strong><p>Recommended default. Uses the approved theme/native output and lets legitimate content filters operate.</p></div><div><strong>AI Builder</strong><p>Uses the same post content with per-page design, order, visibility and CSS corrections.</p></div><div><strong>Gutenberg</strong><p>Forces native block/post content so you can temporarily publish a simple message or alternative layout.</p></div><div><strong>Elementor</strong><p>Uses Elementor only when it is installed and the document is available. Otherwise the theme falls back safely.</p></div></div></section>
        <p class="submit"><button class="button button-primary button-hero">Save builder defaults</button></p>
    </form></div>
    <?php
}

function ncu_builder_mode_select( $name, $value ) {
    $modes = array( 'auto' => 'Auto Builder', 'ai' => 'AI Builder', 'gutenberg' => 'Gutenberg', 'elementor' => 'Elementor' );
    ?><select name="<?php echo esc_attr( $name ); ?>"><?php foreach ( $modes as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><?php
}

function ncu_builder_allowed_post_types() {
    $types = apply_filters( 'ncu_builder_post_types', array( 'post', 'page' ) );
    $types = is_array( $types ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $types ) ) ) ) : array( 'post', 'page' );
    return $types;
}

add_action( 'init', 'ncu_register_builder_meta', 100 );
function ncu_register_builder_meta() {
    $post_types = ncu_builder_allowed_post_types();
    foreach ( $post_types as $post_type ) {
        if ( 'attachment' === $post_type ) { continue; }
        if ( post_type_supports( $post_type, 'editor' ) && ! post_type_supports( $post_type, 'custom-fields' ) ) {
            add_post_type_support( $post_type, 'custom-fields' );
        }
        $string_fields = array(
            'ncu_render_mode', 'ncu_ai_preset', 'ncu_ai_spacing_scale', 'ncu_ai_font_scale', 'ncu_ai_background', 'ncu_ai_text_color', 'ncu_ai_accent_color', 'ncu_ai_hidden_sections', 'ncu_ai_section_order', 'ncu_page_css',
        );
        foreach ( $string_fields as $key ) {
            register_post_meta( $post_type, $key, array(
                'single'            => true,
                'type'              => 'string',
                'show_in_rest'      => true,
                'sanitize_callback' => 'ncu_sanitize_builder_meta',
                'auth_callback'     => 'ncu_builder_meta_auth',
                'default'           => '',
            ) );
        }
        foreach ( array( 'ncu_ai_content_width', 'ncu_ai_radius' ) as $key ) {
            register_post_meta( $post_type, $key, array(
                'single'            => true,
                'type'              => 'integer',
                'show_in_rest'      => true,
                'sanitize_callback' => 'absint',
                'auth_callback'     => 'ncu_builder_meta_auth',
                'default'           => 0,
            ) );
        }
    }
}

function ncu_builder_meta_auth( $allowed = false, $meta_key = '', $post_id = 0 ) {
    return $post_id ? current_user_can( 'edit_post', (int) $post_id ) : current_user_can( 'edit_posts' );
}

function ncu_sanitize_builder_meta( $value, $meta_key = '' ) {
    $value = is_scalar( $value ) ? (string) $value : '';
    switch ( $meta_key ) {
        case 'ncu_render_mode':
            $v = sanitize_key( $value );
            return in_array( $v, array( 'auto', 'ai', 'gutenberg', 'elementor' ), true ) ? $v : '';
        case 'ncu_ai_preset':
            $v = sanitize_key( $value );
            return in_array( $v, array( 'inherit', 'neutral', 'editorial', 'compact', 'executive', 'learning', 'newsroom', 'visual', 'minimal', 'technical', 'dark' ), true ) ? $v : 'inherit';
        case 'ncu_ai_spacing_scale':
            return ncu_builder_float_string( $value, 0.65, 1.65, 1 );
        case 'ncu_ai_font_scale':
            return ncu_builder_float_string( $value, 0.8, 1.35, 1 );
        case 'ncu_ai_background':
        case 'ncu_ai_text_color':
        case 'ncu_ai_accent_color':
            $color = sanitize_hex_color( $value );
            return $color ? $color : '';
        case 'ncu_ai_hidden_sections':
        case 'ncu_ai_section_order':
            return ncu_sanitize_index_list( $value );
        case 'ncu_page_css':
            return ncu_sanitize_page_css( $value );
        default:
            return sanitize_text_field( $value );
    }
}

function ncu_sanitize_index_list( $value ) {
    $parts = preg_split( '/[\s,]+/', (string) $value );
    $out = array();
    foreach ( $parts as $part ) {
        if ( '' === $part || ! ctype_digit( $part ) ) { continue; }
        $i = (int) $part;
        if ( $i >= 0 && $i <= 999 && ! in_array( $i, $out, true ) ) { $out[] = $i; }
    }
    return implode( ',', $out );
}

function ncu_sanitize_page_css( $css ) {
    $css = is_scalar( $css ) ? (string) $css : '';
    $css = wp_strip_all_tags( $css );
    $css = substr( $css, 0, 20000 );
    $css = preg_replace( '/@import\b[^;]*;?/i', '', $css );
    $css = preg_replace( '/@charset\b[^;]*;?/i', '', $css );
    $css = preg_replace( '/expression\s*\([^)]*\)/i', '', $css );
    $css = preg_replace( '/(?:javascript|vbscript)\s*:/i', '', $css );
    $css = preg_replace( '/-moz-binding\s*:/i', '', $css );
    $css = preg_replace( '/behavior\s*:/i', '', $css );
    $css = preg_replace( '/url\s*\([^)]*\)/i', 'none', $css );
    return trim( $css );
}

function ncu_get_post_render_mode( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) { return 'auto'; }
    $meta = sanitize_key( (string) get_post_meta( $post_id, 'ncu_render_mode', true ) );
    if ( in_array( $meta, array( 'auto', 'ai', 'gutenberg', 'elementor' ), true ) ) {
        return $meta;
    }
    $settings = ncu_get_builder_settings();
    $post_type = get_post_type( $post_id );
    if ( $post_type && isset( $settings['post_types'][ $post_type ] ) && in_array( $settings['post_types'][ $post_type ], array( 'auto', 'ai', 'gutenberg', 'elementor' ), true ) ) {
        $resolved = $settings['post_types'][ $post_type ];
    } else {
        $resolved = in_array( $settings['global_mode'], array( 'auto', 'ai', 'gutenberg', 'elementor' ), true ) ? $settings['global_mode'] : 'auto';
    }
    $filtered = apply_filters( 'ncu_default_render_mode', $resolved, $post_id, $post_type );
    $filtered = is_scalar( $filtered ) ? sanitize_key( (string) $filtered ) : $resolved;
    return in_array( $filtered, array( 'auto', 'ai', 'gutenberg', 'elementor' ), true ) ? $filtered : $resolved;
}

function ncu_get_ai_config( $post_id ) {
    $defaults = ncu_get_builder_settings();
    $defaults = $defaults['ai_defaults'];
    $post_id = absint( $post_id );
    $config = array(
        'preset'          => (string) get_post_meta( $post_id, 'ncu_ai_preset', true ),
        'content_width'   => (int) get_post_meta( $post_id, 'ncu_ai_content_width', true ),
        'radius'          => (int) get_post_meta( $post_id, 'ncu_ai_radius', true ),
        'spacing_scale'   => (string) get_post_meta( $post_id, 'ncu_ai_spacing_scale', true ),
        'font_scale'      => (string) get_post_meta( $post_id, 'ncu_ai_font_scale', true ),
        'background'      => (string) get_post_meta( $post_id, 'ncu_ai_background', true ),
        'text_color'      => (string) get_post_meta( $post_id, 'ncu_ai_text_color', true ),
        'accent_color'    => (string) get_post_meta( $post_id, 'ncu_ai_accent_color', true ),
        'hidden_sections' => (string) get_post_meta( $post_id, 'ncu_ai_hidden_sections', true ),
        'section_order'   => (string) get_post_meta( $post_id, 'ncu_ai_section_order', true ),
        'page_css'        => (string) get_post_meta( $post_id, 'ncu_page_css', true ),
    );
    foreach ( array( 'preset', 'spacing_scale', 'font_scale', 'background', 'text_color', 'accent_color' ) as $key ) {
        if ( '' === $config[ $key ] ) { $config[ $key ] = isset( $defaults[ $key ] ) ? $defaults[ $key ] : ''; }
    }
    if ( 0 === $config['content_width'] ) { $config['content_width'] = (int) $defaults['content_width']; }
    if ( 0 === $config['radius'] && -1 !== (int) $defaults['radius'] ) { $config['radius'] = (int) $defaults['radius']; }
    return $config;
}

add_filter( 'the_content', 'ncu_ai_restructure_content', 8 );
function ncu_ai_restructure_content( $content ) {
    if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $content; }
    $post_id = get_the_ID();
    if ( ! $post_id || ! in_array( get_post_type( $post_id ), ncu_builder_allowed_post_types(), true ) || 'ai' !== ncu_get_post_render_mode( $post_id ) ) { return $content; }
    return ncu_ai_restructure_raw( $content, $post_id );
}

function ncu_ai_restructure_raw( $content, $post_id ) {
    if ( ! has_blocks( $content ) ) { return $content; }
    $config = ncu_get_ai_config( $post_id );
    $blocks = parse_blocks( $content );
    if ( ! is_array( $blocks ) ) { return $content; }
    $hidden = ncu_index_list_to_array( $config['hidden_sections'] );
    $ordered = ncu_index_list_to_array( $config['section_order'] );
    $kept = array();
    foreach ( $blocks as $index => $block ) {
        if ( ! in_array( (int) $index, $hidden, true ) ) { $kept[ (int) $index ] = $block; }
    }
    if ( $ordered ) {
        $new = array();
        foreach ( $ordered as $index ) {
            if ( isset( $kept[ $index ] ) ) { $new[] = $kept[ $index ]; unset( $kept[ $index ] ); }
        }
        foreach ( $kept as $block ) { $new[] = $block; }
        $blocks = $new;
    } else {
        $blocks = array_values( $kept );
    }
    return serialize_blocks( $blocks );
}

function ncu_index_list_to_array( $value ) {
    $sanitized = ncu_sanitize_index_list( $value );
    if ( '' === $sanitized ) { return array(); }
    return array_map( 'intval', explode( ',', $sanitized ) );
}

add_action( 'add_meta_boxes', 'ncu_add_classic_builder_box' );
function ncu_add_classic_builder_box() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) { return; }
    $types = ncu_builder_allowed_post_types();
    foreach ( $types as $type ) {
        if ( 'attachment' === $type ) { continue; }
        add_meta_box( 'ncu-builders', 'Nine Code Builders', 'ncu_classic_builder_box', $type, 'side', 'high' );
    }
}

function ncu_classic_builder_box( $post ) {
    wp_nonce_field( 'ncu_classic_builder', 'ncu_classic_builder_nonce' );
    $mode = ncu_get_post_render_mode( $post->ID );
    $config = ncu_get_ai_config( $post->ID );
    ?><p><label><strong>Renderer</strong><br><?php ncu_builder_mode_select( 'ncu_render_mode_classic', $mode ); ?></label></p>
    <p><label>AI preset<br><select name="ncu_ai_preset_classic"><option value="inherit" <?php selected( $config['preset'], 'inherit' ); ?>>Inherit</option><option value="neutral" <?php selected( $config['preset'], 'neutral' ); ?>>9code Black & White</option><option value="editorial" <?php selected( $config['preset'], 'editorial' ); ?>>Editorial</option><option value="executive" <?php selected( $config['preset'], 'executive' ); ?>>Executive</option><option value="learning" <?php selected( $config['preset'], 'learning' ); ?>>Learning</option><option value="dark" <?php selected( $config['preset'], 'dark' ); ?>>Dark Premium</option></select></label></p>
    <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-doctor&post_id=' . $post->ID ) ); ?>">Open Doctor</a></p><?php
}

add_action( 'save_post', 'ncu_save_classic_builder_box' );
function ncu_save_classic_builder_box( $post_id ) {
    if ( ! isset( $_POST['ncu_classic_builder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ncu_classic_builder_nonce'] ) ), 'ncu_classic_builder' ) ) { return; }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
    if ( isset( $_POST['ncu_render_mode_classic'] ) ) { update_post_meta( $post_id, 'ncu_render_mode', ncu_sanitize_builder_meta( wp_unslash( $_POST['ncu_render_mode_classic'] ), 'ncu_render_mode' ) ); }
    if ( isset( $_POST['ncu_ai_preset_classic'] ) ) { update_post_meta( $post_id, 'ncu_ai_preset', ncu_sanitize_builder_meta( wp_unslash( $_POST['ncu_ai_preset_classic'] ), 'ncu_ai_preset' ) ); }
}

add_action( 'enqueue_block_editor_assets', 'ncu_enqueue_editor_tools' );
function ncu_enqueue_editor_tools() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || empty( $screen->post_type ) || ! method_exists( $screen, 'is_block_editor' ) || ! $screen->is_block_editor() || ! in_array( $screen->post_type, ncu_builder_allowed_post_types(), true ) ) { return; }
    $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
    if ( ! $post_id && isset( $GLOBALS['post']->ID ) ) { $post_id = (int) $GLOBALS['post']->ID; }
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { return; }
    wp_enqueue_style( 'ncu-editor-tools', NCU_CORE_URL . 'assets/css/editor-tools.css', array(), NCU_CORE_VERSION );
    wp_enqueue_script( 'ncu-editor-tools', NCU_CORE_URL . 'assets/js/editor-tools.js', array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ), NCU_CORE_VERSION, true );
    wp_localize_script( 'ncu-editor-tools', 'NCU_EDITOR', array(
        'postId'           => $post_id,
        'postType'         => get_post_type( $post_id ),
        'fileBase'         => '9code-' . ( sanitize_title( get_the_title( $post_id ) ) ? sanitize_title( get_the_title( $post_id ) ) : get_post_type( $post_id ) ) . '-' . $post_id,
        'restBase'         => '/ncu/v2',
        'nonce'            => wp_create_nonce( 'wp_rest' ),
        'dashboardUrl'     => admin_url( 'admin.php?page=nine-code-ultra' ),
        'buildersUrl'      => admin_url( 'admin.php?page=nine-code-ultra-builders' ),
        'designUrl'        => admin_url( 'admin.php?page=nine-code-ultra-design' ),
        'styleAuthorityUrl'=> admin_url( 'admin.php?page=nine-code-ultra-style-takeover' ),
        'doctorUrl'        => admin_url( 'admin.php?page=nine-code-ultra-doctor&post_id=' . $post_id ),
        'elementorActive'  => did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ),
        'elementorEditUrl' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
        'globalMode'       => ncu_get_post_render_mode( $post_id ),
        'iconUrl'          => NCU_CORE_URL . 'assets/images/9code-theme-icon.png',
    ) );
}

add_action( 'rest_api_init', 'ncu_register_builder_rest_routes' );
function ncu_register_builder_rest_routes() {
    register_rest_route( 'ncu/v2', '/ai-export/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ncu_rest_ai_export',
        'permission_callback' => 'ncu_rest_can_edit_post',
        'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
    ) );
    register_rest_route( 'ncu/v2', '/ai-import/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'ncu_rest_ai_import',
        'permission_callback' => 'ncu_rest_can_edit_post',
        'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
    ) );
}

function ncu_rest_can_edit_post( $request ) {
    $post_id = absint( $request['id'] );
    return $post_id
        && in_array( get_post_type( $post_id ), ncu_builder_allowed_post_types(), true )
        && current_user_can( 'edit_post', $post_id );
}

function ncu_rest_ai_export( $request ) {
    return rest_ensure_response( ncu_build_ai_export_package( absint( $request['id'] ) ) );
}

function ncu_build_ai_export_package( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post ) { return array( 'error' => 'Post not found.' ); }
    return array(
        'format'       => '9-code-ultra-ai-page',
        'schema'       => 2,
        'generated_at' => gmdate( 'c' ),
        'source'       => array( 'post_id' => $post_id, 'post_type' => $post->post_type, 'title' => get_the_title( $post_id ), 'url' => get_permalink( $post_id ) ),
        'renderer'     => ncu_get_post_render_mode( $post_id ),
        'ai_builder'   => ncu_get_ai_config( $post_id ),
        'structure'    => ncu_builder_block_outline( parse_blocks( $post->post_content ) ),
        'instructions' => array(
            'purpose' => 'Presentation correction only. Do not duplicate or rewrite source content unless the site owner separately requests content editing.',
            'editable' => array( 'renderer', 'ai_builder.preset', 'ai_builder.content_width', 'ai_builder.radius', 'ai_builder.spacing_scale', 'ai_builder.font_scale', 'ai_builder.background', 'ai_builder.text_color', 'ai_builder.accent_color', 'ai_builder.hidden_sections', 'ai_builder.section_order', 'ai_builder.page_css' ),
            'repair_boundary' => 'No executable PHP or JavaScript is accepted by Nine Code import.',
        ),
    );
}

function ncu_builder_block_outline( $blocks, $depth = 0 ) {
    $out = array();
    if ( ! is_array( $blocks ) || $depth > 8 ) { return $out; }
    foreach ( $blocks as $index => $block ) {
        if ( empty( $block['blockName'] ) && empty( trim( isset( $block['innerHTML'] ) ? $block['innerHTML'] : '' ) ) ) { continue; }
        $item = array(
            'index' => (int) $index,
            'block' => isset( $block['blockName'] ) ? $block['blockName'] : 'freeform',
            'attrs' => isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? ncu_builder_safe_attrs( $block['attrs'] ) : array(),
        );
        if ( ! empty( $block['innerBlocks'] ) ) { $item['children'] = ncu_builder_block_outline( $block['innerBlocks'], $depth + 1 ); }
        $out[] = $item;
    }
    return $out;
}

function ncu_builder_safe_attrs( $attrs ) {
    $safe = array();
    foreach ( $attrs as $key => $value ) {
        if ( is_scalar( $value ) && strlen( (string) $value ) <= 500 ) { $safe[ sanitize_key( $key ) ] = $value; }
        elseif ( is_array( $value ) && count( $value ) <= 30 ) { $safe[ sanitize_key( $key ) ] = $value; }
    }
    return $safe;
}

function ncu_rest_ai_import( $request ) {
    $post_id = absint( $request['id'] );
    $payload = $request->get_json_params();
    if ( ! is_array( $payload ) || '9-code-ultra-ai-page' !== ( isset( $payload['format'] ) ? $payload['format'] : '' ) ) {
        return new WP_Error( 'ncu_invalid_ai_package', 'Invalid Nine Code AI package.', array( 'status' => 400 ) );
    }
    if ( strlen( (string) wp_json_encode( $payload ) ) > 1048576 ) {
        return new WP_Error( 'ncu_ai_package_too_large', 'AI presentation packages must be 1 MB or smaller.', array( 'status' => 413 ) );
    }
    $result = ncu_apply_ai_package( $post_id, $payload );
    return rest_ensure_response( array( 'ok' => true, 'updated' => $result ) );
}

function ncu_apply_ai_package( $post_id, $payload ) {
    $updated = array();
    if ( isset( $payload['renderer'] ) ) {
        $mode = ncu_sanitize_builder_meta( $payload['renderer'], 'ncu_render_mode' );
        if ( $mode ) { update_post_meta( $post_id, 'ncu_render_mode', $mode ); $updated[] = 'renderer'; }
    }
    $ai = isset( $payload['ai_builder'] ) && is_array( $payload['ai_builder'] ) ? $payload['ai_builder'] : array();
    $map = array(
        'preset' => 'ncu_ai_preset', 'content_width' => 'ncu_ai_content_width', 'radius' => 'ncu_ai_radius', 'spacing_scale' => 'ncu_ai_spacing_scale', 'font_scale' => 'ncu_ai_font_scale', 'background' => 'ncu_ai_background', 'text_color' => 'ncu_ai_text_color', 'accent_color' => 'ncu_ai_accent_color', 'hidden_sections' => 'ncu_ai_hidden_sections', 'section_order' => 'ncu_ai_section_order', 'page_css' => 'ncu_page_css',
    );
    foreach ( $map as $source => $meta_key ) {
        if ( ! array_key_exists( $source, $ai ) ) { continue; }
        if ( in_array( $meta_key, array( 'ncu_ai_content_width', 'ncu_ai_radius' ), true ) ) {
            $value = max( 0, min( 'ncu_ai_content_width' === $meta_key ? 1800 : 40, (int) $ai[ $source ] ) );
        } else {
            $value = ncu_sanitize_builder_meta( $ai[ $source ], $meta_key );
        }
        update_post_meta( $post_id, $meta_key, $value );
        $updated[] = $source;
    }
    return $updated;
}
