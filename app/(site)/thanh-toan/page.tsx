import { Html } from '@/components/Html';
import { money, shopHead, shopNotice, WooCss } from '@/components/Shop';
import { getCurrentUser } from '@/lib/auth';
import { cartCourses } from '@/lib/cart';
import { esc } from '@/lib/html';
import { hasAccess } from '@/lib/lms';
import { q, type SP } from '@/lib/page';
import { momoReady, vnpayReady } from '@/lib/payments';
import { getSettings } from '@/lib/settings';

export const dynamic = 'force-dynamic';
export const metadata = { title: 'Thanh toán', robots: { index: false } };

const ERR: Record<string, string> = {
  'thieu-ten': 'Vui lòng nhập họ và tên.',
  'sai-sdt': 'Số điện thoại chưa đúng.',
  'chon-tt': 'Vui lòng chọn phương thức thanh toán.',
};

export default async function CheckoutPage({ searchParams }: { searchParams: Promise<SP> }) {
  const sp = await searchParams;
  const s = await getSettings();
  const user = await getCurrentUser();
  const items = await cartCourses();
  const head = (
    <>
      <WooCss />
      <Html html={shopHead('Thanh toán')} />
    </>
  );

  if (!items.length) {
    return (
      <div className="woocommerce-checkout woocommerce-page">
        {head}
        <article className="kh-container kh-page">
          <div className="kh-prose kh-entry">
            <div className="woocommerce" dangerouslySetInnerHTML={{ __html: `<div class="woocommerce-info">Giỏ hàng của bạn đang trống.</div><p class="return-to-shop"><a class="button wc-backward" href="/khoa-hoc/">Xem các khóa học</a></p>` }} />
          </div>
        </article>
      </div>
    );
  }

  // Bắt buộc có tài khoản để mua (khóa học gắn với tài khoản).
  if (!user) {
    const back = encodeURIComponent('/thanh-toan/');
    return (
      <div className="woocommerce-checkout woocommerce-page">
        {head}
        <article className="kh-container kh-page">
          <div className="kh-prose kh-entry">
            <div
              className="woocommerce"
              dangerouslySetInnerHTML={{
                __html: `<div class="woocommerce-info">Bạn cần <strong>đăng nhập</strong> hoặc <strong>tạo tài khoản miễn phí</strong> để đặt mua – khóa học sẽ được gắn vào tài khoản của bạn.</div>
<p><a class="kh-btn kh-btn-primary" href="/tai-khoan/?redirect_to=${back}">Đăng nhập / Tạo tài khoản</a></p>`,
              }}
            />
          </div>
        </article>
      </div>
    );
  }

  const owned = await Promise.all(items.map((c) => hasAccess(c.id, user)));
  const buy = items.filter((_, i) => !owned[i]);
  const total = buy.reduce((a, c) => a + (c.price || 0), 0);
  const methods: { id: string; title: string; desc: string }[] = [];
  if (s.bacs.enabled) methods.push({ id: 'bacs', title: 'Chuyển khoản ngân hàng', desc: 'Chuyển khoản theo thông tin (và mã QR) hiện sau khi đặt mua, nội dung ghi mã đơn hàng. Khóa học được mở ngay khi chúng tôi nhận được tiền.' });
  if (s.payments.vnpay && vnpayReady()) methods.push({ id: 'vnpay', title: 'VNPay (thẻ ATM, Visa/Master, QR ngân hàng)', desc: 'Thanh toán qua cổng VNPay. Khóa học tự mở ngay sau khi thanh toán thành công.' });
  if (s.payments.momo && momoReady()) methods.push({ id: 'momo', title: 'Ví MoMo', desc: 'Thanh toán bằng ví MoMo. Khóa học tự mở ngay sau khi thanh toán thành công.' });
  const err = ERR[q(sp, 'tb')] ? shopNotice('err', ERR[q(sp, 'tb')]) : '';
  const nameParts = user.name.split(/\s+/);
  const first = user.firstName || nameParts.at(-1) || '';
  const last = user.lastName || nameParts.slice(0, -1).join(' ');

  const review = `<table class="shop_table woocommerce-checkout-review-order-table"><thead><tr><th class="product-name">Khóa học</th><th class="product-total">Tạm tính</th></tr></thead><tbody>${items
    .map((c, i) => `<tr class="cart_item"><td class="product-name">${esc(c.title)}${owned[i] ? ' <small>(đã sở hữu – không tính tiền)</small>' : ''}</td><td class="product-total">${owned[i] ? '—' : money(c.price || 0)}</td></tr>`)
    .join('')}</tbody><tfoot><tr class="order-total"><th>Tổng cộng</th><td><strong>${money(total)}</strong></td></tr></tfoot></table>`;

  return (
    <div className="woocommerce-checkout woocommerce-page">
      {head}
      <article className="kh-container kh-page">
        <div className="kh-prose kh-entry">
          <div className="woocommerce">
            <Html html={`<div class="woocommerce-notices-wrapper">${err}</div>`} />
            <form name="checkout" className="checkout woocommerce-checkout" method="post" action="/api/form/dat-mua/">
              <div className="col2-set" id="customer_details">
                <div className="col-1">
                  <div className="woocommerce-billing-fields">
                    <h3>Thông tin học viên</h3>
                    <div className="woocommerce-billing-fields__field-wrapper">
                      <p className="form-row form-row-first validate-required">
                        <label htmlFor="billing_last_name">Họ&nbsp;<abbr className="required" title="bắt buộc">*</abbr></label>
                        <input type="text" className="input-text" name="billing_last_name" id="billing_last_name" defaultValue={last} autoComplete="family-name" required />
                      </p>
                      <p className="form-row form-row-last validate-required">
                        <label htmlFor="billing_first_name">Tên&nbsp;<abbr className="required" title="bắt buộc">*</abbr></label>
                        <input type="text" className="input-text" name="billing_first_name" id="billing_first_name" defaultValue={first} autoComplete="given-name" required />
                      </p>
                      <p className="form-row form-row-wide validate-required validate-phone">
                        <label htmlFor="billing_phone">Số điện thoại / Zalo&nbsp;<abbr className="required" title="bắt buộc">*</abbr></label>
                        <input type="tel" className="input-text" name="billing_phone" id="billing_phone" defaultValue={user.phone} autoComplete="tel" required />
                      </p>
                      <p className="form-row form-row-wide validate-required validate-email">
                        <label htmlFor="billing_email">Địa chỉ email&nbsp;<abbr className="required" title="bắt buộc">*</abbr></label>
                        <input type="email" className="input-text" name="billing_email" id="billing_email" defaultValue={user.email} autoComplete="email" required />
                      </p>
                    </div>
                  </div>
                </div>
                <div className="col-2">
                  <div className="woocommerce-additional-fields">
                    <h3>Ghi chú thêm</h3>
                    <p className="form-row notes">
                      <label htmlFor="order_comments">Ghi chú (không bắt buộc)</label>
                      <textarea name="order_comments" className="input-text" id="order_comments" rows={4} placeholder="Ghi chú cho đơn hàng (nếu có)." />
                    </p>
                  </div>
                </div>
              </div>

              <h3 id="order_review_heading">Đơn hàng của bạn</h3>
              <div id="order_review" className="woocommerce-checkout-review-order">
                <Html html={review} />
                <div id="payment" className="woocommerce-checkout-payment">
                  {total > 0 ? (
                    methods.length ? (
                      <ul className="wc_payment_methods payment_methods methods">
                        {methods.map((m, i) => (
                          <li key={m.id} className={`wc_payment_method payment_method_${m.id}`}>
                            <input id={`payment_method_${m.id}`} type="radio" className="input-radio" name="payment_method" value={m.id} defaultChecked={i === 0} />
                            <label htmlFor={`payment_method_${m.id}`}>{m.title}</label>
                            <div className={`payment_box payment_method_${m.id}`}>
                              <p>{m.desc}</p>
                            </div>
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <Html html={shopNotice('info', 'Hiện chưa có phương thức thanh toán. Vui lòng liên hệ với chúng tôi để được hỗ trợ.')} />
                    )
                  ) : null}
                  <div className="form-row place-order">
                    <p className="woocommerce-privacy-policy-text">Thông tin của bạn được dùng để xử lý đơn hàng và hỗ trợ việc học trên website.</p>
                    <button type="submit" className="button alt" id="place_order">
                      {total > 0 ? 'Đặt mua khóa học' : 'Nhận khóa học'}
                    </button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
      </article>
    </div>
  );
}
