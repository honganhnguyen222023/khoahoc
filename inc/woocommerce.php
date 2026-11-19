<?php
/**
 * WooCommerce: giỏ hàng, thanh toán, đăng ký học viên, mở khóa khóa học.
 * Chỉ chạy khi plugin WooCommerce đang bật.
 *
 * Luồng: Khóa học (có học phí) → theme tự tạo 1 sản phẩm ẩn → học viên thêm vào
 * giỏ / thanh toán (bắt buộc có tài khoản) → đơn "Đang xử lý" hoặc "Hoàn thành"
 * → khóa học tự mở. Đơn bị hủy / hoàn tiền → khóa học bị thu hồi.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'woocommerce' );
	}
);

if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
	return;
}

// Theme wrappers around shop pages.
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
add_action(
	'woocommerce_before_main_content',
	function () {
		echo '<div class="kh-container kh-page kh-woo">';
	},
	10
);
add_action(
	'woocommerce_after_main_content',
	function () {
		echo '</div>';
	},
	10
);

// Khóa học online: bỏ các ô địa chỉ giao hàng không cần thiết.
add_filter(
	'woocommerce_checkout_fields',
	function ( $fields ) {
		$drop = array( 'billing_company', 'billing_address_2', 'billing_postcode', 'billing_state' );
		if ( WC()->cart && ! WC()->cart->needs_shipping() ) {
			$drop = array_merge( $drop, array( 'billing_address_1', 'billing_city' ) );
		}
		foreach ( $drop as $k ) {
			unset( $fields['billing'][ $k ] );
		}
		return $fields;
	}
);

/**
 * Giỏ hàng chỉ có khóa học online (không cần giao hàng)?
 *
 * @return bool
 */
function kh_wc_digital_cart() {
	return function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() && ! WC()->cart->needs_shipping();
}

