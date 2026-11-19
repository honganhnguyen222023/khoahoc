<?php
/**
 * Lucide icon helper (inline SVG, no icon font = fast).
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * All available icons: name => inner SVG markup.
 *
 * @return array
 */
function kh_icons() {
	static $icons = null;
	if ( null === $icons ) {
		$icons = require KH_DIR . '/inc/icons-data.php';
		// Brand icons (Lucide does not ship brand logos).
		$icons['facebook'] = '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />';
		$icons['tiktok']   = '<path d="M16 3a5 5 0 0 0 5 5" /><path d="M16 3v11.5a4.5 4.5 0 1 1-4.5-4.5" />';
		$icons['youtube']  = '<path d="M2.5 17a24 24 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49 49 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24 24 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49 49 0 0 1-16.2 0A2 2 0 0 1 2.5 17" /><path d="m10 15 5-3-5-3z" />';
		$icons['zalo']     = '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" /><path d="M8 9h4l-4 6h4" /><path d="M15 9v6" />';
		$icons['eye']          = '<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0" /><circle cx="12" cy="12" r="3" />';
		$icons['chevron-left'] = '<path d="m15 18-6-6 6-6" />';
		$icons['log-out']      = '<path d="m16 17 5-5-5-5" /><path d="M21 12H9" /><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />';
		ksort( $icons );
	}
	return $icons;
}

/**
 * Print-ready inline SVG.
 *
 * @param string $name  Icon name.
 * @param string $class Extra classes.
 * @param int    $size  Pixel size.
 * @return string
 */
function kh_icon( $name, $class = '', $size = 20 ) {
	$icons = kh_icons();
	if ( empty( $icons[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="%1$s" xmlns="http://www.w3.org/2000/svg" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( trim( 'kh-svg ' . $class ) ),
		(int) $size,
		$icons[ $name ]
	);
}
