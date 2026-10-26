<?php
/** Entry title and meta, governed by the site defaults and per-entry "9Code Display" overrides. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$show_title = function_exists( 'ncu_safe_show' ) ? ncu_safe_show( 'title', true ) : true;
$show_meta  = is_singular( 'post' ) && function_exists( 'ncu_safe_show' ) && ncu_safe_show( 'meta', false );
if ( ! $show_title && ! $show_meta ) { return; }
?>
<header class="ncu-entry-header">
	<?php if ( $show_title ) { the_title( '<h1 class="ncu-entry-title">', '</h1>' ); } ?>
	<?php if ( $show_meta ) : ?>
		<div class="ncu-entry-meta"><?php echo esc_html( get_the_date() ); ?> · <?php the_author(); ?></div>
	<?php endif; ?>
</header>
