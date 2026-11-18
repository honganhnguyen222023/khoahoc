<?php
/**
 * Search form.
 *
 * @package KhoaHoc
 */

?>
<form role="search" method="get" class="kh-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="kh-s"><?php esc_html_e( 'Tìm kiếm', 'khoahoc' ); ?></label>
	<input id="kh-s" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Tìm khóa học, bài viết…', 'khoahoc' ); ?>">
	<button type="submit" class="kh-btn kh-btn-primary kh-btn-sm"><?php echo kh_icon( 'search', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="screen-reader-text"><?php esc_html_e( 'Tìm', 'khoahoc' ); ?></span></button>
</form>
