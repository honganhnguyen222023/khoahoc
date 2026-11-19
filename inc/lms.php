<?php
/**
 * Học viên & quyền xem bài học.
 *
 * - Học viên đã mua khóa (hoặc được admin mở) xem được mọi bài của khóa.
 * - Người đã đăng nhập nhưng chưa mua: chỉ xem bài học thử (bài đầu tiên + bài
 *   được đánh dấu "Cho học thử"). Các bài khác hiện icon khóa.
 * - Link video của bài bị khóa không bao giờ được in ra HTML.
 * - Học viên đăng nhập / đăng xuất: về trang chủ, không vào trang quản trị.
 *
 * Khóa đã mở lưu ở user meta `_kh_courses` = [ course_id => nguồn ('order:123' | 'admin') ].
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/* ==========================================================================
 * Quyền truy cập
 * ======================================================================= */

/**
 * Nhân viên (quản trị, biên tập, tác giả…) – được vào wp-admin, xem mọi bài.
 *
 * @param WP_User|int|null $user User.
 * @return bool
 */
function kh_is_staff( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return $user && user_can( $user, 'edit_posts' );
}

/**
 * Khóa học user đã được mở trực tiếp (mua hoặc admin cấp), kể cả khóa đã hết hạn.
 *
 * @param int $user_id User ID (0 = người đang đăng nhập).
 * @return array course_id => nguồn
 */
function kh_user_course_map( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}
	$map = get_user_meta( $user_id, '_kh_courses', true );
	return is_array( $map ) ? $map : array();
}

/**
 * Ngày mở từng khóa: course_id => timestamp (dùng để tính hạn học).
 *
 * @param int $user_id User ID.
 * @return array
 */
function kh_user_course_since( $user_id ) {
	$since = get_user_meta( $user_id, '_kh_course_since', true );
	$since = is_array( $since ) ? $since : array();
	$dirty = false;
	foreach ( array_keys( kh_user_course_map( $user_id ) ) as $cid ) {
		if ( empty( $since[ $cid ] ) ) {
			$since[ $cid ] = time(); // Khóa mở trước khi có thời hạn: tính từ hôm nay.
			$dirty         = true;
		}
	}
	if ( $dirty ) {
		update_user_meta( $user_id, '_kh_course_since', $since );
	}
	return $since;
}

/**
 * Các khóa nằm trong một combo (trường "Combo gồm các khóa").
 *
 * @param int $course_id Course ID.
 * @return int[]
 */
function kh_course_bundle( $course_id ) {
	$raw = (string) get_post_meta( $course_id, '_kh_bundle', true );
	return array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
}

/**
 * Thời hạn học của khóa, tính bằng tháng (0 = trọn đời).
 * Ô "Thời hạn học" trong khóa; để trống thì theo Cài đặt website → Học viên & bài học.
 *
 * @param int $course_id Course ID.
 * @return int
 */
function kh_course_months( $course_id ) {
	$v = (string) get_post_meta( $course_id, '_kh_duration', true );
	if ( '' === $v ) {
		$v = (string) kh_opt( 'course_duration' );
	}
	return 'life' === $v ? 0 : max( 0, (int) $v );
}

/**
 * Chữ mô tả thời hạn học: "Trọn đời" / "12 tháng".
 *
 * @param int $course_id Course ID.
 * @return string
 */
function kh_duration_label( $course_id ) {
	$m = kh_course_months( $course_id );
	return $m ? sprintf( '%d tháng', $m ) : 'Trọn đời';
}

/**
 * Xóa bộ nhớ tạm quyền truy cập (sau khi mở / thu hồi / gia hạn).
 */
function kh_access_flush() {
	$GLOBALS['kh_access_cache'] = array();
}

/**
 * Quyền của user với từng khóa: [ course_id => [ active, expires (0 = trọn đời), via ] ].
 * Gồm khóa mua trực tiếp và khóa con của combo; hạn của khóa con theo combo.
 *
 * @param int $user_id User ID.
 * @return array
 */
function kh_access_list( $user_id ) {
	$user_id = (int) $user_id;
	if ( isset( $GLOBALS['kh_access_cache'][ $user_id ] ) ) {
		return $GLOBALS['kh_access_cache'][ $user_id ];
	}
	$out   = array();
	$since = kh_user_course_since( $user_id );
	$now   = time();
	foreach ( array_keys( kh_user_course_map( $user_id ) ) as $cid ) {
		$cid = (int) $cid;
		if ( 'khoa_hoc' !== get_post_type( $cid ) ) {
			continue;
		}
		$months  = kh_course_months( $cid );
		$expires = $months ? strtotime( '+' . $months . ' months', (int) $since[ $cid ] ) : 0;
		$active  = ! $expires || $expires > $now;
		foreach ( array_merge( array( $cid ), kh_course_bundle( $cid ) ) as $id ) {
			if ( 'khoa_hoc' !== get_post_type( $id ) ) {
				continue;
			}
			$cur = isset( $out[ $id ] ) ? $out[ $id ] : null;
			// Giữ quyền tốt nhất: còn hạn > hết hạn; trọn đời > có hạn; hạn xa hơn > gần hơn.
			$better = ! $cur
				|| ( $active && ! $cur['active'] )
				|| ( $active === $cur['active'] && ( 0 === $expires ? 0 !== $cur['expires'] : ( 0 !== $cur['expires'] && $expires > $cur['expires'] ) ) );
			if ( $better ) {
				$out[ $id ] = array( 'active' => $active, 'expires' => $expires, 'via' => $cid );
			}
		}
	}
	$GLOBALS['kh_access_cache'][ $user_id ] = $out;
	return $out;
}

