<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
/* Open Scholar Theme intentionally renders no theme header. Keep wp_body_open()
 * so 9stagram/Instagram and other shell-owner plugins can render their header. */
wp_body_open();
?>
<a class="ncu-skip-link" href="#primary"><?php esc_html_e( 'Skip to content', 'nine-code' ); ?></a>
