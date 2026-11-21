// Trang quản trị /quan-tri/ – khung trang + mẩu giao diện dùng chung (viết lại từ inc/quantri.php).
import type { User } from '@prisma/client';
import { db } from '../db';
import { digits, esc, num, vnDate } from '../html';
import { icon } from '../icons';
import { opt, type Settings } from '../settings';

export type AdminPage = { title: string; html: string; actions?: string; back?: string; status?: number };

export type Section = { label: string; icon: string; admin?: boolean };
export const SECTIONS: Record<string, Section> = {
  'tong-quan': { label: 'Tổng quan', icon: 'layout-dashboard' },
  'don-hang': { label: 'Đơn hàng', icon: 'receipt' },
  'hoc-vien': { label: 'Học viên', icon: 'users' },
  'khoa-hoc': { label: 'Khóa học', icon: 'book-open' },
  'lien-he': { label: 'Liên hệ', icon: 'inbox' },
  'cai-dat': { label: 'Cài đặt', icon: 'settings', admin: true },
  'tai-khoan': { label: 'Tài khoản của tôi', icon: 'user' },
};

/** Quyền vào mục: quản trị viên vào hết; nhân viên không vào Cài đặt. */
export function canSection(user: User, section: string): boolean {
  const s = SECTIONS[section];
  return !!s && (user.role === 'admin' || (user.role === 'staff' && !s.admin));
}

export function qtUrl(section = '', id: string | number = '', args: Record<string, string | number> = {}): string {
  let path = '/quan-tri/';
  if (section && section !== 'tong-quan') {
    path += section + '/';
    if (id !== '' && id !== 0) path += id + '/';
  }
  const qs = new URLSearchParams(Object.entries(args).map(([k, v]) => [k, String(v)])).toString();
  return path + (qs ? '?' + qs : '');
}

export const qi = (name: string, size = 18) => icon(name, 'qt-ico', size);

/** Nút thao tác nhanh (1 form nhỏ gửi tới /quan-tri/xu-ly/). */
export function actionButton(doName: string, fields: Record<string, string | number>, label: string, cls = 'qt-btn', confirm = ''): string {
  return `<form method="post" action="/quan-tri/xu-ly/" class="qt-inline">${formFields(doName)}${Object.entries(fields).map(([k, v]) => `<input type="hidden" name="${esc(k)}" value="${esc(v)}">`).join('')}<button type="submit" class="${esc(cls)}"${confirm ? ` data-confirm="${esc(confirm)}"` : ''}>${label}</button></form>`;
}

export function formFields(doName: string): string {
  return `<input type="hidden" name="kh_qt_do" value="${esc(doName)}">`;
}

export function card(title: string, body: string, extra = '', cls = ''): string {
  const head = title || extra ? `<div class="qt-card-head"><h2>${esc(title)}</h2>${extra}</div>` : '';
  return `<section class="qt-card ${esc(cls)}">${head}<div class="qt-card-body">${body}</div></section>`;
}

export function badge(text: string, tone = 'mute'): string {
  return `<span class="qt-badge is-${esc(tone)}">${esc(text)}</span>`;
}

export function empty(text: string, ic = 'inbox', cta = ''): string {
  return `<div class="qt-empty">${qi(ic, 36)}<p>${esc(text)}</p>${cta}</div>`;
}

export function pager(page: number, pages: number, base: string): string {
  if (pages < 2) return '';
  const link = (n: number) => {
    const u = new URL(base, 'http://x');
    if (n > 1) u.searchParams.set('trang', String(n));
    else u.searchParams.delete('trang');
    return u.pathname + u.search;
  };
  let out = '<nav class="qt-pager" aria-label="Phân trang">';
  if (page > 1) out += `<a class="qt-btn qt-btn-ghost qt-btn-sm" href="${esc(link(page - 1))}">${qi('chevron-left', 16)} Trước</a>`;
  out += `<span>Trang ${page}/${pages}</span>`;
  if (page < pages) out += `<a class="qt-btn qt-btn-ghost qt-btn-sm" href="${esc(link(page + 1))}">Sau ${qi('chevron-right', 16)}</a>`;
  return out + '</nav>';
}