/**
 * Quyền của user với 1 khóa, hoặc null nếu chưa từng mở.
 *
 * @param int $course_id Course ID.
 * @param int $user_id   User ID.
 * @return array|null [ active, expires, via ]
 */
function kh_access_info( $course_id, $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return null;
	}
	$list = kh_access_list( $user_id );
	return isset( $list[ (int) $course_id ] ) ? $list[ (int) $course_id ] : null;
}

/**
 * Khóa user đang học được (còn hạn).
 *
 * @param int $user_id User ID.
 * @return int[]
 */
function kh_user_courses( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}
	return array_keys( array_filter( kh_access_list( $user_id ), function ( $a ) { return $a['active']; } ) );
}

/**
 * User có quyền học toàn bộ khóa này không (còn hạn).
 *
 * @param int $course_id Course ID.
 * @param int $user_id   User ID.
 * @return bool
 */
function kh_has_access( $course_id, $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id || ! $course_id ) {
		return false;
	}
	if ( user_can( $user_id, 'edit_post', $course_id ) ) {
		return true;
	}
	$info = kh_access_info( $course_id, $user_id );
	return $info && $info['active'];
}

/**
 * Khóa user từng có nhưng đã hết hạn.
 *
 * @param int $course_id Course ID.
 * @param int $user_id   User ID.
 * @return bool
 */
function kh_access_expired( $course_id, $user_id = 0 ) {
	$info = kh_access_info( $course_id, $user_id );
	return $info && ! $info['active'] && ! kh_has_access( $course_id, $user_id );
}

/**
 * Mở khóa học cho user. Khóa đã hết hạn thì mở lại từ đầu (xóa tiến độ cũ).
 *
 * @param int    $user_id   User ID.
 * @param int    $course_id Course ID.
 * @param string $source    'order:123' hoặc 'admin'.
 * @return bool Có mở mới không.
 */
function kh_grant_course( $user_id, $course_id, $source = 'admin' ) {
	$course_id = (int) $course_id;
	$map       = kh_user_course_map( $user_id );
	$since     = kh_user_course_since( $user_id );
	if ( isset( $map[ $course_id ] ) ) {
		$months  = kh_course_months( $course_id );
		$expires = $months ? strtotime( '+' . $months . ' months', (int) $since[ $course_id ] ) : 0;
		if ( ! $expires || $expires > time() ) {
			return false; // Còn hạn: giữ nguyên.
		}
	}
	$map[ $course_id ]   = (string) $source;
	$since[ $course_id ] = time();
	update_user_meta( $user_id, '_kh_courses', $map );
	update_user_meta( $user_id, '_kh_course_since', $since );
	// Mua lại / mở lại: học từ đầu.
	foreach ( array_merge( array( $course_id ), kh_course_bundle( $course_id ) ) as $id ) {
		kh_reset_progress( $user_id, $id );
	}
	kh_access_flush();
	do_action( 'kh_course_granted', $user_id, $course_id, $source );
	return true;
}

/**
 * Gia hạn: tính lại thời hạn học từ hôm nay, giữ tiến độ đã học.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 */
function kh_renew_course( $user_id, $course_id ) {
	$since = kh_user_course_since( $user_id );
	if ( isset( kh_user_course_map( $user_id )[ $course_id ] ) ) {
		$since[ (int) $course_id ] = time();
		update_user_meta( $user_id, '_kh_course_since', $since );
		kh_access_flush();
	}
}

/**
 * Thu hồi khóa học. Có $source thì chỉ thu hồi khi khóa được mở từ đúng nguồn đó
 * (hủy đơn A không làm mất khóa đã mua ở đơn B).
 *
 * @param int         $user_id   User ID.
 * @param int         $course_id Course ID.
 * @param string|null $source    Nguồn.
 */
function kh_revoke_course( $user_id, $course_id, $source = null ) {
	$map = kh_user_course_map( $user_id );
	if ( ! isset( $map[ $course_id ] ) || ( null !== $source && $map[ $course_id ] !== $source ) ) {
		return;
	}
	unset( $map[ $course_id ] );
	update_user_meta( $user_id, '_kh_courses', $map );
	$since = kh_user_course_since( $user_id );
	unset( $since[ $course_id ] );
	update_user_meta( $user_id, '_kh_course_since', $since );
	kh_access_flush();
	do_action( 'kh_course_revoked', $user_id, $course_id, $source );
}

/* ==========================================================================
 * Tiến độ học (học xong bài trước mới mở bài sau)
 * ======================================================================= */

/**
 * Các bài đã học xong: [ số bài => thời điểm ].
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return array
 */
function kh_progress( $user_id, $course_id ) {
	$all = get_user_meta( $user_id, '_kh_progress', true );
	return is_array( $all ) && isset( $all[ $course_id ] ) && is_array( $all[ $course_id ] ) ? $all[ $course_id ] : array();
}

/**
 * Đánh dấu học xong 1 bài.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @param int $n         Số bài.
 */
function kh_mark_done( $user_id, $course_id, $n ) {
	$all = get_user_meta( $user_id, '_kh_progress', true );
	$all = is_array( $all ) ? $all : array();
	$all[ (int) $course_id ][ (int) $n ] = time();
	update_user_meta( $user_id, '_kh_progress', $all );
}

/**
 * Xóa tiến độ 1 khóa (khi mua lại).
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 */
function kh_reset_progress( $user_id, $course_id ) {
	$all = get_user_meta( $user_id, '_kh_progress', true );
	if ( is_array( $all ) && isset( $all[ $course_id ] ) ) {
		unset( $all[ $course_id ] );
		update_user_meta( $user_id, '_kh_progress', $all );
	}
	$open = get_user_meta( $user_id, '_kh_lesson_open', true );
	if ( is_array( $open ) && isset( $open[ $course_id ] ) ) {
		unset( $open[ $course_id ] );
		update_user_meta( $user_id, '_kh_lesson_open', $open );
	}
}

