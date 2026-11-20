<?php
/**
 * Trang quản trị riêng: http://ten-mien/quan-tri/
 *
 * Giao diện đơn giản cho người bán khóa học, không cần biết WordPress:
 * Tổng quan · Đơn hàng · Học viên · Khóa học · Liên hệ · Cài đặt.
 * Trang quản trị WordPress (wp-admin) vẫn còn cho việc nâng cao.
 *
 * Mỗi mục là 1 file trong inc/quantri/. Thao tác (lưu, xác nhận đơn…) gửi POST
 * về chính trang đó, kiểm tra nonce + quyền rồi chuyển hướng lại kèm thông báo.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

define( 'KH_QT_SLUG', 'quan-tri' );
define( 'KH_QT_VERSION', '1' );

/* ==========================================================================
 * Danh sách mục
 * ======================================================================= */

/**
 * Các mục của trang quản trị.
 *
 * @return array slug => [ label, icon, cap, file ]
 */
function kh_qt_sections() {
	$s = array(
		'tong-quan' => array( 'label' => 'Tổng quan', 'icon' => 'layout-dashboard', 'cap' => 'edit_posts', 'file' => 'dashboard' ),
		'don-hang'  => array( 'label' => 'Đơn hàng', 'icon' => 'receipt', 'cap' => 'edit_shop_orders', 'file' => 'orders' ),
		'hoc-vien'  => array( 'label' => 'Học viên', 'icon' => 'users', 'cap' => 'edit_users', 'file' => 'students' ),
		'khoa-hoc'  => array( 'label' => 'Khóa học', 'icon' => 'book-open', 'cap' => 'edit_posts', 'file' => 'courses' ),
		'lien-he'   => array( 'label' => 'Liên hệ', 'icon' => 'inbox', 'cap' => 'edit_posts', 'file' => 'contacts' ),
		'cai-dat'   => array( 'label' => 'Cài đặt', 'icon' => 'settings', 'cap' => 'manage_options', 'file' => 'settings' ),
	);
	if ( ! function_exists( 'wc_get_orders' ) ) {
		unset( $s['don-hang'] );
	}
	return $s;
}

foreach ( array( 'dashboard', 'orders', 'students', 'courses', 'contacts', 'settings' ) as $kh_qt_file ) {
	$kh_qt_path = KH_DIR . '/inc/quantri/' . $kh_qt_file . '.php';
	if ( is_readable( $kh_qt_path ) ) {
		require_once $kh_qt_path;
	}
}

/* ==========================================================================
 * Đường dẫn /quan-tri/<muc>/<id>/
 * ======================================================================= */

add_action(
	'init',
	function () {
		add_rewrite_rule( '^' . KH_QT_SLUG . '/?$', 'index.php?kh_qt=tong-quan', 'top' );
		add_rewrite_rule( '^' . KH_QT_SLUG . '/([a-z-]+)/?$', 'index.php?kh_qt=$matches[1]', 'top' );
		add_rewrite_rule( '^' . KH_QT_SLUG . '/([a-z-]+)/([a-z0-9-]+)/?$', 'index.php?kh_qt=$matches[1]&kh_qt_id=$matches[2]', 'top' );
		if ( get_option( 'kh_qt_rules' ) !== KH_QT_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'kh_qt_rules', KH_QT_VERSION );
		}
	}
);

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'kh_qt';
		$vars[] = 'kh_qt_id';
		return $vars;
	}
);

/**
 * Link trong trang quản trị.
 *
 * @param string     $section Mục (slug).
 * @param int|string $id      ID hoặc "moi".
 * @param array      $args    Tham số thêm.
 * @return string
 */
