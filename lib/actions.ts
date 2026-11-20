// Xử lý form của học viên: tài khoản, giỏ hàng, đặt hàng.
// Form gửi tới /api/form/<tên>/ (app/api/form/[action]/route.ts); xử lý xong chuyển trang (tải lại cả trang
// để public/js/app.js chạy lại, giống WordPress).
import { headers } from 'next/headers';
import { randomBytes } from 'node:crypto';
import { authenticate, endSession, getCurrentUser, startSession } from './auth';
import { cartCourses, cartIds, saveCart } from './cart';
import { db } from './db';
import { digits } from './html';
import { hasAccess } from './lms';
import { sendMail } from './mail';
import { addNote, mailNewOrder, newRef, orderKey, PAYMENT_TITLES, setOrderStatus } from './orders';
import { hashPassword } from './passwords';
import { momoCreate, momoReady, vnpayReady, vnpayUrl } from './payments';
import { getSettings, opt } from './settings';

const str = (f: FormData, k: string) => String(f.get(k) ?? '').trim();

/** Chuyển trang sau khi xử lý form. */
export class Go {
  constructor(public url: string) {}
}
function redirect(url: string): never {
  throw new Go(url);
}

/** Chỉ cho quay lại trang trong website (không phải trang quản trị). */
function safeBack(v: string, def = '/'): string {
  return v.startsWith('/') && !v.startsWith('//') && !v.startsWith('/quan-tri') ? v : def;
}

async function ip(): Promise<string> {
  const h = await headers();
  return h.get('x-forwarded-for')?.split(',')[0].trim() || h.get('x-real-ip') || '127.0.0.1';
}

const fails = new Map<string, { n: number; at: number }>();

/* ============================================================ Tài khoản */

export async function loginAction(f: FormData) {
  const back = safeBack(str(f, 'redirect_to'), '/');
  const key = await ip();
  const rec = fails.get(key);
  const locked = rec && rec.n >= 5 && Date.now() - rec.at < 15 * 60 * 1000;
  const qs = (tb: string) => `/tai-khoan/?tb=${tb}${back !== '/' ? '&redirect_to=' + encodeURIComponent(back) : ''}`;
  if (locked) redirect(qs('khoa'));
  const user = await authenticate(str(f, 'username'), String(f.get('password') ?? ''));
  if (!user) {
    fails.set(key, { n: (rec && Date.now() - rec.at < 15 * 60 * 1000 ? rec.n : 0) + 1, at: Date.now() });
    redirect(qs('sai'));
  }
  fails.delete(key);
  await startSession(user.id, !!f.get('rememberme'));
  redirect(user.role === 'student' ? back : back === '/' ? '/quan-tri/' : back);
}

export async function registerAction(f: FormData) {
  const back = safeBack(str(f, 'redirect_to'), '/');
  const name = str(f, 'kh_full_name');
  const phone = str(f, 'kh_phone');
  const email = str(f, 'email').toLowerCase();
  const pass = String(f.get('password') ?? '');
  const err = (tb: string) => redirect(`/tai-khoan/?tb=${tb}&dk=1${back !== '/' ? '&redirect_to=' + encodeURIComponent(back) : ''}`);
  if (!name) err('thieu-ten');
  const d = digits(phone);
  if (d.length < 9 || d.length > 12) err('sai-sdt');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) err('sai-email');
  if (pass.length < 6) err('mk-ngan');
  if (await db.user.findUnique({ where: { email } })) err('trung-email');

  const parts = name.split(/\s+/);
  const firstName = parts.pop() || '';
  let login = email.split('@')[0].replace(/[^a-z0-9._-]/gi, '') || 'hocvien';
  for (let i = 2; await db.user.findUnique({ where: { login } }); i++) login = login.replace(/\d*$/, '') + i;
  const user = await db.user.create({ data: { login, email, name, firstName, lastName: parts.join(' '), phone, passwordHash: await hashPassword(pass) } });
  await startSession(user.id, true);
  redirect(back);
}

export async function logoutAction() {
  await endSession();
  redirect('/');
}

export async function lostPasswordAction(f: FormData) {
  const login = str(f, 'user_login');
  if (login) {
    const user = await db.user.findFirst({ where: { OR: [{ email: login.toLowerCase() }, { login }] } });
    if (user) {
      const id = randomBytes(24).toString('hex');
      await db.passwordReset.deleteMany({ where: { userId: user.id } });
      await db.passwordReset.create({ data: { id, userId: user.id, expiresAt: new Date(Date.now() + 60 * 60 * 1000) } });
      const s = await getSettings();
      const site = process.env.SITE_URL || 'http://localhost:3000';
      await sendMail(user.email, `[${opt(s, 'brand_name')}] Đặt lại mật khẩu`, `Chào ${user.name},\n\nBấm vào link sau để đặt mật khẩu mới (hiệu lực 1 giờ):\n${site}/tai-khoan/dat-lai-mat-khau/?key=${id}\n\nNếu bạn không yêu cầu, hãy bỏ qua email này.`);
    }
  }
  redirect('/tai-khoan/quen-mat-khau/?tb=da-gui');
}

export async function resetPasswordAction(f: FormData) {
  const key = str(f, 'key');
  const p1 = String(f.get('password_1') ?? '');
  const p2 = String(f.get('password_2') ?? '');
  const r = await db.passwordReset.findUnique({ where: { id: key } });
  if (!r || r.expiresAt.getTime() < Date.now()) redirect('/tai-khoan/quen-mat-khau/?tb=het-han');
  if (p1.length < 6) redirect(`/tai-khoan/dat-lai-mat-khau/?key=${key}&tb=mk-ngan`);
  if (p1 !== p2) redirect(`/tai-khoan/dat-lai-mat-khau/?key=${key}&tb=mk-khac`);
  await db.user.update({ where: { id: r.userId }, data: { passwordHash: await hashPassword(p1) } });
  await db.passwordReset.deleteMany({ where: { userId: r.userId } });
  await db.session.deleteMany({ where: { userId: r.userId } });
  redirect('/tai-khoan/?tb=da-doi-mk');
}

