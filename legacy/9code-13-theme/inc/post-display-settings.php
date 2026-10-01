<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Per-post/page presentation controls.
 *
 * These controls only govern presentation owned by the Theme. They never delete
 * post data and never rewrite plugin output. This lets a specialist plugin own
 * the visible title/meta treatment without forcing the site to change themes.
 */



/**
 * Site-wide Theme presentation defaults.
 *
 * Specialist plugins usually render their own title/meta treatment, so the
 * safe default is to keep the Theme's native title and post meta off. These
 * defaults are presentation-only and never alter stored WordPress content.
 */
function ncu_theme_display_defaults() {
    $defaults = array(
        'show_title' => 0,
        'show_meta'  => 0,
    );
    $saved = get_option( 'ncu_theme_display_defaults', array() );
    if ( ! is_array( $saved ) ) { $saved = array(); }
    return array(
        'show_title' => ! empty( $saved['show_title'] ) ? 1 : 0,
        'show_meta'  => ! empty( $saved['show_meta'] ) ? 1 : 0,
    );
}

function ncu_theme_site_feature_default( $feature, $fallback = true ) {
    $feature = sanitize_key( (string) $feature );
    $defaults = ncu_theme_display_defaults();
    if ( 'title' === $feature ) { return ! empty( $defaults['show_title'] ); }
    if ( 'meta' === $feature ) { return ! empty( $defaults['show_meta'] ); }
    return (bool) $fallback;
}

add_action( 'admin_menu', 'ncu_register_theme_display_defaults_page', 95 );
function ncu_register_theme_display_defaults_page() {
    add_theme_page(
        __( '9Code Theme Display', 'nine-code-ultra' ),
        __( '9Code Display', 'nine-code-ultra' ),
        'edit_theme_options',
        'ninecode-theme-display',
        'ncu_render_theme_display_defaults_page'
    );
}

