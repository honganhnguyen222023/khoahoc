<?php
/**
 * Tiếng Việt cho toàn bộ website: trang quản trị, trang học viên, giỏ hàng, thanh toán.
 *
 * 1. Luôn dùng ngôn ngữ "vi" (kể cả khi tài khoản admin chọn ngôn ngữ khác).
 * 2. Tự tải bản dịch tiếng Việt của WordPress + plugin (WooCommerce…) nếu hosting cho phép.
 * 3. Cài định dạng Việt Nam một lần: ngày dd/mm/yyyy, giờ 24h, múi giờ Hà Nội, tuần bắt đầu Thứ 2.
 * 4. Viết lại các chữ WooCommerce cho hợp website khóa học
 *    (ngoài trang web: luôn dùng chữ của theme; trong quản trị: chỉ thay chữ chưa được dịch).
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

define( 'KH_LOCALE', 'vi' );

/* ==========================================================================
 * 1. Luôn dùng tiếng Việt
 * ======================================================================= */

add_filter(
	'locale',
	function () {
		return KH_LOCALE;
	},
	99
);
add_filter(
	'determine_locale',
	function () {
		return KH_LOCALE;
	},
	99
);

/*
 * WordPress nạp ngôn ngữ gốc ("Bảng tin", "Bài viết"…) TRƯỚC khi chạy theme.
 * - Lưu "vi" vào cài đặt của site để các lần sau WordPress nạp đúng ngay từ đầu.
 * - Lần này: nạp lại tiếng Việt cho WordPress + tên ngày/tháng.
 */
// Ngôn ngữ WordPress đã nạp trước theme: của site, hoặc của tài khoản (trong quản trị).
$kh_loaded_locale = get_option( 'WPLANG' );
if ( ( is_admin() || wp_doing_ajax() ) && get_current_user_id() ) {
	$kh_user_locale = get_user_meta( get_current_user_id(), 'locale', true );
	if ( $kh_user_locale ) {
		$kh_loaded_locale = $kh_user_locale;
	}
}
if ( get_option( 'WPLANG' ) !== KH_LOCALE && ! wp_installing() ) {
	update_option( 'WPLANG', KH_LOCALE );
}
if ( KH_LOCALE !== $kh_loaded_locale ) {
	load_default_textdomain( KH_LOCALE );
	$GLOBALS['wp_locale'] = new WP_Locale();
}

/* ==========================================================================
 * 2. Tự tải bản dịch tiếng Việt (WordPress, WooCommerce và các plugin khác)
 * ======================================================================= */

/**
 * Tải gói ngôn ngữ còn thiếu. Chạy khi admin mở trang quản trị,
 * tối đa 1 lần / 12 giờ để không làm chậm trang.
 *
 * @param bool $force Bỏ qua giới hạn 12 giờ.
 * @return array{ok:bool,msg:string}|null
 */
function kh_vi_install_translations( $force = false ) {
	if ( ! current_user_can( 'install_languages' ) && ! current_user_can( 'update_core' ) ) {
		return null;
	}
	if ( ! $force && get_transient( 'kh_vi_translations_checked' ) ) {
		return null;
	}
	set_transient( 'kh_vi_translations_checked', 1, 12 * HOUR_IN_SECONDS );

	require_once ABSPATH . 'wp-admin/includes/translation-install.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	if ( get_option( 'WPLANG' ) !== KH_LOCALE ) {
		update_option( 'WPLANG', KH_LOCALE );
	}

	if ( ! wp_can_install_language_pack() ) {
		return array( 'ok' => false, 'msg' => 'Hosting không cho phép tự tải bản dịch. Vào Bảng tin → Cập nhật và bấm "Cập nhật bản dịch".' );
	}

	// Bản dịch của WordPress.
	if ( ! in_array( KH_LOCALE, get_available_languages(), true ) ) {
		if ( ! wp_download_language_pack( KH_LOCALE ) ) {
			return array( 'ok' => false, 'msg' => 'Chưa tải được bản dịch tiếng Việt của WordPress. Thử lại sau hoặc vào Bảng tin → Cập nhật.' );
		}
	}

	// Bản dịch của plugin / giao diện (WooCommerce…).
	wp_clean_update_cache();
	wp_update_plugins();
	wp_update_themes();
	$updates = wp_get_translation_updates();
	if ( $updates ) {
		$upgrader = new Language_Pack_Upgrader( new Automatic_Upgrader_Skin() );
		$upgrader->bulk_upgrade( $updates );
	}
	return array( 'ok' => true, 'msg' => 'Đã cập nhật bản dịch tiếng Việt cho WordPress và các plugin.' );
}

