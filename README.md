# KhoaHoc Academy: theme WordPress cho website khóa học

Theme WordPress viết bằng Tailwind CSS cho học viện hoặc trung tâm dạy online. Bố cục, màu sắc, cỡ chữ và khoảng cách lấy cảm hứng từ phuonghao.com. Nội dung, logo và hình ảnh là của bạn, sửa được hoàn toàn trong trang quản trị, không cần biết code.

![Ảnh giao diện](screenshot.png)

## Có gì trong theme

| Tính năng | Bạn sửa ở đâu |
|---|---|
| Trang chủ với 12 khu vực (banner, số liệu, vấn đề, giới thiệu, lộ trình, khóa học, dự án thực chiến, cách dạy, phản hồi, kênh miễn phí, câu hỏi thường gặp, kêu gọi) | **Trang → Trang chủ**: bấm vào chữ để sửa trực tiếp |
| Quản lý khóa học: học phí, giá gốc, nhãn, lợi ích, video giới thiệu, nút đăng ký | **Khóa học → Thêm khóa học** |
| Giáo trình theo chương/bài, có bài "Học thử" | Trong nội dung khóa học: khối **Chương học** và **Bài học** |
| Form liên hệ/tư vấn: lưu vào admin và gửi email báo | Khối **Form liên hệ**. Xem tin nhắn ở menu **Liên hệ** |
| Hotline, Zalo, email, mạng xã hội, màu sắc, nút đầu trang, chân trang | **Cài đặt website** |
| Nút Zalo/Gọi nổi ở góc màn hình (mobile) | **Cài đặt website → Liên hệ** |
| SEO: meta description, ảnh chia sẻ Facebook/Zalo, schema Khóa học, FAQ, Breadcrumb | Tự động. Tự nhường cho Yoast/Rank Math nếu bạn cài |
| Google Analytics 4, Facebook Pixel | **Cài đặt website → SEO & đo lường** |
| Bán khóa học qua WooCommerce (không bắt buộc) | Nhập **ID sản phẩm WooCommerce** trong khóa học |

### Tốc độ
- Font Be Vietnam Pro và Inter lưu ngay trên hosting, chỉ tải bộ chữ Việt/Latin cần dùng, không gọi Google Fonts.
- CSS khoảng 10KB, JS khoảng 2KB (đã nén gzip). Không dùng jQuery ở giao diện ngoài.
- Icon Lucide nhúng dạng SVG, không tải bộ font icon.
- Video YouTube chỉ tải khi người xem bấm Play.
- Ảnh tự tải chậm (lazy load), có srcset. Ảnh banner được ưu tiên tải trước.
- Đã tắt emoji script và các thẻ thừa trong `<head>`.

### Hiệu ứng
- Banner có một hiệu ứng mở màn: chữ và ảnh trượt lên, 2 nhãn nổi nhẹ.
- Các khu vực hiện dần khi cuộn tới. Con số chạy từ 0.
- Câu hỏi FAQ mở/đóng có hiệu ứng.
- Người dùng bật "giảm chuyển động" trên máy thì toàn bộ hiệu ứng tự tắt.

## Cài đặt

1. Tải theme: trên GitHub bấm **Code → Download ZIP**.
2. Giải nén, **đổi tên thư mục thành `khoahoc`**, nén lại thành `khoahoc.zip`.
3. Vào WordPress: **Giao diện → Giao diện → Thêm mới → Tải giao diện lên**, chọn `khoahoc.zip`, rồi **Kích hoạt**.
4. Khi kích hoạt, theme tự tạo sẵn:
   - Trang **Trang chủ**, **Giới thiệu**, **Liên hệ**
   - 6 khóa học mẫu, chia theo danh mục
   - Menu đầu trang và 2 menu chân trang
5. Vào **Cài đặt → Đường dẫn tĩnh**, chọn **Tên bài viết**, bấm **Lưu**.
6. Vào **Cài đặt website** để nhập hotline, Zalo, email, Facebook, TikTok.

> Yêu cầu: WordPress 6.6 trở lên, PHP 7.4 trở lên.
> Muốn tạo lại dữ liệu mẫu: **Cài đặt website → Dữ liệu mẫu → Nhập dữ liệu mẫu**. Trang đã có sẽ không bị ghi đè.

## Hướng dẫn sửa nội dung (không cần code)

### Đổi logo
**Giao diện → Tùy biến → Nhận dạng site → Logo**. Nếu chưa có logo, theme hiện chữ cái trong ô vuông. Đổi chữ này ở **Cài đặt website → Thương hiệu**.

### Sửa trang chủ
1. Vào **Trang → Trang chủ → Chỉnh sửa**.
2. Bấm vào bất kỳ chữ nào để sửa.
3. **Bôi đậm** một chữ trong tiêu đề, chữ đó sẽ được tô màu nổi bật như trên website.
4. Muốn đổi ảnh hoặc chọn biểu tượng: bấm vào khối, cột bên phải hiện các tùy chọn.
5. Thêm khối mới: bấm dấu **+**, tìm nhóm **"Khối của theme"**.

| Khối | Dùng để |
|---|---|
| Banner đầu trang (Hero) | Tiêu đề lớn, mô tả, nút, ảnh, 2 nhãn nổi |
| Dãy số liệu | Các con số nổi bật (học viên, người theo dõi…) |
| Khu vực nội dung | Khung có tiêu đề nhỏ, tiêu đề lớn, mô tả. Kéo khối khác vào trong. Chọn được nền trắng hoặc nền xanh nhạt |
| Lưới thẻ + Thẻ | Thẻ có 6 kiểu: tính năng, vấn đề, bước lộ trình, ảnh, liên kết, gợi ý chọn khóa |
| Giới thiệu (ảnh + câu trích) | Ảnh người dạy và câu chuyện thương hiệu |
| Danh sách khóa học | Tự lấy từ mục **Khóa học**, kiểu thẻ lớn hoặc lưới |
| Ảnh phản hồi học viên | Lưới ảnh chụp tin nhắn, nhận xét |
| Câu hỏi thường gặp | Hỏi – đáp xổ xuống, tự tạo dữ liệu FAQ cho Google |
| Kêu gọi hành động | Khối nền đậm cuối trang, tự hiện hotline và email |
| Form liên hệ | Khách điền form, bạn nhận email và xem lại trong admin |
| Chương học / Bài học | Giáo trình trong trang khóa học |

