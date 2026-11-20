# Ghi chú thay đổi – KhoaHoc Academy

Cập nhật: 20/11/2026

## Bản và nơi lưu

| | Bản gốc (tiếng Việt) | Bản B (quản trị riêng) |
|---|---|---|
| Thư mục code | `/Users/mac/khoahoc` | `/Users/mac/khoahoc-quantri` |
| Nhánh git | `main` (mốc `v1-tieng-viet`) | `quan-tri-b` |
| File đóng gói | `~/Documents/VANHIEP/WEB KHOAHOC WORDPRESS/` | `~/Documents/VANHIEP/WEB KHOAHOC WORDPRESS/QUAN-TRI-B/` |
| Sao lưu | `~/Documents/VANHIEP/WEB KHOAHOC WORDPRESS/BACKUP/` | |

Site LocalWP `khoahoc` (http://khoahoc.local) đang nối thẳng vào thư mục **bản B**
(`wp-content/themes/khoahoc` là đường tắt tới `/Users/mac/khoahoc-quantri`): sửa code là thấy ngay, không cần import lại.

File đóng gói:
- `khoahoc.zip`: chỉ theme → cài lên hosting (Giao diện → Thêm mới → Tải lên).
- `khoahoc-localwp.zip`: cả website (dữ liệu + plugin + theme) → kéo thả vào app Local ở máy khác.
- `khoahoc-source.zip`: mã nguồn + lịch sử git → để sửa code tiếp ở máy khác. Không dùng để import.

## 1. Toàn bộ website bằng tiếng Việt (`inc/vietnamese.php`)

- Luôn dùng tiếng Việt cho cả website và trang quản trị, kể cả khi tài khoản chọn ngôn ngữ khác.
- Tự tải bản dịch tiếng Việt cho WordPress và WooCommerce. Tải lại bằng tay: Cài đặt → tab Công cụ → "Tải lại bản dịch tiếng Việt".
- Viết lại chữ WooCommerce cho website khóa học: giỏ hàng, thanh toán ("Đặt mua khóa học"), tài khoản, đăng nhập, trạng thái đơn.
- Họ tên kiểu Việt ("Nguyễn Văn An"), ô Họ đứng trước ô Tên, form thông tin thanh toán chỉ còn họ tên, điện thoại, email.
- Ngày dd/mm/yyyy, giờ 24h, múi giờ Hà Nội. Đổi tên trang Cart/Checkout/Shop sang tiếng Việt (giữ nguyên link).
- Theme không sập khi thiếu file trong `inc/` (giải nén lỗi, phần mềm diệt virus xóa nhầm): báo lỗi đỏ trong quản trị.

## 2. Trang quản trị riêng `/quan-tri/` (bản B)

Code: `inc/quantri.php`, `inc/quantri/*.php`, `assets/css/quantri.css`, `assets/js/quantri.js`.

| Mục | Làm được gì |
|---|---|
| Tổng quan | Doanh thu tháng, đơn chờ xác nhận, học viên mới, liên hệ chưa đọc, nút "Đã nhận tiền" |
| Đơn hàng | Lọc, tìm kiếm; xác nhận đã nhận tiền (khóa học tự mở), hủy, hoàn tiền, ghi chú |
| Học viên | Tìm theo tên/email/SĐT, thêm học viên + mở khóa, gia hạn, cho xem lại, thu hồi |
| Khóa học | Học phí, thời hạn, ảnh, danh mục, combo, giáo trình (thêm/sửa/sắp xếp chương và bài) |
| Liên hệ | Hộp thư chưa xử lý / đã xử lý, gọi, Zalo, tạo tài khoản học viên |
| Cài đặt | Toàn bộ cài đặt website + tài khoản ngân hàng nhận chuyển khoản |

- Dùng tốt trên điện thoại (menu ☰). Trang WordPress cũ vẫn còn: menu trái → "Quản trị nâng cao".
- Lưu ý giáo trình: tiến độ học viên lưu theo **thứ tự bài** → khóa đã có học viên nên thêm bài mới vào cuối.

## 3. Trang đăng nhập riêng

- `/quan-tri/dang-nhap/` và `/quan-tri/quen-mat-khau/`, cùng giao diện quản trị. Code: `inc/quantri/login.php`.
- Chưa đăng nhập mở `/quan-tri/...` → về trang đăng nhập rồi quay lại đúng trang.
- Sai mật khẩu 5 lần trong 15 phút (theo IP) → tạm khóa 15 phút.
- Học viên đăng nhập nhầm ở đây được đưa về website. Đăng xuất về trang đăng nhập.

## 4. Sửa lỗi: hết lượt xem vẫn xem lại được (`inc/lms.php`, `assets/js/app.js`)

Lỗi cũ: 1 lượt = 1 lần **mở trang**, tải lại trong 30 phút vẫn xem → ở lượt cuối, bấm Play xem lại vô hạn; link "Mở trên YouTube" xem ngoài không bị đếm.

Cách tính mới:
- **1 lượt = 1 lần xem hết video** (đủ % "Xem bao nhiêu % thì tính là học xong", mặc định 80%). Mở / tải lại trang không tính.
- Xem lại từ đầu (hết video rồi phát lại, hoặc tua về nửa đầu) = lượt mới.
- Lượt cuối đã tính: hết video hoặc tua ngược → video bị khóa ngay trên trang; tải lại vẫn khóa.
- Áp dụng cả lượt học thử. Ẩn link "Mở trên YouTube" ở bài có giới hạn lượt (quản trị viên vẫn thấy).
- Video Google Drive / Bunny: trình duyệt không theo dõi được thời gian xem → vẫn tính khi mở trang (như cũ).
- Muốn cho học viên xem thêm: `/quan-tri/hoc-vien/<id>/` → "Cho xem lại".

Giới hạn còn lại: video YouTube để "Không công khai" thì ai có link vẫn xem được trên YouTube.
Muốn chặn hẳn, nên dùng Vimeo (chặn nhúng ngoài) hoặc Bunny Stream có khóa bảo mật.

## Đã kiểm tra (trên khoahoc.local, Chrome tự động)

- 11 trang quản trị mở được, không lỗi PHP / JavaScript; điện thoại 390px không tràn ngang.
- Xác nhận đơn → học viên được mở đúng khóa trong combo; tạo học viên + mở khóa; thêm bài → website hiện bài mới; lưu khóa / cài đặt không làm sai dữ liệu.
- Đăng nhập đúng/sai, quên mật khẩu, khóa sau 5 lần sai, đăng xuất, học viên bị chặn khỏi quản trị.
- Lượt xem với video YouTube thật: mở trang 3 lần không trừ, xem 2 lần trừ 2 lượt, tua ngược khóa ngay, tải lại vẫn khóa; học thử mở trang không trừ.
- Sau mỗi lần thử, dữ liệu site Local được khôi phục như trước khi thử.

## Tài khoản thử trên máy (không dùng cho website thật)

- Quản trị: `admin` / `admin`
- Học viên: `hocvien` / `hocvien123` (đã mua Excel), `hocvien2` / `hocvien123` (đơn #38 chờ xác nhận)
