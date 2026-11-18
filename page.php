<?php
/**
 * Pages. Pages built with theme blocks are full-width "landing" pages;
 * normal pages get a title and a readable text column.
 *
 * @package KhoaHoc
 */

get_header();
while ( have_posts() ) :
	the_post();
	if ( kh_is_landing() ) :
		?>
		<article <?php post_class( 'kh-landing' ); ?>>
			<?php if ( ! is_front_page() ) : ?>
				<h1 class="screen-reader-text"><?php the_title(); ?></h1>
			<?php endif; ?>
			<?php the_content(); ?>
		</article>
		<?php
	else :
		kh_page_header( get_the_title(), '', array( array( get_the_title(), '' ) ) );
		?>
		<article <?php post_class( 'kh-container kh-page' ); ?>>
			<div class="kh-prose kh-entry"><?php the_content(); ?></div>
			<?php wp_link_pages(); ?>
		</article>
		<?php
	endif;
endwhile;
get_footer();
