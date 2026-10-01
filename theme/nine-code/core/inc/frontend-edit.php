<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Nine Code Front-End Edit Layer.
 *
 * Canonical-data rule: this layer edits the same WordPress post/theme options
 * already used by wp-admin. It never creates a parallel page record.
 */

function ncu_frontend_edit_can_manage_theme() {
    return is_user_logged_in() && current_user_can( 'manage_options' );
}

function ncu_frontend_edit_post_id() {
    if ( ! is_singular() ) { return 0; }
    return absint( get_queried_object_id() );
}

function ncu_frontend_edit_can_edit_post( $post_id = 0 ) {
    $post_id = $post_id ? absint( $post_id ) : ncu_frontend_edit_post_id();
    return $post_id && is_user_logged_in() && current_user_can( 'edit_post', $post_id );
}

function ncu_frontend_edit_is_active() {
    if ( is_admin() || wp_doing_ajax() || ! is_user_logged_in() ) { return false; }
    $s = ncu_get_settings();
    if ( empty( $s['frontend_edit_enabled'] ) ) { return false; }
    /* Public pages remain clean by default. A developer can deliberately opt
     * the runtime back in without reintroducing floating controls globally. */
    if ( ! apply_filters( 'ncu_frontend_edit_runtime_enabled', false, $s ) ) { return false; }
    return ncu_frontend_edit_can_manage_theme() || ncu_frontend_edit_can_edit_post();
}

/**
 * Public helper for theme/plugin templates.
 * A plugin may use this helper around a region it owns without giving Core
 * ownership of the plugin's data.
 */
function ncu_frontend_edit_region_attrs( $region, $panel = 'content', $label = '' ) {
    if ( ! ncu_frontend_edit_is_active() ) { return ''; }
    $region = sanitize_key( $region );
    $panel  = sanitize_key( $panel );
    if ( ! $region || ! $panel ) { return ''; }
    $label  = $label ? sanitize_text_field( $label ) : ucfirst( str_replace( '-', ' ', $region ) );
    return ' data-ncu-edit-region="' . esc_attr( $region ) . '" data-ncu-edit-panel="' . esc_attr( $panel ) . '" data-ncu-edit-label="' . esc_attr( $label ) . '"';
}

function ncu_frontend_edit_badge( $region, $panel = 'content', $label = 'Edit' ) {
    if ( ! ncu_frontend_edit_is_active() ) { return; }
    $s = ncu_get_settings();
    if ( empty( $s['frontend_edit_badges'] ) ) { return; }
    $region = sanitize_key( $region );
    $panel  = sanitize_key( $panel );
    if ( in_array( $panel, array( 'header', 'footer', 'theme' ), true ) && ! ncu_frontend_edit_can_manage_theme() ) { return; }
    if ( in_array( $panel, array( 'content', 'post', 'presentation' ), true ) && ! ncu_frontend_edit_can_edit_post() ) { return; }
    echo '<button type="button" class="ncu-fe-region-edit" data-ncu-fe-open="' . esc_attr( $panel ) . '" data-ncu-fe-region="' . esc_attr( $region ) . '"><span aria-hidden="true">✎</span> ' . esc_html( $label ) . '</button>';
}

function ncu_frontend_edit_default_regions( $post_id ) {
    $regions = array();
    if ( ncu_frontend_edit_can_manage_theme() ) {
        $regions[] = array( 'selector' => '#ncu-site-header', 'label' => 'Edit header', 'panel' => 'header' );
        $regions[] = array( 'selector' => '.ncu-site-footer', 'label' => 'Edit footer', 'panel' => 'footer' );
    }
    if ( $post_id && ncu_frontend_edit_can_edit_post( $post_id ) ) {
        $regions[] = array( 'selector' => '#post-' . absint( $post_id ), 'label' => 'Edit content', 'panel' => 'content' );
    }
    return apply_filters( 'ncu_frontend_edit_regions', $regions, $post_id );
}

