// Học viên & quyền xem bài học – viết lại từ inc/lms.php của theme WordPress.
//
// - Học viên đã mua (hoặc được mở) xem mọi bài của khóa trong thời hạn học.
// - Chưa mua: chỉ xem bài học thử (bài 1 + bài đánh dấu "Cho học thử"), có giới hạn lượt.
// - Học tuần tự: xong bài trước mới mở bài sau.
// - Lượt xem: 1 lượt = 1 lần xem hết video (theo dõi được); Drive/Bunny: tính khi mở trang.
import { cache } from 'react';
import type { Course, User } from '@prisma/client';
import { db } from './db';
import { getSettings, opt, type Settings } from './settings';
import { youtubeId, esc } from './html';

export type Block = { name: string | null; attrs: Record<string, unknown>; inner: Block[]; content: (string | null)[] };

export type Lesson = { n: number; title: string; duration: string; type: 'video' | 'doc'; src: string; free: boolean; chapter: string };

export type AccessInfo = { active: boolean; expires: number; via: number }; // expires: ms (0 = trọn đời)

export type LessonState = 'ok' | 'trial' | 'login' | 'buy' | 'expired' | 'sequence' | 'trial_used' | 'views_used';

const TRIAL_SESSION_MS = 30 * 60 * 1000;

export function isStaff(user?: Pick<User, 'role'> | null): boolean {
  return !!user && (user.role === 'admin' || user.role === 'staff');
}

export function parseBlocks(json: string | null | undefined): Block[] {
  try {
    const v = JSON.parse(json || '[]');
    return Array.isArray(v) ? v : [];
  } catch {
    return [];
  }
}

const strip = (s: unknown) => String(s ?? '').replace(/<[^>]*>/g, '');

/** Danh sách bài học (đánh số từ 1 theo thứ tự trong giáo trình). */
export function courseLessons(course: Pick<Course, 'blocks' | 'video'>): Record<number, Lesson> {
  const list: Record<number, Lesson> = {};
  let i = 0;
  const intro = course.video || '';
  const walk = (blocks: Block[], chapter: string) => {
    for (const b of blocks) {
      if (b.name === 'kh/chapter') {
        walk(b.inner || [], strip(b.attrs?.title));
      } else if (b.name === 'kh/lesson') {
        const a = b.attrs || {};
        const video = String(a.video ?? '').trim();
        const legacy = String(a.url ?? '').trim();
        const src = video || legacy;
        i++;
        list[i] = {
          n: i,
          title: String(a.title ?? ''),
          duration: String(a.duration ?? ''),
          type: a.type === 'doc' ? 'doc' : 'video',
          src: src.startsWith('#') ? intro : src,
          free: !!a.free || (!video && !!legacy),
          chapter,
        };
      } else if (b.inner?.length) {
        walk(b.inner, chapter);
      }
    }
  };
  walk(parseBlocks(course.blocks), '');
  if (list[1]) list[1].free = true; // Bài đầu tiên luôn được học thử.
  return list;
}

export function bundleIds(course: Pick<Course, 'bundle'>): number[] {
  return String(course.bundle || '').split(',').map((x) => parseInt(x, 10)).filter((x) => x > 0);
}

/** Thời hạn học (tháng, 0 = trọn đời). */
export function courseMonths(course: Pick<Course, 'duration'> | null, s: Settings): number {
  let v = course?.duration ?? '';
  if (v === '') v = opt(s, 'course_duration');
  return v === 'life' ? 0 : Math.max(0, parseInt(v, 10) || 0);
}

export function durationLabel(course: Pick<Course, 'duration'> | null, s: Settings): string {
  const m = courseMonths(course, s);
  return m ? `${m} tháng` : 'Trọn đời';
}

function addMonths(d: Date, months: number): number {
  const x = new Date(d);
  x.setMonth(x.getMonth() + months);
  return x.getTime();
}

