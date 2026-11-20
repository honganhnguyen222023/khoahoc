import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { CourseDetail } from '@/components/CourseDetail';
import { getCurrentUser } from '@/lib/auth';
import { db } from '@/lib/db';
import { isStaff } from '@/lib/lms';
import type { SP } from '@/lib/page';

export const dynamic = 'force-dynamic';

type P = { params: Promise<{ slug: string }>; searchParams: Promise<SP> };

async function find(slug: string) {
  return db.course.findUnique({ where: { slug: decodeURIComponent(slug) } });
}

export async function generateMetadata({ params }: P): Promise<Metadata> {
  const c = await find((await params).slug);
  return c ? { title: c.title, description: c.excerpt || undefined, openGraph: { images: c.thumbnail || c.cover ? [c.thumbnail || c.cover] : undefined } } : {};
}

export default async function CoursePage({ params }: P) {
  const course = await find((await params).slug);
  const user = await getCurrentUser();
  if (!course || (course.status !== 'publish' && !isStaff(user))) notFound();
  return <CourseDetail course={course} user={user} />;
}
