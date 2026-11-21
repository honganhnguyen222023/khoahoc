// Trang chi tiết khóa học (giống single-khoa_hoc.php).
import type { Course, User } from '@prisma/client';
import { Html } from '@/components/Html';
import { renderBlocks } from '@/lib/blocks';
import { benefit, courseView } from '@/lib/courses';
import { db } from '@/lib/db';
import { esc, linkAttrs, vnDate, youtubeId } from '@/lib/html';
import { icon } from '@/lib/icons';
import { courseLessons, isStaff, learnUrl, lessonState, loginUrl, parseBlocks, progress, trialLeft, trialLimit } from '@/lib/lms';
import { getSettings, zaloUrl } from '@/lib/settings';
import { breadcrumb, courseCards } from '@/lib/templates';

export async function CourseDetail({ course, user, notice }: { course: Course; user: User | null; notice?: string }) {
  const s = await getSettings();
  const c = await courseView(course, user, s);
  const yt = youtubeId(c.video);
  const terms = await db.category.findMany({ where: { courses: { some: { courseId: course.id } } } });
  const path = `/khoa-hoc/${course.slug}/`;

  // Phần đầu: thông tin + video giới thiệu.
  let media: string;
  if (yt) media = `<button type="button" class="kh-yt" data-yt="${esc(yt)}" aria-label="Phát video giới thiệu"><img src="https://i.ytimg.com/vi/${esc(yt)}/hqdefault.jpg" alt="" width="480" height="360" fetchpriority="high"><span class="kh-yt-play">${icon('play', '', 28)}</span></button>`;
  else if (c.thumbnail || c.cover) media = `<img src="${esc(c.thumbnail || c.cover)}" alt="" width="1280" height="720" fetchpriority="high">`;
  else media = `<span class="kh-fcourse-ph">${icon(c.iconName, '', 48)}</span>`;

  // Khung mua.
  let buy = `${c.regularHtml ? `<s class="kh-price-old">${esc(c.regularHtml)}</s>` : ''}<p class="kh-price">${esc(c.priceHtml || 'Liên hệ')}${c.saveHtml ? ` <span class="kh-save">${esc(c.saveHtml)}</span>` : ''}</p>${notice || ''}`;
  buy += `<p class="kh-buybox-term">${icon('calendar', '', 16)} ${esc('Thời hạn học: ' + c.durationTxt)}</p>`;
  if (c.owned) {
    const adminView = isStaff(user) && !c.access;
    let txt = adminView ? 'Quản trị viên xem được mọi bài' : 'Bạn đã sở hữu khóa học này';
    if (!adminView && c.access?.expires) txt += ' · hạn đến ' + vnDate(c.access.expires);
    const started = user ? Object.keys(await progress(user.id, course.id)).length > 0 : false;
    buy += `<p class="kh-owned">${icon('badge-check', '', 18)} ${esc(txt)}</p><a class="kh-btn kh-btn-primary kh-btn-block" href="${esc(c.register)}">${icon('play', '', 18)} ${started ? 'Học tiếp' : 'Vào học'}</a>`;
  } else {
    if (c.expired && c.access) buy += `<p class="kh-owned is-expired">${icon('alert-circle', '', 18)} ${esc('Khóa học của bạn đã hết hạn ngày ' + vnDate(c.access.expires) + '. Mua lại để học từ đầu.')}</p>`;
    buy += `<a class="kh-btn kh-btn-primary kh-btn-block" ${linkAttrs(c.register)}>${c.expired ? 'Mua lại khóa học' : 'Đăng ký học ngay'}</a>`;
    if (c.cartUrl) buy += `<a class="kh-btn kh-btn-outline kh-btn-block" href="${esc(c.cartUrl)}">${icon('shopping-cart', '', 18)} Thêm vào giỏ hàng</a>`;
    const lessons = Object.values(courseLessons(course));
    const first = lessons[0];
    if (first && !c.expired) {
      const st = await lessonState(course, first, user);
      if (st === 'trial_used') {
        buy += `<p class="kh-trial-link is-used">${icon('lock', '', 16)} Bạn đã dùng hết lượt học thử</p>`;
      } else {
        const left = user ? await trialLeft(user.id, course.id, first.n, s) : trialLimit(s);
        const tl = 'Học thử miễn phí bài đầu tiên' + (left > 0 && trialLimit(s) ? ` (còn ${left} lượt)` : '');
        buy += `<a class="kh-trial-link" href="${esc(user ? learnUrl(course) : loginUrl(learnUrl(course)))}">${icon('play', '', 16)} ${esc(tl)}</a>`;
      }
    }
    if (!user) buy += `<p class="kh-buybox-login">Đã mua khóa này? <a href="${esc(loginUrl(path))}">Đăng nhập</a></p>`;
  }
  if (zaloUrl(s)) buy += `<a class="kh-btn kh-btn-outline kh-btn-block" href="${esc(zaloUrl(s))}" target="_blank" rel="noopener">Hỏi thêm qua Zalo</a>`;
  const lines = c.benefitList.length ? c.benefitList : ['Xem lại không giới hạn trong thời hạn học', 'Học theo lộ trình: xong bài trước mở bài sau', 'Học trên điện thoại hoặc máy tính'];
  buy += `<ul class="kh-checks kh-checks-sm">${lines.map((l) => `<li>${icon('check', '', 16)}<span>${benefit(l)}</span></li>`).join('')}</ul>`;

  const head = `<header class="kh-course-hero"><div class="kh-container">${breadcrumb([['Khóa học', '/khoa-hoc/'], [course.title]])}
<div class="kh-course-layout"><div class="kh-course-main"><div class="kh-course-tags">${c.badge ? `<span class="kh-badge">${esc(c.badge)}</span>` : ''}${terms.map((t) => `<a class="kh-tag" href="/danh-muc-khoa-hoc/${esc(t.slug)}/">${esc(t.name)}</a>`).join('')}</div>
<h1 class="kh-h1 kh-h1-sm">${esc(course.title)}</h1>${course.excerpt ? `<p class="kh-lead">${esc(course.excerpt)}</p>` : ''}${c.metaLine ? `<p class="kh-course-metaline">${icon('clock', '', 16)} ${esc(c.metaLine)}</p>` : ''}
<div class="kh-course-video">${media}</div></div>
<aside class="kh-buybox" aria-label="Đăng ký khóa học"><div class="kh-buybox-card">${buy}</div></aside></div></div></header>`;

  // Nội dung + giáo trình.
  const body = await renderBlocks(parseBlocks(course.blocks), { s, user, path, course, lessonN: 0 });

  // Khóa học khác.
  const related = await db.course.findMany({ where: { status: 'publish', id: { not: course.id } }, orderBy: [{ sortOrder: 'asc' }], take: 4 });
  const rel = related.length
    ? `<section class="kh-section kh-bg-soft kh-align-center"><div class="kh-container"><div class="kh-section-box"><header class="kh-section-head"><h2 class="kh-h2">Khóa học khác</h2></header><div class="kh-grid kh-cols-4 kh-course-grid">${await courseCards(related, user, s)}</div></div></div></section>`
    : '';

  const mobile = `<div class="kh-mobile-buy"><div><strong>${esc(c.priceHtml || 'Liên hệ')}</strong>${c.regularHtml ? `<s>${esc(c.regularHtml)}</s>` : ''}</div><a class="kh-btn kh-btn-primary kh-btn-sm" ${linkAttrs(c.register)}>${c.owned ? 'Vào học' : 'Đăng ký học'}</a></div>`;

  return (
    <article className="kh-course-page">
      <Html html={head} />
      <div className="kh-container">
        <div className="kh-course-layout kh-course-body">
          <div className="kh-prose kh-entry" dangerouslySetInnerHTML={{ __html: body }} />
        </div>
      </div>
      <Html html={rel + mobile} />
    </article>
  );
}
