<?php
/**
 * Course archive + course category pages.
 *
 * @package KhoaHoc
 */

get_header();
$kh_term  = is_tax( 'danh_muc_khoa_hoc' ) ? get_queried_object() : null;
$kh_title = $kh_term ? $kh_term->name : __( 'Tất cả khóa học', 'khoahoc' );
$kh_desc  = $kh_term && $kh_term->description ? $kh_term->description : __( 'Thanh toán một lần, học trọn đời trên điện thoại hoặc máy tính.', 'khoahoc' );
kh_page_header( $kh_title, $kh_desc, $kh_term ? array( array( 'Khóa học', get_post_type_archive_link( 'khoa_hoc' ) ), array( $kh_title, '' ) ) : array( array( $kh_title, '' ) ) );
$kh_terms = get_terms( array( 'taxonomy' => 'danh_muc_khoa_hoc', 'hide_empty' => true ) );
?>
<div class="kh-container kh-page">
	<?php if ( $kh_terms && ! is_wp_error( $kh_terms ) ) : ?>
		<nav class="kh-filter" aria-label="<?php esc_attr_e( 'Lọc theo danh mục', 'khoahoc' ); ?>">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'khoa_hoc' ) ); ?>" class="<?php echo $kh_term ? '' : 'is-active'; ?>" <?php echo $kh_term ? '' : 'aria-current="page"'; ?>><?php esc_html_e( 'Tất cả', 'khoahoc' ); ?></a>
			<?php foreach ( $kh_terms as $t ) : ?>
				<?php $active = $kh_term && $kh_term->term_id === $t->term_id; ?>
				<a href="<?php echo esc_url( get_term_link( $t ) ); ?>" class="<?php echo $active ? 'is-active' : ''; ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $t->name ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="kh-grid kh-cols-3 kh-course-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/course', 'card' );
			endwhile;
			?>
		</div>
		<?php kh_pagination(); ?>
	<?php else : ?>
		<div class="kh-empty-state">
			<?php echo kh_icon( 'book-open', '', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p><?php esc_html_e( 'Chưa có khóa học nào trong mục này.', 'khoahoc' ); ?></p>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