**Menu cuộn tới một khu vực:** trong khối, mục **ID neo**, nhập ví dụ `khoa-hoc`. Trong menu, thêm link `/#khoa-hoc`.

### Thêm hoặc sửa khóa học
Vào **Khóa học → Thêm khóa học** và điền các phần sau:
- **Tiêu đề** và **Đoạn trích** (mô tả ngắn hiện trên thẻ khóa học).
- **Ảnh đại diện**: ảnh bìa trên thẻ lớn và khi chia sẻ Facebook.
- **Thông tin khóa học**:
  - Học phí và giá gốc (có giá gốc sẽ tự hiện số tiền tiết kiệm)
  - Nhãn, biểu tượng, dòng thông tin
  - Lợi ích: mỗi dòng một ý, `**chữ**` để in đậm
  - Link YouTube, link đăng ký
  - Ô **Khóa nổi bật**: tick để hiện thẻ lớn ở trang chủ
- **Thứ tự** (cột phải, mục Thuộc tính): số nhỏ hiện trước.

Nút **Đăng ký** đi theo thứ tự ưu tiên:
1. Link bạn tự nhập.
2. Thanh toán WooCommerce, nếu có nhập ID sản phẩm.
3. Mở Zalo.

### Xem khách để lại thông tin
Menu **Liên hệ** (có số đỏ báo tin mới) hiện danh sách khách. Mở từng khách sẽ có nút **Gọi** và **Nhắn Zalo**.

> Nếu không nhận được email báo, cài plugin **WP Mail SMTP**. Nhiều hosting chặn gửi mail mặc định.

## Hệ thống thiết kế

| Token | Giá trị | Sửa được trong admin |
|---|---|---|
| Màu chính | `#157A4F` | Có |
| Màu tiêu đề | `#10261C` | Có |
| Màu nền đậm (CTA) | `#0E3B2B` | Có |
| Màu chữ thường | `#57586E` | |
| Nền nhạt | `#F2F7F4` | |
| Font tiêu đề | Be Vietnam Pro 600/700/800 | |
| Font nội dung | Inter 400/500/600 | |
| H1 / H2 / chữ thường | 56px / 42px / 16–19px (tự co nhỏ trên mobile) | |
| Khung nội dung | 1140px (khối nền), 1040px (nội dung) | |
| Bo góc | Thẻ 14px, khối 20px, nút bo tròn hoàn toàn | |

## Dành cho lập trình viên

```
khoahoc/
├── style.css                  # Thông tin theme
├── functions.php              # Nạp các file trong inc/
├── inc/
│   ├── setup.php              # Hỗ trợ theme, menu, font, CSS/JS, tối ưu tốc độ
│   ├── options.php            # Trang "Cài đặt website"
│   ├── post-types.php         # Khóa học, Danh mục, Liên hệ
│   ├── course-meta.php        # Ô "Thông tin khóa học"
│   ├── blocks.php             # Render PHP của 15 khối Gutenberg
│   ├── contact.php            # Form liên hệ + chống spam
│   ├── seo.php                # Meta, Open Graph, JSON-LD, GA4
│   ├── woocommerce.php        # Tương thích WooCommerce
│   ├── demo.php               # Dữ liệu mẫu khi kích hoạt
│   └── icons.php / icons-data.php  # Icon Lucide (SVG)
├── blocks/editor.js           # Giao diện soạn thảo các khối (JS thuần, không cần build)
├── src/app.css                # Nguồn Tailwind
├── assets/css/app.css         # CSS đã build (đã commit, hosting không cần Node)
├── assets/js/app.js           # Menu mobile, hiệu ứng, video, FAQ
├── assets/fonts/              # Font tự host
└── assets/img/                # Ảnh mẫu (SVG), thay bằng ảnh thật
```

**Build CSS sau khi sửa giao diện:**
```bash
npm install
```
```bash
npm run build
```
Hoặc dùng `npm run dev` để tự build mỗi khi lưu file.

**Đóng gói** (macOS/Linux, file nằm trong `dist/`):
```bash
npm run zip:theme    # dist/khoahoc.zip: tải lên WordPress (Giao diện → Tải giao diện lên)
npm run zip:source   # dist/khoahoc-source.zip: chép sang máy khác (có .git, không có node_modules)
```
Hướng dẫn đầy đủ đưa website lên hosting và chép sang máy khác: `docs/Huong-dan-KhoaHoc-Academy.docx` (Phần 3 và 4).

**Vì sao các khối render bằng PHP:** khối chỉ lưu dữ liệu (thuộc tính), HTML do PHP tạo khi hiển thị. Khi nâng cấp thiết kế, nội dung cũ không bị lỗi "khối không hợp lệ".

**Bảo mật form:** có nonce, ô bẫy bot (honeypot), chặn gửi nhanh dưới 3 giây, giới hạn 1 lần/30 giây mỗi IP. Mọi dữ liệu đều được làm sạch trước khi lưu.

## Giấy phép
GPL-2.0-or-later. Icon [Lucide](https://lucide.dev) (ISC). Font Be Vietnam Pro và Inter (SIL OFL).
