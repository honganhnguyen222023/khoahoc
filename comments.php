<?php
/**
 * Comments.
 *
 * @package KhoaHoc
 */

if ( post_password_required() ) {
	return;
}
?>
<section class="kh-comments kh-prose" id="comments">
	<?php if ( have_comments() ) : ?>
		<h2><?php echo esc_html( sprintf( '%d bình luận', get_comments_number() ) ); ?></h2>
		<ol class="kh-comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true, 'avatar_size' => 40 ) ); ?></ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php comment_form(); ?>
</section>
