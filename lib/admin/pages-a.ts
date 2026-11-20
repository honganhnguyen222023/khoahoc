// Quản trị: Tổng quan, Đơn hàng, Học viên (viết lại từ inc/quantri/dashboard.php, orders.php, students.php).
import type { Prisma, User } from '@prisma/client';
import { db } from '../db';
import { esc, price, vnDate } from '../html';
import { accessList, bundleIds, courseLessons, durationLabel, progress, viewsLimit, viewsUsedCount } from '../lms';
import { orderName, PAID, STATUS } from '../orders';
import { getSettings } from '../settings';
import { actionButton, ago, badge, card, type AdminPage, empty, field, formFields, pager, phoneLinks, qi, qtUrl } from './ui';

type Q = Record<string, string>;
const money = (n: number) => esc(price(n) || '0đ');
const st = (s: string) => STATUS[s] || [s, 'info'];
const confirmPaid = (o: { id: number; total: number }) => `Xác nhận đã nhận đủ ${price(o.total)} của đơn #${o.id}? Khóa học sẽ được mở ngay cho học viên.`;

/* ============================================================ Tổng quan */

export async function pageDashboard(user: User): Promise<AdminPage> {
  const monthStart = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Ho_Chi_Minh' }));
  monthStart.setDate(1);
  monthStart.setHours(0, 0, 0, 0);
  const since30 = new Date(Date.now() - 30 * 86400000);
  const [revenue, waiting, waitCount, newStudents, newCount, totalStudents, contacts, contactNew] = await Promise.all([
    db.order.aggregate({ _sum: { total: true }, where: { status: { in: PAID }, createdAt: { gte: monthStart } } }),
    db.order.findMany({ where: { status: { in: ['on-hold', 'pending'] } }, orderBy: { createdAt: 'desc' }, take: 6, include: { items: true } }),
    db.order.count({ where: { status: { in: ['on-hold', 'pending'] } } }),
    db.user.findMany({ where: { role: 'student', createdAt: { gte: since30 } }, orderBy: { createdAt: 'desc' }, take: 6 }),
    db.user.count({ where: { role: 'student', createdAt: { gte: since30 } } }),
    db.user.count({ where: { role: 'student' } }),
    db.contact.findMany({ orderBy: { createdAt: 'desc' }, take: 5 }),
    db.contact.count({ where: { status: 'new' } }),
  ]);
  const stats: [string, string, string, string, string?][] = [
    [`Doanh thu tháng ${new Date().getMonth() + 1}`, price(revenue._sum.total || 0) || '0đ', 'wallet', qtUrl('don-hang', '', { loc: 'da-thanh-toan' })],
    ['Đơn chờ xác nhận', String(waitCount), 'hourglass', qtUrl('don-hang'), waitCount ? 'warn' : ''],
    ['Học viên mới (30 ngày)', String(newCount), 'user-plus', qtUrl('hoc-vien')],
    ['Tổng học viên', String(totalStudents), 'users', qtUrl('hoc-vien')],
    ['Liên hệ chưa đọc', String(contactNew), 'inbox', qtUrl('lien-he'), contactNew ? 'warn' : ''],
  ];
  let html = '<div class="qt-stats">' + stats.map((s) => `<a class="qt-stat${s[4] ? ' is-' + s[4] : ''}" href="${esc(s[3])}"><span class="qt-stat-ico">${qi(s[2], 22)}</span><span class="qt-stat-label">${esc(s[0])}</span><strong>${esc(s[1])}</strong></a>`).join('') + '</div>';

  let rows = '';
  for (const o of waiting) {
    rows += `<li><div class="qt-list-main"><a href="${qtUrl('don-hang', o.id)}"><strong>#${o.id} · ${esc(orderName(o))}</strong></a><span>${esc(o.items.map((i) => i.name).join(', '))}</span><span class="qt-muted">${esc(ago(o.createdAt))}</span></div><div class="qt-list-side"><strong>${money(o.total)}</strong>${actionButton('order_status', { order_id: o.id, status: 'completed' }, qi('check', 16) + ' Đã nhận tiền', 'qt-btn qt-btn-sm', confirmPaid(o))}</div></li>`;
  }
  let grid = card('Đơn chờ xác nhận thanh toán', rows ? `<ul class="qt-list">${rows}</ul>` : empty('Không có đơn nào chờ xác nhận.', 'circle-check'), `<a class="qt-link" href="${qtUrl('don-hang')}">Xem tất cả</a>`, 'is-todo');
  const st1 = newStudents.map((u) => `<li><div class="qt-list-main"><a href="${qtUrl('hoc-vien', u.id)}"><strong>${esc(u.name)}</strong></a><span class="qt-muted">${esc(u.email)}</span></div><div class="qt-list-side"><span class="qt-muted">${esc(ago(u.createdAt))}</span></div></li>`).join('');
  grid += card('Học viên mới', st1 ? `<ul class="qt-list">${st1}</ul>` : empty('Chưa có học viên mới trong 30 ngày.', 'users'), `<a class="qt-link" href="${qtUrl('hoc-vien', 'moi')}">+ Thêm học viên</a>`);
  const ct = contacts.map((c) => `<li><div class="qt-list-main"><strong>${esc(c.name)}${c.status === 'new' ? ' ' + badge('Mới', 'warn') : ''}</strong><span>${esc(c.message.split(/\s+/).slice(0, 14).join(' '))}</span></div><div class="qt-list-side">${phoneLinks(c.phone)}</div></li>`).join('');
  grid += card('Liên hệ gần đây', ct ? `<ul class="qt-list">${ct}</ul>` : empty('Chưa có ai gửi liên hệ.', 'inbox'), `<a class="qt-link" href="${qtUrl('lien-he')}">Xem tất cả</a>`);
  let quick = `<div class="qt-quick"><a href="${qtUrl('hoc-vien', 'moi')}">${qi('user-plus', 22)}<span>Thêm học viên</span></a><a href="${qtUrl('khoa-hoc', 'moi')}">${qi('list-plus', 22)}<span>Thêm khóa học</span></a>`;
  if (user.role === 'admin') quick += `<a href="${qtUrl('cai-dat', '', { tab: 'chuyen-khoan' })}">${qi('landmark', 22)}<span>Tài khoản nhận tiền</span></a>`;
  quick += `<a href="/" target="_blank" rel="noopener">${qi('globe', 22)}<span>Xem website</span></a></div>`;
  grid += card('Làm nhanh', quick);

  const hour = Number(new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Ho_Chi_Minh', hour: 'numeric', hour12: false }).format(new Date()));
  const hello = hour < 11 ? 'Chào buổi sáng' : hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối';
  return { title: `${hello}, ${user.name}`, html: html + `<div class="qt-grid">${grid}</div>` };
}

/* ============================================================ Đơn hàng */

const FILTERS: Record<string, [string, string[]]> = {
  '': ['Tất cả', Object.keys(STATUS)],
  'cho-xac-nhan': ['Chờ xác nhận', ['on-hold', 'pending']],
  'da-thanh-toan': ['Đã thanh toán', ['processing', 'completed']],
  'da-huy': ['Đã hủy / hoàn tiền', ['cancelled', 'refunded', 'failed']],
};

export async function pageOrders(id: string, q: Q): Promise<AdminPage> {
  if (id) return orderDetail(parseInt(id, 10));
  const f = FILTERS[q.loc] ? q.loc : '';
  const search = (q.q || '').trim();
  const page = Math.max(1, parseInt(q.trang, 10) || 1);
  const where: Prisma.OrderWhereInput = { status: { in: FILTERS[f][1] } };
  if (search) {
    const n = parseInt(search.replace('#', ''), 10);
    where.OR = [{ email: { contains: search } }, { phone: { contains: search } }, { firstName: { contains: search } }, { lastName: { contains: search } }, ...(n ? [{ id: n }] : [])];
  }
  const [total, orders] = await Promise.all([db.order.count({ where }), db.order.findMany({ where, orderBy: { createdAt: 'desc' }, skip: (page - 1) * 20, take: 20, include: { items: true } })]);
  let html = '<div class="qt-toolbar"><nav class="qt-tabs">';
  for (const [slug, [label, sts]] of Object.entries(FILTERS)) {
    const count = await db.order.count({ where: { status: { in: sts } } });
    html += `<a href="${qtUrl('don-hang', '', slug ? { loc: slug } : {})}" class="${slug === f ? 'is-active' : ''}">${esc(label)} <em>${count}</em></a>`;
  }
  html += `</nav><form class="qt-search" method="get" action="${qtUrl('don-hang')}">${f ? `<input type="hidden" name="loc" value="${esc(f)}">` : ''}${qi('search', 16)}<input type="search" name="q" value="${esc(search)}" placeholder="Mã đơn, tên, email, SĐT…" aria-label="Tìm đơn hàng"></form></div>`;
  if (!orders.length) return { title: 'Đơn hàng', html: html + card('', empty(search ? 'Không tìm thấy đơn hàng phù hợp.' : 'Chưa có đơn hàng nào ở mục này.', 'receipt')) };
  let rows = '';
  for (const o of orders) {
    const [label, tone] = st(o.status);
    const url = qtUrl('don-hang', o.id);
    rows += `<tr data-href="${url}"><td data-label="Đơn"><a href="${url}"><strong>#${o.id}</strong></a><span class="qt-muted qt-block">${esc(vnDate(o.createdAt, true))}</span></td><td data-label="Học viên"><strong>${esc(orderName(o))}</strong><span class="qt-muted qt-block">${esc(o.phone || o.email)}</span></td><td data-label="Khóa học">${esc(o.items.map((i) => i.name).join(', '))}</td><td data-label="Tổng tiền" class="qt-num"><strong>${money(o.total)}</strong></td><td data-label="Trạng thái">${badge(label, tone)}</td><td class="qt-row-actions">${['on-hold', 'pending'].includes(o.status) ? actionButton('order_status', { order_id: o.id, status: 'completed' }, qi('check', 16) + ' Đã nhận tiền', 'qt-btn qt-btn-sm', confirmPaid(o)) : ''}</td></tr>`;
  }
  const base = qtUrl('don-hang', '', { ...(f ? { loc: f } : {}), ...(search ? { q: search } : {}) });
  html += card('', `<table class="qt-table is-click"><thead><tr><th>Đơn</th><th>Học viên</th><th>Khóa học</th><th class="qt-num">Tổng tiền</th><th>Trạng thái</th><th><span class="screen-reader-text">Thao tác</span></th></tr></thead><tbody>${rows}</tbody></table>` + pager(page, Math.ceil(total / 20), base), '', 'is-flush');
  return { title: 'Đơn hàng', html };
}

async function orderDetail(id: number): Promise<AdminPage> {
  const o = await db.order.findUnique({ where: { id }, include: { items: true, notes: { orderBy: { createdAt: 'desc' } } } });
  if (!o) return { title: 'Không tìm thấy đơn', back: qtUrl('don-hang'), html: empty('Đơn hàng không tồn tại hoặc đã bị xóa.', 'receipt'), status: 404 };
  const [label, tone] = st(o.status);
  const paid = PAID.includes(o.status);
  const total = price(o.total);
  let bar = `<div class="qt-order-bar"><div>${badge(label, tone)}<span class="qt-muted">Đặt lúc ${esc(vnDate(o.createdAt, true))}</span></div><div class="qt-order-total">${money(o.total)}</div><div class="qt-btn-row">`;
  if (['on-hold', 'pending', 'failed'].includes(o.status)) {
    bar += actionButton('order_status', { order_id: id, status: 'completed' }, qi('check', 18) + ' Xác nhận đã nhận tiền', 'qt-btn', confirmPaid(o));
    bar += actionButton('order_status', { order_id: id, status: 'cancelled' }, 'Hủy đơn', 'qt-btn qt-btn-ghost', `Hủy đơn #${id}?`);
  } else if (o.status === 'processing') {
    bar += actionButton('order_status', { order_id: id, status: 'completed' }, qi('check', 18) + ' Đánh dấu hoàn thành', 'qt-btn');
    bar += actionButton('order_status', { order_id: id, status: 'refunded' }, 'Hoàn tiền', 'qt-btn qt-btn-ghost', `Đánh dấu đã hoàn tiền đơn #${id}? Học viên sẽ bị khóa các khóa học trong đơn này. (Tiền bạn tự chuyển trả cho học viên.)`);
  } else if (o.status === 'completed') {
    bar += actionButton('order_status', { order_id: id, status: 'refunded' }, 'Hoàn tiền', 'qt-btn qt-btn-ghost', `Đánh dấu đã hoàn tiền đơn #${id}? Học viên sẽ bị khóa các khóa học trong đơn này. (Tiền bạn tự chuyển trả cho học viên.)`);
  } else {
    bar += actionButton('order_status', { order_id: id, status: 'on-hold' }, qi('rotate-ccw', 16) + ' Mở lại đơn', 'qt-btn qt-btn-ghost', `Mở lại đơn #${id} về trạng thái chờ xác nhận?`);
  }
  bar += '</div></div>';

  const who = `<dl class="qt-dl"><dt>Họ tên</dt><dd><strong>${esc(orderName(o))}</strong></dd><dt>Điện thoại</dt><dd>${esc(o.phone || '—')} ${phoneLinks(o.phone)}</dd><dt>Email</dt><dd>${o.email ? `<a href="mailto:${esc(o.email)}">${esc(o.email)}</a>` : '—'}</dd></dl>`;
  let items = '<ul class="qt-list">';
  for (const it of o.items) {
    let state = '';
    if (o.userId && it.courseId) {
      const a = (await accessList(o.userId))[it.courseId];
      state = a ? (a.active ? badge('Đã mở khóa', 'ok') : badge('Hết hạn', 'mute')) : badge('Chưa mở', 'warn');
    }
    items += `<li><div class="qt-list-main"><strong>${esc(it.name)}</strong>${state ? `<span>${state}</span>` : ''}</div><div class="qt-list-side"><strong>${money(it.total)}</strong></div></li>`;
  }
  items += '</ul>';
  let pay = `<dl class="qt-dl"><dt>Hình thức</dt><dd>${esc(o.paymentTitle || '—')}</dd><dt>Tổng tiền</dt><dd><strong>${money(o.total)}</strong></dd>`;
  if (o.paidAt) pay += `<dt>Ngày thanh toán</dt><dd>${esc(vnDate(o.paidAt, true))}</dd>`;
  if (o.paymentRef) pay += `<dt>Mã giao dịch</dt><dd><code>${esc(o.paymentRef)}</code></dd>`;
  if (o.note) pay += `<dt>Học viên ghi chú</dt><dd>${esc(o.note)}</dd>`;
  pay += '</dl>';
  if (!paid && o.paymentMethod === 'bacs') pay += `<p class="qt-note">${qi('info', 16)}<span>Kiểm tra tài khoản ngân hàng có khoản <strong>${esc(total)}</strong>, nội dung chuyển khoản thường có mã <strong>KH${o.id}</strong>, rồi bấm "Xác nhận đã nhận tiền".</span></p>`;
  let nh = `<form method="post" action="/quan-tri/xu-ly/" class="qt-note-form">${formFields('order_note')}<input type="hidden" name="order_id" value="${id}"><textarea name="note" rows="2" placeholder="Thêm ghi chú nội bộ (học viên không thấy)…" required></textarea><button class="qt-btn qt-btn-sm" type="submit">Thêm ghi chú</button></form>`;
  if (o.notes.length) nh += '<ul class="qt-timeline">' + o.notes.map((n) => `<li><p>${esc(n.content)}</p><span class="qt-muted">${esc(vnDate(n.createdAt, true))}${n.author && n.author !== 'system' ? ' · ' + esc(n.author) : ''}</span></li>`).join('') + '</ul>';
  const html = bar + '<div class="qt-grid">' + card('Học viên', who, o.userId ? `<a class="qt-link" href="${qtUrl('hoc-vien', o.userId)}">Xem học viên</a>` : '') + card('Thanh toán', pay) + card('Khóa học trong đơn', items) + card('Lịch sử & ghi chú', nh) + '</div>';
  return { title: `Đơn hàng #${id}`, back: qtUrl('don-hang'), html };
}

/* ============================================================ Học viên */

async function allCourses() {
  return db.course.findMany({ orderBy: [{ sortOrder: 'asc' }, { title: 'asc' }] });
}

async function courseChecks(name: string, skip: number[] = []): Promise<string> {
  const s = await getSettings();
  return '<div class="qt-checks">' + (await allCourses()).filter((c) => !skip.includes(c.id)).map((c) => `<label class="qt-check"><input type="checkbox" name="${esc(name)}" value="${c.id}"><span>${esc(c.title)}${c.status !== 'publish' ? ' <em class="qt-muted">(đang ẩn)</em>' : ''}</span><small>${esc(durationLabel(c, s))}</small></label>`).join('') + '</div>';
}

export async function pageStudents(id: string, q: Q): Promise<AdminPage> {
  const add = `<a class="qt-btn qt-btn-sm" href="${qtUrl('hoc-vien', 'moi')}">${qi('user-plus', 16)} <span>Thêm học viên</span></a>`;
  if (id === 'moi') return studentNew(q);
  if (id) return studentDetail(parseInt(id, 10));
  const search = (q.q || '').trim();
  const page = Math.max(1, parseInt(q.trang, 10) || 1);
  const where: Prisma.UserWhereInput = { role: 'student' };
  if (search) where.OR = [{ name: { contains: search } }, { email: { contains: search } }, { login: { contains: search } }, { phone: { contains: search.replace(/\D/g, '') || search } }];
  const [total, users] = await Promise.all([db.user.count({ where }), db.user.findMany({ where, orderBy: { createdAt: 'desc' }, skip: (page - 1) * 20, take: 20, include: { access: { include: { course: true } } } })]);
  let html = `<div class="qt-toolbar"><p class="qt-muted">${total} học viên</p><form class="qt-search" method="get" action="${qtUrl('hoc-vien')}">${qi('search', 16)}<input type="search" name="q" value="${esc(search)}" placeholder="Tên, email hoặc số điện thoại…" aria-label="Tìm học viên"></form></div>`;
  if (!users.length) return { title: 'Học viên', actions: add, html: html + card('', empty(search ? 'Không tìm thấy học viên phù hợp.' : 'Chưa có học viên nào.', 'users', `<a class="qt-btn" href="${qtUrl('hoc-vien', 'moi')}">${qi('user-plus', 16)} Thêm học viên</a>`)) };
  let rows = '';
  for (const u of users) {
    const list = await accessList(u.id);
    const chips = u.access.map((a) => { const info = list[a.courseId]; return `<span class="qt-chip${info?.active ? ' is-ok' : ' is-mute'}">${esc(a.course.title)}${info?.active ? '' : ' (hết hạn)'}</span>`; }).join('');
    const url = qtUrl('hoc-vien', u.id);
    rows += `<tr data-href="${url}"><td data-label="Học viên"><div class="qt-person"><span class="qt-avatar" style="width:36px;height:36px;border-radius:50%;background:var(--qt-primary-soft);color:var(--qt-primary);display:grid;place-items:center;font-weight:700;flex:none">${esc(u.name.charAt(0).toUpperCase())}</span><div><a href="${url}"><strong>${esc(u.name)}</strong></a><span class="qt-muted qt-block">${esc(u.email)}</span></div></div></td><td data-label="Điện thoại">${u.phone ? esc(u.phone) : '<span class="qt-muted">—</span>'}</td><td data-label="Khóa học">${chips ? `<div class="qt-chips">${chips}</div>` : '<span class="qt-muted">Chưa có khóa nào</span>'}</td><td data-label="Tham gia">${esc(vnDate(u.createdAt))}</td></tr>`;
  }
  html += card('', `<table class="qt-table is-click"><thead><tr><th>Học viên</th><th>Điện thoại</th><th>Khóa học</th><th>Tham gia</th></tr></thead><tbody>${rows}</tbody></table>` + pager(page, Math.ceil(total / 20), qtUrl('hoc-vien', '', search ? { q: search } : {})), '', 'is-flush');
  return { title: 'Học viên', actions: add, html };
}

async function studentNew(q: Q): Promise<AdminPage> {
  let form = `<form method="post" action="/quan-tri/xu-ly/" class="qt-form" autocomplete="off">${formFields('student_create')}<div class="qt-form-grid">`;
  form += field('name', 'Họ và tên', q.ten || '', { required: true, placeholder: 'Nguyễn Văn An' });
  form += field('phone', 'Số điện thoại / Zalo', q.sdt || '', { type: 'tel', placeholder: '0912 345 678' });
  form += field('email', 'Email (dùng để đăng nhập)', q.email || '', { type: 'email', required: true, placeholder: 'hocvien@gmail.com' });
  form += field('password', 'Mật khẩu', '', { placeholder: 'Để trống: tự tạo', help: 'Để trống thì học viên tự đặt mật khẩu qua email.', attrs: 'autocomplete="new-password"' });
  form += '</div><h3 class="qt-h3">Mở khóa học ngay</h3><p class="qt-help">Dùng khi học viên chuyển khoản trực tiếp cho bạn. Có thể bỏ trống và mở sau.</p>';
  form += await courseChecks('courses');
  form += field('notify', 'Gửi email cho học viên (link đặt mật khẩu và đăng nhập)', '1', { type: 'checkbox' });
  form += `<div class="qt-form-foot"><button class="qt-btn" type="submit">${qi('user-plus', 18)} Tạo học viên</button><a class="qt-btn qt-btn-ghost" href="${qtUrl('hoc-vien')}">Hủy</a></div></form>`;
  return { title: 'Thêm học viên', back: qtUrl('hoc-vien'), html: card('', form, '', 'is-narrow') };
}

async function studentDetail(id: number): Promise<AdminPage> {
  const u = await db.user.findUnique({ where: { id } });
  if (!u) return { title: 'Không tìm thấy', back: qtUrl('hoc-vien'), html: empty('Học viên không tồn tại.', 'users'), status: 404 };
  const s = await getSettings();
  const direct = await db.access.findMany({ where: { userId: id } });
  const map = Object.fromEntries(direct.map((a) => [a.courseId, a.source]));
  const list = await accessList(id);
  const courses = await db.course.findMany({ where: { id: { in: Object.keys(list).map(Number) } } });
  const limit = viewsLimit(s);

  const head = `<div class="qt-profile"><span class="qt-avatar" style="width:64px;height:64px;border-radius:50%;background:var(--qt-primary-soft);color:var(--qt-primary);display:grid;place-items:center;font:800 26px var(--qt-head);flex:none">${esc(u.name.charAt(0).toUpperCase())}</span><div><h2>${esc(u.name)}</h2><p class="qt-muted">${esc(u.email)}${u.phone ? ' · ' + esc(u.phone) : ''} · Tham gia ${esc(vnDate(u.createdAt))}</p><div class="qt-btn-row">${phoneLinks(u.phone)}<a class="qt-chip" href="mailto:${esc(u.email)}">${qi('mail', 14)} Email</a></div></div></div>`;
  let rows = '';
  for (const [cidStr, a] of Object.entries(list)) {
    const cid = Number(cidStr);
    const c = courses.find((x) => x.id === cid);
    if (!c) continue;
    const src = map[cid];
    const from = src?.startsWith('order:') ? `Mua qua đơn <a href="${qtUrl('don-hang', src.slice(6))}">#${esc(src.slice(6))}</a>` : src ? 'Quản trị mở tay' : `Nằm trong combo “${esc(courses.find((x) => x.id === a.via)?.title || '')}”`;
    const exp = a.expires ? (a.active ? 'Hạn đến ' + vnDate(a.expires) : 'Hết hạn ' + vnDate(a.expires)) : 'Học trọn đời';
    const lessons = Object.values(courseLessons(c)).filter((l) => l.src);
    const done = Object.keys(await progress(id, cid)).map(Number).filter((n) => lessons.some((l) => l.n === n)).length;
    const pct = lessons.length ? Math.round((done / lessons.length) * 100) : 0;
    const used = await viewsUsedCount(id, cid, s);
    rows += `<li class="qt-course-row${a.active ? '' : ' is-expired'}"><div class="qt-course-info"><strong>${esc(c.title)}</strong> ${a.active ? badge('Đang học', 'ok') : badge('Hết hạn', 'bad')}<span class="qt-muted qt-block">${from} · ${esc(exp)}</span>`;
    if (lessons.length) rows += `<div class="qt-progress"><span style="width:${pct}%"></span></div><span class="qt-muted">Đã học ${done}/${lessons.length} bài${used ? ` · <strong class="qt-warn">${used} bài hết lượt xem</strong>` : ''}</span>`;
    rows += '</div><div class="qt-course-actions">';
    if (src) rows += actionButton('course_renew', { user_id: id, course_id: cid }, qi('refresh-cw', 15) + ' Gia hạn', 'qt-btn qt-btn-ghost qt-btn-sm', `Gia hạn khóa “${c.title}” từ hôm nay (${durationLabel(c, s)})? Tiến độ học được giữ nguyên.`);
    if (limit && used) rows += actionButton('course_views', { user_id: id, course_id: cid }, qi('rotate-ccw', 15) + ' Cho xem lại', 'qt-btn qt-btn-ghost qt-btn-sm', `Đặt lại lượt xem khóa “${c.title}”? Học viên được xem mỗi bài thêm ${limit} lần.`);
    if (src) rows += actionButton('course_revoke', { user_id: id, course_id: cid }, qi('x', 15) + ' Thu hồi', 'qt-btn qt-btn-ghost qt-btn-sm is-danger', `Thu hồi khóa “${c.title}” của ${u.name}? Học viên sẽ không vào học được nữa.`);
    rows += '</div></li>';
  }
  const coursesHtml = rows ? `<ul class="qt-course-list">${rows}</ul>` : empty('Học viên chưa có khóa học nào.', 'book-open');
  const grant = `<form method="post" action="/quan-tri/xu-ly/" class="qt-form">${formFields('course_grant')}<input type="hidden" name="user_id" value="${id}">${await courseChecks('courses', direct.map((a) => a.courseId))}<div class="qt-form-foot"><button class="qt-btn qt-btn-sm" type="submit">${qi('plus', 16)} Mở khóa đã chọn</button></div></form>`;
  let edit = `<form method="post" action="/quan-tri/xu-ly/" class="qt-form">${formFields('student_update')}<input type="hidden" name="user_id" value="${id}">`;
  edit += field('name', 'Họ và tên', u.name, { required: true }) + field('phone', 'Số điện thoại / Zalo', u.phone, { type: 'tel' }) + field('email', 'Email đăng nhập', u.email, { type: 'email', required: true });
  edit += `<div class="qt-form-foot"><button class="qt-btn qt-btn-sm" type="submit">${qi('save', 16)} Lưu</button></div></form><hr class="qt-hr">`;
  edit += actionButton('student_reset', { user_id: id }, qi('mail', 15) + ' Gửi email đặt lại mật khẩu', 'qt-btn qt-btn-ghost qt-btn-sm', `Gửi email đặt lại mật khẩu tới ${u.email}?`);
  const orders = await db.order.findMany({ where: { userId: id }, orderBy: { createdAt: 'desc' }, take: 10, include: { items: true } });
  const ol = orders.map((o) => { const [l, t] = st(o.status); return `<li><div class="qt-list-main"><a href="${qtUrl('don-hang', o.id)}"><strong>#${o.id}</strong></a><span>${esc(o.items.map((i) => i.name).join(', '))}</span><span class="qt-muted">${esc(vnDate(o.createdAt))}</span></div><div class="qt-list-side"><strong>${money(o.total)}</strong>${badge(l, t)}</div></li>`; }).join('');
  const html = card('', head, '', 'is-profile') + `<div class="qt-grid is-wide-left"><div class="qt-stack">${card('Khóa học', coursesHtml)}${card('Mở thêm khóa học', grant)}</div><div class="qt-stack">${card('Thông tin học viên', edit)}${card('Đơn hàng', ol ? `<ul class="qt-list">${ol}</ul>` : empty('Chưa có đơn hàng.', 'receipt'))}</div></div>`;
  void bundleIds;
  return { title: u.name, back: qtUrl('hoc-vien'), html };
}
