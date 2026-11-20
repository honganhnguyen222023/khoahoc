<?php
/**
 * Quản trị → Liên hệ: tin nhắn từ form tư vấn (post type kh_lien_he).
 * Trạng thái: pending = mới, private = đã xử lý.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Trang Liên hệ.
 *
 * @return array
 */
function kh_qt_page_contacts() {
	$tabs = array(
		''      => array( 'Chưa xử lý', array( 'pending' ) ),
		'da-xu-ly' => array( 'Đã xử lý', array( 'private', 'publish' ) ),
	);
	$f    = kh_qt_get( 'loc' );
	$f    = isset( $tabs[ $f ] ) ? $f : '';
	$page = kh_qt_paged();
	$q    = new WP_Query(
		array(
			'post_type'      => 'kh_lien_he',
			'post_status'    => $tabs[ $f ][1],
			'posts_per_page' => 20,
			'paged'          => $page,
		)
	);
	$counts = wp_count_posts( 'kh_lien_he' );

	$html = '<div class="qt-toolbar"><nav class="qt-tabs">';
	foreach ( $tabs as $slug => $t ) {
		$n = 0;
		foreach ( $t[1] as $st ) {
			$n += isset( $counts->$st ) ? (int) $counts->$st : 0;
		}
		$html .= '<a class="' . ( $slug === $f ? 'is-active' : '' ) . '" href="' . esc_url( kh_qt_url( 'lien-he', '', $slug ? array( 'loc' => $slug ) : array() ) ) . '">' . esc_html( $t[0] ) . ' <em>' . (int) $n . '</em></a>';
	}
	$html .= '</nav></div>';

	if ( ! $q->posts ) {
		$html .= kh_qt_card( '', kh_qt_empty( $f ? 'Chưa có liên hệ nào đã xử lý.' : 'Không còn liên hệ nào cần xử lý.', 'circle-check' ) );
		return array( 'title' => 'Liên hệ', 'html' => $html );
	}

	$list = '<ul class="qt-inbox">';
	foreach ( $q->posts as $c ) {
		$phone   = (string) get_post_meta( $c->ID, '_kh_phone', true );
		$email   = (string) get_post_meta( $c->ID, '_kh_email', true );
		$topic   = (string) get_post_meta( $c->ID, '_kh_topic', true );
		$msg     = (string) get_post_meta( $c->ID, '_kh_message', true );
		$source  = (string) get_post_meta( $c->ID, '_kh_source', true );
		$is_new  = 'pending' === $c->post_status;

		$list .= '<li class="' . ( $is_new ? 'is-new' : '' ) . '"><details><summary>';
		$list .= '<div class="qt-inbox-who"><strong>' . esc_html( $c->post_title ) . '</strong>' . ( $topic ? kh_qt_badge( $topic, 'info' ) : '' ) . '</div>';
		$list .= '<div class="qt-inbox-msg">' . esc_html( $msg ? wp_trim_words( $msg, 18 ) : '(không có lời nhắn)' ) . '</div>';
		$list .= '<div class="qt-inbox-time qt-muted">' . esc_html( kh_qt_ago( get_post_time( 'U', true, $c ) ) ) . '</div>';
		$list .= '</summary><div class="qt-inbox-body">';
		$list .= '<dl class="qt-dl">';
		$list .= '<dt>Điện thoại</dt><dd>' . esc_html( $phone ? $phone : '—' ) . ' ' . kh_qt_phone_links( $phone ) . '</dd>';
		$list .= '<dt>Email</dt><dd>' . ( $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—' ) . '</dd>';
		if ( $topic ) {
			$list .= '<dt>Quan tâm</dt><dd>' . esc_html( $topic ) . '</dd>';
		}
		$list .= '<dt>Lời nhắn</dt><dd>' . nl2br( esc_html( $msg ? $msg : '—' ) ) . '</dd>';
		$list .= '<dt>Gửi lúc</dt><dd>' . esc_html( kh_qt_date( get_post_time( 'U', true, $c ), true ) ) . ( $source ? ' · từ trang <a href="' . esc_url( $source ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $source, PHP_URL_PATH ) ? wp_parse_url( $source, PHP_URL_PATH ) : '/' ) . '</a>' : '' ) . '</dd>';
		$list .= '</dl><div class="qt-btn-row">';
		if ( $is_new ) {
			$list .= kh_qt_action_button( 'contact_done', array( 'contact_id' => $c->ID ), kh_qt_icon( 'check', 16 ) . ' Đã xử lý', 'qt-btn qt-btn-sm' );
		} else {
			$list .= kh_qt_action_button( 'contact_undo', array( 'contact_id' => $c->ID ), kh_qt_icon( 'rotate-ccw', 15 ) . ' Chuyển về chưa xử lý', 'qt-btn qt-btn-ghost qt-btn-sm' );
		}
		if ( $email && current_user_can( 'create_users' ) && ! email_exists( $email ) ) {
			$list .= '<a class="qt-btn qt-btn-ghost qt-btn-sm" href="' . esc_url( kh_qt_url( 'hoc-vien', 'moi', array( 'ten' => $c->post_title, 'email' => $email, 'sdt' => $phone ) ) ) . '">' . kh_qt_icon( 'user-plus', 15 ) . ' Tạo tài khoản học viên</a>';
		}
		$list .= kh_qt_action_button( 'contact_trash', array( 'contact_id' => $c->ID ), kh_qt_icon( 'trash-2', 15 ) . ' Xóa', 'qt-btn qt-btn-ghost qt-btn-sm is-danger', 'Xóa liên hệ của ' . $c->post_title . '?' );
		$list .= '</div></div></details></li>';
	}
	$list .= '</ul>';
	$html .= kh_qt_card( '', $list . kh_qt_pager( $page, (int) $q->max_num_pages, remove_query_arg( 'trang' ) ), '', 'is-flush' );

	return array(
		'title' => 'Liên hệ',
		'html'  => $html,
	);
}

/**
 * Lấy liên hệ từ form + kiểm tra quyền.
 *
 * @param array $p Dữ liệu.
 * @return int
 */
function kh_qt_contact_id( $p ) {
	$id = isset( $p['contact_id'] ) ? absint( $p['contact_id'] ) : 0;
	return 'kh_lien_he' === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ? $id : 0;
}

/**
 * Đánh dấu đã xử lý.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_contact_done( $p ) {
	$id = kh_qt_contact_id( $p );
	if ( ! $id ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền.' );
	}
	wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );
	return array( 'ok' => true, 'msg' => 'Đã chuyển liên hệ của ' . get_the_title( $id ) . ' sang "Đã xử lý".' );
}

/**
 * Chuyển về chưa xử lý.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_contact_undo( $p ) {
	$id = kh_qt_contact_id( $p );
	if ( ! $id ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền.' );
	}
	wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) );
	return array( 'ok' => true, 'msg' => 'Đã chuyển về "Chưa xử lý".' );
}

/**
 * Xóa liên hệ.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_contact_trash( $p ) {
	$id = kh_qt_contact_id( $p );
	if ( ! $id || ! current_user_can( 'delete_post', $id ) ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền xóa.' );
	}
	wp_trash_post( $id );
	return array( 'ok' => true, 'msg' => 'Đã xóa liên hệ.' );
}
