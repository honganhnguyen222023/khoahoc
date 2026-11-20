#!/bin/bash
# Bấm đúp file này để chạy website KhoaHoc (bản Node.js) trên máy Mac.
# Website mở ở http://localhost:3000  –  Quản trị: http://localhost:3000/quan-tri/
# Muốn tắt: đóng cửa sổ Terminal này (hoặc bấm Ctrl + C).
cd "$(dirname "$0")"
export PATH="$HOME/.node22/bin:$PATH"
if ! command -v node >/dev/null 2>&1; then
  echo "Chưa có Node.js. Cài Node.js 22 tại https://nodejs.org rồi chạy lại."
  read -r -p "Bấm Enter để đóng…"
  exit 1
fi
[ -f .env ] || cp .env.example .env
[ -d node_modules ] || npm install
[ -f prisma/khoahoc.db ] || npm run setup
echo ""
echo "Website: http://localhost:3000   |   Quản trị: http://localhost:3000/quan-tri/"
echo "Đóng cửa sổ này để tắt website."
(sleep 5; open "http://localhost:3000") &
npm run dev