/**
 * Có bắt buộc học tuần tự không (Cài đặt website → Học viên & bài học).
 *
 * @return bool
 */
function kh_sequential() {
	return '1' === (string) kh_opt( 'sequential' );
}

/**
 * Bài xa nhất user được học: bài đầu tiên chưa học xong.
 * Bài chưa có video/tài liệu không chặn đường.
 *
 * @param int $course_id Course ID.
 * @param int $user_id   User ID.
 * @return int Số bài (PHP_INT_MAX = đã học xong hết).
 */
function kh_first_open_lesson( $course_id, $user_id ) {
	$done = kh_progress( $user_id, $course_id );
	foreach ( kh_course_lessons( $course_id ) as $n => $l ) {
		if ( $l['src'] && empty( $done[ $n ] ) ) {
			return $n;
		}
	}
	return PHP_INT_MAX;
}

/**
 * Thời lượng ghi ở bài học ("10:19", "1:02:30") ra giây.
 *
 * @param string $text Thời lượng.
 * @return int
 */
function kh_duration_seconds( $text ) {
	$parts = array_map( 'intval', explode( ':', preg_replace( '/[^\d:]/', '', (string) $text ) ) );
	$sec   = 0;
	foreach ( $parts as $p ) {
		$sec = $sec * 60 + $p;
	}
	return count( $parts ) > 1 ? $sec : 0;
}

/**
 * Tỷ lệ phải xem để tính là học xong (0.1 – 1).
 *
 * @return float
 */
function kh_watch_need() {
	$p = (int) kh_opt( 'watch_percent' );
	return max( 10, min( 100, $p ? $p : 80 ) ) / 100;
}

/**
 * Thời gian tối thiểu (giây) phải mở bài trước khi được tính là học xong:
 * đủ để xem phần cần xem ở tốc độ 2x (chống bấm "học xong" ngay).
 *
 * @param array $lesson Lesson.
 * @return int
 */
function kh_lesson_min_seconds( $lesson ) {
	return (int) floor( kh_duration_seconds( $lesson['duration'] ) * kh_watch_need() * 0.45 );
}

// Bản cũ mặc định 90%: chuyển sang 80% (1 lần).
add_action(
	'init',
	function () {
		if ( get_option( 'kh_watch80' ) ) {
			return;
		}
		$opts = get_option( 'kh_settings' );
		if ( is_array( $opts ) && isset( $opts['watch_percent'] ) && '90' === (string) $opts['watch_percent'] ) {
			$opts['watch_percent'] = '80';
			update_option( 'kh_settings', $opts );
		}
		update_option( 'kh_watch80', 1 );
	}
);

/* ==========================================================================
 * Lượt học thử (tài khoản chưa mua)
 * ======================================================================= */

/**
 * Số lượt xem thử mỗi bài (0 = không giới hạn).
 *
 * @return int
 */
function kh_trial_limit() {
	return max( 0, (int) kh_opt( 'trial_views' ) );
}

/**
 * Lượt xem thử đã dùng: [ count, last ].
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @param int $n         Số bài.
 * @return array
 */
function kh_trial_record( $user_id, $course_id, $n ) {
	$all = get_user_meta( $user_id, '_kh_trial_views', true );
	$r   = is_array( $all ) && isset( $all[ $course_id ][ $n ] ) ? $all[ $course_id ][ $n ] : array();
	return array(
		'count' => isset( $r['count'] ) ? (int) $r['count'] : 0,
		'last'  => isset( $r['last'] ) ? (int) $r['last'] : 0,
	);
}

/**
 * Tải lại trang trong khoảng này (giây) vẫn tính là cùng 1 lượt xem.
 *
 * @return int
 */
function kh_trial_session() {
	return (int) apply_filters( 'kh_trial_session', 30 * MINUTE_IN_SECONDS );
}

/**
 * Còn được xem thử bài này không.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @param int $n         Số bài.
 * @return bool
 */
function kh_trial_allowed( $user_id, $course_id, $n ) {
	$limit = kh_trial_limit();
	if ( ! $limit ) {
		return true;
	}
	$r = kh_trial_record( $user_id, $course_id, $n );
	return $r['count'] < $limit || ( time() - $r['last'] ) < kh_trial_session();
}

/**
 * Số lượt xem thử còn lại (không tính lượt đang xem).
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @param int $n         Số bài.
 * @return int -1 = không giới hạn.
 */
function kh_trial_left( $user_id, $course_id, $n ) {
	$limit = kh_trial_limit();
	if ( ! $limit ) {
		return -1;
	}
	return max( 0, $limit - kh_trial_record( $user_id, $course_id, $n )['count'] );
}

/**
 * Ghi 1 lượt xem thử (mở trang bài học). Tải lại trong 30 phút không tính thêm.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @param int $n         Số bài.
 */
function kh_trial_register_view( $user_id, $course_id, $n ) {
	$r = kh_trial_record( $user_id, $course_id, $n );
	if ( ( time() - $r['last'] ) < kh_trial_session() ) {
		return;
	}
	$all = get_user_meta( $user_id, '_kh_trial_views', true );
	$all = is_array( $all ) ? $all : array();
	$all[ (int) $course_id ][ (int) $n ] = array( 'count' => $r['count'] + 1, 'last' => time() );
	update_user_meta( $user_id, '_kh_trial_views', $all );
}

/* ==========================================================================
 * Trạng thái 1 bài học với người đang xem
 * ======================================================================= */