export function ago(t: Date | number | null | undefined): string {
  if (!t) return '';
  const ms = typeof t === 'number' ? t : t.getTime();
  const diff = (Date.now() - ms) / 1000;
  if (diff < 60) return 'vừa xong';
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
  if (diff < 3 * 86400) return `${Math.floor(diff / 86400)} ngày trước`;
  return vnDate(ms);
}

export function phoneLinks(phone: string): string {
  const d = digits(phone);
  if (!d) return '';
  return `<span class="qt-phone-links"><a class="qt-chip" href="tel:${d}">${qi('phone', 14)} Gọi</a><a class="qt-chip" target="_blank" rel="noopener" href="https://zalo.me/${d}">${qi('zalo', 14)} Zalo</a></span>`;
}

type FieldOpts = { type?: string; help?: string; options?: Record<string, string>; required?: boolean; placeholder?: string; attrs?: string; wide?: boolean };

/** Ô nhập liệu có nhãn (giống kh_qt_field). */
export function field(name: string, label: string, value: unknown = '', o: FieldOpts = {}): string {
  const type = o.type || 'text';
  const id = 'f-' + name.replace(/[^a-z0-9_-]/gi, '-');
  const attr = (o.required ? ' required' : '') + (o.placeholder ? ` placeholder="${esc(o.placeholder)}"` : '') + (o.attrs ? ' ' + o.attrs : '');
  const v = value === null || value === undefined ? '' : String(value);
  let out = `<div class="qt-field${o.wide ? ' is-wide' : ''}${type === 'checkbox' ? ' is-check' : ''}">`;
  switch (type) {
    case 'checkbox':
      out += `<label class="qt-switch"><input type="checkbox" id="${id}" name="${esc(name)}" value="1"${value === true || v === '1' ? ' checked' : ''}${attr}><span class="qt-switch-ui" aria-hidden="true"></span><span>${esc(label)}</span></label>`;
      break;
    case 'textarea':
    case 'code':
      out += `<label for="${id}">${esc(label)}</label><textarea id="${id}" name="${esc(name)}" rows="${type === 'code' ? 6 : 4}"${type === 'code' ? ' class="is-code"' : ''}${attr}>${esc(v)}</textarea>`;
      break;
    case 'select':
      out += `<label for="${id}">${esc(label)}</label><select id="${id}" name="${esc(name)}"${attr}>${Object.entries(o.options || {}).map(([ov, ol]) => `<option value="${esc(ov)}"${ov === v ? ' selected' : ''}>${esc(ol)}</option>`).join('')}</select>`;
      break;
    case 'money':
      out += `<label for="${id}">${esc(label)}</label><div class="qt-money"><input type="text" inputmode="numeric" data-qt-money id="${id}" name="${esc(name)}" value="${v === '' ? '' : esc(num(Number(v)))}"${attr}><span>đ</span></div>`;
      break;
    case 'image':
      out += `<label>${esc(label)}</label><div class="qt-image" data-qt-image><input type="hidden" name="${esc(name)}" value="${esc(v)}"><div class="qt-image-preview">${v ? `<img src="${esc(v)}" alt="">` : qi('image', 28)}</div><div class="qt-image-btns"><button type="button" class="qt-btn qt-btn-ghost qt-btn-sm" data-qt-image-pick>Tải ảnh lên</button><button type="button" class="qt-btn qt-btn-ghost qt-btn-sm" data-qt-image-clear>Bỏ ảnh</button></div></div>`;
      break;
    case 'color':
      out += `<label for="${id}">${esc(label)}</label><div class="qt-color"><input type="color" id="${id}" name="${esc(name)}" value="${esc(v)}"${attr}><code>${esc(v)}</code></div>`;
      break;
    default:
      out += `<label for="${id}">${esc(label)}</label><input type="${esc(type)}" id="${id}" name="${esc(name)}" value="${esc(v)}"${attr}>`;
  }
  if (o.help) out += `<p class="qt-help">${esc(o.help)}</p>`;
  return out + '</div>';
}

