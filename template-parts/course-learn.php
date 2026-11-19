<?php
/**
 * Trang học một bài: /khoa-hoc/<khoa>/?bai=N
 *
 * Xem được (đã mua, hoặc bài học thử khi đã đăng nhập) → khung video.
 * Chưa mở → màn hình khóa + nút đăng ký. Link video chỉ in ra khi xem được.
 *
 * @package KhoaHoc
 */

$course_id = get_the_ID();
$c         = kh_course( $course_id );
$lessons   = kh_course_lessons( $course_id );
$n         = kh_learn_index();
$lesson    = $lessons[ $n ];
$can_view  = kh_can_view_lesson( $course_id, $lesson );
$owned     = $c['owned'];
$keys      = array_keys( $lessons );
$pos       = array_search( $n, $keys, true );
$prev      = $pos > 0 ? $lessons[ $keys[ $pos - 1 ] ] : null;
$next      = $pos < count( $keys ) - 1 ? $lessons[ $keys[ $pos + 1 ] ] : null;
$free_n    = count( array_filter( wp_list_pluck( $lessons, 'free' ) ) );
$open_n    = $owned ? count( $lessons ) : ( is_user_logged_in() ? $free_n : 0 );
?>
<div class="kh-learn">
	<div class="kh-container">
		<?php kh_breadcrumb( array( array( 'Khóa học', get_post_type_archive_link( 'khoa_hoc' ) ), array( get_the_title(), get_permalink() ), array( sprintf( 'Bài %d', $n ), '' ) ) ); ?>
		<?php
		if ( function_exists( 'wc_print_notices' ) && WC()->session ) {
			wc_print_notices();
		}
		?>

		<div class="kh-learn-layout">
			<div class="kh-learn-main">
				<div class="kh-learn-player<?php echo $can_view ? '' : ' is-locked'; ?>">
					<?php if ( $can_view && $lesson['src'] && 'doc' !== $lesson['type'] ) : ?>
						<?php echo kh_video_embed( $lesson['src'], $lesson['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php elseif ( $can_view && $lesson['src'] ) : ?>
						<div class="kh-learn-lock">
							<span class="kh-learn-lock-icon"><?php echo kh_icon( 'file-text', '', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<p class="kh-learn-lock-title">Tài liệu bài học</p>
							<a class="kh-btn kh-btn-primary" href="<?php echo esc_url( $lesson['src'] ); ?>" target="_blank" rel="noopener"><?php echo kh_icon( 'external-link', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Mở tài liệu</a>
						</div>
					<?php elseif ( $can_view ) : ?>
						<div class="kh-learn-lock">
							<span class="kh-learn-lock-icon"><?php echo kh_icon( 'clock', '', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<p class="kh-learn-lock-title">Bài học đang được cập nhật</p>
							<p>Video sẽ có trong thời gian sớm nhất. Bạn học tiếp bài khác nhé.</p>
						</div>
					<?php else : ?>
						<div class="kh-learn-lock">
							<span class="kh-learn-lock-icon"><?php echo kh_icon( 'lock', '', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<p class="kh-learn-lock-title">Bài học này dành cho học viên của khóa</p>
							<p>Đăng ký khóa học để mở toàn bộ <?php echo esc_html( count( $lessons ) ); ?> bài, học trọn đời.</p>
							<div class="kh-learn-lock-actions">
								<a class="kh-btn kh-btn-primary" <?php echo kh_link_attrs( $c['register'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>Đăng ký học<?php echo $c['price_html'] ? ' · ' . esc_html( $c['price_html'] ) : ''; ?></a>
								<?php if ( $c['cart_url'] ) : ?>
									<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( $c['cart_url'] ); ?>"><?php echo kh_icon( 'shopping-cart', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Thêm vào giỏ</a>
								<?php endif; ?>
							</div>
							<?php if ( ! is_user_logged_in() ) : ?>
								<p class="kh-learn-lock-login">Đã mua khóa này? <a href="<?php echo esc_url( kh_login_url( kh_learn_url( $course_id, $n ) ) ); ?>">Đăng nhập</a></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="kh-learn-info">
					<?php if ( $lesson['chapter'] ) : ?>
						<p class="kh-learn-chapter"><?php echo esc_html( $lesson['chapter'] ); ?></p>
					<?php endif; ?>
					<h1 class="kh-learn-title"><?php echo esc_html( sprintf( 'Bài %d: ', $n ) ); ?><?php echo kh_rich( $lesson['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
					<?php if ( $lesson['duration'] ) : ?>
						<p class="kh-learn-time"><?php echo kh_icon( 'clock', '', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $lesson['duration'] ); ?></p>
					<?php endif; ?>
					<?php if ( $can_view && 'doc' !== $lesson['type'] && kh_youtube_id( $lesson['src'] ) ) : ?>
						<p class="kh-learn-help">Video không chạy? <a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . kh_youtube_id( $lesson['src'] ) ); ?>" target="_blank" rel="noopener">Mở trên YouTube</a> (hoặc tắt trình chặn quảng cáo cho trang này).</p>
					<?php endif; ?>
				</div>

				<nav class="kh-learn-nav" aria-label="Chuyển bài">
					<?php if ( $prev ) : ?>
						<a class="kh-btn kh-btn-outline kh-btn-sm" href="<?php echo esc_url( kh_learn_url( $course_id, $prev['n'] ) ); ?>" rel="prev"><?php echo kh_icon( 'chevron-left', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Bài trước</a>
					<?php else : ?>
						<span></span>
					<?php endif; ?>
					<?php if ( $next ) : ?>
						<a class="kh-btn kh-btn-primary kh-btn-sm" href="<?php echo esc_url( kh_learn_url( $course_id, $next['n'] ) ); ?>" rel="next">Bài tiếp theo <?php echo kh_icon( 'chevron-right', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					<?php endif; ?>
				</nav>

				<?php if ( ! $owned && $can_view ) : ?>
					<div class="kh-learn-upsell">
						<div>
							<p class="kh-learn-upsell-title">Bạn đang học thử</p>
							<p>Thích cách dạy này? Đăng ký để mở <?php echo esc_html( count( $lessons ) - $free_n ); ?> bài còn lại.</p>
						</div>
						<a class="kh-btn kh-btn-primary" <?php echo kh_link_attrs( $c['register'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>Đăng ký học<?php echo $c['price_html'] ? ' · ' . esc_html( $c['price_html'] ) : ''; ?></a>
					</div>
				<?php endif; ?>
			</div>

			<aside class="kh-learn-side" aria-label="Danh sách bài học">
				<div class="kh-learn-side-head">
					<a class="kh-learn-course" href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a>
					<p><?php echo esc_html( sprintf( 'Đã mở %1$d/%2$d bài', $open_n, count( $lessons ) ) ); ?></p>
					<span class="kh-learn-bar"><span style="width:<?php echo esc_attr( $lessons ? round( $open_n / count( $lessons ) * 100 ) : 0 ); ?>%"></span></span>
				</div>
				<div class="kh-learn-list">
					<?php
					$chapter = null;
					foreach ( $lessons as $l ) :
						if ( $l['chapter'] !== $chapter ) :
							if ( null !== $chapter ) {
								echo '</ol>';
							}
							$chapter = $l['chapter'];
							echo $chapter ? '<p class="kh-learn-list-ch">' . esc_html( $chapter ) . '</p>' : '';
							echo '<ol class="kh-lessons">';
						endif;
						$ok    = kh_can_view_lesson( $course_id, $l );
						$icon  = $ok || ( $l['free'] && ! is_user_logged_in() ) ? ( 'doc' === $l['type'] ? 'file-text' : 'play' ) : 'lock';
						$class = 'kh-lesson ' . ( 'lock' === $icon ? 'is-locked' : 'is-open' ) . ( $l['n'] === $n ? ' is-current' : '' );
						?>
						<li class="<?php echo esc_attr( $class ); ?>">
							<?php echo kh_icon( $icon, 'kh-lesson-icon', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span class="kh-lesson-title">
								<a href="<?php echo esc_url( kh_learn_url( $course_id, $l['n'] ) ); ?>"<?php echo $l['n'] === $n ? ' aria-current="page"' : ''; ?>><?php echo kh_rich( $l['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
								<?php if ( $l['free'] && ! $owned ) : ?>
									<span class="kh-preview">Học thử</span>
								<?php endif; ?>
							</span>
							<span class="kh-lesson-time"><?php echo esc_html( $l['duration'] ); ?></span>
						</li>
						<?php
					endforeach;
					echo $lessons ? '</ol>' : '';
					?>
				</div>
			</aside>
		</div>
	</div>
</div>
