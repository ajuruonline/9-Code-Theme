<?php
/** Lightweight SEO/social fallback. Dedicated SEO plugins always take priority. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function osch_seo_defaults() {
    return array(
        'enabled' => 1,
        'site_description' => '',
        'organisation_name' => '',
        'organisation_logo_id' => 0,
        'default_social_image_id' => 0,
        'twitter_site' => '',
    );
}

function osch_seo_settings() {
    $saved = get_option( 'osch_seo_settings', array() );
    return wp_parse_args( is_array( $saved ) ? $saved : array(), osch_seo_defaults() );
}

function osch_seo_is_available() { return true; }

function osch_seo_has_provider() {
    return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' )
        || defined( 'SEOPRESS_VERSION' ) || class_exists( 'The_SEO_Framework\Load', false )
        || (bool) apply_filters( 'open_scholar_theme_has_seo_provider', false );
}

add_action( 'admin_menu', 'osch_seo_admin_menu', 20 );
function osch_seo_admin_menu() {
    add_submenu_page( 'nine-code-ultra', __( 'SEO & Sharing', 'nine-code' ), __( 'SEO & Sharing', 'nine-code' ), 'manage_options', 'open-scholar-seo', 'osch_seo_settings_page' );
}

add_action( 'admin_post_osch_save_seo', 'osch_seo_save_settings' );
function osch_seo_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code' ) ); }
    check_admin_referer( 'osch_save_seo' );
    $raw = isset( $_POST['osch_seo'] ) && is_array( $_POST['osch_seo'] ) ? wp_unslash( $_POST['osch_seo'] ) : array();
    $settings = array(
        'enabled' => empty( $raw['enabled'] ) ? 0 : 1,
        'site_description' => sanitize_textarea_field( $raw['site_description'] ?? '' ),
        'organisation_name' => sanitize_text_field( $raw['organisation_name'] ?? '' ),
        'organisation_logo_id' => absint( $raw['organisation_logo_id'] ?? 0 ),
        'default_social_image_id' => absint( $raw['default_social_image_id'] ?? 0 ),
        'twitter_site' => ltrim( sanitize_text_field( $raw['twitter_site'] ?? '' ), '@' ),
    );
    update_option( 'osch_seo_settings', $settings, false );
    wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=open-scholar-seo' ) ) );
    exit;
}

function osch_seo_media_field( $label, $key, $value, $description ) {
    $image = $value ? wp_get_attachment_image_url( $value, 'thumbnail' ) : '';
    ?>
    <label data-ninecode-settings-field><span><?php echo esc_html( $label ); ?></span>
        <input type="number" min="0" name="osch_seo[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
        <?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" width="64" height="64"><?php endif; ?>
        <small><?php echo esc_html( $description ); ?> Use the attachment ID from Media.</small>
    </label>
    <?php
}

function osch_seo_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $s = osch_seo_settings();
    ?>
    <div class="wrap ncu-admin" data-ninecode-settings-root>
        <?php ncu_admin_header( 'SEO & Sharing', 'Best-practice search and social metadata for sites without a dedicated SEO plugin. Open Scholar automatically yields when one is detected.' ); ?>
        <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>SEO settings saved.</p></div><?php endif; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="osch_save_seo"><?php wp_nonce_field( 'osch_save_seo' ); ?>
            <details class="ncu-panel ncu-panel--padded" data-ninecode-settings-section>
                <summary>Search metadata</summary><div class="ninecode-settings-body ncu-grid">
                    <label data-ninecode-settings-field><input type="hidden" name="osch_seo[enabled]" value="0"><input type="checkbox" name="osch_seo[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <span>Enable the Open Scholar SEO fallback</span><small>Automatically stops output when Yoast, Rank Math, All in One SEO, SEOPress or another declared provider is active.</small></label>
                    <label data-ninecode-settings-field><span>Default site description</span><textarea name="osch_seo[site_description]" rows="3" maxlength="320"><?php echo esc_textarea( $s['site_description'] ); ?></textarea><small>Used on the home page when no more specific description exists. Aim for roughly 120–160 characters.</small></label>
                </div>
            </details>
            <details class="ncu-panel ncu-panel--padded" data-ninecode-settings-section>
                <summary>Organisation identity</summary><div class="ninecode-settings-body ncu-grid">
                    <label data-ninecode-settings-field><span>Organisation name</span><input type="text" name="osch_seo[organisation_name]" value="<?php echo esc_attr( $s['organisation_name'] ); ?>"><small>Used in structured data. Blank uses the site title.</small></label>
                    <?php osch_seo_media_field( 'Organisation logo', 'organisation_logo_id', $s['organisation_logo_id'], 'A square or wide institutional logo for structured data.' ); ?>
                </div>
            </details>
            <details class="ncu-panel ncu-panel--padded" data-ninecode-settings-section>
                <summary>Social sharing</summary><div class="ninecode-settings-body ncu-grid">
                    <?php osch_seo_media_field( 'Default sharing image', 'default_social_image_id', $s['default_social_image_id'], 'Used when a post has no dedicated social or featured image.' ); ?>
                    <label data-ninecode-settings-field><span>X/Twitter account</span><input type="text" name="osch_seo[twitter_site]" value="<?php echo esc_attr( $s['twitter_site'] ); ?>" placeholder="institution"><small>Enter the account name without @. This adds the twitter:site attribution.</small></label>
                </div>
            </details>
            <p class="submit"><button class="button button-primary button-hero">Save SEO settings</button></p>
        </form>
    </div>
    <?php
}

add_action( 'add_meta_boxes', 'osch_seo_add_meta_boxes' );
function osch_seo_add_meta_boxes() {
    if ( osch_seo_has_provider() ) { return; }
    $types = apply_filters( 'open_scholar_seo_post_types', array( 'post', 'page' ) );
    foreach ( array_filter( array_map( 'sanitize_key', (array) $types ) ) as $type ) {
        if ( post_type_exists( $type ) ) { add_meta_box( 'osch-seo-sharing', __( 'Open Scholar SEO & Sharing', 'nine-code' ), 'osch_seo_meta_box', $type, 'normal', 'default' ); }
    }
}

add_filter( 'postbox_classes_post_osch-seo-sharing', 'osch_seo_close_meta_box' );
add_filter( 'postbox_classes_page_osch-seo-sharing', 'osch_seo_close_meta_box' );
function osch_seo_close_meta_box( $classes ) { $classes[] = 'closed'; return array_unique( $classes ); }

function osch_seo_meta_box( $post ) {
    wp_nonce_field( 'osch_save_post_seo', 'osch_seo_nonce' );
    $description = get_post_meta( $post->ID, '_osch_seo_description', true );
    $social_title = get_post_meta( $post->ID, '_osch_social_title', true );
    $social_description = get_post_meta( $post->ID, '_osch_social_description', true );
    $social_image = absint( get_post_meta( $post->ID, '_osch_social_image_id', true ) );
    $noindex = (bool) get_post_meta( $post->ID, '_osch_noindex', true );
    ?>
    <div class="osch-seo-fields">
        <p class="description">Optional overrides for this item. Blank fields use the title, excerpt/content and featured image. A dedicated SEO plugin takes over automatically.</p>
        <label><strong>Search description</strong><textarea name="osch_seo_description" rows="3" maxlength="320"><?php echo esc_textarea( $description ); ?></textarea><small>Write a clear summary of approximately 120–160 characters.</small></label>
        <label><strong>Social title</strong><input type="text" name="osch_social_title" value="<?php echo esc_attr( $social_title ); ?>" maxlength="120"><small>Optional title for link previews.</small></label>
        <label><strong>Social description</strong><textarea name="osch_social_description" rows="3" maxlength="320"><?php echo esc_textarea( $social_description ); ?></textarea><small>Optional description for Open Graph and social cards.</small></label>
        <label><strong>Social image attachment ID</strong><input type="number" min="0" name="osch_social_image_id" value="<?php echo esc_attr( $social_image ); ?>"><small>Blank uses the featured image, then the site default.</small></label>
        <label><input type="checkbox" name="osch_noindex" value="1" <?php checked( $noindex ); ?>> <strong>Ask search engines not to index this item</strong><small>Use for private-quality or temporary public content; this is a request, not access control.</small></label>
    </div>
    <?php
}

add_action( 'save_post', 'osch_seo_save_post' );
function osch_seo_save_post( $post_id ) {
    if ( ! isset( $_POST['osch_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['osch_seo_nonce'] ) ), 'osch_save_post_seo' ) ) { return; }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
    $text = array(
        '_osch_seo_description' => 'osch_seo_description',
        '_osch_social_title' => 'osch_social_title',
        '_osch_social_description' => 'osch_social_description',
    );
    foreach ( $text as $meta => $field ) {
        $value = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';
        if ( '' === $value ) delete_post_meta( $post_id, $meta ); else update_post_meta( $post_id, $meta, $value );
    }
    $image = isset( $_POST['osch_social_image_id'] ) ? absint( $_POST['osch_social_image_id'] ) : 0;
    if ( $image ) update_post_meta( $post_id, '_osch_social_image_id', $image ); else delete_post_meta( $post_id, '_osch_social_image_id' );
    if ( ! empty( $_POST['osch_noindex'] ) ) update_post_meta( $post_id, '_osch_noindex', 1 ); else delete_post_meta( $post_id, '_osch_noindex' );
}

function osch_seo_description( $post_id = 0 ) {
    $s = osch_seo_settings();
    if ( $post_id ) {
        $custom = trim( (string) get_post_meta( $post_id, '_osch_seo_description', true ) );
        if ( $custom ) return $custom;
        $post = get_post( $post_id );
        if ( $post ) {
            $source = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
            $source = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $source ) ) ) );
            if ( $source ) return wp_html_excerpt( $source, 160, '…' );
        }
    }
    return trim( (string) $s['site_description'] ) ?: get_bloginfo( 'description' );
}

add_filter( 'wp_robots', 'osch_seo_robots' );
function osch_seo_robots( $robots ) {
    if ( ! osch_seo_has_provider() && is_singular() && get_post_meta( get_queried_object_id(), '_osch_noindex', true ) ) {
        $robots['noindex'] = true; $robots['follow'] = true;
    }
    return $robots;
}

add_action( 'wp_head', 'osch_seo_head', 3 );
function osch_seo_head() {
    $s = osch_seo_settings();
    if ( empty( $s['enabled'] ) || osch_seo_has_provider() || is_admin() || is_feed() ) { return; }
    $post_id = is_singular() ? get_queried_object_id() : 0;
    $title = $post_id ? trim( (string) get_post_meta( $post_id, '_osch_social_title', true ) ) : '';
    if ( ! $title ) $title = wp_get_document_title();
    $description = $post_id ? trim( (string) get_post_meta( $post_id, '_osch_social_description', true ) ) : '';
    if ( ! $description ) $description = osch_seo_description( $post_id );
    $url = $post_id ? get_permalink( $post_id ) : ( is_front_page() ? home_url( '/' ) : get_pagenum_link() );
    $image_id = $post_id ? absint( get_post_meta( $post_id, '_osch_social_image_id', true ) ) : 0;
    if ( ! $image_id && $post_id ) $image_id = get_post_thumbnail_id( $post_id );
    if ( ! $image_id ) $image_id = absint( $s['default_social_image_id'] );
    $image = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
    echo "\n<!-- Open Scholar SEO fallback -->\n";
    if ( $description ) echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
    if ( $description ) echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
    if ( $image ) echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
    echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
    if ( $description ) echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
    if ( $image ) echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
    if ( $s['twitter_site'] ) echo '<meta name="twitter:site" content="@' . esc_attr( $s['twitter_site'] ) . '">' . "\n";
    osch_seo_schema( $post_id, $title, $description, $url, $image );
}

function osch_seo_schema( $post_id, $title, $description, $url, $image ) {
    $s = osch_seo_settings();
    $org_name = $s['organisation_name'] ?: get_bloginfo( 'name' );
    $logo = $s['organisation_logo_id'] ? wp_get_attachment_image_url( $s['organisation_logo_id'], 'full' ) : '';
    $org = array( '@type'=>'Organization', '@id'=>home_url( '/#organization' ), 'name'=>$org_name, 'url'=>home_url( '/' ) );
    if ( $logo ) $org['logo'] = array( '@type'=>'ImageObject', 'url'=>$logo );
    $graph = array( $org, array( '@type'=>'WebSite', '@id'=>home_url( '/#website' ), 'url'=>home_url( '/' ), 'name'=>get_bloginfo( 'name' ), 'publisher'=>array( '@id'=>home_url( '/#organization' ) ) ) );
    if ( $post_id ) {
        $item = array( '@type'=>is_singular( 'post' ) ? 'Article' : 'WebPage', '@id'=>$url . '#primary', 'url'=>$url, 'headline'=>$title, 'isPartOf'=>array( '@id'=>home_url( '/#website' ) ) );
        if ( $description ) $item['description'] = $description;
        if ( $image ) $item['image'] = $image;
        $published = get_post_time( DATE_W3C, true, $post_id ); $modified = get_post_modified_time( DATE_W3C, true, $post_id );
        if ( $published ) $item['datePublished'] = $published;
        if ( $modified ) $item['dateModified'] = $modified;
        $graph[] = $item;
    }
    echo '<script type="application/ld+json">' . wp_json_encode( array( '@context'=>'https://schema.org', '@graph'=>$graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
