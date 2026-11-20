import type { Metadata } from 'next';
import { notFound, redirect } from 'next/navigation';
import { CourseDetail } from '@/components/CourseDetail';
import { CourseLearn } from '@/components/CourseLearn';
import { getCurrentUser } from '@/lib/auth';
import { db } from '@/lib/db';
import { courseLessons, isStaff } from '@/lib/lms';
import { q, type SP } from '@/lib/page';

export const dynamic = 'force-dynamic';

type P = { params: Promise<{ slug: string }>; searchParams: Promise<SP> };

async function find(slug: string) {
  return db.course.findUnique({ where: { slug: decodeURIComponent(slug) } });
}

export async function generateMetadata({ params, searchParams }: P): Promise<Metadata> {
  const c = await find((await params).slug);
  const n = parseInt(q(await searchParams, 'bai'), 10);
  const l = c && n ? courseLessons(c)[n] : null;
  if (c && l) return { title: `${l.title.replace(/<[^>]*>/g, '')} – ${c.title}`, robots: { index: false, follow: true } };
  return c ? { title: c.title, description: c.excerpt || undefined, openGraph: { images: c.thumbnail || c.cover ? [c.thumbnail || c.cover] : undefined } } : {};
}

export default async function CoursePage({ params, searchParams }: P) {
  const course = await find((await params).slug);
  const user = await getCurrentUser();
  if (!course || (course.status !== 'publish' && !isStaff(user))) notFound();
  // ?bai=N → trang học bài; số bài sai → về trang khóa học.
  const bai = q(await searchParams, 'bai');
  if (bai !== '') {
    const n = parseInt(bai, 10);
    if (!courseLessons(course)[n]) redirect(`/khoa-hoc/${course.slug}/`);
    return <CourseLearn course={course} user={user} n={n} />;
  }
  return <CourseDetail course={course} user={user} />;
}
