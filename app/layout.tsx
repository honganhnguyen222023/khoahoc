import type { Metadata, Viewport } from 'next';
import { getSettings, opt } from '@/lib/settings';

export async function generateMetadata(): Promise<Metadata> {
  const s = await getSettings();
  const name = opt(s, 'brand_name');
  return {
    title: { default: `${name} – ${opt(s, 'brand_tagline')}`, template: `%s – ${name}` },
    description: opt(s, 'seo_description'),
    metadataBase: new URL(process.env.SITE_URL || 'http://localhost:3000'),
    openGraph: { siteName: name, locale: 'vi_VN', type: 'website', images: opt(s, 'og_image') ? [opt(s, 'og_image')] : undefined },
  };
}

export const viewport: Viewport = { themeColor: '#ffffff', width: 'device-width', initialScale: 1 };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="vi">
      <body>{children}</body>
    </html>
  );
}
