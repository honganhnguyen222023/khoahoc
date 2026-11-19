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
 * Khóa học user đã được mở trực tiếp (mua hoặc admin cấp).
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
 * Tất cả khóa user học được (đã mua + khóa con của combo đã mua).
 *
 * @param int $user_id User ID.
 * @return int[]
 */
function kh_user_courses( $user_id = 0 ) {
	$ids = array();
	foreach ( array_keys( kh_user_course_map( $user_id ) ) as $cid ) {
		$ids[] = (int) $cid;
		foreach ( kh_course_bundle( $cid ) as $child ) {
			$ids[] = $child;
		}
	}
	$ids = array_values( array_unique( array_filter( $ids ) ) );
	return array_values(
		array_filter(
			$ids,
			function ( $id ) {
				return 'khoa_hoc' === get_post_type( $id );
			}
		)
	);
}

/**
 * User có quyền học toàn bộ khóa này không.
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
	return in_array( (int) $course_id, kh_user_courses( $user_id ), true );
}

/**
 * Mở khóa học cho user.
 *
 * @param int    $user_id   User ID.
 * @param int    $course_id Course ID.
 * @param string $source    'order:123' hoặc 'admin'.
 */
function kh_grant_course( $user_id, $course_id, $source = 'admin' ) {
	$map = kh_user_course_map( $user_id );
	if ( isset( $map[ $course_id ] ) ) {
		return;
	}
	$map[ (int) $course_id ] = (string) $source;
	update_user_meta( $user_id, '_kh_courses', $map );
	do_action( 'kh_course_granted', $user_id, $course_id, $source );
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
	do_action( 'kh_course_revoked', $user_id, $course_id, $source );
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
	if ( ! is_user_logged_in() ) {
		return false;
	}
	return ! empty( $lesson['free'] ) || kh_has_access( $course_id );
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
			'<iframe src="%1$s" title="%2$s" style="background:#000 url(%3$s) center/cover no-repeat" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>',
			esc_url( 'https://www.youtube.com/embed/' . $yt . '?rel=0&playsinline=1' ),
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
		// Bài học thử: cần đăng nhập (hoặc tạo tài khoản miễn phí) rồi quay lại đúng bài này.
		if ( ! is_user_logged_in() && $lesson['free'] ) {
			wp_safe_redirect( kh_login_url( kh_learn_url( $course_id, $n ) ) );
			exit;
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
 * Danh sách "Khóa học của tôi"
 * ======================================================================= */

/**
 * HTML danh sách khóa học của user.
 *
 * @param int $user_id User ID.
 * @return string
 */
function kh_my_courses_html( $user_id = 0 ) {
	$ids = kh_user_courses( $user_id );
	if ( ! $ids ) {
		return '<div class="kh-mycourses-empty"><p>Bạn chưa đăng ký khóa học nào.</p><a class="kh-btn kh-btn-primary" href="' . esc_url( get_post_type_archive_link( 'khoa_hoc' ) ) . '">Xem các khóa học</a></div>';
	}
	$out = '<ul class="kh-mycourses">';
	foreach ( $ids as $id ) {
		$lessons = kh_course_lessons( $id );
		if ( ! $lessons && kh_course_bundle( $id ) ) {
			continue; // Combo: các khóa bên trong đã nằm trong danh sách.
		}
		$thumb   = get_the_post_thumbnail( $id, 'kh-card', array( 'alt' => '', 'loading' => 'lazy' ) );
		$cover   = get_post_meta( $id, '_kh_cover', true );
		if ( ! $thumb && $cover ) {
			$thumb = '<img src="' . esc_url( $cover ) . '" alt="" width="1280" height="720" loading="lazy">';
		}
		$out .= '<li class="kh-mycourse">';
		$out .= '<a class="kh-mycourse-media" href="' . esc_url( kh_learn_url( $id ) ) . '" tabindex="-1" aria-hidden="true">' . ( $thumb ? $thumb : kh_icon( 'book-open', '', 32 ) ) . '</a>';
		$out .= '<div class="kh-mycourse-body"><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>';
		$out .= $lessons ? '<p>' . esc_html( sprintf( '%d bài học', count( $lessons ) ) ) . '</p>' : '<p>Combo – xem các khóa bên trong</p>';
		$out .= '<a class="kh-btn kh-btn-primary kh-btn-sm" href="' . esc_url( kh_learn_url( $id ) ) . '">' . kh_icon( 'play', '', 16 ) . ' Vào học</a></div></li>';
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
	foreach ( $courses as $c ) {
		$src  = isset( $map[ $c->ID ] ) ? $map[ $c->ID ] : '';
		$note = '';
		if ( 0 === strpos( $src, 'order:' ) ) {
			$oid  = (int) substr( $src, 6 );
			$note = ' <span class="description">(' . esc_html( sprintf( 'đơn #%d', $oid ) ) . ')</span>';
		} elseif ( $src ) {
			$note = ' <span class="description">(admin mở)</span>';
		}
		printf(
			'<label style="display:block;margin:0 0 6px"><input type="checkbox" name="kh_user_courses[]" value="%1$d" %2$s> %3$s%4$s</label>',
			(int) $c->ID,
			checked( (bool) $src, true, false ),
			esc_html( $c->post_title ),
			$note // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}
	echo '<p class="description">' . esc_html__( 'Đánh dấu để mở khóa học cho học viên này (ví dụ khi học viên chuyển khoản trực tiếp). Bỏ đánh dấu để thu hồi. Khóa mua qua đơn hàng được mở tự động.', 'khoahoc' ) . '</p>';
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
		if ( 'khoa_hoc' === get_post_type( $cid ) ) {
			kh_grant_course( $user_id, $cid, 'admin' );
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
		$titles = array_map( 'get_the_title', array_keys( kh_user_course_map( $user_id ) ) );
		return $titles ? esc_html( implode( ', ', $titles ) ) : '—';
	},
	10,
	3
);
