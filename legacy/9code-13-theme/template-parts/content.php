<article id="post-<?php the_ID(); ?>" <?php post_class( 'ncu-card' ); ?>>
<a class="ncu-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium_large' ); } else { echo '<span class="ncu-card__fallback">9</span>'; } ?></a>
<div class="ncu-card__body"><div class="ncu-card__meta"><?php echo esc_html( get_the_date() ); ?></div><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><div class="ncu-card__excerpt"><?php the_excerpt(); ?></div></div>
</article>
