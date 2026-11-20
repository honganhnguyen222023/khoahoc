<?php
/**
 * Quản trị → Khóa học: danh sách, thêm / sửa thông tin, học phí, ảnh và giáo trình.
 *
 * Giáo trình vẫn lưu dạng khối kh/chapter > kh/lesson trong nội dung khóa học
 * (giống trình soạn thảo WordPress), nên 2 cách sửa dùng chung dữ liệu.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Số học viên đang học từng khóa: course_id => số người.
 *
 * @return array
 */
function kh_qt_course_student_counts() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	foreach ( get_users( array( 'meta_key' => '_kh_courses', 'fields' => 'ID' ) ) as $uid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( kh_access_list( (int) $uid ) as $cid => $a ) {
			if ( $a['active'] ) {
				$out[ $cid ] = isset( $out[ $cid ] ) ? $out[ $cid ] + 1 : 1;
			}
		}
	}
	return $out;
}

/**
 * Trạng thái hiển thị của khóa.
 *
 * @param WP_Post $p Khóa.
 * @return array [ chữ, màu ]
 */
function kh_qt_course_status( $p ) {
	if ( 'publish' === $p->post_status ) {
		return array( 'Đang hiện', 'ok' );
	}
	if ( 'future' === $p->post_status ) {
		return array( 'Hẹn giờ', 'info' );
	}
	return array( 'Đang ẩn', 'mute' );
}

/**
 * Trang Khóa học.
 *
 * @param string $id ID khóa hoặc "moi".
 * @return array
 */
