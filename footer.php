<?php
/**
 * Footer.
 *
 * @package KhoaHoc
 */

$kh_socials = array(
	'facebook' => kh_opt( 'facebook' ),
	'tiktok'   => kh_opt( 'tiktok' ),
	'youtube'  => kh_opt( 'youtube' ),
	'zalo'     => kh_zalo_url(),
);
?>
</main>

<footer class="kh-footer">
	<div class="kh-container">
		<div class="kh-footer-grid">
			<div class="kh-footer-brand">
				<a class="kh-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php
					$logo_id = get_theme_mod( 'custom_logo' );
					if ( $logo_id ) {
						echo wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'kh-logo-img', 'alt' => kh_opt( 'brand_name' ), 'loading' => 'lazy' ) );
					} else {
						echo '<span class="kh-logo-mark">' . esc_html( kh_opt( 'brand_initial' ) ) . '</span>';
					}
					?>
					<span><strong class="kh-logo-text"><?php echo esc_html( kh_opt( 'brand_name' ) ); ?></strong><small><?php echo esc_html( kh_opt( 'brand_tagline' ) ); ?></small></span>
				</a>
				<?php if ( kh_opt( 'footer_about' ) ) : ?>
					<p><?php echo esc_html( kh_opt( 'footer_about' ) ); ?></p>
				<?php endif; ?>
				<div class="kh-socials">
					<?php foreach ( $kh_socials as $net => $url ) : ?>
						<?php if ( $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $net ) ); ?>"><?php echo kh_icon( $net, '', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>

			<?php foreach ( array( 'footer_1', 'footer_2' ) as $loc ) : ?>
				<?php if ( has_nav_menu( $loc ) ) : ?>
					<?php $locs = get_nav_menu_locations(); $menu = wp_get_nav_menu_object( $locs[ $loc ] ); ?>
					<nav class="kh-footer-col" aria-label="<?php echo esc_attr( $menu ? $menu->name : '' ); ?>">
						<h2 class="kh-footer-title"><?php echo esc_html( $menu ? preg_replace( '/^Chân trang\s*–\s*/u', '', $menu->name ) : '' ); ?></h2>
						<?php wp_nav_menu( array( 'theme_location' => $loc, 'container' => false, 'menu_class' => 'kh-footer-menu', 'depth' => 1 ) ); ?>
					</nav>
				<?php endif; ?>
			<?php endforeach; ?>

			<div class="kh-footer-col">
				<h2 class="kh-footer-title"><?php esc_html_e( 'Liên hệ', 'khoahoc' ); ?></h2>
				<ul class="kh-footer-contact">
					<?php if ( kh_opt( 'phone' ) ) : ?>
						<li><span class="kh-icon-box kh-icon-sm"><?php echo kh_icon( 'phone', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span><small><?php esc_html_e( 'Hotline / Zalo', 'khoahoc' ); ?></small><a href="tel:<?php echo esc_attr( kh_digits( kh_opt( 'phone' ) ) ); ?>"><?php echo esc_html( kh_opt( 'phone' ) ); ?></a></span></li>
					<?php endif; ?>
					<?php if ( kh_opt( 'email' ) ) : ?>
						<li><span class="kh-icon-box kh-icon-sm"><?php echo kh_icon( 'mail', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span><small>Email</small><a href="mailto:<?php echo esc_attr( kh_opt( 'email' ) ); ?>"><?php echo esc_html( kh_opt( 'email' ) ); ?></a></span></li>
					<?php endif; ?>
					<?php if ( kh_opt( 'address' ) ) : ?>
						<li><span class="kh-icon-box kh-icon-sm"><?php echo kh_icon( 'map-pin', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span><small><?php esc_html_e( 'Địa chỉ', 'khoahoc' ); ?></small><?php echo esc_html( kh_opt( 'address' ) ); ?></span></li>
					<?php endif; ?>
				</ul>
				<?php if ( kh_zalo_url() ) : ?>
					<a class="kh-btn kh-btn-primary kh-btn-sm" href="<?php echo esc_url( kh_zalo_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Nhắn Zalo tư vấn', 'khoahoc' ); ?> <?php echo kh_icon( 'arrow-right', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<?php endif; ?>
			</div>
		</div>

		<div class="kh-footer-bottom">
			<p><?php echo esc_html( kh_opt( 'copyright' ) ); ?></p>
			<p><?php echo esc_html( kh_opt( 'footer_note' ) ); ?></p>
		</div>
	</div>
</footer>

<?php if ( '1' === kh_opt( 'float_contact' ) && ( kh_zalo_url() || kh_opt( 'phone' ) ) ) : ?>
	<div class="kh-float" aria-label="<?php esc_attr_e( 'Liên hệ nhanh', 'khoahoc' ); ?>">
		<?php if ( kh_zalo_url() ) : ?>
			<a class="kh-float-zalo" href="<?php echo esc_url( kh_zalo_url() ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Nhắn Zalo', 'khoahoc' ); ?>"><span>Zalo</span></a>
		<?php endif; ?>
		<?php if ( kh_opt( 'phone' ) ) : ?>
			<a class="kh-float-call" href="tel:<?php echo esc_attr( kh_digits( kh_opt( 'phone' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Gọi hotline', 'khoahoc' ); ?>"><?php echo kh_icon( 'phone', '', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
