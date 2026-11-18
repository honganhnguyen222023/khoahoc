<?php
/**
 * 404.
 *
 * @package KhoaHoc
 */

get_header();
?>
<div class="kh-container kh-page kh-404">
	<p class="kh-404-code">404</p>
	<h1 class="kh-h2"><?php esc_html_e( 'Không tìm thấy trang này', 'khoahoc' ); ?></h1>
	<p class="kh-lead"><?php esc_html_e( 'Đường dẫn có thể đã đổi. Thử tìm kiếm hoặc quay về trang chủ.', 'khoahoc' ); ?></p>
	<div class="kh-search-wrap"><?php get_search_form(); ?></div>
	<div class="kh-404-actions">
		<a class="kh-btn kh-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Về trang chủ', 'khoahoc' ); ?></a>
		<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( get_post_type_archive_link( 'khoa_hoc' ) ); ?>"><?php esc_html_e( 'Xem khóa học', 'khoahoc' ); ?></a>
	</div>
</div>
<?php
get_footer();
