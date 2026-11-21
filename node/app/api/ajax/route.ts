// Trình duyệt (public/js/app.js) báo về khi học: "học xong bài" và "đã xem hết 1 lượt video".
import { getCurrentUser } from '@/lib/auth';
import { db } from '@/lib/db';
import {
  courseLessons, isStaff, learnUrl, lessonMinSeconds, lessonState, markDone, progress, trialLimit, viewCountPlay, viewsCounted, viewsLimit,
} from '@/lib/lms';
import { getSettings } from '@/lib/settings';

const ok = (data: unknown) => Response.json({ success: true, data });
const fail = (data: unknown, status = 400) => Response.json({ success: false, data }, { status });

export async function POST(req: Request) {
  // Chỉ nhận yêu cầu gửi từ chính website (chống trang khác gửi thay).
  const origin = req.headers.get('origin');
  if (origin && new URL(origin).host !== new URL(req.url).host && origin !== process.env.SITE_URL) return fail({ msg: 'Sai nguồn gửi.' }, 403);

  const f = await req.formData();
  const action = String(f.get('action') || '');
  const courseId = parseInt(String(f.get('course') || ''), 10);
  const n = parseInt(String(f.get('lesson') || ''), 10);
  const user = await getCurrentUser();
  const course = courseId ? await db.course.findUnique({ where: { id: courseId } }) : null;
  if (!user || !course) return fail({ msg: 'Bạn cần đăng nhập.' }, 403);
  const lessons = courseLessons(course);
  const lesson = lessons[n];
  if (!lesson) return fail({ msg: 'Không có bài này.' }, 404);
  const s = await getSettings();
  const state = await lessonState(course, lesson, user);

  if (action === 'kh_lesson_done') {
    // "views_used": đang xem dở lượt cuối vẫn được ghi nhận học xong.
    if (!['ok', 'views_used'].includes(state)) return fail({ msg: 'Không học được bài này.' }, 403);
    if (!isStaff(user)) {
      const open = await db.lessonOpen.findUnique({ where: { userId_courseId_lesson: { userId: user.id, courseId, lesson: n } } });
      const from = open ? open.openedAt.getTime() : Date.now();
      const need = lessonMinSeconds(lesson, s, parseInt(String(f.get('dur') || '0'), 10) || 0);
      const wait = need - Math.floor((Date.now() - from) / 1000);
      if (wait > 0) return fail({ msg: 'Bạn học hơi nhanh, hãy xem tiếp bài học.', wait }, 429);
    }
    await markDone(user.id, courseId, n);
    const keys = Object.keys(lessons).map(Number);
    const next = keys[keys.indexOf(n) + 1];
    return ok({ next: next ? learnUrl(course, next) : '', done: Object.keys(await progress(user.id, courseId)).length });
  }

  if (action === 'kh_view_used') {
    if (isStaff(user)) return ok({ left: -1 });
    const tok = String(f.get('tok') || '').replace(/[^a-z0-9]/gi, '').slice(0, 40);
    let kind: 'views' | 'trial';
    let limit: number;
    if (state === 'trial' && trialLimit(s)) {
      kind = 'trial';
      limit = trialLimit(s);
    } else if (state === 'ok' && viewsCounted(lesson, s)) {
      kind = 'views';
      limit = viewsLimit(s);
    } else if (state === 'views_used' || state === 'trial_used') {
      return ok({ left: 0, over: true });
    } else {
      return ok({ left: -1 });
    }
    const rec = await viewCountPlay(kind, user.id, courseId, n, tok);
    return ok({ left: Math.max(0, limit - rec.count), limit });
  }

  return fail({ msg: 'Không rõ yêu cầu.' });
}