/**
 * Trạng thái bài học:
 *  ok        – xem được (học viên đã mua / quản trị)
 *  trial     – xem được, đang dùng lượt học thử
 *  login     – bài học thử, cần đăng nhập
 *  buy       – cần mua khóa
 *  expired   – khóa đã hết hạn, cần mua lại
 *  sequence  – cần học xong bài trước
 *  trial_used – đã hết lượt học thử
 *
 * @param int   $course_id Course ID.
 * @param array $lesson    Lesson (từ kh_course_lessons).
 * @return string
 */
function kh_lesson_state( $course_id, $lesson ) {
	$user_id = get_current_user_id();
	$free    = ! empty( $lesson['free'] );
	if ( ! $user_id ) {
		return $free ? 'login' : 'buy';
	}
	if ( kh_is_staff() && user_can( $user_id, 'edit_post', $course_id ) ) {
		return 'ok';
	}
	if ( kh_has_access( $course_id, $user_id ) ) {
		if ( kh_sequential() && $lesson['n'] > kh_first_open_lesson( $course_id, $user_id ) ) {
			return 'sequence';
		}
		return 'ok';
	}
	if ( kh_access_expired( $course_id, $user_id ) ) {
		return 'expired';
	}
	if ( $free ) {
		return kh_trial_allowed( $user_id, $course_id, $lesson['n'] ) ? 'trial' : 'trial_used';
	}
	return 'buy';
}

/* ==========================================================================
 * Bài học
 * ======================================================================= */

/**
 * Danh sách bài học của khóa, đánh số từ 1 theo đúng thứ tự trong giáo trình.
 *
 * @param int $course_id Course ID.
 * @return array[] [ n => [ n, title, duration, type, src, free, chapter ] ]
 */
function kh_course_lessons( $course_id ) {
	static $cache = array();
	$course_id = (int) $course_id;
	if ( isset( $cache[ $course_id ] ) ) {
		return $cache[ $course_id ];
	}
	$post  = get_post( $course_id );
	$list  = array();
	$intro = (string) get_post_meta( $course_id, '_kh_video', true );
	if ( $post ) {
		$walk = function ( $blocks, $chapter ) use ( &$walk, &$list, $intro ) {
			foreach ( $blocks as $b ) {
				if ( 'kh/chapter' === $b['blockName'] ) {
					$walk( $b['innerBlocks'], wp_strip_all_tags( isset( $b['attrs']['title'] ) ? $b['attrs']['title'] : '' ) );
				} elseif ( 'kh/lesson' === $b['blockName'] ) {
					$a      = $b['attrs'];
					$n      = count( $list ) + 1;
					$video  = isset( $a['video'] ) ? trim( (string) $a['video'] ) : '';
					$legacy = isset( $a['url'] ) ? trim( (string) $a['url'] ) : '';
					// Bản cũ: "Link học thử" = bài học thử.
					$src         = $video ? $video : $legacy;
					$list[ $n ] = array(
						'n'        => $n,
						'title'    => isset( $a['title'] ) ? $a['title'] : '',
						'duration' => isset( $a['duration'] ) ? $a['duration'] : '',
						'type'     => isset( $a['type'] ) && 'doc' === $a['type'] ? 'doc' : 'video',
						// "#..." (dữ liệu mẫu cũ) = phát video giới thiệu của khóa.
						'src'      => 0 === strpos( $src, '#' ) ? $intro : $src,
						'free'     => ! empty( $a['free'] ) || ( ! $video && $legacy ),
						'chapter'  => $chapter,
					);
				} elseif ( ! empty( $b['innerBlocks'] ) ) {
					$walk( $b['innerBlocks'], $chapter );
				}
			}
		};
		$walk( parse_blocks( $post->post_content ), '' );
		// Bài đầu tiên luôn được học thử.
		if ( isset( $list[1] ) ) {
			$list[1]['free'] = true;
		}
	}
	$cache[ $course_id ] = $list;
	return $list;
}

/**
 * Người đang xem có xem được bài này không.
 *
 * @param int   $course_id Course ID.
 * @param array $lesson    Lesson (từ kh_course_lessons).
 * @return bool
 */
function kh_can_view_lesson( $course_id, $lesson ) {
	return in_array( kh_lesson_state( $course_id, $lesson ), array( 'ok', 'trial' ), true );
}

/**
 * Cách hiển thị 1 dòng bài học (dùng chung cho giáo trình và cột bài học ở trang học).
 *
 * @param int   $course_id Course ID.
 * @param array $lesson    Lesson.
 * @return array [ state, class, icon, href, badge (HTML), title ]
 */
function kh_lesson_row_parts( $course_id, $lesson ) {
	$state = kh_lesson_state( $course_id, $lesson );
	$n     = $lesson['n'];
	$media = 'doc' === $lesson['type'] ? 'file-text' : 'play';
	$href  = kh_learn_url( $course_id, $n );
	$out   = array( 'state' => $state, 'class' => 'is-locked', 'icon' => 'lock', 'href' => $href, 'badge' => '', 'title' => '' );
	$badge = function ( $text, $mod = '' ) {
		return '<span class="kh-preview' . ( $mod ? ' ' . $mod : '' ) . '">' . esc_html( $text ) . '</span>';
	};
	switch ( $state ) {
		case 'ok':
			$done         = is_user_logged_in() && ! empty( kh_progress( get_current_user_id(), $course_id )[ $n ] );
			$out['class'] = 'is-open' . ( $done ? ' is-done' : '' );
			$out['icon']  = $done ? 'check' : $media;
			$out['title'] = $done ? 'Đã học xong' : '';
			break;
		case 'trial':
			$left         = kh_trial_left( get_current_user_id(), $course_id, $n );
			$out['class'] = 'is-open';
			$out['icon']  = $media;
			$out['badge'] = $badge( $left < 0 ? 'Học thử' : sprintf( 'Học thử · còn %d lượt', $left ) );
			break;
		case 'login':
			$out['class'] = 'is-open';
			$out['icon']  = $media;
			$out['href']  = kh_login_url( $href );
			$out['badge'] = $badge( 'Học thử' );
			break;
		case 'trial_used':
			$out['badge'] = $badge( 'Hết lượt học thử', 'is-muted' );
			$out['title'] = 'Bạn đã dùng hết lượt xem thử. Đăng ký khóa học để học tiếp.';
			break;
		case 'sequence':
			$out['title'] = 'Học xong bài trước để mở bài này';
			break;
		case 'expired':
			$out['title'] = 'Khóa học đã hết hạn. Mua lại để học từ đầu.';
			break;
		default:
			$out['title'] = 'Đăng ký khóa học để mở bài này';
	}
	return $out;
}