// Trang thanh toán dạng khối (WooCommerce 8.3+) và Store API: ẩn + không bắt buộc ô địa chỉ.
add_filter(
	'woocommerce_default_address_fields',
	function ( $fields ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $fields;
		}
		if ( ! kh_wc_digital_cart() ) {
			return $fields;
		}
		foreach ( array( 'company', 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $k ) {
			if ( isset( $fields[ $k ] ) ) {
				$fields[ $k ]['required'] = false;
				$fields[ $k ]['hidden']   = true;
			}
		}
		return $fields;
	},
	20
);
add_filter(
	'woocommerce_get_country_locale',
	function ( $locale ) {
		if ( ! kh_wc_digital_cart() ) {
			return $locale;
		}
		foreach ( $locale as $country => $fields ) {
			foreach ( array( 'company', 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $k ) {
				$locale[ $country ][ $k ]['required'] = false;
				$locale[ $country ][ $k ]['hidden']   = true;
			}
		}
		return $locale;
	},
	20
);

// Cart count in header updates via AJAX fragments.
add_filter(
	'woocommerce_add_to_cart_fragments',
	function ( $fragments ) {
		$fragments['span.kh-cart-count'] = '<span class="kh-cart-count">' . (int) WC()->cart->get_cart_contents_count() . '</span>';
		return $fragments;
	}
);

/* ==========================================================================
 * Khóa học ↔ sản phẩm
 * ======================================================================= */

/**
 * Các khóa học bán qua sản phẩm này.
 *
 * @param int $product_id Product ID.
 * @return int[]
 */
function kh_wc_product_courses( $product_id ) {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'khoa_hoc',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_kh_wc_product', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => (int) $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);
}

/**
 * Tạo / cập nhật sản phẩm WooCommerce cho khóa học (tên, giá, ảnh).
 * Sản phẩm để "ẩn" khỏi cửa hàng: trang khóa học chính là trang bán hàng.
 *
 * @param int $course_id Course ID.
 * @return int Product ID (0 nếu khóa miễn phí / chưa có giá).
 */
function kh_wc_sync_product( $course_id ) {
	$price   = (float) get_post_meta( $course_id, '_kh_price', true );
	$regular = (float) get_post_meta( $course_id, '_kh_regular_price', true );
	$pid     = (int) get_post_meta( $course_id, '_kh_wc_product', true );
	$product = $pid ? wc_get_product( $pid ) : null;

	if ( ! $product ) {
		// Đã tạo trước đó (VD: lưu 2 lần liên tiếp trong trình soạn thảo)?
		$found = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_kh_course_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => (int) $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$product = $found ? wc_get_product( $found[0] ) : null;
	}
	if ( ! $product ) {
		if ( $price <= 0 || 'publish' !== get_post_status( $course_id ) ) {
			return 0;
		}
		$product = new WC_Product_Simple();
		$product->update_meta_data( '_kh_course_id', (int) $course_id );
	}

	// Chỉ đồng bộ sản phẩm do theme tạo; sản phẩm admin tự gắn thì để nguyên.
	if ( (int) $product->get_meta( '_kh_course_id' ) === (int) $course_id ) {
		$product->set_name( get_the_title( $course_id ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_tax_status( 'none' );
		if ( $price > 0 ) {
			$product->set_regular_price( (string) ( $regular > $price ? $regular : $price ) );
			$product->set_sale_price( $regular > $price ? (string) $price : '' );
		}
		$thumb = get_post_thumbnail_id( $course_id );
		if ( $thumb ) {
			$product->set_image_id( $thumb );
		}
		$product->save();
	}

	$pid = $product->get_id();
	update_post_meta( $course_id, '_kh_wc_product', $pid );
	return $pid;
}

add_action(
	'save_post_khoa_hoc',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		kh_wc_sync_product( $post_id );
	},
	20
);

// Trang sản phẩm của khóa học → chuyển về trang khóa học.
add_action(
	'template_redirect',
	function () {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$cid = (int) get_post_meta( get_queried_object_id(), '_kh_course_id', true );
		if ( $cid && 'publish' === get_post_status( $cid ) ) {
			wp_safe_redirect( get_permalink( $cid ), 301 );
			exit;
		}
	}
);

/**
 * Link khóa học của một sản phẩm trong giỏ / đơn hàng.
 *
 * @param string $link       Link gốc.
 * @param int    $product_id Product ID.
 * @return string
 */
function kh_wc_course_link( $link, $product_id ) {
	$cid = (int) get_post_meta( $product_id, '_kh_course_id', true );
	return $cid ? get_permalink( $cid ) : $link;
}
add_filter(
	'woocommerce_cart_item_permalink',
	function ( $link, $item ) {
		return kh_wc_course_link( $link, $item['product_id'] );
	},
	10,
	2
);
add_filter(
	'woocommerce_order_item_permalink',
	function ( $link, $item ) {
		return kh_wc_course_link( $link, $item->get_product_id() );
	},
	10,
	2
);

// Không cho mua lại khóa đã sở hữu; bấm "Mua" 2 lần không báo lỗi.
add_filter(
	'woocommerce_add_to_cart_validation',
	function ( $ok, $product_id ) {
		foreach ( kh_wc_product_courses( $product_id ) as $cid ) {
			if ( kh_has_access( $cid ) ) {
				wc_add_notice( sprintf( 'Bạn đã sở hữu khóa "%s". <a href="%s">Vào học ngay</a>', esc_html( get_the_title( $cid ) ), esc_url( kh_learn_url( $cid ) ) ), 'notice' );
				return false;
			}
		}
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( (int) $item['product_id'] === (int) $product_id ) {
					return false; // Đã có trong giỏ: giữ nguyên, không báo lỗi.
				}
			}
		}
		return $ok;
	},
	10,
	2
);

/* ==========================================================================
 * Đơn hàng → mở / thu hồi khóa học
 * ======================================================================= */

/**
 * Mở khóa học của một đơn đã thanh toán.
 *
 * @param int $order_id Order ID.
 */
