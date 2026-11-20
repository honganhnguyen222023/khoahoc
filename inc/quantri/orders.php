<?php
/**
 * Quản trị → Đơn hàng (WooCommerce): xem, xác nhận đã nhận tiền, hủy, ghi chú.
 * Đổi trạng thái đơn → theme tự mở / thu hồi khóa học (xem inc/woocommerce.php).
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tên người mua: "Nguyễn Văn An".
 *
 * @param WC_Order $o Đơn.
 * @return string
 */
function kh_qt_order_name( $o ) {
	$name = trim( $o->get_billing_last_name() . ' ' . $o->get_billing_first_name() );
	if ( ! $name && $o->get_customer_id() ) {
		$u    = get_userdata( $o->get_customer_id() );
		$name = $u ? $u->display_name : '';
	}
	return $name ? $name : ( $o->get_billing_email() ? $o->get_billing_email() : 'Khách' );
}

/**
 * Tên các khóa / sản phẩm trong đơn.
 *
 * @param WC_Order $o Đơn.
 * @return string[]
 */
function kh_qt_order_titles( $o ) {
	$out = array();
	foreach ( $o->get_items() as $item ) {
		$out[] = $item->get_name();
	}
	return $out;
}

/**
 * Trạng thái đơn → [ chữ, màu ].
 *
 * @param string $status Trạng thái (không có "wc-").
 * @return array
 */
function kh_qt_order_status( $status ) {
	$map = array(
		'pending'    => array( 'Chờ thanh toán', 'warn' ),
		'on-hold'    => array( 'Chờ xác nhận', 'warn' ),
		'processing' => array( 'Đã thanh toán', 'ok' ),
		'completed'  => array( 'Hoàn thành', 'ok' ),
		'cancelled'  => array( 'Đã hủy', 'mute' ),
		'refunded'   => array( 'Đã hoàn tiền', 'mute' ),
		'failed'     => array( 'Thất bại', 'bad' ),
	);
	if ( isset( $map[ $status ] ) ) {
		return $map[ $status ];
	}
	return array( wc_get_order_status_name( $status ), 'info' );
}

/**
 * Nhóm lọc trong danh sách đơn.
 *
 * @return array slug => [ chữ, trạng thái ]
 */
function kh_qt_order_filters() {
	return array(
		''              => array( 'Tất cả', array_keys( wc_get_order_statuses() ) ),
		'cho-xac-nhan'  => array( 'Chờ xác nhận', array( 'on-hold', 'pending' ) ),
		'da-thanh-toan' => array( 'Đã thanh toán', array( 'processing', 'completed' ) ),
		'da-huy'        => array( 'Đã hủy / hoàn tiền', array( 'cancelled', 'refunded', 'failed' ) ),
	);
}

/**
 * Trang Đơn hàng: danh sách hoặc chi tiết.
 *
 * @param string $id Mã đơn.
 * @return array
 */
