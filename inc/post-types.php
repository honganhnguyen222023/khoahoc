<?php
/**
 * Custom content types: Khóa học, Danh mục khóa học, Liên hệ (form submissions).
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'kh_register_post_types' );

/**
 * Register CPTs and taxonomy.
 */
function kh_register_post_types() {
	register_post_type(
		'khoa_hoc',
		array(
			'labels'       => array(
				'name'          => __( 'Khóa học', 'khoahoc' ),
				'singular_name' => __( 'Khóa học', 'khoahoc' ),
				'add_new'       => __( 'Thêm khóa học', 'khoahoc' ),
				'add_new_item'  => __( 'Thêm khóa học mới', 'khoahoc' ),
				'edit_item'     => __( 'Sửa khóa học', 'khoahoc' ),
				'all_items'     => __( 'Tất cả khóa học', 'khoahoc' ),
				'search_items'  => __( 'Tìm khóa học', 'khoahoc' ),
				'not_found'     => __( 'Chưa có khóa học nào', 'khoahoc' ),
			),
			'public'       => true,
			'has_archive'  => 'khoa-hoc',
			'rewrite'      => array( 'slug' => 'khoa-hoc', 'with_front' => false ),
			'menu_icon'    => 'dashicons-welcome-learn-more',
			'menu_position'=> 4,
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions' ),
			'template'     => array(
				array( 'core/heading', array( 'content' => 'Giới thiệu khóa học' ) ),
				array( 'core/paragraph', array( 'placeholder' => 'Viết giới thiệu khóa học…' ) ),
				array( 'core/heading', array( 'content' => 'Nội dung khóa học' ) ),
				array( 'kh/chapter', array( 'title' => 'Chương 1' ), array( array( 'kh/lesson', array( 'title' => 'Bài 1' ) ) ) ),
			),
		)
	);

	register_taxonomy(
		'danh_muc_khoa_hoc',
		'khoa_hoc',
		array(
			'labels'            => array(
				'name'          => __( 'Danh mục khóa học', 'khoahoc' ),
				'singular_name' => __( 'Danh mục', 'khoahoc' ),
				'add_new_item'  => __( 'Thêm danh mục', 'khoahoc' ),
			),
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'danh-muc-khoa-hoc', 'with_front' => false ),
		)
	);

	register_post_type(
		'kh_lien_he',
		array(
			'labels'          => array(
				'name'          => __( 'Liên hệ', 'khoahoc' ),
				'singular_name' => __( 'Liên hệ', 'khoahoc' ),
				'all_items'     => __( 'Tin nhắn liên hệ', 'khoahoc' ),
				'edit_item'     => __( 'Xem liên hệ', 'khoahoc' ),
				'not_found'     => __( 'Chưa có liên hệ nào', 'khoahoc' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'menu_icon'       => 'dashicons-email-alt',
			'menu_position'   => 26,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
}

// Flush permalinks once after the theme is switched so /khoa-hoc/ works immediately.
add_action(
	'after_switch_theme',
	function () {
		kh_register_post_types();
		flush_rewrite_rules();
	}
);

// ---- Admin list columns for contacts ---------------------------------------
add_filter(
	'manage_kh_lien_he_posts_columns',
	function ( $cols ) {
		return array(
			'cb'       => $cols['cb'],
			'title'    => __( 'Họ tên', 'khoahoc' ),
			'kh_phone' => __( 'Điện thoại', 'khoahoc' ),
			'kh_email' => __( 'Email', 'khoahoc' ),
			'kh_topic' => __( 'Quan tâm', 'khoahoc' ),
			'date'     => __( 'Ngày gửi', 'khoahoc' ),
		);
	}
);
add_action(
	'manage_kh_lien_he_posts_custom_column',
	function ( $col, $post_id ) {
		$map = array(
			'kh_phone' => '_kh_phone',
			'kh_email' => '_kh_email',
			'kh_topic' => '_kh_topic',
		);
		if ( isset( $map[ $col ] ) ) {
			echo esc_html( get_post_meta( $post_id, $map[ $col ], true ) );
		}
	},
	10,
	2
);

// Contact detail box.
add_action(
	'add_meta_boxes_kh_lien_he',
	function () {
		add_meta_box(
			'kh_contact_detail',
			__( 'Nội dung liên hệ', 'khoahoc' ),
			function ( $post ) {
				$rows = array(
					'Họ tên'     => $post->post_title,
					'Điện thoại' => get_post_meta( $post->ID, '_kh_phone', true ),
					'Email'      => get_post_meta( $post->ID, '_kh_email', true ),
					'Quan tâm'   => get_post_meta( $post->ID, '_kh_topic', true ),
					'Lời nhắn'   => get_post_meta( $post->ID, '_kh_message', true ),
					'Gửi từ'     => get_post_meta( $post->ID, '_kh_source', true ),
				);
				echo '<table class="widefat striped"><tbody>';
				foreach ( $rows as $k => $v ) {
					echo '<tr><th style="width:140px">' . esc_html( $k ) . '</th><td>' . nl2br( esc_html( $v ) ) . '</td></tr>';
				}
				echo '</tbody></table>';
				$phone = kh_digits( get_post_meta( $post->ID, '_kh_phone', true ) );
				if ( $phone ) {
					echo '<p><a class="button button-primary" href="tel:' . esc_attr( $phone ) . '">Gọi</a> <a class="button" target="_blank" rel="noopener" href="https://zalo.me/' . esc_attr( $phone ) . '">Nhắn Zalo</a></p>';
				}
			},
			'kh_lien_he',
			'normal',
			'high'
		);
	}
);

// Unread badge in the admin menu.
add_action(
	'admin_menu',
	function () {
		global $menu;
		$count = wp_count_posts( 'kh_lien_he' );
		$new   = isset( $count->pending ) ? (int) $count->pending : 0;
		if ( ! $new || ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $i => $item ) {
			if ( 'edit.php?post_type=kh_lien_he' === $item[2] ) {
				$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . $new . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	}
);

// Opening a pending contact marks it as read.
add_action(
	'load-post.php',
	function () {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $id && 'kh_lien_he' === get_post_type( $id ) && 'pending' === get_post_status( $id ) ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );
		}
	}
);

// Course lists: manual order first (Thuộc tính trang → Thứ tự), 12 per page.
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() && $q->is_main_query() && ( $q->is_post_type_archive( 'khoa_hoc' ) || $q->is_tax( 'danh_muc_khoa_hoc' ) ) ) {
			$q->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
			$q->set( 'posts_per_page', 12 );
		}
	}
);
