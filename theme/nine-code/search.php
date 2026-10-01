<?php get_header(); ?>
<main id="primary" class="ncu-main ncu-container">
<header class="ncu-page-header"><h1><?php printf( esc_html__( 'Results for “%s”', 'nine-code' ), esc_html( get_search_query() ) ); ?></h1><?php get_search_form(); ?></header>
<?php if ( have_posts() ) : ?><div class="ncu-post-grid"><?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/content', 'search' ); endwhile; ?></div><?php the_posts_pagination(); else : get_template_part( 'template-parts/content', 'none' ); endif; ?>
</main>
<?php get_footer(); ?>
