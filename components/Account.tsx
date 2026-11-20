// Trang tài khoản học viên (HTML giống trang "Tài khoản" của WooCommerce trên bản WordPress).
import type { User } from '@prisma/client';
import { Html } from '@/components/Html';
import { WooCss } from '@/components/Shop';
import { db } from '@/lib/db';
import { esc, price, vnDate } from '@/lib/html';
import { icon } from '@/lib/icons';
import { accessList, continueUrl, courseLessons, courseUrl, progress } from '@/lib/lms';
import { PAID, STATUS } from '@/lib/orders';
import { breadcrumb } from '@/lib/templates';

export const NOTICES: Record<string, [string, 'ok' | 'err' | 'info']> = {
  sai: ['Email/tên đăng nhập hoặc mật khẩu chưa đúng.', 'err'],
  khoa: ['Bạn đã nhập sai nhiều lần. Vui lòng thử lại sau 15 phút hoặc bấm "Quên mật khẩu".', 'err'],
  'thieu-ten': ['Vui lòng nhập họ và tên.', 'err'],
  'sai-sdt': ['Số điện thoại chưa đúng.', 'err'],
  'sai-email': ['Email chưa đúng.', 'err'],
  'mk-ngan': ['Mật khẩu cần ít nhất 6 ký tự.', 'err'],
  'mk-khac': ['Hai mật khẩu không khớp.', 'err'],
  'trung-email': ['Email này đã có tài khoản. Vui lòng đăng nhập.', 'err'],
  'sai-mk-cu': ['Mật khẩu hiện tại chưa đúng.', 'err'],
  'da-luu': ['Đã lưu thông tin tài khoản.', 'ok'],
  'da-gui': ['Nếu thông tin bạn nhập có tài khoản, chúng tôi đã gửi email kèm link đặt lại mật khẩu. Kiểm tra cả hộp thư Spam/Quảng cáo nhé.', 'ok'],
  'het-han': ['Link đặt lại mật khẩu đã hết hạn hoặc không đúng. Vui lòng yêu cầu lại.', 'err'],
  'da-doi-mk': ['Đã đổi mật khẩu thành công. Mời bạn đăng nhập.', 'ok'],
};

export function notice(tb: string): string {
  const n = NOTICES[tb];
  if (!n) return '<div class="woocommerce-notices-wrapper"></div>';
  if (n[1] === 'err') return `<div class="woocommerce-notices-wrapper"><ul class="woocommerce-error" role="alert"><li>${esc(n[0])}</li></ul></div>`;
  return `<div class="woocommerce-notices-wrapper"><div class="woocommerce-message" role="alert">${esc(n[0])}</div></div>`;
}

/** Khung trang tài khoản: tiêu đề + breadcrumb + (menu tài khoản) + nội dung. */
export function AccountShell({ title, crumbs, html = '', nav, tb = '', children }: { title: string; crumbs?: [string, string?][]; html?: string; nav?: string; tb?: string; children?: React.ReactNode }) {
  const head = `<header class="kh-page-head"><div class="kh-container">${breadcrumb(crumbs || [[title]])}<h1 class="kh-h1 kh-h1-sm">${esc(title)}</h1></div></header>`;
  return (
    <div className="woocommerce-account woocommerce-page">
      <WooCss />
      <Html html={head} />
      <article className="kh-container kh-page">
        <div className="kh-prose kh-entry">
          <div className="woocommerce">
            {nav ? (
              <>
                <Html html={accountNav(nav)} />
                <div className="woocommerce-MyAccount-content">
                  <Html html={notice(tb) + html} />
                  {children}
                </div>
              </>
            ) : (
              <>
                <Html html={notice(tb) + html} />
                {children}
              </>
            )}
          </div>
        </div>
      </article>
    </div>
  );
}

const MENU: [string, string, string][] = [
  ['dashboard', 'Tổng quan', '/tai-khoan/'],
  ['khoa-hoc-cua-toi', 'Khóa học của tôi', '/tai-khoan/khoa-hoc-cua-toi/'],
  ['orders', 'Đơn hàng', '/tai-khoan/don-hang/'],
  ['edit-account', 'Thông tin tài khoản', '/tai-khoan/thong-tin/'],
  ['customer-logout', 'Đăng xuất', '/tai-khoan/dang-xuat/'],
];

export function accountNav(active: string): string {
  return `<nav class="woocommerce-MyAccount-navigation" aria-label="Trang tài khoản"><ul>${MENU.map(
    ([k, l, u]) => `<li class="woocommerce-MyAccount-navigation-link woocommerce-MyAccount-navigation-link--${k}${k === active ? ' is-active' : ''}"><a href="${u}"${k === active ? ' aria-current="page"' : ''}>${esc(l)}</a></li>`,
  ).join('')}</ul></nav>`;
}

export function withNav(active: string, content: string, tb = ''): string {
  return `${accountNav(active)}<div class="woocommerce-MyAccount-content">${notice(tb)}${content}</div>`;
}

/* ---------------------------------------------------------- Khóa học của tôi */

