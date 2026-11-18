<?php
/**
 * Small helpers shared by templates and blocks.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Format a price in VND: 1790000 -> 1.790.000đ.
 *
 * @param mixed $amount Number.
 * @return string
 */
function kh_price( $amount ) {
	$amount = (float) preg_replace( '/[^\d.]/', '', (string) $amount );
	if ( $amount <= 0 ) {
		return '';
	}
	return number_format( $amount, 0, ',', '.' ) . 'đ';
}

/**
 * Allowed inline HTML for rich text attributes (bold = highlight, italic, links, line breaks).
 *
 * @param string $html Raw rich text.
 * @return string
 */
function kh_rich( $html ) {
	return wp_kses(
		(string) $html,
		array(
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'br'     => array(),
			'mark'   => array( 'class' => true, 'style' => true ),
			'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
			'span'   => array( 'class' => true ),
			'code'   => array(),
			's'      => array(),
		)
	);
}

/**
 * Build a class attribute string from a list (falsy values skipped).
 *
 * @param array $classes Classes.
 * @return string
 */
function kh_cx( array $classes ) {
	return esc_attr( trim( implode( ' ', array_filter( $classes ) ) ) );
}

/**
 * Normalise a phone number to digits only (for tel: and zalo.me links).
 *
 * @param string $phone Phone.
 * @return string
 */
function kh_digits( $phone ) {
	return preg_replace( '/\D+/', '', (string) $phone );
}

/**
 * Zalo chat link built from the Zalo number in settings.
 *
 * @return string
 */
function kh_zalo_url() {
	$zalo = kh_digits( kh_opt( 'zalo' ) ? kh_opt( 'zalo' ) : kh_opt( 'phone' ) );
	return $zalo ? 'https://zalo.me/' . $zalo : '';
}

/**
 * Turn an attribute array of a link (url + text) into a safe URL.
 *
 * @param string $url URL.
 * @return string
 */
function kh_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( 0 === strpos( $url, '#' ) ) {
		return $url;
	}
	return esc_url( $url );
}

/**
 * Whether a URL points outside this site.
 *
 * @param string $url URL.
 * @return bool
 */
function kh_is_external( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	return $host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $host;
}

/**
 * Attributes for an <a> that may be external.
 *
 * @param string $url URL.
 * @return string
 */
function kh_link_attrs( $url ) {
	$out = 'href="' . esc_attr( kh_url( $url ) ) . '"';
	if ( kh_is_external( $url ) ) {
		$out .= ' target="_blank" rel="noopener"';
	}
	return $out;
}
