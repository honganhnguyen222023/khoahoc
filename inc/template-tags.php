<?php
/**
 * Template helpers.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Does this content use full-width theme sections (landing layout)?
 *
 * @param WP_Post|int|null $post Post.
 * @return bool
 */
function kh_is_landing( $post = null ) {
	foreach ( array( 'kh/hero', 'kh/section', 'kh/about', 'kh/cta' ) as $b ) {
		if ( has_block( $b, $post ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Breadcrumb.
 *
 * @param array $items [ [label, url], ... ] last item = current.
 */
function kh_breadcrumb( $items ) {
	echo '<nav class="kh-breadcrumb" aria-label="Breadcrumb"><ol>';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">Trang chủ</a></li>';
	$last = count( $items ) - 1;
	foreach ( $items as $i => $it ) {
		if ( $i === $last || empty( $it[1] ) ) {
			echo '<li aria-current="page">' . esc_html( $it[0] ) . '</li>';
		} else {
			echo '<li><a href="' . esc_url( $it[1] ) . '">' . esc_html( $it[0] ) . '</a></li>';
		}
	}
	echo '</ol></nav>';
}

/**
 * Simple page header (title + optional description).
 *
 * @param string $title Title.
 * @param string $desc  Description.
 * @param array  $crumbs Breadcrumb items.
 */
function kh_page_header( $title, $desc = '', $crumbs = array() ) {
	echo '<header class="kh-page-head"><div class="kh-container">';
	if ( $crumbs ) {
		kh_breadcrumb( $crumbs );
	}
	echo '<h1 class="kh-h1 kh-h1-sm">' . esc_html( $title ) . '</h1>';
	if ( $desc ) {
		echo '<p class="kh-lead">' . wp_kses_post( $desc ) . '</p>';
	}
	echo '</div></header>';
}

/**
 * YouTube ID from a URL.
 *
 * @param string $url URL.
 * @return string
 */
function kh_youtube_id( $url ) {
	if ( preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|live/))([\w-]{11})~', (string) $url, $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * Pagination.
 */
function kh_pagination() {
	$links = paginate_links(
		array(
			'type'      => 'list',
			'prev_text' => '‹',
			'next_text' => '›',
		)
	);
	if ( $links ) {
		echo '<nav class="kh-pagination" aria-label="Phân trang">' . wp_kses_post( $links ) . '</nav>';
	}
}

/**
 * Login block on the "Tài khoản" page: Vietnamese labels, a "forgot password"
 * link, and a short welcome with useful links once the student is logged in.
 */
add_filter(
	'login_form_defaults',
	function ( $args ) {
		$args['label_username'] = 'Email hoặc tên đăng nhập';
		$args['label_password'] = 'Mật khẩu';
		$args['label_remember'] = 'Ghi nhớ đăng nhập';
		$args['label_log_in']   = 'Đăng nhập';
		return $args;
	}
);

add_filter(
	'render_block_core/loginout',
	function ( $html, $block ) {
		if ( empty( $block['attrs']['className'] ) || false === strpos( $block['attrs']['className'], 'kh-login' ) ) {
			return $html;
		}
		if ( ! is_user_logged_in() ) {
			$lost = '<p class="kh-login-lost"><a href="' . esc_url( wp_lostpassword_url( get_permalink() ) ) . '">Quên mật khẩu?</a></p>';
			return preg_replace( '#</form>#', $lost . '</form>', $html, 1 );
		}
		$user = wp_get_current_user();
		$out  = '<div class="wp-block-loginout kh-login kh-login-in">';
		$out .= '<p class="kh-login-hello">Xin chào <strong>' . esc_html( $user->display_name ) . '</strong>, bạn đã đăng nhập.</p>';
		$out .= '<div class="kh-login-actions">';
		$out .= '<a class="kh-btn kh-btn-primary" href="' . esc_url( home_url( '/khoa-hoc/' ) ) . '">Vào khóa học</a>';
		$out .= '<a class="kh-btn kh-btn-outline" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Đăng xuất</a>';
		$out .= '</div></div>';
		return $out;
	},
	10,
	2
);
