<?php
/** Native site footer: footer menu and copyright. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<footer class="ncu-site-footer" role="contentinfo">
	<div class="ncu-container">
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav class="ncu-footer-nav" aria-label="<?php esc_attr_e( 'Footer', 'nine-code' ); ?>">
				<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'menu_class' => 'ncu-menu', 'depth' => 1, 'fallback_cb' => false ) ); ?>
			</nav>
		<?php endif; ?>
		<p class="ncu-copyright">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>
