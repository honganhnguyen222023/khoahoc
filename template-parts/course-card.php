<?php
/**
 * Small course card (grid).
 *
 * @package KhoaHoc
 */

$c = kh_course();
?>
<article class="kh-ccard">
	<div class="kh-ccard-top">
		<span class="kh-icon-box"><?php echo kh_icon( $c['icon'], '', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<?php if ( $c['badge'] ) : ?>
			<span class="kh-tag"><?php echo esc_html( $c['badge'] ); ?></span>
		<?php endif; ?>
	</div>
	<h3 class="kh-ccard-title"><a href="<?php echo esc_url( $c['detail'] ); ?>"><?php the_title(); ?></a></h3>
	<?php if ( $c['meta_line'] ) : ?>
		<p class="kh-ccard-meta"><?php echo esc_html( $c['meta_line'] ); ?></p>
	<?php endif; ?>
	<?php if ( has_excerpt() ) : ?>
		<p class="kh-ccard-text"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
	<div class="kh-ccard-foot">
		<strong class="kh-ccard-price"><?php echo esc_html( $c['price_html'] ? $c['price_html'] : 'Liên hệ' ); ?></strong>
		<a class="kh-link-arrow" href="<?php echo esc_url( $c['detail'] ); ?>" aria-label="<?php echo esc_attr( 'Chi tiết khóa ' . get_the_title() ); ?>">Chi tiết <?php echo kh_icon( 'arrow-right', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</div>
</article>