/** Quyền của học viên với từng khóa (gồm khóa con trong combo). */
export const accessList = cache(async (userId: number): Promise<Record<number, AccessInfo>> => {
  if (!userId) return {};
  const s = await getSettings();
  const rows = await db.access.findMany({ where: { userId }, include: { course: true } });
  const all = await db.course.findMany({ select: { id: true } });
  const exists = new Set(all.map((c) => c.id));
  const out: Record<number, AccessInfo> = {};
  const now = Date.now();
  for (const r of rows) {
    const months = courseMonths(r.course, s);
    const expires = months ? addMonths(r.since, months) : 0;
    const active = !expires || expires > now;
    for (const id of [r.courseId, ...bundleIds(r.course)]) {
      if (!exists.has(id)) continue;
      const cur = out[id];
      const better = !cur || (active && !cur.active) || (active === cur.active && (expires === 0 ? cur.expires !== 0 : cur.expires !== 0 && expires > cur.expires));
      if (better) out[id] = { active, expires, via: r.courseId };
    }
  }
  return out;
});

export async function accessInfo(courseId: number, userId?: number | null): Promise<AccessInfo | null> {
  if (!userId) return null;
  return (await accessList(userId))[courseId] ?? null;
}

export async function hasAccess(courseId: number, user?: User | null): Promise<boolean> {
  if (!user || !courseId) return false;
  if (isStaff(user)) return true;
  const a = await accessInfo(courseId, user.id);
  return !!a?.active;
}

export async function accessExpired(courseId: number, user?: User | null): Promise<boolean> {
  if (!user || isStaff(user)) return false;
  const a = await accessInfo(courseId, user.id);
  return !!a && !a.active;
}

/** Mở khóa học. Khóa đã hết hạn thì mở lại từ đầu (xóa tiến độ cũ). */
export async function grantCourse(userId: number, courseId: number, source = 'admin'): Promise<boolean> {
  const s = await getSettings();
  const course = await db.course.findUnique({ where: { id: courseId } });
  if (!course) return false;
  const cur = await db.access.findUnique({ where: { userId_courseId: { userId, courseId } } });
  if (cur) {
    const months = courseMonths(course, s);
    const expires = months ? addMonths(cur.since, months) : 0;
    if (!expires || expires > Date.now()) return false; // Còn hạn: giữ nguyên.
  }
  await db.access.upsert({
    where: { userId_courseId: { userId, courseId } },
    update: { source, since: new Date() },
    create: { userId, courseId, source, since: new Date() },
  });
  for (const id of [courseId, ...bundleIds(course)]) await resetProgress(userId, id);
  return true;
}

/** Gia hạn từ hôm nay, giữ tiến độ. */
export async function renewCourse(userId: number, courseId: number) {
  await db.access.updateMany({ where: { userId, courseId }, data: { since: new Date() } });
}

/** Thu hồi (có source: chỉ thu hồi khi mở từ đúng nguồn đó). */
export async function revokeCourse(userId: number, courseId: number, source?: string) {
  const cur = await db.access.findUnique({ where: { userId_courseId: { userId, courseId } } });
  if (!cur || (source !== undefined && cur.source !== source)) return;
  await db.access.delete({ where: { userId_courseId: { userId, courseId } } });
}

export async function progress(userId: number, courseId: number): Promise<Record<number, Date>> {
  const rows = await db.progress.findMany({ where: { userId, courseId } });
  return Object.fromEntries(rows.map((r) => [r.lesson, r.doneAt]));
}

export async function markDone(userId: number, courseId: number, lesson: number) {
  await db.progress.upsert({
    where: { userId_courseId_lesson: { userId, courseId, lesson } },
    update: {},
    create: { userId, courseId, lesson },
  });
}

export async function resetProgress(userId: number, courseId: number) {
  await db.progress.deleteMany({ where: { userId, courseId } });
  await db.lessonOpen.deleteMany({ where: { userId, courseId } });
  await viewsReset(userId, courseId);
}

export function sequential(s: Settings): boolean {
  return opt(s, 'sequential') === '1';
}

/** Bài xa nhất học viên được học (bài đầu tiên chưa xong). Infinity = đã xong hết. */
export async function firstOpenLesson(course: Course, userId: number): Promise<number> {
  const done = await progress(userId, course.id);
  for (const l of Object.values(courseLessons(course))) {
    if (l.src && !done[l.n]) return l.n;
  }
  return Number.POSITIVE_INFINITY;
}

export function durationSeconds(text: string): number {
  const parts = String(text || '').replace(/[^\d:]/g, '').split(':').map((x) => parseInt(x, 10) || 0);
  const sec = parts.reduce((a, p) => a * 60 + p, 0);
  return parts.length > 1 ? sec : 0;
}

