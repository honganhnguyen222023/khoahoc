<?php
/**
 * Custom Gutenberg blocks. All blocks are rendered by PHP ("dynamic"), so the
 * design can be improved later without breaking content already written.
 * Editing UI lives in blocks/editor.js (no build step needed).
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block name => render callback.
 *
 * @return array
 */
function kh_block_list() {
	return array(
		'section'      => 'kh_render_section',
		'hero'         => 'kh_render_hero',
		'stats'        => 'kh_render_stats',
		'stat'         => 'kh_render_stat',
		'grid'         => 'kh_render_grid',
		'card'         => 'kh_render_card',
		'about'        => 'kh_render_about',
		'courses'      => 'kh_render_courses',
		'masonry'      => 'kh_render_masonry',
		'faq'          => 'kh_render_faq',
		'faq-item'     => 'kh_render_faq_item',
		'cta'          => 'kh_render_cta',
		'contact-form' => 'kh_render_contact_form',
		'chapter'      => 'kh_render_chapter',
		'lesson'       => 'kh_render_lesson',
	);
}

add_action(
	'init',
	function () {
		$js = KH_DIR . '/blocks/editor.js';
		wp_register_script(
			'kh-blocks',
			KH_URI . '/blocks/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-server-side-render' ),
			KH_VERSION . '.' . ( file_exists( $js ) ? filemtime( $js ) : 0 ),
			true
		);
		wp_localize_script(
			'kh-blocks',
			'KH_EDITOR',
			array(
				'icons'      => kh_icons(),
				'categories' => kh_course_category_options(),
				'phone'      => kh_opt( 'phone' ),
				'email'      => kh_opt( 'email' ),
			)
		);
		foreach ( kh_block_list() as $name => $cb ) {
			$args = array(
				'editor_script'   => 'kh-blocks',
				'render_callback' => $cb,
			);
			if ( 'courses' === $name ) {
				// Needed so the editor preview (ServerSideRender) accepts these attributes.
				$args['attributes'] = array(
					'layout'     => array( 'type' => 'string', 'default' => 'featured' ),
					'count'      => array( 'type' => 'number', 'default' => 2 ),
					'category'   => array( 'type' => 'string', 'default' => '' ),
					'title'      => array( 'type' => 'string', 'default' => '' ),
					'onlyOthers' => array( 'type' => 'boolean', 'default' => true ),
					'className'  => array( 'type' => 'string' ),
				);
			}
			register_block_type( 'kh/' . $name, $args );
		}
	}
);

/**
 * Course categories for the "Danh sách khóa học" block.
 *
 * @return array
 */
function kh_course_category_options() {
	$out   = array( array( 'label' => 'Tất cả', 'value' => '' ) );
	$terms = get_terms( array( 'taxonomy' => 'danh_muc_khoa_hoc', 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$out[] = array( 'label' => $t->name, 'value' => $t->slug );
		}
	}
	return $out;
}

add_filter(
	'block_categories_all',
	function ( $cats ) {
		array_unshift( $cats, array( 'slug' => 'khoahoc', 'title' => __( 'Khối của theme', 'khoahoc' ), 'icon' => null ) );
		return $cats;
	}
);

/**
 * Attribute getter with default.
 *
 * @param array  $a   Attributes.
 * @param string $key Key.
 * @param mixed  $def Default.
 * @return mixed
 */
function kh_a( $a, $key, $def = '' ) {
	return isset( $a[ $key ] ) && '' !== $a[ $key ] && null !== $a[ $key ] ? $a[ $key ] : $def;
}

/**
 * Image tag from block attributes (uses srcset when the image is in the Media Library).
 *
 * @param array  $a     Attributes.
 * @param string $size  Size.
 * @param array  $attr  Extra attributes.
 * @param string $prefix Attribute prefix (image).
 * @return string
 */
