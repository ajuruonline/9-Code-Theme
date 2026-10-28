<?php
/**
 * A green filter bar at the top of the posts list: category, tag and author.
 * It sends the same request parameters WordPress already understands (cat, tag, author),
 * so results and the existing list table do not change.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_notices', 'ncu_osch_post_filter_bar' );
function ncu_osch_post_filter_bar() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || 'edit' !== $screen->base || 'post' !== $screen->post_type ) { return; }

    $cat    = isset( $_GET['cat'] ) ? absint( $_GET['cat'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter display only.
    $tag    = isset( $_GET['tag'] ) ? sanitize_title( wp_unslash( $_GET['tag'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $author = isset( $_GET['author'] ) ? absint( $_GET['author'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $categories = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
    $tags       = get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false ) );
    $authors    = get_users( array( 'capability' => array( 'edit_posts' ), 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) );
    $format     = isset( $_GET['post_format'] ) ? sanitize_key( wp_unslash( $_GET['post_format'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $formats    = get_post_format_strings();
    $active     = ( $cat || '' !== $tag || $author || '' !== $format );
    ?>
    <details class="osch-list-bar"<?php echo $active ? ' open' : ''; ?>>
    <summary class="osch-list-bar-summary"><span>Filter posts</span><small><?php echo $active ? 'Filters on' : 'All posts'; ?></small></summary>
    <form class="osch-list-bar-form" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
        <input type="hidden" name="post_type" value="post">
        <label>Format
            <select name="post_format"><option value="">All formats</option>
                <?php foreach ( $formats as $key => $name ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $format, $key ); ?>><?php echo esc_html( $name ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Category
            <select name="cat"><option value="">All categories</option>
                <?php foreach ( is_wp_error( $categories ) ? array() : $categories as $term ) : ?>
                    <option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $cat, (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tag
            <select name="tag"><option value="">All tags</option>
                <?php foreach ( is_wp_error( $tags ) ? array() : $tags as $term ) : ?>
                    <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $tag, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Author
            <select name="author"><option value="">All authors</option>
                <?php foreach ( $authors as $user ) : ?>
                    <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $author, (int) $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="osch-list-bar-actions">
            <button type="submit">Apply filters</button>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=post' ) ); ?>">Clear</a>
        </div>
    </form>
    </details>
    <?php
}