/**
 * Link trang học một bài.
 *
 * @param int $course_id Course ID.
 * @param int $n         Số thứ tự bài (0 = bài đầu tiên).
 * @return string
 */
function kh_learn_url( $course_id, $n = 0 ) {
	$lessons = kh_course_lessons( $course_id );
	if ( ! $lessons ) {
		// Combo không có bài riêng: tới danh sách "Khóa học của tôi".
		return kh_my_courses_url();
	}
	$n = $n && isset( $lessons[ $n ] ) ? (int) $n : (int) key( $lessons );
	return add_query_arg( 'bai', $n, get_permalink( $course_id ) );
}

/**
 * Số bài đang mở trên trang học (?bai=N), 0 nếu đang ở trang giới thiệu khóa.
 *
 * @return int
 */
function kh_learn_index() {
	if ( ! is_singular( 'khoa_hoc' ) || ! isset( $_GET['bai'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return 0;
	}
	$n = absint( $_GET['bai'] ); // phpcs:ignore WordPress.Security.NonceVerification
	return isset( kh_course_lessons( get_queried_object_id() )[ $n ] ) ? $n : 0;
}

/**
 * Trang tài khoản (đăng nhập / đăng ký / khóa học của tôi).
 *
 * @return string
 */
function kh_account_url() {
	if ( function_exists( 'wc_get_page_permalink' ) && wc_get_page_id( 'myaccount' ) > 0 ) {
		return wc_get_page_permalink( 'myaccount' );
	}
	$page = get_page_by_path( 'tai-khoan' );
	return $page ? get_permalink( $page ) : wp_login_url();
}

/**
 * Trang "Khóa học của tôi".
 *
 * @return string
 */
function kh_my_courses_url() {
	if ( function_exists( 'wc_get_account_endpoint_url' ) && wc_get_page_id( 'myaccount' ) > 0 ) {
		return wc_get_account_endpoint_url( 'khoa-hoc-cua-toi' );
	}
	return kh_account_url();
}

/**
 * Link đăng nhập, xong quay lại $back.
 *
 * @param string $back URL quay lại.
 * @return string
 */
function kh_login_url( $back = '' ) {
	$back = $back ? $back : home_url( add_query_arg( array() ) );
	if ( function_exists( 'wc_get_page_permalink' ) && wc_get_page_id( 'myaccount' ) > 0 ) {
		return add_query_arg( 'redirect_to', rawurlencode( $back ), wc_get_page_permalink( 'myaccount' ) );
	}
	return wp_login_url( $back );
}

/**
 * Đếm bài học trong lúc in giáo trình (để biết đang in bài số mấy).
 *
 * @param bool $reset Đặt lại về 0.
 * @return int
 */
function kh_lesson_counter( $reset = false ) {
	static $i = 0;
	if ( $reset ) {
		$i = 0;
		return 0;
	}
	return ++$i;
}
add_filter(
	'the_content',
	function ( $content ) {
		kh_lesson_counter( true );
		return $content;
	},
	1
);

/* ==========================================================================
 * Trình phát video
 * ======================================================================= */

/**
 * Khung phát video/tài liệu từ link: YouTube, Vimeo, file .mp4/.webm,
 * Google Drive, Bunny Stream hoặc bất kỳ link nhúng (iframe) nào.
 *
 * @param string $src   Link (hoặc mã <iframe> dán vào).
 * @param string $title Tiêu đề.
 * @return string HTML.
 */
function kh_video_embed( $src, $title = '' ) {
	$src = trim( (string) $src );
	if ( preg_match( '~<iframe[^>]+src=["\']([^"\']+)~i', $src, $m ) ) {
		$src = html_entity_decode( $m[1] );
	}
	if ( '' === $src ) {
		return '';
	}
	$iframe = function ( $url ) use ( $title ) {
		return sprintf(
			'<iframe src="%1$s" title="%2$s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>',
			esc_url( $url ),
			esc_attr( wp_strip_all_tags( $title ) )
		);
	};

	$yt = kh_youtube_id( $src );
	if ( $yt ) {
		// Trình phát YouTube in sẵn trong trang (youtube.com: bản "nocookie" bị màn hình đen
		// với một số video). Ảnh bìa làm nền nên lúc đang tải không bị đen trơn.
		return sprintf(
			'<iframe id="kh-yt-player" src="%1$s" title="%2$s" style="background:#000 url(%3$s) center/cover no-repeat" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>',
			// enablejsapi: để theme biết học viên đã xem được bao nhiêu (học xong mới mở bài sau).
			esc_url( 'https://www.youtube.com/embed/' . $yt . '?rel=0&playsinline=1&enablejsapi=1&origin=' . rawurlencode( untrailingslashit( home_url() ) ) ),
			esc_attr( wp_strip_all_tags( $title ) ),
			esc_url( 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' )
		);
	}
	// vimeo.com/123456 hoặc vimeo.com/123456/abcdef (video không công khai).
	if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)(?:/([0-9a-f]+))?~i', $src, $m ) && false === strpos( $src, 'player.vimeo.com' ) ) {
		$url = 'https://player.vimeo.com/video/' . $m[1];
		if ( ! empty( $m[2] ) ) {
			$url = add_query_arg( 'h', $m[2], $url );
		}
		return $iframe( $url );
	}
	// Google Drive: /file/d/ID/view → /preview.
	if ( preg_match( '~drive\.google\.com/file/d/([\w-]+)~', $src, $m ) ) {
		return $iframe( 'https://drive.google.com/file/d/' . $m[1] . '/preview' );
	}
	$path = (string) wp_parse_url( $src, PHP_URL_PATH );
	if ( preg_match( '~\.(mp4|webm|ogv|mov)$~i', $path ) ) {
		return sprintf(
			'<video src="%1$s" controls controlslist="nodownload" playsinline preload="metadata" oncontextmenu="return false"></video>',
			esc_url( $src )
		);
	}
	return $iframe( $src );
}

/**
 * Cách theo dõi tiến độ xem của một link: yt | vimeo | video | manual.
 *
 * @param string $src  Link.
 * @param string $type video | doc.
 * @return string
 */
function kh_video_track_type( $src, $type = 'video' ) {
	if ( 'doc' === $type ) {
		return 'manual';
	}
	if ( kh_youtube_id( $src ) ) {
		return 'yt';
	}
	if ( preg_match( '~vimeo\.com/~i', $src ) ) {
		return 'vimeo';
	}
	if ( preg_match( '~\.(mp4|webm|ogv|mov)$~i', (string) wp_parse_url( $src, PHP_URL_PATH ) ) ) {
		return 'video';
	}
	return 'manual';
}

/* ==========================================================================
 * Đăng nhập / đăng xuất: học viên về trang chủ, không vào wp-admin
 * ======================================================================= */

/**
 * Link quay lại hợp lệ (cùng website, không phải wp-admin).
 *
 * @param string $url URL.
 * @return string '' nếu không hợp lệ.
 */
function kh_front_redirect( $url ) {
	$url = wp_validate_redirect( (string) $url, '' );
	if ( ! $url || false !== strpos( $url, '/wp-admin' ) || false !== strpos( $url, 'wp-login.php' ) ) {
		return '';
	}
	return $url;
}

add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		if ( ! $user instanceof WP_User || kh_is_staff( $user ) ) {
			return $redirect;
		}
		$back = kh_front_redirect( $requested );
		return $back ? $back : home_url( '/' );
	},
	20,
	3
);