export async function myCoursesHtml(user: User): Promise<string> {
  const list = Object.entries(await accessList(user.id)).map(([id, a]) => [Number(id), a] as const);
  if (!list.length) return '<div class="kh-mycourses-empty"><p>Bạn chưa đăng ký khóa học nào.</p><a class="kh-btn kh-btn-primary" href="/khoa-hoc/">Xem các khóa học</a></div>';
  list.sort((x, y) => Number(y[1].active) - Number(x[1].active));
  const courses = await db.course.findMany({ where: { id: { in: list.map((x) => x[0]) } } });
  let out = '<ul class="kh-mycourses">';
  for (const [id, info] of list) {
    const c = courses.find((x) => x.id === id);
    if (!c) continue;
    const lessons = Object.values(courseLessons(c)).filter((l) => l.src);
    if (!lessons.length && c.bundle) continue; // Combo: các khóa bên trong đã có trong danh sách.
    const img = c.thumbnail || c.cover;
    const thumb = img ? `<img src="${esc(img)}" alt="" width="1280" height="720" loading="lazy">` : icon('book-open', '', 32);
    const done = Object.keys(await progress(user.id, c.id)).map(Number).filter((n) => lessons.some((l) => l.n === n)).length;
    const total = lessons.length;
    const pct = total ? Math.round((done / total) * 100) : 0;
    const href = info.active ? await continueUrl(c, user.id) : courseUrl(c);
    out += `<li class="kh-mycourse${info.active ? '' : ' is-expired'}"><a class="kh-mycourse-media" href="${esc(href)}" tabindex="-1" aria-hidden="true">${thumb}</a><div class="kh-mycourse-body"><h3><a href="${esc(courseUrl(c))}">${esc(c.title)}</a></h3>`;
    if (info.active) {
      out += `<p>Đã học ${done}/${total} bài</p><span class="kh-learn-bar"><span style="width:${pct}%"></span></span><p class="kh-mycourse-exp">${info.expires ? 'Hạn học đến ' + vnDate(info.expires) : 'Học trọn đời'}</p><a class="kh-btn kh-btn-primary kh-btn-sm" href="${esc(href)}">${icon('play', '', 16)} ${done ? 'Học tiếp' : 'Vào học'}</a>`;
    } else {
      out += `<p class="kh-mycourse-exp is-expired">Đã hết hạn ngày ${vnDate(info.expires)}</p><a class="kh-btn kh-btn-outline kh-btn-sm" href="${esc(courseUrl(c))}">${icon('refresh-cw', '', 16)} Mua lại để học từ đầu</a>`;
    }
    out += '</div></li>';
  }
  return out + '</ul>';
}

/* ---------------------------------------------------------- Đơn hàng */

const money = (n: number) => `<span class="woocommerce-Price-amount amount">${price(n).replace('đ', '')}<span class="woocommerce-Price-currencySymbol">₫</span></span>`;

export async function ordersHtml(user: User): Promise<string> {
  const orders = await db.order.findMany({ where: { userId: user.id }, orderBy: { createdAt: 'desc' }, include: { items: true } });
  if (!orders.length) return `<div class="woocommerce-info">Bạn chưa có đơn hàng nào. <a class="woocommerce-Button button" href="/khoa-hoc/">Xem các khóa học</a></div>`;
  let out = '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table"><thead><tr>';
  out += ['Đơn hàng', 'Ngày', 'Trạng thái', 'Tổng cộng', 'Thao tác'].map((h) => `<th scope="col"><span class="nobr">${h}</span></th>`).join('') + '</tr></thead><tbody>';
  for (const o of orders) {
    const url = `/tai-khoan/don-hang/${o.id}/`;
    out += `<tr class="woocommerce-orders-table__row order"><th data-title="Đơn hàng" scope="row"><a href="${url}">#${o.id}</a></th><td data-title="Ngày"><time datetime="${o.createdAt.toISOString()}">${vnDate(o.createdAt)}</time></td><td data-title="Trạng thái">${esc(STATUS[o.status]?.[0] || o.status)}</td><td data-title="Tổng cộng">${money(o.total)} cho ${o.items.length} khóa học</td><td data-title="Thao tác"><a href="${url}" class="woocommerce-button button view">Xem</a></td></tr>`;
  }
  return out + '</tbody></table>';
}

export async function orderDetailHtml(user: User, id: number): Promise<string | null> {
  const o = await db.order.findUnique({ where: { id }, include: { items: { include: { course: true } }, notes: true } });
  if (!o || o.userId !== user.id) return null;
  let out = `<p>Đơn hàng #<mark class="order-number">${o.id}</mark> đặt ngày <mark class="order-date">${vnDate(o.createdAt)}</mark>, hiện đang ở trạng thái: <mark class="order-status">${esc(STATUS[o.status]?.[0] || o.status)}</mark>.</p>`;
  out += '<section class="woocommerce-order-details"><h2 class="woocommerce-order-details__title">Chi tiết đơn hàng</h2><table class="woocommerce-table woocommerce-table--order-details shop_table order_details"><thead><tr><th class="product-name">Khóa học</th><th class="product-total">Tổng cộng</th></tr></thead><tbody>';
  for (const it of o.items) {
    const link = it.course && PAID.includes(o.status) ? ` – <a href="${esc(courseUrl(it.course))}">Vào học</a>` : '';
    out += `<tr class="order_item"><td class="product-name">${esc(it.name)}${link}</td><td class="product-total">${money(it.total)}</td></tr>`;
  }
  out += `</tbody><tfoot><tr><th scope="row">Phương thức thanh toán:</th><td>${esc(o.paymentTitle)}</td></tr><tr><th scope="row">Tổng cộng:</th><td>${money(o.total)}</td></tr>${o.note ? `<tr><th>Ghi chú:</th><td>${esc(o.note)}</td></tr>` : ''}</tfoot></table></section>`;
  out += `<section class="woocommerce-customer-details"><h2 class="woocommerce-column__title">Thông tin thanh toán</h2><address>${esc(`${o.lastName} ${o.firstName}`.trim())}<p class="woocommerce-customer-details--phone">${esc(o.phone)}</p><p class="woocommerce-customer-details--email">${esc(o.email)}</p></address></section>`;
  return out;
}
