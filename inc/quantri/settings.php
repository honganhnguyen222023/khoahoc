<?php
/**
 * Quản trị → Cài đặt: cùng dữ liệu với "Cài đặt website" (option kh_settings)
 * + tài khoản ngân hàng nhận chuyển khoản (WooCommerce BACS).
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Trang Cài đặt.
 *
 * @return array
 */
function kh_qt_page_settings() {
	$groups = kh_option_fields();
	$tabs   = array();
	if ( function_exists( 'WC' ) ) {
		$tabs['chuyen-khoan'] = 'Nhận chuyển khoản';
	}
	foreach ( $groups as $key => $g ) {
		$tabs[ $key ] = wp_strip_all_tags( $g['label'] );
	}
	$active = kh_qt_get( 'tab' );
	$active = isset( $tabs[ $active ] ) ? $active : (string) key( $tabs );

	$form  = '<form method="post" class="qt-form qt-settings" data-qt-dirty>' . kh_qt_form_fields( 'settings_save' );
	$form .= '<input type="hidden" name="tab" value="' . esc_attr( $active ) . '" data-qt-tab-input>';
	$form .= '<nav class="qt-tabs is-scroll" data-qt-tabs>';
	foreach ( $tabs as $k => $label ) {
		$form .= '<a href="' . esc_url( kh_qt_url( 'cai-dat', '', array( 'tab' => $k ) ) ) . '" data-tab="' . esc_attr( $k ) . '" class="' . ( $k === $active ? 'is-active' : '' ) . '">' . esc_html( $label ) . '</a>';
	}
	$form .= '</nav>';

	// Nhận chuyển khoản.
	if ( isset( $tabs['chuyen-khoan'] ) ) {
		$bacs     = (array) get_option( 'woocommerce_bacs_settings', array() );
		$accounts = (array) get_option( 'woocommerce_bacs_accounts', array() );
		$body     = kh_qt_field( 'bacs_enabled', 'Cho học viên thanh toán bằng chuyển khoản ngân hàng', ! isset( $bacs['enabled'] ) || 'yes' === $bacs['enabled'], array( 'type' => 'checkbox' ) );
		$body    .= '<p class="qt-help">Thông tin này hiện cho học viên ở trang cảm ơn sau khi đặt mua và trong email đơn hàng.</p>';
		for ( $i = 0; $i < 3; $i++ ) {
			$a     = isset( $accounts[ $i ] ) ? (array) $accounts[ $i ] : array();
			$body .= '<fieldset class="qt-fieldset"><legend>Tài khoản ' . ( $i + 1 ) . ( $i ? ' (không bắt buộc)' : '' ) . '</legend><div class="qt-form-grid is-3">';
			$body .= kh_qt_field( 'bacs[' . $i . '][bank_name]', 'Ngân hàng', isset( $a['bank_name'] ) ? $a['bank_name'] : '', array( 'placeholder' => 'VD: Vietcombank' ) );
			$body .= kh_qt_field( 'bacs[' . $i . '][account_number]', 'Số tài khoản', isset( $a['account_number'] ) ? $a['account_number'] : '', array( 'attrs' => 'inputmode="numeric"' ) );
			$body .= kh_qt_field( 'bacs[' . $i . '][account_name]', 'Chủ tài khoản', isset( $a['account_name'] ) ? $a['account_name'] : '', array( 'placeholder' => 'VD: NGUYEN VAN A' ) );
			$body .= '</div></fieldset>';
		}
		$body .= kh_qt_field( 'bacs_instructions', 'Lời nhắn sau khi đặt mua', isset( $bacs['instructions'] ) ? $bacs['instructions'] : '', array( 'type' => 'textarea', 'placeholder' => 'VD: Chuyển khoản với nội dung là mã đơn hàng. Khóa học được mở ngay khi chúng tôi nhận được học phí.' ) );
		$body .= '<p class="qt-help">Cổng thanh toán khác (VNPay, MoMo…) cài trong Quản trị nâng cao → WooCommerce → Cài đặt → Thanh toán.</p>';
		$form .= '<div class="qt-tab-panel" data-panel="chuyen-khoan"' . ( 'chuyen-khoan' === $active ? '' : ' hidden' ) . '>' . kh_qt_card( 'Tài khoản nhận chuyển khoản', $body ) . '</div>';
	}

	// Các nhóm cài đặt của theme.
	$opts = wp_parse_args( (array) get_option( 'kh_settings', array() ), kh_option_defaults() );
	foreach ( $groups as $key => $g ) {
		$body = '';
		foreach ( $g['fields'] as $fk => $f ) {
			$type = isset( $f['type'] ) ? $f['type'] : 'text';
			$o    = array(
				// Link có thể là đường dẫn ngắn (VD: /tai-khoan/) nên không dùng type="url".
				'type'    => 'url' === $type ? 'text' : $type,
				'help'    => isset( $f['help'] ) ? $f['help'] : '',
				'options' => isset( $f['options'] ) ? $f['options'] : array(),
			);
			if ( 'image' === $type ) {
				$o['store'] = 'url';
			}
			if ( 'url' === $type ) {
				$o['attrs'] = 'inputmode="url" placeholder="https://… hoặc /duong-dan/"';
			}
			if ( 'number' === $type ) {
				$o['attrs'] = 'min="' . ( isset( $f['min'] ) ? (int) $f['min'] : 0 ) . '"' . ( isset( $f['max'] ) ? ' max="' . (int) $f['max'] . '"' : '' );
			}
			if ( 'checkbox' === $type ) {
				$body .= '<input type="hidden" name="kh[' . esc_attr( $fk ) . ']" value="0">';
			}
			$body .= kh_qt_field( 'kh[' . $fk . ']', $f['label'], isset( $opts[ $fk ] ) ? ( 'checkbox' === $type ? '1' === (string) $opts[ $fk ] : $opts[ $fk ] ) : '', $o );
		}
		if ( 'brand' === $key ) {
			$body .= '<p class="qt-help">Ảnh logo: Quản trị nâng cao → Giao diện → Tùy biến → Nhận dạng site.</p>';
		}
		$form .= '<div class="qt-tab-panel" data-panel="' . esc_attr( $key ) . '"' . ( $key === $active ? '' : ' hidden' ) . '>' . kh_qt_card( wp_strip_all_tags( $g['label'] ), $body ) . '</div>';
	}

	$form .= '<div class="qt-savebar"><span class="qt-muted" data-qt-dirty-note>Chưa có thay đổi</span><button class="qt-btn" type="submit">' . kh_qt_icon( 'save', 18 ) . ' Lưu cài đặt</button></div>';
	$form .= '</form>';

	return array(
		'title' => 'Cài đặt',
		'html'  => '<div class="qt-narrow">' . $form . '</div>',
		'media' => true,
	);
}