/** "490.000" → "490000" ('' nếu trống). */
export function moneyValue(raw: unknown): string {
  const d = String(raw ?? '').replace(/\D/g, '');
  return d === '' ? '' : String(parseInt(d, 10));
}

/** Số việc cần làm trên menu (đơn chờ xác nhận, liên hệ mới). */
async function badges(): Promise<Record<string, number>> {
  const [orders, contacts] = await Promise.all([db.order.count({ where: { status: { in: ['on-hold', 'pending'] } } }), db.contact.count({ where: { status: 'new' } })]);
  return { 'don-hang': orders, 'lien-he': contacts };
}

/** Cả trang quản trị: thanh bên + nội dung. */
export async function adminShell(s: Settings, user: User, section: string, page: AdminPage, flash?: { msg: string; ok: boolean } | null): Promise<string> {
  const b = await badges();
  const brand = opt(s, 'brand_name');
  const nav = Object.entries(SECTIONS)
    .filter(([slug]) => canSection(user, slug))
    .map(([slug, sec]) => `<a href="${qtUrl(slug)}" class="${slug === section ? 'is-active' : ''}"${slug === section ? ' aria-current="page"' : ''}>${qi(sec.icon, 20)}<span>${esc(sec.label)}</span>${b[slug] ? `<em class="qt-count">${b[slug]}</em>` : ''}</a>`)
    .join('');
  const initial = esc(user.name.trim().charAt(0).toUpperCase() || 'A');
  return `<div class="qt-app">
<aside class="qt-side" id="qt-side"><a class="qt-brand" href="${qtUrl()}"><span class="qt-logo">${esc(opt(s, 'brand_initial'))}</span><span><strong>${esc(brand)}</strong><small>Quản trị</small></span></a>
<nav class="qt-nav" aria-label="Mục quản trị">${nav}</nav>
<div class="qt-side-foot"><a href="/" target="_blank" rel="noopener">${qi('globe')} Xem website</a><a href="/quan-tri/dang-xuat/">${qi('log-out')} Đăng xuất</a></div></aside>
<div class="qt-scrim" data-qt-close></div>
<div class="qt-main"><header class="qt-top"><button type="button" class="qt-burger" data-qt-menu aria-controls="qt-side" aria-label="Mở menu">${qi('menu', 22)}</button>
<div class="qt-title">${page.back ? `<a class="qt-back" href="${esc(page.back)}">${qi('arrow-left', 16)} Quay lại</a>` : ''}<h1>${esc(page.title)}</h1></div>
<div class="qt-top-actions">${page.actions || ''}</div>
<a class="qt-user" href="${qtUrl('tai-khoan')}" title="${esc(user.email)} – Đổi tên, mật khẩu"><span class="qt-avatar" style="width:32px;height:32px;border-radius:50%;background:var(--qt-primary-soft);color:var(--qt-primary);display:grid;place-items:center;font-weight:700">${initial}</span><span>${esc(user.name)}</span></a></header>
${flash ? `<div class="qt-flash ${flash.ok ? 'is-ok' : 'is-err'}" role="status">${qi(flash.ok ? 'circle-check' : 'alert-circle')}<span>${esc(flash.msg)}</span><button type="button" data-qt-dismiss aria-label="Đóng">${qi('x', 16)}</button></div>` : ''}
<main class="qt-content">${page.html}</main></div></div>`;
}

export function denied(): AdminPage {
  return { title: 'Không có quyền', html: `<div class="qt-empty">${qi('lock', 40)}<p>Tài khoản của bạn không được dùng mục này. Liên hệ chủ website để được cấp quyền.</p></div>` };
}
