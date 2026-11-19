<?php
/**
 * Demo content: created once when the theme is activated (and on demand from
 * "Cài đặt website → Dữ liệu mẫu"). Existing pages with the same slug are kept.
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

// Public YouTube video used for the demo "Học thử" lessons. Replace with your own.
const KH_DEMO_TRIAL_VIDEO = 'https://www.youtube.com/watch?v=yUdrAymdbrA'; // ~3 phút 45 giây.
const KH_DEMO_VIDEO_TIME  = '03:45';

/**
 * Serialize one block.
 *
 * @param string $name  Block name.
 * @param array  $attrs Attributes.
 * @param string $inner Inner HTML / blocks.
 * @return string
 */
function kh_b( $name, $attrs = array(), $inner = null ) {
	$json = $attrs ? ' ' . serialize_block_attributes( $attrs ) : '';
	if ( null === $inner ) {
		return "<!-- wp:{$name}{$json} /-->\n";
	}
	$short = 0 === strpos( $name, 'core/' ) ? substr( $name, 5 ) : $name;
	return "<!-- wp:{$short}{$json} -->\n{$inner}\n<!-- /wp:{$short} -->\n";
}

/**
 * Core paragraph.
 *
 * @param string $html Text.
 * @return string
 */
function kh_p( $html ) {
	return kh_b( 'core/paragraph', array(), '<p>' . $html . '</p>' );
}

/**
 * Core buttons (first = filled, others = outline).
 *
 * @param array $buttons [ [text, url], ... ].
 * @return string
 */
function kh_buttons( $buttons ) {
	$inner = '';
	foreach ( $buttons as $i => $btn ) {
		$outline = $i > 0;
		$inner  .= kh_b(
			'core/button',
			$outline ? array( 'className' => 'is-style-outline' ) : array(),
			'<div class="wp-block-button' . ( $outline ? ' is-style-outline' : '' ) . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $btn[1] ) . '">' . esc_html( $btn[0] ) . '</a></div>'
		);
	}
	return kh_b( 'core/buttons', array(), '<div class="wp-block-buttons">' . $inner . '</div>' );
}

/**
 * Core list.
 *
 * @param array  $items Items.
 * @param string $class Class.
 * @return string
 */
function kh_list( $items, $class = '' ) {
	$inner = '';
	foreach ( $items as $it ) {
		$inner .= kh_b( 'core/list-item', array(), '<li>' . $it . '</li>' );
	}
	return kh_b( 'core/list', $class ? array( 'className' => $class ) : array(), '<ul class="wp-block-list' . ( $class ? ' ' . $class : '' ) . '">' . $inner . '</ul>' );
}

/**
 * Theme image URL.
 *
 * @param string $file File.
 * @return string
 */
function kh_img( $file ) {
	return KH_URI . '/assets/img/' . $file;
}

/**
 * Home page content.
 *
 * @return string
 */