add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		$force = isset( $_GET['kh_vi_update'] ) && check_admin_referer( 'kh_vi_update' );
		$res   = kh_vi_install_translations( $force );
		if ( $res ) {
			set_transient( 'kh_vi_notice_' . get_current_user_id(), $res, 5 * MINUTE_IN_SECONDS );
		}
		if ( $force ) {
			wp_safe_redirect( remove_query_arg( array( 'kh_vi_update', '_wpnonce' ) ) );
			exit;
		}
	}
);

add_action(
	'admin_notices',
	function () {
		$key = 'kh_vi_notice_' . get_current_user_id();
		$res = get_transient( $key );
		if ( ! $res ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			$res['ok'] ? 'success' : 'warning',
			esc_html( $res['msg'] )
		);
	}
);

/**
 * Link "Tải lại bản dịch tiếng Việt" (dùng ở trang Cài đặt website).
 *
 * @return string
 */
function kh_vi_update_url() {
	return wp_nonce_url( add_query_arg( 'kh_vi_update', 1, admin_url( 'admin.php?page=kh-settings' ) ), 'kh_vi_update' );
}

/* ==========================================================================
 * 3. Định dạng Việt Nam (cài 1 lần, sau đó admin đổi trong Cài đặt vẫn giữ)
 * ======================================================================= */

