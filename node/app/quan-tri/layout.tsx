import '@/styles/quantri.css';
import { cssVars, fontFaces, getSettings } from '@/lib/settings';

export const metadata = { robots: { index: false, follow: false } };

// Trang quản trị: giao diện riêng (quantri.css + quantri.js), không dùng đầu/chân trang của website.
export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const s = await getSettings();
  return (
    <div className="qt-body" style={{ display: 'contents' }}>
      <style dangerouslySetInnerHTML={{ __html: cssVars(s) + fontFaces() + 'body{margin:0;background:var(--qt-bg);color:var(--qt-text);font:15px/1.55 var(--qt-font);-webkit-font-smoothing:antialiased}' }} />
      {children}
      {/* Bỏ thông báo khỏi địa chỉ để tải lại trang không hiện lại. */}
      <script dangerouslySetInnerHTML={{ __html: "try{var u=new URL(location.href);if(u.searchParams.has('tb')){u.searchParams.delete('tb');u.searchParams.delete('ok');history.replaceState(null,'',u.pathname+u.search+u.hash)}}catch(e){}" }} />
      <script src="/js/quantri.js" defer />
    </div>
  );
}
