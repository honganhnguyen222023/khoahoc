import Script from 'next/script';
import '@/styles/app.css';
import { Html } from '@/components/Html';
import { getCurrentUser } from '@/lib/auth';
import { cartIds } from '@/lib/cart';
import { siteFooter, siteHeader } from '@/lib/layout';
import { cssVars, fontFaces, getSettings, opt } from '@/lib/settings';

const PRELOAD = ['be-vietnam-pro-vietnamese-800-normal.woff2', 'be-vietnam-pro-latin-800-normal.woff2', 'inter-latin-400-normal.woff2', 'inter-vietnamese-400-normal.woff2'];

export default async function SiteLayout({ children }: { children: React.ReactNode }) {
  const s = await getSettings();
  const user = await getCurrentUser();
  const cart = (await cartIds()).length;
  const ga = opt(s, 'ga4_id');
  return (
    <>
      {PRELOAD.map((f) => (
        <link key={f} rel="preload" href={`/fonts/${f}`} as="font" type="font/woff2" crossOrigin="" />
      ))}
      <style dangerouslySetInnerHTML={{ __html: cssVars(s) + fontFaces() }} />
      {opt(s, 'head_code') ? <Html html={opt(s, 'head_code')} /> : null}
      <Html html={siteHeader(s, user, cart)} />
      <main id="main" className="kh-main">
        {children}
      </main>
      <Html html={siteFooter(s)} />
      {/* Nạp 1 lần, chạy sau khi trang tải xong (giống theme WordPress). */}
      <script src="/js/app.js" defer />
      {ga ? (
        <>
          <Script src={`https://www.googletagmanager.com/gtag/js?id=${ga}`} strategy="afterInteractive" />
          <Script id="ga4" strategy="afterInteractive">{`window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','${ga.replace(/[^\w-]/g, '')}');`}</Script>
        </>
      ) : null}
    </>
  );
}
