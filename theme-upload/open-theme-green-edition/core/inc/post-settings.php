<?php
/**
 * Post settings panel: every setting of a post (title, address, status, date, author,
 * parent, order, comments, password, format, categories, tags, sticky) in one panel,
 * opened from the posts list. Content and custom fields are not part of it.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_ajax_osch_post_settings_form', 'ncu_osch_post_settings_form' );
add_action( 'wp_ajax_osch_post_settings_save', 'ncu_osch_post_settings_save' );
add_action( 'admin_enqueue_scripts', 'ncu_osch_post_settings_assets', 1200 );

function ncu_osch_post_settings_assets( $hook ) {
    if ( 'edit.php' !== $hook ) { return; }
    wp_enqueue_style( 'osch-post-settings', NCU_THEME_URI . '/core/assets/css/post-settings.css', array(), NCU_THEME_VERSION );
    wp_enqueue_script( 'osch-post-settings', NCU_THEME_URI . '/core/assets/js/post-settings.js', array(), NCU_THEME_VERSION, true );
    wp_localize_script( 'osch-post-settings', 'oschPostSettings', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'osch_post_settings' ),
        'saved'   => __( 'Saved.', 'nine-code' ),
        'failed'  => __( 'Nothing was saved. Check the message above and try again.', 'nine-code' ),
    ) );
}

/** The post a request is about, if the current user may edit it. */
function ncu_osch_post_settings_target() {
    check_ajax_referer( 'osch_post_settings', 'nonce' );
    $id   = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked above.
    $post = $id ? get_post( $id ) : null;
    if ( ! $post || ! current_user_can( 'edit_post', $id ) || 'revision' === $post->post_type ) {
        wp_send_json_error( array( 'message' => __( 'You cannot change this post.', 'nine-code' ) ), 403 );
    }
    return $post;
}

