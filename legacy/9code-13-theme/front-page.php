<?php
/** WordPress-safe front page. */
get_header();
?>
<main id="primary" class="ncu-main ncu-front-native">
<?php if ( 'posts' === get_option( 'show_on_front' ) ) : ?>
    <section class="ncu-container"><div class="ncu-post-grid">
    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); get_template_part( 'template-parts/content', get_post_type() ); endwhile; else : get_template_part( 'template-parts/content', 'none' ); endif; ?>
    </div><?php the_posts_pagination(); ?></section>
<?php else : ?>
    <?php while ( have_posts() ) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class( 'ncu-page ncu-page-native' ); ?>><div class="ncu-entry-content"><?php the_content(); wp_link_pages(); ?></div></article>
    <?php endwhile; ?>
<?php endif; ?>
</main>
<?php get_footer();
