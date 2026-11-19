<?php
/**
 * "Cài đặt website" admin page: one place for brand, contact, social, colours, SEO.
 * Non-coders change everything here; templates read it via kh_opt().
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field definitions grouped by tab.
 *
 * @return array
 */
function kh_option_fields() {
	return array(
		'brand'   => array(
			'label'  => __( 'Thương hiệu', 'khoahoc' ),
			'fields' => array(
				'brand_name'    => array( 'label' => 'Tên thương hiệu', 'default' => 'KhoaHoc Academy', 'help' => 'Hiện cạnh logo, chân trang và trong dữ liệu SEO.' ),
				'brand_tagline' => array( 'label' => 'Khẩu hiệu ngắn', 'default' => 'Tin học văn phòng & AI cho người đi làm' ),
				'brand_initial' => array( 'label' => 'Chữ trong logo (khi chưa có ảnh logo)', 'default' => 'K', 'help' => 'Muốn dùng ảnh logo: vào Giao diện → Tùy biến → Nhận dạng site → Logo.' ),
				'announce_text' => array( 'label' => 'Thông báo đầu trang', 'default' => '', 'help' => 'Để trống nếu không muốn hiện thanh thông báo.' ),
				'announce_url'  => array( 'label' => 'Link của thông báo', 'default' => '', 'type' => 'url' ),
			),
		),
		'contact' => array(
			'label'  => __( 'Liên hệ', 'khoahoc' ),
			'fields' => array(
				'phone'        => array( 'label' => 'Hotline', 'default' => '0900 000 000' ),
				'zalo'         => array( 'label' => 'Số Zalo', 'default' => '0900000000', 'help' => 'Dùng cho các nút "Nhắn Zalo". Để trống = dùng số Hotline.' ),
				'email'        => array( 'label' => 'Email', 'default' => 'hello@example.com', 'type' => 'email' ),
				'address'      => array( 'label' => 'Địa chỉ', 'default' => '' ),
				'notify_email' => array( 'label' => 'Email nhận thông báo form liên hệ', 'default' => '', 'type' => 'email', 'help' => 'Để trống = gửi về email quản trị.' ),
				'float_contact'=> array( 'label' => 'Hiện nút Zalo/Gọi nổi ở góc màn hình', 'default' => '1', 'type' => 'checkbox' ),
			),
		),
		'social'  => array(
			'label'  => __( 'Mạng xã hội', 'khoahoc' ),
			'fields' => array(
				'facebook' => array( 'label' => 'Facebook', 'default' => '', 'type' => 'url' ),
				'tiktok'   => array( 'label' => 'TikTok', 'default' => '', 'type' => 'url' ),
				'youtube'  => array( 'label' => 'YouTube', 'default' => '', 'type' => 'url' ),
			),
		),
		'layout'  => array(
			'label'  => __( 'Đầu trang & chân trang', 'khoahoc' ),
			'fields' => array(
				'header_cta_text' => array( 'label' => 'Chữ nút trên đầu trang', 'default' => 'Vào học' ),
				'header_cta_url'  => array( 'label' => 'Link nút trên đầu trang', 'default' => '/tai-khoan/', 'type' => 'url' ),
				'footer_about'    => array( 'label' => 'Giới thiệu ngắn ở chân trang', 'default' => 'Học tin học văn phòng và AI trên bài tập sát công việc thật. Học xong làm được ngay.', 'type' => 'textarea' ),
				'copyright'       => array( 'label' => 'Dòng bản quyền', 'default' => '© {year} KhoaHoc Academy. Mọi quyền được bảo lưu.', 'help' => '{year} tự đổi thành năm hiện tại.' ),
				'footer_note'     => array( 'label' => 'Dòng nhỏ cuối trang (bên phải)', 'default' => 'Học online · Học theo lộ trình · Hỗ trợ qua nhóm Zalo' ),
			),
		),
		'learning' => array(
			'label'  => __( 'Học viên & bài học', 'khoahoc' ),
			'fields' => array(
				'course_duration' => array(
					'label'   => 'Thời hạn học mặc định',
					'default' => '12',
					'type'    => 'select',
					'options' => array( 'life' => 'Trọn đời', '3' => '3 tháng', '6' => '6 tháng', '12' => '12 tháng (1 năm)', '24' => '24 tháng (2 năm)' ),
					'help'    => 'Tính từ ngày mở khóa. Hết hạn thì khóa học bị khóa lại; mua lại sẽ học từ đầu. Từng khóa có thể đặt riêng ở ô "Thời hạn học" khi sửa khóa học.',
				),
				'sequential'      => array( 'label' => 'Học xong bài trước mới mở bài sau', 'default' => '1', 'type' => 'checkbox', 'help' => 'Học viên phải xem xong bài 1 mới vào được bài 2, cứ thế đến hết khóa. Quản trị viên luôn xem được mọi bài.' ),
				'watch_percent'   => array( 'label' => 'Xem bao nhiêu % thì tính là học xong (%)', 'default' => '80', 'type' => 'number', 'min' => 10, 'max' => 100, 'help' => 'Mặc định 80: xem được 80% bài trước là bài sau tự mở. Theme đếm số giây thật sự đã xem (tua qua không tính). Video Google Drive/Bunny và bài tài liệu dùng nút "Đã học xong".' ),
				'trial_views'     => array( 'label' => 'Số lượt xem thử mỗi bài (tài khoản chưa mua)', 'default' => '2', 'type' => 'number', 'min' => 0, 'max' => 50, 'help' => 'Hết lượt thì bài học thử bị khóa. Tải lại trang trong 30 phút vẫn tính là 1 lượt. Nhập 0 = không giới hạn.' ),
			),
		),
		'colors'  => array(
			'label'  => __( 'Màu sắc', 'khoahoc' ),
			'fields' => array(
				'color_primary' => array( 'label' => 'Màu chính (nút, điểm nhấn)', 'default' => '#157A4F', 'type' => 'color' ),
				'color_ink'     => array( 'label' => 'Màu tiêu đề', 'default' => '#10261C', 'type' => 'color' ),
				'color_deep'    => array( 'label' => 'Màu khối nền đậm (kêu gọi hành động)', 'default' => '#0E3B2B', 'type' => 'color' ),
			),
		),
		'seo'     => array(
			'label'  => __( 'SEO & đo lường', 'khoahoc' ),
			'fields' => array(
				'seo_description' => array( 'label' => 'Mô tả trang chủ (meta description)', 'default' => 'Khóa học Excel, Word và AI thực chiến cho người đi làm. Học theo lộ trình, có nhóm hỗ trợ.', 'type' => 'textarea', 'help' => 'Khoảng 140–160 ký tự. Nếu bạn dùng Yoast/Rank Math, theme tự nhường cho plugin.' ),
				'og_image'        => array( 'label' => 'Ảnh chia sẻ mặc định (Facebook, Zalo)', 'default' => '', 'type' => 'image', 'help' => 'Kích thước đẹp nhất 1200×630.' ),
				'ga4_id'          => array( 'label' => 'Google Analytics 4 (G-XXXX)', 'default' => '' ),
				'head_code'       => array( 'label' => 'Mã chèn vào <head> (Pixel, xác minh…)', 'default' => '', 'type' => 'code', 'help' => 'Chỉ dán mã từ nguồn tin cậy.' ),
			),
		),
	);
}

