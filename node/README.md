# KhoaHoc Academy – bản Node.js

Website KhoaHoc Academy viết lại từ bản WordPress + WooCommerce bằng **Next.js + Prisma**.
Giao diện dùng lại đúng CSS / JS của theme nên trông giống hệt (đã so từng điểm ảnh).

## Chạy trên máy Mac

**Cách dễ nhất:** bấm đúp file **`Chay-website.command`** (Mac) hoặc **`Chay-website.bat`** (Windows).
Hướng dẫn từng bước cho máy khác (Windows / Mac): **`HUONG-DAN-CHAY-MAY-KHAC.txt`**.
Website mở ở http://localhost:3000 – quản trị ở http://localhost:3000/quan-tri/

Hoặc gõ lệnh (cần Node.js 18.18 trở lên; máy này: `~/.node22/bin`):

```bash
npm install      # lần đầu
npm run setup    # tạo cơ sở dữ liệu + nhập dữ liệu từ data/wordpress-export.json
npm run dev      # chạy thử
```

Tài khoản (nhập từ WordPress, mật khẩu giữ nguyên): `admin` / `admin`, `hocvien` / `hocvien123`, `hocvien2` / `hocvien123`.

## Có gì

| Phần | Đường dẫn |
|---|---|
| Trang chủ, Giới thiệu, Liên hệ (form tư vấn) | `/`, `/gioi-thieu/`, `/lien-he/` |
| Khóa học: lọc, sắp xếp, chi tiết, giáo trình | `/khoa-hoc/`, `/khoa-hoc/<tên-khóa>/` |
| Trang học: tuần tự, lượt xem, học thử, thời hạn | `/khoa-hoc/<tên-khóa>/?bai=1` |
| Tài khoản học viên: đăng nhập/đăng ký, khóa học của tôi, đơn hàng | `/tai-khoan/` |
| Giỏ hàng, thanh toán: chuyển khoản (mã QR VietQR), VNPay, MoMo | `/gio-hang/`, `/thanh-toan/` |
| Quản trị: tổng quan, đơn hàng, học viên, khóa học, liên hệ, cài đặt | `/quan-tri/` |

## Cấu hình (`.env`)

Sao chép `.env.example` thành `.env`:

- `SITE_URL`: địa chỉ website (dùng cho link trong email và cổng thanh toán).
- `SMTP_*`: gửi email thật. Để trống thì email chỉ được lưu lại, xem ở **Quản trị → Cài đặt → Email đã gửi**.
- `VNPAY_*`: đăng ký sandbox miễn phí tại https://sandbox.vnpayment.vn/devreg/ rồi bật trong **Quản trị → Cài đặt → Thanh toán**.
- `MOMO_*`: đang là tài khoản **test công khai** của MoMo (thanh toán thử, không mất tiền). Khi bán thật, thay bằng mã của bạn.

Cổng thanh toán cần gọi ngược về website (IPN): VNPay `…/api/thanh-toan/vnpay/ipn/`, MoMo `…/api/thanh-toan/momo/`. Trên máy thì cổng không gọi tới được, nhưng trang quay lại sau thanh toán vẫn ghi nhận kết quả.

## Lấy dữ liệu mới từ WordPress

```bash
php -d mysqli.default_socket="<socket MySQL của Local>" scripts/export-wordpress.php \
    "$HOME/Local Sites/khoahoc/app/public" > data/wordpress-export.json
npm run import:wp    # XÓA dữ liệu bản Node rồi nhập lại
```

## Đưa lên server

Cần VPS (hoặc dịch vụ chạy được Node.js). Nên đổi cơ sở dữ liệu sang PostgreSQL: sửa `provider = "postgresql"` trong
`prisma/schema.prisma`, đặt `DATABASE_URL`, rồi `npm run db:push && npm run import:wp && npm run build && npm start`.

## Cấu trúc

| Thư mục | Nội dung |
|---|---|
| `app/(site)` | Trang công khai, tài khoản, giỏ hàng, thanh toán |
| `app/quan-tri` | Trang quản trị |
| `app/api` | Form liên hệ, form học viên, học bài, kết quả thanh toán |
| `lib/` | Logic: cài đặt, học viên & bài học (`lms.ts`), đơn hàng, thanh toán, khối nội dung |
| `lib/admin/` | Các trang và thao tác quản trị |
| `prisma/schema.prisma` | Cấu trúc cơ sở dữ liệu |
| `styles/`, `public/` | CSS, JS, font, ảnh lấy từ theme |
| `scripts/` | Xuất / nhập dữ liệu WordPress |

## Còn khác bản WordPress

- Chưa có trình sửa nội dung trang (trang chủ, Giới thiệu, bài giới thiệu dài của khóa học). Muốn sửa: sửa trên WordPress rồi xuất/nhập lại dữ liệu.
- Không có Thư viện ảnh: ảnh khóa học / logo tải lên trực tiếp trong quản trị.
- Chưa có mã giảm giá.
