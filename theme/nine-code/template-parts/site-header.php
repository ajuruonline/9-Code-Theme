<?php
/** Native site header: logo or title, primary menu and a mobile toggle. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<header class="ncu-site-header" role="banner">
	<div class="ncu-site-header__inner ncu-container">
		<div class="ncu-site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="ncu-site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<button type="button" class="ncu-nav-toggle" aria-expanded="false" aria-controls="ncu-primary-nav">
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'nine-code' ); ?></span>
				<svg class="ncu-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
			</button>
			<nav id="ncu-primary-nav" class="ncu-primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'nine-code' ); ?>">
				<?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'ncu-menu', 'depth' => 2, 'fallback_cb' => false ) ); ?>
			</nav>
		<?php endif; ?>
	</div>
</header>