function kh_wc_grant_order( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$user_id = $order->get_customer_id();
	if ( ! $user_id ) {
		// Đơn khách (hoặc admin tạo tay): gắn vào tài khoản theo email, chưa có thì tạo.
		$email = $order->get_billing_email();
		if ( ! is_email( $email ) ) {
			return;
		}
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$user_id = $user->ID;
		} else {
			$user_id = wc_create_new_customer( $email, '', wp_generate_password(), array( 'first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name() ) );
			if ( is_wp_error( $user_id ) ) {
				$order->add_order_note( 'Không tạo được tài khoản học viên: ' . $user_id->get_error_message() );
				return;
			}
		}
		$order->set_customer_id( $user_id );
		$order->save();
	}

	$opened = array();
	foreach ( $order->get_items() as $item ) {
		foreach ( kh_wc_product_courses( $item->get_product_id() ) as $cid ) {
			if ( ! isset( kh_user_course_map( $user_id )[ $cid ] ) ) {
				kh_grant_course( $user_id, $cid, 'order:' . $order->get_id() );
				$opened[] = get_the_title( $cid );
			}
		}
	}
	if ( $opened ) {
		$order->add_order_note( 'Đã mở khóa học: ' . implode( ', ', $opened ) );
	}
}
add_action( 'woocommerce_order_status_processing', 'kh_wc_grant_order' );
add_action( 'woocommerce_order_status_completed', 'kh_wc_grant_order' );

/**
 * Thu hồi khóa học của đơn bị hủy / hoàn tiền.
 *
 * @param int $order_id Order ID.
 */
function kh_wc_revoke_order( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! $order->get_customer_id() ) {
		return;
	}
	$closed = array();
	foreach ( $order->get_items() as $item ) {
		foreach ( kh_wc_product_courses( $item->get_product_id() ) as $cid ) {
			if ( isset( kh_user_course_map( $order->get_customer_id() )[ $cid ] ) ) {
				kh_revoke_course( $order->get_customer_id(), $cid, 'order:' . $order->get_id() );
				$closed[] = get_the_title( $cid );
			}
		}
	}
	if ( $closed ) {
		$order->add_order_note( 'Đã thu hồi khóa học: ' . implode( ', ', $closed ) );
	}
}
foreach ( array( 'cancelled', 'refunded', 'failed' ) as $kh_status ) {
	add_action( 'woocommerce_order_status_' . $kh_status, 'kh_wc_revoke_order' );
}

/**
 * Các khóa trong đơn (HTML danh sách link).
 *
 * @param WC_Order $order Order.
 * @param bool     $paid  Đã mở chưa.
 * @return string
 */
function kh_wc_order_courses_html( $order, $paid ) {
	$items = '';
	foreach ( $order->get_items() as $item ) {
		foreach ( kh_wc_product_courses( $item->get_product_id() ) as $cid ) {
			$items .= '<li><strong>' . esc_html( get_the_title( $cid ) ) . '</strong>';
			if ( $paid ) {
				$items .= ' – <a href="' . esc_url( kh_learn_url( $cid ) ) . '">Vào học</a>';
			}
			$items .= '</li>';
		}
	}
	return $items ? '<ul>' . $items . '</ul>' : '';
}

// Trang cảm ơn sau khi đặt hàng.
add_action(
	'woocommerce_thankyou',
	function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$paid = $order->has_status( array( 'processing', 'completed' ) );
		$list = kh_wc_order_courses_html( $order, $paid );
		if ( ! $list ) {
			return;
		}
		echo '<section class="kh-thanks ' . ( $paid ? 'is-paid' : 'is-wait' ) . '">';
		if ( $paid ) {
			echo '<h2>Khóa học đã được mở</h2>' . $list; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<a class="kh-btn kh-btn-primary" href="' . esc_url( kh_my_courses_url() ) . '">Vào khóa học của tôi</a>';
		} else {
			echo '<h2>Đang chờ xác nhận thanh toán</h2><p>Ngay khi nhận được học phí, chúng tôi sẽ mở khóa học và gửi email báo cho bạn. Bạn vào học tại mục <a href="' . esc_url( kh_my_courses_url() ) . '">Khóa học của tôi</a>.</p>' . $list; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</section>';
	},
	5
);