function kh_qt_page_orders( $id ) {
	if ( $id ) {
		return kh_qt_order_detail( absint( $id ) );
	}
	$filters = kh_qt_order_filters();
	$f       = kh_qt_get( 'loc' );
	$f       = isset( $filters[ $f ] ) ? $f : '';
	$q       = kh_qt_get( 'q' );
	$page    = kh_qt_paged();
	$per     = 20;

	$args = array(
		'status'   => array_map( function ( $s ) { return 0 === strpos( $s, 'wc-' ) ? substr( $s, 3 ) : $s; }, $filters[ $f ][1] ),
		'limit'    => $per,
		'page'     => $page,
		'paginate' => true,
		'orderby'  => 'date',
		'order'    => 'DESC',
		'type'     => 'shop_order',
	);
	$orders = array();
	$total  = 0;
	$pages  = 1;
	if ( '' !== $q && ctype_digit( ltrim( $q, '#' ) ) && wc_get_order( (int) ltrim( $q, '#' ) ) ) {
		$orders = array( wc_get_order( (int) ltrim( $q, '#' ) ) );
		$total  = 1;
	} else {
		if ( '' !== $q ) {
			if ( is_email( $q ) ) {
				$args['billing_email'] = $q;
			} else {
				$args['s'] = $q;
			}
		}
		$res    = wc_get_orders( $args );
		$orders = $res->orders;
		$total  = (int) $res->total;
		$pages  = (int) $res->max_num_pages;
	}

	// Thanh lọc.
	$html = '<div class="qt-toolbar"><nav class="qt-tabs">';
	foreach ( $filters as $slug => $fl ) {
		$count = 0;
		foreach ( $fl[1] as $st ) {
			$count += (int) wc_orders_count( 0 === strpos( $st, 'wc-' ) ? substr( $st, 3 ) : $st );
		}
		$html .= '<a href="' . esc_url( kh_qt_url( 'don-hang', '', $slug ? array( 'loc' => $slug ) : array() ) ) . '" class="' . ( $slug === $f ? 'is-active' : '' ) . '">' . esc_html( $fl[0] ) . ' <em>' . (int) $count . '</em></a>';
	}
	$html .= '</nav><form class="qt-search" method="get" action="' . esc_url( kh_qt_url( 'don-hang' ) ) . '">';
	if ( $f ) {
		$html .= '<input type="hidden" name="loc" value="' . esc_attr( $f ) . '">';
	}
	$html .= kh_qt_icon( 'search', 16 ) . '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Mã đơn, tên, email, SĐT…" aria-label="Tìm đơn hàng"></form></div>';

	if ( ! $orders ) {
		$html .= kh_qt_card( '', kh_qt_empty( '' !== $q ? 'Không tìm thấy đơn hàng phù hợp.' : 'Chưa có đơn hàng nào ở mục này.', 'receipt' ) );
		return array( 'title' => 'Đơn hàng', 'html' => $html );
	}

	$rows = '';
	foreach ( $orders as $o ) {
		if ( ! $o instanceof WC_Order ) {
			continue;
		}
		$st    = kh_qt_order_status( $o->get_status() );
		$url   = kh_qt_url( 'don-hang', $o->get_id() );
		$rows .= '<tr data-href="' . esc_url( $url ) . '">';
		$rows .= '<td data-label="Đơn"><a href="' . esc_url( $url ) . '"><strong>#' . esc_html( $o->get_order_number() ) . '</strong></a><span class="qt-muted qt-block">' . esc_html( kh_qt_date( $o->get_date_created(), true ) ) . '</span></td>';
		$rows .= '<td data-label="Học viên"><strong>' . esc_html( kh_qt_order_name( $o ) ) . '</strong><span class="qt-muted qt-block">' . esc_html( $o->get_billing_phone() ? $o->get_billing_phone() : $o->get_billing_email() ) . '</span></td>';
		$rows .= '<td data-label="Khóa học">' . esc_html( implode( ', ', kh_qt_order_titles( $o ) ) ) . '</td>';
		$rows .= '<td data-label="Tổng tiền" class="qt-num"><strong>' . wp_kses_post( wc_price( $o->get_total() ) ) . '</strong></td>';
		$rows .= '<td data-label="Trạng thái">' . kh_qt_badge( $st[0], $st[1] ) . '</td>';
		$rows .= '<td class="qt-row-actions">';
		if ( $o->has_status( array( 'on-hold', 'pending' ) ) ) {
			$rows .= kh_qt_action_button( 'order_status', array( 'order_id' => $o->get_id(), 'status' => 'completed' ), kh_qt_icon( 'check', 16 ) . ' Đã nhận tiền', 'qt-btn qt-btn-sm', 'Xác nhận đã nhận đủ ' . wp_strip_all_tags( wc_price( $o->get_total() ) ) . ' của đơn #' . $o->get_order_number() . '? Khóa học sẽ được mở ngay cho học viên.' );
		}
		$rows .= '</td></tr>';
	}
	$table = '<table class="qt-table is-click"><thead><tr><th>Đơn</th><th>Học viên</th><th>Khóa học</th><th class="qt-num">Tổng tiền</th><th>Trạng thái</th><th><span class="screen-reader-text">Thao tác</span></th></tr></thead><tbody>' . $rows . '</tbody></table>';
	$html .= kh_qt_card( '', $table . kh_qt_pager( $page, $pages, remove_query_arg( 'trang' ) ), '', 'is-flush' );

	return array(
		'title' => 'Đơn hàng',
		'html'  => $html,
	);
}

/**
 * Chi tiết 1 đơn.
 *
 * @param int $id Mã đơn.
 * @return array
 */
