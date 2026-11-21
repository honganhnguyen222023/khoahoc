import { Html } from '@/components/Html';
import { money, shopHead, shopNotice, WooCss } from '@/components/Shop';
import { getCurrentUser } from '@/lib/auth';
import { cartCourses } from '@/lib/cart';
import { esc } from '@/lib/html';
import { courseUrl, hasAccess } from '@/lib/lms';
import { q, type SP } from '@/lib/page';

export const dynamic = 'force-dynamic';
export const metadata = { title: 'Giỏ hàng', robots: { index: false } };

export default async function CartPage({ searchParams }: { searchParams: Promise<SP> }) {
  const sp = await searchParams;
  const user = await getCurrentUser();
  const items = await cartCourses();
  const tb = q(sp, 'tb');
  const added = items.find((c) => String(c.id) === q(sp, 'khoa'));
  let note = '';
  if (tb === 'da-them' && added) note = shopNotice('ok', `“${added.title}” đã được thêm vào giỏ hàng.`);
  if (tb === 'da-bo') note = shopNotice('ok', 'Đã bỏ khóa học khỏi giỏ hàng.');
  if (tb === 'trong') note = shopNotice('err', 'Giỏ hàng chưa có khóa học nào cần thanh toán.');

  if (!items.length) {
    return (
      <div className="woocommerce-cart woocommerce-page">
        <WooCss />
        <Html html={shopHead('Giỏ hàng')} />
        <article className="kh-container kh-page">
          <div className="kh-prose kh-entry">
            <div className="woocommerce" dangerouslySetInnerHTML={{ __html: `<div class="woocommerce-notices-wrapper">${note}</div><div class="wc-empty-cart-message"><div class="cart-empty woocommerce-info" role="status">Giỏ hàng của bạn đang trống.</div></div><p class="return-to-shop"><a class="button wc-backward" href="/khoa-hoc/">Xem các khóa học</a></p>` }} />
          </div>
        </article>
      </div>
    );
  }

  const owned = await Promise.all(items.map((c) => hasAccess(c.id, user)));
  const total = items.reduce((a, c, i) => a + (owned[i] ? 0 : c.price || 0), 0);
  return (
    <div className="woocommerce-cart woocommerce-page">
      <WooCss />
        <Html html={shopHead('Giỏ hàng')} />
      <article className="kh-container kh-page">
        <div className="kh-prose kh-entry">
          <div className="woocommerce">
            <Html html={`<div class="woocommerce-notices-wrapper">${note}</div>`} />
            <table className="shop_table shop_table_responsive cart woocommerce-cart-form__contents">
              <thead>
                <tr>
                  <th className="product-remove"><span className="screen-reader-text">Bỏ</span></th>
                  <th className="product-thumbnail"><span className="screen-reader-text">Ảnh</span></th>
                  <th className="product-name">Khóa học</th>
                  <th className="product-subtotal">Học phí</th>
                </tr>
              </thead>
              <tbody>
                {items.map((c, i) => (
                  <tr key={c.id} className="woocommerce-cart-form__cart-item cart_item">
                    <td className="product-remove">
                      <form method="post" action="/api/form/bo-khoi-gio/">
                        <input type="hidden" name="course" value={c.id} />
                        <button type="submit" className="remove" aria-label={`Bỏ ${c.title} khỏi giỏ`} style={{ border: 0, background: 'none', cursor: 'pointer' }}>×</button>
                      </form>
                    </td>
                    <td className="product-thumbnail">{c.thumbnail || c.cover ? <img src={c.thumbnail || c.cover} alt="" width={96} height={54} style={{ width: 96, borderRadius: 8 }} /> : null}</td>
                    <td className="product-name" data-title="Khóa học">
                      <a href={courseUrl(c)}>{c.title}</a>
                      {owned[i] ? <><br /><small>Bạn đã sở hữu khóa này – sẽ không tính tiền.</small></> : null}
                    </td>
                    <td className="product-subtotal" data-title="Học phí" dangerouslySetInnerHTML={{ __html: owned[i] ? '—' : money(c.price || 0) }} />
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="cart-collaterals">
              <div className="cart_totals">
                <h2>Tổng giỏ hàng</h2>
                <table className="shop_table shop_table_responsive">
                  <tbody>
                    <tr className="order-total">
                      <th>Tổng cộng</th>
                      <td data-title="Tổng cộng" dangerouslySetInnerHTML={{ __html: `<strong>${money(total)}</strong>` }} />
                    </tr>
                  </tbody>
                </table>
                <div className="wc-proceed-to-checkout">
                  <a href="/thanh-toan/" className="checkout-button button alt wc-forward">Tiến hành thanh toán</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </article>
    </div>
  );
}