/**
 * Flat defaults.
 *
 * @return array
 */
function kh_option_defaults() {
	$out = array();
	foreach ( kh_option_fields() as $tab ) {
		foreach ( $tab['fields'] as $key => $field ) {
			$out[ $key ] = $field['default'];
		}
	}
	return $out;
}

/**
 * Read one setting.
 *
 * @param string $key Key.
 * @return string
 */
function kh_opt( $key ) {
	static $opts = null;
	if ( null === $opts ) {
		$opts = wp_parse_args( (array) get_option( 'kh_settings', array() ), kh_option_defaults() );
	}
	$val = isset( $opts[ $key ] ) ? $opts[ $key ] : '';
	if ( 'copyright' === $key ) {
		$val = str_replace( '{year}', gmdate( 'Y' ), $val );
	}
	return $val;
}

add_action(
	'admin_menu',
	function () {
		add_menu_page(
			__( 'Cài đặt website', 'khoahoc' ),
			__( 'Cài đặt website', 'khoahoc' ),
			'manage_options',
			'kh-settings',
			'kh_render_settings_page',
			'dashicons-admin-customizer',
			3
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'kh_settings_group',
			'kh_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'kh_sanitize_settings',
			)
		);
	}
);

/**
 * Sanitize everything by field type.
 *
 * @param array $input Raw.
 * @return array
 */
