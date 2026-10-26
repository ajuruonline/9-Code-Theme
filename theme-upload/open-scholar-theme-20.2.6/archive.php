<?php get_header(); ?>
<main id="primary" class="ncu-main ncu-container">
<header class="ncu-page-header"><?php the_archive_title( '<h1>', '</h1>' ); ?><?php the_archive_description( '<div class="ncu-archive-description">', '</div>' ); ?></header>
<?php if ( have_posts() ) : ?><div class="ncu-post-grid"><?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/content', get_post_type() ); endwhile; ?></div><?php the_posts_pagination(); else : get_template_part( 'template-parts/content', 'none' ); endif; ?>
</main>
<?php get_footer(); ?>
