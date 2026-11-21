// Mẩu HTML dùng chung (giống template-parts của theme WordPress).
import type { Course, User } from '@prisma/client';
import { esc, linkAttrs, rich } from './html';
import { icon } from './icons';
import { benefit, courseView, type CourseView } from './courses';
import { type Lesson, isStaff, lessonState, learnUrl, loginUrl, progress, trialLeft, viewsLeft, viewsLimit } from './lms';
import type { Settings } from './settings';

/** Breadcrumb: [ [chữ, link], … ], mục cuối = trang hiện tại. */
export function breadcrumb(items: [string, string?][]): string {
  let out = '<nav class="kh-breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Trang chủ</a></li>';
  items.forEach(([label, url], i) => {
    out += i === items.length - 1 || !url ? `<li aria-current="page">${esc(label)}</li>` : `<li><a href="${esc(url)}">${esc(label)}</a></li>`;
  });
  return out + '</ol></nav>';
}

export function pageHeader(title: string, desc = '', crumbs: [string, string?][] = []): string {
  return `<header class="kh-page-head"><div class="kh-container">${crumbs.length ? breadcrumb(crumbs) : ''}<h1 class="kh-h1 kh-h1-sm">${esc(title)}</h1>${desc ? `<p class="kh-lead">${rich(desc)}</p>` : ''}</div></header>`;
}

/** Thẻ khóa học nhỏ (lưới). */
export function courseCard(c: CourseView): string {
  return `<article class="kh-ccard"><div class="kh-ccard-top"><span class="kh-icon-box">${icon(c.iconName, '', 20)}</span>${c.badge ? `<span class="kh-tag">${esc(c.badge)}</span>` : ''}</div>
<h3 class="kh-ccard-title"><a href="${esc(c.detail)}">${esc(c.title)}</a></h3>${c.metaLine ? `<p class="kh-ccard-meta">${esc(c.metaLine)}</p>` : ''}${c.excerpt ? `<p class="kh-ccard-text">${esc(c.excerpt)}</p>` : ''}
<div class="kh-ccard-foot"><strong class="kh-ccard-price">${esc(c.priceHtml || 'Liên hệ')}</strong><a class="kh-link-arrow" href="${esc(c.detail)}" aria-label="${esc('Chi tiết khóa ' + c.title)}">Chi tiết ${icon('arrow-right', '', 16)}</a></div></article>`;
}

/** Thẻ khóa học lớn (khóa nổi bật ở trang chủ). */
export function courseFeatured(c: CourseView): string {
  const img = c.thumbnail || c.cover;
  const media = img ? `<img src="${esc(img)}" alt="" width="1280" height="720" loading="lazy" decoding="async">` : `<span class="kh-fcourse-ph">${icon(c.iconName, '', 40)}</span>`;
  return `<article class="kh-fcourse"><a class="kh-fcourse-media" href="${esc(c.detail)}" tabindex="-1" aria-hidden="true">${media}</a>
<div class="kh-fcourse-body">${c.badge ? `<span class="kh-badge">${esc(c.badge)}</span>` : ''}<h3 class="kh-fcourse-title"><a href="${esc(c.detail)}">${esc(c.title)}</a></h3>
${c.audience ? `<p class="kh-fcourse-aud">${esc(c.audience)}</p>` : ''}${c.benefitList.length ? `<ul class="kh-checks">${c.benefitList.map((l) => `<li>${icon('check', '', 16)}<span>${benefit(l)}</span></li>`).join('')}</ul>` : ''}
<div class="kh-fcourse-foot"><div class="kh-price-block">${c.regularHtml ? `<s class="kh-price-old">Mua lẻ ${esc(c.regularHtml)}</s>` : ''}<p class="kh-price">${esc(c.priceHtml || 'Liên hệ')}${c.saveHtml ? ` <span class="kh-save">${esc(c.saveHtml)}</span>` : ''}</p></div>
<div class="kh-fcourse-actions"><a class="kh-btn kh-btn-outline" href="${esc(c.detail)}">Xem chi tiết</a><a class="kh-btn kh-btn-primary" ${linkAttrs(c.register)}>${esc(c.ctaText)}</a></div></div></div></article>`;
}

export async function courseCards(list: Course[], user: User | null, s: Settings, featured = false): Promise<string> {
  const views = await Promise.all(list.map((c) => courseView(c, user, s)));
  return views.map(featured ? courseFeatured : courseCard).join('');
}