// Email gửi học viên khi đơn đã thanh toán: kèm link vào học.
add_action(
	'woocommerce_email_after_order_table',
	function ( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || $plain_text || ! $order->has_status( array( 'processing', 'completed' ) ) ) {
			return;
		}
		$list = kh_wc_order_courses_html( $order, true );
		if ( $list ) {
			echo '<h2>Vào học</h2>' . $list; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	10,
	3
);

/* ==========================================================================
 * Đăng nhập / đăng ký học viên (trang Tài khoản)
 * ======================================================================= */

/**
 * Link quay lại được truyền qua ?redirect_to= (VD: từ bài học thử).
 *
 * @return string
 */
function kh_wc_requested_back() {
	return isset( $_GET['redirect_to'] ) ? kh_front_redirect( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
}

// Giữ link quay lại trong form đăng nhập + đăng ký.
foreach ( array( 'woocommerce_login_form_end', 'woocommerce_register_form_end' ) as $kh_hook ) {
	add_action(
		$kh_hook,
		function () {
			$back = kh_wc_requested_back();
			if ( $back ) {
				echo '<input type="hidden" name="redirect" value="' . esc_attr( $back ) . '">';
			}
		}
	);
}

add_action(
	'woocommerce_before_customer_login_form',
	function () {
		if ( kh_wc_requested_back() ) {
			echo '<div class="kh-login-note">' . kh_icon( 'play', '', 18 ) . '<p>Đăng nhập hoặc <strong>tạo tài khoản miễn phí</strong> để học thử bài đầu tiên.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
);

/**
 * Sau đăng nhập / đăng ký: học viên về trang chủ (hoặc về đúng bài học đang xem).
 *
 * @param string $redirect Mặc định.
 * @param mixed  $user     User (chỉ có khi đăng nhập).
 * @return string
 */
function kh_wc_after_login( $redirect, $user = null ) {
	if ( $user instanceof WP_User && kh_is_staff( $user ) ) {
		return $redirect;
	}
	$back = isset( $_POST['redirect'] ) ? kh_front_redirect( wp_unslash( $_POST['redirect'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	return $back ? $back : home_url( '/' );
}
add_filter( 'woocommerce_login_redirect', 'kh_wc_after_login', 20, 2 );
add_filter( 'woocommerce_registration_redirect', 'kh_wc_after_login', 20 );

// Đăng xuất từ trang Tài khoản → trang chủ.
add_filter(
	'woocommerce_logout_default_redirect_url',
	function () {
		return home_url( '/' );
	}
);

// Học viên không bị WooCommerce đẩy vào wp-admin.
add_filter(
	'woocommerce_prevent_admin_access',
	function ( $prevent ) {
		return kh_is_staff() ? false : $prevent;
	}
);

// Form đăng ký: thêm Họ tên + Số điện thoại.
add_action(
	'woocommerce_register_form_start',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification
		$name  = isset( $_POST['kh_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['kh_full_name'] ) ) : '';
		$phone = isset( $_POST['kh_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['kh_phone'] ) ) : '';
		// phpcs:enable
		?>
		<p class="woocommerce-form-row form-row form-row-wide">
			<label for="kh_full_name">Họ và tên&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="text" class="woocommerce-Input input-text" name="kh_full_name" id="kh_full_name" autocomplete="name" value="<?php echo esc_attr( $name ); ?>" required>
		</p>
		<p class="woocommerce-form-row form-row form-row-wide">
			<label for="kh_phone">Số điện thoại / Zalo&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="tel" class="woocommerce-Input input-text" name="kh_phone" id="kh_phone" autocomplete="tel" inputmode="tel" value="<?php echo esc_attr( $phone ); ?>" required>
		</p>
		<?php
	}
);

add_action(
	'woocommerce_register_post',
	function ( $username, $email, $errors ) {
		// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce đã kiểm tra nonce.
		if ( ! isset( $_POST['kh_full_name'] ) ) {
			return; // Đăng ký lúc thanh toán: dùng ô Họ/Tên của form thanh toán.
		}
		if ( '' === trim( sanitize_text_field( wp_unslash( $_POST['kh_full_name'] ) ) ) ) {
			$errors->add( 'kh_full_name', 'Vui lòng nhập họ và tên.' );
		}
		$digits = kh_digits( isset( $_POST['kh_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['kh_phone'] ) ) : '' );
		if ( strlen( $digits ) < 9 || strlen( $digits ) > 12 ) {
			$errors->add( 'kh_phone', 'Số điện thoại chưa đúng.' );
		}
		// phpcs:enable
	},
	10,
	3
);

add_action(
	'woocommerce_created_customer',
	function ( $user_id ) {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['kh_full_name'] ) ) {
			return;
		}
		$full  = trim( sanitize_text_field( wp_unslash( $_POST['kh_full_name'] ) ) );
		$phone = isset( $_POST['kh_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['kh_phone'] ) ) : '';
		// phpcs:enable
		// "Nguyễn Văn An" → Tên: An, Họ: Nguyễn Văn.
		$parts = preg_split( '/\s+/u', $full );
		$first = array_pop( $parts );
		$last  = implode( ' ', $parts );
		wp_update_user( array( 'ID' => $user_id, 'first_name' => $first, 'last_name' => $last, 'display_name' => $full ) );
		update_user_meta( $user_id, 'billing_first_name', $first );
		update_user_meta( $user_id, 'billing_last_name', $last );
		update_user_meta( $user_id, 'billing_phone', $phone );
	}
);

/* ==========================================================================
 * Trang Tài khoản: mục "Khóa học của tôi"
 * ======================================================================= */

add_filter(
	'woocommerce_get_query_vars',
	function ( $vars ) {
		$vars['khoa-hoc-cua-toi'] = 'khoa-hoc-cua-toi';
		return $vars;
	}
);

add_filter(
	'woocommerce_account_menu_items',
	function ( $items ) {
		unset( $items['downloads'] );
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'dashboard' === $key ) {
				$new['khoa-hoc-cua-toi'] = 'Khóa học của tôi';
			}
		}
		return $new;
	}
);

add_filter(
	'woocommerce_endpoint_khoa-hoc-cua-toi_title',
	function () {
		return 'Khóa học của tôi';
	}
);

add_action(
	'woocommerce_account_khoa-hoc-cua-toi_endpoint',
	function () {
		echo kh_my_courses_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

// Trang tổng quan tài khoản: hiện luôn các khóa đang học.
add_action(
	'woocommerce_account_dashboard',
	function () {
		echo '<h2 class="kh-account-h">Khóa học của tôi</h2>' . kh_my_courses_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

/* ==========================================================================
 * Cài đặt WooCommerce một lần cho website khóa học
 * ======================================================================= */

/**
 * Bật đăng ký tài khoản, bắt buộc có tài khoản khi mua, tiền VND,
 * dùng trang "Tài khoản" của theme, tạo sản phẩm cho các khóa học có sẵn.
 * Chạy 1 lần; sau đó admin đổi trong WooCommerce → Cài đặt vẫn được giữ.
 */
function kh_wc_setup() {
	if ( get_option( 'kh_wc_setup' ) === KH_VERSION || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
	update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
	update_option( 'woocommerce_enable_guest_checkout', 'no' );
	update_option( 'woocommerce_registration_generate_username', 'yes' );
	update_option( 'woocommerce_registration_generate_password', 'no' );

	if ( 'USD' === get_option( 'woocommerce_currency', 'USD' ) ) {
		update_option( 'woocommerce_currency', 'VND' );
		update_option( 'woocommerce_currency_pos', 'right' );
		update_option( 'woocommerce_price_thousand_sep', '.' );
		update_option( 'woocommerce_price_decimal_sep', ',' );
		update_option( 'woocommerce_price_num_decimals', '0' );
	}

	// Trang "Tài khoản" của theme làm trang tài khoản WooCommerce.
	$page = get_page_by_path( 'tai-khoan' );
	if ( $page ) {
		if ( ! has_shortcode( $page->post_content, 'woocommerce_my_account' ) && false !== strpos( $page->post_content, 'kh-login' ) ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_content' => "<!-- wp:shortcode -->\n[woocommerce_my_account]\n<!-- /wp:shortcode -->" ) );
		}
		if ( has_shortcode( get_post_field( 'post_content', $page->ID ), 'woocommerce_my_account' ) ) {
			update_option( 'woocommerce_myaccount_page_id', $page->ID );
		}
	}

	foreach ( get_posts( array( 'post_type' => 'khoa_hoc', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $cid ) {
		kh_wc_sync_product( $cid );
	}

	update_option( 'kh_wc_setup', KH_VERSION );
	flush_rewrite_rules();
}
add_action( 'admin_init', 'kh_wc_setup' );
