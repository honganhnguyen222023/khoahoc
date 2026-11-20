<?php
/**
 * KhoaHoc Academy theme bootstrap.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

define( 'KH_VERSION', '1.0.0' );
define( 'KH_DIR', get_template_directory() );
define( 'KH_URI', get_template_directory_uri() );

$kh_includes = array(
	'vietnamese',
	'helpers',
	'template-tags',
	'icons',
	'options',
	'setup',
	'post-types',
	'lms',
	'course-meta',
	'blocks',
	'contact',
	'seo',
	'woocommerce',
	'demo',
	'quantri',
);

// Thiếu file (giải nén lỗi, phần mềm diệt virus xóa nhầm…): bỏ qua và báo trong quản trị,
// không làm sập cả website.
$kh_missing = array();
foreach ( $kh_includes as $kh_file ) {
	$kh_path = KH_DIR . '/inc/' . $kh_file . '.php';
	if ( is_readable( $kh_path ) ) {
		require_once $kh_path;
	} else {
		$kh_missing[] = 'inc/' . $kh_file . '.php';
	}
}
if ( $kh_missing ) {
	add_action(
		'admin_notices',
		function () use ( $kh_missing ) {
			echo '<div class="notice notice-error"><p><strong>Theme KhoaHoc thiếu file:</strong> ' . esc_html( implode( ', ', $kh_missing ) ) . '. Vào Giao diện → Thêm mới → Tải lên, chọn lại file khoahoc.zip và bấm "Thay thế bản hiện tại".</p></div>';
		}
	);
}