function kh_demo_home() {
	$zalo = kh_zalo_url() ? kh_zalo_url() : '#lien-he';
	$o    = '';

	$o .= kh_b(
		'kh/hero',
		array(
			'badge'      => 'Mới',
			'badgeText'  => 'Khóa AI cho Excel đã mở đăng ký',
			'title'      => 'Làm chủ Excel &amp; AI,<br><strong>tan làm đúng giờ</strong>',
			'text'       => 'Học Excel, Word và AI trên bộ file mô phỏng công việc thật: báo cáo, công nợ, chấm công, tồn kho. Học hôm nay, dùng được ngay sáng mai.',
			'imageUrl'   => kh_img( 'hero-placeholder.svg' ),
			'imageAlt'   => 'Giảng viên KhoaHoc Academy',
			'formula'    => '=HOC_DUNG_CACH(Excel; AI)',
			'chip1Value' => '3.000+',
			'chip1Label' => 'học viên đã học',
			'chip1Icon'  => 'users',
			'chip2Value' => 'File thật',
			'chip2Label' => 'mô phỏng doanh nghiệp',
			'chip2Icon'  => 'file-spreadsheet',
		),
		kh_buttons( array( array( 'Xem các khóa học', '#khoa-hoc' ), array( 'Tư vấn qua Zalo', $zalo ) ) ) .
		kh_list( array( 'Học 12 tháng, xem lại không giới hạn', 'Học theo lộ trình, xong bài trước mở bài sau', 'Kích hoạt tự động sau khi thanh toán' ), 'kh-checklist' )
	);

	$stats = '';
	foreach ( array( array( '3.000+', 'Học viên đã đăng ký' ), array( '100.000+', 'Người theo dõi Facebook' ), array( '50.000+', 'Người theo dõi TikTok' ), array( '5+ năm', 'Đào tạo tin học văn phòng' ) ) as $s ) {
		$stats .= kh_b( 'kh/stat', array( 'value' => $s[0], 'label' => $s[1] ) );
	}
	$o .= kh_b( 'kh/stats', array(), $stats );

	$cards    = '';
	$problems = array(
		array( 'Báo cáo cuối tháng mất trọn một ngày', 'Mọi người đã về, bạn vẫn ngồi gom số liệu từ năm file khác nhau.' ),
		array( 'Copy – dán thủ công hàng nghìn dòng', 'Không biết hàm nào làm được việc này nên đành làm tay. Chậm và dễ sai.' ),
		array( 'Nghe VLOOKUP, Pivot mà ngại hỏi', 'Đồng nghiệp nói lướt qua là hiểu, còn bạn sợ hỏi lại bị đánh giá.' ),
		array( 'Xem video miễn phí, càng xem càng rối', 'Mỗi nơi dạy một kiểu, không có thứ tự, xem xong không biết bắt đầu từ đâu.' ),
		array( 'Học rồi mà không áp dụng được', 'File mẫu quá đơn giản. Mở file thật ở công ty lại không biết làm gì.' ),
		array( 'Sợ bị AI bỏ lại phía sau', 'Ai cũng nói về ChatGPT, nhưng bạn chưa biết dùng nó cho việc thật thế nào.' ),
	);
	foreach ( $problems as $i => $p ) {
		$cards .= kh_b( 'kh/card', array( 'variant' => 'problem', 'number' => sprintf( '%02d', $i + 1 ), 'title' => $p[0], 'text' => $p[1] ) );
	}
	$o .= kh_b(
		'kh/section',
		array(
			'eyebrow'  => 'Có thể bạn cũng vậy',
			'title'    => 'Excel đang lấy mất <strong>giờ tan làm</strong> của bạn?',
			'text'     => 'Đây là những điều học viên chia sẻ nhiều nhất trước khi bắt đầu học.',
			'footnote' => '<strong>Không phải vì bạn kém. Chỉ là chưa có ai chỉ đúng cách.</strong>',
		),
		kh_b( 'kh/grid', array( 'columns' => 3 ), $cards )
	);

	$about_stats = '';
	foreach ( array( array( '3.000+', 'học viên trả phí' ), array( '20.000+', 'người học lớp miễn phí' ), array( '5+ năm', 'giảng dạy' ) ) as $s ) {
		$about_stats .= kh_b( 'kh/stat', array( 'value' => $s[0], 'label' => $s[1] ) );
	}
	$o .= kh_b(
		'kh/about',
		array(
			'eyebrow'     => 'Về chúng tôi',
			'title'       => 'Dạy tin học bằng <strong>việc thật</strong>, không bằng lý thuyết',
			'quote'       => '“Excel không khó. Chỉ là chưa có ai chỉ đúng cách.”',
			'quoteAuthor' => 'Tên giảng viên · Sáng lập KhoaHoc Academy',
			'imageUrl'    => kh_img( 'about-placeholder.svg' ),
			'imageAlt'    => 'Người sáng lập KhoaHoc Academy',
			'anchor'      => 've-chung-toi',
		),
		kh_p( 'Chúng tôi bắt đầu bằng những video mẹo Excel ngắn, mỗi video giải đúng một việc văn phòng cụ thể. Dần dần, cộng đồng lớn lên thành <strong>hàng trăm nghìn người theo dõi</strong>.' ) .
		kh_p( 'Sau nhiều năm, chúng tôi đã đồng hành cùng <strong>hàng nghìn học viên</strong>: kế toán, nhân sự, sale, kho vận, chủ doanh nghiệp nhỏ. Điểm chung sau khóa học là họ làm nhanh hơn và hết ngại Excel.' ) .
		kh_p( 'KhoaHoc Academy gom cách dạy đó thành lộ trình rõ ràng: bài tập trên file thật, tình huống từ doanh nghiệp, tài liệu chỉn chu và cách dùng AI để làm nhanh hơn.' ) .
		kh_b( 'kh/stats', array( 'variant' => 'compact' ), $about_stats )
	);

	$steps = '';
	foreach ( array(
		array( 'Word chuyên nghiệp', 'Định dạng văn bản chuẩn, mục lục tự động, trộn thư. Soạn hợp đồng, báo cáo gọn gàng.', '40 bài · khoảng 5 giờ' ),
		array( 'Excel nền tảng', 'Từ thao tác cơ bản tới VLOOKUP, INDEX-MATCH, Pivot Table và dashboard động.', '120 bài · khoảng 16 giờ' ),
		array( 'Thủ thuật văn phòng', 'Mỗi bài vài phút, giải trọn một việc: đối chiếu danh sách, chấm công, tính thuế.', '35 bài · khoảng 3 giờ' ),
		array( 'AI cho Excel', 'Mô tả việc bằng tiếng Việt để ChatGPT, Claude viết công thức, làm tool, dựng file.', '55 video · khoảng 13 giờ' ),
	) as $i => $s ) {
		$steps .= kh_b( 'kh/card', array( 'variant' => 'step', 'number' => (string) ( $i + 1 ), 'title' => $s[0], 'text' => $s[1], 'meta' => $s[2] ) );
	}
	$o .= kh_b(
		'kh/section',
		array(
			'eyebrow' => 'Lộ trình học',
			'title'   => '4 bước để làm chủ <strong>tin học văn phòng</strong>',
			'text'    => 'Đi theo thứ tự hoặc bắt đầu ở bước bạn đang cần. Bài nào cũng gắn với một việc văn phòng cụ thể.',
		),
		kh_b( 'kh/grid', array( 'columns' => 4, 'className' => 'kh-steps' ), $steps )
	);

	$choices = '';
	foreach ( array(
		array( 'Muốn dùng AI nhưng Excel chưa vững', 'Combo 3 khóa · 1.790.000đ' ),
		array( 'Đã dùng Excel ổn, chỉ cần học AI', 'AI cho Excel · 1.490.000đ' ),
		array( 'Muốn giỏi Excel trước, chưa cần AI', 'Combo 2 khóa Excel · 690.000đ' ),
		array( 'Cần cả soạn văn bản lẫn Excel', 'Combo Word + Excel · 649.000đ' ),
	) as $ch ) {
		$choices .= kh_b( 'kh/card', array( 'variant' => 'choice', 'text' => $ch[0], 'title' => $ch[1], 'url' => '#khoa-hoc' ) );
	}
	$o .= kh_b(
		'kh/section',
		array(
			'eyebrow' => 'Khóa học',
			'title'   => 'Chọn khóa theo <strong>việc bạn cần</strong>',
			'text'    => 'Thanh toán một lần, học 12 tháng trên điện thoại hoặc máy tính. Học phí đã gồm VAT.',
			'bg'      => 'soft',
			'anchor'  => 'khoa-hoc',
		),
		kh_b( 'kh/courses', array( 'layout' => 'featured', 'count' => 2 ) ) .
		kh_b( 'kh/courses', array( 'layout' => 'grid', 'count' => 8, 'title' => 'Khóa lẻ và combo khác' ) ) .
		kh_b( 'kh/grid', array( 'columns' => 2, 'boxed' => true, 'title' => 'Chưa biết chọn khóa nào?' ), $choices )
	);

	$shots = '';
	foreach ( array(
		array( 'Phần mềm quản lý nhân sự', 'Bảng điều hành nhân sự, quỹ lương, hợp đồng sắp hết hạn, gom từ nhiều file.', 'showcase-1.svg' ),
		array( 'Đối chiếu công nợ, xuất biên bản', 'Tính tuổi nợ, cảnh báo quá hạn, xuất biên bản PDF cho từng khách.', 'showcase-2.svg' ),
		array( 'Quản lý xuất nhập tồn kho', 'Tổng quan tồn kho, mã sắp hết hàng, mặt hàng xuất nhiều trong tháng.', 'showcase-3.svg' ),
		array( 'File quản lý khách hàng (CRM)', 'Tìm kiếm, thêm, sửa, xóa khách hàng bằng nút bấm ngay trong Excel.', 'showcase-4.svg' ),
	) as $s ) {
		$shots .= kh_b( 'kh/card', array( 'variant' => 'image', 'title' => $s[0], 'text' => $s[1], 'imageUrl' => kh_img( $s[2] ), 'imageAlt' => $s[0] ) );
	}
	$o .= kh_b(
		'kh/section',
		array(
			'eyebrow' => 'Thực chiến',
			'title'   => 'Học xong, bạn <strong>tự làm được</strong> những file này',
			'text'    => 'Ảnh chụp từ bài học. Dữ liệu mô phỏng theo nghiệp vụ doanh nghiệp thật.',
		),
		kh_b( 'kh/grid', array( 'columns' => 2 ), $shots )
	);

	$ways = '';
	foreach ( array(
		array( 'briefcase', 'Thực chiến', 'Bài tập trên file doanh nghiệp, không phải file mẫu vài dòng. Học xong áp dụng được ngay.' ),
		array( 'search', 'Tình huống thật', 'Phân tích tình huống ở công ty thật để hiểu vì sao làm, không chỉ biết bấm nút nào.' ),
		array( 'trending-up', 'Dễ hiểu, có lộ trình', 'Chia nhỏ từng bước, ít thuật ngữ. Người mới bắt đầu vẫn theo kịp.' ),
		array( 'book-open', 'Tài liệu chỉn chu', 'Slide, file mẫu và bảng tra cứu trình bày gọn gàng, giữ lại dùng lâu dài.' ),
	) as $w ) {
		$ways .= kh_b( 'kh/card', array( 'variant' => 'feature', 'icon' => $w[0], 'title' => $w[1], 'text' => $w[2] ) );
	}
	$o .= kh_b(
		'kh/section',
		array(
			'eyebrow' => 'Cách chúng tôi dạy',
			'title'   => 'Khác biệt nằm ở <strong>cách giảng</strong>',
			'bg'      => 'soft',
		),
		kh_b( 'kh/grid', array( 'columns' => 4 ), $ways )
	);

	$imgs = array();
	for ( $i = 1; $i <= 6; $i++ ) {
		$imgs[] = array( 'id' => 0, 'url' => kh_img( 'feedback-' . $i . '.svg' ), 'alt' => 'Ảnh phản hồi mẫu ' . $i );
	}
	$o .= kh_b(
		'kh/section',
		array(
			'eyebrow' => 'Học viên nói gì',
			'title'   => 'Tin nhắn thật từ <strong>học viên</strong>',
			'text'    => 'Ảnh chụp giữ nguyên, chỉ che bớt tên và thông tin cá nhân.',
		),
		kh_b( 'kh/masonry', array( 'images' => $imgs ) )
	);

	$links = '';
	foreach ( array(
		array( 'facebook', 'Fanpage Facebook', 'Mẹo Excel & AI mỗi tuần', kh_opt( 'facebook' ) ? kh_opt( 'facebook' ) : 'https://facebook.com' ),
		array( 'tiktok', 'Kênh TikTok', 'Video 60 giây, giải một việc', kh_opt( 'tiktok' ) ? kh_opt( 'tiktok' ) : 'https://tiktok.com' ),
		array( 'users', 'Nhóm học miễn phí', 'Lớp Excel và AI miễn phí trên Zalo', $zalo ),
	) as $l ) {
		$links .= kh_b( 'kh/card', array( 'variant' => 'link', 'icon' => $l[0], 'title' => $l[1], 'text' => $l[2], 'url' => $l[3] ) );
	}
	$o .= kh_b(
		'kh/section',
		array(
			'eyebrow' => 'Học miễn phí trước',
			'title'   => 'Chưa sẵn sàng đăng ký? <strong>Bắt đầu từ đây</strong>',
			'text'    => 'Mẹo ngắn gọn mỗi tuần, cùng các nhóm học miễn phí trên Zalo.',
			'bg'      => 'soft',
		),
		kh_b( 'kh/grid', array( 'columns' => 3 ), $links )
	);

	$o .= kh_b(
		'kh/section',
		array(
			'eyebrow' => 'Câu hỏi thường gặp',
			'title'   => 'Bạn còn <strong>băn khoăn</strong> điều gì?',
			'className' => 'kh-narrow',
		),
		kh_demo_faq()
	);

	$o .= kh_b(
		'kh/cta',
		array(
			'title' => 'Bắt đầu từ một việc nhỏ bạn đang làm tay mỗi ngày',
			'text'  => 'Chọn khóa hợp với công việc, hoặc nhắn chúng tôi để được gợi ý lộ trình. Hàng nghìn học viên đã bắt đầu như vậy.',
		),
		kh_buttons( array( array( 'Xem các khóa học', '#khoa-hoc' ), array( 'Nhắn Zalo tư vấn', $zalo ) ) )
	);

	return $o;
}