function kh_block_img( $a, $size = 'large', $attr = array(), $prefix = 'image' ) {
	$id  = (int) kh_a( $a, $prefix . 'Id', 0 );
	$url = kh_a( $a, $prefix . 'Url' );
	$alt = kh_a( $a, $prefix . 'Alt' );
	if ( $id && wp_attachment_is_image( $id ) ) {
		if ( '' !== $alt ) {
			$attr['alt'] = $alt;
		}
		return wp_get_attachment_image( $id, $size, false, $attr );
	}
	if ( $url ) {
		$extra = '';
		foreach ( $attr as $k => $v ) {
			$extra .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		if ( empty( $attr['loading'] ) ) {
			$extra .= ' loading="lazy"';
		}
		return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" decoding="async"' . $extra . '>';
	}
	return '';
}

/**
 * Section heading (eyebrow, title, text).
 *
 * @param array  $a     Attributes.
 * @param string $tag   Heading tag.
 * @return string
 */
function kh_heading( $a, $tag = 'h2' ) {
	$out = '';
	if ( kh_a( $a, 'eyebrow' ) ) {
		$out .= '<p class="kh-eyebrow">' . kh_rich( $a['eyebrow'] ) . '</p>';
	}
	if ( kh_a( $a, 'title' ) ) {
		$out .= '<' . $tag . ' class="kh-h2">' . kh_rich( $a['title'] ) . '</' . $tag . '>';
	}
	if ( kh_a( $a, 'text' ) ) {
		$out .= '<p class="kh-lead">' . kh_rich( $a['text'] ) . '</p>';
	}
	return $out ? '<header class="kh-section-head">' . $out . '</header>' : '';
}

/**
 * Anchor + extra class from block supports.
 *
 * @param array $a Attributes.
 * @return array [id attr string, class string]
 */
function kh_block_wrap( $a ) {
	$id    = kh_a( $a, 'anchor' ) ? ' id="' . esc_attr( $a['anchor'] ) . '"' : '';
	$class = kh_a( $a, 'className' );
	return array( $id, $class );
}

// ---------------------------------------------------------------------------

/**
 * Section wrapper.
 *
 * @param array  $a       Attributes.
 * @param string $content Inner blocks.
 * @return string
 */
function kh_render_section( $a, $content ) {
	list( $id, $class ) = kh_block_wrap( $a );
	$bg    = kh_a( $a, 'bg', 'white' );
	$align = kh_a( $a, 'align', 'center' );
	$note  = kh_a( $a, 'footnote' ) ? '<p class="kh-footnote">' . kh_rich( $a['footnote'] ) . '</p>' : '';
	return sprintf(
		'<section%1$s class="%2$s"><div class="kh-container"><div class="kh-section-box">%3$s<div class="kh-section-body">%4$s</div>%5$s</div></div></section>',
		$id,
		kh_cx( array( 'kh-section', 'kh-bg-' . $bg, 'kh-align-' . $align, $class ) ),
		kh_heading( $a ),
		$content,
		$note
	);
}

/**
 * Hero.
 *
 * @param array  $a       Attributes.
 * @param string $content Inner blocks (buttons + checklist).
 * @return string
 */
function kh_render_hero( $a, $content ) {
	list( $id, $class ) = kh_block_wrap( $a );
	$badge = '';
	if ( kh_a( $a, 'badgeText' ) ) {
		$badge = '<p class="kh-pill-note">' . ( kh_a( $a, 'badge' ) ? '<span>' . esc_html( $a['badge'] ) . '</span>' : '' ) . kh_rich( $a['badgeText'] ) . '</p>';
	}
	$img = kh_block_img(
		$a,
		'kh-portrait',
		array(
			'class'         => 'kh-hero-img',
			'fetchpriority' => 'high',
			'loading'       => 'eager',
			'sizes'         => '(min-width: 1024px) 430px, 90vw',
		)
	);
	if ( ! $img ) {
		$img = '<img class="kh-hero-img" src="' . esc_url( KH_URI . '/assets/img/hero-placeholder.svg' ) . '" alt="" width="683" height="1024" fetchpriority="high">';
	}
	$formula = kh_a( $a, 'formula' ) ? '<div class="kh-formula"><span>fx</span><code>' . esc_html( $a['formula'] ) . '</code></div>' : '';
	$chips   = '';
	foreach ( array( 1, 2 ) as $n ) {
		if ( kh_a( $a, "chip{$n}Value" ) ) {
			$chips .= sprintf(
				'<div class="kh-chip kh-chip-%1$d"><span class="kh-chip-icon">%2$s</span><span><strong>%3$s</strong><small>%4$s</small></span></div>',
				$n,
				kh_icon( kh_a( $a, "chip{$n}Icon", 1 === $n ? 'users' : 'table' ), '', 18 ),
				esc_html( $a[ "chip{$n}Value" ] ),
				esc_html( kh_a( $a, "chip{$n}Label" ) )
			);
		}
	}
	return sprintf(
		'<section%1$s class="%2$s"><div class="kh-container"><div class="kh-hero-box"><div class="kh-hero-copy">%3$s<h1 class="kh-h1">%4$s</h1>%5$s<div class="kh-hero-inner">%6$s</div></div><div class="kh-hero-media"><figure class="kh-hero-frame">%7$s%8$s</figure>%9$s</div></div></div></section>',
		$id,
		kh_cx( array( 'kh-hero', $class ) ),
		$badge,
		kh_rich( kh_a( $a, 'title' ) ),
		kh_a( $a, 'text' ) ? '<p class="kh-hero-text">' . kh_rich( $a['text'] ) . '</p>' : '',
		$content,
		$formula,
		$img,
		$chips
	);
}

/**
 * Stats row.
 *
 * @param array  $a       Attributes.
 * @param string $content Inner stats.
 * @return string
 */
function kh_render_stats( $a, $content ) {
	$variant = kh_a( $a, 'variant', 'bar' );
	if ( 'compact' === $variant ) {
		return '<div class="kh-stats kh-stats-compact">' . $content . '</div>';
	}
	return '<div class="kh-container"><div class="kh-stats kh-stats-bar">' . $content . '</div></div>';
}

/**
 * Single stat.
 *
 * @param array $a Attributes.
 * @return string
 */
function kh_render_stat( $a ) {
	return sprintf(
		'<div class="kh-stat"><strong class="kh-stat-value" data-count>%1$s</strong><span class="kh-stat-label">%2$s</span></div>',
		esc_html( kh_a( $a, 'value' ) ),
		esc_html( kh_a( $a, 'label' ) )
	);
}

/**
 * Card grid.
 *
 * @param array  $a       Attributes.
 * @param string $content Inner cards.
 * @return string
 */
function kh_render_grid( $a, $content ) {
	$cols  = max( 1, min( 4, (int) kh_a( $a, 'columns', 3 ) ) );
	$title = kh_a( $a, 'title' ) ? '<h3 class="kh-grid-title">' . kh_rich( $a['title'] ) . '</h3>' : '';
	$grid  = '<div class="kh-grid kh-cols-' . $cols . '">' . $content . '</div>';
	if ( kh_a( $a, 'boxed', false ) ) {
		return '<div class="kh-panel">' . $title . $grid . '</div>';
	}
	return '<div class="kh-grid-wrap">' . $title . $grid . '</div>';
}

/**
 * Card (6 looks).
 *
 * @param array $a Attributes.
 * @return string
 */
function kh_render_card( $a ) {
	$variant = kh_a( $a, 'variant', 'feature' );
	$title   = kh_rich( kh_a( $a, 'title' ) );
	$text    = kh_a( $a, 'text' ) ? '<p class="kh-card-text">' . kh_rich( $a['text'] ) . '</p>' : '';
	$url     = kh_a( $a, 'url' );
	$icon    = kh_a( $a, 'icon', 'sparkles' );

	switch ( $variant ) {
		case 'problem':
			$inner = '<span class="kh-num">' . esc_html( kh_a( $a, 'number' ) ) . '</span><div><h3 class="kh-card-title">' . $title . '</h3>' . $text . '</div>';
			break;
		case 'step':
			$inner = '<p class="kh-step-label"><span>' . esc_html( kh_a( $a, 'number', '1' ) ) . '</span>' . esc_html( kh_a( $a, 'meta2', 'Bước' ) ) . '</p><h3 class="kh-card-title">' . $title . '</h3>' . $text . ( kh_a( $a, 'meta' ) ? '<p class="kh-card-meta">' . esc_html( $a['meta'] ) . '</p>' : '' );
			break;
		case 'image':
			$img   = kh_block_img( $a, 'kh-card', array( 'class' => 'kh-card-img' ) );
			$inner = ( $img ? '<div class="kh-card-media">' . $img . '</div>' : '<div class="kh-card-media kh-card-media-empty"></div>' ) . '<div class="kh-card-body"><h3 class="kh-card-title">' . $title . '</h3>' . $text . '</div>';
			break;
		case 'link':
			$inner = '<span class="kh-icon-box">' . kh_icon( $icon, '', 20 ) . '</span><div class="kh-card-grow"><h3 class="kh-card-title">' . $title . '</h3>' . $text . '</div>' . kh_icon( 'arrow-up-right', 'kh-card-arrow', 16 );
			break;
		case 'choice':
			$inner = '<span class="kh-choice-need">' . kh_rich( kh_a( $a, 'text' ) ) . '</span><strong class="kh-choice-pick">' . $title . '</strong>';
			break;
		default: // feature.
			$inner = '<span class="kh-icon-box">' . kh_icon( $icon, '', 20 ) . '</span><h3 class="kh-card-title">' . $title . '</h3>' . $text;
	}

	$classes = kh_cx( array( 'kh-card', 'kh-card-' . $variant, kh_a( $a, 'className' ) ) );
	if ( $url ) {
		return '<a class="' . $classes . ' is-link" ' . kh_link_attrs( $url ) . '>' . $inner . '</a>';
	}
	return '<div class="' . $classes . '">' . $inner . '</div>';
}

/**
 * About block.
 *
 * @param array  $a       Attributes.
 * @param string $content Inner (paragraphs, stats).
 * @return string
 */
function kh_render_about( $a, $content ) {
	list( $id, $class ) = kh_block_wrap( $a );
	$img = kh_block_img( $a, 'kh-portrait', array( 'class' => 'kh-about-img', 'sizes' => '(min-width: 1024px) 420px, 90vw' ) );
	if ( ! $img ) {
		$img = '<img class="kh-about-img" src="' . esc_url( KH_URI . '/assets/img/hero-placeholder.svg' ) . '" alt="" width="683" height="1024" loading="lazy">';
	}
	$quote = '';
	if ( kh_a( $a, 'quote' ) ) {
		$quote = '<blockquote class="kh-quote"><p>' . kh_rich( $a['quote'] ) . '</p>' . ( kh_a( $a, 'quoteAuthor' ) ? '<cite>' . esc_html( $a['quoteAuthor'] ) . '</cite>' : '' ) . '</blockquote>';
	}
	return sprintf(
		'<section%1$s class="%2$s"><div class="kh-container"><div class="kh-section-box"><div class="kh-about"><div class="kh-about-media">%3$s%4$s</div><div class="kh-about-copy">%5$s<h2 class="kh-h2">%6$s</h2><div class="kh-prose">%7$s</div></div></div></div></div></section>',
		$id,
		kh_cx( array( 'kh-section', 'kh-bg-' . kh_a( $a, 'bg', 'soft' ), $class ) ),
		$img,
		$quote,
		kh_a( $a, 'eyebrow' ) ? '<p class="kh-eyebrow">' . kh_rich( $a['eyebrow'] ) . '</p>' : '',
		kh_rich( kh_a( $a, 'title' ) ),
		$content
	);
}

/**
 * Courses list from "Khóa học".
 *
 * @param array $a Attributes.
 * @return string
 */
function kh_render_courses( $a ) {
	$layout = kh_a( $a, 'layout', 'featured' );
	$args   = array(
		'post_type'      => 'khoa_hoc',
		'posts_per_page' => (int) kh_a( $a, 'count', 'featured' === $layout ? 2 : 8 ),
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'no_found_rows'  => true,
	);
	if ( 'featured' === $layout ) {
		$args['meta_query'] = array( array( 'key' => '_kh_featured', 'value' => '1' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	} elseif ( kh_a( $a, 'onlyOthers', true ) ) {
		$args['meta_query'] = array( array( 'key' => '_kh_featured', 'compare' => 'NOT EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	if ( kh_a( $a, 'category' ) ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'danh_muc_khoa_hoc', 'field' => 'slug', 'terms' => $a['category'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$q = new WP_Query( $args );
	if ( ! $q->have_posts() ) {
		return current_user_can( 'edit_posts' ) ? '<p class="kh-empty">Chưa có khóa học phù hợp. Vào <strong>Khóa học → Thêm khóa học</strong>' . ( 'featured' === $layout ? ' và tick "Khóa nổi bật"' : '' ) . '.</p>' : '';
	}
	$title = kh_a( $a, 'title' ) ? '<h3 class="kh-grid-title">' . kh_rich( $a['title'] ) . '</h3>' : '';
	ob_start();
	echo $title; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="' . ( 'featured' === $layout ? 'kh-course-featured' : 'kh-grid kh-cols-4 kh-course-grid' ) . '">';
	while ( $q->have_posts() ) {
		$q->the_post();
		get_template_part( 'template-parts/course', 'featured' === $layout ? 'featured' : 'card' );
	}
	echo '</div>';
	wp_reset_postdata();
	return ob_get_clean();
}

/**
 * Masonry images (testimonial screenshots).
 *
 * @param array $a Attributes.
 * @return string
 */
function kh_render_masonry( $a ) {
	$images = (array) kh_a( $a, 'images', array() );
	if ( ! $images ) {
		return '';
	}
	$out = '';
	foreach ( $images as $img ) {
		$tag = kh_block_img(
			array(
				'imageId'  => isset( $img['id'] ) ? $img['id'] : 0,
				'imageUrl' => isset( $img['url'] ) ? $img['url'] : '',
				'imageAlt' => isset( $img['alt'] ) ? $img['alt'] : '',
			),
			'large',
			array( 'sizes' => '(min-width: 1024px) 340px, 90vw' )
		);
		if ( $tag ) {
			$out .= '<figure class="kh-masonry-item">' . $tag . '</figure>';
		}
	}
	return '<div class="kh-masonry">' . $out . '</div>';
}

/**
 * FAQ list.
 *
 * @param array  $a       Attributes.
 * @param string $content Items.
 * @return string
 */
function kh_render_faq( $a, $content ) {
	return '<div class="kh-faq">' . $content . '</div>';
}

/**
 * FAQ item (native <details>, works without JS).
 *
 * @param array $a Attributes.
 * @return string
 */
function kh_render_faq_item( $a ) {
	if ( ! kh_a( $a, 'question' ) ) {
		return '';
	}
	return sprintf(
		'<details class="kh-faq-item"><summary><span>%1$s</span><i aria-hidden="true">%2$s</i></summary><div class="kh-faq-answer"><p>%3$s</p></div></details>',
		kh_rich( $a['question'] ),
		kh_icon( 'plus', '', 14 ),
		kh_rich( kh_a( $a, 'answer' ) )
	);
}

/**
 * Dark call-to-action box; contacts come from "Cài đặt website".
 *
 * @param array  $a       Attributes.
 * @param string $content Buttons.
 * @return string
 */
function kh_render_cta( $a, $content ) {
	list( $id, $class ) = kh_block_wrap( $a );
	$contacts = '';
	if ( kh_a( $a, 'showContacts', true ) ) {
		$items = array();
		if ( kh_opt( 'phone' ) ) {
			$items[] = array( 'phone', 'Hotline / Zalo', kh_opt( 'phone' ), 'tel:' . kh_digits( kh_opt( 'phone' ) ) );
		}
		if ( kh_opt( 'email' ) ) {
			$items[] = array( 'mail', 'Email', kh_opt( 'email' ), 'mailto:' . kh_opt( 'email' ) );
		}
		foreach ( $items as $it ) {
			$contacts .= sprintf( '<a class="kh-cta-contact" href="%4$s"><span>%1$s</span><span><small>%2$s</small>%3$s</span></a>', kh_icon( $it[0], '', 18 ), esc_html( $it[1] ), esc_html( $it[2] ), esc_attr( $it[3] ) );
		}
		$contacts = $contacts ? '<div class="kh-cta-contacts">' . $contacts . '</div>' : '';
	}
	return sprintf(
		'<section%1$s class="%2$s"><div class="kh-container"><div class="kh-cta-box"><div class="kh-cta-copy"><h2 class="kh-h2">%3$s</h2>%4$s<div class="kh-cta-actions">%5$s</div></div>%6$s</div></div></section>',
		$id,
		kh_cx( array( 'kh-cta', $class ) ),
		kh_rich( kh_a( $a, 'title' ) ),
		kh_a( $a, 'text' ) ? '<p>' . kh_rich( $a['text'] ) . '</p>' : '',
		$content,
		$contacts
	);
}

/**
 * Course chapter (collapsible on the course page).
 *
 * @param array  $a       Attributes.
 * @param string $content Lessons.
 * @return string
 */
function kh_render_chapter( $a, $content ) {
	$count = substr_count( $content, '<li class="kh-lesson"' );
	return sprintf(
		'<details class="kh-chapter"%4$s><summary><span class="kh-chapter-title">%1$s</span><span class="kh-chapter-count">%2$s</span>%3$s</summary><ol class="kh-lessons">%5$s</ol></details>',
		kh_rich( kh_a( $a, 'title', 'Chương' ) ),
		$count ? esc_html( sprintf( '%d bài', $count ) ) : '',
		kh_icon( 'chevron-down', 'kh-chapter-caret', 18 ),
		kh_a( $a, 'open', false ) ? ' open' : '',
		$content
	);
}

/**
 * Lesson row.
 *
 * @param array $a Attributes.
 * @return string
 */
function kh_render_lesson( $a ) {
	$type  = kh_a( $a, 'type', 'video' );
	$title = kh_rich( kh_a( $a, 'title' ) );
	$url   = kh_a( $a, 'url' );
	$label = $url ? '<a ' . kh_link_attrs( $url ) . '>' . $title . '</a><span class="kh-preview">Học thử</span>' : '<span>' . $title . '</span>';
	return sprintf(
		'<li class="kh-lesson">%1$s<span class="kh-lesson-title">%2$s</span><span class="kh-lesson-time">%3$s</span></li>',
		kh_icon( 'doc' === $type ? 'file-text' : ( $url ? 'play' : 'lock' ), 'kh-lesson-icon', 16 ),
		$label,
		esc_html( kh_a( $a, 'duration' ) )
	);
}