add_action(
	'admin_init',
	function () {
		$done = (int) get_option( 'kh_vi_setup' );
		if ( $done >= 3 || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( $done < 1 ) {
			update_option( 'date_format', 'd/m/Y' );
			update_option( 'time_format', 'H:i' );
			update_option( 'start_of_week', 1 );
			if ( ! get_option( 'timezone_string' ) && 0 === (int) get_option( 'gmt_offset' ) ) {
				update_option( 'timezone_string', 'Asia/Ho_Chi_Minh' );
			}
			if ( in_array( get_option( 'woocommerce_default_country', '' ), array( '', 'US:CA' ), true ) ) {
				update_option( 'woocommerce_default_country', 'VN' );
			}
		}
		// Danh mục mặc định "Uncategorized" → "Chưa phân loại".
		foreach ( array( 'category', 'product_cat' ) as $tax ) {
			$term = taxonomy_exists( $tax ) ? get_term_by( 'name', 'Uncategorized', $tax ) : false;
			if ( $term ) {
				wp_update_term( $term->term_id, $tax, array( 'name' => 'Chưa phân loại' ) );
			}
		}
		// Trang mặc định do WordPress / WooCommerce tạo bằng tiếng Anh (chỉ đổi tên, giữ nguyên link).
		// Chỉ đổi những trang đang được dùng, không đụng trang thừa.
		$pages = array(
			'woocommerce_cart_page_id'      => array( 'Cart', 'Giỏ hàng' ),
			'woocommerce_checkout_page_id'  => array( 'Checkout', 'Thanh toán' ),
			'woocommerce_shop_page_id'      => array( 'Shop', 'Cửa hàng' ),
			'woocommerce_myaccount_page_id' => array( 'My account', 'Tài khoản' ),
			'wp_page_for_privacy_policy'    => array( 'Privacy Policy', 'Chính sách bảo mật' ),
			'woocommerce_refund_returns_page_id' => array( 'Refund and Returns Policy', 'Chính sách hoàn tiền' ),
		);
		foreach ( $pages as $opt => $names ) {
			$pid = (int) get_option( $opt );
			if ( $pid && 0 === strcasecmp( get_the_title( $pid ), $names[0] ) ) {
				wp_update_post( array( 'ID' => $pid, 'post_title' => $names[1] ) );
			}
		}
		update_option( 'kh_vi_setup', 3 );
	},
	5
);

/* ==========================================================================
 * 4. Chữ WooCommerce cho website khóa học
 * ======================================================================= */

/**
 * Chữ tiếng Anh → tiếng Việt (miền "woocommerce").
 *
 * @return array
 */
function kh_vi_wc_strings() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map = array(
		// Giỏ hàng.
		'Product'                                => 'Khóa học',
		'Products'                               => 'Khóa học',
		'Price'                                  => 'Học phí',
		'Quantity'                               => 'Số lượng',
		'Subtotal'                               => 'Tạm tính',
		'Subtotal:'                              => 'Tạm tính:',
		'Total'                                  => 'Tổng cộng',
		'Total:'                                 => 'Tổng cộng:',
		'Cart'                                   => 'Giỏ hàng',
		'Cart totals'                            => 'Tổng giỏ hàng',
		'View cart'                              => 'Xem giỏ hàng',
		'Update cart'                            => 'Cập nhật giỏ hàng',
		'Proceed to checkout'                    => 'Tiến hành thanh toán',
		'Remove this item'                       => 'Bỏ khóa học này',
		'Coupon:'                                => 'Mã giảm giá:',
		'Coupon code'                            => 'Mã giảm giá',
		'Apply coupon'                           => 'Áp dụng',
		'Have a coupon?'                         => 'Bạn có mã giảm giá?',
		'Click here to enter your code'          => 'Bấm vào đây để nhập mã',
		'If you have a coupon code, please apply it below.' => 'Nếu có mã giảm giá, bạn nhập vào ô bên dưới.',
		'Your cart is currently empty.'          => 'Giỏ hàng của bạn đang trống.',
		'Return to shop'                         => 'Xem các khóa học',
		'Browse products'                        => 'Xem các khóa học',
		'Cart updated.'                          => 'Đã cập nhật giỏ hàng.',
		'%s removed.'                            => 'Đã bỏ %s khỏi giỏ hàng.',
		'Undo?'                                  => 'Hoàn tác?',
		'%s has been added to your cart.'        => '%s đã được thêm vào giỏ hàng.',
		'%s have been added to your cart.'       => '%s đã được thêm vào giỏ hàng.',
		'Free!'                                  => 'Miễn phí',

		// Thanh toán.
		'Checkout'                               => 'Thanh toán',
		'Billing details'                        => 'Thông tin học viên',
		'Billing &amp; Shipping'                 => 'Thông tin học viên',
		'Additional information'                 => 'Ghi chú thêm',
		'Order notes'                            => 'Ghi chú',
		'Notes about your order, e.g. special notes for delivery.' => 'Ghi chú cho đơn hàng (nếu có).',
		'Your order'                             => 'Đơn hàng của bạn',
		'Place order'                            => 'Đặt mua khóa học',
		'First name'                             => 'Tên',
		'Last name'                              => 'Họ',
		'Phone'                                  => 'Số điện thoại',
		'Email address'                          => 'Địa chỉ email',
		'Country / Region'                       => 'Quốc gia',
		'Street address'                         => 'Địa chỉ',
		'Town / City'                            => 'Tỉnh / Thành phố',
		'(optional)'                             => '(không bắt buộc)',
		'optional'                               => 'không bắt buộc',
		'required'                               => 'bắt buộc',
		'Returning customer?'                    => 'Bạn đã có tài khoản?',
		'Click here to login'                    => 'Bấm vào đây để đăng nhập',
		'Create an account?'                     => 'Tạo tài khoản?',
		'Create account password'                => 'Tạo mật khẩu',
		'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.' => 'Hiện chưa có phương thức thanh toán. Vui lòng liên hệ với chúng tôi để được hỗ trợ.',
		'I have read and agree to the website %s' => 'Tôi đã đọc và đồng ý với %s của website',
		'terms and conditions'                   => 'điều khoản',
		'Direct bank transfer'                   => 'Chuyển khoản ngân hàng',
		'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.' => 'Chuyển khoản trực tiếp vào tài khoản ngân hàng của chúng tôi, nội dung chuyển khoản ghi mã đơn hàng. Khóa học sẽ được mở ngay khi chúng tôi nhận được học phí.',
		'Cash on delivery'                       => 'Thanh toán khi nhận hàng',
		'Pay with cash upon delivery.'           => 'Trả tiền mặt khi nhận hàng.',
		'Check payments'                         => 'Thanh toán bằng séc',

		// Sau khi đặt hàng.
		'Thank you. Your order has been received.' => 'Cảm ơn bạn! Đơn hàng đã được ghi nhận.',
		'Order received'                         => 'Đã nhận đơn hàng',
		'Order number:'                          => 'Mã đơn hàng:',
		'Date:'                                  => 'Ngày:',
		'Email:'                                 => 'Email:',
		'Payment method:'                        => 'Phương thức thanh toán:',
		'Order details'                          => 'Chi tiết đơn hàng',
		'Our bank details'                       => 'Thông tin chuyển khoản',
		'Bank'                                   => 'Ngân hàng',
		'Account number'                         => 'Số tài khoản',
		'Account name'                           => 'Chủ tài khoản',
		'Note:'                                  => 'Ghi chú:',
		'Order again'                            => 'Mua lại',

		// Tài khoản học viên.
		'My account'                             => 'Tài khoản',
		'Dashboard'                              => 'Tổng quan',
		'Orders'                                 => 'Đơn hàng',
		'Addresses'                              => 'Thông tin thanh toán',
		'Address'                                => 'Thông tin thanh toán',
		'Account details'                        => 'Thông tin tài khoản',
		'Payment methods'                        => 'Phương thức thanh toán',
		'Log out'                                => 'Đăng xuất',
		'Hello %1$s (not %1$s? <a href="%2$s">Log out</a>)' => 'Xin chào %1$s (không phải %1$s? <a href="%2$s">Đăng xuất</a>)',
		'Hello %1$s (not %2$s? <a href="%3$s">Log out</a>)' => 'Xin chào %1$s (không phải %2$s? <a href="%3$s">Đăng xuất</a>)',
		'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">billing address</a>, and <a href="%3$s">edit your password and account details</a>.' => 'Tại đây bạn có thể xem <a href="%1$s">đơn hàng</a>, cập nhật <a href="%2$s">thông tin thanh toán</a> và <a href="%3$s">đổi mật khẩu, thông tin tài khoản</a>.',
		'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a>, and <a href="%3$s">edit your password and account details</a>.' => 'Tại đây bạn có thể xem <a href="%1$s">đơn hàng</a>, cập nhật <a href="%2$s">thông tin thanh toán</a> và <a href="%3$s">đổi mật khẩu, thông tin tài khoản</a>.',
		'Order'                                  => 'Đơn hàng',
		'Date'                                   => 'Ngày',
		'Status'                                 => 'Trạng thái',
		'Actions'                                => 'Thao tác',
		'View'                                   => 'Xem',
		'Pay'                                    => 'Thanh toán',
		'Cancel'                                 => 'Hủy',
		'Previous'                               => 'Trang trước',
		'Next'                                   => 'Trang sau',
		'No order has been made yet.'            => 'Bạn chưa có đơn hàng nào.',
		'%1$s for %2$s item'                     => '%1$s cho %2$s khóa học',
		'%1$s for %2$s items'                    => '%1$s cho %2$s khóa học',
		'Order #%1$s was placed on %2$s and is currently %3$s.' => 'Đơn hàng #%1$s đặt ngày %2$s, hiện đang ở trạng thái: %3$s.',
		'Order updates'                          => 'Cập nhật đơn hàng',
		'Billing address'                        => 'Thông tin thanh toán',
		'Shipping address'                       => 'Địa chỉ giao hàng',
		'The following addresses will be used on the checkout page by default.' => 'Thông tin dưới đây sẽ được điền sẵn khi bạn thanh toán.',
		'Edit'                                   => 'Sửa',
		'Add'                                    => 'Thêm',
		'Save address'                           => 'Lưu thông tin',
		'You have not set up this type of address yet.' => 'Bạn chưa nhập thông tin này.',
		'Address changed successfully.'          => 'Đã lưu thông tin.',
		'Display name'                           => 'Tên hiển thị',
		'This will be how your name will be displayed in the account section and in reviews' => 'Tên này sẽ hiện trong tài khoản và phần đánh giá',
		'Password change'                        => 'Đổi mật khẩu',
		'Current password (leave blank to leave unchanged)' => 'Mật khẩu hiện tại (để trống nếu không đổi)',
		'New password (leave blank to leave unchanged)'     => 'Mật khẩu mới (để trống nếu không đổi)',
		'Confirm new password'                   => 'Nhập lại mật khẩu mới',
		'Save changes'                           => 'Lưu thay đổi',
		'Account details changed successfully.'  => 'Đã lưu thông tin tài khoản.',

		// Đăng nhập / đăng ký.
		'Login'                                  => 'Đăng nhập',
		'Log in'                                 => 'Đăng nhập',
		'Register'                               => 'Đăng ký',
		'Username or email address'              => 'Email hoặc tên đăng nhập',
		'Username'                               => 'Tên đăng nhập',
		'Password'                               => 'Mật khẩu',
		'Remember me'                            => 'Ghi nhớ đăng nhập',
		'Lost your password?'                    => 'Quên mật khẩu?',
		'Lost your password? Please enter your username or email address. You will receive a link to create a new password via email.' => 'Quên mật khẩu? Nhập email hoặc tên đăng nhập, chúng tôi sẽ gửi link tạo mật khẩu mới vào email của bạn.',
		'Reset password'                         => 'Đặt lại mật khẩu',
		'Enter a new password below.'            => 'Nhập mật khẩu mới bên dưới.',
		'New password'                           => 'Mật khẩu mới',
		'Re-enter new password'                  => 'Nhập lại mật khẩu mới',
		'Password reset email has been sent.'    => 'Đã gửi email đặt lại mật khẩu.',
		'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.' => 'Email đặt lại mật khẩu đã được gửi tới hộp thư của bạn, có thể mất vài phút mới nhận được. Vui lòng đợi ít nhất 10 phút trước khi thử lại.',
		'Your password has been reset successfully.' => 'Đã đổi mật khẩu thành công.',
		'A link to set a new password will be sent to your email address.' => 'Link tạo mật khẩu sẽ được gửi vào email của bạn.',
		'Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our %s.' => 'Thông tin của bạn được dùng để quản lý tài khoản, hỗ trợ việc học trên website và các mục đích khác được nêu trong %s.',
		'privacy policy'                         => 'chính sách bảo mật',
		'Strong'                                 => 'Mạnh',
		'Medium'                                 => 'Trung bình',
		'Weak - Please enter a stronger password.' => 'Yếu – vui lòng đặt mật khẩu mạnh hơn.',
		'Very weak - Please enter a stronger password.' => 'Rất yếu – vui lòng đặt mật khẩu mạnh hơn.',
		'Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).' => 'Gợi ý: mật khẩu nên dài ít nhất 12 ký tự, có chữ hoa, chữ thường, số và ký hiệu như ! " ? $ % ^ &amp; ).',

		// Khung "Trạng thái WooCommerce" ở trang quản trị.
		'Net sales this month %s'                => 'Doanh thu tháng này %s',
		'Top seller this month %1$s (%2$d sale)' => 'Bán chạy nhất tháng này %1$s (%2$d lượt bán)',
		'Top seller this month %1$s (%2$d sales)' => 'Bán chạy nhất tháng này %1$s (%2$d lượt bán)',
		'Awaiting processing <strong>%s order</strong>'  => 'Chờ xử lý <strong>%s đơn hàng</strong>',
		'Awaiting processing <strong>%s orders</strong>' => 'Chờ xử lý <strong>%s đơn hàng</strong>',
		'On-hold <strong>%s order</strong>'      => 'Tạm giữ <strong>%s đơn hàng</strong>',
		'On-hold <strong>%s orders</strong>'     => 'Tạm giữ <strong>%s đơn hàng</strong>',
		'Low in stock <strong>%s product</strong>'   => 'Sắp hết hàng <strong>%s sản phẩm</strong>',
		'Low in stock <strong>%s products</strong>'  => 'Sắp hết hàng <strong>%s sản phẩm</strong>',
		'Out of stock <strong>%s product</strong>'   => 'Hết hàng <strong>%s sản phẩm</strong>',
		'Out of stock <strong>%s products</strong>'  => 'Hết hàng <strong>%s sản phẩm</strong>',

		// Thông báo lỗi thường gặp.
		'%s is a required field.'                => '%s là thông tin bắt buộc.',
		'<strong>Error:</strong> Please provide a valid email address.' => '<strong>Lỗi:</strong> Email chưa đúng.',
		'Please provide a valid email address.'  => 'Email chưa đúng.',
		'Please enter a valid email address.'    => 'Email chưa đúng.',
		'An account is already registered with your email address. <a href="#" class="showlogin">Please log in.</a>' => 'Email này đã có tài khoản. <a href="#" class="showlogin">Vui lòng đăng nhập.</a>',
		'An account is already registered with your email address. <a href="%s" class="showlogin">Please log in.</a>' => 'Email này đã có tài khoản. <a href="%s" class="showlogin">Vui lòng đăng nhập.</a>',
		'Unknown email address. Check again or try your username.' => 'Không tìm thấy email này. Kiểm tra lại hoặc thử tên đăng nhập.',
		'Invalid username or email.'             => 'Sai tên đăng nhập hoặc email.',
		'Please enter a valid account username.' => 'Vui lòng nhập tên đăng nhập.',
		'Please enter an account password.'      => 'Vui lòng nhập mật khẩu.',
		'Passwords do not match.'                => 'Hai mật khẩu không khớp.',
		'Your current password is incorrect.'    => 'Mật khẩu hiện tại chưa đúng.',
		'Please fill out all password fields.'   => 'Vui lòng nhập đủ các ô mật khẩu.',
		'Please enter your current password.'    => 'Vui lòng nhập mật khẩu hiện tại.',
		'Please re-enter your password.'         => 'Vui lòng nhập lại mật khẩu.',
		'New passwords do not match.'            => 'Hai mật khẩu mới không khớp.',
	);
	return $map;
}