/**
 * FAQ block.
 *
 * @return string
 */
function kh_demo_faq() {
	$items = '';
	foreach ( array(
		array( 'Chưa biết gì về Excel có học được không?', 'Được. Khóa Excel nền tảng bắt đầu từ con số 0, chỉ cần bạn biết dùng máy tính.' ),
		array( 'Không biết lập trình có học được AI cho Excel không?', 'Được. Khóa không dạy viết code. Bạn học cách mô tả công việc bằng tiếng Việt để AI làm, rồi kiểm tra kết quả.' ),
		array( 'Học theo hình thức nào? Có lịch cố định không?', 'Học qua video quay sẵn trên website, không có lịch cố định. Xem trên điện thoại được, nhưng nên thực hành trên máy tính có Excel.' ),
		array( 'Học được bao lâu? Có cập nhật bài mới không?', 'Thời hạn học 12 tháng kể từ ngày kích hoạt (xem ở trang từng khóa), xem lại không giới hạn trong thời gian đó. Hết hạn muốn học lại thì mua lại và học từ đầu. Bài mới thêm vào khóa bạn được học luôn, không mất thêm phí.' ),
		array( 'Gặp chỗ chưa hiểu thì hỏi ai?', 'Mỗi khóa có nhóm hỗ trợ học viên trên Zalo, hỗ trợ không thời hạn. Link nhóm nằm trong email kích hoạt khóa học.' ),
		array( 'Đăng ký xong bao lâu thì vào học được?', 'Bạn chuyển khoản ngay trên trang đăng ký. Hệ thống tự kích hoạt và gửi tài khoản về email trong vài phút.' ),
		array( 'Có xuất hóa đơn cho công ty không?', 'Có, cho cả cá nhân và doanh nghiệp. Mua cho nhiều người hoặc cho công ty, bạn nhắn Zalo để được báo giá.' ),
	) as $f ) {
		$items .= kh_b( 'kh/faq-item', array( 'question' => $f[0], 'answer' => $f[1] ) );
	}
	return kh_b( 'kh/faq', array(), $items );
}

