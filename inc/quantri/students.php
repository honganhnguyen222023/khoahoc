<?php
/**
 * Quản trị → Học viên: danh sách, thêm mới, mở / gia hạn / thu hồi khóa học.
 * Dùng chung dữ liệu với inc/lms.php (user meta _kh_courses, _kh_course_since…).
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Các khóa học đã đăng (để chọn mở cho học viên).
 *
 * @return WP_Post[]
 */
function kh_qt_all_courses() {
	static $list = null;
	if ( null === $list ) {
		$list = get_posts( array( 'post_type' => 'khoa_hoc', 'post_status' => array( 'publish', 'private', 'draft' ), 'posts_per_page' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
	}
	return $list;
}

/**
 * Tiến độ: [ đã học, tổng số bài ].
 *
 * @param int $user_id   User.
 * @param int $course_id Khóa.
 * @return int[]
 */
function kh_qt_progress( $user_id, $course_id ) {
	$lessons = array_filter( wp_list_pluck( kh_course_lessons( $course_id ), 'src' ) );
	return array( count( array_intersect_key( kh_progress( $user_id, $course_id ), $lessons ) ), count( $lessons ) );
}

/**
 * Số điện thoại của học viên.
 *
 * @param int $user_id User.
 * @return string
 */
function kh_qt_user_phone( $user_id ) {
	return (string) get_user_meta( $user_id, 'billing_phone', true );
}

/**
 * Trang Học viên.
 *
 * @param string $id ID học viên hoặc "moi".
 * @return array
 */
function kh_qt_page_students( $id ) {
	if ( 'moi' === $id ) {
		return kh_qt_student_new();
	}
	if ( $id ) {
		return kh_qt_student_detail( absint( $id ) );
	}

	$q    = kh_qt_get( 'q' );
	$page = kh_qt_paged();
	$per  = 20;
	$args = array(
		'role__in'    => array( 'customer', 'subscriber' ),
		'orderby'     => 'registered',
		'order'       => 'DESC',
		'number'      => $per,
		'paged'       => $page,
		'count_total' => true,
	);
	if ( '' !== $q ) {
		$digits = kh_digits( $q );
		if ( strlen( $digits ) >= 4 && strlen( $digits ) >= strlen( preg_replace( '/\s/', '', $q ) ) - 1 ) {
			$args['meta_query'] = array( array( 'key' => 'billing_phone', 'value' => $digits, 'compare' => 'LIKE' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		} else {
			$args['search']         = '*' . $q . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
		}
	}
	$uq    = new WP_User_Query( $args );
	$users = $uq->get_results();
	$pages = (int) ceil( $uq->get_total() / $per );

	$html  = '<div class="qt-toolbar"><p class="qt-muted">' . esc_html( sprintf( '%s học viên', number_format_i18n( $uq->get_total() ) ) ) . '</p>';
	$html .= '<form class="qt-search" method="get" action="' . esc_url( kh_qt_url( 'hoc-vien' ) ) . '">' . kh_qt_icon( 'search', 16 ) . '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Tên, email hoặc số điện thoại…" aria-label="Tìm học viên"></form></div>';

	if ( ! $users ) {
		$html .= kh_qt_card( '', kh_qt_empty( '' !== $q ? 'Không tìm thấy học viên phù hợp.' : 'Chưa có học viên nào.', 'users', '<a class="qt-btn" href="' . esc_url( kh_qt_url( 'hoc-vien', 'moi' ) ) . '">' . kh_qt_icon( 'user-plus', 16 ) . ' Thêm học viên</a>' ) );
		return array( 'title' => 'Học viên', 'html' => $html, 'actions' => kh_qt_student_add_btn() );
	}

	$rows = '';
	foreach ( $users as $u ) {
		$url    = kh_qt_url( 'hoc-vien', $u->ID );
		$chips  = '';
		foreach ( kh_access_list( $u->ID ) as $cid => $a ) {
			if ( ! isset( kh_user_course_map( $u->ID )[ $cid ] ) ) {
				continue; // Khóa con trong combo: chỉ hiện combo.
			}
			$chips .= '<span class="qt-chip' . ( $a['active'] ? ' is-ok' : ' is-mute' ) . '">' . esc_html( get_the_title( $cid ) ) . ( $a['active'] ? '' : ' (hết hạn)' ) . '</span>';
		}
		$phone = kh_qt_user_phone( $u->ID );
		$rows .= '<tr data-href="' . esc_url( $url ) . '">';
		$rows .= '<td data-label="Học viên"><div class="qt-person">' . get_avatar( $u->ID, 36 ) . '<div><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $u->display_name ) . '</strong></a><span class="qt-muted qt-block">' . esc_html( $u->user_email ) . '</span></div></div></td>';
		$rows .= '<td data-label="Điện thoại">' . ( $phone ? esc_html( $phone ) : '<span class="qt-muted">—</span>' ) . '</td>';
		$rows .= '<td data-label="Khóa học">' . ( $chips ? '<div class="qt-chips">' . $chips . '</div>' : '<span class="qt-muted">Chưa có khóa nào</span>' ) . '</td>';
		$rows .= '<td data-label="Tham gia">' . esc_html( kh_qt_date( strtotime( $u->user_registered . ' UTC' ) ) ) . '</td>';
		$rows .= '</tr>';
	}
	$table = '<table class="qt-table is-click"><thead><tr><th>Học viên</th><th>Điện thoại</th><th>Khóa học</th><th>Tham gia</th></tr></thead><tbody>' . $rows . '</tbody></table>';
	$html .= kh_qt_card( '', $table . kh_qt_pager( $page, $pages, remove_query_arg( 'trang' ) ), '', 'is-flush' );

	return array(
		'title'   => 'Học viên',
		'html'    => $html,
		'actions' => kh_qt_student_add_btn(),
	);
}

/**
 * Nút "Thêm học viên".
 *
 * @return string
 */
function kh_qt_student_add_btn() {
	return '<a class="qt-btn qt-btn-sm" href="' . esc_url( kh_qt_url( 'hoc-vien', 'moi' ) ) . '">' . kh_qt_icon( 'user-plus', 16 ) . ' <span>Thêm học viên</span></a>';
}

/**
 * Các ô chọn khóa học.
 *
 * @param string $name   Tên field (mảng).
 * @param int[]  $skip   Khóa không hiện.
 * @return string
 */
function kh_qt_course_checks( $name, $skip = array() ) {
	$out = '<div class="qt-checks">';
	foreach ( kh_qt_all_courses() as $c ) {
		if ( in_array( $c->ID, $skip, true ) ) {
			continue;
		}
		$out .= '<label class="qt-check"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . (int) $c->ID . '"><span>' . esc_html( $c->post_title ) . ( 'publish' !== $c->post_status ? ' <em class="qt-muted">(đang ẩn)</em>' : '' ) . '</span><small>' . esc_html( kh_duration_label( $c->ID ) ) . '</small></label>';
	}
	return $out . '</div>';
}

/**
 * Form thêm học viên.
 *
 * @return array
 */
function kh_qt_student_new() {
	$form  = '<form method="post" class="qt-form" autocomplete="off">' . kh_qt_form_fields( 'student_create' );
	$form .= '<div class="qt-form-grid">';
	// Điền sẵn khi tạo từ một liên hệ.
	$form .= kh_qt_field( 'name', 'Họ và tên', kh_qt_get( 'ten' ), array( 'required' => true, 'placeholder' => 'Nguyễn Văn An' ) );
	$form .= kh_qt_field( 'phone', 'Số điện thoại / Zalo', kh_qt_get( 'sdt' ), array( 'type' => 'tel', 'placeholder' => '0912 345 678' ) );
	$form .= kh_qt_field( 'email', 'Email (dùng để đăng nhập)', sanitize_email( kh_qt_get( 'email' ) ), array( 'type' => 'email', 'required' => true, 'placeholder' => 'hocvien@gmail.com' ) );
	$form .= kh_qt_field( 'password', 'Mật khẩu', '', array( 'placeholder' => 'Để trống: tự tạo', 'help' => 'Để trống thì học viên tự đặt mật khẩu qua email.', 'attrs' => 'autocomplete="new-password"' ) );
	$form .= '</div>';
	$form .= '<h3 class="qt-h3">Mở khóa học ngay</h3><p class="qt-help">Dùng khi học viên chuyển khoản trực tiếp cho bạn. Có thể bỏ trống và mở sau.</p>';
	$form .= kh_qt_course_checks( 'courses' );
	$form .= kh_qt_field( 'notify', 'Gửi email cho học viên (link đặt mật khẩu và đăng nhập)', '1', array( 'type' => 'checkbox' ) );
	$form .= '<div class="qt-form-foot"><button class="qt-btn" type="submit">' . kh_qt_icon( 'user-plus', 18 ) . ' Tạo học viên</button><a class="qt-btn qt-btn-ghost" href="' . esc_url( kh_qt_url( 'hoc-vien' ) ) . '">Hủy</a></div>';
	$form .= '</form>';

	return array(
		'title' => 'Thêm học viên',
		'back'  => kh_qt_url( 'hoc-vien' ),
		'html'  => kh_qt_card( '', $form, '', 'is-narrow' ),
	);
}

/**
 * Chi tiết học viên.
 *
 * @param int $id User ID.
 * @return array
 */
function kh_qt_student_detail( $id ) {
	$u = get_userdata( $id );
	if ( ! $u ) {
		return array( 'title' => 'Không tìm thấy', 'back' => kh_qt_url( 'hoc-vien' ), 'html' => kh_qt_empty( 'Học viên không tồn tại.', 'users' ) );
	}
	$phone  = kh_qt_user_phone( $id );
	$map    = kh_user_course_map( $id );
	$access = kh_access_list( $id );
	$limit  = kh_views_limit();

	// Đầu trang: thông tin.
	$head  = '<div class="qt-profile">' . get_avatar( $id, 64 ) . '<div><h2>' . esc_html( $u->display_name ) . '</h2>';
	$head .= '<p class="qt-muted">' . esc_html( $u->user_email ) . ( $phone ? ' · ' . esc_html( $phone ) : '' ) . ' · Tham gia ' . esc_html( kh_qt_date( strtotime( $u->user_registered . ' UTC' ) ) ) . '</p>';
	$head .= '<div class="qt-btn-row">' . kh_qt_phone_links( $phone ) . '<a class="qt-chip" href="mailto:' . esc_attr( $u->user_email ) . '">' . kh_qt_icon( 'mail', 14 ) . ' Email</a></div></div></div>';

	// Khóa học.
	$list = '';
	foreach ( $access as $cid => $a ) {
		$direct = isset( $map[ $cid ] );
		$src    = $direct ? (string) $map[ $cid ] : '';
		if ( 0 === strpos( $src, 'order:' ) ) {
			$from = 'Mua qua đơn <a href="' . esc_url( kh_qt_url( 'don-hang', (int) substr( $src, 6 ) ) ) . '">#' . (int) substr( $src, 6 ) . '</a>';
		} elseif ( $direct ) {
			$from = 'Quản trị mở tay';
		} else {
			$from = 'Nằm trong combo “' . esc_html( get_the_title( $a['via'] ) ) . '”';
		}
		$exp = $a['expires'] ? ( $a['active'] ? 'Hạn đến ' . kh_qt_date( $a['expires'] ) : 'Hết hạn ' . kh_qt_date( $a['expires'] ) ) : 'Học trọn đời';
		list( $done, $total ) = kh_qt_progress( $id, $cid );
		$pct  = $total ? (int) round( $done / $total * 100 ) : 0;
		$used = kh_views_used_count( $id, $cid );

		$list .= '<li class="qt-course-row' . ( $a['active'] ? '' : ' is-expired' ) . '"><div class="qt-course-info">';
		$list .= '<strong>' . esc_html( get_the_title( $cid ) ) . '</strong> ' . ( $a['active'] ? kh_qt_badge( 'Đang học', 'ok' ) : kh_qt_badge( 'Hết hạn', 'bad' ) );
		$list .= '<span class="qt-muted qt-block">' . $from . ' · ' . esc_html( $exp ) . '</span>';
		if ( $total ) {
			$list .= '<div class="qt-progress"><span style="width:' . (int) $pct . '%"></span></div><span class="qt-muted">Đã học ' . (int) $done . '/' . (int) $total . ' bài' . ( $used ? ' · <strong class="qt-warn">' . (int) $used . ' bài hết lượt xem</strong>' : '' ) . '</span>';
		}
		$list .= '</div><div class="qt-course-actions">';
		if ( $direct ) {
			$list .= kh_qt_action_button( 'course_renew', array( 'user_id' => $id, 'course_id' => $cid ), kh_qt_icon( 'refresh-cw', 15 ) . ' Gia hạn', 'qt-btn qt-btn-ghost qt-btn-sm', 'Gia hạn khóa “' . get_the_title( $cid ) . '” từ hôm nay (' . kh_duration_label( $cid ) . ')? Tiến độ học được giữ nguyên.' );
		}
		if ( $limit && $used ) {
			$list .= kh_qt_action_button( 'course_views', array( 'user_id' => $id, 'course_id' => $cid ), kh_qt_icon( 'rotate-ccw', 15 ) . ' Cho xem lại', 'qt-btn qt-btn-ghost qt-btn-sm', 'Đặt lại lượt xem khóa “' . get_the_title( $cid ) . '”? Học viên được xem mỗi bài thêm ' . $limit . ' lần.' );
		}
		if ( $direct ) {
			$list .= kh_qt_action_button( 'course_revoke', array( 'user_id' => $id, 'course_id' => $cid ), kh_qt_icon( 'x', 15 ) . ' Thu hồi', 'qt-btn qt-btn-ghost qt-btn-sm is-danger', 'Thu hồi khóa “' . get_the_title( $cid ) . '” của ' . $u->display_name . '? Học viên sẽ không vào học được nữa.' );
		}
		$list .= '</div></li>';
	}
	$courses_html = $list ? '<ul class="qt-course-list">' . $list . '</ul>' : kh_qt_empty( 'Học viên chưa có khóa học nào.', 'book-open' );

	// Mở thêm khóa.
	$grant  = '<form method="post" class="qt-form">' . kh_qt_form_fields( 'course_grant' ) . '<input type="hidden" name="user_id" value="' . (int) $id . '">';
	$grant .= kh_qt_course_checks( 'courses', array_map( 'intval', array_keys( $map ) ) );
	$grant .= '<div class="qt-form-foot"><button class="qt-btn qt-btn-sm" type="submit">' . kh_qt_icon( 'plus', 16 ) . ' Mở khóa đã chọn</button></div></form>';

	// Sửa thông tin.
	$edit  = '<form method="post" class="qt-form">' . kh_qt_form_fields( 'student_update' ) . '<input type="hidden" name="user_id" value="' . (int) $id . '">';
	$edit .= kh_qt_field( 'name', 'Họ và tên', $u->display_name, array( 'required' => true ) );
	$edit .= kh_qt_field( 'phone', 'Số điện thoại / Zalo', $phone, array( 'type' => 'tel' ) );
	$edit .= kh_qt_field( 'email', 'Email đăng nhập', $u->user_email, array( 'type' => 'email', 'required' => true ) );
	$edit .= '<div class="qt-form-foot"><button class="qt-btn qt-btn-sm" type="submit">' . kh_qt_icon( 'save', 16 ) . ' Lưu</button></div></form>';
	$edit .= '<hr class="qt-hr">' . kh_qt_action_button( 'student_reset', array( 'user_id' => $id ), kh_qt_icon( 'mail', 15 ) . ' Gửi email đặt lại mật khẩu', 'qt-btn qt-btn-ghost qt-btn-sm', 'Gửi email đặt lại mật khẩu tới ' . $u->user_email . '?' );

	// Đơn hàng của học viên.
	$orders_html = '';
	if ( function_exists( 'wc_get_orders' ) && current_user_can( 'edit_shop_orders' ) ) {
		$orders = wc_get_orders( array( 'customer_id' => $id, 'limit' => 10, 'orderby' => 'date', 'order' => 'DESC' ) );
		$ol     = '';
		foreach ( $orders as $o ) {
			$st  = kh_qt_order_status( $o->get_status() );
			$ol .= '<li><div class="qt-list-main"><a href="' . esc_url( kh_qt_url( 'don-hang', $o->get_id() ) ) . '"><strong>#' . esc_html( $o->get_order_number() ) . '</strong></a><span>' . esc_html( implode( ', ', kh_qt_order_titles( $o ) ) ) . '</span><span class="qt-muted">' . esc_html( kh_qt_date( $o->get_date_created() ) ) . '</span></div><div class="qt-list-side"><strong>' . wp_kses_post( wc_price( $o->get_total() ) ) . '</strong>' . kh_qt_badge( $st[0], $st[1] ) . '</div></li>';
		}
		$orders_html = kh_qt_card( 'Đơn hàng', $ol ? '<ul class="qt-list">' . $ol . '</ul>' : kh_qt_empty( 'Chưa có đơn hàng.', 'receipt' ) );
	}

	$html  = kh_qt_card( '', $head, '', 'is-profile' );
	$html .= '<div class="qt-grid is-wide-left">';
	$html .= '<div class="qt-stack">' . kh_qt_card( 'Khóa học', $courses_html ) . kh_qt_card( 'Mở thêm khóa học', $grant ) . '</div>';
	$html .= '<div class="qt-stack">' . kh_qt_card( 'Thông tin học viên', $edit ) . $orders_html . '</div>';
	$html .= '</div>';

	return array(
		'title' => $u->display_name,
		'back'  => kh_qt_url( 'hoc-vien' ),
		'html'  => $html,
	);
}

/* ==========================================================================
 * Thao tác
 * ======================================================================= */

/**
 * Tách "Nguyễn Văn An" → [ first: An, last: Nguyễn Văn ].
 *
 * @param string $full Họ tên.
 * @return array
 */
function kh_qt_split_name( $full ) {
	$parts = preg_split( '/\s+/u', trim( $full ) );
	$first = array_pop( $parts );
	return array( (string) $first, implode( ' ', $parts ) );
}

/**
 * Tạo học viên.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_student_create( $p ) {
	if ( ! current_user_can( 'create_users' ) ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền tạo học viên.' );
	}
	$name  = sanitize_text_field( isset( $p['name'] ) ? $p['name'] : '' );
	$email = sanitize_email( isset( $p['email'] ) ? $p['email'] : '' );
	$phone = sanitize_text_field( isset( $p['phone'] ) ? $p['phone'] : '' );
	$pass  = isset( $p['password'] ) ? (string) $p['password'] : '';
	if ( '' === $name || ! is_email( $email ) ) {
		return array( 'ok' => false, 'msg' => 'Vui lòng nhập họ tên và email đúng.' );
	}
	if ( email_exists( $email ) ) {
		$other = get_user_by( 'email', $email );
		return array( 'ok' => false, 'msg' => 'Email này đã có tài khoản (' . $other->display_name . '). Mở trang học viên đó để thêm khóa học.', 'go' => kh_qt_url( 'hoc-vien', $other->ID ) );
	}
	list( $first, $last ) = kh_qt_split_name( $name );
	$login = sanitize_user( current( explode( '@', $email ) ), true );
	$base  = $login ? $login : 'hocvien';
	$i     = 1;
	while ( username_exists( $login ) || '' === $login ) {
		$login = $base . ( ++$i );
	}
	$uid = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => '' !== $pass ? $pass : wp_generate_password( 16 ),
			'display_name' => $name,
			'first_name'   => $first,
			'last_name'    => $last,
			'role'         => get_role( 'customer' ) ? 'customer' : 'subscriber',
		)
	);
	if ( is_wp_error( $uid ) ) {
		return array( 'ok' => false, 'msg' => 'Chưa tạo được: ' . $uid->get_error_message() );
	}
	update_user_meta( $uid, 'billing_first_name', $first );
	update_user_meta( $uid, 'billing_last_name', $last );
	update_user_meta( $uid, 'billing_email', $email );
	if ( $phone ) {
		update_user_meta( $uid, 'billing_phone', $phone );
	}
	$opened = 0;
	foreach ( isset( $p['courses'] ) ? array_map( 'absint', (array) $p['courses'] ) : array() as $cid ) {
		if ( 'khoa_hoc' === get_post_type( $cid ) && kh_grant_course( $uid, $cid, 'admin' ) ) {
			++$opened;
		}
	}
	if ( ! empty( $p['notify'] ) ) {
		wp_new_user_notification( $uid, null, 'user' );
	}
	return array(
		'ok'  => true,
		'msg' => 'Đã tạo học viên ' . $name . ( $opened ? ' và mở ' . $opened . ' khóa học' : '' ) . ( ! empty( $p['notify'] ) ? '. Đã gửi email cho học viên.' : '.' ),
		'go'  => kh_qt_url( 'hoc-vien', $uid ),
	);
}

/**
 * Sửa thông tin học viên.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_student_update( $p ) {
	$uid = isset( $p['user_id'] ) ? absint( $p['user_id'] ) : 0;
	if ( ! $uid || ! current_user_can( 'edit_user', $uid ) ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền sửa học viên này.' );
	}
	$name  = sanitize_text_field( isset( $p['name'] ) ? $p['name'] : '' );
	$email = sanitize_email( isset( $p['email'] ) ? $p['email'] : '' );
	$phone = sanitize_text_field( isset( $p['phone'] ) ? $p['phone'] : '' );
	if ( '' === $name || ! is_email( $email ) ) {
		return array( 'ok' => false, 'msg' => 'Vui lòng nhập họ tên và email đúng.' );
	}
	$owner = email_exists( $email );
	if ( $owner && (int) $owner !== $uid ) {
		return array( 'ok' => false, 'msg' => 'Email này đang thuộc về tài khoản khác.' );
	}
	list( $first, $last ) = kh_qt_split_name( $name );
	$res = wp_update_user( array( 'ID' => $uid, 'display_name' => $name, 'first_name' => $first, 'last_name' => $last, 'user_email' => $email ) );
	if ( is_wp_error( $res ) ) {
		return array( 'ok' => false, 'msg' => $res->get_error_message() );
	}
	update_user_meta( $uid, 'billing_first_name', $first );
	update_user_meta( $uid, 'billing_last_name', $last );
	update_user_meta( $uid, 'billing_phone', $phone );
	return array( 'ok' => true, 'msg' => 'Đã lưu thông tin học viên.' );
}

/**
 * Gửi email đặt lại mật khẩu.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_student_reset( $p ) {
	$u = get_userdata( isset( $p['user_id'] ) ? absint( $p['user_id'] ) : 0 );
	if ( ! $u || ! current_user_can( 'edit_user', $u->ID ) ) {
		return array( 'ok' => false, 'msg' => 'Không gửi được email.' );
	}
	$res = retrieve_password( $u->user_login );
	return is_wp_error( $res ) ? array( 'ok' => false, 'msg' => 'Không gửi được email: ' . $res->get_error_message() ) : array( 'ok' => true, 'msg' => 'Đã gửi email đặt lại mật khẩu tới ' . $u->user_email . '.' );
}

/**
 * Kiểm tra quyền + lấy user/course từ form.
 *
 * @param array $p Dữ liệu.
 * @return array|false [ user_id, course_id ]
 */
function kh_qt_uc( $p ) {
	$uid = isset( $p['user_id'] ) ? absint( $p['user_id'] ) : 0;
	$cid = isset( $p['course_id'] ) ? absint( $p['course_id'] ) : 0;
	if ( ! $uid || ! get_userdata( $uid ) || ! current_user_can( 'edit_user', $uid ) || 'khoa_hoc' !== get_post_type( $cid ) ) {
		return false;
	}
	return array( $uid, $cid );
}

/**
 * Mở khóa học (nhiều khóa).
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_course_grant( $p ) {
	$uid = isset( $p['user_id'] ) ? absint( $p['user_id'] ) : 0;
	if ( ! $uid || ! get_userdata( $uid ) || ! current_user_can( 'edit_user', $uid ) ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền.' );
	}
	$ids = isset( $p['courses'] ) ? array_map( 'absint', (array) $p['courses'] ) : array();
	if ( ! $ids ) {
		return array( 'ok' => false, 'msg' => 'Bạn chưa chọn khóa học nào.' );
	}
	$names = array();
	foreach ( $ids as $cid ) {
		if ( 'khoa_hoc' === get_post_type( $cid ) && kh_grant_course( $uid, $cid, 'admin' ) ) {
			$names[] = get_the_title( $cid );
		}
	}
	return $names ? array( 'ok' => true, 'msg' => 'Đã mở: ' . implode( ', ', $names ) . '.' ) : array( 'ok' => false, 'msg' => 'Các khóa đã chọn đang còn hạn, không cần mở lại.' );
}

/**
 * Gia hạn từ hôm nay.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_course_renew( $p ) {
	$uc = kh_qt_uc( $p );
	if ( ! $uc ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền.' );
	}
	kh_renew_course( $uc[0], $uc[1] );
	$a = kh_access_info( $uc[1], $uc[0] );
	return array( 'ok' => true, 'msg' => 'Đã gia hạn “' . get_the_title( $uc[1] ) . '”' . ( $a && $a['expires'] ? ' đến ' . kh_qt_date( $a['expires'] ) : ' (trọn đời)' ) . '.' );
}

/**
 * Đặt lại lượt xem (cả combo).
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_course_views( $p ) {
	$uc = kh_qt_uc( $p );
	if ( ! $uc ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền.' );
	}
	foreach ( array_merge( array( $uc[1] ), kh_course_bundle( $uc[1] ) ) as $id ) {
		kh_views_reset( $uc[0], $id );
	}
	return array( 'ok' => true, 'msg' => 'Đã đặt lại lượt xem “' . get_the_title( $uc[1] ) . '”.' );
}

/**
 * Thu hồi khóa học.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_course_revoke( $p ) {
	$uc = kh_qt_uc( $p );
	if ( ! $uc ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền.' );
	}
	kh_revoke_course( $uc[0], $uc[1] );
	return array( 'ok' => true, 'msg' => 'Đã thu hồi “' . get_the_title( $uc[1] ) . '”.' );
}
