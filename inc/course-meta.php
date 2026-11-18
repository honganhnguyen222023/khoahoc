<?php
/**
 * Course details box (price, badge, benefits, register link…) – plain form fields.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field list for the course box.
 *
 * @return array
 */
function kh_course_fields() {
	return array(
		'price'         => array( 'label' => 'Học phí (đ)', 'type' => 'number', 'help' => 'Chỉ nhập số, ví dụ 490000.' ),
		'regular_price' => array( 'label' => 'Giá gốc / mua lẻ (đ)', 'type' => 'number', 'help' => 'Để trống nếu không giảm giá. Có giá gốc sẽ hiện gạch ngang và số tiền tiết kiệm.' ),
		'badge'         => array( 'label' => 'Nhãn nhỏ', 'help' => 'Ví dụ: Khóa mới, Cơ bản → Nâng cao, Trọn bộ.' ),
		'icon'          => array( 'label' => 'Biểu tượng', 'type' => 'icon' ),
		'meta_line'     => array( 'label' => 'Dòng thông tin', 'help' => 'Ví dụ: 12 chương · 121 bài · ~16,5 giờ' ),
		'audience'      => array( 'label' => 'Dành cho ai', 'help' => 'Một câu ngắn, ví dụ: Dành cho bạn muốn giỏi Excel trước.' ),
		'benefits'      => array( 'label' => 'Lợi ích nổi bật', 'type' => 'textarea', 'help' => 'Mỗi dòng một ý. Bọc chữ trong **hai dấu sao** để in đậm.' ),
		'video'         => array( 'label' => 'Video giới thiệu (YouTube)', 'type' => 'url', 'help' => 'Dán link YouTube. Video chỉ tải khi người xem bấm Play (giúp trang nhanh).' ),
		'register_url'  => array( 'label' => 'Link nút Đăng ký', 'type' => 'url', 'help' => 'Để trống: tự dùng sản phẩm WooCommerce bên dưới, nếu không có thì mở Zalo.' ),
		'wc_product'    => array( 'label' => 'ID sản phẩm WooCommerce', 'type' => 'number', 'help' => 'Nếu bán qua WooCommerce: nút Đăng ký thêm sản phẩm này vào giỏ và chuyển tới thanh toán.' ),
		'detail_url'    => array( 'label' => 'Link "Xem chi tiết" riêng', 'type' => 'url', 'help' => 'Để trống = mở trang khóa học này. Dùng khi có landing page riêng.' ),
		'featured'      => array( 'label' => 'Khóa nổi bật (thẻ lớn ở trang chủ)', 'type' => 'checkbox' ),
	);
}

/**
 * All course data in one array.
 *
 * @param int $id Post ID.
 * @return array
 */
function kh_course( $id = 0 ) {
	$id  = $id ? $id : get_the_ID();
	$out = array( 'id' => $id );
	foreach ( array_keys( kh_course_fields() ) as $key ) {
		$out[ $key ] = get_post_meta( $id, '_kh_' . $key, true );
	}
	$price   = (float) $out['price'];
	$regular = (float) $out['regular_price'];

	$out['price_html']   = kh_price( $price );
	$out['regular_html'] = $regular > $price ? kh_price( $regular ) : '';
	$out['save_html']    = $regular > $price && $price > 0 ? '−' . kh_price( $regular - $price ) : '';
	$out['detail']       = $out['detail_url'] ? $out['detail_url'] : get_permalink( $id );
	$out['icon']         = $out['icon'] ? $out['icon'] : 'book-open';
	$out['cover']        = get_post_meta( $id, '_kh_cover', true ); // Demo cover image (theme file).
	$out['benefit_list'] = array_values( array_filter( array_map( 'trim', explode( "\n", (string) $out['benefits'] ) ) ) );
	$out['register']     = kh_course_register_url( $out );
	return $out;
}

/**
 * Where the "Đăng ký" button goes.
 *
 * @param array $c Course data.
 * @return string
 */
function kh_course_register_url( $c ) {
	if ( ! empty( $c['register_url'] ) ) {
		return $c['register_url'];
	}
	if ( ! empty( $c['wc_product'] ) && function_exists( 'wc_get_checkout_url' ) ) {
		return add_query_arg( 'add-to-cart', absint( $c['wc_product'] ), wc_get_checkout_url() );
	}
	$zalo = kh_zalo_url();
	return $zalo ? $zalo : home_url( '/lien-he/' );
}