/**
 * Sample courses.
 *
 * @return array
 */
function kh_demo_courses() {
	return array(
		array(
			'title'    => 'Combo 3 khóa: AI cho Excel + Excel nền tảng + Thủ thuật',
			'slug'     => 'combo-ai-excel',
			'excerpt'  => 'Trọn bộ cho người muốn dùng AI nhưng Excel chưa vững: học nền tảng trước, rồi để AI làm phần việc lặp lại.',
			'cat'      => 'Combo',
			'meta'     => array(
				'price'         => 1790000,
				'regular_price' => 2570000,
				'badge'         => 'Trọn bộ Excel + AI',
				'icon'          => 'sparkles',
				'meta_line'     => '3 khóa · 210+ bài · ~32 giờ',
				'audience'      => 'Dành cho bạn muốn dùng AI nhưng chưa vững Excel.',
				'benefits'      => "Nhờ AI viết công thức, viết code tự động hóa, **không cần biết lập trình**\nTự dựng file công nợ, tồn kho, CRM, quản lý nhân sự\nKèm trọn khóa Excel từ cơ bản tới nâng cao và Thủ thuật văn phòng",
				'featured'      => '1',
			),
			'thumb'    => 'course-combo-ai.svg',
			'order'    => 1,
		),
		array(
			'title'    => 'Combo 2 khóa: Excel nền tảng + Thủ thuật văn phòng',
			'slug'     => 'combo-excel',
			'excerpt'  => 'Giỏi Excel trước, chưa cần AI: đi từ con số 0 tới Pivot Table, dashboard và hàng chục mẹo xử lý việc hằng ngày.',
			'cat'      => 'Combo',
			'meta'     => array(
				'price'         => 690000,
				'regular_price' => 1080000,
				'badge'         => 'Combo Excel',
				'icon'          => 'table',
				'meta_line'     => '2 khóa · 155 bài · ~19 giờ',
				'audience'      => 'Dành cho bạn muốn giỏi Excel trước, chưa cần AI.',
				'benefits'      => "**155 bài**, đi từ con số 0 tới Pivot Table và dashboard\nHơn 25 bài về hàm: IF, SUMIFS, VLOOKUP, INDEX-MATCH…\nCó chứng chỉ hoàn thành, tài liệu và bài tập từng chương",
				'featured'      => '1',
			),
			'thumb'    => 'course-combo-excel.svg',
			'order'    => 2,
		),
		array(
			'title'   => 'AI cho Excel',
			'slug'    => 'ai-cho-excel',
			'excerpt' => 'Dùng ChatGPT và Claude làm việc với Excel: công thức, tool tự động, file hệ thống.',
			'cat'     => 'AI',
			'meta'    => array( 'price' => 1490000, 'badge' => 'Khóa mới', 'icon' => 'sparkles', 'meta_line' => '7 module · 55 video · ~13 giờ' ),
			'order'   => 3,
		),
		array(
			'title'   => 'Excel từ cơ bản tới nâng cao',
			'slug'    => 'excel-co-ban-nang-cao',
			'excerpt' => 'Chỉ cần biết dùng máy tính. Hàm, Pivot Table, biểu đồ, dashboard và tự động hóa với ChatGPT.',
			'cat'     => 'Excel',
			'meta'    => array( 'price' => 490000, 'badge' => 'Cơ bản → Nâng cao', 'icon' => 'table', 'meta_line' => '12 chương · 120 bài · ~16 giờ' ),
			'order'   => 4,
		),
		array(
			'title'   => 'Thủ thuật Excel văn phòng',
			'slug'    => 'thu-thuat-excel',
			'excerpt' => 'Mỗi bài 2–10 phút giải trọn một việc văn phòng thật. Cần bài nào mở bài đó.',
			'cat'     => 'Excel',
			'meta'    => array( 'price' => 590000, 'badge' => 'Đã biết cơ bản', 'icon' => 'zap', 'meta_line' => '35 bài · ~3 giờ' ),
			'order'   => 5,
		),
		array(
			'title'   => 'Word cơ bản tới nâng cao',
			'slug'    => 'word-co-ban-nang-cao',
			'excerpt' => 'Định dạng chuẩn, mục lục tự động, trộn thư. Hoặc chọn combo Word + Excel chỉ 649.000đ.',
			'cat'     => 'Word',
			'meta'    => array( 'price' => 390000, 'badge' => 'Word + Excel', 'icon' => 'file-text', 'meta_line' => '40 bài · ~5 giờ' ),
			'order'   => 6,
		),
	);
}

