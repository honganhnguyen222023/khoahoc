<?php
/**
 * Single post.
 *
 * @package KhoaHoc
 */

get_header();
while ( have_posts() ) :
	the_post();
	$kh_blog = get_option( 'page_for_posts' );
	kh_page_header( get_the_title(), '', array_filter( array( $kh_blog ? array( get_the_title( $kh_blog ), get_permalink( $kh_blog ) ) : null, array( get_the_title(), '' ) ) ) );
	?>
	<article <?php post_class( 'kh-container kh-page' ); ?>>
		<p class="kh-post-meta"><?php echo esc_html( get_the_date() ); ?> · <?php the_category( ', ' ); ?></p>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="kh-post-thumb"><?php the_post_thumbnail( 'large', array( 'fetchpriority' => 'high', 'loading' => 'eager' ) ); ?></figure>
		<?php endif; ?>
		<div class="kh-prose kh-entry"><?php the_content(); ?></div>
		<?php wp_link_pages(); ?>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</article>
	<?php
endwhile;
get_footer();