export async function updateAccountAction(f: FormData) {
  const user = await getCurrentUser();
  if (!user) redirect('/tai-khoan/');
  const name = str(f, 'account_display_name');
  const email = str(f, 'account_email').toLowerCase();
  const phone = str(f, 'account_phone');
  const cur = String(f.get('password_current') ?? '');
  const p1 = String(f.get('password_1') ?? '');
  const p2 = String(f.get('password_2') ?? '');
  const err = (tb: string) => redirect(`/tai-khoan/thong-tin/?tb=${tb}`);
  if (!name) err('thieu-ten');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) err('sai-email');
  const other = await db.user.findUnique({ where: { email } });
  if (other && other.id !== user.id) err('trung-email');
  const data: Record<string, string> = { name, email, phone };
  const parts = name.split(/\s+/);
  data.firstName = parts.pop() || '';
  data.lastName = parts.join(' ');
  if (p1 || p2 || cur) {
    if (!(await authenticate(user.email, cur))) err('sai-mk-cu');
    if (p1.length < 6) err('mk-ngan');
    if (p1 !== p2) err('mk-khac');
    data.passwordHash = await hashPassword(p1);
  }
  await db.user.update({ where: { id: user.id }, data });
  redirect('/tai-khoan/thong-tin/?tb=da-luu');
}

/* ============================================================ Giỏ hàng */

export async function cartRemoveAction(f: FormData) {
  const id = parseInt(str(f, 'course'), 10);
  await saveCart((await cartIds()).filter((x) => x !== id));
  redirect('/gio-hang/?tb=da-bo');
}

/* ============================================================ Đặt hàng */

export async function placeOrderAction(f: FormData) {
  const user = await getCurrentUser();
  if (!user) redirect('/thanh-toan/');
  const s = await getSettings();
  const courses = [];
  for (const c of await cartCourses()) if (!(await hasAccess(c.id, user))) courses.push(c);
  if (!courses.length) redirect('/gio-hang/?tb=trong');

  const lastName = str(f, 'billing_last_name');
  const firstName = str(f, 'billing_first_name');
  const phone = str(f, 'billing_phone');
  const email = str(f, 'billing_email').toLowerCase() || user.email;
  const method = str(f, 'payment_method');
  const err = (tb: string) => redirect(`/thanh-toan/?tb=${tb}`);
  if (!firstName || !lastName) err('thieu-ten');
  if (digits(phone).length < 9) err('sai-sdt');
  const allowed = [s.bacs.enabled && 'bacs', s.payments.vnpay && vnpayReady() && 'vnpay', s.payments.momo && momoReady() && 'momo'].filter(Boolean);
  const total = courses.reduce((a, c) => a + (c.price || 0), 0);
  if (total > 0 && !allowed.includes(method)) err('chon-tt');

  const pm = total > 0 ? method : 'free';
  const order = await db.order.create({
    data: {
      userId: user.id, status: 'pending', total, paymentMethod: pm, paymentTitle: PAYMENT_TITLES[pm], firstName, lastName, email, phone,
      note: str(f, 'order_comments').slice(0, 1000),
      items: { create: courses.map((c) => ({ name: c.title, courseId: c.id, total: c.price || 0 })) },
    },
    include: { items: true },
  });
  await addNote(order.id, `Đơn hàng được tạo, thanh toán: ${order.paymentTitle}.`);
  await db.user.update({ where: { id: user.id }, data: { firstName, lastName, phone } });
  await saveCart([]);
  const done = `/thanh-toan/da-dat/${order.id}/?key=${orderKey(order)}`;

  if (pm === 'free') {
    await setOrderStatus(order.id, 'completed', 'Khóa học miễn phí.');
    redirect(done);
  }
  if (pm === 'bacs') {
    await setOrderStatus(order.id, 'on-hold', 'Chờ chuyển khoản.');
    await mailNewOrder(order);
    redirect(done);
  }
  const ref = newRef(pm === 'vnpay' ? 'V' : 'M') + order.id;
  await db.order.update({ where: { id: order.id }, data: { paymentRef: ref } });
  await mailNewOrder(order);
  if (pm === 'vnpay') redirect(vnpayUrl(order, await ip(), ref));
  const momo = await momoCreate(order, ref);
  if (!momo.ok) {
    await addNote(order.id, 'Tạo giao dịch MoMo lỗi: ' + momo.message);
    redirect(`${done}&loi=${encodeURIComponent(momo.message)}`);
  }
  redirect(momo.payUrl!);
}

/** Thanh toán lại một đơn chưa trả (VNPay / MoMo). */
export async function payAgainAction(f: FormData) {
  const user = await getCurrentUser();
  const order = await db.order.findUnique({ where: { id: parseInt(str(f, 'order'), 10) } });
  if (!user || !order || order.userId !== user.id || !['pending', 'failed'].includes(order.status)) redirect('/tai-khoan/don-hang/');
  const ref = newRef(order.paymentMethod === 'vnpay' ? 'V' : 'M') + order.id;
  await db.order.update({ where: { id: order.id }, data: { paymentRef: ref } });
  if (order.paymentMethod === 'vnpay') redirect(vnpayUrl(order, await ip(), ref));
  const momo = await momoCreate(order, ref);
  if (!momo.ok) redirect(`/thanh-toan/da-dat/${order.id}/?key=${orderKey(order)}&loi=${encodeURIComponent(momo.message)}`);
  redirect(momo.payUrl!);
}