/**
 * Course body: intro + curriculum.
 *
 * @param array $c Course def.
 * @return string
 */
function kh_demo_course_body( $c ) {
	$o  = kh_b( 'core/heading', array(), '<h2 class="wp-block-heading">Giới thiệu khóa học</h2>' );
	$o .= kh_p( esc_html( $c['excerpt'] ) . ' Mỗi bài học gắn với một việc văn phòng cụ thể, có file bài tập để bạn làm theo.' );
	$o .= kh_b( 'core/heading', array( 'level' => 3 ), '<h3 class="wp-block-heading">Bạn sẽ làm được gì sau khóa học</h3>' );
	$o .= kh_list( array( 'Xử lý số liệu nhanh và chính xác hơn', 'Tự lập báo cáo, biểu đồ trình bày rõ ràng', 'Biết cách tự học tiếp những tính năng mới' ) );
	$o .= kh_b( 'core/heading', array(), '<h2 class="wp-block-heading">Nội dung khóa học</h2>' );
	$chapters = array(
		'Chương 1 – Làm quen và thao tác nhanh' => array( array( 'Tổng quan giao diện', '10:19', true ), array( 'Phím tắt dùng mỗi ngày', '06:26', false ), array( 'Định dạng bảng chuẩn', '07:41', false ) ),
		'Chương 2 – Hàm và xử lý dữ liệu'        => array( array( 'Cách viết hàm đúng ngay lần đầu', '15:33', false ), array( 'IF, SUMIFS, COUNTIFS', '14:34', false ), array( 'VLOOKUP và INDEX-MATCH', '11:55', false ) ),
		'Chương 3 – Báo cáo và dashboard'        => array( array( 'Pivot Table từ A tới Z', '18:18', false ), array( 'Biểu đồ trình bày báo cáo', '11:32', false ), array( 'Dashboard động với Slicer', '10:36', false ) ),
	);
	$first = true;
	foreach ( $chapters as $title => $lessons ) {
		$inner = '';
		foreach ( $lessons as $l ) {
			$inner .= kh_b( 'kh/lesson', array_filter( array( 'title' => $l[0], 'duration' => KH_DEMO_VIDEO_TIME, 'video' => KH_DEMO_TRIAL_VIDEO, 'free' => $l[2] ) ) );
		}
		$o    .= kh_b( 'kh/chapter', $first ? array( 'title' => $title, 'open' => true ) : array( 'title' => $title ), $inner );
		$first = false;
	}
	return $o;
}

/**
 * "Tài khoản" page (target of the header "Vào học" button).
 * With WooCommerce: its My Account screen. Without: a WordPress login form.
 *
 * @return string
 */
function kh_demo_account() {
	if ( class_exists( 'WooCommerce' ) ) {
		return kh_b( 'core/shortcode', array(), '[woocommerce_my_account]' );
	}
	return kh_p( 'Đăng nhập để vào học các khóa bạn đã đăng ký.' )
		. kh_b( 'core/loginout', array( 'displayLoginAsForm' => true, 'className' => 'kh-login' ) )
		. kh_p( 'Chưa có tài khoản? <a href="' . esc_url( home_url( '/lien-he/' ) ) . '">Liên hệ</a> để được cấp tài khoản sau khi đăng ký khóa học.' );
}

/**
 * Import everything (idempotent).
 */
