// Quản trị: xử lý thao tác (viết lại các hàm kh_qt_do_* của theme).
import type { User } from '@prisma/client';
import { randomBytes } from 'node:crypto';
import { db } from '../db';
import { digits, price } from '../html';
import { bundleIds, durationLabel, grantCourse, renewCourse, revokeCourse, viewsReset, accessInfo } from '../lms';
import { sendMail } from '../mail';
import { addNote, setOrderStatus } from '../orders';
import { hashPassword, verifyPassword } from '../passwords';
import { endOtherSessions } from '../auth';
import { getSettings, opt, OPTION_GROUPS, saveSetting } from '../settings';
import { curriculumClean, curriculumRead, curriculumWrite } from './pages-b';
import { moneyValue, qtUrl } from './ui';

export type Result = { ok: boolean; msg: string; go?: string };
type F = FormData;
const str = (f: F, k: string) => String(f.get(k) ?? '').trim();
const int = (f: F, k: string) => parseInt(str(f, k), 10) || 0;
const all = (f: F, k: string) => f.getAll(k).map((x) => parseInt(String(x), 10)).filter((x) => x > 0);
const isEmail = (e: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
const vnd = (t: Date | number) => new Intl.DateTimeFormat('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(t));

function splitName(full: string) {
  const parts = full.trim().split(/\s+/);
  const first = parts.pop() || '';
  return { firstName: first, lastName: parts.join(' ') };
}

async function resetLink(userId: number, email: string, name: string, subjectPrefix: string) {
  const s = await getSettings();
  const id = randomBytes(24).toString('hex');
  await db.passwordReset.deleteMany({ where: { userId } });
  await db.passwordReset.create({ data: { id, userId, expiresAt: new Date(Date.now() + 24 * 3600 * 1000) } });
  const site = process.env.SITE_URL || 'http://localhost:3000';
  const link = `${site}/tai-khoan/dat-lai-mat-khau/?key=${id}`;
  const sent = await sendMail(email, `[${opt(s, 'brand_name')}] ${subjectPrefix}`, `Chào ${name},\n\nBấm vào link sau để đặt mật khẩu (hiệu lực 24 giờ):\n${link}\n\nĐăng nhập tại: ${site}/tai-khoan/`);
  return { sent, link };
}

/** Thông báo sau khi gửi (hoặc chưa gửi được) email đặt mật khẩu. */
function mailNote(r: { sent: boolean; link: string }, email: string): string {
  if (r.sent) return ` Đã gửi email tới ${email}.`;
  const why = process.env.SMTP_HOST ? 'gửi email bị lỗi (xem Cài đặt → Email đã gửi)' : 'website chưa cài đặt gửi email (SMTP trong file .env)';
  return ` CHƯA gửi được email vì ${why}. Gửi link này cho học viên qua Zalo để họ tự đặt mật khẩu (hiệu lực 24 giờ): ${r.link}`;
}

export const ACTIONS: Record<string, (f: F, me: User) => Promise<Result>> = {
  /* ---------------- Đơn hàng */
  async order_status(f, me) {
    const id = int(f, 'order_id');
    const status = str(f, 'status');
    if (!['completed', 'cancelled', 'refunded', 'on-hold'].includes(status)) return { ok: false, msg: 'Không đổi được trạng thái đơn.' };
    const o = await setOrderStatus(id, status, `Đổi từ trang Quản trị (${me.name}).`, me.name);
    if (!o) return { ok: false, msg: 'Không tìm thấy đơn hàng.' };
    const msgs: Record<string, string> = {
      completed: `Đã xác nhận đơn #${id}. Khóa học đã được mở cho học viên.`,
      cancelled: `Đã hủy đơn #${id}.`,
      refunded: `Đã đánh dấu hoàn tiền đơn #${id} và khóa các khóa học trong đơn.`,
      'on-hold': `Đã mở lại đơn #${id} (chờ xác nhận).`,
    };
    return { ok: true, msg: msgs[status] };
  },
  async order_delete(f, me) {
    const id = int(f, 'order_id');
    const o = await db.order.findUnique({ where: { id }, include: { items: true } });
    if (!o) return { ok: false, msg: 'Không tìm thấy đơn hàng.', go: qtUrl('don-hang') };
    // Khóa lại các khóa học mở từ chính đơn này (khóa mở tay hoặc từ đơn khác giữ nguyên).
    let closed = 0;
    if (o.userId) {
      for (const it of o.items) {
        if (!it.courseId) continue;
        const cur = await db.access.findUnique({ where: { userId_courseId: { userId: o.userId, courseId: it.courseId } } });
        if (cur?.source === `order:${o.id}`) {
          await revokeCourse(o.userId, it.courseId, `order:${o.id}`);
          closed++;
        }
      }
    }
    await db.order.delete({ where: { id } });
    void me;
    return { ok: true, msg: `Đã xóa đơn #${id}${closed ? ` và khóa lại ${closed} khóa học mở từ đơn này` : ''}.`, go: qtUrl('don-hang') };
  },
  async order_note(f, me) {
    const id = int(f, 'order_id');
    const note = str(f, 'note').slice(0, 2000);
    if (!id || !note || !(await db.order.findUnique({ where: { id } }))) return { ok: false, msg: 'Chưa thêm được ghi chú.' };
    await addNote(id, note, me.name);
    return { ok: true, msg: 'Đã thêm ghi chú.' };
  },

  /* ---------------- Học viên */
  async student_create(f) {
    const name = str(f, 'name');
    const email = str(f, 'email').toLowerCase();
    const phone = str(f, 'phone');
    const pass = String(f.get('password') ?? '');
    if (!name || !isEmail(email)) return { ok: false, msg: 'Vui lòng nhập họ tên và email đúng.' };
    const other = await db.user.findUnique({ where: { email } });
    if (other) return { ok: false, msg: `Email này đã có tài khoản (${other.name}). Mở trang học viên đó để thêm khóa học.`, go: qtUrl('hoc-vien', other.id) };
    let login = email.split('@')[0].replace(/[^a-z0-9._-]/gi, '') || 'hocvien';
    for (let i = 2; await db.user.findUnique({ where: { login } }); i++) login = login.replace(/\d*$/, '') + i;
    const u = await db.user.create({ data: { login, email, name, phone, ...splitName(name), passwordHash: await hashPassword(pass || randomBytes(12).toString('hex')) } });
    let opened = 0;
    for (const cid of all(f, 'courses')) if (await grantCourse(u.id, cid, 'admin')) opened++;
    const mail = f.get('notify') ? await resetLink(u.id, email, name, 'Tài khoản học viên của bạn') : null;
    return { ok: !mail || mail.sent, msg: `Đã tạo học viên ${name}${opened ? ` và mở ${opened} khóa học` : ''}. Tên đăng nhập: “${login}”${pass ? '' : ' (chưa có mật khẩu – đặt mật khẩu ở khung “Đăng nhập của học viên”)'}.${mail ? mailNote(mail, email) : ''}`, go: qtUrl('hoc-vien', u.id) };
  },
  async student_update(f) {
    const id = int(f, 'user_id');
    const name = str(f, 'name');
    const email = str(f, 'email').toLowerCase();
    if (!id || !name || !isEmail(email)) return { ok: false, msg: 'Vui lòng nhập họ tên và email đúng.' };
    const other = await db.user.findUnique({ where: { email } });
    if (other && other.id !== id) return { ok: false, msg: 'Email này đang thuộc về tài khoản khác.' };
    await db.user.update({ where: { id }, data: { name, email, phone: str(f, 'phone'), ...splitName(name) } });
    return { ok: true, msg: 'Đã lưu thông tin học viên.' };
  },
  async student_reset(f) {
    const u = await db.user.findUnique({ where: { id: int(f, 'user_id') } });
    if (!u) return { ok: false, msg: 'Không gửi được email.' };
    const mail = await resetLink(u.id, u.email, u.name, 'Đặt lại mật khẩu');
    return { ok: mail.sent, msg: mail.sent ? `Đã gửi email đặt lại mật khẩu tới ${u.email}.` : mailNote(mail, u.email).trim() };
  },
  async student_delete(f, me) {
    const u = await db.user.findUnique({ where: { id: int(f, 'user_id') } });
    if (!u) return { ok: false, msg: 'Không tìm thấy học viên.' };
    if (u.role !== 'student' || u.id === me.id) return { ok: false, msg: 'Chỉ xóa được tài khoản học viên.' };
    // Xóa tài khoản: phiên đăng nhập, khóa học đã mở, tiến độ… tự xóa theo; đơn hàng giữ lại (không gắn tài khoản).
    await db.user.delete({ where: { id: u.id } });
    return { ok: true, msg: `Đã xóa học viên ${u.name} (${u.email}).`, go: qtUrl('hoc-vien') };
  },
  async student_setpass(f) {
    const u = await db.user.findUnique({ where: { id: int(f, 'user_id') } });
    if (!u) return { ok: false, msg: 'Không tìm thấy học viên.' };
    if (u.role !== 'student') return { ok: false, msg: 'Chỉ đặt mật khẩu cho tài khoản học viên ở đây.' };
    const pass = String(f.get('password') ?? '');
    if (pass.length < 6) return { ok: false, msg: 'Mật khẩu mới cần ít nhất 6 ký tự.' };
    await db.user.update({ where: { id: u.id }, data: { passwordHash: await hashPassword(pass) } });
    await db.session.deleteMany({ where: { userId: u.id } });
    await db.passwordReset.deleteMany({ where: { userId: u.id } });
    return { ok: true, msg: `Đã đặt mật khẩu mới cho ${u.name}. Báo cho học viên: tên đăng nhập “${u.login}” (hoặc email ${u.email}), mật khẩu “${pass}”.` };
  },
  async student_logout(f) {
    const u = await db.user.findUnique({ where: { id: int(f, 'user_id') } });
    if (!u) return { ok: false, msg: 'Không tìm thấy học viên.' };
    const r = await db.session.deleteMany({ where: { userId: u.id, kind: 'site' } });
    return { ok: true, msg: `Đã đăng xuất ${u.name} khỏi ${r.count} thiết bị.` };
  },
  async course_grant(f) {
    const uid = int(f, 'user_id');
    const ids = all(f, 'courses');
    if (!uid) return { ok: false, msg: 'Không tìm thấy học viên.' };
    if (!ids.length) return { ok: false, msg: 'Bạn chưa chọn khóa học nào.' };
    const names: string[] = [];
    for (const cid of ids) if (await grantCourse(uid, cid, 'admin')) names.push((await db.course.findUnique({ where: { id: cid } }))?.title || '');
    return names.length ? { ok: true, msg: `Đã mở: ${names.join(', ')}.` } : { ok: false, msg: 'Các khóa đã chọn đang còn hạn, không cần mở lại.' };
  },
  async course_renew(f) {
    const uid = int(f, 'user_id');
    const cid = int(f, 'course_id');
    await renewCourse(uid, cid);
    const a = await accessInfo(cid, uid);
    const c = await db.course.findUnique({ where: { id: cid } });
    return { ok: true, msg: `Đã gia hạn “${c?.title}”${a?.expires ? ' đến ' + vnd(a.expires) : ' (trọn đời)'}.` };
  },
  async course_views(f) {
    const uid = int(f, 'user_id');
    const c = await db.course.findUnique({ where: { id: int(f, 'course_id') } });
    if (!c) return { ok: false, msg: 'Không tìm thấy khóa học.' };
    for (const id of [c.id, ...bundleIds(c)]) await viewsReset(uid, id);
    return { ok: true, msg: `Đã đặt lại lượt xem “${c.title}”.` };
  },
  async course_revoke(f) {
    const uid = int(f, 'user_id');
    const c = await db.course.findUnique({ where: { id: int(f, 'course_id') } });
    if (!c) return { ok: false, msg: 'Không tìm thấy khóa học.' };
    await revokeCourse(uid, c.id);
    return { ok: true, msg: `Đã thu hồi “${c.title}”.` };
  },

  /* ---------------- Khóa học */
  async course_save(f) {
    let id = int(f, 'course_id');
    const title = str(f, 'title').slice(0, 200);
    if (!title) return { ok: false, msg: 'Vui lòng nhập tên khóa học.' };
    const existing = id ? await db.course.findUnique({ where: { id } }) : null;
    if (id && !existing) return { ok: false, msg: 'Không tìm thấy khóa học.' };
    const excerpt = str(f, 'excerpt').slice(0, 1000);
    const priceV = moneyValue(f.get('price'));
    const regularV = moneyValue(f.get('regular_price'));
    const dur = str(f, 'duration');
    const video = str(f, 'video');
    const thumb = str(f, 'thumbnail');
    const data = {
      title,
      excerpt,
      status: f.get('publish') ? 'publish' : 'draft',
      price: priceV === '' ? null : parseInt(priceV, 10),
      regularPrice: regularV === '' ? null : parseInt(regularV, 10),
      badge: str(f, 'badge').slice(0, 60),
      metaLine: str(f, 'meta_line').slice(0, 120),
      audience: str(f, 'audience').slice(0, 200),
      benefits: str(f, 'benefits').slice(0, 3000),
      video: /^https?:\/\//.test(video) ? video : '',
      featured: !!f.get('featured'),
      duration: ['', 'life', '3', '6', '12', '24'].includes(dur) ? dur : '',
      bundle: all(f, 'bundle').filter((x) => x !== id).join(','),
      thumbnail: thumb.startsWith('/') || /^https?:\/\//.test(thumb) ? thumb : '',
      ...(existing && existing.cover && thumb === existing.cover ? { thumbnail: '' } : {}),
    };
    if (!id) {
      const base = title.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'khoa-hoc';
      let slug = base;
      for (let i = 2; await db.course.findUnique({ where: { slug } }); i++) slug = `${base}-${i}`;
      const intro = [
        { name: 'core/heading', attrs: {}, inner: [], content: ['<h2 class="wp-block-heading">Giới thiệu khóa học</h2>'] },
        { name: 'core/paragraph', attrs: {}, inner: [], content: [`<p>${excerpt.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]!)}</p>`] },
      ];
      id = (await db.course.create({ data: { ...data, slug, blocks: JSON.stringify(intro) } })).id;
    } else {
      await db.course.update({ where: { id }, data });
    }
    // Danh mục.
    const cats = all(f, 'cats');
    const nc = str(f, 'new_cat');
    if (nc) {
      const slug = nc.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      const t = (await db.category.findUnique({ where: { slug } })) || (await db.category.create({ data: { name: nc, slug } }));
      cats.push(t.id);
    }
    await db.courseCategory.deleteMany({ where: { courseId: id } });
    for (const cid of [...new Set(cats)]) await db.courseCategory.create({ data: { courseId: id, categoryId: cid } });
    // Giáo trình.
    let warn = '';
    const cj = f.get('curriculum');
    if (cj !== null) {
      const chapters = curriculumClean(String(cj));
      const c = await db.course.findUniqueOrThrow({ where: { id } });
      if (chapters === null) warn = ' Giáo trình chưa được lưu (dữ liệu lỗi).';
      else if (curriculumRead(c.blocks).editable) await db.course.update({ where: { id }, data: { blocks: curriculumWrite(c.blocks, chapters) } });
    }
    return { ok: !warn, msg: (existing ? 'Đã lưu khóa học.' : 'Đã tạo khóa học.') + (data.status === 'publish' ? '' : ' Khóa đang ẩn, bật "Hiện trên website" khi sẵn sàng bán.') + warn, go: qtUrl('khoa-hoc', id) };
  },
  async course_trash(f) {
    const c = await db.course.findUnique({ where: { id: int(f, 'course_id') } });
    if (!c) return { ok: false, msg: 'Không tìm thấy khóa học.' };
    await db.course.delete({ where: { id: c.id } });
    return { ok: true, msg: `Đã xóa khóa “${c.title}”.`, go: qtUrl('khoa-hoc') };
  },

  /* ---------------- Tài khoản của tôi (người đang đăng nhập quản trị) */
  async me_save(f, me) {
    const name = str(f, 'name').slice(0, 100);
    const login = str(f, 'login');
    const email = str(f, 'email').toLowerCase();
    const p1 = String(f.get('pass1') ?? '');
    const p2 = String(f.get('pass2') ?? '');
    if (!(await verifyPassword(String(f.get('current') ?? ''), me.passwordHash)).ok) return { ok: false, msg: 'Mật khẩu hiện tại chưa đúng.' };
    if (!name) return { ok: false, msg: 'Vui lòng nhập tên hiển thị.' };
    if (!/^[A-Za-z0-9._-]{3,60}$/.test(login)) return { ok: false, msg: 'Tên đăng nhập cần 3–60 ký tự: chữ không dấu, số, dấu chấm, gạch dưới, gạch ngang.' };
    if (!isEmail(email)) return { ok: false, msg: 'Email chưa đúng.' };
    const dupLogin = await db.user.findUnique({ where: { login } });
    if (dupLogin && dupLogin.id !== me.id) return { ok: false, msg: 'Tên đăng nhập này đã có người dùng.' };
    const dupEmail = await db.user.findUnique({ where: { email } });
    if (dupEmail && dupEmail.id !== me.id) return { ok: false, msg: 'Email này đang thuộc về tài khoản khác.' };
    const data: Record<string, string> = { name, login, email, ...splitName(name) };
    if (p1 || p2) {
      if (p1.length < 6) return { ok: false, msg: 'Mật khẩu mới cần ít nhất 6 ký tự.' };
      if (p1 !== p2) return { ok: false, msg: 'Hai mật khẩu mới không khớp.' };
      data.passwordHash = await hashPassword(p1);
    }
    await db.user.update({ where: { id: me.id }, data });
    // Đổi mật khẩu: đăng xuất các thiết bị khác, giữ lại phiên quản trị đang dùng.
    if (data.passwordHash) await endOtherSessions(me.id, 'admin');
    return { ok: true, msg: data.passwordHash ? 'Đã lưu. Mật khẩu mới có hiệu lực, các thiết bị khác đã bị đăng xuất.' : 'Đã lưu thông tin tài khoản.', go: qtUrl('tai-khoan') };
  },

  /* ---------------- Liên hệ */
  async contact_done(f) {
    await db.contact.update({ where: { id: int(f, 'contact_id') }, data: { status: 'done' } }).catch(() => null);
    return { ok: true, msg: 'Đã chuyển liên hệ sang "Đã xử lý".' };
  },
  async contact_undo(f) {
    await db.contact.update({ where: { id: int(f, 'contact_id') }, data: { status: 'new' } }).catch(() => null);
    return { ok: true, msg: 'Đã chuyển về "Chưa xử lý".' };
  },
  async contact_trash(f) {
    await db.contact.delete({ where: { id: int(f, 'contact_id') } }).catch(() => null);
    return { ok: true, msg: 'Đã xóa liên hệ.' };
  },

  /* ---------------- Cài đặt (chỉ quản trị viên) */
  async settings_save(f, me) {
    if (me.role !== 'admin') return { ok: false, msg: 'Bạn không có quyền đổi cài đặt.' };
    const s = await getSettings();
    const tab = str(f, 'tab');
    const kh: Record<string, string> = { ...s.kh };
    for (const g of Object.values(OPTION_GROUPS)) {
      for (const [k, def] of Object.entries(g.fields)) {
        const raw = f.get('kh_' + k);
        if (raw === null) continue;
        let v = String(raw).trim();
        const type = def.type || 'text';
        if (type === 'checkbox') v = v === '1' ? '1' : '0';
        else if (type === 'number') v = String(Math.max(def.min ?? 0, Math.min(def.max ?? 1e9, parseInt(v, 10) || 0)));
        else if (type === 'select') v = def.options && v in def.options ? v : def.default;
        else if (type === 'color') v = /^#[0-9a-f]{6}$/i.test(v) ? v : def.default;
        else if (type === 'email') v = isEmail(v) ? v : '';
        kh[k] = v;
      }
    }
    // Lấy giá trị checkbox cuối cùng (ô ẩn "0" + ô tick "1").
    for (const g of Object.values(OPTION_GROUPS)) for (const [k, def] of Object.entries(g.fields)) if (def.type === 'checkbox') kh[k] = f.getAll('kh_' + k).includes('1') ? '1' : '0';
    const logo = str(f, 'kh_site_logo');
    kh.site_logo = logo.startsWith('/') || /^https?:\/\//.test(logo) ? logo : '';
    await saveSetting('kh', kh);
    const accounts = [0, 1, 2]
      .map((i) => ({ bank_name: str(f, `bacs_${i}_bank_name`), account_number: str(f, `bacs_${i}_account_number`), account_name: str(f, `bacs_${i}_account_name`) }))
      .filter((a) => a.bank_name || a.account_number);
    await saveSetting('bacs', { enabled: !!f.get('bacs_enabled'), instructions: str(f, 'bacs_instructions'), accounts });
    await saveSetting('payments', { vnpay: !!f.get('pay_vnpay'), momo: !!f.get('pay_momo') });
    void digits;
    void price;
    void durationLabel;
    return { ok: true, msg: 'Đã lưu cài đặt.', go: qtUrl('cai-dat', '', tab ? { tab } : {}) };
  },
};
