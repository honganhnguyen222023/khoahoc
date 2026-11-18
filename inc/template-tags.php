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