function kh_import_demo() {
	kh_register_post_types();

	// Courses.
	$course_ids = array();
	foreach ( kh_demo_courses() as $c ) {
		$existing = get_page_by_path( $c['slug'], OBJECT, 'khoa_hoc' );
		if ( $existing ) {
			$course_ids[ $c['slug'] ] = $existing->ID;
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'khoa_hoc',
				'post_status'  => 'publish',
				'post_title'   => $c['title'],
				'post_name'    => $c['slug'],
				'post_excerpt' => $c['excerpt'],
				'post_content' => wp_slash( kh_demo_course_body( $c ) ),
				'menu_order'   => $c['order'],
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		foreach ( $c['meta'] as $k => $v ) {
			update_post_meta( $id, '_kh_' . $k, (string) $v );
		}
		if ( ! empty( $c['thumb'] ) ) {
			update_post_meta( $id, '_kh_cover', kh_img( $c['thumb'] ) );
		}
		if ( ! empty( $c['cat'] ) ) {
			wp_set_object_terms( $id, $c['cat'], 'danh_muc_khoa_hoc' );
		}
		$course_ids[ $c['slug'] ] = $id;
	}

	// Combo: mua combo được học các khóa bên trong.
	$bundles = array(
		'combo-ai-excel' => array( 'ai-cho-excel', 'excel-co-ban-nang-cao', 'thu-thuat-excel' ),
		'combo-excel'    => array( 'excel-co-ban-nang-cao', 'thu-thuat-excel' ),
	);
	foreach ( $bundles as $combo => $children ) {
		if ( ! empty( $course_ids[ $combo ] ) && ! get_post_meta( $course_ids[ $combo ], '_kh_bundle', true ) ) {
			$ids = array_filter( array_map( function ( $slug ) use ( $course_ids ) { return isset( $course_ids[ $slug ] ) ? $course_ids[ $slug ] : 0; }, $children ) );
			update_post_meta( $course_ids[ $combo ], '_kh_bundle', implode( ',', $ids ) );
		}
	}
	// Sản phẩm WooCommerce cho từng khóa (nếu đang bật WooCommerce).
	if ( function_exists( 'kh_wc_sync_product' ) ) {
		foreach ( $course_ids as $cid ) {
			kh_wc_sync_product( $cid );
		}
	}

	// Pages.
	$zalo  = kh_zalo_url() ? kh_zalo_url() : '#';
	$pages = array(
		'trang-chu'  => array( 'Trang chủ', kh_demo_home() ),
		'gioi-thieu' => array(
			'Giới thiệu',
			kh_b(
				'kh/about',
				array(
					'eyebrow'     => 'Về chúng tôi',
					'title'       => 'Giúp người đi làm <strong>làm nhanh hơn</strong> mỗi ngày',
					'quote'       => '“Học để dùng được ngay ngày mai.”',
					'quoteAuthor' => 'Đội ngũ KhoaHoc Academy',
					'imageUrl'    => kh_img( 'about-placeholder.svg' ),
					'bg'          => 'white',
				),
				kh_p( 'Viết câu chuyện của bạn ở đây: bạn là ai, vì sao bắt đầu dạy, học viên của bạn là ai và họ thay đổi thế nào sau khóa học.' ) . kh_p( 'Mẹo: bấm vào bất kỳ chữ nào để sửa trực tiếp. Bôi đậm chữ trong tiêu đề để tô nổi bật.' )
			) . kh_b(
				'kh/section',
				array( 'eyebrow' => 'Cách chúng tôi dạy', 'title' => 'Ba điều <strong>không đổi</strong>', 'bg' => 'soft' ),
				kh_b(
					'kh/grid',
					array( 'columns' => 3 ),
					kh_b( 'kh/card', array( 'icon' => 'target', 'title' => 'Học đúng việc', 'text' => 'Mỗi bài giải một việc văn phòng có thật.' ) ) .
					kh_b( 'kh/card', array( 'icon' => 'headphones', 'title' => 'Hỗ trợ lâu dài', 'text' => 'Nhóm Zalo giải đáp, không giới hạn thời gian.' ) ) .
					kh_b( 'kh/card', array( 'icon' => 'refresh-cw', 'title' => 'Cập nhật liên tục', 'text' => 'Bài mới được thêm vào khóa, không mất thêm phí.' ) )
				)
			) . kh_b( 'kh/cta', array( 'title' => 'Sẵn sàng bắt đầu?', 'text' => 'Xem khóa học phù hợp hoặc nhắn để được tư vấn lộ trình.' ), kh_buttons( array( array( 'Xem các khóa học', home_url( '/khoa-hoc/' ) ), array( 'Nhắn Zalo tư vấn', $zalo ) ) ) ),
		),
		'lien-he'    => array(
			'Liên hệ',
			kh_b(
				'kh/section',
				array( 'eyebrow' => 'Liên hệ', 'title' => 'Nói chuyện với <strong>chúng tôi</strong>', 'text' => 'Để lại thông tin hoặc nhắn trực tiếp qua các kênh dưới đây.' ),
				kh_b(
					'kh/grid',
					array( 'columns' => 3 ),
					kh_b( 'kh/card', array( 'variant' => 'link', 'icon' => 'zalo', 'title' => 'Nhắn Zalo', 'text' => kh_opt( 'zalo' ) ? kh_opt( 'zalo' ) : kh_opt( 'phone' ), 'url' => $zalo ) ) .
					kh_b( 'kh/card', array( 'variant' => 'link', 'icon' => 'phone', 'title' => 'Gọi hotline', 'text' => kh_opt( 'phone' ), 'url' => 'tel:' . kh_digits( kh_opt( 'phone' ) ) ) ) .
					kh_b( 'kh/card', array( 'variant' => 'link', 'icon' => 'mail', 'title' => 'Gửi email', 'text' => kh_opt( 'email' ), 'url' => 'mailto:' . kh_opt( 'email' ) ) )
				) . kh_b( 'kh/contact-form', array( 'topics' => 'Excel, Word, AI cho công việc, Mua cho công ty' ) )
			),
		),
		'tai-khoan'  => array( 'Tài khoản', kh_demo_account() ),
	);
	$page_ids = array();
	foreach ( $pages as $slug => $p ) {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			$page_ids[ $slug ] = $existing->ID;
			continue;
		}
		$page_ids[ $slug ] = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $p[0],
				'post_name'    => $slug,
				'post_content' => wp_slash( $p[1] ),
			)
		);
	}

	if ( ! empty( $page_ids['trang-chu'] ) && 'page' !== get_option( 'show_on_front' ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_ids['trang-chu'] );
	}

	kh_demo_menus( $page_ids, $course_ids );
	update_option( 'kh_demo_done', KH_VERSION );
	flush_rewrite_rules();
}

/**
 * Menus.
 *
 * @param array $page_ids   Page IDs.
 * @param array $course_ids Course IDs.
 */