function kh_sanitize_settings( $input ) {
	$input = (array) $input;
	$old   = (array) get_option( 'kh_settings', array() );
	$out   = $old;
	foreach ( kh_option_fields() as $tab ) {
		foreach ( $tab['fields'] as $key => $field ) {
			$type = isset( $field['type'] ) ? $field['type'] : 'text';
			if ( 'checkbox' === $type ) {
				$out[ $key ] = empty( $input[ $key ] ) ? '0' : '1';
				continue;
			}
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$val = wp_unslash( $input[ $key ] );
			switch ( $type ) {
				case 'url':
				case 'image':
					$out[ $key ] = esc_url_raw( trim( $val ) );
					break;
				case 'email':
					$out[ $key ] = sanitize_email( $val );
					break;
				case 'color':
					$out[ $key ] = sanitize_hex_color( $val ) ? sanitize_hex_color( $val ) : $field['default'];
					break;
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( $val );
					break;
				case 'select':
					$out[ $key ] = array_key_exists( (string) $val, $field['options'] ) ? (string) $val : $field['default'];
					break;
				case 'number':
					$num         = (int) $val;
					$out[ $key ] = (string) max( isset( $field['min'] ) ? $field['min'] : 0, min( isset( $field['max'] ) ? $field['max'] : PHP_INT_MAX, $num ) );
					break;
				case 'code':
					$out[ $key ] = current_user_can( 'unfiltered_html' ) ? trim( $val ) : wp_kses_post( $val );
					break;
				default:
					$out[ $key ] = sanitize_text_field( $val );
			}
		}
	}
	return $out;
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'toplevel_page_kh-settings' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".kh-color").wpColorPicker();});' );	}
);

/**
 * Render the settings page.
 */
