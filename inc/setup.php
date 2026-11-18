<?php
/**
 * Theme supports, assets, performance clean-up.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		load_theme_textdomain( 'khoahoc', KH_DIR . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 96,
				'width'       => 96,
				'flex-width'  => true,
				'flex-height' => true,
			)
		);
		add_editor_style( 'assets/css/app.css' );

		register_nav_menus(
			array(
				'primary'  => __( 'Menu chính (đầu trang)', 'khoahoc' ),
				'footer_1' => __( 'Chân trang – cột 1', 'khoahoc' ),
				'footer_2' => __( 'Chân trang – cột 2', 'khoahoc' ),
			)
		);

		add_image_size( 'kh-card', 720, 405, true );
		add_image_size( 'kh-portrait', 683, 1024, false );

		// Colours in the editor palette match the theme.
		add_theme_support(
			'editor-color-palette',
			array(
				array( 'name' => 'Màu chính', 'slug' => 'brand', 'color' => kh_opt( 'color_primary' ) ),
				array( 'name' => 'Tiêu đề', 'slug' => 'ink', 'color' => kh_opt( 'color_ink' ) ),
				array( 'name' => 'Chữ thường', 'slug' => 'body', 'color' => '#57586E' ),
				array( 'name' => 'Nền nhạt', 'slug' => 'surface', 'color' => '#F2F7F4' ),
				array( 'name' => 'Trắng', 'slug' => 'white', 'color' => '#FFFFFF' ),
			)
		);
	}
);

/**
 * Mix a hex colour with white (amount 0..1) or black (negative).
 *
 * @param string $hex    Colour.
 * @param float  $amount Amount.
 * @return string
 */
function kh_mix( $hex, $amount ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 6 !== strlen( $hex ) ) {
		return '#' . $hex;
	}
	$rgb = array_map( 'hexdec', str_split( $hex, 2 ) );
	foreach ( $rgb as &$c ) {
		$c = $amount >= 0 ? $c + ( 255 - $c ) * $amount : $c * ( 1 + $amount );
		$c = max( 0, min( 255, (int) round( $c ) ) );
	}
	return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
}

/**
 * CSS custom properties from settings.
 *
 * @return string
 */
function kh_css_vars() {
	$p = kh_opt( 'color_primary' );
	return sprintf(
		':root{--kh-primary:%1$s;--kh-primary-dark:%2$s;--kh-primary-soft:%3$s;--kh-mint:%4$s;--kh-ink:%5$s;--kh-deep:%6$s;--kh-body:#57586E}',
		esc_attr( $p ),
		esc_attr( kh_mix( $p, -0.22 ) ),
		esc_attr( kh_mix( $p, 0.9 ) ),
		esc_attr( kh_mix( $p, 0.75 ) ),
		esc_attr( kh_opt( 'color_ink' ) ),
		esc_attr( kh_opt( 'color_deep' ) )
	);
}

/**
 * Font files to preload (the ones above the fold).
 *
 * @return array
 */
function kh_preload_fonts() {
	return array( 'be-vietnam-pro-vietnamese-800-normal.woff2', 'be-vietnam-pro-latin-800-normal.woff2', 'inter-latin-400-normal.woff2', 'inter-vietnamese-400-normal.woff2' );
}

add_action(
	'wp_head',
	function () {
		foreach ( kh_preload_fonts() as $font ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( KH_URI . '/assets/fonts/' . $font ) );
		}
	},
	1
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$css = KH_DIR . '/assets/css/app.css';
		wp_enqueue_style( 'kh-app', KH_URI . '/assets/css/app.css', array(), KH_VERSION . '.' . ( file_exists( $css ) ? filemtime( $css ) : 0 ) );
		wp_add_inline_style( 'kh-app', kh_css_vars() . kh_font_faces() );

		$js = KH_DIR . '/assets/js/app.js';
		wp_enqueue_script( 'kh-app', KH_URI . '/assets/js/app.js', array(), KH_VERSION . '.' . ( file_exists( $js ) ? filemtime( $js ) : 0 ), array( 'strategy' => 'defer', 'in_footer' => true ) );

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}

		// Classic theme: skip global styles/SVG filters we do not use.
		wp_dequeue_style( 'classic-theme-styles' );
	}
);

/**
 * @font-face rules for the self-hosted fonts (Vietnamese + Latin subsets).
 *
 * @return string
 */
function kh_font_faces() {
	$base   = KH_URI . '/assets/fonts/';
	$ranges = array(
		'latin'      => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD',
		'vietnamese' => 'U+0102-0103,U+0110-0111,U+0128-0129,U+0168-0169,U+01A0-01A1,U+01AF-01B0,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+0329,U+1EA0-1EF9,U+20AB',
	);
	$fonts  = array(
		'Inter'          => array( 'inter', array( 400, 500, 600 ) ),
		'Be Vietnam Pro' => array( 'be-vietnam-pro', array( 600, 700, 800 ) ),
	);
	$css    = '';
	foreach ( $fonts as $family => $def ) {
		foreach ( $def[1] as $weight ) {
			foreach ( $ranges as $subset => $range ) {
				$css .= sprintf(
					"@font-face{font-family:'%s';font-style:normal;font-display:swap;font-weight:%d;src:url(%s) format('woff2');unicode-range:%s}",
					$family,
					$weight,
					esc_url( $base . $def[0] . '-' . $subset . '-' . $weight . '-normal.woff2' ),
					$range
				);
			}
		}
	}
	return $css;
}

// Same fonts + colours inside the block editor.
add_action(
	'enqueue_block_assets',
	function () {
		if ( is_admin() ) {
			wp_register_style( 'kh-editor-vars', false, array(), KH_VERSION );
			wp_enqueue_style( 'kh-editor-vars' );
			wp_add_inline_style( 'kh-editor-vars', kh_css_vars() . kh_font_faces() );
		}
	}
);

// ---- Performance clean-up -------------------------------------------------
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
add_filter( 'emoji_svg_url', '__return_false' );

// Load only the CSS of core blocks that are actually on the page.
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * Body classes.
 */
add_filter(
	'body_class',
	function ( $classes ) {
		if ( kh_opt( 'announce_text' ) ) {
			$classes[] = 'has-announce';
		}
		return $classes;
	}
);

add_filter(
	'excerpt_length',
	function () {
		return 28;
	}
);
add_filter(
	'excerpt_more',
	function () {
		return '…';
	}
);

/**
 * Menu fallback: list main pages so the header is never empty.
 *
 * @param array $args Menu args.
 */
function kh_menu_fallback( $args ) {
	$items = array(
		home_url( '/' )                       => __( 'Trang chủ', 'khoahoc' ),
		get_post_type_archive_link( 'khoa_hoc' ) => __( 'Khóa học', 'khoahoc' ),
	);
	foreach ( array( 'gioi-thieu', 'lien-he' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			$items[ get_permalink( $page ) ] = get_the_title( $page );
		}
	}
	$class = isset( $args['menu_class'] ) ? $args['menu_class'] : '';
	echo '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $items as $url => $label ) {
		if ( $url ) {
			echo '<li class="menu-item"><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
		}
	}
	echo '</ul>';
}