add_action( 'wp_enqueue_scripts', 'ncu_frontend_edit_enqueue', 999 );
function ncu_frontend_edit_enqueue() {
    if ( ! ncu_frontend_edit_is_active() ) { return; }
    $post_id = ncu_frontend_edit_post_id();
    $s = ncu_get_settings();

    wp_enqueue_style( 'ncu-front-edit', NCU_CORE_URL . 'assets/css/front-edit.css', array(), NCU_CORE_VERSION );
    wp_enqueue_script( 'ncu-front-edit', NCU_CORE_URL . 'assets/js/front-edit.js', array( 'jquery' ), NCU_CORE_VERSION, true );

    if ( $post_id && ncu_frontend_edit_can_edit_post( $post_id ) && ! empty( $s['frontend_edit_featured'] ) ) {
        wp_enqueue_media();
    }

    $post = $post_id ? get_post( $post_id ) : null;
    $modified = $post ? (string) $post->post_modified_gmt : '';
    $render_mode = $post_id && function_exists( 'ncu_get_post_render_mode' ) ? ncu_get_post_render_mode( $post_id ) : 'auto';
    $regions = ncu_frontend_edit_default_regions( $post_id );

    wp_localize_script( 'ncu-front-edit', 'NCU_FRONT_EDIT', array(
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'ncu_frontend_edit' ),
        'postId'        => $post_id,
        'modifiedGmt'   => $modified,
        'renderMode'    => $render_mode,
        'canEditPost'   => (bool) ( $post_id && ncu_frontend_edit_can_edit_post( $post_id ) ),
        'canManageTheme'=> (bool) ncu_frontend_edit_can_manage_theme(),
        'regions'       => array_values( is_array( $regions ) ? $regions : array() ),
        'editUrl'       => $post_id ? get_edit_post_link( $post_id, 'raw' ) : '',
        'elementorUrl'  => $post_id && ( defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' ) ) ? admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) : '',
        'messages'      => array(
            'saving'   => __( 'Saving…', 'nine-code' ),
            'saved'    => __( 'Saved. Reloading the page…', 'nine-code' ),
            'stale'    => __( 'This content changed after you opened the page. Reload before overwriting the newer version.', 'nine-code' ),
            'error'    => __( 'The change could not be saved.', 'nine-code' ),
        ),
    ) );
}

