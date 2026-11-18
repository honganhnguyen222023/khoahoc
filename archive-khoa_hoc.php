<?php
/**
 * Course archive + course category pages: filter sidebar on the left, list on the right.
 *
 * @package KhoaHoc
 */

get_header();
$kh_term  = is_tax( 'danh_muc_khoa_hoc' ) ? get_queried_object() : null;
$kh_title = $kh_term ? $kh_term->name : __( 'Tất cả khóa học', 'khoahoc' );
$kh_desc  = $kh_term && $kh_term->description ? $kh_term->description : __( 'Thanh toán một lần, học trọn đời trên điện thoại hoặc máy tính.', 'khoahoc' );
kh_page_header( $kh_title, $kh_desc, $kh_term ? array( array( 'Khóa học', get_post_type_archive_link( 'khoa_hoc' ) ), array( $kh_title, '' ) ) : array( array( $kh_title, '' ) ) );

$kh_f     = kh_course_filters();
$kh_combo = kh_combo_slug();
$kh_terms = get_terms( array( 'taxonomy' => 'danh_muc_khoa_hoc', 'hide_empty' => true ) );
$kh_terms = is_wp_error( $kh_terms ) ? array() : $kh_terms;
$kh_has_combo = false;
$kh_topics    = array();
foreach ( $kh_terms as $t ) {
	if ( $t->slug === $kh_combo ) {
		$kh_has_combo = true;
	} else {
		$kh_topics[] = $t;
	}
}
// A category page (/danh-muc-khoa-hoc/excel/) shows its own category as ticked.
if ( $kh_term ) {
	if ( $kh_term->slug === $kh_combo ) {
		$kh_f['loai'] = array_unique( array_merge( $kh_f['loai'], array( 'combo' ) ) );
	} else {
		$kh_f['chu_de'] = array_unique( array_merge( $kh_f['chu_de'], array( $kh_term->slug ) ) );
	}
}
$kh_active = count( $kh_f['loai'] ) + count( $kh_f['chu_de'] );
$kh_base   = get_post_type_archive_link( 'khoa_hoc' );
$kh_total  = (int) $GLOBALS['wp_query']->found_posts;

$kh_check = function ( $name, $value, $label, $checked ) {
	printf(
		'<label class="kh-check"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s><span>%4$s</span></label>',
		esc_attr( $name ),
		esc_attr( $value ),
		checked( $checked, true, false ),
		esc_html( $label )
	);
};
?>
<div class="kh-container kh-page">
	<form class="kh-catalog" method="get" action="<?php echo esc_url( $kh_base ); ?>" data-autosubmit>
		<aside class="kh-catalog-side">
			<details class="kh-filterbox" data-filterbox>
				<summary class="kh-filterbox-toggle">
					<?php echo kh_icon( 'sliders-horizontal', '', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php esc_html_e( 'Bộ lọc', 'khoahoc' ); ?>
					<?php if ( $kh_active ) : ?>
						<span class="kh-filterbox-count"><?php echo (int) $kh_active; ?></span>
					<?php endif; ?>
				</summary>
				<div class="kh-filterbox-body">
					<p class="kh-filterbox-title"><?php esc_html_e( 'Lọc khóa học', 'khoahoc' ); ?></p>

					<?php if ( $kh_has_combo ) : ?>
						<fieldset class="kh-filter-group">
							<legend><?php esc_html_e( 'Thể loại', 'khoahoc' ); ?></legend>
							<?php
							$kh_check( 'loai', 'le', __( 'Khóa học lẻ', 'khoahoc' ), in_array( 'le', $kh_f['loai'], true ) );
							$kh_check( 'loai', 'combo', __( 'Combo', 'khoahoc' ), in_array( 'combo', $kh_f['loai'], true ) );
							?>
						</fieldset>
					<?php endif; ?>

					<?php if ( $kh_topics ) : ?>
						<fieldset class="kh-filter-group">
							<legend><?php esc_html_e( 'Chủ đề', 'khoahoc' ); ?></legend>
							<?php
							foreach ( $kh_topics as $t ) {
								$kh_check( 'chu_de', $t->slug, $t->name, in_array( $t->slug, $kh_f['chu_de'], true ) );
							}
							?>
						</fieldset>
					<?php endif; ?>

					<button type="submit" class="kh-btn kh-btn-primary kh-btn-block kh-filter-submit"><?php esc_html_e( 'Lọc', 'khoahoc' ); ?></button>
					<?php if ( $kh_active || $kh_f['sap_xep'] ) : ?>
						<a class="kh-filter-reset" href="<?php echo esc_url( $kh_base ); ?>"><?php echo kh_icon( 'x', '', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Xóa tất cả bộ lọc', 'khoahoc' ); ?></a>
					<?php endif; ?>
				</div>
			</details>
		</aside>

		<div class="kh-catalog-main">
			<div class="kh-catalog-bar">
				<p class="kh-catalog-count" role="status">
					<?php
					/* translators: %d: number of courses */
					echo esc_html( sprintf( __( '%d khóa học', 'khoahoc' ), $kh_total ) );
					?>
				</p>
				<label class="kh-sort">
					<span><?php esc_html_e( 'Sắp xếp', 'khoahoc' ); ?></span>
					<select name="sap_xep">
						<?php foreach ( kh_course_sorts() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $kh_f['sap_xep'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

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
					<p><?php esc_html_e( 'Không có khóa học nào khớp bộ lọc.', 'khoahoc' ); ?></p>
					<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( $kh_base ); ?>"><?php esc_html_e( 'Xem tất cả khóa học', 'khoahoc' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
	</form>
</div>
<?php
get_footer();