function ncu_render_theme_display_defaults_page() {
    if ( ! current_user_can( 'edit_theme_options' ) ) { return; }
    $defaults = ncu_theme_display_defaults();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( '9Code Theme Display', 'nine-code-ultra' ); ?></h1>
        <p><?php esc_html_e( 'Site-wide defaults for Theme-owned post and page presentation. Specialist plugins can supply their own title and meta without duplication.', 'nine-code-ultra' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="ncu_save_theme_display_defaults">
            <?php wp_nonce_field( 'ncu_save_theme_display_defaults' ); ?>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><?php esc_html_e( 'Post/Page title', 'nine-code-ultra' ); ?></th><td><label><input type="checkbox" name="ncu_theme_display[show_title]" value="1" <?php checked( ! empty( $defaults['show_title'] ) ); ?>> <?php esc_html_e( 'Show the Theme title by default', 'nine-code-ultra' ); ?></label><p class="description"><?php esc_html_e( 'Default is OFF because 9Code plugins normally provide their own styled title.', 'nine-code-ultra' ); ?></p></td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Post meta', 'nine-code-ultra' ); ?></th><td><label><input type="checkbox" name="ncu_theme_display[show_meta]" value="1" <?php checked( ! empty( $defaults['show_meta'] ) ); ?>> <?php esc_html_e( 'Show Theme date / author meta by default', 'nine-code-ultra' ); ?></label><p class="description"><?php esc_html_e( 'Default is OFF so plugin-owned author/date/meta treatments are not duplicated.', 'nine-code-ultra' ); ?></p></td></tr>
            </tbody></table>
            <p class="submit"><button class="button button-primary"><?php esc_html_e( 'Save display defaults', 'nine-code-ultra' ); ?></button></p>
        </form>
        <hr>
        <p><strong><?php esc_html_e( 'Per-post/page override:', 'nine-code-ultra' ); ?></strong> <?php esc_html_e( 'Use 9Code Display in the editor and choose Show or Hide for an individual item.', 'nine-code-ultra' ); ?></p>
        <?php do_action( 'ncu_theme_display_editor_controls' ); ?>
    </div>
    <?php
}

add_action( 'admin_post_ncu_save_theme_display_defaults', 'ncu_save_theme_display_defaults' );
function ncu_save_theme_display_defaults() {
    if ( ! current_user_can( 'edit_theme_options' ) ) { wp_die( esc_html__( 'You are not allowed to change Theme display defaults.', 'nine-code-ultra' ) ); }
    check_admin_referer( 'ncu_save_theme_display_defaults' );
    $raw = isset( $_POST['ncu_theme_display'] ) && is_array( $_POST['ncu_theme_display'] ) ? wp_unslash( $_POST['ncu_theme_display'] ) : array();
    update_option( 'ncu_theme_display_defaults', array(
        'show_title' => ! empty( $raw['show_title'] ) ? 1 : 0,
        'show_meta'  => ! empty( $raw['show_meta'] ) ? 1 : 0,
    ), false );
    wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-theme-display', 'updated' => '1' ), admin_url( 'themes.php' ) ) );
    exit;
}

function ncu_display_setting_keys() {
    return array(
        '_ncu_display_title',
        '_ncu_display_meta',
        '_ncu_display_featured',
        '_ncu_display_taxonomy',
        '_ncu_display_tags',
        '_ncu_display_navigation',
        '_ncu_display_comments',
        '_ncu_display_breadcrumbs',
        '_ncu_content_width',
        '_ncu_presentation_owner',
    );
}

function ncu_sanitize_display_setting( $value ) {
    $value = sanitize_key( (string) $value );
    return in_array( $value, array( 'inherit', 'show', 'hide' ), true ) ? $value : 'inherit';
}

function ncu_sanitize_content_width_setting( $value ) {
    $value = sanitize_key( (string) $value );
    return in_array( $value, array( 'default', 'reading', 'wide', 'full' ), true ) ? $value : 'default';
}

function ncu_sanitize_presentation_owner_setting( $value ) {
    $value = sanitize_key( (string) $value );
    return in_array( $value, array( 'auto', 'theme', 'plugin' ), true ) ? $value : 'auto';
}

function ncu_display_meta_auth( $allowed, $meta_key, $post_id ) {
    return current_user_can( 'edit_post', (int) $post_id );
}

add_action( 'init', 'ncu_register_display_settings_meta' );
function ncu_register_display_settings_meta() {
    $types = array( 'post', 'page' );
    $features = array(
        '_ncu_display_title', '_ncu_display_meta', '_ncu_display_featured', '_ncu_display_taxonomy',
        '_ncu_display_tags', '_ncu_display_navigation', '_ncu_display_comments', '_ncu_display_breadcrumbs',
    );
    foreach ( $types as $type ) {
        foreach ( $features as $key ) {
            register_post_meta( $type, $key, array(
                'single'            => true,
                'type'              => 'string',
                'default'           => 'inherit',
                'show_in_rest'      => true,
                'sanitize_callback' => 'ncu_sanitize_display_setting',
                'auth_callback'     => 'ncu_display_meta_auth',
            ) );
        }
        register_post_meta( $type, '_ncu_content_width', array(
            'single'            => true,
            'type'              => 'string',
            'default'           => 'default',
            'show_in_rest'      => true,
            'sanitize_callback' => 'ncu_sanitize_content_width_setting',
            'auth_callback'     => 'ncu_display_meta_auth',
        ) );
        register_post_meta( $type, '_ncu_presentation_owner', array(
            'single'            => true,
            'type'              => 'string',
            'default'           => 'auto',
            'show_in_rest'      => true,
            'sanitize_callback' => 'ncu_sanitize_presentation_owner_setting',
            'auth_callback'     => 'ncu_display_meta_auth',
        ) );
    }
}

function ncu_post_display_setting( $post_id, $key, $fallback = 'inherit' ) {
    $post_id = absint( $post_id );
    if ( ! $post_id || ! in_array( $key, ncu_display_setting_keys(), true ) ) { return $fallback; }
    $raw = get_post_meta( $post_id, $key, true );
    if ( '' === $raw || null === $raw ) { return $fallback; }
    if ( '_ncu_content_width' === $key ) { return ncu_sanitize_content_width_setting( $raw ); }
    if ( '_ncu_presentation_owner' === $key ) { return ncu_sanitize_presentation_owner_setting( $raw ); }
    return ncu_sanitize_display_setting( $raw );
}

function ncu_post_presentation_owner( $post_id ) {
    return ncu_post_display_setting( $post_id, '_ncu_presentation_owner', 'auto' );
}

/**
 * Decide whether the Theme should render a presentation feature.
 * $default is the Theme's existing behavior before per-item overrides.
 */
function ncu_should_show_post_feature( $post_id, $feature, $default = true ) {
    $post_id = absint( $post_id );
    $feature = sanitize_key( (string) $feature );
    if ( ! $post_id ) { return (bool) $default; }

    $key = '_ncu_display_' . $feature;
    if ( ! in_array( $key, ncu_display_setting_keys(), true ) ) { return (bool) $default; }

    $setting = ncu_post_display_setting( $post_id, $key, 'inherit' );
    if ( 'show' === $setting ) { return true; }
    if ( 'hide' === $setting ) { return false; }

    // Plugin/custom presentation means the content can remain in the Theme shell,
    // but Theme-owned ornamental wrappers must stay out of the plugin's way.
    if ( 'plugin' === ncu_post_presentation_owner( $post_id ) && in_array( $feature, array( 'title', 'meta', 'featured', 'taxonomy', 'tags', 'navigation', 'breadcrumbs' ), true ) ) {
        return false;
    }

    // Title and post meta follow Theme-owned site defaults when the item itself
    // inherits. Both are OFF on a fresh install to avoid duplicating plugin UI.
    $default = ncu_theme_site_feature_default( $feature, $default );

    try {
        return (bool) apply_filters( 'ncu_should_show_post_feature', $default, $post_id, $feature, $setting );
    } catch ( \Throwable $e ) {
        error_log( '[9Code Theme ' . NCU_THEME_VERSION . '] 9Code Theme feature filter fallback (' . $feature . '): ' . $e->getMessage() );
        return (bool) $default;
    }
}

function ncu_post_content_width( $post_id ) {
    return ncu_post_display_setting( $post_id, '_ncu_content_width', 'default' );
}

function ncu_post_content_width_class( $post_id ) {
    $width = ncu_post_content_width( $post_id );
    return 'default' === $width ? '' : ' ncu-content-width-' . sanitize_html_class( $width );
}

/** Gutenberg document-settings sidebar. */
add_action( 'enqueue_block_editor_assets', 'ncu_enqueue_display_settings_editor' );
function ncu_enqueue_display_settings_editor() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) { return; }
    wp_enqueue_script(
        'ncu-editor-display-settings',
        NCU_THEME_URI . '/assets/js/editor-display-settings.js',
        array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
        NCU_THEME_VERSION,
        true
    );
}

