<?php
/**
 * Template Name: Nine Code Service
 * Template Post Type: page
 * WordPress-safe template: no 9Code builder/render helper dependency.
 */
get_header();
?>
<main id="primary" class="ncu-main ncu-service-template">
<?php while ( have_posts() ) : the_post(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'ncu-page ncu-page-native' ); ?>>
    <?php get_template_part( 'template-parts/entry-header' ); ?><div class="ncu-entry-content"><?php the_content(); wp_link_pages(); ?></div>
</article>
<?php endwhile; ?>
</main>
<?php get_footer();
