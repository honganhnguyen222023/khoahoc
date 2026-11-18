<?php
/**
 * Blog index / fallback.
 *
 * @package KhoaHoc
 */

get_header();
$kh_title = is_home() && get_option( 'page_for_posts' ) ? get_the_title( get_option( 'page_for_posts' ) ) : ( is_archive() ? wp_strip_all_tags( get_the_archive_title() ) : ( is_search() ? sprintf( 'Kết quả tìm kiếm: %s', get_search_query() ) : 'Bài viết' ) );
kh_page_header( $kh_title, is_archive() ? get_the_archive_description() : '', array( array( $kh_title, '' ) ) );
?>
<div class="kh-container kh-page">
	<?php if ( is_search() ) : ?>
		<div class="kh-search-wrap"><?php get_search_form(); ?></div>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<div class="kh-grid kh-cols-3">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/post', 'card' );
			endwhile;
			?>
		</div>
		<?php kh_pagination(); ?>
	<?php else : ?>
		<div class="kh-empty-state">
			<?php echo kh_icon( 'search', '', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p><?php esc_html_e( 'Chưa có nội dung nào ở đây. Thử tìm với từ khóa khác hoặc xem các khóa học.', 'khoahoc' ); ?></p>
			<a class="kh-btn kh-btn-primary" href="<?php echo esc_url( get_post_type_archive_link( 'khoa_hoc' ) ); ?>"><?php esc_html_e( 'Xem các khóa học', 'khoahoc' ); ?></a>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
