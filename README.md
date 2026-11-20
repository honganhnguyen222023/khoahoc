# KhoaHoc Academy – bản Node.js

Viết lại website KhoaHoc Academy (WordPress + WooCommerce) bằng **Next.js + Prisma**.
Giao diện dùng lại đúng CSS / JS của theme WordPress nên trông giống hệt.

## Chạy trên máy

Cần Node.js 18.18 trở lên (máy này: `~/.node22/bin`).

```bash
npm install          # lần đầu
npm run setup        # tạo cơ sở dữ liệu + nhập dữ liệu từ data/wordpress-export.json
npm run dev          # mở http://localhost:3000
```

## Lấy dữ liệu mới từ WordPress

```bash
php -d mysqli.default_socket="<socket MySQL của Local>" scripts/export-wordpress.php \
    "~/Local Sites/khoahoc/app/public" > data/wordpress-export.json
npm run import:wp    # XÓA dữ liệu bản Node rồi nhập lại
```

## Cấu trúc

| Thư mục | Nội dung |
|---|---|
| `app/(site)` | Trang công khai: trang chủ, khóa học, trang con |
| `app/api` | Xử lý form, thanh toán, học bài |
| `lib/` | Logic: cài đặt, học viên & bài học (`lms.ts`), khối nội dung (`blocks.ts`)… |
| `prisma/schema.prisma` | Cấu trúc cơ sở dữ liệu |
| `styles/`, `public/` | CSS, JS, font, ảnh lấy từ theme |
| `scripts/` | Xuất / nhập dữ liệu WordPress |