/** Cách hiển thị 1 dòng bài học (giáo trình + cột bài học ở trang học). */
export async function lessonRowParts(course: Course, lesson: Lesson, user: User | null, s: Settings) {
  const state = await lessonState(course, lesson, user);
  const media = lesson.type === 'doc' ? 'file-text' : 'play';
  const href = learnUrl(course, lesson.n);
  const out = { state, cls: 'is-locked', icon: 'lock', href, badge: '', title: '' };
  const badge = (t: string, mod = '') => `<span class="kh-preview${mod ? ' ' + mod : ''}">${esc(t)}</span>`;
  switch (state) {
    case 'ok': {
      const done = !!user && !!(await progress(user.id, course.id))[lesson.n];
      out.cls = 'is-open' + (done ? ' is-done' : '');
      out.icon = done ? 'check' : media;
      out.title = done ? 'Đã học xong' : '';
      if (user && !isStaff(user)) {
        const left = await viewsLeft(user.id, course.id, lesson, s);
        if (left >= 0 && left < viewsLimit(s)) out.badge = badge(`Còn ${left} lượt xem`, left ? '' : 'is-muted');
      }
      break;
    }
    case 'views_used':
      out.badge = badge('Hết lượt xem', 'is-muted');
      out.title = `Bạn đã xem bài này ${viewsLimit(s)} lần (tối đa ${viewsLimit(s)} lần).`;
      break;
    case 'trial': {
      const left = user ? await trialLeft(user.id, course.id, lesson.n, s) : -1;
      out.cls = 'is-open';
      out.icon = media;
      out.badge = badge(left < 0 ? 'Học thử' : `Học thử · còn ${left} lượt`);
      break;
    }
    case 'login':
      out.cls = 'is-open';
      out.icon = media;
      out.href = loginUrl(href);
      out.badge = badge('Học thử');
      break;
    case 'trial_used':
      out.badge = badge('Hết lượt học thử', 'is-muted');
      out.title = 'Bạn đã dùng hết lượt xem thử. Đăng ký khóa học để học tiếp.';
      break;
    case 'sequence':
      out.title = 'Học xong bài trước để mở bài này';
      break;
    case 'expired':
      out.title = 'Khóa học đã hết hạn. Mua lại để học từ đầu.';
      break;
    default:
      out.title = 'Đăng ký khóa học để mở bài này';
  }
  return out;
}

/** Form liên hệ / đăng ký tư vấn. */
export function contactForm(a: Record<string, unknown>, status: string, back: string): string {
  const title = String(a.title || 'Để lại thông tin, chúng tôi gọi lại trong 24 giờ');
  const button = String(a.buttonText || 'Gửi thông tin');
  const topics = String(a.topics || '').split(',').map((t) => t.trim()).filter(Boolean);
  const notices: Record<string, string> = {
    sent: `<div class="kh-alert kh-alert-ok" role="status">${icon('check-circle-2', '', 18)}<span>Đã nhận thông tin của bạn. Chúng tôi sẽ liên hệ sớm.</span></div>`,
    invalid: `<div class="kh-alert kh-alert-err" role="alert">${icon('alert-circle', '', 18)}<span>Vui lòng nhập họ tên và số điện thoại hợp lệ (9–11 số).</span></div>`,
    wait: `<div class="kh-alert kh-alert-err" role="alert">${icon('alert-circle', '', 18)}<span>Bạn vừa gửi xong. Vui lòng đợi 30 giây rồi gửi lại.</span></div>`,
  };
  return `<div class="kh-form-card" id="kh-form"><h3 class="kh-card-title">${rich(title)}</h3>${a.text ? `<p class="kh-card-text">${rich(a.text)}</p>` : ''}${notices[status] || ''}
<form class="kh-form" method="post" action="/api/lien-he">
<input type="hidden" name="kh_ts" value="${Date.now()}"><input type="hidden" name="kh_back" value="${esc(back)}">
<div class="kh-hp" aria-hidden="true"><label>Website <input type="text" name="kh_website" tabindex="-1" autocomplete="off"></label></div>
<div class="kh-form-grid">
<label class="kh-field"><span>Họ và tên *</span><input type="text" name="kh_name" required maxlength="80" autocomplete="name"></label>
<label class="kh-field"><span>Số điện thoại *</span><input type="tel" name="kh_phone" required inputmode="tel" pattern="[0-9 +.\\-]{9,15}" autocomplete="tel"></label>
<label class="kh-field"><span>Email</span><input type="email" name="kh_email" maxlength="120" autocomplete="email"></label>
${topics.length ? `<label class="kh-field"><span>Bạn quan tâm</span><select name="kh_topic">${topics.map((t) => `<option>${esc(t)}</option>`).join('')}</select></label>` : ''}
<label class="kh-field kh-field-full"><span>Lời nhắn</span><textarea name="kh_message" rows="3" maxlength="2000"></textarea></label>
</div><button type="submit" class="kh-btn kh-btn-primary">${esc(button)} ${icon('send', '', 16)}</button>
<p class="kh-form-note">Thông tin chỉ dùng để tư vấn khóa học, không chia sẻ cho bên thứ ba.</p></form></div>`;
}