/**
 * Đang ở trang quản trị (kể cả AJAX gọi từ trang quản trị)?
 *
 * @return bool
 */
function kh_vi_in_admin() {
	if ( ! is_admin() ) {
		return false;
	}
	if ( ! wp_doing_ajax() ) {
		return true;
	}
	$ref = (string) wp_get_raw_referer();
	return false !== strpos( $ref, '/wp-admin/' );
}

/**
 * Trang web (học viên): luôn dùng chữ của theme.
 * Trang quản trị: chỉ thay chữ mà bản dịch chính thức chưa có.
 *
 * @param string $translation Bản dịch hiện tại.
 * @param string $text        Chữ gốc.
 * @return string
 */
function kh_vi_pick( $translation, $text ) {
	$map = kh_vi_wc_strings();
	if ( ! isset( $map[ $text ] ) ) {
		return $translation;
	}
	if ( kh_vi_in_admin() && $translation !== $text ) {
		return $translation;
	}
	return $map[ $text ];
}

add_filter(
	'gettext_woocommerce',
	function ( $translation, $text ) {
		return kh_vi_pick( $translation, $text );
	},
	20,
	2
);
add_filter(
	'ngettext_woocommerce',
	function ( $translation, $single, $plural, $number ) {
		return kh_vi_pick( $translation, 1 === (int) $number ? $single : $plural );
	},
	20,
	4
);