function kh_qt_url( $section = '', $id = '', $args = array() ) {
	$path = KH_QT_SLUG . '/';
	if ( $section && 'tong-quan' !== $section ) {
		$path .= $section . '/';
		if ( '' !== (string) $id && 0 !== $id ) {
			$path .= $id . '/';
		}
	}
	$url = home_url( '/' . $path );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/* ==========================================================================
 * Vào trang quản trị
 * ======================================================================= */

/**
 * Người đang đăng nhập có được vào mục này không.
 *
 * @param string $section Slug.
 * @return bool
 */
function kh_qt_can( $section ) {
	$s = kh_qt_sections();
	return isset( $s[ $section ] ) && current_user_can( $s[ $section ]['cap'] );
}

add_action(
	'template_redirect',
	function () {
		$section = get_query_var( 'kh_qt' );
		if ( ! $section ) {
			return;
		}
		nocache_headers();
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( kh_qt_url( $section, get_query_var( 'kh_qt_id' ) ) ) );
			exit;
		}
		if ( ! kh_is_staff() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		$sections = kh_qt_sections();
		if ( ! isset( $sections[ $section ] ) ) {
			wp_safe_redirect( kh_qt_url() );
			exit;
		}
		if ( ! kh_qt_can( $section ) ) {
			kh_qt_render( $section, '', 'kh_qt_page_denied' );
			exit;
		}

		// Thao tác gửi lên (lưu, xác nhận…).
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['kh_qt_do'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			kh_qt_handle_post( $section );
			exit;
		}

		$id = (string) get_query_var( 'kh_qt_id' );
		kh_qt_render( $section, $id, 'kh_qt_page_' . str_replace( '-', '_', $sections[ $section ]['file'] ) );
		exit;
	},
	0
);

/**
 * Xử lý thao tác: gọi kh_qt_do_<ten>( $post ) → [ 'ok' => bool, 'msg' => '', 'go' => url ].
 *
 * @param string $section Slug.
 */
