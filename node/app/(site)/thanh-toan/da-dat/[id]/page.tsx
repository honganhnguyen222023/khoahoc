import { notFound } from 'next/navigation';
import { Html } from '@/components/Html';
import { money, shopHead, shopNotice, WooCss } from '@/components/Shop';
import { getCurrentUser } from '@/lib/auth';
import { db } from '@/lib/db';
import { esc, vnDate } from '@/lib/html';
import { icon } from '@/lib/icons';
import { courseUrl, isStaff, learnUrl } from '@/lib/lms';
import { orderKey, PAID, STATUS } from '@/lib/orders';
import { q, type SP } from '@/lib/page';
import { vietQr } from '@/lib/payments';
import { getSettings } from '@/lib/settings';

export const dynamic = 'force-dynamic';
export const metadata = { title: 'Đã nhận đơn hàng', robots: { index: false } };

export default async function ThankYou({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<SP> }) {
  const id = parseInt((await params).id, 10);
  const sp = await searchParams;
  const user = await getCurrentUser();
  const o = await db.order.findUnique({ where: { id }, include: { items: { include: { course: true } } } });
  if (!o || (q(sp, 'key') !== orderKey(o) && !(user && (user.id === o.userId || isStaff(user))))) notFound();
  const s = await getSettings();
  const paid = PAID.includes(o.status);

  let html = '';
  if (q(sp, 'loi')) html += shopNotice('err', 'Thanh toán chưa thành công: ' + q(sp, 'loi'));
  if (o.status === 'failed') html += shopNotice('err', 'Thanh toán chưa thành công. Bạn có thể thử lại bên dưới.');
  html += `<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">${paid ? 'Cảm ơn bạn! Đơn hàng đã được thanh toán.' : 'Cảm ơn bạn! Đơn hàng đã được ghi nhận.'}</p>`;
  html += `<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
<li class="woocommerce-order-overview__order order">Mã đơn hàng: <strong>${o.id}</strong></li>
<li class="woocommerce-order-overview__date date">Ngày: <strong>${vnDate(o.createdAt)}</strong></li>
<li class="woocommerce-order-overview__email email">Email: <strong>${esc(o.email)}</strong></li>
<li class="woocommerce-order-overview__total total">Tổng cộng: <strong>${money(o.total)}</strong></li>
<li class="woocommerce-order-overview__payment-method method">Phương thức thanh toán: <strong>${esc(o.paymentTitle)}</strong></li></ul>`;

  const list = `<ul>${o.items.map((it) => `<li><strong>${esc(it.name)}</strong>${paid && it.course ? ` – <a href="${esc(learnUrl(it.course))}">Vào học</a>` : ''}</li>`).join('')}</ul>`;
  if (paid) {
    html += `<section class="kh-thanks is-paid"><h2>Khóa học đã được mở</h2>${list}<a class="kh-btn kh-btn-primary" href="/tai-khoan/khoa-hoc-cua-toi/">Vào khóa học của tôi</a></section>`;
  } else if (['on-hold', 'pending'].includes(o.status)) {
    html += `<section class="kh-thanks is-wait"><h2>Đang chờ xác nhận thanh toán</h2><p>Ngay khi nhận được học phí, chúng tôi sẽ mở khóa học và gửi email báo cho bạn. Bạn vào học tại mục <a href="/tai-khoan/khoa-hoc-cua-toi/">Khóa học của tôi</a>.</p>${list}</section>`;
  }

  // Chuyển khoản: thông tin tài khoản + mã QR.
  if (o.paymentMethod === 'bacs' && !paid && !['cancelled', 'refunded'].includes(o.status)) {
    const info = `KH${o.id}`;
    html += `<section class="woocommerce-bacs-bank-details"><h2 class="wc-bacs-bank-details-heading">Thông tin chuyển khoản</h2>`;
    if (s.bacs.instructions) html += `<p>${esc(s.bacs.instructions)}</p>`;
    for (const a of s.bacs.accounts) {
      const qr = vietQr(a, o.total, info);
      html += `<div class="kh-bank" style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;margin:16px 0;padding:18px;border:1px solid #E3EAE6;border-radius:14px">${qr ? `<img src="${esc(qr)}" alt="Mã QR chuyển khoản" width="220" height="220" style="width:220px;height:auto;border-radius:10px" loading="lazy">` : ''}<ul class="wc-bacs-bank-details order_details bacs_details" style="margin:0"><li class="bank_name">Ngân hàng: <strong>${esc(a.bank_name)}</strong></li><li class="account_number">Số tài khoản: <strong>${esc(a.account_number)}</strong></li><li>Chủ tài khoản: <strong>${esc(a.account_name)}</strong></li><li>Số tiền: <strong>${money(o.total)}</strong></li><li>Nội dung: <strong>${esc(info)}</strong></li></ul></div>`;
    }
    html += `<p>${icon('info', '', 16)} Quét mã QR bằng ứng dụng ngân hàng: số tiền và nội dung được điền sẵn.</p></section>`;
  }

  return (
    <div className="woocommerce-checkout woocommerce-page woocommerce-order-received">
      <WooCss />
      <Html html={shopHead('Đã nhận đơn hàng')} />
      <article className="kh-container kh-page">
        <div className="kh-prose kh-entry">
          <div className="woocommerce">
            <div className="woocommerce-order">
              <Html html={html} />
              {['pending', 'failed'].includes(o.status) && ['vnpay', 'momo'].includes(o.paymentMethod) && user?.id === o.userId ? (
                <form method="post" action="/api/form/thanh-toan-lai/">
                  <input type="hidden" name="order" value={o.id} />
                  <button type="submit" className="kh-btn kh-btn-primary">
                    Thanh toán lại bằng {o.paymentMethod === 'vnpay' ? 'VNPay' : 'MoMo'}
                  </button>
                </form>
              ) : null}
              <Html html={`<p style="margin-top:24px"><a href="/tai-khoan/don-hang/${o.id}/">Xem chi tiết đơn hàng</a> · Trạng thái: ${esc(STATUS[o.status]?.[0] || o.status)}</p>`} />
            </div>
          </div>
        </div>
      </article>
    </div>
  );
}