// Chữ có ngữ cảnh (_x): họ tên kiểu Việt luôn áp dụng; còn lại chỉ thay khi chưa có bản dịch.
add_filter(
	'gettext_with_context_woocommerce',
	function ( $translation, $text, $context ) {
		if ( 'full name' === $context && '%1$s %2$s' === $text ) {
			return '%2$s %1$s'; // Họ (last) trước, Tên (first) sau.
		}
		static $map = array(
			'Order status' => array( 'Status' => 'Trạng thái' ),
		);
		if ( $translation === $text && isset( $map[ $context ][ $text ] ) ) {
			return $map[ $context ][ $text ];
		}
		return $translation;
	},
	20,
	3
);

// Trạng thái đơn hàng (cả trong quản trị và trang học viên).
add_filter(
	'wc_order_statuses',
	function ( $statuses ) {
		$vi = array(
			'wc-pending'        => 'Chờ thanh toán',
			'wc-processing'     => 'Đang xử lý',
			'wc-on-hold'        => 'Tạm giữ',
			'wc-completed'      => 'Hoàn thành',
			'wc-cancelled'      => 'Đã hủy',
			'wc-refunded'       => 'Đã hoàn tiền',
			'wc-failed'         => 'Thất bại',
			'wc-checkout-draft' => 'Bản nháp',
		);
		foreach ( $vi as $key => $label ) {
			if ( isset( $statuses[ $key ] ) ) {
				$statuses[ $key ] = $label;
			}
		}
		return $statuses;
	},
	20
);