function kh_demo_menus( $page_ids, $course_ids ) {
	$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
	$make      = function ( $name, $items ) {
		$menu = wp_get_nav_menu_object( $name );
		if ( $menu ) {
			return $menu->term_id;
		}
		$id = wp_create_nav_menu( $name );
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		foreach ( $items as $it ) {
			wp_update_nav_menu_item(
				$id,
				0,
				array_merge( array( 'menu-item-status' => 'publish' ), $it )
			);
		}
		return $id;
	};
	$page_item   = function ( $id ) {
		return array( 'menu-item-object' => 'page', 'menu-item-object-id' => $id, 'menu-item-type' => 'post_type' );
	};
	$course_item = function ( $id ) {
		return array( 'menu-item-object' => 'khoa_hoc', 'menu-item-object-id' => $id, 'menu-item-type' => 'post_type' );
	};
	// Link "tự đi theo" địa chỉ website (không hỏng khi đổi tên miền / chuyển hosting).
	$archive     = array( 'menu-item-title' => 'Khóa học', 'menu-item-type' => 'post_type_archive', 'menu-item-object' => 'khoa_hoc' );

	if ( empty( $locations['primary'] ) ) {
		$locations['primary'] = $make(
			'Menu chính',
			array_filter(
				array(
					! empty( $page_ids['trang-chu'] ) ? $page_item( $page_ids['trang-chu'] ) : null,
					$archive,
					! empty( $page_ids['gioi-thieu'] ) ? $page_item( $page_ids['gioi-thieu'] ) : null,
					! empty( $page_ids['lien-he'] ) ? $page_item( $page_ids['lien-he'] ) : null,
				)
			)
		);
	}
	if ( empty( $locations['footer_1'] ) ) {
		$locations['footer_1'] = $make( 'Chân trang – Khóa học', array_map( $course_item, array_slice( array_values( $course_ids ), 2, 4 ) ) );
	}
	if ( empty( $locations['footer_2'] ) ) {
		$locations['footer_2'] = $make(
			'Chân trang – Về chúng tôi',
			array_filter(
				array(
					! empty( $page_ids['gioi-thieu'] ) ? $page_item( $page_ids['gioi-thieu'] ) : null,
					array( 'menu-item-title' => 'Tất cả khóa học', 'menu-item-type' => 'post_type_archive', 'menu-item-object' => 'khoa_hoc' ),
					! empty( $page_ids['lien-he'] ) ? $page_item( $page_ids['lien-he'] ) : null,
				)
			)
		);
	}
	set_theme_mod( 'nav_menu_locations', $locations );
}

/**
 * Sửa menu cũ: mục "Khóa học" dạng link cố định (…/khoa-hoc/) → mục "Trang danh sách
 * khóa học" của WordPress, luôn đúng địa chỉ kể cả khi đổi tên miền. Chạy 1 lần.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'kh_menu_archive_fix' ) || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$items = get_posts(
			array(
				'post_type'      => 'nav_menu_item',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => '_menu_item_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'custom', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $items as $item ) {
			$path = (string) wp_parse_url( get_post_meta( $item->ID, '_menu_item_url', true ), PHP_URL_PATH );
			if ( preg_match( '~/khoa-hoc/?$~', $path ) ) {
				update_post_meta( $item->ID, '_menu_item_type', 'post_type_archive' );
				update_post_meta( $item->ID, '_menu_item_object', 'khoa_hoc' );
				update_post_meta( $item->ID, '_menu_item_url', '' );
			}
		}
		update_option( 'kh_menu_archive_fix', 1 );
	}
);

// Run once on first activation.
add_action(
	'after_switch_theme',
	function () {
		if ( ! get_option( 'kh_demo_done' ) ) {
			kh_import_demo();
		}
	},
	20
);

// Manual run from settings page.
add_action(
	'admin_post_kh_import_demo',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền.', 'khoahoc' ) );
		}
		check_admin_referer( 'kh_import_demo' );
		kh_import_demo();
		wp_safe_redirect( admin_url( 'admin.php?page=kh-settings&kh_demo=1' ) );
		exit;
	}
);

/* ---------------------------------------------------------------------------
 * Học viên mẫu: tài khoản để thử trải nghiệm học viên (đăng nhập, học thử,
 * khóa đã mua, bài bị khóa). Mật khẩu ngẫu nhiên, chỉ hiện 1 lần cho admin.
 * ------------------------------------------------------------------------- */

const KH_DEMO_STUDENT = 'hocvien-demo';

/**
 * Tạo (hoặc đặt lại mật khẩu) học viên mẫu, mở sẵn 1 khóa học.
 *
 * @return array{login:string,email:string,password:string,course:string}|WP_Error
 */
function kh_demo_student_create() {
	$password = wp_generate_password( 12, false );
	$user     = get_user_by( 'login', KH_DEMO_STUDENT );
	if ( $user ) {
		wp_set_password( $password, $user->ID );
		$user_id = $user->ID;
	} else {
		$user_id = wp_insert_user(
			array(
				'user_login'   => KH_DEMO_STUDENT,
				'user_email'   => 'hocvien-demo@example.com', // Tên miền dành riêng cho thử nghiệm: không gửi mail tới ai thật.
				'user_pass'    => $password,
				'display_name' => 'Học viên Demo',
				'first_name'   => 'Demo',
				'last_name'    => 'Học viên',
				'role'         => get_role( 'customer' ) ? 'customer' : 'subscriber',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, 'billing_phone', '0900000000' );
		update_user_meta( $user_id, '_kh_demo_student', 1 );
	}

	// Mở sẵn 1 khóa lẻ để thấy cả bài đã mở lẫn bài bị khóa ở các khóa khác.
	$course = get_page_by_path( 'excel-co-ban-nang-cao', OBJECT, 'khoa_hoc' );
	if ( ! $course ) {
		$any    = get_posts( array( 'post_type' => 'khoa_hoc', 'posts_per_page' => 1, 'orderby' => 'menu_order', 'order' => 'DESC' ) );
		$course = $any ? $any[0] : null;
	}
	if ( $course ) {
		kh_grant_course( $user_id, $course->ID, 'admin' );
	}
	$u = get_userdata( $user_id );
	return array(
		'login'    => $u->user_login,
		'email'    => $u->user_email,
		'password' => $password,
		'course'   => $course ? $course->post_title : '',
	);
}

add_action(
	'admin_post_kh_demo_student',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền.', 'khoahoc' ) );
		}
		check_admin_referer( 'kh_demo_student' );
		$res = kh_demo_student_create();
		if ( is_wp_error( $res ) ) {
			wp_die( esc_html( $res->get_error_message() ) );
		}
		// Mật khẩu chỉ hiện 1 lần, cho đúng admin vừa bấm, trong 10 phút.
		set_transient( 'kh_demo_student_' . get_current_user_id(), $res, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=kh-settings&kh_student=1#kh-tab-tools' ) );
		exit;
	}
);