function kh_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs  = kh_option_fields();
	$first = true;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cài đặt website', 'khoahoc' ); ?></h1>
		<p><?php esc_html_e( 'Sửa thông tin ở đây sẽ tự cập nhật trên toàn bộ website (đầu trang, chân trang, nút Zalo, SEO…).', 'khoahoc' ); ?></p>
		<?php if ( isset( $_GET['kh_demo'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Đã nhập dữ liệu mẫu.', 'khoahoc' ); ?></p></div>
		<?php endif; ?>
		<?php kh_demo_student_notice(); ?>
		<h2 class="nav-tab-wrapper kh-tabs">
			<?php foreach ( $tabs as $id => $tab ) : ?>
				<a href="#kh-tab-<?php echo esc_attr( $id ); ?>" class="nav-tab <?php echo $first ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab['label'] ); ?></a>
				<?php $first = false; ?>
			<?php endforeach; ?>
			<a href="#kh-tab-tools" class="nav-tab"><?php esc_html_e( 'Dữ liệu mẫu', 'khoahoc' ); ?></a>
		</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'kh_settings_group' ); ?>
			<?php $first = true; ?>
			<?php foreach ( $tabs as $id => $tab ) : ?>
				<div class="kh-tab" id="kh-tab-<?php echo esc_attr( $id ); ?>" style="<?php echo $first ? '' : 'display:none'; ?>">
					<table class="form-table" role="presentation">
						<?php foreach ( $tab['fields'] as $key => $field ) : ?>
							<?php
							$type = isset( $field['type'] ) ? $field['type'] : 'text';
							$name = 'kh_settings[' . $key . ']';
							$val  = kh_opt_raw( $key );
							$fid  = 'kh-' . $key;
							?>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
								<td>
									<?php if ( 'textarea' === $type || 'code' === $type ) : ?>
										<textarea id="<?php echo esc_attr( $fid ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="<?php echo 'code' === $type ? 6 : 3; ?>" class="large-text <?php echo 'code' === $type ? 'code' : ''; ?>"><?php echo esc_textarea( $val ); ?></textarea>
									<?php elseif ( 'checkbox' === $type ) : ?>
										<label><input id="<?php echo esc_attr( $fid ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( '1', $val ); ?>> <?php esc_html_e( 'Bật', 'khoahoc' ); ?></label>
									<?php elseif ( 'select' === $type ) : ?>
										<select id="<?php echo esc_attr( $fid ); ?>" name="<?php echo esc_attr( $name ); ?>">
											<?php foreach ( $field['options'] as $ok => $ol ) : ?>
												<option value="<?php echo esc_attr( $ok ); ?>" <?php selected( (string) $ok, (string) $val ); ?>><?php echo esc_html( $ol ); ?></option>
											<?php endforeach; ?>
										</select>
									<?php elseif ( 'number' === $type ) : ?>
										<input id="<?php echo esc_attr( $fid ); ?>" type="number" class="small-text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $val ); ?>" min="<?php echo esc_attr( isset( $field['min'] ) ? $field['min'] : 0 ); ?>" max="<?php echo esc_attr( isset( $field['max'] ) ? $field['max'] : '' ); ?>">
									<?php elseif ( 'color' === $type ) : ?>
										<input id="<?php echo esc_attr( $fid ); ?>" type="text" class="kh-color" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $val ); ?>" data-default-color="<?php echo esc_attr( $field['default'] ); ?>">
									<?php elseif ( 'image' === $type ) : ?>
										<input id="<?php echo esc_attr( $fid ); ?>" type="url" class="regular-text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $val ); ?>">
										<button class="button kh-pick"><?php esc_html_e( 'Chọn ảnh', 'khoahoc' ); ?></button>
									<?php else : ?>
										<input id="<?php echo esc_attr( $fid ); ?>" type="<?php echo 'email' === $type ? 'email' : 'text'; ?>" class="regular-text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $val ); ?>">
									<?php endif; ?>
									<?php if ( ! empty( $field['help'] ) ) : ?>
										<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
				<?php $first = false; ?>
			<?php endforeach; ?>
			<div class="kh-tab" id="kh-tab-tools" style="display:none">
				<p><?php esc_html_e( 'Tạo lại trang chủ mẫu, trang Giới thiệu, Liên hệ, 6 khóa học mẫu và menu. Trang đã có cùng tên sẽ không bị ghi đè.', 'khoahoc' ); ?></p>
				<p><a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kh_import_demo' ), 'kh_import_demo' ) ); ?>"><?php esc_html_e( 'Nhập dữ liệu mẫu', 'khoahoc' ); ?></a></p>
				<?php kh_demo_student_box(); ?>
			</div>
			<?php submit_button( __( 'Lưu thay đổi', 'khoahoc' ) ); ?>
		</form>
	</div>
	<script>
	( function () {
		var links = document.querySelectorAll( '.kh-tabs a' );
		var tabs = document.querySelectorAll( '.kh-tab' );
		function show( id ) {
			links.forEach( function ( a ) { a.classList.toggle( 'nav-tab-active', a.getAttribute( 'href' ) === id ); } );
			tabs.forEach( function ( t ) { t.style.display = ( '#' + t.id ) === id ? '' : 'none'; } );
			try { localStorage.setItem( 'khTab', id ); } catch ( e ) {}
		}
		links.forEach( function ( a ) { a.addEventListener( 'click', function ( e ) { e.preventDefault(); show( a.getAttribute( 'href' ) ); } ); } );
		try { var saved = localStorage.getItem( 'khTab' ); if ( saved && document.querySelector( saved ) ) { show( saved ); } } catch ( e ) {}
		document.querySelectorAll( '.kh-pick' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ! window.wp || ! wp.media ) { return; }
				var input = btn.previousElementSibling;
				var frame = wp.media( { title: 'Chọn ảnh', multiple: false, library: { type: 'image' } } );
				frame.on( 'select', function () { input.value = frame.state().get( 'selection' ).first().toJSON().url; } );
				frame.open();
			} );
		} );
	} )();
	</script>
	<?php
}

/**
 * Raw (unformatted) setting value for the form.
 *
 * @param string $key Key.
 * @return string
 */
function kh_opt_raw( $key ) {
	$opts = wp_parse_args( (array) get_option( 'kh_settings', array() ), kh_option_defaults() );
	return isset( $opts[ $key ] ) ? $opts[ $key ] : '';
}