export function watchNeed(s: Settings): number {
  const p = parseInt(opt(s, 'watch_percent'), 10);
  return Math.max(10, Math.min(100, p || 80)) / 100;
}

/** Thời gian tối thiểu (giây) phải mở bài trước khi tính là học xong (xem 2x). */
export function lessonMinSeconds(lesson: Lesson, s: Settings, real = 0): number {
  let sec = durationSeconds(lesson.duration);
  if (real >= 30 && (!sec || real < sec)) sec = sec ? real : 0;
  return Math.floor(sec * watchNeed(s) * 0.45);
}

/* ---------------------------------------------------------------- Lượt xem */

export function trialLimit(s: Settings): number {
  return Math.max(0, parseInt(opt(s, 'trial_views'), 10) || 0);
}

export function viewsLimit(s: Settings): number {
  return Math.max(0, parseInt(opt(s, 'lesson_views'), 10) || 0);
}

export async function viewRecord(kind: 'views' | 'trial', userId: number, courseId: number, lesson: number) {
  const r = await db.viewRecord.findUnique({ where: { userId_courseId_lesson_kind: { userId, courseId, lesson, kind } } });
  return { count: r?.count ?? 0, last: r?.last?.getTime() ?? 0, grace: !!r?.grace, tok: r?.tok ?? '' };
}

async function viewAllowed(kind: 'views' | 'trial', limit: number, userId: number, courseId: number, lesson: number) {
  if (!limit) return true;
  const r = await viewRecord(kind, userId, courseId, lesson);
  return r.count < limit || (r.grace && Date.now() - r.last < TRIAL_SESSION_MS);
}

/** Ghi 1 lượt khi MỞ TRANG (video không theo dõi được). */
export async function viewRegisterOpen(kind: 'views' | 'trial', userId: number, courseId: number, lesson: number) {
  const r = await viewRecord(kind, userId, courseId, lesson);
  if (r.grace && Date.now() - r.last < TRIAL_SESSION_MS) return;
  await db.viewRecord.upsert({
    where: { userId_courseId_lesson_kind: { userId, courseId, lesson, kind } },
    update: { count: r.count + 1, last: new Date(), grace: true },
    create: { userId, courseId, lesson, kind, count: 1, last: new Date(), grace: true },
  });
}

/** Ghi 1 lượt khi XEM HẾT video (mỗi lần xem có mã riêng, gửi lặp không tính thêm). */
export async function viewCountPlay(kind: 'views' | 'trial', userId: number, courseId: number, lesson: number, tok: string) {
  const r = await viewRecord(kind, userId, courseId, lesson);
  if (tok && tok === r.tok) return r;
  await db.viewRecord.upsert({
    where: { userId_courseId_lesson_kind: { userId, courseId, lesson, kind } },
    update: { count: r.count + 1, last: new Date(), grace: false, tok },
    create: { userId, courseId, lesson, kind, count: 1, last: new Date(), grace: false, tok },
  });
  return viewRecord(kind, userId, courseId, lesson);
}

export async function viewsReset(userId: number, courseId: number, lesson = 0) {
  await db.viewRecord.deleteMany({ where: { userId, courseId, kind: 'views', ...(lesson ? { lesson } : {}) } });
}

export function viewsCounted(lesson: Lesson, s: Settings): boolean {
  return viewsLimit(s) > 0 && lesson.type !== 'doc' && !!lesson.src;
}

export async function viewsLeft(userId: number, courseId: number, lesson: Lesson, s: Settings): Promise<number> {
  if (!viewsCounted(lesson, s)) return -1;
  return Math.max(0, viewsLimit(s) - (await viewRecord('views', userId, courseId, lesson.n)).count);
}

export async function trialLeft(userId: number, courseId: number, n: number, s: Settings): Promise<number> {
  const limit = trialLimit(s);
  return limit ? Math.max(0, limit - (await viewRecord('trial', userId, courseId, n)).count) : -1;
}

export async function viewsUsedCount(userId: number, courseId: number, s: Settings): Promise<number> {
  const limit = viewsLimit(s);
  if (!limit) return 0;
  return db.viewRecord.count({ where: { userId, courseId, kind: 'views', count: { gte: limit } } });
}

/* ---------------------------------------------------------- Trạng thái bài */

