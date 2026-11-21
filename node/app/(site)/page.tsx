import { notFound } from 'next/navigation';
import { Html } from '@/components/Html';
import { getCurrentUser } from '@/lib/auth';
import { renderBlocks } from '@/lib/blocks';
import { db } from '@/lib/db';
import { parseBlocks } from '@/lib/lms';
import { q, type SP } from '@/lib/page';
import { getSettings } from '@/lib/settings';

export const dynamic = 'force-dynamic';

// Trang chủ: trang được đánh dấu "isFront".
export default async function Home({ searchParams }: { searchParams: Promise<SP> }) {
  const sp = await searchParams;
  const page = await db.page.findFirst({ where: { type: 'page', isFront: true, status: 'publish' } });
  if (!page) notFound();
  const html = await renderBlocks(parseBlocks(page.blocks), { s: await getSettings(), user: await getCurrentUser(), path: '/', formStatus: q(sp, 'kh_form') });
  return (
    <article className="kh-landing">
      <Html html={html} />
    </article>
  );
}
