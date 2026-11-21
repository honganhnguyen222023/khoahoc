// Ghi nhận kết quả thanh toán từ cổng (dùng chung cho trang quay lại và IPN).
import { db } from './db';
import { addNote, orderKey, setOrderStatus } from './orders';

export async function applyPayment(gateway: 'VNPay' | 'MoMo', r: { valid: boolean; paid: boolean; ref: string; amount: number; trans: string }) {
  if (!r.valid) return { ok: false as const, code: 'chu-ky', order: null };
  const order = await db.order.findFirst({ where: { paymentRef: r.ref } });
  if (!order) return { ok: false as const, code: 'khong-thay', order: null };
  if (r.amount !== order.total) {
    await addNote(order.id, `${gateway}: số tiền không khớp (${r.amount} ≠ ${order.total}). Kiểm tra lại giao dịch ${r.trans}.`, gateway);
    return { ok: false as const, code: 'sai-tien', order };
  }
  if (r.paid) {
    if (!['processing', 'completed'].includes(order.status)) await setOrderStatus(order.id, 'processing', `${gateway} – mã giao dịch ${r.trans}.`, gateway);
    return { ok: true as const, code: 'ok', order };
  }
  if (order.status === 'pending') await setOrderStatus(order.id, 'failed', `${gateway}: giao dịch không thành công.`, gateway);
  return { ok: false as const, code: 'that-bai', order };
}

export function thanksUrl(order: { id: number; createdAt: Date }, err = ''): string {
  return `/thanh-toan/da-dat/${order.id}/?key=${orderKey(order)}${err ? '&loi=' + encodeURIComponent(err) : ''}`;
}
