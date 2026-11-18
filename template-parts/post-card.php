<?php
/**
 * Post card.
 *
 * @package KhoaHoc
 */

?>
<article <?php post_class( 'kh-card kh-card-image kh-post-card' ); ?>>
	<a class="kh-card-media<?php echo has_post_thumbnail() ? '' : ' kh-card-media-empty'; ?>" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php
		if ( has_post_thumbnail() ) {
			the_post_thumbnail( 'kh-card', array( 'class' => 'kh-card-img', 'alt' => '', 'sizes' => '(min-width: 1024px) 340px, 92vw' ) );
		}
		?>
	</a>
	<div class="kh-card-body">
		<p class="kh-card-meta"><?php echo esc_html( get_the_date() ); ?></p>
		<h2 class="kh-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="kh-card-text"><?php echo esc_html( get_the_excerpt() ); ?></p>
	</div>
</article>
