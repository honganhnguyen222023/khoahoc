// VNPay báo kết quả trực tiếp cho máy chủ (khai báo link này trong tài khoản VNPay).
import { applyPayment } from '@/lib/payment-result';
import { vnpayVerify } from '@/lib/payments';

export async function GET(req: Request) {
  const v = vnpayVerify(new URL(req.url).searchParams);
  if (!v.valid) return Response.json({ RspCode: '97', Message: 'Invalid signature' });
  const r = await applyPayment('VNPay', { ...v, trans: v.transNo });
  if (!r.order) return Response.json({ RspCode: '01', Message: 'Order not found' });
  if (r.code === 'sai-tien') return Response.json({ RspCode: '04', Message: 'Invalid amount' });
  return Response.json({ RspCode: '00', Message: 'Confirm Success' });
}
