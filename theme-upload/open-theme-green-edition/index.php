<?php get_header(); ?>
<main id="primary" class="ncu-main ncu-container">
    <?php if ( have_posts() ) : ?>
        <header class="ncu-page-header"><h1><?php bloginfo( 'name' ); ?></h1></header>
        <div class="ncu-post-grid">
        <?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/content', get_post_type() ); endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
    <?php else : get_template_part( 'template-parts/content', 'none' ); endif; ?>
</main>
<?php get_footer(); ?>
