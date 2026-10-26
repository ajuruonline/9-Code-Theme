<?php
/** WordPress-safe Page template. */
get_header();
?>
<main id="primary" class="ncu-main ncu-page-native">
<?php while ( have_posts() ) : the_post(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'ncu-page ncu-page-native' ); ?>>
    <?php get_template_part( 'template-parts/entry-header' ); ?>
    <div class="ncu-entry-content">
        <?php the_content(); wp_link_pages(); ?>
    </div>
</article>
<?php if ( ( comments_open() || get_comments_number() ) && ncu_safe_show( 'comments', true ) ) { comments_template(); } ?>
<?php endwhile; ?>
</main>
<?php get_footer();