add_action(
	'admin_post_kh_demo_student_delete',
	function () {
		if ( ! current_user_can( 'delete_users' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền.', 'khoahoc' ) );
		}
		check_admin_referer( 'kh_demo_student_delete' );
		$user = get_user_by( 'login', KH_DEMO_STUDENT );
		if ( $user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user->ID );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=kh-settings&kh_student=deleted#kh-tab-tools' ) );
		exit;
	}
);

/**
 * Khung "Học viên mẫu" trong tab Dữ liệu mẫu.
 */
function kh_demo_student_box() {
	$user = get_user_by( 'login', KH_DEMO_STUDENT );
	echo '<hr><h3>' . esc_html__( 'Học viên mẫu', 'khoahoc' ) . '</h3>';
	echo '<p>' . esc_html__( 'Tạo một tài khoản học viên để tự thử: đăng nhập, học thử bài đầu tiên, vào khóa đã mua, thấy bài bị khóa ở khóa chưa mua. Tài khoản được mở sẵn khóa "Excel từ cơ bản tới nâng cao". Mật khẩu tạo ngẫu nhiên và chỉ hiện một lần; bấm lại để đặt mật khẩu mới.', 'khoahoc' ) . '</p>';
	echo '<p><a class="button button-secondary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kh_demo_student' ), 'kh_demo_student' ) ) . '">' . esc_html( $user ? __( 'Đặt lại mật khẩu học viên mẫu', 'khoahoc' ) : __( 'Tạo học viên mẫu', 'khoahoc' ) ) . '</a> ';
	if ( $user ) {
		echo '<a class="button button-link-delete" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kh_demo_student_delete' ), 'kh_demo_student_delete' ) ) . '" onclick="return confirm(\'Xóa tài khoản học viên mẫu?\')">' . esc_html__( 'Xóa học viên mẫu', 'khoahoc' ) . '</a>';
	}
	echo '</p>';
	if ( $user ) {
		echo '<p class="description">' . esc_html( sprintf( 'Đang có: %1$s (%2$s). Nên xóa trước khi website chạy thật.', $user->user_login, $user->user_email ) ) . '</p>';
	}
}

/**
 * Thông báo sau khi tạo / xóa học viên mẫu (trang Cài đặt website).
 */
function kh_demo_student_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification
	$flag = isset( $_GET['kh_student'] ) ? sanitize_key( $_GET['kh_student'] ) : '';
	if ( 'deleted' === $flag ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Đã xóa học viên mẫu.', 'khoahoc' ) . '</p></div>';
		return;
	}
	if ( '1' !== $flag ) {
		return;
	}
	$key = 'kh_demo_student_' . get_current_user_id();
	$res = get_transient( $key );
	delete_transient( $key );
	if ( ! $res ) {
		return;
	}
	printf(
		'<div class="notice notice-success"><p><strong>%1$s</strong></p><p>%2$s <code>%3$s</code> %4$s <code>%5$s</code><br>%6$s <code>%7$s</code></p><p>%8$s</p><p><a class="button" href="%9$s" target="_blank" rel="noopener">%10$s</a></p></div>',
		esc_html__( 'Đã tạo học viên mẫu. Hãy chép mật khẩu ngay, nó chỉ hiện lần này.', 'khoahoc' ),
		esc_html__( 'Tên đăng nhập:', 'khoahoc' ),
		esc_html( $res['login'] ),
		esc_html__( 'hoặc email', 'khoahoc' ),
		esc_html( $res['email'] ),
		esc_html__( 'Mật khẩu:', 'khoahoc' ),
		esc_html( $res['password'] ),
		esc_html( $res['course'] ? sprintf( 'Đã mở khóa: %s. Mở cửa sổ ẩn danh (Cmd/Ctrl + Shift + N) để đăng nhập thử mà không phải thoát tài khoản admin.', $res['course'] ) : '' ),
		esc_url( function_exists( 'kh_account_url' ) ? kh_account_url() : wp_login_url() ),
		esc_html__( 'Mở trang đăng nhập học viên', 'khoahoc' )
	);
}

/**
 * Dữ liệu mẫu cũ dùng video 33 phút: đổi sang video mẫu ~4 phút (1 lần).
 * Chỉ sửa bài học đang dùng đúng video mẫu cũ.
 */
add_action(
	'init',
	function () {
		if ( get_option( 'kh_demo_video_short' ) ) {
			return;
		}
		update_option( 'kh_demo_video_short', 1 );
		$old   = 'k1VUZEVuDJ8';
		$posts = get_posts( array( 'post_type' => 'khoa_hoc', 'post_status' => 'any', 'posts_per_page' => -1, 's' => $old ) );
		foreach ( $posts as $p ) {
			$content = preg_replace_callback(
				'/<!-- wp:kh\/lesson (\{.*?\}) \/-->/',
				function ( $m ) use ( $old ) {
					$a = json_decode( $m[1], true );
					if ( ! is_array( $a ) || empty( $a['video'] ) || false === strpos( $a['video'], $old ) ) {
						return $m[0];
					}
					$a['video']    = KH_DEMO_TRIAL_VIDEO;
					$a['duration'] = KH_DEMO_VIDEO_TIME;
					return '<!-- wp:kh/lesson ' . serialize_block_attributes( $a ) . ' /-->';
				},
				$p->post_content
			);
			if ( $content !== $p->post_content ) {
				wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( $content ) ) );
			}
		}
	},
	20
);