// Menu trang Tài khoản.
add_filter(
	'woocommerce_account_menu_items',
	function ( $items ) {
		$vi = array(
			'dashboard'       => 'Tổng quan',
			'orders'          => 'Đơn hàng',
			'edit-address'    => 'Thông tin thanh toán',
			'payment-methods' => 'Phương thức thanh toán',
			'edit-account'    => 'Thông tin tài khoản',
			'customer-logout' => 'Đăng xuất',
		);
		foreach ( $vi as $key => $label ) {
			if ( isset( $items[ $key ] ) ) {
				$items[ $key ] = $label;
			}
		}
		return $items;
	},
	20
);

// Tiêu đề từng mục trong trang Tài khoản.
foreach (
	array(
		'orders'          => 'Đơn hàng',
		'view-order'      => 'Chi tiết đơn hàng',
		'edit-address'    => 'Thông tin thanh toán',
		'payment-methods' => 'Phương thức thanh toán',
		'edit-account'    => 'Thông tin tài khoản',
		'lost-password'   => 'Quên mật khẩu',
	) as $kh_endpoint => $kh_title
) {
	add_filter(
		'woocommerce_endpoint_' . $kh_endpoint . '_title',
		function ( $title ) use ( $kh_endpoint, $kh_title ) {
			if ( 'view-order' === $kh_endpoint ) {
				global $wp;
				$order = isset( $wp->query_vars['view-order'] ) ? wc_get_order( absint( $wp->query_vars['view-order'] ) ) : null;
				return $order ? sprintf( 'Đơn hàng #%s', $order->get_order_number() ) : $kh_title;
			}
			return $kh_title;
		},
		20
	);
}

