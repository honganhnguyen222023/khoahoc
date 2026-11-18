<?php
/**
 * Single course.
 *
 * @package KhoaHoc
 */

get_header();
while ( have_posts() ) :
	the_post();
	$c     = kh_course();
	$yt    = kh_youtube_id( $c['video'] );
	$terms = get_the_terms( get_the_ID(), 'danh_muc_khoa_hoc' );
	?>
	<article <?php post_class( 'kh-course-page' ); ?>>
		<header class="kh-course-hero">
			<div class="kh-container">
				<?php kh_breadcrumb( array( array( 'Khóa học', get_post_type_archive_link( 'khoa_hoc' ) ), array( get_the_title(), '' ) ) ); ?>
				<div class="kh-course-layout">
					<div class="kh-course-main">
						<div class="kh-course-tags">
							<?php if ( $c['badge'] ) : ?>
								<span class="kh-badge"><?php echo esc_html( $c['badge'] ); ?></span>
							<?php endif; ?>
							<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
								<?php foreach ( $terms as $t ) : ?>
									<a class="kh-tag" href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?></a>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<h1 class="kh-h1 kh-h1-sm"><?php the_title(); ?></h1>
						<?php if ( has_excerpt() ) : ?>
							<p class="kh-lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php endif; ?>
						<?php if ( $c['meta_line'] ) : ?>
							<p class="kh-course-metaline"><?php echo kh_icon( 'clock', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $c['meta_line'] ); ?></p>
						<?php endif; ?>

						<div class="kh-course-video">
							<?php if ( $yt ) : ?>
								<button type="button" class="kh-yt" data-yt="<?php echo esc_attr( $yt ); ?>" aria-label="<?php esc_attr_e( 'Phát video giới thiệu', 'khoahoc' ); ?>">
									<img src="<?php echo esc_url( 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' ); ?>" alt="" width="480" height="360" fetchpriority="high">
									<span class="kh-yt-play"><?php echo kh_icon( 'play', '', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								</button>
							<?php elseif ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'large', array( 'fetchpriority' => 'high', 'loading' => 'eager', 'sizes' => '(min-width: 1024px) 680px, 92vw' ) ); ?>
							<?php elseif ( $c['cover'] ) : ?>
								<img src="<?php echo esc_url( $c['cover'] ); ?>" alt="" width="1280" height="720" fetchpriority="high">
							<?php else : ?>
								<span class="kh-fcourse-ph"><?php echo kh_icon( $c['icon'], '', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<?php endif; ?>
						</div>
					</div>

					<aside class="kh-buybox" aria-label="<?php esc_attr_e( 'Đăng ký khóa học', 'khoahoc' ); ?>">
						<div class="kh-buybox-card">
							<?php if ( $c['regular_html'] ) : ?>
								<s class="kh-price-old"><?php echo esc_html( $c['regular_html'] ); ?></s>
							<?php endif; ?>
							<p class="kh-price"><?php echo esc_html( $c['price_html'] ? $c['price_html'] : 'Liên hệ' ); ?>
								<?php if ( $c['save_html'] ) : ?>
									<span class="kh-save"><?php echo esc_html( $c['save_html'] ); ?></span>
								<?php endif; ?>
							</p>
							<a class="kh-btn kh-btn-primary kh-btn-block" <?php echo kh_link_attrs( $c['register'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Đăng ký học', 'khoahoc' ); ?></a>
							<?php if ( kh_zalo_url() ) : ?>
								<a class="kh-btn kh-btn-outline kh-btn-block" href="<?php echo esc_url( kh_zalo_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Hỏi thêm qua Zalo', 'khoahoc' ); ?></a>
							<?php endif; ?>
							<ul class="kh-checks kh-checks-sm">
								<?php
								$kh_lines = $c['benefit_list'] ? $c['benefit_list'] : array( 'Học trọn đời, xem lại không giới hạn', 'Nhóm Zalo hỗ trợ không thời hạn', 'Học trên điện thoại hoặc máy tính' );
								foreach ( $kh_lines as $line ) :
									?>
									<li><?php echo kh_icon( 'check', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo kh_benefit( $line ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></li>
								<?php endforeach; ?>
							</ul>
						</div>
					</aside>
				</div>
			</div>
		</header>

		<div class="kh-container">
			<div class="kh-course-layout kh-course-body">
				<div class="kh-prose kh-entry">
					<?php the_content(); ?>
				</div>
			</div>
		</div>

		<?php
		$related = new WP_Query(
			array(
				'post_type'      => 'khoa_hoc',
				'posts_per_page' => 4,
				'post__not_in'   => array( get_the_ID() ),
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		if ( $related->have_posts() ) :
			?>
			<section class="kh-section kh-bg-soft kh-align-center">
				<div class="kh-container"><div class="kh-section-box">
					<header class="kh-section-head"><h2 class="kh-h2"><?php esc_html_e( 'Khóa học khác', 'khoahoc' ); ?></h2></header>
					<div class="kh-grid kh-cols-4 kh-course-grid">
						<?php
						while ( $related->have_posts() ) :
							$related->the_post();
							get_template_part( 'template-parts/course', 'card' );
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</div></div>
			</section>
		<?php endif; ?>

		<div class="kh-mobile-buy">
			<div>
				<strong><?php echo esc_html( $c['price_html'] ? $c['price_html'] : 'Liên hệ' ); ?></strong>
				<?php if ( $c['regular_html'] ) : ?>
					<s><?php echo esc_html( $c['regular_html'] ); ?></s>
				<?php endif; ?>
			</div>
			<a class="kh-btn kh-btn-primary kh-btn-sm" <?php echo kh_link_attrs( $c['register'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php esc_html_e( 'Đăng ký học', 'khoahoc' ); ?></a>
		</div>
	</article>
	<?php
endwhile;
get_footer();
