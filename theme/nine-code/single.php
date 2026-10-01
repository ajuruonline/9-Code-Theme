<?php
/** WordPress-safe singular template. Posts/CPTs own their content presentation. */
get_header();
?>
<main id="primary" class="ncu-main ncu-single-native">
<?php while ( have_posts() ) : the_post(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'ncu-single ncu-single-native' ); ?>>
    <?php get_template_part( 'template-parts/entry-header' ); ?>
    <div class="ncu-entry-content">
        <?php the_content(); wp_link_pages(); ?>
    </div>
</article>
<?php endwhile; ?>
</main>
<?php get_footer();