/**
 * Lưu cài đặt.
 *
 * @param array $p Dữ liệu.
 * @return array
 */
function kh_qt_do_settings_save( $p ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array( 'ok' => false, 'msg' => 'Bạn không có quyền đổi cài đặt.' );
	}
	$tab = isset( $p['tab'] ) ? sanitize_key( $p['tab'] ) : '';
	$go  = kh_qt_url( 'cai-dat', '', $tab ? array( 'tab' => $tab ) : array() );

	if ( isset( $p['kh'] ) && is_array( $p['kh'] ) ) {
		// kh_sanitize_settings tự wp_unslash: trả lại dấu gạch chéo như form gốc gửi lên.
		update_option( 'kh_settings', kh_sanitize_settings( wp_slash( $p['kh'] ) ) );
	}

	if ( function_exists( 'WC' ) && isset( $p['bacs'] ) && is_array( $p['bacs'] ) ) {
		$rows = array();
		foreach ( $p['bacs'] as $a ) {
			$row = array(
				'account_name'   => sanitize_text_field( isset( $a['account_name'] ) ? $a['account_name'] : '' ),
				'account_number' => sanitize_text_field( isset( $a['account_number'] ) ? $a['account_number'] : '' ),
				'bank_name'      => sanitize_text_field( isset( $a['bank_name'] ) ? $a['bank_name'] : '' ),
				'sort_code'      => '',
				'iban'           => '',
				'bic'            => '',
			);
			if ( $row['account_number'] || $row['bank_name'] ) {
				$rows[] = $row;
			}
		}
		update_option( 'woocommerce_bacs_accounts', $rows );
		$bacs                 = (array) get_option( 'woocommerce_bacs_settings', array() );
		$bacs['enabled']      = ! empty( $p['bacs_enabled'] ) ? 'yes' : 'no';
		$bacs['instructions'] = sanitize_textarea_field( isset( $p['bacs_instructions'] ) ? $p['bacs_instructions'] : '' );
		update_option( 'woocommerce_bacs_settings', $bacs );
	}

	return array( 'ok' => true, 'msg' => 'Đã lưu cài đặt.', 'go' => $go );
}
