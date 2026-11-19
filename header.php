<?php
/**
 * Header.
 *
 * @package KhoaHoc
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#ffffff">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="kh-skip" href="#main"><?php esc_html_e( 'Chuyển đến nội dung', 'khoahoc' ); ?></a>

<?php if ( kh_opt( 'announce_text' ) ) : ?>
	<div class="kh-announce">
		<?php if ( kh_opt( 'announce_url' ) ) : ?>
			<a <?php echo kh_link_attrs( kh_opt( 'announce_url' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( kh_opt( 'announce_text' ) ); ?> <?php echo kh_icon( 'arrow-right', '', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		<?php else : ?>
			<span><?php echo esc_html( kh_opt( 'announce_text' ) ); ?></span>
		<?php endif; ?>
	</div>
<?php endif; ?>

<header class="kh-header" data-header>
	<div class="kh-container kh-header-row">
		<a class="kh-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( kh_opt( 'brand_name' ) ); ?>">
			<?php
			$logo_id = get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
				echo wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'kh-logo-img', 'alt' => kh_opt( 'brand_name' ), 'loading' => 'eager' ) );
			} else {
				echo '<span class="kh-logo-mark">' . esc_html( kh_opt( 'brand_initial' ) ) . '</span><span class="kh-logo-text">' . esc_html( kh_opt( 'brand_name' ) ) . '</span>';
			}
			?>
		</a>

		<nav class="kh-nav" aria-label="<?php esc_attr_e( 'Menu chính', 'khoahoc' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'kh-menu',
					'depth'          => 2,
					'fallback_cb'    => 'kh_menu_fallback',
				)
			);
			?>
		</nav>

		<div class="kh-header-actions">
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<a class="kh-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Giỏ hàng', 'khoahoc' ); ?>">
					<?php echo kh_icon( 'shopping-cart', '', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="kh-cart-count"><?php echo (int) ( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
				</a>
			<?php endif; ?>
			<a class="kh-cart kh-account<?php echo is_user_logged_in() ? ' is-in' : ''; ?>" href="<?php echo esc_url( is_user_logged_in() ? kh_my_courses_url() : kh_account_url() ); ?>" aria-label="<?php echo esc_attr( is_user_logged_in() ? 'Khóa học của tôi' : 'Đăng nhập / Đăng ký' ); ?>" title="<?php echo esc_attr( is_user_logged_in() ? wp_get_current_user()->display_name : 'Đăng nhập / Đăng ký' ); ?>">
				<?php echo kh_icon( 'user', '', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
			<?php if ( kh_opt( 'header_cta_text' ) ) : ?>
				<a class="kh-btn kh-btn-primary kh-btn-sm kh-header-cta" <?php echo kh_link_attrs( kh_opt( 'header_cta_url' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( kh_opt( 'header_cta_text' ) ); ?></a>
			<?php endif; ?>
			<button class="kh-burger" type="button" aria-expanded="false" aria-controls="kh-drawer" data-menu-toggle>
				<span class="screen-reader-text"><?php esc_html_e( 'Mở menu', 'khoahoc' ); ?></span>
				<?php echo kh_icon( 'menu', 'kh-burger-open', 24 ) . kh_icon( 'x', 'kh-burger-close', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
		</div>
	</div>

	<div class="kh-drawer" id="kh-drawer" hidden>
		<nav aria-label="<?php esc_attr_e( 'Menu di động', 'khoahoc' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'kh-drawer-menu',
					'depth'          => 2,
					'fallback_cb'    => 'kh_menu_fallback',
				)
			);
			?>
		</nav>
		<div class="kh-drawer-actions">
			<?php if ( is_user_logged_in() ) : ?>
				<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( kh_my_courses_url() ); ?>"><?php esc_html_e( 'Khóa học của tôi', 'khoahoc' ); ?></a>
				<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Đăng xuất', 'khoahoc' ); ?></a>
			<?php else : ?>
				<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( kh_account_url() ); ?>"><?php esc_html_e( 'Đăng nhập / Đăng ký', 'khoahoc' ); ?></a>
			<?php endif; ?>
			<?php if ( kh_opt( 'header_cta_text' ) ) : ?>
				<a class="kh-btn kh-btn-primary" <?php echo kh_link_attrs( kh_opt( 'header_cta_url' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( kh_opt( 'header_cta_text' ) ); ?></a>
			<?php endif; ?>
			<?php if ( kh_zalo_url() ) : ?>
				<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( kh_zalo_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Nhắn Zalo tư vấn', 'khoahoc' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>

<main id="main" class="kh-main">
