import { notFound } from 'next/navigation';
import { CourseArchive } from '@/components/CourseArchive';
import { db } from '@/lib/db';
import type { SP } from '@/lib/page';

export const dynamic = 'force-dynamic';

export default async function Category({ params, searchParams }: { params: Promise<{ slug: string }>; searchParams: Promise<SP> }) {
  const slug = decodeURIComponent((await params).slug);
  if (!(await db.category.findUnique({ where: { slug } }))) notFound();
  return <CourseArchive sp={await searchParams} termSlug={slug} />;
}