add_action( 'wp_footer', 'ncu_frontend_edit_render_ui', 999 );
function ncu_frontend_edit_render_ui() {
    if ( ! ncu_frontend_edit_is_active() ) { return; }
    $s = ncu_get_settings();
    $post_id = ncu_frontend_edit_post_id();
    $post = $post_id ? get_post( $post_id ) : null;
    $can_post = $post && ncu_frontend_edit_can_edit_post( $post_id );
    $can_theme = ncu_frontend_edit_can_manage_theme();
    $featured_id = $post_id ? get_post_thumbnail_id( $post_id ) : 0;
    $featured_url = $featured_id ? wp_get_attachment_image_url( $featured_id, 'thumbnail' ) : '';
    ?>
    <div class="ncu-fe-dock" data-ncu-fe-dock>
        <?php if ( $can_post ) : ?><button type="button" class="ncu-fe-dock__button" data-ncu-fe-open="content"><span aria-hidden="true">✎</span><b><?php esc_html_e( 'Edit', 'nine-code' ); ?></b></button><?php endif; ?>
        <?php if ( $can_theme && ! empty( $s['frontend_edit_theme_regions'] ) ) : ?><button type="button" class="ncu-fe-dock__button ncu-fe-dock__button--secondary" data-ncu-fe-open="theme"><span aria-hidden="true">⌘</span><b><?php esc_html_e( 'Theme', 'nine-code' ); ?></b></button><?php endif; ?>
    </div>

    <div class="ncu-fe-shell" data-ncu-fe-shell hidden>
        <button class="ncu-fe-backdrop" type="button" data-ncu-fe-close aria-label="<?php esc_attr_e( 'Close front-end editor', 'nine-code' ); ?>"></button>
        <section class="ncu-fe-panel" role="dialog" aria-modal="true" aria-labelledby="ncu-fe-title" tabindex="-1">
            <header class="ncu-fe-panel__head">
                <div><small>9 CODE · LIVE EDIT</small><h2 id="ncu-fe-title"><?php esc_html_e( 'Front-End Editor', 'nine-code' ); ?></h2></div>
                <button type="button" class="ncu-fe-close" data-ncu-fe-close aria-label="<?php esc_attr_e( 'Close', 'nine-code' ); ?>">×</button>
            </header>
            <nav class="ncu-fe-tabs" aria-label="<?php esc_attr_e( 'Editing sections', 'nine-code' ); ?>">
                <?php if ( $can_post ) : ?><button type="button" data-ncu-fe-tab="content"><?php esc_html_e( 'Content', 'nine-code' ); ?></button><?php endif; ?>
                <?php if ( $can_post && ! empty( $s['frontend_edit_builder'] ) ) : ?><button type="button" data-ncu-fe-tab="presentation"><?php esc_html_e( 'Presentation', 'nine-code' ); ?></button><?php endif; ?>
                <?php if ( $can_theme && ! empty( $s['frontend_edit_theme_regions'] ) ) : ?><button type="button" data-ncu-fe-tab="header"><?php esc_html_e( 'Header', 'nine-code' ); ?></button><button type="button" data-ncu-fe-tab="footer"><?php esc_html_e( 'Footer', 'nine-code' ); ?></button><button type="button" data-ncu-fe-tab="theme"><?php esc_html_e( 'Theme', 'nine-code' ); ?></button><?php endif; ?>
            </nav>
            <div class="ncu-fe-panel__body">
                <?php if ( $can_post && ! empty( $s['frontend_edit_content'] ) ) : ?>
                <form class="ncu-fe-pane" data-ncu-fe-pane="content" data-ncu-fe-form="post">
                    <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
                    <input type="hidden" name="modified_gmt" value="<?php echo esc_attr( $post->post_modified_gmt ); ?>" data-ncu-modified>
                    <label><span><?php esc_html_e( 'Title', 'nine-code' ); ?></span><input type="text" name="post_title" value="<?php echo esc_attr( $post->post_title ); ?>"></label>
                    <?php if ( post_type_supports( $post->post_type, 'excerpt' ) ) : ?><label><span><?php esc_html_e( 'Excerpt', 'nine-code' ); ?></span><textarea name="post_excerpt" rows="4"><?php echo esc_textarea( $post->post_excerpt ); ?></textarea></label><?php endif; ?>
                    <label class="ncu-fe-field--content"><span><?php esc_html_e( 'Content', 'nine-code' ); ?></span><textarea name="post_content" rows="16" spellcheck="true"><?php echo esc_textarea( $post->post_content ); ?></textarea><small><?php esc_html_e( 'Edits the canonical WordPress content. Block markup and shortcodes are preserved.', 'nine-code' ); ?></small></label>
                    <?php if ( ! empty( $s['frontend_edit_featured'] ) && post_type_supports( $post->post_type, 'thumbnail' ) ) : ?>
                    <div class="ncu-fe-featured">
                        <span><?php esc_html_e( 'Featured image', 'nine-code' ); ?></span>
                        <div class="ncu-fe-featured__row"><div class="ncu-fe-featured__preview" data-ncu-featured-preview><?php if ( $featured_url ) : ?><img src="<?php echo esc_url( $featured_url ); ?>" alt=""><?php endif; ?></div><input type="hidden" name="featured_image_id" value="<?php echo esc_attr( $featured_id ); ?>" data-ncu-featured-id><button type="button" class="ncu-fe-small" data-ncu-featured-choose><?php esc_html_e( 'Choose image', 'nine-code' ); ?></button><button type="button" class="ncu-fe-link" data-ncu-featured-clear><?php esc_html_e( 'Clear', 'nine-code' ); ?></button></div>
                    </div>
                    <?php endif; ?>
                    <div class="ncu-fe-actions"><button type="submit" class="ncu-fe-primary"><?php esc_html_e( 'Save content', 'nine-code' ); ?></button><?php if ( get_edit_post_link( $post_id, 'raw' ) ) : ?><a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>" class="ncu-fe-secondary"><?php esc_html_e( 'Full editor', 'nine-code' ); ?></a><?php endif; ?></div>
                </form>
                <?php endif; ?>

                <?php if ( $can_post && ! empty( $s['frontend_edit_builder'] ) ) : $mode = function_exists( 'ncu_get_post_render_mode' ) ? ncu_get_post_render_mode( $post_id ) : 'auto'; ?>
                <form class="ncu-fe-pane" data-ncu-fe-pane="presentation" data-ncu-fe-form="presentation" hidden>
                    <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
                    <input type="hidden" name="modified_gmt" value="<?php echo esc_attr( $post->post_modified_gmt ); ?>" data-ncu-modified>
                    <label><span><?php esc_html_e( 'Output builder', 'nine-code' ); ?></span><select name="render_mode"><option value="auto" <?php selected( $mode, 'auto' ); ?>>Auto Builder</option><option value="ai" <?php selected( $mode, 'ai' ); ?>>AI Builder</option><option value="gutenberg" <?php selected( $mode, 'gutenberg' ); ?>>Gutenberg</option><option value="elementor" <?php selected( $mode, 'elementor' ); ?>>Elementor</option></select></label>
                    <p class="ncu-fe-help"><?php esc_html_e( 'This switches presentation only. The post remains the same canonical record.', 'nine-code' ); ?></p>
                    <div class="ncu-fe-actions"><button type="submit" class="ncu-fe-primary"><?php esc_html_e( 'Save presentation', 'nine-code' ); ?></button><?php if ( defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' ) ) : ?><a class="ncu-fe-secondary" href="<?php echo esc_url( admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) ); ?>"><?php esc_html_e( 'Open Elementor', 'nine-code' ); ?></a><?php endif; ?></div>
                </form>
                <?php endif; ?>

                <?php if ( $can_theme && ! empty( $s['frontend_edit_theme_regions'] ) ) : ?>
                <form class="ncu-fe-pane" data-ncu-fe-pane="header" data-ncu-fe-form="theme" hidden>
                    <input type="hidden" name="theme_section" value="header">
                    <label><span><?php esc_html_e( 'Site name', 'nine-code' ); ?></span><input type="text" name="site_name" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></label>
                    <label><span><?php esc_html_e( 'Tagline', 'nine-code' ); ?></span><input type="text" name="site_tagline" value="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"></label>
                    <div class="ncu-fe-grid"><label><span><?php esc_html_e( 'Header background', 'nine-code' ); ?></span><input type="color" name="header_background_color" value="<?php echo esc_attr( $s['header_background_color'] ); ?>"></label><label><span><?php esc_html_e( 'Header icons', 'nine-code' ); ?></span><input type="color" name="header_icon_color" value="<?php echo esc_attr( $s['header_icon_color'] ); ?>"></label></div>
                    <label class="ncu-fe-check"><input type="checkbox" name="sticky_header" value="1" <?php checked( ! empty( $s['sticky_header'] ) ); ?>> <span><?php esc_html_e( 'Sticky header', 'nine-code' ); ?></span></label>
                    <div class="ncu-fe-actions"><button type="submit" class="ncu-fe-primary"><?php esc_html_e( 'Save header', 'nine-code' ); ?></button><a class="ncu-fe-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-header-footer' ) ); ?>"><?php esc_html_e( 'Full header settings', 'nine-code' ); ?></a></div>
                </form>

                <form class="ncu-fe-pane" data-ncu-fe-pane="footer" data-ncu-fe-form="theme" hidden>
                    <input type="hidden" name="theme_section" value="footer">
                    <label class="ncu-fe-check"><input type="checkbox" name="footer_enabled" value="1" <?php checked( ! empty( $s['footer_enabled'] ) ); ?>> <span><?php esc_html_e( 'Enable built-in 9 Code footer', 'nine-code' ); ?></span></label>
                    <label><span><?php esc_html_e( 'Footer text', 'nine-code' ); ?></span><input type="text" name="footer_text" value="<?php echo esc_attr( $s['footer_text'] ); ?>"></label>
                    <label><span><?php esc_html_e( 'Footer link', 'nine-code' ); ?></span><input type="url" name="footer_url" value="<?php echo esc_attr( $s['footer_url'] ); ?>"></label>
                    <div class="ncu-fe-grid"><label><span><?php esc_html_e( 'Footer background', 'nine-code' ); ?></span><input type="color" name="footer_background_color" value="<?php echo esc_attr( $s['footer_background_color'] ); ?>"></label><label><span><?php esc_html_e( 'Footer text colour', 'nine-code' ); ?></span><input type="color" name="footer_text_color" value="<?php echo esc_attr( $s['footer_text_color'] ); ?>"></label></div>
                    <div class="ncu-fe-actions"><button type="submit" class="ncu-fe-primary"><?php esc_html_e( 'Save footer', 'nine-code' ); ?></button><a class="ncu-fe-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-header-footer' ) ); ?>"><?php esc_html_e( 'Full footer settings', 'nine-code' ); ?></a></div>
                </form>

                <form class="ncu-fe-pane" data-ncu-fe-pane="theme" data-ncu-fe-form="theme" hidden>
                    <input type="hidden" name="theme_section" value="theme">
                    <div class="ncu-fe-grid"><label><span><?php esc_html_e( 'Site accent', 'nine-code' ); ?></span><input type="color" name="accent_color" value="<?php echo esc_attr( $s['accent_color'] ); ?>"></label><label><span><?php esc_html_e( 'Text', 'nine-code' ); ?></span><input type="color" name="text_color" value="<?php echo esc_attr( $s['text_color'] ); ?>"></label></div>
                    <label><span><?php esc_html_e( 'Content maximum width', 'nine-code' ); ?></span><input type="number" min="720" max="1800" name="max_width" value="<?php echo esc_attr( $s['max_width'] ); ?>"></label>
                    <div class="ncu-fe-actions"><button type="submit" class="ncu-fe-primary"><?php esc_html_e( 'Save theme', 'nine-code' ); ?></button><a class="ncu-fe-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-code-ultra-design' ) ); ?>"><?php esc_html_e( 'Full design settings', 'nine-code' ); ?></a></div>
                </form>
                <?php endif; ?>

                <?php do_action( 'ncu_frontend_edit_panels', $post_id ); ?>
                <div class="ncu-fe-status" data-ncu-fe-status role="status" aria-live="polite"></div>
            </div>
        </section>
    </div>
    <?php
}

add_action( 'wp_ajax_ncu_frontend_edit_save_post', 'ncu_frontend_edit_ajax_save_post' );
function ncu_frontend_edit_ajax_save_post() {
    check_ajax_referer( 'ncu_frontend_edit', 'nonce' );
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this content.', 'nine-code' ) ), 403 );
    }
    $post = get_post( $post_id );
    if ( ! $post ) { wp_send_json_error( array( 'message' => __( 'Content not found.', 'nine-code' ) ), 404 ); }

    $client_modified = isset( $_POST['modified_gmt'] ) ? sanitize_text_field( wp_unslash( $_POST['modified_gmt'] ) ) : '';
    if ( $client_modified && $post->post_modified_gmt && $client_modified !== $post->post_modified_gmt ) {
        wp_send_json_error( array( 'message' => __( 'This content was changed elsewhere after the page loaded. Reload before saving.', 'nine-code' ), 'code' => 'stale_edit', 'modified_gmt' => $post->post_modified_gmt ), 409 );
    }

    $update = array( 'ID' => $post_id );
    if ( isset( $_POST['post_title'] ) ) { $update['post_title'] = sanitize_text_field( wp_unslash( $_POST['post_title'] ) ); }
    if ( isset( $_POST['post_excerpt'] ) ) { $update['post_excerpt'] = wp_unslash( $_POST['post_excerpt'] ); }
    if ( isset( $_POST['post_content'] ) ) { $update['post_content'] = wp_unslash( $_POST['post_content'] ); }

    if ( count( $update ) > 1 ) {
        $result = wp_update_post( wp_slash( $update ), true );
        if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 ); }
    }

    if ( isset( $_POST['render_mode'] ) && function_exists( 'ncu_sanitize_builder_meta' ) ) {
        update_post_meta( $post_id, 'ncu_render_mode', ncu_sanitize_builder_meta( wp_unslash( $_POST['render_mode'] ), 'ncu_render_mode' ) );
    }

    if ( isset( $_POST['featured_image_id'] ) ) {
        $image_id = absint( $_POST['featured_image_id'] );
        if ( $image_id ) { set_post_thumbnail( $post_id, $image_id ); } else { delete_post_thumbnail( $post_id ); }
    }

    clean_post_cache( $post_id );
    $fresh = get_post( $post_id );
    do_action( 'ncu_frontend_edit_saved_post', $post_id, $fresh );
    wp_send_json_success( array(
        'message'      => __( 'Saved.', 'nine-code' ),
        'modified_gmt' => $fresh ? $fresh->post_modified_gmt : '',
    ) );
}