/** Classic editor / meta-box fallback. */
add_action( 'add_meta_boxes', 'ncu_add_display_settings_metabox' );
function ncu_add_display_settings_metabox() {
    foreach ( array( 'post', 'page' ) as $type ) {
        // Gutenberg gets the native document-settings sidebar panel above. Keep
        // this meta box only as a Classic Editor / non-block-editor fallback.
        if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $type ) ) { continue; }
        add_meta_box( 'ncu-display-settings', __( '9Code Display', 'nine-code-ultra' ), 'ncu_render_display_settings_metabox', $type, 'side', 'default' );
    }
}

function ncu_display_select( $post_id, $key, $label ) {
    $value = ncu_post_display_setting( $post_id, $key, 'inherit' );
    echo '<label class="ncu-display-field"><span>' . esc_html( $label ) . '</span><select name="ncu_display[' . esc_attr( $key ) . ']">';
    foreach ( array( 'inherit'=>'Site default', 'show'=>'Show', 'hide'=>'Hide' ) as $v => $name ) {
        echo '<option value="' . esc_attr( $v ) . '"' . selected( $value, $v, false ) . '>' . esc_html( $name ) . '</option>';
    }
    echo '</select></label>';
}

function ncu_render_display_settings_metabox( $post ) {
    if ( ! $post instanceof WP_Post ) { return; }
    wp_nonce_field( 'ncu_display_settings_save', 'ncu_display_settings_nonce' );
    $owner = ncu_post_presentation_owner( $post->ID );
    $width = ncu_post_content_width( $post->ID );
    echo '<p><label><strong>Presentation owner</strong><select name="ncu_display[_ncu_presentation_owner]" style="width:100%;margin-top:5px">';
    foreach ( array( 'auto'=>'Automatic', 'theme'=>'9Code Theme', 'plugin'=>'Plugin / custom presentation' ) as $v => $name ) {
        echo '<option value="' . esc_attr( $v ) . '"' . selected( $owner, $v, false ) . '>' . esc_html( $name ) . '</option>';
    }
    echo '</select></label></p><p class="description">Plugin / custom suppresses Theme title, meta, category line, featured image, breadcrumbs, tags and navigation unless explicitly overridden below.</p>';
    ncu_display_select( $post->ID, '_ncu_display_title', 'Title' );
    if ( 'post' === $post->post_type ) {
        ncu_display_select( $post->ID, '_ncu_display_meta', 'Date / author meta' );
        ncu_display_select( $post->ID, '_ncu_display_taxonomy', 'Category line' );
        ncu_display_select( $post->ID, '_ncu_display_tags', 'Tags' );
        ncu_display_select( $post->ID, '_ncu_display_navigation', 'Previous / next' );
    }
    ncu_display_select( $post->ID, '_ncu_display_featured', 'Featured image' );
    ncu_display_select( $post->ID, '_ncu_display_breadcrumbs', 'Breadcrumbs' );
    ncu_display_select( $post->ID, '_ncu_display_comments', 'Comments' );
    echo '<p><label><strong>Content width</strong><select name="ncu_display[_ncu_content_width]" style="width:100%;margin-top:5px">';
    foreach ( array( 'default'=>'Theme default', 'reading'=>'Reading width', 'wide'=>'Wide', 'full'=>'Full width' ) as $v => $name ) {
        echo '<option value="' . esc_attr( $v ) . '"' . selected( $width, $v, false ) . '>' . esc_html( $name ) . '</option>';
    }
    echo '</select></label></p><style>#ncu-display-settings .ncu-display-field{display:grid;grid-template-columns:1fr 105px;gap:8px;align-items:center;margin:8px 0}#ncu-display-settings .ncu-display-field select{width:100%;min-width:0}</style>';
}

add_action( 'save_post_post', 'ncu_save_display_settings_metabox' );
add_action( 'save_post_page', 'ncu_save_display_settings_metabox' );
function ncu_save_display_settings_metabox( $post_id ) {
    if ( ! isset( $_POST['ncu_display_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ncu_display_settings_nonce'] ) ), 'ncu_display_settings_save' ) ) { return; }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
    $posted = isset( $_POST['ncu_display'] ) && is_array( $_POST['ncu_display'] ) ? wp_unslash( $_POST['ncu_display'] ) : array();
    foreach ( ncu_display_setting_keys() as $key ) {
        if ( ! array_key_exists( $key, $posted ) ) { continue; }
        $value = $posted[ $key ];
        if ( '_ncu_content_width' === $key ) { $value = ncu_sanitize_content_width_setting( $value ); }
        elseif ( '_ncu_presentation_owner' === $key ) { $value = ncu_sanitize_presentation_owner_setting( $value ); }
        else { $value = ncu_sanitize_display_setting( $value ); }
        update_post_meta( $post_id, $key, $value );
    }
}
