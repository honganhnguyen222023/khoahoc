#!/bin/sh
# Đóng gói theme.
#   sh scripts/package.sh theme   → dist/khoahoc.zip         (tải lên hosting: Giao diện → Thêm mới → Tải lên)
#   sh scripts/package.sh source  → dist/khoahoc-source.zip  (chép sang máy khác: đủ mã nguồn + lịch sử git)
# Thư mục bên trong file zip luôn tên "khoahoc", dù thư mục dự án trên máy tên gì.
set -e

cd "$(dirname "$0")/.."
ROOT=$(pwd)
MODE=${1:-theme}
TMP=$(mktemp -d)
STAGE="$TMP/khoahoc"
trap 'rm -rf "$TMP"' EXIT

case "$MODE" in
	theme)
		npm run build --silent
		OUT="dist/khoahoc.zip"
		rsync -a ./ "$STAGE/" \
			--exclude node_modules --exclude .git --exclude dist --exclude docs \
			--exclude scripts --exclude .DS_Store --exclude '*.log'
		;;
	source)
		OUT="dist/khoahoc-source.zip"
		rsync -a ./ "$STAGE/" \
			--exclude node_modules --exclude dist --exclude .DS_Store --exclude '*.log'
		;;
	*)
		echo "Cách dùng: sh scripts/package.sh [theme|source]" >&2
		exit 1
		;;
esac

mkdir -p dist
rm -f "$OUT"
(cd "$TMP" && zip -qrX "$ROOT/$OUT" khoahoc)
echo "Đã tạo $OUT ($(du -h "$OUT" | cut -f1))"
