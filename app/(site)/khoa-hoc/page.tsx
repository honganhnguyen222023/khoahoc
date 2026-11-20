import { CourseArchive } from '@/components/CourseArchive';
import type { SP } from '@/lib/page';

export const dynamic = 'force-dynamic';
export const metadata = { title: 'Tất cả khóa học' };

export default async function Courses({ searchParams }: { searchParams: Promise<SP> }) {
  return <CourseArchive sp={await searchParams} />;
}
