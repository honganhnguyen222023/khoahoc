<?php
/**
 * Xuất toàn bộ dữ liệu website WordPress (theme KhoaHoc) ra 1 file JSON
 * để nhập vào bản Node.js (scripts/import-wordpress.mjs).
 *
 * Cách chạy (site WordPress phải đang chạy, ví dụ trong app Local):
 *   php -d mysqli.default_socket="<socket MySQL của Local>" scripts/export-wordpress.php \
 *       "/duong/dan/toi/wordpress" > data/wordpress-export.json
 *
 * @package KhoaHoc
 */

if ( empty( $argv[1] ) || ! file_exists( rtrim( $argv[1], '/' ) . '/wp-load.php' ) ) {
	fwrite( STDERR, "Cách dùng: php export-wordpress.php /duong/dan/wordpress\n" );
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_USE_THEMES', false );
require rtrim( $argv[1], '/' ) . '/wp-load.php';

$home   = untrailingslashit( home_url() );
$theme  = untrailingslashit( get_template_directory_uri() );
$upload = untrailingslashit( wp_get_upload_dir()['baseurl'] );

/**
 * Đổi link tuyệt đối của WordPress thành đường dẫn của bản Node.
 */
function kx_url( $url ) {
	global $home, $theme, $upload;
	$url = (string) $url;
	if ( '' === $url ) {
		return '';
	}
	$url = str_replace( $theme . '/assets/img/', '/img/', $url );
	$url = str_replace( $upload . '/', '/uploads/', $url );
	if ( 0 === strpos( $url, $home ) ) {
		$url = substr( $url, strlen( $home ) );
		$url = '' === $url ? '/' : $url;
	}
	return $url;
}

/** Đổi link trong một đoạn HTML. */
function kx_html( $html ) {
	global $home, $theme, $upload;
	$html = str_replace( array( $theme . '/assets/img/', $upload . '/' ), array( '/img/', '/uploads/' ), (string) $html );
	return str_replace( array( '"' . $home . '/', "'" . $home . '/' ), array( '"/', "'/" ), $html );
}

/** Cây khối Gutenberg → mảng gọn. */
function kx_blocks( $content ) {
	$walk = function ( $blocks ) use ( &$walk ) {
		$out = array();
		foreach ( $blocks as $b ) {
			if ( null === $b['blockName'] && '' === trim( (string) $b['innerHTML'] ) ) {
				continue;
			}
			$attrs = (array) $b['attrs'];
			array_walk_recursive(
				$attrs,
				function ( &$v, $k ) {
					if ( is_string( $v ) && ( in_array( $k, array( 'url', 'imageUrl', 'href' ), true ) || preg_match( '~^https?://~', $v ) ) ) {
						$v = kx_url( $v );
					}
				}
			);
			$out[] = array(
				'name'    => $b['blockName'],
				'attrs'   => (object) $attrs,
				'inner'   => $walk( $b['innerBlocks'] ),
				'content' => array_map( function ( $c ) { return null === $c ? null : kx_html( $c ); }, $b['innerContent'] ),
			);
		}
		return $out;
	};
	return $walk( parse_blocks( (string) $content ) );
}

/** Menu theo vị trí. */
function kx_menu( $location ) {
	$locs = get_nav_menu_locations();
	if ( empty( $locs[ $location ] ) ) {
		return null;
	}
	$menu  = wp_get_nav_menu_object( $locs[ $location ] );
	$items = wp_get_nav_menu_items( $locs[ $location ] );
	$flat  = array();
	foreach ( (array) $items as $it ) {
		$flat[] = array( 'id' => (int) $it->ID, 'parent' => (int) $it->menu_item_parent, 'label' => $it->title, 'url' => kx_url( $it->url ) );
	}
	return array( 'name' => $menu ? $menu->name : '', 'items' => $flat );
}

$out = array(
	'exportedAt' => gmdate( 'c' ),
	'source'     => $home,
	'site'       => array(
		'name'    => get_bloginfo( 'name' ),
		'tagline' => get_bloginfo( 'description' ),
		'logo'    => get_theme_mod( 'custom_logo' ) ? kx_url( wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) ) : '',
	),
	'settings'   => wp_parse_args( (array) get_option( 'kh_settings', array() ), kh_option_defaults() ),
	'bacs'       => array(
		'settings' => (array) get_option( 'woocommerce_bacs_settings', array() ),
		'accounts' => (array) get_option( 'woocommerce_bacs_accounts', array() ),
	),
	'menus'      => array(
		'primary'  => kx_menu( 'primary' ),
		'footer_1' => kx_menu( 'footer_1' ),
		'footer_2' => kx_menu( 'footer_2' ),
	),
	'frontPage'  => (int) get_option( 'page_on_front' ),
);

// Danh mục khóa học.
$out['categories'] = array();
foreach ( get_terms( array( 'taxonomy' => 'danh_muc_khoa_hoc', 'hide_empty' => false ) ) as $t ) {
	$out['categories'][] = array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'description' => $t->description );
}

