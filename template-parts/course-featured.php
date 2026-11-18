<?php
/**
 * Big course card (featured courses on the home page).
 *
 * @package KhoaHoc
 */

$c = kh_course();
?>
<article class="kh-fcourse">
	<a class="kh-fcourse-media" href="<?php echo esc_url( $c['detail'] ); ?>" tabindex="-1" aria-hidden="true">
		<?php
		if ( has_post_thumbnail() ) {
			the_post_thumbnail( 'kh-card', array( 'sizes' => '(min-width: 1024px) 510px, 92vw', 'alt' => '' ) );
		} elseif ( $c['cover'] ) {
			echo '<img src="' . esc_url( $c['cover'] ) . '" alt="" width="1280" height="720" loading="lazy" decoding="async">';
		} else {
			echo '<span class="kh-fcourse-ph">' . kh_icon( $c['icon'], '', 40 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
	</a>
	<div class="kh-fcourse-body">
		<?php if ( $c['badge'] ) : ?>
			<span class="kh-badge"><?php echo esc_html( $c['badge'] ); ?></span>
		<?php endif; ?>
		<h3 class="kh-fcourse-title"><a href="<?php echo esc_url( $c['detail'] ); ?>"><?php the_title(); ?></a></h3>
		<?php if ( $c['audience'] ) : ?>
			<p class="kh-fcourse-aud"><?php echo esc_html( $c['audience'] ); ?></p>
		<?php endif; ?>
		<?php if ( $c['benefit_list'] ) : ?>
			<ul class="kh-checks">
				<?php foreach ( $c['benefit_list'] as $line ) : ?>
					<li><?php echo kh_icon( 'check', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo kh_benefit( $line ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<div class="kh-fcourse-foot">
			<div class="kh-price-block">
				<?php if ( $c['regular_html'] ) : ?>
					<s class="kh-price-old">Mua lẻ <?php echo esc_html( $c['regular_html'] ); ?></s>
				<?php endif; ?>
				<p class="kh-price"><?php echo esc_html( $c['price_html'] ? $c['price_html'] : 'Liên hệ' ); ?>
					<?php if ( $c['save_html'] ) : ?>
						<span class="kh-save"><?php echo esc_html( $c['save_html'] ); ?></span>
					<?php endif; ?>
				</p>
			</div>
			<div class="kh-fcourse-actions">
				<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( $c['detail'] ); ?>">Xem chi tiết</a>
				<a class="kh-btn kh-btn-primary" <?php echo kh_link_attrs( $c['register'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>Đăng ký</a>
			</div>
		</div>
	</div>
</article>