// Tên phương thức thanh toán được lưu sẵn bằng tiếng Anh trong cài đặt.
add_filter(
	'woocommerce_gateway_title',
	function ( $title ) {
		$map = kh_vi_wc_strings();
		return isset( $map[ $title ] ) ? $map[ $title ] : $title;
	},
	20
);
add_filter(
	'woocommerce_gateway_description',
	function ( $desc ) {
		$map = kh_vi_wc_strings();
		return isset( $map[ $desc ] ) ? $map[ $desc ] : $desc;
	},
	20
);

// Nút "Xem các khóa học" (giỏ trống, chưa có đơn) dẫn về trang khóa học.
add_filter(
	'woocommerce_return_to_shop_redirect',
	function ( $url ) {
		$courses = get_post_type_archive_link( 'khoa_hoc' );
		return $courses ? $courses : $url;
	}
);

/* ==========================================================================
 * 5. Giỏ hàng / thanh toán dạng khối (chữ do JavaScript vẽ)
 * ======================================================================= */

/**
 * Chữ riêng của giỏ hàng / thanh toán dạng khối (cách viết hoa khác bản cổ điển).
 *
 * @return array
 */
function kh_vi_block_strings() {
	return array(
		'Place Order'                 => 'Đặt mua khóa học',
		'Place order'                 => 'Đặt mua khóa học',
		'Proceed to Checkout'         => 'Tiến hành thanh toán',
		'Return to Cart'              => 'Quay lại giỏ hàng',
		'Contact information'         => 'Thông tin liên hệ',
		'Billing address'             => 'Thông tin học viên',
		'Order summary'               => 'Tóm tắt đơn hàng',
		'Estimated total'             => 'Tổng cộng',
		'Add coupons'                 => 'Thêm mã giảm giá',
		'Add a coupon'                => 'Thêm mã giảm giá',
		'Enter code'                  => 'Nhập mã',
		'Apply'                       => 'Áp dụng',
		'Add a note to your order'    => 'Thêm ghi chú cho đơn hàng',
		'Payment options'             => 'Phương thức thanh toán',
		'Remove item'                 => 'Bỏ khóa học này',
		'Your cart is currently empty!' => 'Giỏ hàng của bạn đang trống!',
		'Details'                     => 'Chi tiết',
		'Total'                       => 'Tổng cộng',
		'Subtotal'                    => 'Tạm tính',
		'Product'                     => 'Khóa học',
		'Products'                    => 'Khóa học',
		'Cart totals'                 => 'Tổng giỏ hàng',
	);
}

// Gắn bộ lọc vào wp.i18n để thay chữ ngay trong JavaScript (chỉ ngoài trang web).
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() || is_account_page() ) ) {
			return;
		}
		$map = array_merge( kh_vi_wc_strings(), kh_vi_block_strings() );
		wp_enqueue_script( 'wp-hooks' );
		wp_enqueue_script( 'wp-i18n' );
		wp_add_inline_script(
			'wp-i18n',
			'(function(){var m=' . wp_json_encode( $map, JSON_UNESCAPED_UNICODE ) . ';' .
			'function t(r,s){return Object.prototype.hasOwnProperty.call(m,s)?m[s]:r;}' .
			'wp.hooks.addFilter("i18n.gettext_woocommerce","kh/vi",function(r,s){return t(r,s);});' .
			'wp.hooks.addFilter("i18n.ngettext_woocommerce","kh/vi",function(r,s,p,n){return t(r,n===1?s:p);});' .
			'})();'
		);
	},
	1
);

