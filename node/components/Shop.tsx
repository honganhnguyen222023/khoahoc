// Mẩu HTML cho giỏ hàng / thanh toán (giống WooCommerce cổ điển).
import { esc, price } from '@/lib/html';

export const money = (n: number) => `<span class="woocommerce-Price-amount amount"><bdi>${price(n).replace('đ', '') || '0'}<span class="woocommerce-Price-currencySymbol">₫</span></bdi></span>`;

export function shopNotice(kind: 'ok' | 'err' | 'info', text: string): string {
  if (kind === 'err') return `<ul class="woocommerce-error" role="alert"><li>${esc(text)}</li></ul>`;
  return `<div class="woocommerce-${kind === 'ok' ? 'message' : 'info'}" role="alert">${esc(text)}</div>`;
}

export function shopHead(title: string): string {
  return `<header class="kh-page-head"><div class="kh-container"><nav class="kh-breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Trang chủ</a></li><li aria-current="page">${esc(title)}</li></ol></nav><h1 class="kh-h1 kh-h1-sm">${esc(title)}</h1></div></header>`;
}

/** CSS WooCommerce đã nạp chung trong app/(site)/layout.tsx (giữ hàm để không phải sửa các trang). */
export function WooCss() {
  return null;
}