function ncu_osch_post_settings_form() {
    $post   = ncu_osch_post_settings_target();
    $type   = get_post_type_object( $post->post_type );
    $is_hier = is_post_type_hierarchical( $post->post_type );
    $is_post = 'post' === $post->post_type;
    $local  = function ( $gmt ) { return mysql2date( 'Y-m-d\TH:i', $gmt ); };
    $date_value = '0000-00-00 00:00:00' === $post->post_date_gmt ? '' : $local( $post->post_date );

    ob_start();
    ?>
    <input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
    <div class="osch-ps-group">
        <label class="osch-ps-field"><span>Title</span>
            <input type="text" name="post_title" value="<?php echo esc_attr( $post->post_title ); ?>" maxlength="255"></label>
        <label class="osch-ps-field"><span>Web address (slug)</span>
            <input type="text" name="post_name" value="<?php echo esc_attr( $post->post_name ); ?>" autocapitalize="off" spellcheck="false">
            <small>Used in the link to this <?php echo esc_html( strtolower( $type ? $type->labels->singular_name : 'item' ) ); ?>.</small></label>
    </div>
    <div class="osch-ps-group">
        <label class="osch-ps-field"><span>Status</span>
            <select name="post_status">
                <?php foreach ( array( 'publish' => 'Published', 'draft' => 'Draft', 'pending' => 'Pending review', 'private' => 'Private' ) as $value => $label ) :
                    if ( 'publish' === $value && ! current_user_can( $type->cap->publish_posts ) ) { continue; } ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $post->post_status, $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
                <?php if ( 'future' === $post->post_status ) : ?><option value="future" selected>Scheduled</option><?php endif; ?>
            </select></label>
        <label class="osch-ps-field"><span>Date</span>
            <input type="datetime-local" name="post_date" value="<?php echo esc_attr( $date_value ); ?>"></label>
        <?php if ( current_user_can( 'edit_others_posts' ) ) : ?>
        <label class="osch-ps-field"><span>Author</span>
            <select name="post_author">
                <?php foreach ( get_users( array( 'capability' => array( $type->cap->edit_posts ), 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) ) as $user ) : ?>
                    <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( (int) $post->post_author, (int) $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
                <?php endforeach; ?>
            </select></label>
        <?php endif; ?>
    </div>
    <?php if ( $is_hier ) :
        $parents = get_pages( array( 'post_type' => $post->post_type, 'exclude_tree' => $post->ID, 'sort_column' => 'menu_order,post_title', 'post_status' => array( 'publish', 'draft', 'private', 'pending' ) ) ); ?>
    <div class="osch-ps-group">
        <label class="osch-ps-field"><span>Parent</span>
            <select name="post_parent">
                <option value="0">No parent</option>
                <?php foreach ( $parents as $parent ) : ?>
                    <option value="<?php echo esc_attr( $parent->ID ); ?>" <?php selected( (int) $post->post_parent, (int) $parent->ID ); ?>><?php echo esc_html( $parent->post_title ); ?></option>
                <?php endforeach; ?>
            </select></label>
        <label class="osch-ps-field"><span>Order</span>
            <input type="number" name="menu_order" value="<?php echo esc_attr( (int) $post->menu_order ); ?>" step="1"></label>
    </div>
    <?php endif; ?>
    <div class="osch-ps-group">
        <label class="osch-ps-field osch-ps-check"><input type="checkbox" name="comment_status" value="open" <?php checked( 'open', $post->comment_status ); ?>> <span>Allow comments</span></label>
        <label class="osch-ps-field osch-ps-check"><input type="checkbox" name="ping_status" value="open" <?php checked( 'open', $post->ping_status ); ?>> <span>Allow link notifications</span></label>
        <label class="osch-ps-field"><span>Password (blank = public)</span>
            <input type="text" name="post_password" value="<?php echo esc_attr( $post->post_password ); ?>" autocomplete="off"></label>
    </div>
    <?php if ( $is_post ) : ?>
    <div class="osch-ps-group">
        <label class="osch-ps-field"><span>Format</span>
            <select name="post_format">
                <option value="standard" <?php selected( get_post_format( $post ) ?: 'standard', 'standard' ); ?>>Standard</option>
                <?php foreach ( get_post_format_strings() as $key => $label ) : if ( 'standard' === $key ) { continue; } ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( get_post_format( $post ), $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select></label>
        <label class="osch-ps-field osch-ps-check"><input type="checkbox" name="sticky" value="1" <?php checked( is_sticky( $post->ID ) ); ?>> <span>Keep at the top of the blog</span></label>
    </div>
    <fieldset class="osch-ps-group osch-ps-terms">
        <legend>Categories</legend>
        <?php foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $cat ) : ?>
            <label class="osch-ps-check"><input type="checkbox" name="categories[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( has_term( (int) $cat->term_id, 'category', $post->ID ) ); ?>> <span><?php echo esc_html( $cat->name ); ?></span></label>
        <?php endforeach; ?>
    </fieldset>
    <div class="osch-ps-group">
        <label class="osch-ps-field"><span>Tags</span>
            <input type="text" name="tags" value="<?php echo esc_attr( implode( ', ', wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) ) ) ); ?>">
            <small>Separate with commas.</small></label>
    </div>
    <?php endif; ?>
    <?php
    $html = ob_get_clean();
    wp_send_json_success( array( 'html' => $html, 'title' => get_the_title( $post ) ) );
}

function ncu_osch_post_settings_save() {
    $post = ncu_osch_post_settings_target();
    $id   = $post->ID;
    $in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in the target function.
    $type = get_post_type_object( $post->post_type );

    $args = array( 'ID' => $id );
    if ( isset( $in['post_title'] ) ) { $args['post_title'] = sanitize_text_field( $in['post_title'] ); }
    if ( isset( $in['post_name'] ) ) { $args['post_name'] = sanitize_title( $in['post_name'] ); }
    if ( isset( $in['post_status'] ) ) {
        $status = sanitize_key( $in['post_status'] );
        if ( 'publish' === $status && ! current_user_can( $type->cap->publish_posts ) ) { $status = 'pending'; }
        if ( in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) { $args['post_status'] = $status; }
    }
    if ( ! empty( $in['post_date'] ) ) {
        $date = str_replace( 'T', ' ', sanitize_text_field( $in['post_date'] ) ) . ':00';
        $args['post_date'] = $date;
        $args['post_date_gmt'] = get_gmt_from_date( $date );
        $args['edit_date'] = true;
    }
    if ( isset( $in['post_author'] ) && current_user_can( 'edit_others_posts' ) ) { $args['post_author'] = absint( $in['post_author'] ); }
    if ( isset( $in['post_parent'] ) && is_post_type_hierarchical( $post->post_type ) ) { $args['post_parent'] = absint( $in['post_parent'] ); }
    if ( isset( $in['menu_order'] ) && is_post_type_hierarchical( $post->post_type ) ) { $args['menu_order'] = (int) $in['menu_order']; }
    $args['comment_status'] = ! empty( $in['comment_status'] ) ? 'open' : 'closed';
    $args['ping_status']    = ! empty( $in['ping_status'] ) ? 'open' : 'closed';
    if ( isset( $in['post_password'] ) ) { $args['post_password'] = sanitize_text_field( $in['post_password'] ); }

    $result = wp_update_post( $args, true );
    if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 ); }

    if ( 'post' === $post->post_type ) {
        set_post_format( $id, ( isset( $in['post_format'] ) && 'standard' !== $in['post_format'] ) ? sanitize_key( $in['post_format'] ) : '' );
        if ( ! empty( $in['sticky'] ) ) { stick_post( $id ); } else { unstick_post( $id ); }
        $cats = isset( $in['categories'] ) ? array_map( 'absint', (array) $in['categories'] ) : array();
        wp_set_post_categories( $id, $cats );
        $tags = isset( $in['tags'] ) ? array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $in['tags'] ) ) ) ) : array();
        wp_set_post_tags( $id, $tags, false );
    }

    wp_send_json_success( array( 'message' => __( 'Saved.', 'nine-code' ), 'title' => get_the_title( $id ), 'status' => get_post_status( $id ) ) );
}