function kh_qt_handle_post( $section ) {
	$do = sanitize_key( wp_unslash( $_POST['kh_qt_do'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! isset( $_POST['_kh_qt'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_kh_qt'] ), 'kh_qt_' . $do ) ) {
		kh_qt_flash( 'Phiên làm việc đã hết hạn, bạn thử lại nhé.', false );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : kh_qt_url( $section ) );
		return;
	}
	$fn  = 'kh_qt_do_' . $do;
	$res = function_exists( $fn ) ? call_user_func( $fn, wp_unslash( $_POST ) ) : array( 'ok' => false, 'msg' => 'Không rõ thao tác.' );
	if ( ! empty( $res['msg'] ) ) {
		kh_qt_flash( $res['msg'], ! empty( $res['ok'] ) );
	}
	$go = ! empty( $res['go'] ) ? $res['go'] : ( wp_get_referer() ? wp_get_referer() : kh_qt_url( $section ) );
	wp_safe_redirect( $go );
}

/**
 * Hidden fields cho 1 form thao tác.
 *
 * @param string $do Tên thao tác.
 * @return string
 */
function kh_qt_form_fields( $do ) {
	return '<input type="hidden" name="kh_qt_do" value="' . esc_attr( $do ) . '">' . wp_nonce_field( 'kh_qt_' . $do, '_kh_qt', false, false );
}

/**
 * Nút thao tác nhanh (1 form nhỏ).
 *
 * @param string $do      Thao tác.
 * @param array  $fields  Dữ liệu gửi kèm.
 * @param string $label   Chữ trên nút (đã escape HTML).
 * @param string $class   Lớp CSS của nút.
 * @param string $confirm Câu hỏi xác nhận (trống = không hỏi).
 * @return string
 */
function kh_qt_action_button( $do, $fields, $label, $class = 'qt-btn', $confirm = '' ) {
	$out = '<form method="post" class="qt-inline">' . kh_qt_form_fields( $do );
	foreach ( $fields as $k => $v ) {
		$out .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	$out .= '<button type="submit" class="' . esc_attr( $class ) . '"' . ( $confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . $label . '</button></form>';
	return $out;
}

/**
 * Lưu / lấy thông báo hiện 1 lần sau khi chuyển trang.
 *
 * @param string|null $msg Thông báo (null = lấy ra).
 * @param bool        $ok  Thành công?
 * @return array|null
 */
function kh_qt_flash( $msg = null, $ok = true ) {
	$key = 'kh_qt_flash_' . get_current_user_id();
	if ( null !== $msg ) {
		set_transient( $key, array( 'msg' => $msg, 'ok' => $ok ), 120 );
		return null;
	}
	$f = get_transient( $key );
	if ( $f ) {
		delete_transient( $key );
	}
	return $f ? $f : null;
}

/* ==========================================================================
 * Khung trang
 * ======================================================================= */

/**
 * Icon cho trang quản trị.
 *
 * @param string $name Tên.
 * @param int    $size Kích thước.
 * @return string
 */
function kh_qt_icon( $name, $size = 18 ) {
	return kh_icon( $name, 'qt-ico', $size );
}

/**
 * In cả trang: thanh bên + nội dung.
 *
 * @param string $section Slug.
 * @param string $id      ID trong URL.
 * @param string $fn      Hàm in nội dung, trả về [ title, html, actions ].
 */
function kh_qt_render( $section, $id, $fn ) {
	show_admin_bar( false );
	$page = function_exists( $fn ) ? call_user_func( $fn, $id ) : array( 'title' => 'Không tìm thấy', 'html' => '' );
	$page = wp_parse_args( $page, array( 'title' => '', 'html' => '', 'actions' => '', 'back' => '', 'media' => false ) );

	$css = KH_DIR . '/assets/css/quantri.css';
	$js  = KH_DIR . '/assets/js/quantri.js';
	wp_enqueue_style( 'kh-qt', KH_URI . '/assets/css/quantri.css', array(), KH_VERSION . '.' . ( file_exists( $css ) ? filemtime( $css ) : 0 ) );
	wp_enqueue_script( 'kh-qt', KH_URI . '/assets/js/quantri.js', array(), KH_VERSION . '.' . ( file_exists( $js ) ? filemtime( $js ) : 0 ), true );
	if ( $page['media'] && current_user_can( 'upload_files' ) ) {
		wp_enqueue_media();
	}

	$user     = wp_get_current_user();
	$brand    = kh_opt( 'brand_name' );
	$flash    = kh_qt_flash();
	$sections = kh_qt_sections();
	$badges   = kh_qt_badges();

	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( wp_strip_all_tags( $page['title'] ) . ' · Quản trị ' . $brand ); ?></title>
<style><?php echo kh_css_vars() . kh_font_faces(); // phpcs:ignore WordPress.Security.EscapeOutput ?></style>
	<?php
	wp_print_styles();
	wp_print_head_scripts();
	?>
</head>
<body class="qt-body qt-sec-<?php echo esc_attr( $section ); ?>">
<div class="qt-app">
	<aside class="qt-side" id="qt-side">
		<a class="qt-brand" href="<?php echo esc_url( kh_qt_url() ); ?>">
			<span class="qt-logo"><?php echo esc_html( kh_opt( 'brand_initial' ) ); ?></span>
			<span><strong><?php echo esc_html( $brand ); ?></strong><small>Quản trị</small></span>
		</a>
		<nav class="qt-nav" aria-label="Mục quản trị">
			<?php foreach ( $sections as $slug => $s ) : ?>
				<?php
				if ( ! current_user_can( $s['cap'] ) ) {
					continue;
				}
				?>
				<a href="<?php echo esc_url( kh_qt_url( $slug ) ); ?>" class="<?php echo $slug === $section ? 'is-active' : ''; ?>"<?php echo $slug === $section ? ' aria-current="page"' : ''; ?>>
					<?php echo kh_qt_icon( $s['icon'], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span><?php echo esc_html( $s['label'] ); ?></span>
					<?php if ( ! empty( $badges[ $slug ] ) ) : ?>
						<em class="qt-count"><?php echo (int) $badges[ $slug ]; ?></em>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="qt-side-foot">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php echo kh_qt_icon( 'globe' ); // phpcs:ignore ?> Xem website</a>
			<a href="<?php echo esc_url( admin_url() ); ?>"><?php echo kh_qt_icon( 'sliders-horizontal' ); // phpcs:ignore ?> Quản trị nâng cao</a>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php echo kh_qt_icon( 'log-out' ); // phpcs:ignore ?> Đăng xuất</a>
		</div>
	</aside>
	<div class="qt-scrim" data-qt-close></div>

	<div class="qt-main">
		<header class="qt-top">
			<button type="button" class="qt-burger" data-qt-menu aria-controls="qt-side" aria-label="Mở menu"><?php echo kh_qt_icon( 'menu', 22 ); // phpcs:ignore ?></button>
			<div class="qt-title">
				<?php if ( $page['back'] ) : ?>
					<a class="qt-back" href="<?php echo esc_url( $page['back'] ); ?>"><?php echo kh_qt_icon( 'arrow-left', 16 ); // phpcs:ignore ?> Quay lại</a>
				<?php endif; ?>
				<h1><?php echo esc_html( $page['title'] ); ?></h1>
			</div>
			<div class="qt-top-actions"><?php echo $page['actions']; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<div class="qt-user" title="<?php echo esc_attr( $user->user_email ); ?>">
				<?php echo get_avatar( $user->ID, 32 ); ?>
				<span><?php echo esc_html( $user->display_name ); ?></span>
			</div>
		</header>

		<?php if ( $flash ) : ?>
			<div class="qt-flash <?php echo $flash['ok'] ? 'is-ok' : 'is-err'; ?>" role="status">
				<?php echo kh_qt_icon( $flash['ok'] ? 'circle-check' : 'alert-circle' ); // phpcs:ignore ?>
				<span><?php echo esc_html( $flash['msg'] ); ?></span>
				<button type="button" data-qt-dismiss aria-label="Đóng"><?php echo kh_qt_icon( 'x', 16 ); // phpcs:ignore ?></button>
			</div>
		<?php endif; ?>

		<main class="qt-content">
			<?php echo $page['html']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</main>
	</div>
</div>
	<?php
	wp_print_footer_scripts();
	if ( $page['media'] && function_exists( 'wp_print_media_templates' ) && did_action( 'wp_enqueue_media' ) ) {
		wp_print_media_templates();
	}
	?>
</body>
</html>
	<?php
}

/**
 * Số việc cần làm trên menu (đơn chờ xác nhận, liên hệ mới).
 *
 * @return array
 */
function kh_qt_badges() {
	$out = array();
	if ( function_exists( 'wc_orders_count' ) ) {
		$out['don-hang'] = (int) wc_orders_count( 'on-hold' ) + (int) wc_orders_count( 'pending' );
	}
	$c               = wp_count_posts( 'kh_lien_he' );
	$out['lien-he'] = isset( $c->pending ) ? (int) $c->pending : 0;
	return $out;
}

/**
 * Không đủ quyền.
 *
 * @return array
 */
function kh_qt_page_denied() {
	return array(
		'title' => 'Không có quyền',
		'html'  => '<div class="qt-empty">' . kh_qt_icon( 'lock', 40 ) . '<p>Tài khoản của bạn không được dùng mục này. Liên hệ chủ website để được cấp quyền.</p></div>',
	);
}

/* ==========================================================================
 * Mẩu giao diện dùng chung
 * ======================================================================= */

/**
 * Thẻ (card) có tiêu đề.
 *
 * @param string $title Tiêu đề.
 * @param string $body  Nội dung HTML.
 * @param string $extra HTML bên phải tiêu đề.
 * @param string $class Lớp thêm.
 * @return string
 */
function kh_qt_card( $title, $body, $extra = '', $class = '' ) {
	$head = $title || $extra ? '<div class="qt-card-head"><h2>' . esc_html( $title ) . '</h2>' . $extra . '</div>' : '';
	return '<section class="qt-card ' . esc_attr( $class ) . '">' . $head . '<div class="qt-card-body">' . $body . '</div></section>';
}

/**
 * Nhãn trạng thái màu.
 *
 * @param string $text Chữ.
 * @param string $tone ok | warn | bad | info | mute.
 * @return string
 */
function kh_qt_badge( $text, $tone = 'mute' ) {
	return '<span class="qt-badge is-' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
}

/**
 * Khối "chưa có gì".
 *
 * @param string $text Chữ.
 * @param string $icon Icon.
 * @param string $cta  HTML nút.
 * @return string
 */
function kh_qt_empty( $text, $icon = 'inbox', $cta = '' ) {
	return '<div class="qt-empty">' . kh_qt_icon( $icon, 36 ) . '<p>' . esc_html( $text ) . '</p>' . $cta . '</div>';
}

/**
 * Phân trang đơn giản.
 *
 * @param int    $page  Trang hiện tại.
 * @param int    $pages Tổng số trang.
 * @param string $base  URL gốc (giữ các tham số lọc).
 * @return string
 */
function kh_qt_pager( $page, $pages, $base ) {
	if ( $pages < 2 ) {
		return '';
	}
	$out = '<nav class="qt-pager" aria-label="Phân trang">';
	if ( $page > 1 ) {
		$out .= '<a class="qt-btn qt-btn-ghost qt-btn-sm" href="' . esc_url( add_query_arg( 'trang', $page - 1, $base ) ) . '">' . kh_qt_icon( 'chevron-left', 16 ) . ' Trước</a>';
	}
	$out .= '<span>Trang ' . (int) $page . '/' . (int) $pages . '</span>';
	if ( $page < $pages ) {
		$out .= '<a class="qt-btn qt-btn-ghost qt-btn-sm" href="' . esc_url( add_query_arg( 'trang', $page + 1, $base ) ) . '">Sau ' . kh_qt_icon( 'chevron-right', 16 ) . '</a>';
	}
	return $out . '</nav>';
}

/**
 * Trang hiện tại (?trang=).
 *
 * @return int
 */
function kh_qt_paged() {
	return max( 1, isset( $_GET['trang'] ) ? absint( $_GET['trang'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * Giá trị ?key= đã làm sạch.
 *
 * @param string $key Key.
 * @return string
 */
function kh_qt_get( $key ) {
	return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * Ngày giờ kiểu Việt Nam.
 *
 * @param int|string|WC_DateTime|null $time Thời điểm.
 * @param bool                        $with_time Kèm giờ.
 * @return string
 */
function kh_qt_date( $time, $with_time = false ) {
	if ( ! $time ) {
		return '—';
	}
	if ( is_object( $time ) && method_exists( $time, 'getTimestamp' ) ) {
		$time = $time->getTimestamp();
	} elseif ( ! is_numeric( $time ) ) {
		$time = strtotime( (string) $time . ' UTC' );
	}
	return wp_date( $with_time ? 'd/m/Y H:i' : 'd/m/Y', (int) $time );
}

/**
 * "3 phút trước", "2 ngày trước"…
 *
 * @param int $time Timestamp.
 * @return string
 */
function kh_qt_ago( $time ) {
	if ( ! $time ) {
		return '';
	}
	$diff = time() - (int) $time;
	if ( $diff < 3 * DAY_IN_SECONDS ) {
		return human_time_diff( (int) $time ) . ' trước';
	}
	return kh_qt_date( $time );
}

/**
 * Nút gọi / Zalo theo số điện thoại.
 *
 * @param string $phone Số điện thoại.
 * @return string
 */
function kh_qt_phone_links( $phone ) {
	$d = kh_digits( $phone );
	if ( ! $d ) {
		return '';
	}
	return '<span class="qt-phone-links"><a class="qt-chip" href="tel:' . esc_attr( $d ) . '">' . kh_qt_icon( 'phone', 14 ) . ' Gọi</a><a class="qt-chip" target="_blank" rel="noopener" href="https://zalo.me/' . esc_attr( $d ) . '">' . kh_qt_icon( 'zalo', 14 ) . ' Zalo</a></span>';
}

/**
 * Ô nhập liệu có nhãn.
 *
 * @param string $name  Tên field.
 * @param string $label Nhãn.
 * @param mixed  $value Giá trị.
 * @param array  $o     type, help, options, required, placeholder, attrs, wide.
 * @return string
 */
function kh_qt_field( $name, $label, $value = '', $o = array() ) {
	$o    = wp_parse_args( $o, array( 'type' => 'text', 'help' => '', 'options' => array(), 'required' => false, 'placeholder' => '', 'attrs' => '', 'wide' => false ) );
	$id   = 'f-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );
	$req  = $o['required'] ? ' required' : '';
	$ph   = $o['placeholder'] ? ' placeholder="' . esc_attr( $o['placeholder'] ) . '"' : '';
	$attr = $req . $ph . ( $o['attrs'] ? ' ' . $o['attrs'] : '' );
	$out  = '<div class="qt-field' . ( $o['wide'] ? ' is-wide' : '' ) . ( 'checkbox' === $o['type'] ? ' is-check' : '' ) . '">';

	switch ( $o['type'] ) {
		case 'checkbox':
			$out .= '<label class="qt-switch"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . $attr . '><span class="qt-switch-ui" aria-hidden="true"></span><span>' . esc_html( $label ) . '</span></label>';
			break;
		case 'textarea':
		case 'code':
			$out .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . ( 'code' === $o['type'] ? 6 : 4 ) . '"' . ( 'code' === $o['type'] ? ' class="is-code"' : '' ) . $attr . '>' . esc_textarea( $value ) . '</textarea>';
			break;
		case 'select':
			$out .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $attr . '>';
			foreach ( $o['options'] as $ov => $ol ) {
				$out .= '<option value="' . esc_attr( $ov ) . '"' . selected( (string) $ov, (string) $value, false ) . '>' . esc_html( $ol ) . '</option>';
			}
			$out .= '</select>';
			break;
		case 'money':
			$out .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><div class="qt-money"><input type="text" inputmode="numeric" data-qt-money id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( '' === (string) $value ? '' : number_format( (float) $value, 0, ',', '.' ) ) . '"' . $attr . '><span>đ</span></div>';
			break;
		case 'image':
			$out .= '<label>' . esc_html( $label ) . '</label><div class="qt-image" data-qt-image><input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
			$src  = is_numeric( $value ) ? wp_get_attachment_image_url( (int) $value, 'medium' ) : $value;
			$out .= '<div class="qt-image-preview">' . ( $src ? '<img src="' . esc_url( $src ) . '" alt="">' : kh_qt_icon( 'image', 28 ) ) . '</div>';
			$out .= '<div class="qt-image-btns"><button type="button" class="qt-btn qt-btn-ghost qt-btn-sm" data-qt-image-pick data-store="' . ( isset( $o['store'] ) ? esc_attr( $o['store'] ) : 'id' ) . '">Chọn ảnh</button><button type="button" class="qt-btn qt-btn-ghost qt-btn-sm" data-qt-image-clear>Bỏ ảnh</button></div></div>';
			break;
		case 'color':
			$out .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><div class="qt-color"><input type="color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $attr . '><code>' . esc_html( $value ) . '</code></div>';
			break;
		default:
			$out .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><input type="' . esc_attr( $o['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $attr . '>';
	}
	if ( $o['help'] ) {
		$out .= '<p class="qt-help">' . esc_html( $o['help'] ) . '</p>';
	}
	return $out . '</div>';
}

/**
 * "490.000" → 490000.
 *
 * @param string $raw Chuỗi người dùng nhập.
 * @return string Số (chuỗi) hoặc '' nếu trống.
 */
function kh_qt_money_value( $raw ) {
	$d = preg_replace( '/\D/', '', (string) $raw );
	return '' === $d ? '' : (string) absint( $d );
}

/* ==========================================================================
 * Đưa nhân viên vào trang quản trị mới
 * ======================================================================= */

// Đăng nhập xong (không chỉ định trang): nhân viên vào /quan-tri/.
add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && kh_is_staff( $user ) && ( ! $requested || admin_url() === $requested ) ) {
			return kh_qt_url();
		}
		return $redirect;
	},
	20,
	3
);

// Thanh công cụ trên website: thêm nút "Quản trị" dẫn vào trang mới.
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! kh_is_staff() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'kh-quantri',
				'title' => '<span class="ab-icon dashicons dashicons-welcome-learn-more" style="top:2px"></span>Quản trị khóa học',
				'href'  => kh_qt_url(),
				'meta'  => array( 'class' => 'kh-quantri-node' ),
			)
		);
	},
	5
);

// Trong wp-admin: mục đầu tiên dẫn về trang quản trị mới.
add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Quản trị khóa học', 'Quản trị khóa học', 'edit_posts', 'kh-quantri', '__return_null', 'dashicons-welcome-learn-more', 1 );
	}
);
add_action(
	'load-toplevel_page_kh-quantri',
	function () {
		wp_safe_redirect( kh_qt_url() );
		exit;
	}
);