// Khóa học.
$out['courses'] = array();
foreach ( get_posts( array( 'post_type' => 'khoa_hoc', 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'posts_per_page' => -1 ) ) as $p ) {
	$meta = array();
	foreach ( array_keys( kh_course_fields() ) as $k ) {
		$meta[ $k ] = (string) get_post_meta( $p->ID, '_kh_' . $k, true );
	}
	foreach ( array( 'video', 'register_url', 'detail_url' ) as $k ) {
		$meta[ $k ] = kx_url( $meta[ $k ] );
	}
	$out['courses'][] = array(
		'id'         => $p->ID,
		'slug'       => $p->post_name,
		'title'      => $p->post_title,
		'excerpt'    => $p->post_excerpt,
		'status'     => $p->post_status,
		'order'      => (int) $p->menu_order,
		'date'       => $p->post_date_gmt,
		'blocks'     => kx_blocks( $p->post_content ),
		'meta'       => $meta,
		'cover'      => kx_url( get_post_meta( $p->ID, '_kh_cover', true ) ),
		'thumbnail'  => has_post_thumbnail( $p ) ? kx_url( get_the_post_thumbnail_url( $p, 'full' ) ) : '',
		'categories' => wp_get_post_terms( $p->ID, 'danh_muc_khoa_hoc', array( 'fields' => 'ids' ) ),
	);
}

// Trang + bài viết.
foreach ( array( 'page' => 'pages', 'post' => 'posts' ) as $type => $key ) {
	$out[ $key ] = array();
	foreach ( get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1 ) ) as $p ) {
		$out[ $key ][] = array(
			'id'        => $p->ID,
			'slug'      => $p->post_name,
			'title'     => $p->post_title,
			'excerpt'   => $p->post_excerpt,
			'status'    => $p->post_status,
			'date'      => $p->post_date_gmt,
			'parent'    => (int) $p->post_parent,
			'blocks'    => kx_blocks( $p->post_content ),
			'html'      => kx_html( $p->post_content ),
			'thumbnail' => has_post_thumbnail( $p ) ? kx_url( get_the_post_thumbnail_url( $p, 'full' ) ) : '',
		);
	}
}

// Thành viên (mật khẩu đã mã hóa: bản Node đọc được nên học viên dùng mật khẩu cũ).
$out['users'] = array();
foreach ( get_users( array( 'number' => -1 ) ) as $u ) {
	$um = function ( $k ) use ( $u ) {
		$v = get_user_meta( $u->ID, $k, true );
		return '' === $v ? null : $v;
	};
	$out['users'][] = array(
		'id'          => $u->ID,
		'login'       => $u->user_login,
		'email'       => $u->user_email,
		'displayName' => $u->display_name,
		'firstName'   => $u->first_name,
		'lastName'    => $u->last_name,
		'phone'       => (string) get_user_meta( $u->ID, 'billing_phone', true ),
		'passHash'    => $u->user_pass,
		'roles'       => array_values( $u->roles ),
		'registered'  => $u->user_registered,
		'courses'     => $um( '_kh_courses' ),
		'since'       => $um( '_kh_course_since' ),
		'progress'    => $um( '_kh_progress' ),
		'lessonOpen'  => $um( '_kh_lesson_open' ),
		'views'       => $um( '_kh_views' ),
		'trialViews'  => $um( '_kh_trial_views' ),
	);
}

// Đơn hàng.
$out['orders'] = array();
if ( function_exists( 'wc_get_orders' ) ) {
	foreach ( wc_get_orders( array( 'limit' => -1, 'type' => 'shop_order', 'orderby' => 'id', 'order' => 'ASC' ) ) as $o ) {
		$items = array();
		foreach ( $o->get_items() as $it ) {
			$items[] = array( 'name' => $it->get_name(), 'courses' => kh_wc_product_courses( $it->get_product_id() ), 'total' => (float) $it->get_total() );
		}
		$notes = array();
		foreach ( wc_get_order_notes( array( 'order_id' => $o->get_id() ) ) as $n ) {
			$notes[] = array( 'content' => $n->content, 'by' => $n->added_by, 'date' => $n->date_created ? $n->date_created->date( 'c' ) : null );
		}
		$out['orders'][] = array(
			'id'            => $o->get_id(),
			'number'        => $o->get_order_number(),
			'status'        => $o->get_status(),
			'customerId'    => $o->get_customer_id(),
			'total'         => (float) $o->get_total(),
			'discount'      => (float) $o->get_discount_total(),
			'paymentMethod' => $o->get_payment_method(),
			'paymentTitle'  => $o->get_payment_method_title(),
			'firstName'     => $o->get_billing_first_name(),
			'lastName'      => $o->get_billing_last_name(),
			'email'         => $o->get_billing_email(),
			'phone'         => $o->get_billing_phone(),
			'note'          => $o->get_customer_note(),
			'createdAt'     => $o->get_date_created() ? $o->get_date_created()->date( 'c' ) : null,
			'paidAt'        => $o->get_date_paid() ? $o->get_date_paid()->date( 'c' ) : null,
			'items'         => $items,
			'notes'         => array_reverse( $notes ),
		);
	}
}

// Liên hệ.
$out['contacts'] = array();
foreach ( get_posts( array( 'post_type' => 'kh_lien_he', 'post_status' => array( 'pending', 'private', 'publish' ), 'posts_per_page' => -1 ) ) as $c ) {
	$out['contacts'][] = array(
		'name'    => $c->post_title,
		'phone'   => (string) get_post_meta( $c->ID, '_kh_phone', true ),
		'email'   => (string) get_post_meta( $c->ID, '_kh_email', true ),
		'topic'   => (string) get_post_meta( $c->ID, '_kh_topic', true ),
		'message' => (string) get_post_meta( $c->ID, '_kh_message', true ),
		'source'  => kx_url( get_post_meta( $c->ID, '_kh_source', true ) ),
		'status'  => 'pending' === $c->post_status ? 'new' : 'done',
		'date'    => $c->post_date_gmt,
	);
}

echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