function kh_qt_order_detail( $id ) {
	$o = wc_get_order( $id );
	if ( ! $o || 'shop_order' !== $o->get_type() ) {
		return array( 'title' => 'Không tìm thấy đơn', 'back' => kh_qt_url( 'don-hang' ), 'html' => kh_qt_empty( 'Đơn hàng không tồn tại hoặc đã bị xóa.', 'receipt' ) );
	}
	$st     = kh_qt_order_status( $o->get_status() );
	$paid   = $o->has_status( array( 'processing', 'completed' ) );
	$total  = wp_strip_all_tags( wc_price( $o->get_total() ) );
	$num    = $o->get_order_number();
	$cust   = $o->get_customer_id();

	// Thanh trạng thái + thao tác chính.
	$bar = '<div class="qt-order-bar"><div>' . kh_qt_badge( $st[0], $st[1] ) . '<span class="qt-muted">Đặt lúc ' . esc_html( kh_qt_date( $o->get_date_created(), true ) ) . '</span></div><div class="qt-order-total">' . wp_kses_post( wc_price( $o->get_total() ) ) . '</div><div class="qt-btn-row">';
	if ( $o->has_status( array( 'on-hold', 'pending', 'failed' ) ) ) {
		$bar .= kh_qt_action_button( 'order_status', array( 'order_id' => $id, 'status' => 'completed' ), kh_qt_icon( 'check', 18 ) . ' Xác nhận đã nhận tiền', 'qt-btn', 'Xác nhận đã nhận đủ ' . $total . ' của đơn #' . $num . '? Khóa học sẽ được mở ngay cho học viên.' );
		$bar .= kh_qt_action_button( 'order_status', array( 'order_id' => $id, 'status' => 'cancelled' ), 'Hủy đơn', 'qt-btn qt-btn-ghost', 'Hủy đơn #' . $num . '?' );
	} elseif ( 'processing' === $o->get_status() ) {
		$bar .= kh_qt_action_button( 'order_status', array( 'order_id' => $id, 'status' => 'completed' ), kh_qt_icon( 'check', 18 ) . ' Đánh dấu hoàn thành', 'qt-btn' );
		$bar .= kh_qt_action_button( 'order_status', array( 'order_id' => $id, 'status' => 'refunded' ), 'Hoàn tiền', 'qt-btn qt-btn-ghost', 'Đánh dấu đã hoàn tiền đơn #' . $num . '? Học viên sẽ bị khóa các khóa học trong đơn này. (Tiền bạn tự chuyển trả cho học viên.)' );
	} elseif ( 'completed' === $o->get_status() ) {
		$bar .= kh_qt_action_button( 'order_status', array( 'order_id' => $id, 'status' => 'refunded' ), 'Hoàn tiền', 'qt-btn qt-btn-ghost', 'Đánh dấu đã hoàn tiền đơn #' . $num . '? Học viên sẽ bị khóa các khóa học trong đơn này. (Tiền bạn tự chuyển trả cho học viên.)' );
	} else {
		$bar .= kh_qt_action_button( 'order_status', array( 'order_id' => $id, 'status' => 'on-hold' ), kh_qt_icon( 'rotate-ccw', 16 ) . ' Mở lại đơn', 'qt-btn qt-btn-ghost', 'Mở lại đơn #' . $num . ' về trạng thái chờ xác nhận?' );
	}
	$bar .= '</div></div>';

	// Học viên.
	$who  = '<dl class="qt-dl">';
	$who .= '<dt>Họ tên</dt><dd><strong>' . esc_html( kh_qt_order_name( $o ) ) . '</strong></dd>';
	$who .= '<dt>Điện thoại</dt><dd>' . esc_html( $o->get_billing_phone() ? $o->get_billing_phone() : '—' ) . ' ' . kh_qt_phone_links( $o->get_billing_phone() ) . '</dd>';
	$who .= '<dt>Email</dt><dd>' . ( $o->get_billing_email() ? '<a href="mailto:' . esc_attr( $o->get_billing_email() ) . '">' . esc_html( $o->get_billing_email() ) . '</a>' : '—' ) . '</dd>';
	$who .= '</dl>';
	$who_extra = $cust && current_user_can( 'edit_users' ) ? '<a class="qt-link" href="' . esc_url( kh_qt_url( 'hoc-vien', $cust ) ) . '">Xem học viên</a>' : '';

	// Khóa học trong đơn.
	$items = '<ul class="qt-list">';
	foreach ( $o->get_items() as $item ) {
		$courses = function_exists( 'kh_wc_product_courses' ) ? kh_wc_product_courses( $item->get_product_id() ) : array();
		$state   = '';
		if ( $cust && $courses ) {
			$info  = kh_access_info( $courses[0], $cust );
			$state = $info ? ( $info['active'] ? kh_qt_badge( 'Đã mở khóa', 'ok' ) : kh_qt_badge( 'Hết hạn', 'mute' ) ) : kh_qt_badge( 'Chưa mở', 'warn' );
		}
		$items .= '<li><div class="qt-list-main"><strong>' . esc_html( $item->get_name() ) . '</strong>' . ( $courses ? '<span>' . $state . '</span>' : '' ) . '</div><div class="qt-list-side"><strong>' . wp_kses_post( wc_price( $item->get_total() ) ) . '</strong></div></li>';
	}
	$items .= '</ul>';
	if ( (float) $o->get_discount_total() > 0 ) {
		$items .= '<p class="qt-muted">Giảm giá: −' . wp_kses_post( wc_price( $o->get_discount_total() ) ) . '</p>';
	}

	// Thanh toán.
	$pay  = '<dl class="qt-dl">';
	$pay .= '<dt>Hình thức</dt><dd>' . esc_html( $o->get_payment_method_title() ? $o->get_payment_method_title() : '—' ) . '</dd>';
	$pay .= '<dt>Tổng tiền</dt><dd><strong>' . wp_kses_post( wc_price( $o->get_total() ) ) . '</strong></dd>';
	if ( $o->get_date_paid() ) {
		$pay .= '<dt>Ngày thanh toán</dt><dd>' . esc_html( kh_qt_date( $o->get_date_paid(), true ) ) . '</dd>';
	}
	if ( $o->get_customer_note() ) {
		$pay .= '<dt>Học viên ghi chú</dt><dd>' . esc_html( $o->get_customer_note() ) . '</dd>';
	}
	$pay .= '</dl>';
	if ( ! $paid && 'bacs' === $o->get_payment_method() ) {
		$pay .= '<p class="qt-note">' . kh_qt_icon( 'info', 16 ) . '<span>Kiểm tra tài khoản ngân hàng có khoản <strong>' . esc_html( $total ) . '</strong>, nội dung chuyển khoản thường có mã <strong>' . esc_html( $num ) . '</strong>, rồi bấm "Xác nhận đã nhận tiền".</span></p>';
	}

	// Ghi chú.
	$notes = wc_get_order_notes( array( 'order_id' => $id ) );
	$nh    = '<form method="post" class="qt-note-form">' . kh_qt_form_fields( 'order_note' ) . '<input type="hidden" name="order_id" value="' . (int) $id . '"><textarea name="note" rows="2" placeholder="Thêm ghi chú nội bộ (học viên không thấy)…" required></textarea><button class="qt-btn qt-btn-sm" type="submit">Thêm ghi chú</button></form>';
	if ( $notes ) {
		$nh .= '<ul class="qt-timeline">';
		foreach ( $notes as $n ) {
			$nh .= '<li><p>' . wp_kses_post( wpautop( wptexturize( $n->content ) ) ) . '</p><span class="qt-muted">' . esc_html( kh_qt_date( $n->date_created, true ) ) . ( $n->added_by && 'system' !== $n->added_by ? ' · ' . esc_html( $n->added_by ) : '' ) . '</span></li>';
		}
		$nh .= '</ul>';
	}

	$html  = $bar;
	$html .= '<div class="qt-grid">';
	$html .= kh_qt_card( 'Học viên', $who, $who_extra );
	$html .= kh_qt_card( 'Thanh toán', $pay );
	$html .= kh_qt_card( 'Khóa học trong đơn', $items );
	$html .= kh_qt_card( 'Lịch sử & ghi chú', $nh );
	$html .= '</div>';

	return array(
		'title'   => 'Đơn hàng #' . $num,
		'back'    => kh_qt_url( 'don-hang' ),
		'html'    => $html,
		'actions' => '<a class="qt-btn qt-btn-ghost qt-btn-sm" href="' . esc_url( $o->get_edit_order_url() ) . '">Mở trong WooCommerce</a>',
	);
}

