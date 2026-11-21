// Quản trị: Khóa học, Liên hệ, Cài đặt (viết lại từ inc/quantri/courses.php, contacts.php, settings.php).
import type { User } from '@prisma/client';
import { db } from '../db';
import { esc, price, vnDate } from '../html';
import { type Block, courseLessons, courseUrl, durationLabel, parseBlocks } from '../lms';
import { momoReady, vnpayReady } from '../payments';
import { COURSE_FIELDS, getSettings, OPTION_GROUPS } from '../settings';
import { actionButton, ago, badge, card, type AdminPage, empty, field, formFields, pager, phoneLinks, qi, qtUrl } from './ui';

type Q = Record<string, string>;

/* ============================================================ Giáo trình */

export type Chapter = { title: string; lessons: { title: string; duration: string; type: 'video' | 'doc'; video: string; free: boolean }[] };

/** Đọc giáo trình (chương / bài) từ khối nội dung. */
export function curriculumRead(blocksJson: string): { chapters: Chapter[]; editable: boolean } {
  const blocks = parseBlocks(blocksJson);
  const chapters: Chapter[] = [];
  let top = 0;
  const lesson = (a: Record<string, unknown>) => {
    const video = String(a.video ?? '').trim();
    const legacy = String(a.url ?? '').trim();
    return { title: String(a.title ?? '').replace(/<[^>]*>/g, ''), duration: String(a.duration ?? ''), type: (a.type === 'doc' ? 'doc' : 'video') as 'video' | 'doc', video: video || legacy, free: !!a.free || (!video && !!legacy) };
  };
  for (const b of blocks) {
    if (b.name === 'kh/chapter') {
      const ch: Chapter = { title: String(b.attrs.title ?? '').replace(/<[^>]*>/g, ''), lessons: [] };
      for (const ib of b.inner) if (ib.name === 'kh/lesson') ch.lessons.push(lesson(ib.attrs));
      top += ch.lessons.length;
      chapters.push(ch);
    } else if (b.name === 'kh/lesson') {
      if (!chapters.length || chapters[chapters.length - 1].title !== '') chapters.push({ title: '', lessons: [] });
      chapters[chapters.length - 1].lessons.push(lesson(b.attrs));
      top++;
    }
  }
  const all = Object.keys(courseLessons({ blocks: blocksJson, video: '' })).length;
  return { chapters, editable: all === top };
}

/** Ghi giáo trình mới vào khối nội dung (giữ phần giới thiệu). */
export function curriculumWrite(blocksJson: string, chapters: Chapter[]): string {
  const neu: Block[] = chapters.map((ch, i) => {
    const inner: Block[] = ch.lessons.map((l) => {
      const attrs: Record<string, unknown> = { title: l.title };
      if (l.duration) attrs.duration = l.duration;
      if (l.type === 'doc') attrs.type = 'doc';
      if (l.video) attrs.video = l.video;
      if (l.free) attrs.free = true;
      return { name: 'kh/lesson', attrs, inner: [], content: [] };
    });
    const cattrs: Record<string, unknown> = { title: ch.title || `Chương ${i + 1}` };
    if (i === 0) cattrs.open = true;
    return { name: 'kh/chapter', attrs: cattrs, inner, content: ['\n', ...inner.flatMap(() => [null, '\n'])] };
  });
  const out: Block[] = [];
  let placed = false;
  for (const b of parseBlocks(blocksJson)) {
    if (b.name === 'kh/chapter' || b.name === 'kh/lesson') {
      if (!placed) {
        out.push(...neu);
        placed = true;
      }
      continue;
    }
    out.push(b);
  }
  if (!placed) {
    if (!blocksJson.includes('Nội dung khóa học') && neu.length) out.push({ name: 'core/heading', attrs: {}, inner: [], content: ['<h2 class="wp-block-heading">Nội dung khóa học</h2>'] });
    out.push(...neu);
  }
  return JSON.stringify(out);
}

