<?php
/**
 * KhoaHoc Academy theme bootstrap.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

define( 'KH_VERSION', '1.0.0' );
define( 'KH_DIR', get_template_directory() );
define( 'KH_URI', get_template_directory_uri() );

$kh_includes = array(
	'helpers',
	'template-tags',
	'icons',
	'options',
	'setup',
	'post-types',
	'lms',
	'course-meta',
	'blocks',
	'contact',
	'seo',
	'woocommerce',
	'demo',
);

foreach ( $kh_includes as $kh_file ) {
	require_once KH_DIR . '/inc/' . $kh_file . '.php';
}