/**
 * Benefit line: escape, then **bold**.
 *
 * @param string $line Line.
 * @return string
 */
function kh_benefit( $line ) {
	return preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', esc_html( $line ) );
}

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'kh_course_box', __( 'Thông tin khóa học', 'khoahoc' ), 'kh_course_box', 'khoa_hoc', 'normal', 'high' );
	}
);

/**
 * Render box.
 *
 * @param WP_Post $post Post.
 */
function kh_course_box( $post ) {
	wp_nonce_field( 'kh_course_save', 'kh_course_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( kh_course_fields() as $key => $f ) {
		$type = isset( $f['type'] ) ? $f['type'] : 'text';
		$val  = get_post_meta( $post->ID, '_kh_' . $key, true );
		$name = 'kh_course[' . $key . ']';
		$id   = 'kh-course-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label></th><td>';
		switch ( $type ) {
			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" class="large-text" rows="4" name="' . esc_attr( $name ) . '">' . esc_textarea( $val ) . '</textarea>';
				break;
			case 'checkbox':
				echo '<label><input id="' . esc_attr( $id ) . '" type="checkbox" value="1" name="' . esc_attr( $name ) . '" ' . checked( '1', $val, false ) . '> Có</label>';
				break;
			case 'icon':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">— Mặc định —</option>';
				foreach ( array_keys( kh_icons() ) as $icon ) {
					echo '<option value="' . esc_attr( $icon ) . '" ' . selected( $icon, $val, false ) . '>' . esc_html( $icon ) . '</option>';
				}
				echo '</select>';
				break;
			default:
				$t = 'number' === $type ? 'number' : ( 'url' === $type ? 'url' : 'text' );
				echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $t ) . '" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '"' . ( 'number' === $t ? ' min="0" step="1000"' : '' ) . '>';
		}
		if ( ! empty( $f['help'] ) ) {
			echo '<p class="description">' . esc_html( $f['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Mẹo: "Đoạn trích" (Excerpt) ở cột phải là mô tả ngắn hiện trên thẻ khóa học. "Ảnh đại diện" dùng cho thẻ nổi bật và khi chia sẻ Facebook.', 'khoahoc' ) . '</p>';
}

add_action(
	'save_post_khoa_hoc',
	function ( $post_id ) {
		if ( ! isset( $_POST['kh_course_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kh_course_nonce'] ), 'kh_course_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$data = isset( $_POST['kh_course'] ) ? (array) wp_unslash( $_POST['kh_course'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( kh_course_fields() as $key => $f ) {
			$type = isset( $f['type'] ) ? $f['type'] : 'text';
			$raw  = isset( $data[ $key ] ) ? $data[ $key ] : '';
			switch ( $type ) {
				case 'number':
					$val = '' === $raw ? '' : (string) absint( $raw );
					break;
				case 'url':
					$val = esc_url_raw( $raw );
					break;
				case 'textarea':
					$val = sanitize_textarea_field( $raw );
					break;
				case 'checkbox':
					$val = $raw ? '1' : '';
					break;
				case 'icon':
					$val = array_key_exists( $raw, kh_icons() ) ? $raw : '';
					break;
				default:
					$val = sanitize_text_field( $raw );
			}
			if ( '' === $val ) {
				delete_post_meta( $post_id, '_kh_' . $key );
			} else {
				update_post_meta( $post_id, '_kh_' . $key, $val );
			}
		}
	}
);

// Price column in the admin list.
add_filter(
	'manage_khoa_hoc_posts_columns',
	function ( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['kh_price']    = __( 'Học phí', 'khoahoc' );
				$new['kh_featured'] = __( 'Nổi bật', 'khoahoc' );
			}
		}
		return $new;
	}
);
add_action(
	'manage_khoa_hoc_posts_custom_column',
	function ( $col, $id ) {
		if ( 'kh_price' === $col ) {
			echo esc_html( kh_price( get_post_meta( $id, '_kh_price', true ) ) );
		}
		if ( 'kh_featured' === $col ) {
			echo get_post_meta( $id, '_kh_featured', true ) ? '★' : '';
		}
	},
	10,
	2
);
