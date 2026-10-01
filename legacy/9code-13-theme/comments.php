<?php
if ( post_password_required() ) { return; }
?>
<section id="comments" class="ncu-comments">
<?php if ( have_comments() ) : ?><h2><?php comments_number( __( 'No comments', 'nine-code-ultra' ), __( 'One comment', 'nine-code-ultra' ), __( '% comments', 'nine-code-ultra' ) ); ?></h2><ol class="comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true ) ); ?></ol><?php the_comments_navigation(); ?><?php endif; ?>
<?php comment_form(); ?>
</section>