/* ==========================================================================
 * 6. Chữ của WordPress mà bản dịch chính thức còn thiếu
 * ======================================================================= */

add_filter(
	'gettext_default',
	function ( $translation, $text ) {
		static $map = array(
			'Connection lost.'                                   => 'Mất kết nối.',
			'Saving has been disabled until you are reconnected.' => 'Tạm dừng lưu cho tới khi kết nối lại.',
			'Disable infinite scrolling in the Media Library grid view' => 'Tắt cuộn tải thêm liên tục trong Thư viện (dạng lưới)',
			'Uncategorized'                                      => 'Chưa phân loại',
		);
		return ( $translation === $text && isset( $map[ $text ] ) ) ? $map[ $text ] : $translation;
	},
	20,
	2
);

/* ==========================================================================
 * 7. Thông tin học viên kiểu Việt Nam
 * ======================================================================= */

// Đoạn chính sách bảo mật WooCommerce lưu sẵn tiếng Anh trong cài đặt.
add_filter(
	'woocommerce_get_privacy_policy_text',
	function ( $text, $type ) {
		if ( false === strpos( (string) $text, 'Your personal data will be used' ) ) {
			return $text;
		}
		return 'checkout' === $type
			? 'Thông tin của bạn được dùng để xử lý đơn hàng, hỗ trợ việc học trên website và các mục đích khác được nêu trong [privacy_policy].'
			: 'Thông tin của bạn được dùng để quản lý tài khoản, hỗ trợ việc học trên website và các mục đích khác được nêu trong [privacy_policy].';
	},
	20,
	2
);

// Tên đầy đủ: Họ trước, Tên sau ("Nguyễn Văn Học", không phải "Học Nguyễn Văn").
add_filter(
	'woocommerce_formatted_address_replacements',
	function ( $rep, $args ) {
		$name = trim( ( isset( $args['last_name'] ) ? $args['last_name'] : '' ) . ' ' . ( isset( $args['first_name'] ) ? $args['first_name'] : '' ) );
		$rep['{name}']       = $name;
		$rep['{name_upper}'] = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $name ) : strtoupper( $name );
		return $rep;
	},
	20,
	2
);

// Ô "Họ" đứng trước ô "Tên".
add_filter(
	'woocommerce_default_address_fields',
	function ( $fields ) {
		if ( isset( $fields['last_name'], $fields['first_name'] ) ) {
			$fields['last_name']['priority']  = 10;
			$fields['first_name']['priority'] = 20;
			$fields['last_name']['class']     = array( 'form-row-first' );
			$fields['first_name']['class']    = array( 'form-row-last' );
		}
		return $fields;
	},
	30
);

// Trang thanh toán dạng khối: thứ tự ô lấy theo "country locale".
add_filter(
	'woocommerce_get_country_locale_default',
	function ( $locale ) {
		$locale['last_name']['priority']  = 10;
		$locale['first_name']['priority'] = 20;
		return $locale;
	},
	20
);
add_filter(
	'woocommerce_get_country_locale',
	function ( $locale ) {
		foreach ( $locale as $country => $fields ) {
			$locale[ $country ]['last_name']['priority']  = 10;
			$locale[ $country ]['first_name']['priority'] = 20;
		}
		return $locale;
	},
	30
);

// Khóa học online: trang Tài khoản chỉ có "Thông tin thanh toán", không có địa chỉ giao hàng.
add_filter(
	'woocommerce_my_account_get_addresses',
	function ( $addresses ) {
		return array( 'billing' => 'Thông tin thanh toán' );
	},
	20
);

// Form sửa thông tin thanh toán trong trang Tài khoản: chỉ giữ họ tên, điện thoại, email.
add_filter(
	'woocommerce_billing_fields',
	function ( $fields ) {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-address' ) ) {
			return $fields;
		}
		foreach ( array( 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode' ) as $k ) {
			unset( $fields[ $k ] );
		}
		// WooCommerce cần "Quốc gia" khi lưu: giữ lại nhưng ẩn (mặc định Việt Nam).
		if ( isset( $fields['billing_country'] ) ) {
			$fields['billing_country'] = array(
				'type'     => 'hidden',
				'label'    => '',
				'required' => false,
				'default'  => 'VN',
				'priority' => 1,
			);
		}
		return $fields;
	},
	20
);
