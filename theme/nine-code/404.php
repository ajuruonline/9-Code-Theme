<?php get_header(); ?>
<main id="primary" class="ncu-main ncu-container ncu-empty-page">
<p class="ncu-error-code">404</p><h1><?php esc_html_e( 'That page could not be found.', 'nine-code-ultra' ); ?></h1><p><?php esc_html_e( 'Search the site or return to the homepage.', 'nine-code-ultra' ); ?></p><?php get_search_form(); ?><p><a class="ncu-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go home', 'nine-code-ultra' ); ?></a></p>
</main>
<?php get_footer(); ?>