export async function lessonState(course: Course, lesson: Lesson, user: User | null): Promise<LessonState> {
  const s = await getSettings();
  if (!user) return lesson.free ? 'login' : 'buy';
  if (isStaff(user)) return 'ok';
  if (await hasAccess(course.id, user)) {
    if (sequential(s) && lesson.n > (await firstOpenLesson(course, user.id))) return 'sequence';
    if (viewsCounted(lesson, s) && !(await viewAllowed('views', viewsLimit(s), user.id, course.id, lesson.n))) return 'views_used';
    return 'ok';
  }
  if (await accessExpired(course.id, user)) return 'expired';
  if (lesson.free) return (await viewAllowed('trial', trialLimit(s), user.id, course.id, lesson.n)) ? 'trial' : 'trial_used';
  return 'buy';
}

/* ---------------------------------------------------------- Trình phát video */

/** Cách theo dõi tiến độ: yt | vimeo | video | manual. */
export function videoTrackType(src: string, type: string): 'yt' | 'vimeo' | 'video' | 'manual' {
  if (type === 'doc') return 'manual';
  if (youtubeId(src)) return 'yt';
  if (/vimeo\.com\/(?:video\/)?\d+/i.test(src)) return 'vimeo';
  if (/\.(mp4|webm|ogv|mov)(\?|$)/i.test(src)) return 'video';
  return 'manual';
}

export function viewTrackable(lesson: Lesson): boolean {
  return !!lesson.src && lesson.type !== 'doc' && ['yt', 'vimeo', 'video'].includes(videoTrackType(lesson.src, lesson.type));
}

/** Khung phát video/tài liệu: YouTube, Vimeo, file video, Google Drive, link nhúng. */
export function videoEmbed(srcIn: string, title: string, siteUrl: string): string {
  let src = String(srcIn || '').trim();
  const ifr = src.match(/<iframe[^>]+src=["']([^"']+)/i);
  if (ifr) src = ifr[1].replace(/&amp;/g, '&');
  if (!src) return '';
  const t = esc(strip(title));
  const iframe = (url: string) => `<iframe src="${esc(url)}" title="${t}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>`;
  const yt = youtubeId(src);
  if (yt) {
    const url = `https://www.youtube.com/embed/${yt}?rel=0&playsinline=1&enablejsapi=1&origin=${encodeURIComponent(siteUrl.replace(/\/$/, ''))}`;
    return `<iframe id="kh-yt-player" src="${esc(url)}" title="${t}" style="background:#000 url(https://i.ytimg.com/vi/${yt}/hqdefault.jpg) center/cover no-repeat" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>`;
  }
  const vm = src.match(/vimeo\.com\/(?:video\/)?(\d+)(?:\/([0-9a-f]+))?/i);
  if (vm && !src.includes('player.vimeo.com')) return iframe(`https://player.vimeo.com/video/${vm[1]}${vm[2] ? '?h=' + vm[2] : ''}`);
  const gd = src.match(/drive\.google\.com\/file\/d\/([\w-]+)/);
  if (gd) return iframe(`https://drive.google.com/file/d/${gd[1]}/preview`);
  if (/\.(mp4|webm|ogv|mov)(\?|$)/i.test(src)) return `<video src="${esc(src)}" controls controlslist="nodownload" playsinline preload="metadata" oncontextmenu="return false"></video>`;
  return iframe(src);
}

/* ---------------------------------------------------------- Đường dẫn */

export function courseUrl(c: Pick<Course, 'slug'>): string {
  return `/khoa-hoc/${encodeURIComponent(c.slug)}/`;
}

export function learnUrl(c: Pick<Course, 'slug' | 'blocks' | 'video'>, n = 0): string {
  const lessons = courseLessons(c);
  const keys = Object.keys(lessons).map(Number);
  if (!keys.length) return '/tai-khoan/khoa-hoc-cua-toi/';
  return `${courseUrl(c)}?bai=${n && lessons[n] ? n : keys[0]}`;
}

export function loginUrl(back = ''): string {
  return '/tai-khoan/' + (back ? `?redirect_to=${encodeURIComponent(back)}` : '');
}

/** "Học tiếp": bài đầu tiên chưa học xong. */
export async function continueUrl(course: Course, userId: number): Promise<string> {
  const n = userId ? await firstOpenLesson(course, userId) : 0;
  return learnUrl(course, Number.isFinite(n) ? n : 0);
}
