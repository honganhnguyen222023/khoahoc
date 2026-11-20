import { notFound, redirect } from 'next/navigation';
import { AccountShell, myCoursesHtml, notice, orderDetailHtml, ordersHtml, withNav } from '@/components/Account';
import { Html } from '@/components/Html';
import { WooCss } from '@/components/Shop';
import { getCurrentUser } from '@/lib/auth';
import { db } from '@/lib/db';
import { esc } from '@/lib/html';
import { icon } from '@/lib/icons';
import { q, type SP } from '@/lib/page';

export const dynamic = 'force-dynamic';
export const metadata = { title: 'Tài khoản', robots: { index: false } };

// Link cũ của WooCommerce → link mới.
const OLD: Record<string, string> = { orders: 'don-hang', 'view-order': 'don-hang', 'edit-account': 'thong-tin', 'edit-address': 'thong-tin', 'lost-password': 'quen-mat-khau', 'customer-logout': 'dang-xuat' };

const Req = () => (
  <>
    &nbsp;<span className="required" aria-hidden="true">*</span>
  </>
);

export default async function AccountPage({ params, searchParams }: { params: Promise<{ p?: string[] }>; searchParams: Promise<SP> }) {
  const p = (await params).p || [];
  const sp = await searchParams;
  const tb = q(sp, 'tb');
  const back = q(sp, 'redirect_to');
  const user = await getCurrentUser();
  if (p[0] && OLD[p[0]]) redirect(`/tai-khoan/${OLD[p[0]]}/${p[1] ? p[1] + '/' : ''}`);
  const sub = p[0] || '';

  /* ---------------- Quên / đặt lại mật khẩu */
  if (sub === 'quen-mat-khau') {
    return (
      <AccountShell title="Quên mật khẩu" crumbs={[['Tài khoản', '/tai-khoan/'], ['Quên mật khẩu']]} tb={tb}>
        {tb !== 'da-gui' && (
              <form method="post" action="/api/form/quen-mat-khau/" className="woocommerce-ResetPassword lost_reset_password">
                <p>Quên mật khẩu? Nhập email hoặc tên đăng nhập, chúng tôi sẽ gửi link tạo mật khẩu mới vào email của bạn.</p>
                <p className="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
                  <label htmlFor="user_login">Tên đăng nhập hoặc email<Req /></label>
                  <input className="woocommerce-Input woocommerce-Input--text input-text" type="text" name="user_login" id="user_login" autoComplete="username" required />
                </p>
                <div className="clear" />
                <p className="woocommerce-form-row form-row">
                  <button type="submit" className="woocommerce-Button button">Đặt lại mật khẩu</button>
                </p>
              </form>
        )}
      </AccountShell>
    );
  }
  if (sub === 'dat-lai-mat-khau') {
    const key = q(sp, 'key');
    const r = key ? await db.passwordReset.findUnique({ where: { id: key } }) : null;
    if (!r || r.expiresAt.getTime() < Date.now()) redirect('/tai-khoan/quen-mat-khau/?tb=het-han');
    return (
      <AccountShell title="Đặt lại mật khẩu" crumbs={[['Tài khoản', '/tai-khoan/'], ['Đặt lại mật khẩu']]} tb={tb}>
            <form method="post" action="/api/form/dat-lai-mat-khau/" className="woocommerce-ResetPassword lost_reset_password">
              <input type="hidden" name="key" value={key} />
              <p>Nhập mật khẩu mới bên dưới.</p>
              <p className="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
                <label htmlFor="password_1">Mật khẩu mới<Req /></label>
                <input type="password" className="woocommerce-Input input-text" name="password_1" id="password_1" autoComplete="new-password" required minLength={6} />
              </p>
              <p className="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
                <label htmlFor="password_2">Nhập lại mật khẩu mới<Req /></label>
                <input type="password" className="woocommerce-Input input-text" name="password_2" id="password_2" autoComplete="new-password" required minLength={6} />
              </p>
              <div className="clear" />
              <p className="woocommerce-form-row form-row">
                <button type="submit" className="woocommerce-Button button">Lưu</button>
              </p>
            </form>
      </AccountShell>
    );
  }

  /* ---------------- Chưa đăng nhập: đăng nhập / đăng ký */
  if (!user) {
    if (sub) redirect('/tai-khoan/?redirect_to=' + encodeURIComponent(`/tai-khoan/${p.join('/')}/`));
    const note = back ? `<div class="kh-login-note">${icon('play', '', 18)}<p>Đăng nhập hoặc <strong>tạo tài khoản miễn phí</strong> để học thử bài đầu tiên.</p></div>` : '';
    return (
      <div className="woocommerce-account woocommerce-page">
        <WooCss />
        <Html html={`<header class="kh-page-head"><div class="kh-container"><nav class="kh-breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Trang chủ</a></li><li aria-current="page">Tài khoản</li></ol></nav><h1 class="kh-h1 kh-h1-sm">Tài khoản</h1></div></header>`} />
        <article className="kh-container kh-page">
          <div className="kh-prose kh-entry">
            <div className="woocommerce">
              <Html html={notice(tb) + note} />
              <div className="u-columns col2-set" id="customer_login">
                <div className="u-column1 col-1">
                  <h2>Đăng nhập</h2>
                  <form className="woocommerce-form woocommerce-form-login login" method="post" action="/api/form/dang-nhap/">
                    <input type="hidden" name="redirect_to" value={back} />
                    <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                      <label htmlFor="username">Email hoặc tên đăng nhập<Req /></label>
                      <input type="text" className="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autoComplete="username" required />
                    </p>
                    <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                      <label htmlFor="password">Mật khẩu<Req /></label>
                      <input className="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autoComplete="current-password" required />
                    </p>
                    <p className="form-row">
                      <label className="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
                        <input className="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span>Ghi nhớ đăng nhập</span>
                      </label>
                      <button type="submit" className="woocommerce-button button woocommerce-form-login__submit">Đăng nhập</button>
                    </p>
                    <p className="woocommerce-LostPassword lost_password">
                      <a href="/tai-khoan/quen-mat-khau/">Quên mật khẩu?</a>
                    </p>
                  </form>
                </div>
                <div className="u-column2 col-2">
                  <h2>Đăng ký</h2>
                  <form className="woocommerce-form woocommerce-form-register register" method="post" action="/api/form/dang-ky/">
                    <input type="hidden" name="redirect_to" value={back} />
                    <p className="woocommerce-form-row form-row form-row-wide">
                      <label htmlFor="kh_full_name">Họ và tên<Req /></label>
                      <input type="text" className="woocommerce-Input input-text" name="kh_full_name" id="kh_full_name" autoComplete="name" required />
                    </p>
                    <p className="woocommerce-form-row form-row form-row-wide">
                      <label htmlFor="kh_phone">Số điện thoại / Zalo<Req /></label>
                      <input type="tel" className="woocommerce-Input input-text" name="kh_phone" id="kh_phone" autoComplete="tel" inputMode="tel" required />
                    </p>
                    <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                      <label htmlFor="reg_email">Địa chỉ email<Req /></label>
                      <input type="email" className="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autoComplete="email" required />
                    </p>
                    <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                      <label htmlFor="reg_password">Mật khẩu<Req /></label>
                      <input type="password" className="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autoComplete="new-password" required minLength={6} />
                    </p>
                    <div className="woocommerce-privacy-policy-text">
                      <p>Thông tin của bạn được dùng để quản lý tài khoản, hỗ trợ việc học trên website và các mục đích khác được nêu trong chính sách bảo mật.</p>
                    </div>
                    <p className="woocommerce-form-row form-row">
                      <button type="submit" className="woocommerce-Button woocommerce-button button woocommerce-form-register__submit">Đăng ký</button>
                    </p>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>
    );
  }

  /* ---------------- Đã đăng nhập */
  if (sub === 'khoa-hoc-cua-toi') {
    return <AccountShell title="Khóa học của tôi" crumbs={[['Tài khoản', '/tai-khoan/'], ['Khóa học của tôi']]} html={withNav('khoa-hoc-cua-toi', await myCoursesHtml(user), tb)} />;
  }
  if (sub === 'don-hang' && p[1]) {
    const detail = await orderDetailHtml(user, parseInt(p[1], 10));
    if (!detail) notFound();
    return <AccountShell title={`Đơn hàng #${p[1]}`} crumbs={[['Tài khoản', '/tai-khoan/'], ['Đơn hàng', '/tai-khoan/don-hang/'], [`#${p[1]}`]]} html={withNav('orders', detail, tb)} />;
  }
  if (sub === 'don-hang') {
    return <AccountShell title="Đơn hàng" crumbs={[['Tài khoản', '/tai-khoan/'], ['Đơn hàng']]} html={withNav('orders', await ordersHtml(user), tb)} />;
  }
  if (sub === 'thong-tin') {
    return (
      <AccountShell title="Thông tin tài khoản" crumbs={[['Tài khoản', '/tai-khoan/'], ['Thông tin tài khoản']]} nav="edit-account" tb={tb}>
            <form className="woocommerce-EditAccountForm edit-account" method="post" action="/api/form/thong-tin/">
              <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label htmlFor="account_display_name">Họ và tên<Req /></label>
                <input type="text" className="woocommerce-Input input-text" name="account_display_name" id="account_display_name" defaultValue={user.name} required />
              </p>
              <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label htmlFor="account_phone">Số điện thoại / Zalo</label>
                <input type="tel" className="woocommerce-Input input-text" name="account_phone" id="account_phone" defaultValue={user.phone} />
              </p>
              <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label htmlFor="account_email">Địa chỉ email<Req /></label>
                <input type="email" className="woocommerce-Input input-text" name="account_email" id="account_email" defaultValue={user.email} autoComplete="email" required />
              </p>
              <fieldset>
                <legend>Đổi mật khẩu</legend>
                <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                  <label htmlFor="password_current">Mật khẩu hiện tại (để trống nếu không đổi)</label>
                  <input type="password" className="woocommerce-Input input-text" name="password_current" id="password_current" autoComplete="current-password" />
                </p>
                <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                  <label htmlFor="password_1">Mật khẩu mới (để trống nếu không đổi)</label>
                  <input type="password" className="woocommerce-Input input-text" name="password_1" id="password_1" autoComplete="new-password" />
                </p>
                <p className="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                  <label htmlFor="password_2">Nhập lại mật khẩu mới</label>
                  <input type="password" className="woocommerce-Input input-text" name="password_2" id="password_2" autoComplete="new-password" />
                </p>
              </fieldset>
              <p>
                <button type="submit" className="woocommerce-Button button">Lưu thay đổi</button>
              </p>
            </form>
      </AccountShell>
    );
  }
  if (sub) notFound();

  // Tổng quan.
  const hello = `<p>Xin chào <strong>${esc(user.name)}</strong> (không phải <strong>${esc(user.name)}</strong>? <a href="/tai-khoan/dang-xuat/">Đăng xuất</a>)</p><p>Tại đây bạn có thể xem <a href="/tai-khoan/don-hang/">đơn hàng</a> và <a href="/tai-khoan/thong-tin/">đổi mật khẩu, thông tin tài khoản</a>.</p>`;
  return <AccountShell title="Tài khoản" html={withNav('dashboard', hello + '<h2 class="kh-account-h">Khóa học của tôi</h2>' + (await myCoursesHtml(user)), tb)} />;
}
