<?php
/**
 * Quản trị → Tổng quan: số liệu chính + việc cần làm.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Doanh thu (đơn đã thanh toán) từ một thời điểm.
 *
 * @param int $from Timestamp.
 * @return float
 */
function kh_qt_revenue_since( $from ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return 0;
	}
	$ids = wc_get_orders(
		array(
			'status'       => array( 'processing', 'completed' ),
			'date_created' => '>=' . (int) $from,
			'limit'        => -1,
			'return'       => 'ids',
		)
	);
	$sum = 0;
	foreach ( $ids as $id ) {
		$o = wc_get_order( $id );
		if ( $o ) {
			$sum += (float) $o->get_total() - (float) $o->get_total_refunded();
		}
	}
	return $sum;
}

/**
 * Trang Tổng quan.
 *
 * @return array
 */
function kh_qt_page_dashboard() {
	$month_start = strtotime( wp_date( 'Y-m-01 00:00:00' ) . ' ' . wp_timezone_string() );
	$has_wc      = function_exists( 'wc_get_orders' );
	$waiting     = $has_wc ? wc_get_orders( array( 'status' => array( 'on-hold', 'pending' ), 'limit' => 6, 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
	$wait_count  = $has_wc ? (int) wc_orders_count( 'on-hold' ) + (int) wc_orders_count( 'pending' ) : 0;

	$new_students = new WP_User_Query(
		array(
			'role__in'    => array( 'customer', 'subscriber' ),
			'orderby'     => 'registered',
			'order'       => 'DESC',
			'number'      => 6,
			'count_total' => true,
			'date_query'  => array( array( 'after' => '30 days ago', 'inclusive' => true ) ),
		)
	);
	$students_total = ( new WP_User_Query( array( 'role__in' => array( 'customer', 'subscriber' ), 'number' => 1, 'count_total' => true, 'fields' => 'ID' ) ) )->get_total();

	$contacts     = get_posts( array( 'post_type' => 'kh_lien_he', 'post_status' => array( 'pending', 'private' ), 'posts_per_page' => 5 ) );
	$contact_new  = (int) wp_count_posts( 'kh_lien_he' )->pending;

	// Số liệu.
	$stats = array();
	if ( $has_wc ) {
		$stats[] = array( 'Doanh thu tháng ' . wp_date( 'n' ), kh_price( kh_qt_revenue_since( $month_start ) ), 'wallet', kh_qt_url( 'don-hang', '', array( 'loc' => 'da-thanh-toan' ) ) );
		$stats[] = array( 'Đơn chờ xác nhận', number_format_i18n( $wait_count ), 'hourglass', kh_qt_url( 'don-hang' ), $wait_count ? 'warn' : '' );
	}
	$stats[] = array( 'Học viên mới (30 ngày)', number_format_i18n( $new_students->get_total() ), 'user-plus', kh_qt_url( 'hoc-vien' ) );
	$stats[] = array( 'Tổng học viên', number_format_i18n( $students_total ), 'users', kh_qt_url( 'hoc-vien' ) );
	$stats[] = array( 'Liên hệ chưa đọc', number_format_i18n( $contact_new ), 'inbox', kh_qt_url( 'lien-he' ), $contact_new ? 'warn' : '' );

	$html = '<div class="qt-stats">';
	foreach ( $stats as $s ) {
		$html .= '<a class="qt-stat' . ( ! empty( $s[4] ) ? ' is-' . esc_attr( $s[4] ) : '' ) . '" href="' . esc_url( $s[3] ) . '"><span class="qt-stat-ico">' . kh_qt_icon( $s[2], 22 ) . '</span><span class="qt-stat-label">' . esc_html( $s[0] ) . '</span><strong>' . esc_html( $s[1] ) . '</strong></a>';
	}
	$html .= '</div>';

	// Đơn chờ xác nhận.
	$grid = '';
	if ( $has_wc ) {
		if ( $waiting ) {
			$rows = '<ul class="qt-list">';
			foreach ( $waiting as $o ) {
				$rows .= '<li><div class="qt-list-main"><a href="' . esc_url( kh_qt_url( 'don-hang', $o->get_id() ) ) . '"><strong>#' . esc_html( $o->get_order_number() ) . ' · ' . esc_html( kh_qt_order_name( $o ) ) . '</strong></a>';
				$rows .= '<span>' . esc_html( implode( ', ', kh_qt_order_titles( $o ) ) ) . '</span>';
				$rows .= '<span class="qt-muted">' . esc_html( kh_qt_ago( $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0 ) ) . '</span></div>';
				$rows .= '<div class="qt-list-side"><strong>' . wp_kses_post( wc_price( $o->get_total() ) ) . '</strong>';
				$rows .= kh_qt_action_button( 'order_status', array( 'order_id' => $o->get_id(), 'status' => 'completed' ), kh_qt_icon( 'check', 16 ) . ' Đã nhận tiền', 'qt-btn qt-btn-sm', 'Xác nhận đã nhận đủ ' . wp_strip_all_tags( wc_price( $o->get_total() ) ) . ' của đơn #' . $o->get_order_number() . '? Khóa học sẽ được mở ngay cho học viên.' );
				$rows .= '</div></li>';
			}
			$rows .= '</ul>';
		} else {
			$rows = kh_qt_empty( 'Không có đơn nào chờ xác nhận.', 'circle-check' );
		}
		$grid .= kh_qt_card( 'Đơn chờ xác nhận thanh toán', $rows, '<a class="qt-link" href="' . esc_url( kh_qt_url( 'don-hang' ) ) . '">Xem tất cả</a>', 'is-todo' );
	}

	// Học viên mới.
	$list = '';
	foreach ( $new_students->get_results() as $u ) {
		$list .= '<li><div class="qt-list-main"><a href="' . esc_url( kh_qt_url( 'hoc-vien', $u->ID ) ) . '"><strong>' . esc_html( $u->display_name ) . '</strong></a><span class="qt-muted">' . esc_html( $u->user_email ) . '</span></div>';
		$list .= '<div class="qt-list-side"><span class="qt-muted">' . esc_html( kh_qt_ago( strtotime( $u->user_registered . ' UTC' ) ) ) . '</span></div></li>';
	}
	$grid .= kh_qt_card(
		'Học viên mới',
		$list ? '<ul class="qt-list">' . $list . '</ul>' : kh_qt_empty( 'Chưa có học viên mới trong 30 ngày.', 'users' ),
		'<a class="qt-link" href="' . esc_url( kh_qt_url( 'hoc-vien', 'moi' ) ) . '">+ Thêm học viên</a>'
	);

	// Liên hệ.
	$list = '';
	foreach ( $contacts as $c ) {
		$list .= '<li><div class="qt-list-main"><strong>' . esc_html( $c->post_title ) . ( 'pending' === $c->post_status ? ' ' . kh_qt_badge( 'Mới', 'warn' ) : '' ) . '</strong><span>' . esc_html( wp_trim_words( (string) get_post_meta( $c->ID, '_kh_message', true ), 14 ) ) . '</span></div>';
		$list .= '<div class="qt-list-side">' . kh_qt_phone_links( get_post_meta( $c->ID, '_kh_phone', true ) ) . '</div></li>';
	}
	$grid .= kh_qt_card(
		'Liên hệ gần đây',
		$list ? '<ul class="qt-list">' . $list . '</ul>' : kh_qt_empty( 'Chưa có ai gửi liên hệ.', 'inbox' ),
		'<a class="qt-link" href="' . esc_url( kh_qt_url( 'lien-he' ) ) . '">Xem tất cả</a>'
	);

	// Lối tắt.
	$quick  = '<div class="qt-quick">';
	$quick .= '<a href="' . esc_url( kh_qt_url( 'hoc-vien', 'moi' ) ) . '">' . kh_qt_icon( 'user-plus', 22 ) . '<span>Thêm học viên</span></a>';
	$quick .= '<a href="' . esc_url( kh_qt_url( 'khoa-hoc', 'moi' ) ) . '">' . kh_qt_icon( 'list-plus', 22 ) . '<span>Thêm khóa học</span></a>';
	if ( current_user_can( 'manage_options' ) ) {
		$quick .= '<a href="' . esc_url( kh_qt_url( 'cai-dat', '', array( 'tab' => 'chuyen-khoan' ) ) ) . '">' . kh_qt_icon( 'landmark', 22 ) . '<span>Tài khoản nhận tiền</span></a>';
	}
	$quick .= '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . kh_qt_icon( 'globe', 22 ) . '<span>Xem website</span></a>';
	$quick .= '</div>';
	$grid  .= kh_qt_card( 'Làm nhanh', $quick );

	$user  = wp_get_current_user();
	$hour  = (int) wp_date( 'G' );
	$hello = $hour < 11 ? 'Chào buổi sáng' : ( $hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối' );

	return array(
		'title' => $hello . ', ' . $user->display_name,
		'html'  => $html . '<div class="qt-grid">' . $grid . '</div>',
	);
}
