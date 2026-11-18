<?php
/**
 * Contact / consultation form: saves to "Liên hệ" and emails the owner.
 * Anti-spam: nonce, honeypot field, minimum fill time, 1 submit / 30s per IP.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render form block.
 *
 * @param array $a Attributes.
 * @return string
 */
function kh_render_contact_form( $a ) {
	$title  = kh_a( $a, 'title', 'Để lại thông tin, chúng tôi gọi lại trong 24 giờ' );
	$button = kh_a( $a, 'buttonText', 'Gửi thông tin' );
	$topics = array_filter( array_map( 'trim', explode( ',', (string) kh_a( $a, 'topics', '' ) ) ) );
	$status = isset( $_GET['kh_form'] ) ? sanitize_key( $_GET['kh_form'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	$notice = '';
	if ( 'sent' === $status ) {
		$notice = '<div class="kh-alert kh-alert-ok" role="status">' . kh_icon( 'check-circle-2', '', 18 ) . '<span>Đã nhận thông tin của bạn. Chúng tôi sẽ liên hệ sớm.</span></div>';
	} elseif ( 'invalid' === $status ) {
		$notice = '<div class="kh-alert kh-alert-err" role="alert">' . kh_icon( 'alert-circle', '', 18 ) . '<span>Vui lòng nhập họ tên và số điện thoại hợp lệ (9–11 số).</span></div>';
	} elseif ( 'wait' === $status ) {
		$notice = '<div class="kh-alert kh-alert-err" role="alert">' . kh_icon( 'alert-circle', '', 18 ) . '<span>Bạn vừa gửi xong. Vui lòng đợi 30 giây rồi gửi lại.</span></div>';
	}

	ob_start();
	?>
	<div class="kh-form-card" id="kh-form">
		<h3 class="kh-card-title"><?php echo kh_rich( $title ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h3>
		<?php if ( kh_a( $a, 'text' ) ) : ?>
			<p class="kh-card-text"><?php echo kh_rich( $a['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
		<?php endif; ?>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<form class="kh-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="kh_contact">
			<input type="hidden" name="kh_ts" value="<?php echo esc_attr( time() ); ?>">
			<input type="hidden" name="kh_back" value="<?php echo esc_url( get_permalink() ? get_permalink() : home_url( '/' ) ); ?>">
			<?php wp_nonce_field( 'kh_contact', 'kh_nonce' ); ?>
			<div class="kh-hp" aria-hidden="true"><label>Website <input type="text" name="kh_website" tabindex="-1" autocomplete="off"></label></div>
			<div class="kh-form-grid">
				<label class="kh-field"><span>Họ và tên *</span><input type="text" name="kh_name" required maxlength="80" autocomplete="name"></label>
				<label class="kh-field"><span>Số điện thoại *</span><input type="tel" name="kh_phone" required inputmode="tel" pattern="[0-9 +.\-]{9,15}" autocomplete="tel"></label>
				<label class="kh-field"><span>Email</span><input type="email" name="kh_email" maxlength="120" autocomplete="email"></label>
				<?php if ( $topics ) : ?>
					<label class="kh-field"><span>Bạn quan tâm</span>
						<select name="kh_topic">
							<?php foreach ( $topics as $t ) : ?>
								<option><?php echo esc_html( $t ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<label class="kh-field kh-field-full"><span>Lời nhắn</span><textarea name="kh_message" rows="3" maxlength="2000"></textarea></label>
			</div>
			<button type="submit" class="kh-btn kh-btn-primary"><?php echo esc_html( $button ); ?> <?php echo kh_icon( 'send', '', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<p class="kh-form-note">Thông tin chỉ dùng để tư vấn khóa học, không chia sẻ cho bên thứ ba.</p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

add_action( 'admin_post_nopriv_kh_contact', 'kh_handle_contact' );
add_action( 'admin_post_kh_contact', 'kh_handle_contact' );

/**
 * Handle submit.
 */
function kh_handle_contact() {
	$back = isset( $_POST['kh_back'] ) ? esc_url_raw( wp_unslash( $_POST['kh_back'] ) ) : home_url( '/' );
	$back = wp_validate_redirect( $back, home_url( '/' ) );
	$go   = function ( $status ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'kh_form', $status, $back ) . '#kh-form' );
		exit;
	};

	if ( ! isset( $_POST['kh_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kh_nonce'] ), 'kh_contact' ) ) {
		$go( 'invalid' );
	}
	// Bots: honeypot filled or submitted faster than 3 seconds → pretend success.
	$ts = isset( $_POST['kh_ts'] ) ? absint( $_POST['kh_ts'] ) : 0;
	if ( ! empty( $_POST['kh_website'] ) || ( time() - $ts ) < 3 ) {
		$go( 'sent' );
	}

	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'kh_rl_' . md5( $ip );
	if ( get_transient( $key ) ) {
		$go( 'wait' );
	}

	$name    = isset( $_POST['kh_name'] ) ? sanitize_text_field( wp_unslash( $_POST['kh_name'] ) ) : '';
	$phone   = isset( $_POST['kh_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['kh_phone'] ) ) : '';
	$email   = isset( $_POST['kh_email'] ) ? sanitize_email( wp_unslash( $_POST['kh_email'] ) ) : '';
	$topic   = isset( $_POST['kh_topic'] ) ? sanitize_text_field( wp_unslash( $_POST['kh_topic'] ) ) : '';
	$message = isset( $_POST['kh_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['kh_message'] ) ) : '';
	$digits  = kh_digits( $phone );

	if ( '' === $name || strlen( $digits ) < 9 || strlen( $digits ) > 11 ) {
		$go( 'invalid' );
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'kh_lien_he',
			'post_status' => 'pending',
			'post_title'  => mb_substr( $name, 0, 80 ),
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_kh_phone', $phone );
		update_post_meta( $id, '_kh_email', $email );
		update_post_meta( $id, '_kh_topic', $topic );
		update_post_meta( $id, '_kh_message', $message );
		update_post_meta( $id, '_kh_source', $back );
	}
	set_transient( $key, 1, 30 );

	$to = kh_opt( 'notify_email' ) ? kh_opt( 'notify_email' ) : get_option( 'admin_email' );
	wp_mail(
		$to,
		sprintf( '[%s] Liên hệ mới: %s – %s', kh_opt( 'brand_name' ), $name, $phone ),
		"Họ tên: $name\nĐiện thoại: $phone\nEmail: $email\nQuan tâm: $topic\n\nLời nhắn:\n$message\n\nGửi từ: $back\nXem trong quản trị: " . admin_url( 'edit.php?post_type=kh_lien_he' ),
		$email ? array( 'Reply-To: ' . $name . ' <' . $email . '>' ) : array()
	);

	$go( 'sent' );
}
