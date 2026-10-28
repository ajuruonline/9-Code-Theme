<?php
/** WordPress-safe singular template. Posts/CPTs own their content presentation. */
get_header();
?>
<main id="primary" class="ncu-main ncu-single-native">
<?php while ( have_posts() ) : the_post(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'ncu-single ncu-single-native' ); ?>>
    <?php get_template_part( 'template-parts/entry-header' ); ?>
    <?php if ( has_post_thumbnail() ) : ?>
        <figure class="ncu-featured"><?php the_post_thumbnail( 'large', array( 'alt' => esc_attr( get_the_title() ), 'loading' => 'eager', 'decoding' => 'async' ) ); ?></figure>
    <?php endif; ?>
    <div class="ncu-entry-content">
        <?php the_content(); wp_link_pages(); ?>
    </div>
</article>
<?php if ( ( comments_open() || get_comments_number() ) && ncu_safe_show( 'comments', true ) ) { comments_template(); } ?>
<?php endwhile; ?>
</main>
<?php get_footer();