add_filter(
	'logout_redirect',
	function ( $redirect, $requested, $user ) {
		return $user instanceof WP_User && kh_is_staff( $user ) ? $redirect : home_url( '/' );
	},
	20,
	3
);

// Học viên gõ /wp-admin: đưa về trang chủ.
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ! is_user_logged_in() || kh_is_staff() ) {
			return;
		}
		global $pagenow;
		if ( 'admin-post.php' === $pagenow ) {
			return;
		}
		wp_safe_redirect( home_url( '/' ) );
		exit;
	},
	5
);

// Ẩn thanh quản trị màu đen với học viên.
add_filter(
	'show_admin_bar',
	function ( $show ) {
		return is_user_logged_in() && ! kh_is_staff() ? false : $show;
	}
);

/* ==========================================================================
 * Trang học (?bai=N)
 * ======================================================================= */

add_action(
	'template_redirect',
	function () {
		if ( ! is_singular( 'khoa_hoc' ) || ! isset( $_GET['bai'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$course_id = get_queried_object_id();
		$n         = kh_learn_index();
		if ( ! $n ) {
			wp_safe_redirect( get_permalink( $course_id ) );
			exit;
		}
		$lesson = kh_course_lessons( $course_id )[ $n ];
		$state  = kh_lesson_state( $course_id, $lesson );
		// Bài học thử: cần đăng nhập (hoặc tạo tài khoản miễn phí) rồi quay lại đúng bài này.
		if ( 'login' === $state ) {
			wp_safe_redirect( kh_login_url( kh_learn_url( $course_id, $n ) ) );
			exit;
		}
		if ( 'trial' === $state ) {
			kh_trial_register_view( get_current_user_id(), $course_id, $n );
		}
		if ( 'ok' === $state && is_user_logged_in() ) {
			// Giờ bắt đầu học bài (chống bấm "học xong" quá nhanh).
			$open = get_user_meta( get_current_user_id(), '_kh_lesson_open', true );
			$open = is_array( $open ) ? $open : array();
			if ( empty( $open[ $course_id ][ $n ] ) ) {
				$open[ $course_id ][ $n ] = time();
				update_user_meta( get_current_user_id(), '_kh_lesson_open', $open );
			}
		}
		nocache_headers();
	}
);

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( kh_learn_index() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

add_filter(
	'document_title_parts',
	function ( $parts ) {
		$n = kh_learn_index();
		if ( $n ) {
			$lesson         = kh_course_lessons( get_queried_object_id() )[ $n ];
			$parts['title'] = wp_strip_all_tags( $lesson['title'] ) . ' – ' . $parts['title'];
		}
		return $parts;
	}
);

/* ==========================================================================
 * Trình duyệt báo "đã học xong bài" (assets/js/app.js)
 * ======================================================================= */

add_action(
	'wp_ajax_kh_lesson_done',
	function () {
		$course_id = isset( $_POST['course'] ) ? absint( $_POST['course'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$n         = isset( $_POST['lesson'] ) ? absint( $_POST['lesson'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		check_ajax_referer( 'kh_learn_' . $course_id, 'nonce' );
		$lessons = kh_course_lessons( $course_id );
		if ( ! isset( $lessons[ $n ] ) || 'ok' !== kh_lesson_state( $course_id, $lessons[ $n ] ) ) {
			wp_send_json_error( array( 'msg' => 'Không học được bài này.' ), 403 );
		}
		$user_id = get_current_user_id();
		if ( ! kh_is_staff() ) {
			// Phải mở bài đủ lâu để xem phần cần xem ở tốc độ 2x.
			$open = get_user_meta( $user_id, '_kh_lesson_open', true );
			$from = is_array( $open ) && ! empty( $open[ $course_id ][ $n ] ) ? (int) $open[ $course_id ][ $n ] : time();
			$need = kh_lesson_min_seconds( $lessons[ $n ] );
			$wait = $need - ( time() - $from );
			if ( $wait > 0 ) {
				wp_send_json_error( array( 'msg' => 'Bạn học hơi nhanh, hãy xem tiếp bài học.', 'wait' => $wait ), 429 );
			}
		}
		kh_mark_done( $user_id, $course_id, $n );
		$keys = array_keys( $lessons );
		$pos  = array_search( $n, $keys, true );
		$next = isset( $keys[ $pos + 1 ] ) ? $keys[ $pos + 1 ] : 0;
		wp_send_json_success(
			array(
				'next' => $next ? kh_learn_url( $course_id, $next ) : '',
				'done' => count( kh_progress( $user_id, $course_id ) ),
			)
		);
	}
);

/* ==========================================================================
 * Danh sách "Khóa học của tôi"
 * ======================================================================= */

/**
 * Link "Học tiếp": bài đầu tiên chưa học xong (hoặc bài 1).
 *
 * @param int $course_id Course ID.
 * @return string
 */
function kh_continue_url( $course_id ) {
	$user_id = get_current_user_id();
	$n       = $user_id ? kh_first_open_lesson( $course_id, $user_id ) : 0;
	return kh_learn_url( $course_id, PHP_INT_MAX === $n ? 0 : $n );
}

/**
 * HTML danh sách khóa học của user (còn hạn + đã hết hạn).
 *
 * @param int $user_id User ID.
 * @return string
 */
function kh_my_courses_html( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$list    = $user_id ? kh_access_list( $user_id ) : array();
	if ( ! $list ) {
		return '<div class="kh-mycourses-empty"><p>Bạn chưa đăng ký khóa học nào.</p><a class="kh-btn kh-btn-primary" href="' . esc_url( get_post_type_archive_link( 'khoa_hoc' ) ) . '">Xem các khóa học</a></div>';
	}
	// Khóa còn hạn lên trước.
	uasort(
		$list,
		function ( $x, $y ) {
			return (int) $y['active'] - (int) $x['active'];
		}
	);
	$out = '<ul class="kh-mycourses">';
	foreach ( $list as $id => $info ) {
		$lessons = kh_course_lessons( $id );
		if ( ! $lessons && kh_course_bundle( $id ) ) {
			continue; // Combo: các khóa bên trong đã nằm trong danh sách.
		}
		$thumb = get_the_post_thumbnail( $id, 'kh-card', array( 'alt' => '', 'loading' => 'lazy' ) );
		$cover = get_post_meta( $id, '_kh_cover', true );
		if ( ! $thumb && $cover ) {
			$thumb = '<img src="' . esc_url( $cover ) . '" alt="" width="1280" height="720" loading="lazy">';
		}
		$total = count( array_filter( wp_list_pluck( $lessons, 'src' ) ) );
		$done  = $total ? count( array_intersect_key( kh_progress( $user_id, $id ), array_filter( wp_list_pluck( $lessons, 'src' ) ) ) ) : 0;
		$pct   = $total ? (int) round( $done / $total * 100 ) : 0;
		$href  = $info['active'] ? kh_continue_url( $id ) : get_permalink( $id );

		$out .= '<li class="kh-mycourse' . ( $info['active'] ? '' : ' is-expired' ) . '">';
		$out .= '<a class="kh-mycourse-media" href="' . esc_url( $href ) . '" tabindex="-1" aria-hidden="true">' . ( $thumb ? $thumb : kh_icon( 'book-open', '', 32 ) ) . '</a>';
		$out .= '<div class="kh-mycourse-body"><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>';
		if ( $info['active'] ) {
			$out .= '<p>' . esc_html( sprintf( 'Đã học %1$d/%2$d bài', $done, $total ) ) . '</p>';
			$out .= '<span class="kh-learn-bar"><span style="width:' . (int) $pct . '%"></span></span>';
			$out .= '<p class="kh-mycourse-exp">' . esc_html( $info['expires'] ? 'Hạn học đến ' . date_i18n( 'd/m/Y', $info['expires'] ) : 'Học trọn đời' ) . '</p>';
			$out .= '<a class="kh-btn kh-btn-primary kh-btn-sm" href="' . esc_url( $href ) . '">' . kh_icon( 'play', '', 16 ) . ' ' . ( $done ? 'Học tiếp' : 'Vào học' ) . '</a>';
		} else {
			$out .= '<p class="kh-mycourse-exp is-expired">' . esc_html( 'Đã hết hạn ngày ' . date_i18n( 'd/m/Y', $info['expires'] ) ) . '</p>';
			$out .= '<a class="kh-btn kh-btn-outline kh-btn-sm" href="' . esc_url( get_permalink( $id ) ) . '">' . kh_icon( 'refresh-cw', '', 16 ) . ' Mua lại để học từ đầu</a>';
		}
		$out .= '</div></li>';
	}
	return $out . '</ul>';
}

/* ==========================================================================
 * Admin: mở / thu hồi khóa học cho từng học viên
 * ======================================================================= */

/**
 * Hộp chọn khóa học trong trang sửa thành viên.
 *
 * @param WP_User $user User.
 */
function kh_user_courses_box( $user ) {
	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}
	$map     = kh_user_course_map( $user->ID );
	$courses = get_posts( array( 'post_type' => 'khoa_hoc', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
	wp_nonce_field( 'kh_user_courses', 'kh_user_courses_nonce' );
	echo '<h2>' . esc_html__( 'Khóa học đã mở', 'khoahoc' ) . '</h2><table class="form-table" role="presentation"><tr><th>' . esc_html__( 'Khóa học', 'khoahoc' ) . '</th><td><fieldset>';
	$access = kh_access_list( $user->ID );
	foreach ( $courses as $c ) {
		$src  = isset( $map[ $c->ID ] ) ? $map[ $c->ID ] : '';
		$note = array();
		if ( 0 === strpos( $src, 'order:' ) ) {
			$note[] = sprintf( 'đơn #%d', (int) substr( $src, 6 ) );
		} elseif ( $src ) {
			$note[] = 'admin mở';
		}
		if ( isset( $access[ $c->ID ] ) ) {
			$a = $access[ $c->ID ];
			if ( ! $src ) {
				$note[] = 'trong combo ' . get_the_title( $a['via'] );
			}
			$note[] = $a['expires'] ? ( $a['active'] ? 'hạn đến ' : 'HẾT HẠN ' ) . date_i18n( 'd/m/Y', $a['expires'] ) : 'trọn đời';
			$lessons = array_filter( wp_list_pluck( kh_course_lessons( $c->ID ), 'src' ) );
			if ( $lessons ) {
				$note[] = sprintf( 'đã học %d/%d bài', count( array_intersect_key( kh_progress( $user->ID, $c->ID ), $lessons ) ), count( $lessons ) );
			}
		}
		printf(
			'<label style="display:block;margin:0 0 6px"><input type="checkbox" name="kh_user_courses[]" value="%1$d" %2$s> %3$s%4$s</label>',
			(int) $c->ID,
			checked( (bool) $src, true, false ),
			esc_html( $c->post_title ),
			$note ? ' <span class="description">(' . esc_html( implode( ' · ', $note ) ) . ')</span>' : ''
		);
		if ( $src ) {
			printf(
				'<label style="display:block;margin:-2px 0 10px 24px;color:#646970"><input type="checkbox" name="kh_user_courses_renew[]" value="%1$d"> %2$s</label>',
				(int) $c->ID,
				esc_html__( 'Gia hạn từ hôm nay (giữ tiến độ đã học)', 'khoahoc' )
			);
		}
	}
	echo '<p class="description">' . esc_html__( 'Đánh dấu để mở khóa học cho học viên này (ví dụ khi học viên chuyển khoản trực tiếp). Bỏ đánh dấu để thu hồi. Khóa mua qua đơn hàng được mở tự động. Thời hạn học tính từ ngày mở khóa.', 'khoahoc' ) . '</p>';
	echo '</fieldset></td></tr></table>';
}
add_action( 'show_user_profile', 'kh_user_courses_box' );
add_action( 'edit_user_profile', 'kh_user_courses_box' );

/**
 * Lưu hộp chọn khóa học.
 *
 * @param int $user_id User ID.
 */
function kh_user_courses_save( $user_id ) {
	if ( ! current_user_can( 'edit_users' ) || ! isset( $_POST['kh_user_courses_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kh_user_courses_nonce'] ), 'kh_user_courses' ) ) {
		return;
	}
	$want = isset( $_POST['kh_user_courses'] ) ? array_map( 'absint', (array) $_POST['kh_user_courses'] ) : array();
	$map  = kh_user_course_map( $user_id );
	foreach ( $want as $cid ) {
		// Chỉ mở khóa mới tick; khóa đã có (kể cả hết hạn) không tự mở lại khi lưu hồ sơ.
		if ( 'khoa_hoc' === get_post_type( $cid ) && ! isset( $map[ $cid ] ) ) {
			kh_grant_course( $user_id, $cid, 'admin' );
		}
	}
	$renew = isset( $_POST['kh_user_courses_renew'] ) ? array_map( 'absint', (array) $_POST['kh_user_courses_renew'] ) : array();
	foreach ( $renew as $cid ) {
		if ( in_array( $cid, $want, true ) ) {
			kh_renew_course( $user_id, $cid );
		}
	}
	foreach ( array_keys( $map ) as $cid ) {
		if ( ! in_array( (int) $cid, $want, true ) ) {
			kh_revoke_course( $user_id, $cid );
		}
	}
}
add_action( 'personal_options_update', 'kh_user_courses_save' );
add_action( 'edit_user_profile_update', 'kh_user_courses_save' );

// Cột "Khóa học" trong danh sách thành viên.
add_filter(
	'manage_users_columns',
	function ( $cols ) {
		$cols['kh_courses'] = __( 'Khóa học', 'khoahoc' );
		return $cols;
	}
);
add_filter(
	'manage_users_custom_column',
	function ( $val, $col, $user_id ) {
		if ( 'kh_courses' !== $col ) {
			return $val;
		}
		$titles = array();
		foreach ( kh_access_list( $user_id ) as $cid => $a ) {
			if ( isset( kh_user_course_map( $user_id )[ $cid ] ) ) {
				$titles[] = get_the_title( $cid ) . ( $a['active'] ? '' : ' (hết hạn)' );
			}
		}
		return $titles ? esc_html( implode( ', ', $titles ) ) : '—';
	},
	10,
	3
);