function kh_qt_page_courses( $id ) {
	if ( 'moi' === $id ) {
		return kh_qt_course_edit( 0 );
	}
	if ( $id ) {
		return kh_qt_course_edit( absint( $id ) );
	}

	$courses = get_posts( array( 'post_type' => 'khoa_hoc', 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'posts_per_page' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
	$counts  = kh_qt_course_student_counts();
	$add     = '<a class="qt-btn qt-btn-sm" href="' . esc_url( kh_qt_url( 'khoa-hoc', 'moi' ) ) . '">' . kh_qt_icon( 'plus', 16 ) . ' <span>Thêm khóa học</span></a>';

	if ( ! $courses ) {
		return array( 'title' => 'Khóa học', 'actions' => $add, 'html' => kh_qt_card( '', kh_qt_empty( 'Chưa có khóa học nào.', 'book-open', $add ) ) );
	}

	$html = '<div class="qt-course-grid">';
	foreach ( $courses as $c ) {
		$url     = kh_qt_url( 'khoa-hoc', $c->ID );
		$st      = kh_qt_course_status( $c );
		$price   = (float) get_post_meta( $c->ID, '_kh_price', true );
		$regular = (float) get_post_meta( $c->ID, '_kh_regular_price', true );
		$lessons = count( kh_course_lessons( $c->ID ) );
		$bundle  = kh_course_bundle( $c->ID );
		$thumb   = get_the_post_thumbnail_url( $c->ID, 'kh-card' );
		$thumb   = $thumb ? $thumb : get_post_meta( $c->ID, '_kh_cover', true );

		$html .= '<a class="qt-course-card" href="' . esc_url( $url ) . '">';
		$html .= '<div class="qt-course-thumb">' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy">' : kh_qt_icon( 'book-open', 32 ) ) . kh_qt_badge( $st[0], $st[1] ) . '</div>';
		$html .= '<div class="qt-course-body"><h3>' . esc_html( $c->post_title ) . '</h3>';
		$html .= '<p class="qt-course-price"><strong>' . esc_html( $price ? kh_price( $price ) : 'Miễn phí' ) . '</strong>' . ( $regular > $price ? ' <del>' . esc_html( kh_price( $regular ) ) . '</del>' : '' ) . '</p>';
		$html .= '<p class="qt-course-meta"><span>' . kh_qt_icon( 'play', 14 ) . ( $bundle ? esc_html( count( $bundle ) . ' khóa trong combo' ) : esc_html( $lessons . ' bài học' ) ) . '</span><span>' . kh_qt_icon( 'users', 14 ) . esc_html( ( isset( $counts[ $c->ID ] ) ? $counts[ $c->ID ] : 0 ) . ' học viên' ) . '</span><span>' . kh_qt_icon( 'clock', 14 ) . esc_html( kh_duration_label( $c->ID ) ) . '</span></p>';
		$html .= '</div></a>';
	}
	$html .= '</div>';

	return array(
		'title'   => 'Khóa học',
		'actions' => $add,
		'html'    => $html,
	);
}

/* ==========================================================================
 * Giáo trình: đọc / ghi khối kh/chapter, kh/lesson
 * ======================================================================= */

/**
 * Đọc giáo trình từ nội dung khóa học.
 *
 * @param string $content post_content.
 * @return array [ chapters => [ [ title, open, lessons => [...] ] ], editable => bool ]
 */
function kh_qt_curriculum_read( $content ) {
	$blocks   = parse_blocks( (string) $content );
	$chapters = array();
	$top      = 0;
	$lesson   = function ( $a ) {
		$video  = isset( $a['video'] ) ? trim( (string) $a['video'] ) : '';
		$legacy = isset( $a['url'] ) ? trim( (string) $a['url'] ) : '';
		return array(
			'title'    => isset( $a['title'] ) ? wp_strip_all_tags( (string) $a['title'] ) : '',
			'duration' => isset( $a['duration'] ) ? (string) $a['duration'] : '',
			'type'     => isset( $a['type'] ) && 'doc' === $a['type'] ? 'doc' : 'video',
			'video'    => $video ? $video : $legacy,
			'free'     => ! empty( $a['free'] ) || ( ! $video && $legacy ),
		);
	};
	foreach ( $blocks as $b ) {
		if ( 'kh/chapter' === $b['blockName'] ) {
			$ch = array(
				'title'   => isset( $b['attrs']['title'] ) ? wp_strip_all_tags( (string) $b['attrs']['title'] ) : '',
				'open'    => ! empty( $b['attrs']['open'] ),
				'lessons' => array(),
			);
			foreach ( $b['innerBlocks'] as $ib ) {
				if ( 'kh/lesson' === $ib['blockName'] ) {
					$ch['lessons'][] = $lesson( $ib['attrs'] );
				}
			}
			$top       += count( $ch['lessons'] );
			$chapters[] = $ch;
		} elseif ( 'kh/lesson' === $b['blockName'] ) {
			if ( ! $chapters || '' !== end( $chapters )['title'] ) {
				$chapters[] = array( 'title' => '', 'open' => false, 'lessons' => array() );
			}
			$chapters[ count( $chapters ) - 1 ]['lessons'][] = $lesson( $b['attrs'] );
			++$top;
		}
	}
	// Bài học nằm sâu trong khối khác (VD: trong khối Section) → không sửa ở đây được.
	$all = substr_count( (string) $content, '<!-- wp:kh/lesson' );
	return array( 'chapters' => $chapters, 'editable' => $all === $top );
}

/**
 * Khối trống (xuống dòng) giữa các khối.
 *
 * @return array
 */
function kh_qt_block_nl() {
	return array( 'blockName' => null, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => "\n", 'innerContent' => array( "\n" ) );
}

/**
 * Ghi giáo trình mới vào nội dung (giữ nguyên phần giới thiệu).
 *
 * @param string $content  Nội dung cũ.
 * @param array  $chapters Giáo trình mới.
 * @return string
 */
function kh_qt_curriculum_write( $content, $chapters ) {
	$new = array();
	foreach ( $chapters as $i => $ch ) {
		$inner = array();
		$ic    = array( "\n" );
		foreach ( $ch['lessons'] as $l ) {
			$attrs = array( 'title' => $l['title'] );
			if ( '' !== $l['duration'] ) {
				$attrs['duration'] = $l['duration'];
			}
			if ( 'doc' === $l['type'] ) {
				$attrs['type'] = 'doc';
			}
			if ( '' !== $l['video'] ) {
				$attrs['video'] = $l['video'];
			}
			if ( $l['free'] ) {
				$attrs['free'] = true;
			}
			$inner[] = array( 'blockName' => 'kh/lesson', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
			$ic[]    = null;
			$ic[]    = "\n";
		}
		$cattrs = array( 'title' => '' !== $ch['title'] ? $ch['title'] : 'Chương ' . ( $i + 1 ) );
		if ( 0 === $i ) {
			$cattrs['open'] = true;
		}
		$new[] = array( 'blockName' => 'kh/chapter', 'attrs' => $cattrs, 'innerBlocks' => $inner, 'innerHTML' => "\n", 'innerContent' => $ic );
		$new[] = kh_qt_block_nl();
	}

	$blocks = parse_blocks( (string) $content );
	$out    = array();
	$placed = false;
	foreach ( $blocks as $b ) {
		if ( in_array( $b['blockName'], array( 'kh/chapter', 'kh/lesson' ), true ) ) {
			if ( ! $placed ) {
				$out    = array_merge( $out, $new );
				$placed = true;
			}
			continue;
		}
		$out[] = $b;
	}
	if ( ! $placed ) {
		if ( false === strpos( (string) $content, 'Nội dung khóa học' ) && $new ) {
			$out[] = kh_qt_block_nl();
			$out[] = array( 'blockName' => 'core/heading', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '<h2 class="wp-block-heading">Nội dung khóa học</h2>', 'innerContent' => array( '<h2 class="wp-block-heading">Nội dung khóa học</h2>' ) );
			$out[] = kh_qt_block_nl();
		}
		$out = array_merge( $out, $new );
	}
	return serialize_blocks( $out );
}

/**
 * Làm sạch giáo trình gửi lên (JSON).
 *
 * @param string $json JSON.
 * @return array|null
 */
function kh_qt_curriculum_clean( $json ) {
	$data = json_decode( (string) $json, true );
	if ( ! is_array( $data ) ) {
		return null;
	}
	$out = array();
	foreach ( $data as $ch ) {
		if ( ! is_array( $ch ) ) {
			continue;
		}
		$lessons = array();
		foreach ( isset( $ch['lessons'] ) && is_array( $ch['lessons'] ) ? $ch['lessons'] : array() as $l ) {
			$title = sanitize_text_field( isset( $l['title'] ) ? $l['title'] : '' );
			$video = trim( (string) ( isset( $l['video'] ) ? $l['video'] : '' ) );
			if ( '' === $title && '' === $video ) {
				continue;
			}
			$lessons[] = array(
				'title'    => '' !== $title ? $title : 'Bài ' . ( count( $lessons ) + 1 ),
				'duration' => sanitize_text_field( isset( $l['duration'] ) ? $l['duration'] : '' ),
				'type'     => isset( $l['type'] ) && 'doc' === $l['type'] ? 'doc' : 'video',
				'video'    => 0 === strpos( $video, '#' ) ? sanitize_text_field( $video ) : esc_url_raw( $video ),
				'free'     => ! empty( $l['free'] ),
			);
		}
		$title = sanitize_text_field( isset( $ch['title'] ) ? $ch['title'] : '' );
		if ( '' === $title && ! $lessons ) {
			continue;
		}
		$out[] = array( 'title' => $title, 'lessons' => $lessons );
	}
	return $out;
}

/* ==========================================================================
 * Trang sửa khóa học
 * ======================================================================= */

/**
 * Form thêm / sửa khóa học.
 *
 * @param int $id Khóa (0 = mới).
 * @return array
 */
function kh_qt_course_edit( $id ) {
	$post = $id ? get_post( $id ) : null;
	if ( $id && ( ! $post || 'khoa_hoc' !== $post->post_type ) ) {
		return array( 'title' => 'Không tìm thấy', 'back' => kh_qt_url( 'khoa-hoc' ), 'html' => kh_qt_empty( 'Khóa học không tồn tại.', 'book-open' ) );
	}
	if ( $id && ! current_user_can( 'edit_post', $id ) ) {
		return kh_qt_page_denied();
	}
	$m = function ( $k ) use ( $id ) {
		return $id ? (string) get_post_meta( $id, '_kh_' . $k, true ) : '';
	};
	$fields = kh_course_fields();
	$cur    = $post ? kh_qt_curriculum_read( $post->post_content ) : array( 'chapters' => array( array( 'title' => 'Chương 1', 'lessons' => array( array( 'title' => 'Bài 1', 'duration' => '', 'type' => 'video', 'video' => '', 'free' => true ) ) ) ), 'editable' => true );

	$form = '<form method="post" class="qt-form qt-course-form" data-qt-dirty>' . kh_qt_form_fields( 'course_save' ) . '<input type="hidden" name="course_id" value="' . (int) $id . '">';

	// Cột trái.
	$main  = kh_qt_card(
		'Thông tin khóa học',
		kh_qt_field( 'title', 'Tên khóa học', $post ? $post->post_title : '', array( 'required' => true, 'placeholder' => 'VD: Excel từ cơ bản tới nâng cao' ) )
		. kh_qt_field( 'excerpt', 'Mô tả ngắn', $post ? $post->post_excerpt : '', array( 'type' => 'textarea', 'help' => 'Hiện trên thẻ khóa học và đầu trang khóa học. Khoảng 1–2 câu.' ) )
		. kh_qt_field( 'audience', $fields['audience']['label'], $m( 'audience' ), array( 'help' => $fields['audience']['help'] ) )
		. kh_qt_field( 'benefits', $fields['benefits']['label'], $m( 'benefits' ), array( 'type' => 'textarea', 'help' => $fields['benefits']['help'] ) )
		. kh_qt_field( 'video', 'Video giới thiệu (link YouTube)', $m( 'video' ), array( 'type' => 'url', 'placeholder' => 'https://www.youtube.com/watch?v=…' ) )
	);

	// Giáo trình.
	if ( $cur['editable'] ) {
		$cbody  = '<p class="qt-help">' . kh_qt_icon( 'info', 14 ) . ' Tiến độ học viên lưu theo <strong>thứ tự bài</strong>. Khóa đã có học viên: nên thêm bài mới vào cuối, hạn chế xóa hoặc đổi chỗ bài cũ. Bài 1 luôn được học thử miễn phí.</p>';
		$cbody .= '<input type="hidden" name="curriculum" value="' . esc_attr( wp_json_encode( $cur['chapters'] ) ) . '" data-qt-curriculum-input>';
		$cbody .= '<div class="qt-curriculum" data-qt-curriculum></div>';
		$cbody .= '<noscript><p>Cần bật JavaScript để sửa giáo trình.</p></noscript>';
	} else {
		$cbody = '<p class="qt-note">' . kh_qt_icon( 'info', 16 ) . '<span>Giáo trình của khóa này nằm trong một khối đặc biệt, hãy sửa bằng trình soạn thảo WordPress.</span></p>';
	}
	$count = 0;
	foreach ( $cur['chapters'] as $ch ) {
		$count += count( $ch['lessons'] );
	}
	$main .= kh_qt_card( 'Giáo trình', $cbody, '<span class="qt-muted" data-qt-curriculum-count>' . (int) $count . ' bài</span>' );

	// Cột phải.
	$status = $post ? $post->post_status : 'draft';
	$side   = kh_qt_card(
		'Bán khóa học',
		kh_qt_field( 'publish', 'Hiện trên website', 'publish' === $status, array( 'type' => 'checkbox' ) )
		. kh_qt_field( 'price', 'Học phí', $m( 'price' ), array( 'type' => 'money', 'help' => 'Để trống = miễn phí.' ) )
		. kh_qt_field( 'regular_price', 'Giá gốc (gạch ngang)', $m( 'regular_price' ), array( 'type' => 'money', 'help' => 'Để trống nếu không giảm giá.' ) )
		. kh_qt_field( 'duration', 'Thời hạn học', $m( 'duration' ), array( 'type' => 'select', 'options' => array( '' => 'Theo cài đặt chung (' . kh_duration_label( 0 ) . ')' ) + array_slice( $fields['duration']['options'], 1, null, true ) ) )
	);
	$side  .= kh_qt_card( 'Ảnh khóa học', kh_qt_field( 'thumbnail', 'Ảnh đại diện (tỉ lệ 16:9)', $id ? (string) get_post_thumbnail_id( $id ) : '', array( 'type' => 'image' ) ) );

	$terms    = get_terms( array( 'taxonomy' => 'danh_muc_khoa_hoc', 'hide_empty' => false ) );
	$own      = $id ? wp_get_post_terms( $id, 'danh_muc_khoa_hoc', array( 'fields' => 'ids' ) ) : array();
	$cats     = '<div class="qt-checks is-compact">';
	foreach ( is_array( $terms ) ? $terms : array() as $t ) {
		$cats .= '<label class="qt-check"><input type="checkbox" name="cats[]" value="' . (int) $t->term_id . '"' . checked( in_array( $t->term_id, (array) $own, true ), true, false ) . '><span>' . esc_html( $t->name ) . '</span></label>';
	}
	$cats .= '</div>' . kh_qt_field( 'new_cat', 'Thêm danh mục mới', '', array( 'placeholder' => 'VD: PowerPoint' ) );
	$side .= kh_qt_card(
		'Hiển thị',
		kh_qt_field( 'featured', 'Khóa nổi bật (thẻ lớn ở trang chủ)', $m( 'featured' ), array( 'type' => 'checkbox' ) )
		. kh_qt_field( 'badge', 'Nhãn nhỏ', $m( 'badge' ), array( 'placeholder' => 'VD: Khóa mới' ) )
		. kh_qt_field( 'meta_line', 'Dòng thông tin', $m( 'meta_line' ), array( 'placeholder' => 'VD: 12 chương · 121 bài · ~16 giờ' ) )
		. '<div class="qt-field"><label>Danh mục</label>' . $cats . '</div>'
	);

	// Combo.
	$picked = array_map( 'absint', explode( ',', $m( 'bundle' ) ) );
	$combo  = '<p class="qt-help">Chỉ dùng cho khóa Combo: học viên mua combo được học tất cả các khóa chọn ở đây.</p><div class="qt-checks is-compact">';
	foreach ( kh_qt_all_courses() as $o ) {
		if ( $o->ID === $id ) {
			continue;
		}
		$combo .= '<label class="qt-check"><input type="checkbox" name="bundle[]" value="' . (int) $o->ID . '"' . checked( in_array( $o->ID, $picked, true ), true, false ) . '><span>' . esc_html( $o->post_title ) . '</span></label>';
	}
	$combo .= '</div>';
	$side  .= '<details class="qt-card qt-collapse"' . ( array_filter( $picked ) ? ' open' : '' ) . '><summary><h2>Combo gồm các khóa</h2>' . kh_qt_icon( 'chevron-down', 18 ) . '</summary><div class="qt-card-body">' . $combo . '</div></details>';

	if ( $id ) {
		$side .= kh_qt_card(
			'Nâng cao',
			'<p class="qt-help">Bài giới thiệu dài, hình ảnh, câu hỏi thường gặp… sửa trong trình soạn thảo WordPress.</p><a class="qt-btn qt-btn-ghost qt-btn-sm" href="' . esc_url( get_edit_post_link( $id, 'raw' ) ) . '">' . kh_qt_icon( 'pencil', 15 ) . ' Sửa trang giới thiệu</a>'
		);
	}

	$form .= '<div class="qt-grid is-wide-left"><div class="qt-stack">' . $main . '</div><div class="qt-stack">' . $side . '</div></div>';
	$form .= '<div class="qt-savebar"><span class="qt-muted" data-qt-dirty-note>Chưa có thay đổi</span><button class="qt-btn" type="submit">' . kh_qt_icon( 'save', 18 ) . ' ' . ( $id ? 'Lưu khóa học' : 'Tạo khóa học' ) . '</button></div>';
	$form .= '</form>';

	if ( $id && current_user_can( 'delete_post', $id ) ) {
		$form .= '<div class="qt-danger-zone">' . kh_qt_action_button( 'course_trash', array( 'course_id' => $id ), kh_qt_icon( 'trash-2', 15 ) . ' Xóa khóa học', 'qt-btn qt-btn-ghost qt-btn-sm is-danger', 'Xóa khóa “' . $post->post_title . '”? Khóa học được chuyển vào thùng rác (khôi phục được trong Quản trị nâng cao). Học viên đã mua sẽ không vào học được nữa.' ) . '</div>';
	}

	$actions = $id ? '<a class="qt-btn qt-btn-ghost qt-btn-sm" href="' . esc_url( get_permalink( $id ) ) . '" target="_blank" rel="noopener">' . kh_qt_icon( 'eye', 16 ) . ' <span>Xem trang khóa học</span></a>' : '';
	return array(
		'title'   => $id ? $post->post_title : 'Thêm khóa học',
		'back'    => kh_qt_url( 'khoa-hoc' ),
		'html'    => $form,
		'actions' => $actions,
		'media'   => true,
	);
}

/**
 * Lưu khóa học.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_course_save( $p ) {
	$id = isset( $p['course_id'] ) ? absint( $p['course_id'] ) : 0;
	if ( $id ? ( 'khoa_hoc' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) : ! current_user_can( 'edit_posts' ) ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền sửa khóa học này.' );
	}
	$title = sanitize_text_field( isset( $p['title'] ) ? $p['title'] : '' );
	if ( '' === $title ) {
		return array( 'ok' => false, 'msg' => 'Vui lòng nhập tên khóa học.' );
	}
	$publish = ! empty( $p['publish'] ) && current_user_can( 'publish_posts' );

	// Tạo bản nháp trước để có ID.
	if ( ! $id ) {
		$intro = '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">Giới thiệu khóa học</h2>' . "\n" . '<!-- /wp:heading -->' . "\n" . '<!-- wp:paragraph -->' . "\n" . '<p>' . esc_html( sanitize_textarea_field( isset( $p['excerpt'] ) ? $p['excerpt'] : '' ) ) . '</p>' . "\n" . '<!-- /wp:paragraph -->';
		$id    = wp_insert_post( wp_slash( array( 'post_type' => 'khoa_hoc', 'post_status' => 'draft', 'post_title' => $title, 'post_content' => $intro ) ), true );
		if ( is_wp_error( $id ) ) {
			return array( 'ok' => false, 'msg' => 'Chưa tạo được khóa học: ' . $id->get_error_message() );
		}
	}

	// Thông tin thêm.
	$meta = array(
		'price'         => kh_qt_money_value( isset( $p['price'] ) ? $p['price'] : '' ),
		'regular_price' => kh_qt_money_value( isset( $p['regular_price'] ) ? $p['regular_price'] : '' ),
		'badge'         => sanitize_text_field( isset( $p['badge'] ) ? $p['badge'] : '' ),
		'meta_line'     => sanitize_text_field( isset( $p['meta_line'] ) ? $p['meta_line'] : '' ),
		'audience'      => sanitize_text_field( isset( $p['audience'] ) ? $p['audience'] : '' ),
		'benefits'      => sanitize_textarea_field( isset( $p['benefits'] ) ? $p['benefits'] : '' ),
		'video'         => esc_url_raw( isset( $p['video'] ) ? trim( $p['video'] ) : '' ),
		'featured'      => ! empty( $p['featured'] ) ? '1' : '',
		'bundle'        => implode( ',', array_filter( array_map( 'absint', isset( $p['bundle'] ) ? (array) $p['bundle'] : array() ) ) ),
	);
	$durations        = kh_course_fields()['duration']['options'];
	$meta['duration'] = isset( $p['duration'] ) && array_key_exists( (string) $p['duration'], $durations ) ? (string) $p['duration'] : '';
	foreach ( $meta as $k => $v ) {
		if ( '' === $v ) {
			delete_post_meta( $id, '_kh_' . $k );
		} else {
			update_post_meta( $id, '_kh_' . $k, $v );
		}
	}

	// Ảnh.
	$thumb = isset( $p['thumbnail'] ) ? absint( $p['thumbnail'] ) : 0;
	if ( $thumb && wp_attachment_is_image( $thumb ) ) {
		set_post_thumbnail( $id, $thumb );
	} elseif ( ! $thumb ) {
		delete_post_thumbnail( $id );
	}

	// Danh mục.
	$cats = array_map( 'absint', isset( $p['cats'] ) ? (array) $p['cats'] : array() );
	$new  = sanitize_text_field( isset( $p['new_cat'] ) ? $p['new_cat'] : '' );
	if ( '' !== $new && current_user_can( 'manage_categories' ) ) {
		$t = term_exists( $new, 'danh_muc_khoa_hoc' );
		$t = $t ? $t : wp_insert_term( $new, 'danh_muc_khoa_hoc' );
		if ( ! is_wp_error( $t ) ) {
			$cats[] = (int) $t['term_id'];
		}
	}
	wp_set_post_terms( $id, $cats, 'danh_muc_khoa_hoc' );

	// Nội dung + giáo trình.
	$post    = get_post( $id );
	$content = $post->post_content;
	$warn    = '';
	if ( isset( $p['curriculum'] ) ) {
		$chapters = kh_qt_curriculum_clean( $p['curriculum'] );
		if ( null === $chapters ) {
			$warn = ' Giáo trình chưa được lưu (dữ liệu lỗi).';
		} elseif ( kh_qt_curriculum_read( $content )['editable'] ) {
			$content = kh_qt_curriculum_write( $content, $chapters );
		}
	}
	$res = wp_update_post(
		wp_slash(
			array(
				'ID'           => $id,
				'post_title'   => $title,
				'post_excerpt' => sanitize_textarea_field( isset( $p['excerpt'] ) ? $p['excerpt'] : '' ),
				'post_status'  => $publish ? 'publish' : ( 'publish' === $post->post_status && ! current_user_can( 'publish_posts' ) ? 'publish' : 'draft' ),
				'post_content' => $content,
			)
		),
		true
	);
	if ( is_wp_error( $res ) ) {
		return array( 'ok' => false, 'msg' => 'Chưa lưu được: ' . $res->get_error_message() );
	}
	// Học phí mới → cập nhật sản phẩm WooCommerce đi kèm.
	if ( function_exists( 'kh_wc_sync_product' ) ) {
		kh_wc_sync_product( $id );
	}
	return array(
		'ok'  => '' === $warn,
		'msg' => ( isset( $p['course_id'] ) && absint( $p['course_id'] ) ? 'Đã lưu khóa học.' : 'Đã tạo khóa học.' ) . ( $publish ? '' : ' Khóa đang ẩn, bật "Hiện trên website" khi sẵn sàng bán.' ) . $warn,
		'go'  => kh_qt_url( 'khoa-hoc', $id ),
	);
}

/**
 * Xóa (đưa vào thùng rác).
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_course_trash( $p ) {
	$id = isset( $p['course_id'] ) ? absint( $p['course_id'] ) : 0;
	if ( 'khoa_hoc' !== get_post_type( $id ) || ! current_user_can( 'delete_post', $id ) ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền xóa khóa học này.' );
	}
	$title = get_the_title( $id );
	wp_trash_post( $id );
	return array( 'ok' => true, 'msg' => 'Đã xóa khóa “' . $title . '”.', 'go' => kh_qt_url( 'khoa-hoc' ) );
}
