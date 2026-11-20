import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { Html } from '@/components/Html';
import { getCurrentUser } from '@/lib/auth';
import { isLanding, renderBlocks } from '@/lib/blocks';
import { db } from '@/lib/db';
import { esc, vnDate } from '@/lib/html';
import { isStaff, parseBlocks } from '@/lib/lms';
import { q, type SP } from '@/lib/page';
import { getSettings } from '@/lib/settings';
import { pageHeader } from '@/lib/templates';

export const dynamic = 'force-dynamic';

type P = { params: Promise<{ slug: string }>; searchParams: Promise<SP> };

async function find(slug: string) {
  const s = decodeURIComponent(slug);
  return (await db.page.findFirst({ where: { slug: s, type: 'page' } })) ?? (await db.page.findFirst({ where: { slug: s, type: 'post' } }));
}

export async function generateMetadata({ params }: P): Promise<Metadata> {
  const p = await find((await params).slug);
  return p ? { title: p.title, description: p.excerpt || undefined } : {};
}

// Trang con (Giới thiệu, Liên hệ…) và bài viết.
export default async function PageView({ params, searchParams }: P) {
  const { slug } = await params;
  const sp = await searchParams;
  const page = await find(slug);
  const user = await getCurrentUser();
  if (!page || (page.status !== 'publish' && !isStaff(user))) notFound();
  const blocks = parseBlocks(page.blocks);
  const path = `/${page.slug}/`;
  const html = await renderBlocks(blocks, { s: await getSettings(), user, path, formStatus: q(sp, 'kh_form') });

  if (page.type === 'page' && isLanding(blocks)) {
    return (
      <article className="kh-landing">
        <h1 className="screen-reader-text">{page.title}</h1>
        <Html html={html} />
      </article>
    );
  }
  const head = pageHeader(page.title, '', [[page.title]]);
  const meta = page.type === 'post' ? `<p class="kh-post-meta">${esc(vnDate(page.createdAt))}</p>${page.thumbnail ? `<figure class="kh-post-thumb"><img src="${esc(page.thumbnail)}" alt="" fetchpriority="high"></figure>` : ''}` : '';
  return (
    <>
      <Html html={head} />
      <article className="kh-container kh-page">
        <Html html={meta} />
        <div className="kh-prose kh-entry" dangerouslySetInnerHTML={{ __html: html }} />
      </article>
    </>
  );
}