add_action( 'wp_ajax_ncu_frontend_edit_save_theme', 'ncu_frontend_edit_ajax_save_theme' );
function ncu_frontend_edit_ajax_save_theme() {
    check_ajax_referer( 'ncu_frontend_edit', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to change theme settings.', 'nine-code' ) ), 403 );
    }
    $section = isset( $_POST['theme_section'] ) ? sanitize_key( wp_unslash( $_POST['theme_section'] ) ) : 'theme';
    $s = ncu_get_settings();
    $raw = $s;

    if ( 'header' === $section ) {
        if ( isset( $_POST['site_name'] ) ) { update_option( 'blogname', sanitize_text_field( wp_unslash( $_POST['site_name'] ) ) ); }
        if ( isset( $_POST['site_tagline'] ) ) { update_option( 'blogdescription', sanitize_text_field( wp_unslash( $_POST['site_tagline'] ) ) ); }
        foreach ( array( 'header_background_color', 'header_icon_color' ) as $key ) {
            if ( isset( $_POST[ $key ] ) ) { $raw[ $key ] = sanitize_hex_color( wp_unslash( $_POST[ $key ] ) ); }
        }
        $raw['sticky_header'] = ! empty( $_POST['sticky_header'] ) ? 1 : 0;
    } elseif ( 'footer' === $section ) {
        $raw['footer_enabled'] = ! empty( $_POST['footer_enabled'] ) ? 1 : 0;
        if ( isset( $_POST['footer_text'] ) ) { $raw['footer_text'] = sanitize_text_field( wp_unslash( $_POST['footer_text'] ) ); }
        if ( isset( $_POST['footer_url'] ) ) { $raw['footer_url'] = esc_url_raw( wp_unslash( $_POST['footer_url'] ) ); }
        foreach ( array( 'footer_background_color', 'footer_text_color' ) as $key ) {
            if ( isset( $_POST[ $key ] ) ) { $raw[ $key ] = sanitize_hex_color( wp_unslash( $_POST[ $key ] ) ); }
        }
    } else {
        foreach ( array( 'accent_color', 'text_color' ) as $key ) {
            if ( isset( $_POST[ $key ] ) ) { $raw[ $key ] = sanitize_hex_color( wp_unslash( $_POST[ $key ] ) ); }
        }
        if ( isset( $_POST['max_width'] ) ) { $raw['max_width'] = max( 720, min( 1800, absint( $_POST['max_width'] ) ) ); }
    }

    update_option( 'ncu_settings', ncu_sanitize_settings( $raw ), false );
    do_action( 'ncu_frontend_edit_saved_theme', $section, ncu_get_settings() );
    wp_send_json_success( array( 'message' => __( 'Theme settings saved.', 'nine-code' ) ) );
}