/** Làm sạch giáo trình trình duyệt gửi lên. */
export function curriculumClean(json: string): Chapter[] | null {
  let data: unknown;
  try {
    data = JSON.parse(json);
  } catch {
    return null;
  }
  if (!Array.isArray(data)) return null;
  const t = (v: unknown) => String(v ?? '').replace(/<[^>]*>/g, '').trim().slice(0, 200);
  const out: Chapter[] = [];
  for (const ch of data as Record<string, unknown>[]) {
    const lessons: Chapter['lessons'] = [];
    for (const l of (Array.isArray(ch?.lessons) ? ch.lessons : []) as Record<string, unknown>[]) {
      const title = t(l.title);
      const video = String(l.video ?? '').trim();
      if (!title && !video) continue;
      if (video && !/^(https?:\/\/|#|<iframe)/i.test(video)) continue;
      lessons.push({ title: title || `Bài ${lessons.length + 1}`, duration: t(l.duration).slice(0, 12), type: l.type === 'doc' ? 'doc' : 'video', video: video.slice(0, 2000), free: !!l.free });
    }
    const title = t(ch?.title);
    if (!title && !lessons.length) continue;
    out.push({ title, lessons });
  }
  return out;
}

/* ============================================================ Khóa học */

export async function pageCourses(id: string): Promise<AdminPage> {
  if (id === 'moi') return courseEdit(0);
  if (id) return courseEdit(parseInt(id, 10));
  const s = await getSettings();
  const courses = await db.course.findMany({ orderBy: [{ sortOrder: 'asc' }, { title: 'asc' }] });
  const add = `<a class="qt-btn qt-btn-sm" href="${qtUrl('khoa-hoc', 'moi')}">${qi('plus', 16)} <span>Thêm khóa học</span></a>`;
  if (!courses.length) return { title: 'Khóa học', actions: add, html: card('', empty('Chưa có khóa học nào.', 'book-open', add)) };
  const counts: Record<number, number> = {};
  const users = await db.access.findMany({ select: { userId: true } });
  const { accessList } = await import('../lms');
  for (const uid of new Set(users.map((u) => u.userId))) for (const [cid, a] of Object.entries(await accessList(uid))) if (a.active) counts[+cid] = (counts[+cid] || 0) + 1;
  let html = '<div class="qt-course-grid">';
  for (const c of courses) {
    const lessons = Object.keys(courseLessons(c)).length;
    const bundle = c.bundle ? c.bundle.split(',').filter(Boolean).length : 0;
    const thumb = c.thumbnail || c.cover;
    const del = actionButton('course_trash', { course_id: c.id }, qi('trash-2', 15) + ' Xóa', 'qt-btn qt-btn-ghost qt-btn-sm is-danger', `Xóa khóa “${c.title}”? Học viên đã mua sẽ không vào học được nữa. Không khôi phục được.`);
    html += `<div class="qt-course-item"><a class="qt-course-card" href="${qtUrl('khoa-hoc', c.id)}"><div class="qt-course-thumb">${thumb ? `<img src="${esc(thumb)}" alt="" loading="lazy">` : qi('book-open', 32)}${c.status === 'publish' ? badge('Đang hiện', 'ok') : badge('Đang ẩn', 'mute')}</div><div class="qt-course-body"><h3>${esc(c.title)}</h3><p class="qt-course-price"><strong>${esc(c.price ? price(c.price) : 'Miễn phí')}</strong>${(c.regularPrice || 0) > (c.price || 0) ? ` <del>${esc(price(c.regularPrice))}</del>` : ''}</p><p class="qt-course-meta"><span>${qi('play', 14)}${esc(bundle ? `${bundle} khóa trong combo` : `${lessons} bài học`)}</span><span>${qi('users', 14)}${counts[c.id] || 0} học viên</span><span>${qi('clock', 14)}${esc(durationLabel(c, s))}</span></p></div></a><div class="qt-course-tools"><a class="qt-btn qt-btn-ghost qt-btn-sm" href="${qtUrl('khoa-hoc', c.id)}">${qi('pencil', 15)} Sửa</a>${del}</div></div>`;
  }
  return { title: 'Khóa học', actions: add, html: html + '</div>' };
}

async function courseEdit(id: number): Promise<AdminPage> {
  const c = id ? await db.course.findUnique({ where: { id }, include: { categories: true } }) : null;
  if (id && !c) return { title: 'Không tìm thấy', back: qtUrl('khoa-hoc'), html: empty('Khóa học không tồn tại.', 'book-open'), status: 404 };
  const s = await getSettings();
  const cur = c ? curriculumRead(c.blocks) : { chapters: [{ title: 'Chương 1', lessons: [{ title: 'Bài 1', duration: '', type: 'video' as const, video: '', free: true }] }], editable: true };
  let form = `<form method="post" action="/quan-tri/xu-ly/" class="qt-form qt-course-form" data-qt-dirty>${formFields('course_save')}<input type="hidden" name="course_id" value="${id}">`;
  let main = card(
    'Thông tin khóa học',
    field('title', 'Tên khóa học', c?.title || '', { required: true, placeholder: 'VD: Excel từ cơ bản tới nâng cao' }) +
      field('excerpt', 'Mô tả ngắn', c?.excerpt || '', { type: 'textarea', help: 'Hiện trên thẻ khóa học và đầu trang khóa học. Khoảng 1–2 câu.' }) +
      field('audience', COURSE_FIELDS.audience.label, c?.audience || '', { help: COURSE_FIELDS.audience.help }) +
      field('benefits', COURSE_FIELDS.benefits.label, c?.benefits || '', { type: 'textarea', help: COURSE_FIELDS.benefits.help }) +
      field('video', 'Video giới thiệu (link YouTube)', c?.video || '', { type: 'url', placeholder: 'https://www.youtube.com/watch?v=…' }),
  );
  let cbody: string;
  if (cur.editable) {
    cbody = `<p class="qt-help">${qi('info', 14)} Tiến độ học viên lưu theo <strong>thứ tự bài</strong>. Khóa đã có học viên: nên thêm bài mới vào cuối, hạn chế xóa hoặc đổi chỗ bài cũ. Bài 1 luôn được học thử miễn phí.</p><input type="hidden" name="curriculum" value="${esc(JSON.stringify(cur.chapters))}" data-qt-curriculum-input><div class="qt-curriculum" data-qt-curriculum></div><noscript><p>Cần bật JavaScript để sửa giáo trình.</p></noscript>`;
  } else {
    cbody = `<p class="qt-note">${qi('info', 16)}<span>Giáo trình của khóa này nằm trong một khối đặc biệt nên chưa sửa được ở đây.</span></p>`;
  }
  const count = cur.chapters.reduce((a, ch) => a + ch.lessons.length, 0);
  main += card('Giáo trình', cbody, `<span class="qt-muted" data-qt-curriculum-count>${count} bài</span>`);

  const dur = COURSE_FIELDS.duration.options || {};
  const durOpts: Record<string, string> = { '': `Theo cài đặt chung (${durationLabel(null, s)})` };
  for (const [k, v] of Object.entries(dur)) if (k !== '') durOpts[k] = v;
  let side = card(
    'Bán khóa học',
    field('publish', 'Hiện trên website', c?.status === 'publish', { type: 'checkbox' }) +
      field('price', 'Học phí', c?.price ?? '', { type: 'money', help: 'Để trống = miễn phí.' }) +
      field('regular_price', 'Giá gốc (gạch ngang)', c?.regularPrice ?? '', { type: 'money', help: 'Để trống nếu không giảm giá.' }) +
      field('duration', 'Thời hạn học', c?.duration || '', { type: 'select', options: durOpts }),
  );
  side += card('Ảnh khóa học', field('thumbnail', 'Ảnh đại diện (tỉ lệ 16:9)', c?.thumbnail || c?.cover || '', { type: 'image' }));
  const cats = await db.category.findMany({ orderBy: { name: 'asc' } });
  const own = new Set((c?.categories || []).map((x) => x.categoryId));
  const catHtml = `<div class="qt-checks is-compact">${cats.map((t) => `<label class="qt-check"><input type="checkbox" name="cats" value="${t.id}"${own.has(t.id) ? ' checked' : ''}><span>${esc(t.name)}</span></label>`).join('')}</div>` + field('new_cat', 'Thêm danh mục mới', '', { placeholder: 'VD: PowerPoint' });
  side += card('Hiển thị', field('featured', 'Khóa nổi bật (thẻ lớn ở trang chủ)', !!c?.featured, { type: 'checkbox' }) + field('badge', 'Nhãn nhỏ', c?.badge || '', { placeholder: 'VD: Khóa mới' }) + field('meta_line', 'Dòng thông tin', c?.metaLine || '', { placeholder: 'VD: 12 chương · 121 bài · ~16 giờ' }) + `<div class="qt-field"><label>Danh mục</label>${catHtml}</div>`);
  const picked = new Set((c?.bundle || '').split(',').map(Number).filter(Boolean));
  const others = (await db.course.findMany({ orderBy: [{ sortOrder: 'asc' }, { title: 'asc' }] })).filter((o) => o.id !== id);
  const combo = `<p class="qt-help">Chỉ dùng cho khóa Combo: học viên mua combo được học tất cả các khóa chọn ở đây.</p><div class="qt-checks is-compact">${others.map((o) => `<label class="qt-check"><input type="checkbox" name="bundle" value="${o.id}"${picked.has(o.id) ? ' checked' : ''}><span>${esc(o.title)}</span></label>`).join('')}</div>`;
  side += `<details class="qt-card qt-collapse"${picked.size ? ' open' : ''}><summary><h2>Combo gồm các khóa</h2>${qi('chevron-down', 18)}</summary><div class="qt-card-body">${combo}</div></details>`;

  form += `<div class="qt-grid is-wide-left"><div class="qt-stack">${main}</div><div class="qt-stack">${side}</div></div>`;
  form += `<div class="qt-savebar"><span class="qt-muted" data-qt-dirty-note>Chưa có thay đổi</span><button class="qt-btn" type="submit">${qi('save', 18)} ${id ? 'Lưu khóa học' : 'Tạo khóa học'}</button></div></form>`;
  if (c) form += `<div class="qt-danger-zone">${actionButton('course_trash', { course_id: id }, qi('trash-2', 15) + ' Xóa khóa học', 'qt-btn qt-btn-ghost qt-btn-sm is-danger', `Xóa khóa “${c.title}”? Học viên đã mua sẽ không vào học được nữa. Không khôi phục được.`)}</div>`;
  return {
    title: c ? c.title : 'Thêm khóa học',
    back: qtUrl('khoa-hoc'),
    html: form,
    actions: c ? `<a class="qt-btn qt-btn-ghost qt-btn-sm" href="${esc(courseUrl(c))}" target="_blank" rel="noopener">${qi('eye', 16)} <span>Xem trang khóa học</span></a>` : '',
  };
}

/* ============================================================ Liên hệ */

export async function pageContacts(q: Q): Promise<AdminPage> {
  const f = q.loc === 'da-xu-ly' ? 'da-xu-ly' : '';
  const status = f ? 'done' : 'new';
  const page = Math.max(1, parseInt(q.trang, 10) || 1);
  const [nNew, nDone, total, list] = await Promise.all([
    db.contact.count({ where: { status: 'new' } }),
    db.contact.count({ where: { status: 'done' } }),
    db.contact.count({ where: { status } }),
    db.contact.findMany({ where: { status }, orderBy: { createdAt: 'desc' }, skip: (page - 1) * 20, take: 20 }),
  ]);
  let html = `<div class="qt-toolbar"><nav class="qt-tabs"><a class="${f ? '' : 'is-active'}" href="${qtUrl('lien-he')}">Chưa xử lý <em>${nNew}</em></a><a class="${f ? 'is-active' : ''}" href="${qtUrl('lien-he', '', { loc: 'da-xu-ly' })}">Đã xử lý <em>${nDone}</em></a></nav></div>`;
  if (!list.length) return { title: 'Liên hệ', html: html + card('', empty(f ? 'Chưa có liên hệ nào đã xử lý.' : 'Không còn liên hệ nào cần xử lý.', 'circle-check')) };
  let li = '<ul class="qt-inbox">';
  for (const c of list) {
    const isNew = c.status === 'new';
    li += `<li class="${isNew ? 'is-new' : ''}"><details><summary><div class="qt-inbox-who"><strong>${esc(c.name)}</strong>${c.topic ? badge(c.topic, 'info') : ''}</div><div class="qt-inbox-msg">${esc(c.message ? c.message.split(/\s+/).slice(0, 18).join(' ') : '(không có lời nhắn)')}</div><div class="qt-inbox-time qt-muted">${esc(ago(c.createdAt))}</div></summary><div class="qt-inbox-body">`;
    li += `<dl class="qt-dl"><dt>Điện thoại</dt><dd>${esc(c.phone || '—')} ${phoneLinks(c.phone)}</dd><dt>Email</dt><dd>${c.email ? `<a href="mailto:${esc(c.email)}">${esc(c.email)}</a>` : '—'}</dd>${c.topic ? `<dt>Quan tâm</dt><dd>${esc(c.topic)}</dd>` : ''}<dt>Lời nhắn</dt><dd>${esc(c.message || '—').replace(/\n/g, '<br>')}</dd><dt>Gửi lúc</dt><dd>${esc(vnDate(c.createdAt, true))}${c.source ? ` · từ trang <a href="${esc(c.source)}" target="_blank" rel="noopener">${esc(c.source)}</a>` : ''}</dd></dl><div class="qt-btn-row">`;
    li += isNew ? actionButton('contact_done', { contact_id: c.id }, qi('check', 16) + ' Đã xử lý', 'qt-btn qt-btn-sm') : actionButton('contact_undo', { contact_id: c.id }, qi('rotate-ccw', 15) + ' Chuyển về chưa xử lý', 'qt-btn qt-btn-ghost qt-btn-sm');
    if (c.email && !(await db.user.findUnique({ where: { email: c.email.toLowerCase() } }))) li += `<a class="qt-btn qt-btn-ghost qt-btn-sm" href="${esc(qtUrl('hoc-vien', 'moi', { ten: c.name, email: c.email, sdt: c.phone }))}">${qi('user-plus', 15)} Tạo tài khoản học viên</a>`;
    li += actionButton('contact_trash', { contact_id: c.id }, qi('trash-2', 15) + ' Xóa', 'qt-btn qt-btn-ghost qt-btn-sm is-danger', `Xóa liên hệ của ${c.name}?`) + '</div></div></details></li>';
  }
  html += card('', li + '</ul>' + pager(page, Math.ceil(total / 20), qtUrl('lien-he', '', f ? { loc: f } : {})), '', 'is-flush');
  return { title: 'Liên hệ', html };
}

/* ============================================================ Cài đặt */

export async function pageSettings(q: Q): Promise<AdminPage> {
  const s = await getSettings();
  const tabs: Record<string, string> = { 'chuyen-khoan': 'Thanh toán' };
  for (const [k, g] of Object.entries(OPTION_GROUPS)) tabs[k] = g.label;
  tabs.email = 'Email đã gửi';
  const active = tabs[q.tab] ? q.tab : 'chuyen-khoan';
  let form = `<form method="post" action="/quan-tri/xu-ly/" class="qt-form qt-settings" data-qt-dirty>${formFields('settings_save')}<input type="hidden" name="tab" value="${esc(active)}" data-qt-tab-input><nav class="qt-tabs is-scroll" data-qt-tabs>`;
  form += Object.entries(tabs).map(([k, l]) => `<a href="${qtUrl('cai-dat', '', { tab: k })}" data-tab="${k}" class="${k === active ? 'is-active' : ''}">${esc(l)}</a>`).join('') + '</nav>';

  // Thanh toán.
  let pay = field('bacs_enabled', 'Cho học viên thanh toán bằng chuyển khoản ngân hàng', s.bacs.enabled, { type: 'checkbox' });
  pay += '<p class="qt-help">Thông tin này hiện cho học viên ở trang "Đã nhận đơn hàng" (kèm mã QR) và trong email đơn hàng.</p>';
  for (let i = 0; i < 3; i++) {
    const a = s.bacs.accounts[i] || { bank_name: '', account_number: '', account_name: '' };
    pay += `<fieldset class="qt-fieldset"><legend>Tài khoản ${i + 1}${i ? ' (không bắt buộc)' : ''}</legend><div class="qt-form-grid is-3">${field(`bacs_${i}_bank_name`, 'Ngân hàng', a.bank_name, { placeholder: 'VD: Vietcombank' })}${field(`bacs_${i}_account_number`, 'Số tài khoản', a.account_number, { attrs: 'inputmode="numeric"' })}${field(`bacs_${i}_account_name`, 'Chủ tài khoản', a.account_name, { placeholder: 'VD: NGUYEN VAN A' })}</div></fieldset>`;
  }
  pay += field('bacs_instructions', 'Lời nhắn sau khi đặt mua', s.bacs.instructions, { type: 'textarea', placeholder: 'VD: Chuyển khoản với nội dung là mã đơn hàng. Khóa học được mở ngay khi chúng tôi nhận được học phí.' });
  pay += '<hr class="qt-hr">';
  pay += field('pay_vnpay', 'Cho thanh toán qua VNPay', s.payments.vnpay, { type: 'checkbox', help: vnpayReady() ? 'Đã có mã kết nối VNPay.' : 'Chưa có mã kết nối: điền VNPAY_TMN_CODE và VNPAY_HASH_SECRET trong file .env (đăng ký sandbox miễn phí tại sandbox.vnpayment.vn).' });
  pay += field('pay_momo', 'Cho thanh toán qua ví MoMo', s.payments.momo, { type: 'checkbox', help: momoReady() ? 'Đã có mã kết nối MoMo (file .env).' : 'Chưa có mã kết nối MoMo trong file .env.' });
  form += `<div class="qt-tab-panel" data-panel="chuyen-khoan"${active === 'chuyen-khoan' ? '' : ' hidden'}>${card('Thanh toán', pay)}</div>`;

  // Nhóm cài đặt website.
  for (const [key, g] of Object.entries(OPTION_GROUPS)) {
    let body = '';
    for (const [fk, f] of Object.entries(g.fields)) {
      const type = f.type || 'text';
      const v = s.kh[fk] ?? '';
      if (type === 'checkbox') body += `<input type="hidden" name="kh_${fk}" value="0">`;
      const o: Parameters<typeof field>[3] = { type: type === 'url' ? 'text' : type, help: f.help || '', options: f.options };
      if (type === 'url') o.attrs = 'inputmode="url" placeholder="https://… hoặc /duong-dan/"';
      if (type === 'number') o.attrs = `min="${f.min ?? 0}"${f.max !== undefined ? ` max="${f.max}"` : ''}`;
      body += field(`kh_${fk}`, f.label, type === 'checkbox' ? v === '1' : v, o);
    }
    if (key === 'brand') body = field('kh_site_logo', 'Ảnh logo (để trống: dùng chữ trong logo)', s.kh.site_logo || '', { type: 'image' }) + body;
    form += `<div class="qt-tab-panel" data-panel="${key}"${active === key ? '' : ' hidden'}>${card(g.label, body)}</div>`;
  }

  // Email đã gửi (để xem khi chưa cấu hình SMTP).
  const mails = await db.emailLog.findMany({ orderBy: { createdAt: 'desc' }, take: 30 });
  const ml = mails.length
    ? '<ul class="qt-inbox">' + mails.map((m) => `<li><details><summary><div class="qt-inbox-who"><strong>${esc(m.subject)}</strong>${m.sent ? badge('Đã gửi', 'ok') : m.error ? badge('Lỗi gửi', 'bad') : badge('Chỉ lưu (chưa cấu hình SMTP)', 'mute')}</div><div class="qt-inbox-msg">Tới: ${esc(m.to)}</div><div class="qt-inbox-time qt-muted">${esc(ago(m.createdAt))}</div></summary><div class="qt-inbox-body"><pre style="white-space:pre-wrap;font:inherit;margin:0">${esc(m.body)}</pre>${m.error ? `<p class="qt-note">${esc(m.error)}</p>` : ''}</div></details></li>`).join('') + '</ul>'
    : empty('Chưa có email nào.', 'mail');
  form += `<div class="qt-tab-panel" data-panel="email"${active === 'email' ? '' : ' hidden'}>${card('Email đã gửi', `<p class="qt-help">Muốn gửi email thật: điền SMTP_HOST, SMTP_USER, SMTP_PASS trong file .env.</p>${ml}`, '', 'is-flush')}</div>`;

  form += `<div class="qt-savebar"><span class="qt-muted" data-qt-dirty-note>Chưa có thay đổi</span><button class="qt-btn" type="submit">${qi('save', 18)} Lưu cài đặt</button></div></form>`;
  return { title: 'Cài đặt', html: `<div class="qt-narrow">${form}</div>` };
}

/* ============================================================ Tài khoản của tôi (quản trị) */

export async function pageAccount(me: User): Promise<AdminPage> {
  let info = `<form method="post" action="/quan-tri/xu-ly/" class="qt-form" autocomplete="off">${formFields('me_save')}`;
  info += field('name', 'Tên hiển thị', me.name, { required: true });
  info += field('login', 'Tên đăng nhập', me.login, { required: true, help: 'Dùng để đăng nhập (hoặc đăng nhập bằng email). Chỉ gồm chữ không dấu, số, dấu chấm, gạch dưới, gạch ngang.', attrs: 'autocapitalize="off" spellcheck="false" pattern="[A-Za-z0-9._\\-]{3,60}"' });
  info += field('email', 'Email', me.email, { type: 'email', required: true, help: 'Email nhận link khi quên mật khẩu.' });
  info += '<hr class="qt-hr"><h3 class="qt-h3">Đổi mật khẩu</h3><p class="qt-help">Để trống 2 ô dưới nếu không đổi mật khẩu.</p>';
  info += field('pass1', 'Mật khẩu mới', '', { type: 'password', attrs: 'autocomplete="new-password" minlength="6"' });
  info += field('pass2', 'Nhập lại mật khẩu mới', '', { type: 'password', attrs: 'autocomplete="new-password" minlength="6"' });
  info += '<hr class="qt-hr">';
  info += field('current', 'Mật khẩu hiện tại (bắt buộc để lưu)', '', { type: 'password', required: true, attrs: 'autocomplete="current-password"' });
  info += `<div class="qt-form-foot"><button class="qt-btn" type="submit">${qi('save', 18)} Lưu thay đổi</button></div></form>`;
  return { title: 'Tài khoản của tôi', html: card('Tên đăng nhập & mật khẩu', info, '', 'is-narrow') };
}
