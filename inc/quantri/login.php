<?php
/**
 * Quản trị → Đăng nhập / Quên mật khẩu (trang riêng, cùng giao diện quản trị).
 *
 *  /quan-tri/dang-nhap/      Đăng nhập bằng email hoặc tên đăng nhập.
 *  /quan-tri/quen-mat-khau/  Gửi email đặt lại mật khẩu.
 *
 * Sai mật khẩu 5 lần trong 15 phút (theo địa chỉ IP) → tạm khóa 15 phút.
 * Trang wp-login.php của WordPress vẫn dùng được như cũ.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

define( 'KH_QT_LOGIN_TRIES', 5 );
define( 'KH_QT_LOGIN_WINDOW', 15 * MINUTE_IN_SECONDS );

/**
 * Khóa đếm số lần sai theo IP.
 *
 * @return string
 */
function kh_qt_login_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return 'kh_qt_fail_' . md5( $ip );
}

/**
 * Link quay lại sau khi đăng nhập: chỉ chấp nhận trang trong /quan-tri/.
 *
 * @return string
 */
function kh_qt_login_back() {
	$raw  = isset( $_REQUEST['quay-lai'] ) ? esc_url_raw( wp_unslash( $_REQUEST['quay-lai'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$base = kh_qt_url();
	return $raw && 0 === strpos( $raw, $base ) ? $raw : $base;
}

/**
 * Trang đăng nhập / quên mật khẩu.
 *
 * @param string $section dang-nhap | quen-mat-khau.
 */
function kh_qt_auth_page( $section ) {
	// Đã đăng nhập.
	if ( is_user_logged_in() ) {
		wp_safe_redirect( kh_is_staff() ? kh_qt_login_back() : home_url( '/' ) );
		exit;
	}

	$msg   = '';
	$ok    = false;
	$login = '';

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$login = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		if ( ! isset( $_POST['_kh_qt'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_kh_qt'] ), 'kh_qt_' . $section ) ) {
			$msg = 'Phiên làm việc đã hết hạn, bạn thử lại nhé.';
		} elseif ( 'dang-nhap' === $section ) {
			$fails = (int) get_transient( kh_qt_login_key() );
			if ( $fails >= KH_QT_LOGIN_TRIES ) {
				$msg = 'Bạn đã nhập sai nhiều lần. Vui lòng thử lại sau 15 phút hoặc bấm "Quên mật khẩu".';
			} else {
				$user = wp_signon(
					array(
						'user_login'    => $login,
						'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
						'remember'      => ! empty( $_POST['rememberme'] ),
					),
					is_ssl()
				);
				if ( is_wp_error( $user ) ) {
					set_transient( kh_qt_login_key(), $fails + 1, KH_QT_LOGIN_WINDOW );
					$left = KH_QT_LOGIN_TRIES - $fails - 1;
					$msg  = '' === $login || empty( $_POST['pwd'] )
						? 'Vui lòng nhập email (hoặc tên đăng nhập) và mật khẩu.'
						: 'Email/tên đăng nhập hoặc mật khẩu chưa đúng.' . ( $left > 0 && $left < 3 ? ' Còn ' . $left . ' lần thử.' : '' );
				} else {
					delete_transient( kh_qt_login_key() );
					// Học viên đăng nhập nhầm vào đây: đưa về website.
					wp_safe_redirect( kh_is_staff( $user ) ? kh_qt_login_back() : home_url( '/' ) );
					exit;
				}
			}
		} else {
			// Quên mật khẩu: luôn báo giống nhau để không lộ email nào có tài khoản.
			if ( '' !== $login ) {
				$key = 'kh_qt_lost_' . md5( strtolower( $login ) );
				if ( ! get_transient( $key ) ) {
					set_transient( $key, 1, 2 * MINUTE_IN_SECONDS );
					retrieve_password( $login );
				}
				$ok  = true;
				$msg = 'Nếu thông tin bạn nhập có tài khoản, chúng tôi đã gửi email kèm link đặt lại mật khẩu. Kiểm tra cả hộp thư Spam/Quảng cáo nhé.';
			} else {
				$msg = 'Vui lòng nhập email hoặc tên đăng nhập.';
			}
		}
	} elseif ( isset( $_GET['da-dang-xuat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$ok  = true;
		$msg = 'Bạn đã đăng xuất.';
	}

	$back = kh_qt_login_back();
	$hid  = '<input type="hidden" name="quay-lai" value="' . esc_attr( $back ) . '">' . wp_nonce_field( 'kh_qt_' . $section, '_kh_qt', false, false );
	$note = $msg ? '<div class="qt-flash ' . ( $ok ? 'is-ok' : 'is-err' ) . '" role="' . ( $ok ? 'status' : 'alert' ) . '">' . kh_qt_icon( $ok ? 'circle-check' : 'alert-circle' ) . '<span>' . esc_html( $msg ) . '</span></div>' : '';

	if ( 'dang-nhap' === $section ) {
		$title = 'Đăng nhập';
		$form  = '<h1>Đăng nhập quản trị</h1><p class="qt-muted">Quản lý đơn hàng, học viên và khóa học.</p>' . $note;
		$form .= '<form method="post" class="qt-form" action="' . esc_url( kh_qt_url( 'dang-nhap' ) ) . '">' . $hid;
		$form .= kh_qt_field( 'log', 'Email hoặc tên đăng nhập', $login, array( 'required' => true, 'attrs' => 'autocomplete="username" autocapitalize="off" spellcheck="false"' . ( $login ? '' : ' autofocus' ) ) );
		$form .= '<div class="qt-field"><div class="qt-label-row"><label for="f-pwd">Mật khẩu</label><a href="' . esc_url( kh_qt_url( 'quen-mat-khau' ) ) . '">Quên mật khẩu?</a></div>';
		$form .= '<div class="qt-pass"><input type="password" id="f-pwd" name="pwd" required autocomplete="current-password"' . ( $login ? ' autofocus' : '' ) . '><button type="button" class="qt-icon-btn" data-qt-pass aria-label="Hiện mật khẩu" title="Hiện mật khẩu">' . kh_qt_icon( 'eye', 18 ) . '</button></div></div>';
		$form .= kh_qt_field( 'rememberme', 'Ghi nhớ đăng nhập trên máy này', true, array( 'type' => 'checkbox' ) );
		$form .= '<button class="qt-btn qt-btn-block" type="submit">' . kh_qt_icon( 'log-in', 18 ) . ' Đăng nhập</button>';
		$form .= '</form>';
	} else {
		$title = 'Quên mật khẩu';
		$form  = '<h1>Quên mật khẩu</h1><p class="qt-muted">Nhập email hoặc tên đăng nhập, chúng tôi sẽ gửi link để bạn đặt mật khẩu mới.</p>' . $note;
		if ( ! $ok ) {
			$form .= '<form method="post" class="qt-form" action="' . esc_url( kh_qt_url( 'quen-mat-khau' ) ) . '">' . $hid;
			$form .= kh_qt_field( 'log', 'Email hoặc tên đăng nhập', $login, array( 'required' => true, 'attrs' => 'autocomplete="username" autocapitalize="off" spellcheck="false" autofocus' ) );
			$form .= '<button class="qt-btn qt-btn-block" type="submit">' . kh_qt_icon( 'mail', 18 ) . ' Gửi link đặt lại mật khẩu</button>';
			$form .= '</form>';
		}
		$form .= '<p class="qt-auth-alt"><a href="' . esc_url( kh_qt_url( 'dang-nhap', '', array( 'quay-lai' => rawurlencode( $back ) ) ) ) . '">' . kh_qt_icon( 'arrow-left', 16 ) . ' Quay lại đăng nhập</a></p>';
	}

	kh_qt_render_auth( $title, $form );
}

/**
 * Khung trang đăng nhập (không có menu).
 *
 * @param string $title Tiêu đề.
 * @param string $form  Nội dung.
 */
function kh_qt_render_auth( $title, $form ) {
	show_admin_bar( false );
	$css   = KH_DIR . '/assets/css/quantri.css';
	$js    = KH_DIR . '/assets/js/quantri.js';
	$brand = kh_opt( 'brand_name' );
	$logo  = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'medium' ) : '';
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' · ' . $brand ); ?></title>
<style><?php echo kh_css_vars() . kh_font_faces(); // phpcs:ignore WordPress.Security.EscapeOutput ?></style>
<link rel="stylesheet" href="<?php echo esc_url( KH_URI . '/assets/css/quantri.css?v=' . ( file_exists( $css ) ? filemtime( $css ) : KH_VERSION ) ); ?>">
</head>
<body class="qt-body qt-auth-body">
<div class="qt-auth">
	<aside class="qt-auth-side">
		<a class="qt-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( $logo ) : ?>
				<img class="qt-logo-img" src="<?php echo esc_url( $logo ); ?>" alt="">
			<?php else : ?>
				<span class="qt-logo"><?php echo esc_html( kh_opt( 'brand_initial' ) ); ?></span>
			<?php endif; ?>
			<span><strong><?php echo esc_html( $brand ); ?></strong><small><?php echo esc_html( kh_opt( 'brand_tagline' ) ); ?></small></span>
		</a>
		<div class="qt-auth-pitch">
			<h2>Quản lý khóa học gọn trong một nơi</h2>
			<ul>
				<li><?php echo kh_qt_icon( 'check-circle-2', 18 ); // phpcs:ignore ?> Xác nhận đơn, khóa học tự mở cho học viên</li>
				<li><?php echo kh_qt_icon( 'check-circle-2', 18 ); // phpcs:ignore ?> Thêm học viên, gia hạn, cho xem lại bài</li>
				<li><?php echo kh_qt_icon( 'check-circle-2', 18 ); // phpcs:ignore ?> Sửa học phí, giáo trình ngay trên điện thoại</li>
			</ul>
		</div>
		<p class="qt-auth-foot"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo kh_qt_icon( 'arrow-left', 15 ); // phpcs:ignore ?> Về trang chủ website</a></p>
	</aside>
	<main class="qt-auth-main">
		<div class="qt-auth-card">
			<?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
	</main>
</div>
<script src="<?php echo esc_url( KH_URI . '/assets/js/quantri.js?v=' . ( file_exists( $js ) ? filemtime( $js ) : KH_VERSION ) ); ?>"></script>
</body>
</html>
	<?php
}