/**
 * Thao tác: đổi trạng thái đơn.
 *
 * @param array $p Dữ liệu gửi lên.
 * @return array
 */
function kh_qt_do_order_status( $p ) {
	$o      = wc_get_order( isset( $p['order_id'] ) ? absint( $p['order_id'] ) : 0 );
	$status = isset( $p['status'] ) ? sanitize_key( $p['status'] ) : '';
	if ( ! $o || ! current_user_can( 'edit_shop_orders' ) || ! in_array( $status, array( 'completed', 'cancelled', 'refunded', 'on-hold' ), true ) ) {
		return array( 'ok' => false, 'msg' => 'Không đổi được trạng thái đơn.' );
	}
	$who = wp_get_current_user()->display_name;
	$o->update_status( $status, sprintf( 'Đổi trạng thái từ trang Quản trị (%s).', $who ) );
	$msgs = array(
		'completed' => 'Đã xác nhận đơn #%s. Khóa học đã được mở cho học viên.',
		'cancelled' => 'Đã hủy đơn #%s.',
		'refunded'  => 'Đã đánh dấu hoàn tiền đơn #%s và khóa các khóa học trong đơn.',
		'on-hold'   => 'Đã mở lại đơn #%s (chờ xác nhận).',
	);
	return array( 'ok' => true, 'msg' => sprintf( $msgs[ $status ], $o->get_order_number() ) );
}

/**
 * Thao tác: thêm ghi chú nội bộ.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_order_note( $p ) {
	$o    = wc_get_order( isset( $p['order_id'] ) ? absint( $p['order_id'] ) : 0 );
	$note = isset( $p['note'] ) ? sanitize_textarea_field( $p['note'] ) : '';
	if ( ! $o || '' === $note || ! current_user_can( 'edit_shop_orders' ) ) {
		return array( 'ok' => false, 'msg' => 'Chưa thêm được ghi chú.' );
	}
	$o->add_order_note( $note, 0, true );
	return array( 'ok' => true, 'msg' => 'Đã thêm ghi chú.' );
}