function ncu_frontend_edit_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $s = ncu_get_settings();
    ?>
    <div class="wrap ncu-admin">
        <?php ncu_admin_header( 'Front-End Edit', 'Authorized users can make routine changes directly on the live page while the canonical WordPress/theme data remains the single source of truth.' ); ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="ncu_save_settings"><input type="hidden" name="ncu_return_page" value="nine-code-ultra-frontend-edit"><?php wp_nonce_field( 'ncu_save_settings' ); ?>
            <section class="ncu-panel ncu-panel--padded"><h2>Front-End Edit Layer</h2><div class="ncu-checks">
                <?php ncu_checkbox( 'frontend_edit_enabled', 'Enable front-end editing for authorized logged-in users', $s ); ?>
                <?php ncu_checkbox( 'frontend_edit_badges', 'Show contextual Edit badges on editable regions', $s ); ?>
                <?php ncu_checkbox( 'frontend_edit_content', 'Allow quick editing of post/page title, excerpt and canonical content', $s ); ?>
                <?php ncu_checkbox( 'frontend_edit_builder', 'Allow per-page builder/output switching', $s ); ?>
                <?php ncu_checkbox( 'frontend_edit_featured', 'Allow featured-image changes from the front end', $s ); ?>
                <?php ncu_checkbox( 'frontend_edit_theme_regions', 'Allow administrators to edit theme-owned header/footer/design controls', $s ); ?>
            </div><p class="description">There is no anonymous save endpoint. Post changes require <code>edit_post</code>; theme changes require <code>manage_options</code>; every save uses a nonce and stale-edit protection.</p></section>
            <section class="ncu-panel ncu-panel--padded"><h2>Ownership contract</h2><p>Plugins keep ownership of their own fields and layouts. They may register front-end editable regions through <code>ncu_frontend_edit_regions</code> and render their own panels through <code>ncu_frontend_edit_panels</code>. 9 Code does not copy plugin data into a second database.</p><p>Unsupported or complex builder fields retain a direct link to their full native editor rather than being rewritten unsafely.</p></section>
            <p class="submit"><button class="button button-primary button-hero">Save Front-End Edit settings</button></p>
        </form>
    </div>
    <?php
}
