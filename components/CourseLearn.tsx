// Trang học 1 bài: /khoa-hoc/<khoa>/?bai=N  (viết lại từ template-parts/course-learn.php)
import type { Course, User } from '@prisma/client';
import { redirect } from 'next/navigation';
import { Html } from '@/components/Html';
import { courseView } from '@/lib/courses';
import { db } from '@/lib/db';
import { esc, linkAttrs, rich, vnDate, youtubeId } from '@/lib/html';
import { icon } from '@/lib/icons';
import {
  courseLessons, courseUrl, firstOpenLesson, isStaff, learnUrl, lessonMinSeconds, lessonState, loginUrl, progress, sequential,
  trialLeft, trialLimit, videoEmbed, videoTrackType, viewRecord, viewRegisterOpen, viewTrackable, viewsCounted, viewsLimit, watchNeed,
} from '@/lib/lms';
import { getSettings } from '@/lib/settings';
import { breadcrumb, lessonRowParts } from '@/lib/templates';

export async function CourseLearn({ course, user, n }: { course: Course; user: User | null; n: number }) {
  const s = await getSettings();
  const lessons = courseLessons(course);
  const keys = Object.keys(lessons).map(Number);
  const lesson = lessons[n];
  const state = await lessonState(course, lesson, user);
  const site = process.env.SITE_URL || 'http://localhost:3000';

  // Bài học thử: cần đăng nhập rồi quay lại đúng bài này.
  if (state === 'login') redirect(loginUrl(learnUrl(course, n)));

  // Video không theo dõi được (Drive, Bunny…): tính lượt khi mở trang. Theo dõi được: tính khi xem hết.
  const byOpen = !viewTrackable(lesson);
  if (user && state === 'trial' && byOpen) await viewRegisterOpen('trial', user.id, course.id, n);
  if (user && state === 'ok' && byOpen && !isStaff(user) && viewsCounted(lesson, s)) await viewRegisterOpen('views', user.id, course.id, n);
  if (user && state === 'ok') {
    await db.lessonOpen.upsert({ where: { userId_courseId_lesson: { userId: user.id, courseId: course.id, lesson: n } }, update: {}, create: { userId: user.id, courseId: course.id, lesson: n } });
  }

  const c = await courseView(course, user, s);
  const canView = state === 'ok' || state === 'trial';
  const pos = keys.indexOf(n);
  const prev = pos > 0 ? lessons[keys[pos - 1]] : null;
  const next = pos < keys.length - 1 ? lessons[keys[pos + 1]] : null;
  const prog = user ? await progress(user.id, course.id) : {};
  const isDone = !!prog[n];
  const withSrc = Object.values(lessons).filter((l) => l.src);
  const doneN = withSrc.filter((l) => prog[l.n]).length;
  const freeN = Object.values(lessons).filter((l) => l.free).length;
  const track = canView && lesson.src && user ? videoTrackType(lesson.src, lesson.type) : '';
  const byPlay = !!track && viewTrackable(lesson) && !isStaff(user);
  const nextWait = !!next && state === 'ok' && !isDone && sequential(s) && !isStaff(user) && !!lesson.src;
  const priceTxt = c.priceHtml ? ' · ' + c.priceHtml : '';
  const viewsMax = viewsLimit(s);
  const viewsRec = state === 'ok' && user && !isStaff(user) && viewsCounted(lesson, s) ? await viewRecord('views', user.id, course.id, n) : null;
  let viewsLeft = -1;
  if (byPlay && state === 'trial' && trialLimit(s) && user) viewsLeft = await trialLeft(user.id, course.id, n, s);
  else if (byPlay && viewsRec) viewsLeft = Math.max(0, viewsMax - viewsRec.count);

  let main = '';
  // Dòng thông báo trên video.
  if (state === 'trial' && user) {
    const left = await trialLeft(user.id, course.id, n, s);
    let t: string;
    if (left < 0) t = 'Bạn đang học thử bài này.';
    else if (byPlay) t = left === 1 ? 'Bạn đang học thử. Đây là lượt xem thử cuối cùng của bài này.' : `Bạn đang học thử. Còn ${left} lượt xem thử (xem hết video tính 1 lượt).`;
    else if (left > 0) t = `Bạn đang học thử. Sau lượt này còn ${left} lượt xem thử.`;
    else t = 'Đây là lượt xem thử cuối cùng của bạn. Tải lại trang trong 30 phút vẫn xem được.';
    main += `<p class="kh-learn-flag">${icon('info', '', 16)} ${esc(t)}</p>`;
  } else if (state === 'ok' && !isStaff(user) && (viewsRec || c.access?.expires)) {
    const bits: string[] = [];
    if (viewsRec && byPlay) bits.push(viewsLeft === 1 ? 'Đây là lượt xem cuối cùng của bài này: xem hết video thì bài sẽ khóa.' : `Bạn còn ${viewsLeft}/${viewsMax} lượt xem bài này (xem hết video tính 1 lượt).`);
    else if (viewsRec) bits.push(viewsRec.count >= viewsMax ? `Lượt xem cuối cùng (${viewsRec.count}/${viewsMax}) của bài này. Tải lại trang trong 30 phút vẫn xem được.` : `Lượt xem ${viewsRec.count}/${viewsMax} của bài này.`);
    if (c.access?.expires) bits.push('Hạn học đến ' + vnDate(c.access.expires) + '.');
    main += `<p class="kh-learn-flag is-soft">${icon(viewsRec ? 'eye' : 'calendar', '', 16)} ${esc(bits.join(' '))}</p>`;
  }

  // Khung video / khóa.
  const data = track
    ? ` data-track="${track}" data-course="${course.id}" data-lesson="${n}" data-need="${watchNeed(s)}" data-done="${isDone ? 1 : 0}" data-min="${lessonMinSeconds(lesson, s)}" data-ajax="/api/ajax/" data-nonce="ok" data-mode="${state}" data-views-left="${viewsLeft}" data-course-url="${esc(courseUrl(course))}"`
    : '';
  const lock = (ic: string, title: string, text: string, actions = '', extra = '') =>
    `<div class="kh-learn-lock"><span class="kh-learn-lock-icon">${icon(ic, '', 30)}</span><p class="kh-learn-lock-title">${title}</p><p>${text}</p>${actions ? `<div class="kh-learn-lock-actions">${actions}</div>` : ''}${extra}</div>`;
  const buyBtns = `<a class="kh-btn kh-btn-primary" ${linkAttrs(c.register)}>Đăng ký học${esc(priceTxt)}</a>${c.cartUrl ? `<a class="kh-btn kh-btn-outline" href="${esc(c.cartUrl)}">${icon('shopping-cart', '', 16)} Thêm vào giỏ</a>` : ''}`;
  let player: string;
  if (canView && lesson.src && lesson.type !== 'doc') player = videoEmbed(lesson.src, lesson.title, site);
  else if (canView && lesson.src) player = lock('file-text', 'Tài liệu bài học', '', `<a class="kh-btn kh-btn-primary" href="${esc(lesson.src)}" target="_blank" rel="noopener">${icon('external-link', '', 16)} Mở tài liệu</a>`).replace('<p></p>', '');
  else if (canView) player = lock('clock', 'Bài học đang được cập nhật', 'Video sẽ có trong thời gian sớm nhất. Bạn học tiếp bài khác nhé.');
  else if (state === 'sequence') {
    const go = await firstOpenLesson(course, user!.id);
    player = lock('lock', 'Học xong bài trước để mở bài này', `Khóa học đi theo lộ trình: xem hết bài ${go} thì bài tiếp theo tự mở.`, `<a class="kh-btn kh-btn-primary" href="${esc(learnUrl(course, go))}">${icon('play', '', 16)} Học bài ${go}</a>`);
  } else if (state === 'views_used') {
    const nextOk = next && ['ok', 'trial'].includes(await lessonState(course, next, user));
    player = lock('lock', `Bạn đã xem bài này ${viewsMax} lần`, `Mỗi video bài học được xem tối đa ${viewsMax} lần. Cần xem lại, bạn liên hệ để được mở thêm lượt xem.`, `${nextOk ? `<a class="kh-btn kh-btn-primary" href="${esc(learnUrl(course, next!.n))}">${icon('play', '', 16)} Học bài tiếp theo</a>` : ''}<a class="kh-btn kh-btn-outline" href="/lien-he/">Liên hệ</a>`);
  } else if (state === 'trial_used') {
    player = lock('lock', `Bạn đã dùng hết ${trialLimit(s)} lượt xem thử`, `Đăng ký khóa học để xem lại bài này và mở toàn bộ ${keys.length} bài.`, buyBtns);
  } else if (state === 'expired') {
    player = lock('calendar', `Khóa học đã hết hạn${c.access?.expires ? ' ngày ' + vnDate(c.access.expires) : ''}`, `Mua lại để học lại từ đầu, thời hạn ${c.durationTxt.toLowerCase()}.`, `<a class="kh-btn kh-btn-primary" ${linkAttrs(c.register)}>Mua lại${esc(priceTxt)}</a>`);
  } else {
    player = lock('lock', 'Bài học này dành cho học viên của khóa', `Đăng ký khóa học để mở toàn bộ ${keys.length} bài, thời hạn học ${c.durationTxt.toLowerCase()}.`, buyBtns, user ? '' : `<p class="kh-learn-lock-login">Đã mua khóa này? <a href="${esc(loginUrl(learnUrl(course, n)))}">Đăng nhập</a></p>`);
  }
  main += `<div class="kh-learn-player${canView ? '' : ' is-locked'}"${data}>${player}</div>`;

  // Thanh tiến độ xem.
  if (track && state === 'ok') {
    main += `<div class="kh-learn-status${isDone ? ' is-done' : ''}" data-status aria-live="polite">`;
    if (isDone) main += `${icon('check', '', 18)} <span>Bạn đã học xong bài này.</span>`;
    else if (track === 'manual') main += '<span>Học xong bài này rồi bấm nút bên cạnh để mở bài tiếp theo.</span><button type="button" class="kh-btn kh-btn-primary kh-btn-sm" data-done-btn disabled>Đã học xong</button>';
    else main += `<span data-status-text>${esc(`Xem hết video để mở bài tiếp theo (cần xem ${Math.round(watchNeed(s) * 100)}%).`)}</span><span class="kh-learn-bar"><span data-status-bar style="width:0%"></span></span>`;
    main += '</div>';
  }

  // Thông tin bài.
  const showYt = canView && lesson.type !== 'doc' && youtubeId(lesson.src) && viewsLeft < 0 && !viewsRec && !(state === 'trial' && trialLimit(s));
  main += `<div class="kh-learn-info">${lesson.chapter ? `<p class="kh-learn-chapter">${esc(lesson.chapter)}</p>` : ''}<h1 class="kh-learn-title">Bài ${n}: ${rich(lesson.title)}</h1>${lesson.duration ? `<p class="kh-learn-time">${icon('clock', '', 15)} ${esc(lesson.duration)}</p>` : ''}${showYt ? `<p class="kh-learn-help">Video không chạy? <a href="https://www.youtube.com/watch?v=${youtubeId(lesson.src)}" target="_blank" rel="noopener">Mở trên YouTube</a> (hoặc tắt trình chặn quảng cáo cho trang này).</p>` : ''}</div>`;
  main += `<nav class="kh-learn-nav" aria-label="Chuyển bài">${prev ? `<a class="kh-btn kh-btn-outline kh-btn-sm" href="${esc(learnUrl(course, prev.n))}" rel="prev">${icon('chevron-left', '', 16)} Bài trước</a>` : '<span></span>'}${
    next ? `<a class="kh-btn kh-btn-primary kh-btn-sm${nextWait ? ' is-disabled' : ''}" href="${esc(learnUrl(course, next.n))}" rel="next"${nextWait ? ' data-next-wait aria-disabled="true" title="Học xong bài này để mở bài tiếp theo"' : ''}>${nextWait ? icon('lock', '', 14) + ' ' : ''}Bài tiếp theo ${icon('chevron-right', '', 16)}</a>` : ''
  }</nav>`;
  if (state === 'trial') main += `<div class="kh-learn-upsell"><div><p class="kh-learn-upsell-title">Bạn đang học thử</p><p>Thích cách dạy này? Đăng ký để mở ${keys.length - freeN} bài còn lại.</p></div><a class="kh-btn kh-btn-primary" ${linkAttrs(c.register)}>Đăng ký học${esc(priceTxt)}</a></div>`;

  // Cột danh sách bài.
  let side = `<div class="kh-learn-side-head"><a class="kh-learn-course" href="${esc(courseUrl(course))}">${esc(course.title)}</a>`;
  side += c.owned ? `<p>Đã học xong ${doneN}/${withSrc.length} bài</p><span class="kh-learn-bar"><span style="width:${withSrc.length ? Math.round((doneN / withSrc.length) * 100) : 0}%"></span></span>` : `<p>${keys.length} bài · ${freeN} bài học thử</p>`;
  side += '</div><div class="kh-learn-list">';
  let chapter: string | null = null;
  for (const l of Object.values(lessons)) {
    if (l.chapter !== chapter) {
      if (chapter !== null) side += '</ol>';
      chapter = l.chapter;
      side += chapter ? `<p class="kh-learn-list-ch">${esc(chapter)}</p>` : '';
      side += '<ol class="kh-lessons">';
    }
    const row = await lessonRowParts(course, l, user, s);
    side += `<li class="kh-lesson ${row.cls}${l.n === n ? ' is-current' : ''}">${icon(row.icon, 'kh-lesson-icon', 16)}<span class="kh-lesson-title"><a href="${esc(row.href)}"${l.n === n ? ' aria-current="page"' : ''}${row.title ? ` title="${esc(row.title)}"` : ''}>${rich(l.title)}</a>${row.badge}</span><span class="kh-lesson-time">${esc(l.duration)}</span></li>`;
  }
  side += (keys.length ? '</ol>' : '') + '</div>';

  const html = `<div class="kh-learn"><div class="kh-container">${breadcrumb([['Khóa học', '/khoa-hoc/'], [course.title, courseUrl(course)], [`Bài ${n}`]])}<div class="kh-learn-layout"><div class="kh-learn-main">${main}</div><aside class="kh-learn-side" aria-label="Danh sách bài học">${side}</aside></div></div></div>`;
  return <Html html={html} />;
}
