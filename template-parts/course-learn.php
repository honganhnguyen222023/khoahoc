<?php
/**
 * Trang học một bài: /khoa-hoc/<khoa>/?bai=N
 *
 * Trạng thái (kh_lesson_state):
 *  ok         – học viên đã mua (còn hạn) / quản trị: phát video, theo dõi % đã xem
 *  trial      – học thử (còn lượt)
 *  sequence   – phải học xong bài trước
 *  trial_used – hết lượt học thử
 *  views_used – đã xem bài này đủ số lần cho phép
 *  expired    – khóa hết hạn, mua lại học từ đầu
 *  buy        – chưa mua
 * Link video chỉ in ra khi xem được.
 *
 * @package KhoaHoc
 */

$course_id = get_the_ID();
$user_id   = get_current_user_id();
$c         = kh_course( $course_id );
$lessons   = kh_course_lessons( $course_id );
$n         = kh_learn_index();
$lesson    = $lessons[ $n ];
$state     = kh_lesson_state( $course_id, $lesson );
$can_view  = in_array( $state, array( 'ok', 'trial' ), true );
$owned     = $c['owned'];
$keys      = array_keys( $lessons );
$pos       = array_search( $n, $keys, true );
$prev      = $pos > 0 ? $lessons[ $keys[ $pos - 1 ] ] : null;
$next      = $pos < count( $keys ) - 1 ? $lessons[ $keys[ $pos + 1 ] ] : null;
$progress  = $user_id ? kh_progress( $user_id, $course_id ) : array();
$is_done   = ! empty( $progress[ $n ] );
$with_src  = array_filter( wp_list_pluck( $lessons, 'src' ) );
$done_n    = count( array_intersect_key( $progress, $with_src ) );
$free_n    = count( array_filter( wp_list_pluck( $lessons, 'free' ) ) );
$track     = in_array( $state, array( 'ok', 'trial' ), true ) && $lesson['src'] && $user_id ? kh_video_track_type( $lesson['src'], $lesson['type'] ) : '';
$by_play   = $track && kh_view_trackable( $lesson ) && ! kh_is_staff();
$next_wait = $next && 'ok' === $state && ! $is_done && kh_sequential() && ! kh_is_staff() && $lesson['src'];
$price_txt = $c['price_html'] ? ' · ' . $c['price_html'] : '';
$views_max = kh_views_limit();
$views_rec = 'ok' === $state && $user_id && ! kh_is_staff() && kh_views_counted( $lesson ) ? kh_views_record( $user_id, $course_id, $n ) : null;
// Số lượt còn lại cho trình duyệt (-1 = không giới hạn).
$views_left = -1;
if ( $by_play && 'trial' === $state && kh_trial_limit() ) {
	$views_left = kh_trial_left( $user_id, $course_id, $n );
} elseif ( $by_play && $views_rec ) {
	$views_left = max( 0, $views_max - $views_rec['count'] );
}
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
				<?php if ( 'trial' === $state ) : ?>
					<?php $left = kh_trial_left( $user_id, $course_id, $n ); ?>
					<p class="kh-learn-flag"><?php echo kh_icon( 'info', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php
						if ( $left < 0 ) {
							echo esc_html( 'Bạn đang học thử bài này.' );
						} elseif ( $by_play ) {
							echo esc_html( 1 === $left ? 'Bạn đang học thử. Đây là lượt xem thử cuối cùng của bài này.' : sprintf( 'Bạn đang học thử. Còn %d lượt xem thử (xem hết video tính 1 lượt).', $left ) );
						} elseif ( $left > 0 ) {
							echo esc_html( sprintf( 'Bạn đang học thử. Sau lượt này còn %d lượt xem thử.', $left ) );
						} else {
							echo esc_html( 'Đây là lượt xem thử cuối cùng của bạn. Tải lại trang trong 30 phút vẫn xem được.' );
						}
						?>
					</p>
				<?php elseif ( 'ok' === $state && ! kh_is_staff() && ( $views_rec || ( $c['access'] && $c['access']['expires'] ) ) ) : ?>
					<p class="kh-learn-flag is-soft"><?php echo kh_icon( $views_rec ? 'eye' : 'calendar', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php
						$bits = array();
						if ( $views_rec && $by_play ) {
							$bits[] = 1 === $views_left
								? 'Đây là lượt xem cuối cùng của bài này: xem hết video thì bài sẽ khóa.'
								: sprintf( 'Bạn còn %1$d/%2$d lượt xem bài này (xem hết video tính 1 lượt).', $views_left, $views_max );
						} elseif ( $views_rec ) {
							$bits[] = $views_rec['count'] >= $views_max
								? sprintf( 'Lượt xem cuối cùng (%1$d/%2$d) của bài này. Tải lại trang trong 30 phút vẫn xem được.', $views_rec['count'], $views_max )
								: sprintf( 'Lượt xem %1$d/%2$d của bài này.', $views_rec['count'], $views_max );
						}
						if ( $c['access'] && $c['access']['expires'] ) {
							$bits[] = 'Hạn học đến ' . date_i18n( 'd/m/Y', $c['access']['expires'] ) . '.';
						}
						echo esc_html( implode( ' ', $bits ) );
						?>
					</p>
				<?php endif; ?>

				<div class="kh-learn-player<?php echo $can_view ? '' : ' is-locked'; ?>"
					<?php if ( $track ) : ?>
						data-track="<?php echo esc_attr( $track ); ?>"
						data-course="<?php echo (int) $course_id; ?>"
						data-lesson="<?php echo (int) $n; ?>"
						data-need="<?php echo esc_attr( kh_watch_need() ); ?>"
						data-done="<?php echo $is_done ? '1' : '0'; ?>"
						data-min="<?php echo (int) kh_lesson_min_seconds( $lesson ); ?>"
						data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'kh_learn_' . $course_id ) ); ?>"
						data-mode="<?php echo esc_attr( $state ); ?>"
						data-views-left="<?php echo (int) $views_left; ?>"
						data-course-url="<?php echo esc_url( get_permalink( $course_id ) ); ?>"
					<?php endif; ?>>
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
					<?php elseif ( 'sequence' === $state ) : ?>
						<?php $go = kh_first_open_lesson( $course_id, $user_id ); ?>
						<div class="kh-learn-lock">
							<span class="kh-learn-lock-icon"><?php echo kh_icon( 'lock', '', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<p class="kh-learn-lock-title">Học xong bài trước để mở bài này</p>
							<p>Khóa học đi theo lộ trình: xem hết bài <?php echo (int) $go; ?> thì bài tiếp theo tự mở.</p>
							<div class="kh-learn-lock-actions">
								<a class="kh-btn kh-btn-primary" href="<?php echo esc_url( kh_learn_url( $course_id, $go ) ); ?>"><?php echo kh_icon( 'play', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Học bài <?php echo (int) $go; ?></a>
							</div>
						</div>
					<?php elseif ( 'views_used' === $state ) : ?>
						<div class="kh-learn-lock">
							<span class="kh-learn-lock-icon"><?php echo kh_icon( 'lock', '', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<p class="kh-learn-lock-title">Bạn đã xem bài này <?php echo (int) $views_max; ?> lần</p>
							<p>Mỗi video bài học được xem tối đa <?php echo (int) $views_max; ?> lần. Cần xem lại, bạn liên hệ để được mở thêm lượt xem.</p>
							<div class="kh-learn-lock-actions">
								<?php if ( $next && kh_can_view_lesson( $course_id, $next ) ) : ?>
									<a class="kh-btn kh-btn-primary" href="<?php echo esc_url( kh_learn_url( $course_id, $next['n'] ) ); ?>"><?php echo kh_icon( 'play', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Học bài tiếp theo</a>
								<?php endif; ?>
								<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( home_url( '/lien-he/' ) ); ?>">Liên hệ</a>
							</div>
						</div>
					<?php elseif ( 'trial_used' === $state ) : ?>
						<div class="kh-learn-lock">
							<span class="kh-learn-lock-icon"><?php echo kh_icon( 'lock', '', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<p class="kh-learn-lock-title">Bạn đã dùng hết <?php echo (int) kh_trial_limit(); ?> lượt xem thử</p>
							<p>Đăng ký khóa học để xem lại bài này và mở toàn bộ <?php echo esc_html( count( $lessons ) ); ?> bài.</p>
							<div class="kh-learn-lock-actions">
								<a class="kh-btn kh-btn-primary" <?php echo kh_link_attrs( $c['register'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>Đăng ký học<?php echo esc_html( $price_txt ); ?></a>
								<?php if ( $c['cart_url'] ) : ?>
									<a class="kh-btn kh-btn-outline" href="<?php echo esc_url( $c['cart_url'] ); ?>"><?php echo kh_icon( 'shopping-cart', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Thêm vào giỏ</a>
								<?php endif; ?>
							</div>
						</div>
					<?php elseif ( 'expired' === $state ) : ?>
						<div class="kh-learn-lock">
							<span class="kh-learn-lock-icon"><?php echo kh_icon( 'calendar', '', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<p class="kh-learn-lock-title">Khóa học đã hết hạn<?php echo $c['access'] && $c['access']['expires'] ? esc_html( ' ngày ' . date_i18n( 'd/m/Y', $c['access']['expires'] ) ) : ''; ?></p>
							<p>Mua lại để học lại từ đầu, thời hạn <?php echo esc_html( mb_strtolower( $c['duration_txt'] ) ); ?>.</p>
							<div class="kh-learn-lock-actions">
								<a class="kh-btn kh-btn-primary" <?php echo kh_link_attrs( $c['register'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>Mua lại<?php echo esc_html( $price_txt ); ?></a>
							</div>
						</div>
					<?php else : ?>
						<div class="kh-learn-lock">
							<span class="kh-learn-lock-icon"><?php echo kh_icon( 'lock', '', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<p class="kh-learn-lock-title">Bài học này dành cho học viên của khóa</p>
							<p>Đăng ký khóa học để mở toàn bộ <?php echo esc_html( count( $lessons ) ); ?> bài, thời hạn học <?php echo esc_html( mb_strtolower( $c['duration_txt'] ) ); ?>.</p>
							<div class="kh-learn-lock-actions">
								<a class="kh-btn kh-btn-primary" <?php echo kh_link_attrs( $c['register'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>Đăng ký học<?php echo esc_html( $price_txt ); ?></a>
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

				<?php if ( $track && 'ok' === $state ) : ?>
					<div class="kh-learn-status<?php echo $is_done ? ' is-done' : ''; ?>" data-status aria-live="polite">
						<?php if ( $is_done ) : ?>
							<?php echo kh_icon( 'check', '', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span>Bạn đã học xong bài này.</span>
						<?php elseif ( 'manual' === $track ) : ?>
							<span>Học xong bài này rồi bấm nút bên cạnh để mở bài tiếp theo.</span>
							<button type="button" class="kh-btn kh-btn-primary kh-btn-sm" data-done-btn disabled>Đã học xong</button>
						<?php else : ?>
							<span data-status-text><?php echo esc_html( sprintf( 'Xem hết video để mở bài tiếp theo (cần xem %d%%).', round( kh_watch_need() * 100 ) ) ); ?></span>
							<span class="kh-learn-bar"><span data-status-bar style="width:0%"></span></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="kh-learn-info">
					<?php if ( $lesson['chapter'] ) : ?>
						<p class="kh-learn-chapter"><?php echo esc_html( $lesson['chapter'] ); ?></p>
					<?php endif; ?>
					<h1 class="kh-learn-title"><?php echo esc_html( sprintf( 'Bài %d: ', $n ) ); ?><?php echo kh_rich( $lesson['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
					<?php if ( $lesson['duration'] ) : ?>
						<p class="kh-learn-time"><?php echo kh_icon( 'clock', '', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $lesson['duration'] ); ?></p>
					<?php endif; ?>
					<?php // Bài có giới hạn lượt xem: không đưa link YouTube gốc (xem ngoài thì không đếm được). ?>
					<?php if ( $can_view && 'doc' !== $lesson['type'] && kh_youtube_id( $lesson['src'] ) && $views_left < 0 && ! $views_rec && ! ( 'trial' === $state && kh_trial_limit() ) ) : ?>
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
						<a class="kh-btn kh-btn-primary kh-btn-sm<?php echo $next_wait ? ' is-disabled' : ''; ?>" href="<?php echo esc_url( kh_learn_url( $course_id, $next['n'] ) ); ?>" rel="next"<?php echo $next_wait ? ' data-next-wait aria-disabled="true" title="Học xong bài này để mở bài tiếp theo"' : ''; ?>><?php echo $next_wait ? kh_icon( 'lock', '', 14 ) . ' ' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>Bài tiếp theo <?php echo kh_icon( 'chevron-right', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					<?php endif; ?>
				</nav>

				<?php if ( 'trial' === $state ) : ?>
					<div class="kh-learn-upsell">
						<div>
							<p class="kh-learn-upsell-title">Bạn đang học thử</p>
							<p>Thích cách dạy này? Đăng ký để mở <?php echo esc_html( count( $lessons ) - $free_n ); ?> bài còn lại.</p>
						</div>
						<a class="kh-btn kh-btn-primary" <?php echo kh_link_attrs( $c['register'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>Đăng ký học<?php echo esc_html( $price_txt ); ?></a>
					</div>
				<?php endif; ?>
			</div>

			<aside class="kh-learn-side" aria-label="Danh sách bài học">
				<div class="kh-learn-side-head">
					<a class="kh-learn-course" href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a>
					<?php if ( $owned ) : ?>
						<p><?php echo esc_html( sprintf( 'Đã học xong %1$d/%2$d bài', $done_n, count( $with_src ) ) ); ?></p>
						<span class="kh-learn-bar"><span style="width:<?php echo esc_attr( $with_src ? round( $done_n / count( $with_src ) * 100 ) : 0 ); ?>%"></span></span>
					<?php else : ?>
						<p><?php echo esc_html( sprintf( '%d bài · %d bài học thử', count( $lessons ), $free_n ) ); ?></p>
					<?php endif; ?>
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
						$row = kh_lesson_row_parts( $course_id, $l );
						?>
						<li class="kh-lesson <?php echo esc_attr( $row['class'] . ( $l['n'] === $n ? ' is-current' : '' ) ); ?>">
							<?php echo kh_icon( $row['icon'], 'kh-lesson-icon', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span class="kh-lesson-title">
								<a href="<?php echo esc_url( $row['href'] ); ?>"<?php echo $l['n'] === $n ? ' aria-current="page"' : ''; ?><?php echo $row['title'] ? ' title="' . esc_attr( $row['title'] ) . '"' : ''; ?>><?php echo kh_rich( $l['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
								<?php echo $row['badge']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
