<?php
$type_object = get_post_type_object( get_post_type() );
$type_label = $type_object && isset( $type_object->labels->singular_name ) ? $type_object->labels->singular_name : __( 'Content', 'nine-code-ultra' );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'ncu-card ncu-card--search' ); ?>>
<div class="ncu-card__body"><div class="ncu-card__meta"><?php echo esc_html( $type_label ); ?> · <?php echo esc_html( get_the_date() ); ?></div><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><div class="ncu-card__excerpt"><?php the_excerpt(); ?></div></div>
</article>
