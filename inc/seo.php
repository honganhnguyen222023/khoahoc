<?php
/**
 * SEO: meta description, Open Graph, JSON-LD (Organization, Course, FAQ, Breadcrumb).
 * Steps aside automatically when Yoast SEO / Rank Math / AIOSEO / SEOPress is active.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is a dedicated SEO plugin handling meta tags?
 *
 * @return bool
 */
function kh_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

/**
 * Description for the current page.
 *
 * @return string
 */
function kh_meta_description() {
	if ( is_front_page() || is_home() ) {
		return kh_opt( 'seo_description' );
	}
	if ( is_singular() ) {
		$post = get_queried_object();
		$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$text = wp_trim_words( $text, 30, '…' );
		// Pages built only from theme blocks have no plain text: use the site description.
		return '' !== trim( $text ) ? $text : kh_opt( 'seo_description' );
	}
	if ( is_post_type_archive( 'khoa_hoc' ) ) {
		return sprintf( 'Danh sách khóa học tại %s. %s', kh_opt( 'brand_name' ), kh_opt( 'seo_description' ) );
	}
	if ( is_tax() || is_category() ) {
		$d = term_description();
		return $d ? wp_strip_all_tags( $d ) : kh_opt( 'seo_description' );
	}
	return kh_opt( 'seo_description' );
}

/**
 * Share image.
 *
 * @return string
 */
function kh_share_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
		if ( $src ) {
			return $src[0];
		}
	}
	return kh_opt( 'og_image' );
}

add_action(
	'wp_head',
	function () {
		if ( kh_seo_plugin_active() ) {
			return;
		}
		$desc  = trim( preg_replace( '/\s+/', ' ', kh_meta_description() ) );
		$title = wp_get_document_title();
		$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
		$img   = kh_share_image();

		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( is_search() || is_404() ) {
			echo '<meta name="robots" content="noindex,follow">' . "\n";
		}
		echo '<meta property="og:locale" content="vi_VN">' . "\n";
		echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( kh_opt( 'brand_name' ) ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		if ( $img ) {
			echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		}
	},
	2
);

/**
 * Collect FAQ items from the blocks of a post.
 *
 * @param array $blocks Parsed blocks.
 * @param array $out    Accumulator.
 * @return array
 */
function kh_collect_faq( $blocks, $out = array() ) {
	foreach ( $blocks as $b ) {
		if ( 'kh/faq-item' === $b['blockName'] && ! empty( $b['attrs']['question'] ) ) {
			$out[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $b['attrs']['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( isset( $b['attrs']['answer'] ) ? $b['attrs']['answer'] : '' ),
				),
			);
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			$out = kh_collect_faq( $b['innerBlocks'], $out );
		}
	}
	return $out;
}

add_action(
	'wp_head',
	function () {
		$graph = array();
		$org   = array(
			'@type' => 'EducationalOrganization',
			'@id'   => home_url( '/#org' ),
			'name'  => kh_opt( 'brand_name' ),
			'url'   => home_url( '/' ),
		);
		$logo  = get_theme_mod( 'custom_logo' );
		if ( $logo ) {
			$org['logo'] = wp_get_attachment_image_url( $logo, 'full' );
		}
		if ( kh_opt( 'phone' ) ) {
			$org['telephone'] = kh_opt( 'phone' );
		}
		if ( kh_opt( 'email' ) ) {
			$org['email'] = kh_opt( 'email' );
		}
		$same = array_values( array_filter( array( kh_opt( 'facebook' ), kh_opt( 'tiktok' ), kh_opt( 'youtube' ) ) ) );
		if ( $same ) {
			$org['sameAs'] = $same;
		}

		if ( is_front_page() ) {
			$graph[] = $org;
			$graph[] = array(
				'@type'     => 'WebSite',
				'@id'       => home_url( '/#website' ),
				'url'       => home_url( '/' ),
				'name'      => kh_opt( 'brand_name' ),
				'publisher' => array( '@id' => home_url( '/#org' ) ),
				'inLanguage'=> 'vi',
			);
		}

		if ( is_singular( 'khoa_hoc' ) ) {
			$c      = kh_course( get_queried_object_id() );
			$course = array(
				'@type'       => 'Course',
				'name'        => get_the_title(),
				'description' => kh_meta_description(),
				'url'         => get_permalink(),
				'inLanguage'  => 'vi',
				'provider'    => array(
					'@type' => 'Organization',
					'name'  => kh_opt( 'brand_name' ),
					'sameAs'=> home_url( '/' ),
				),
				'hasCourseInstance' => array(
					'@type'          => 'CourseInstance',
					'courseMode'     => 'Online',
					'courseWorkload' => $c['meta_line'] ? $c['meta_line'] : 'Học online theo tốc độ riêng',
				),
			);
			if ( (float) $c['price'] > 0 ) {
				$course['offers'] = array(
					'@type'         => 'Offer',
					'category'      => 'Paid',
					'price'         => (string) (float) $c['price'],
					'priceCurrency' => 'VND',
					'availability'  => 'https://schema.org/InStock',
					'url'           => get_permalink(),
				);
			}
			if ( has_post_thumbnail() ) {
				$course['image'] = get_the_post_thumbnail_url( null, 'large' );
			}
			$graph[] = $course;
		}

		if ( is_singular() && ! is_front_page() ) {
			$items   = array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => home_url( '/' ) ),
			);
			if ( is_singular( 'khoa_hoc' ) ) {
				$items[] = array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Khóa học', 'item' => get_post_type_archive_link( 'khoa_hoc' ) );
			}
			$items[] = array( '@type' => 'ListItem', 'position' => count( $items ) + 1, 'name' => get_the_title(), 'item' => get_permalink() );
			$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post && has_blocks( $post->post_content ) ) {
				$faq = kh_collect_faq( parse_blocks( $post->post_content ) );
				if ( $faq ) {
					$graph[] = array( '@type' => 'FAQPage', 'mainEntity' => $faq );
				}
			}
		}

		// Avoid duplicating what an SEO plugin already prints (it covers Organization/Breadcrumb).
		if ( kh_seo_plugin_active() ) {
			$graph = array_values(
				array_filter(
					$graph,
					function ( $n ) {
						return in_array( $n['@type'], array( 'Course', 'FAQPage' ), true );
					}
				)
			);
		}

		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
		}
	},
	20
);

// Google Analytics 4 + custom head code.
add_action(
	'wp_head',
	function () {
		$ga = kh_opt( 'ga4_id' );
		if ( $ga && preg_match( '/^G-[A-Z0-9]+$/', $ga ) ) {
			printf(
				"<script async src=\"https://www.googletagmanager.com/gtag/js?id=%1\$s\"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','%1\$s');</script>\n",
				esc_attr( $ga )
			);
		}
		$code = kh_opt( 'head_code' );
		if ( $code ) {
			echo $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- admin-only (unfiltered_html) setting.
		}
	},
	30
);

// Home page <title>: "Thương hiệu – Khẩu hiệu" from settings.
add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( is_front_page() && ! kh_seo_plugin_active() ) {
			$parts['title'] = kh_opt( 'brand_name' );
			if ( kh_opt( 'brand_tagline' ) ) {
				$parts['tagline'] = kh_opt( 'brand_tagline' );
			}
			unset( $parts['site'] );
		}
		return $parts;
	}
);
