// MoMo: GET = học viên quay lại sau khi thanh toán; POST = MoMo báo kết quả cho máy chủ (IPN).
import { NextResponse } from 'next/server';
import { applyPayment, thanksUrl } from '@/lib/payment-result';
import { momoVerify } from '@/lib/payments';

export async function GET(req: Request) {
  const url = new URL(req.url);
  const site = process.env.SITE_URL || url.origin;
  const v = momoVerify(Object.fromEntries(url.searchParams));
  const r = await applyPayment('MoMo', { ...v, trans: v.transId });
  if (!r.order) return NextResponse.redirect(new URL('/tai-khoan/don-hang/', site), 303);
  return NextResponse.redirect(new URL(thanksUrl(r.order, r.ok ? '' : r.code === 'that-bai' ? 'Giao dịch bị hủy hoặc không thành công.' : 'Không xác minh được kết quả thanh toán.'), site), 303);
}

export async function POST(req: Request) {
  const body = (await req.json().catch(() => ({}))) as Record<string, unknown>;
  const v = momoVerify(Object.fromEntries(Object.entries(body).map(([k, x]) => [k, String(x ?? '')])));
  await applyPayment('MoMo', { ...v, trans: v.transId });
  return new Response(null, { status: 204 });
}
