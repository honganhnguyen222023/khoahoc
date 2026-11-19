#!/bin/sh
# Chép các file đã đóng gói + tài liệu sang thư mục riêng trên máy.
# Thư mục đích ghi trong file .copy-dist-to (1 dòng, KHÔNG đưa lên GitHub).
# Không có file đó thì bỏ qua.
cd "$(dirname "$0")/.."
[ -f .copy-dist-to ] || exit 0
DEST=$(head -n 1 .copy-dist-to | sed "s|^~|$HOME|")
[ -n "$DEST" ] || exit 0
mkdir -p "$DEST"
for f in dist/*.zip docs/Huong-dan-KhoaHoc-Academy.docx docs/Tai-khoan-website.txt; do
	[ -f "$f" ] && cp -p "$f" "$DEST/"
done
echo "Đã chép sang: $DEST"
