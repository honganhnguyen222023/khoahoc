// VNPay chuyển học viên về đây sau khi thanh toán.
import { NextResponse } from 'next/server';
import { applyPayment, thanksUrl } from '@/lib/payment-result';
import { vnpayVerify } from '@/lib/payments';

export async function GET(req: Request) {
  const url = new URL(req.url);
  const site = process.env.SITE_URL || url.origin;
  const v = vnpayVerify(url.searchParams);
  const r = await applyPayment('VNPay', { ...v, trans: v.transNo });
  if (!r.order) return NextResponse.redirect(new URL('/tai-khoan/don-hang/', site), 303);
  return NextResponse.redirect(new URL(thanksUrl(r.order, r.ok ? '' : r.code === 'that-bai' ? 'Giao dịch bị hủy hoặc không thành công.' : 'Không xác minh được kết quả thanh toán.'), site), 303);
}
