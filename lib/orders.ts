// Đơn hàng: tạo đơn, đổi trạng thái → tự mở / thu hồi khóa học, gửi email.
// (Giống phần WooCommerce trong inc/woocommerce.php của theme.)
import type { Order, OrderItem } from '@prisma/client';
import { createHmac, randomBytes } from 'node:crypto';
import { db } from './db';
import { price } from './html';
import { grantCourse, revokeCourse, learnUrl } from './lms';
import { sendMail } from './mail';
import { getSettings, opt } from './settings';

export const STATUS: Record<string, [string, string]> = {
  pending: ['Chờ thanh toán', 'warn'],
  'on-hold': ['Chờ xác nhận', 'warn'],
  processing: ['Đã thanh toán', 'ok'],
  completed: ['Hoàn thành', 'ok'],
  cancelled: ['Đã hủy', 'mute'],
  refunded: ['Đã hoàn tiền', 'mute'],
  failed: ['Thất bại', 'bad'],
};

export const PAID = ['processing', 'completed'];

export const PAYMENT_TITLES: Record<string, string> = {
  bacs: 'Chuyển khoản ngân hàng',
  vnpay: 'VNPay (thẻ ATM, Visa, QR ngân hàng)',
  momo: 'Ví MoMo',
  free: 'Miễn phí',
};

export function orderName(o: Pick<Order, 'firstName' | 'lastName' | 'email'>): string {
  return `${o.lastName} ${o.firstName}`.trim() || o.email || 'Khách';
}

/** Mã bí mật của đơn (để xem trang "Đã đặt hàng" mà không lộ đơn của người khác). */
export function orderKey(o: Pick<Order, 'id' | 'createdAt'>): string {
  const secret = process.env.APP_SECRET || 'khoahoc';
  return createHmac('sha256', secret).update(`${o.id}:${o.createdAt.getTime()}`).digest('hex').slice(0, 16);
}

export function newRef(prefix = 'KH'): string {
  return `${prefix}${Date.now().toString().slice(-8)}${randomBytes(2).toString('hex').toUpperCase()}`;
}

export async function addNote(orderId: number, content: string, author = 'system') {
  await db.orderNote.create({ data: { orderId, content, author } });
}

/** Mở khóa học của đơn đã thanh toán. */
async function grantOrder(o: Order & { items: OrderItem[] }) {
  if (!o.userId) return;
  const opened: string[] = [];
  for (const it of o.items) {
    if (it.courseId && (await grantCourse(o.userId, it.courseId, `order:${o.id}`))) opened.push(it.name);
  }
  if (opened.length) await addNote(o.id, 'Đã mở khóa học: ' + opened.join(', '));
}

/** Thu hồi khóa học của đơn bị hủy / hoàn tiền (chỉ khóa mở từ chính đơn này). */
async function revokeOrder(o: Order & { items: OrderItem[] }) {
  if (!o.userId) return;
  const closed: string[] = [];
  for (const it of o.items) {
    if (!it.courseId) continue;
    const cur = await db.access.findUnique({ where: { userId_courseId: { userId: o.userId, courseId: it.courseId } } });
    if (cur?.source === `order:${o.id}`) {
      await revokeCourse(o.userId, it.courseId, `order:${o.id}`);
      closed.push(it.name);
    }
  }
  if (closed.length) await addNote(o.id, 'Đã thu hồi khóa học: ' + closed.join(', '));
}

/** Đổi trạng thái đơn và chạy các việc đi kèm. */
export async function setOrderStatus(orderId: number, status: string, note = '', author = 'system') {
  const o = await db.order.findUnique({ where: { id: orderId }, include: { items: true } });
  if (!o || o.status === status) return o;
  const updated = await db.order.update({
    where: { id: orderId },
    data: { status, ...(PAID.includes(status) && !o.paidAt ? { paidAt: new Date() } : {}) },
    include: { items: true },
  });
  await addNote(orderId, `Trạng thái đơn hàng đổi từ ${STATUS[o.status]?.[0] || o.status} thành ${STATUS[status]?.[0] || status}.${note ? ' ' + note : ''}`, author);
  if (PAID.includes(status)) {
    await grantOrder(updated);
    if (!PAID.includes(o.status)) await mailPaid(updated);
  } else if (['cancelled', 'refunded', 'failed'].includes(status)) {
    await revokeOrder(updated);
  }
  return updated;
}

/* ------------------------------------------------------------- Email */

function lines(o: Order & { items: OrderItem[] }) {
  return o.items.map((it) => `- ${it.name}: ${price(it.total)}`).join('\n');
}

/** Email khi vừa đặt hàng (kèm thông tin chuyển khoản). */
export async function mailNewOrder(o: Order & { items: OrderItem[] }) {
  const s = await getSettings();
  const site = process.env.SITE_URL || 'http://localhost:3000';
  let bank = '';
  if (o.paymentMethod === 'bacs') {
    bank = '\n\nThông tin chuyển khoản:\n' + s.bacs.accounts.map((a) => `${a.bank_name} – ${a.account_number} – ${a.account_name}`).join('\n') + `\nSố tiền: ${price(o.total)}\nNội dung: ${o.id}\n${s.bacs.instructions}`;
  }
  await sendMail(o.email, `[${opt(s, 'brand_name')}] Đã nhận đơn hàng #${o.id}`, `Chào ${orderName(o)},\n\nChúng tôi đã nhận đơn hàng #${o.id}:\n${lines(o)}\nTổng cộng: ${price(o.total)}${bank}\n\nXem đơn hàng: ${site}/tai-khoan/don-hang/${o.id}/`);
  await sendMail(opt(s, 'notify_email') || opt(s, 'email'), `[${opt(s, 'brand_name')}] Đơn hàng mới #${o.id} – ${price(o.total)}`, `${orderName(o)} (${o.phone} · ${o.email}) vừa đặt:\n${lines(o)}\nTổng: ${price(o.total)}\nThanh toán: ${o.paymentTitle}\n\nXem: ${site}/quan-tri/don-hang/${o.id}/`);
}

/** Email khi đơn đã thanh toán: link vào học. */
async function mailPaid(o: Order & { items: OrderItem[] }) {
  const s = await getSettings();
  const site = process.env.SITE_URL || 'http://localhost:3000';
  const courses = await db.course.findMany({ where: { id: { in: o.items.map((i) => i.courseId).filter((x): x is number => !!x) } } });
  const learn = courses.map((c) => `- ${c.title}: ${site}${learnUrl(c)}`).join('\n');
  await sendMail(o.email, `[${opt(s, 'brand_name')}] Khóa học đã được mở – đơn #${o.id}`, `Chào ${orderName(o)},\n\nĐơn hàng #${o.id} đã được thanh toán. Bạn vào học tại:\n${learn}\n\nHoặc xem tất cả khóa học của bạn: ${site}/tai-khoan/khoa-hoc-cua-toi/`);
}
