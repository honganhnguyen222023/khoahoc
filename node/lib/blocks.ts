// In nội dung dạng khối (giống khối Gutenberg của theme) ra HTML.
// Khối "kh/*" viết lại từ inc/blocks.php; khối có sẵn (đoạn văn, tiêu đề, nút…) giữ nguyên HTML đã lưu.
import type { Course, User } from '@prisma/client';
import { db } from './db';
import { cx, digits, esc, linkAttrs, rich } from './html';
import { icon } from './icons';
import { type Block, courseLessons } from './lms';
import { opt, type Settings } from './settings';
import { contactForm, courseCards, lessonRowParts } from './templates';

export type BlockCtx = {
  s: Settings;
  user: User | null;
  path: string; // trang hiện tại (cho form liên hệ quay lại)
  formStatus?: string; // ?kh_form=sent|invalid|wait
  course?: Course; // đang in giáo trình trong trang khóa học
  lessonN?: number; // đếm bài khi in giáo trình
  currentLesson?: number;
};

type A = Record<string, unknown>;
const a_ = (a: A, k: string, def: unknown = ''): unknown => (a[k] === undefined || a[k] === null || a[k] === '' ? def : a[k]);
const s_ = (a: A, k: string, def = ''): string => String(a_(a, k, def));

function blockImg(a: A, attrs: Record<string, string> = {}, prefix = 'image'): string {
  const url = s_(a, prefix + 'Url');
  if (!url) return '';
  const alt = s_(a, prefix + 'Alt');
  const extra = Object.entries(attrs).map(([k, v]) => ` ${k}="${esc(v)}"`).join('');
  return `<img src="${esc(url)}" alt="${esc(alt)}" decoding="async"${attrs.loading ? '' : ' loading="lazy"'}${extra}>`;
}

function heading(a: A, tag = 'h2'): string {
  let out = '';
  if (a_(a, 'eyebrow')) out += `<p class="kh-eyebrow">${rich(a.eyebrow)}</p>`;
  if (a_(a, 'title')) out += `<${tag} class="kh-h2">${rich(a.title)}</${tag}>`;
  if (a_(a, 'text')) out += `<p class="kh-lead">${rich(a.text)}</p>`;
  return out ? `<header class="kh-section-head">${out}</header>` : '';
}

const wrapId = (a: A) => (a_(a, 'anchor') ? ` id="${esc(a.anchor)}"` : '');

/* ------------------------------------------------------------ từng khối */

const R: Record<string, (a: A, inner: string, ctx: BlockCtx, b: Block) => string | Promise<string>> = {
  section: (a, inner) =>
    `<section${wrapId(a)} class="${cx('kh-section', 'kh-bg-' + s_(a, 'bg', 'white'), 'kh-align-' + s_(a, 'align', 'center'), s_(a, 'className'))}"><div class="kh-container"><div class="kh-section-box">${heading(a)}<div class="kh-section-body">${inner}</div>${a_(a, 'footnote') ? `<p class="kh-footnote">${rich(a.footnote)}</p>` : ''}</div></div></section>`,

  hero: (a, inner) => {
    const badge = a_(a, 'badgeText') ? `<p class="kh-pill-note">${a_(a, 'badge') ? `<span>${esc(a.badge)}</span>` : ''}${rich(a.badgeText)}</p>` : '';
    const img = blockImg(a, { class: 'kh-hero-img', fetchpriority: 'high', loading: 'eager', sizes: '(min-width: 1024px) 430px, 90vw' }) || '<img class="kh-hero-img" src="/img/hero-placeholder.svg" alt="" width="683" height="1024" fetchpriority="high">';
    const formula = a_(a, 'formula') ? `<div class="kh-formula"><span>fx</span><code>${esc(a.formula)}</code></div>` : '';
    let chips = '';
    for (const n of [1, 2]) {
      if (a_(a, `chip${n}Value`)) {
        chips += `<div class="kh-chip kh-chip-${n}"><span class="kh-chip-icon">${icon(s_(a, `chip${n}Icon`, n === 1 ? 'users' : 'table'), '', 18)}</span><span><strong>${esc(a[`chip${n}Value`])}</strong><small>${esc(s_(a, `chip${n}Label`))}</small></span></div>`;
      }
    }
    return `<section${wrapId(a)} class="${cx('kh-hero', s_(a, 'className'))}"><div class="kh-container"><div class="kh-hero-box"><div class="kh-hero-copy">${badge}<h1 class="kh-h1">${rich(s_(a, 'title'))}</h1>${a_(a, 'text') ? `<p class="kh-hero-text">${rich(a.text)}</p>` : ''}<div class="kh-hero-inner">${inner}</div></div><div class="kh-hero-media"><figure class="kh-hero-frame">${formula}${img}</figure>${chips}</div></div></div></section>`;
  },

  stats: (a, inner) => (s_(a, 'variant', 'bar') === 'compact' ? `<div class="kh-stats kh-stats-compact">${inner}</div>` : `<div class="kh-container"><div class="kh-stats kh-stats-bar">${inner}</div></div>`),

  stat: (a) => `<div class="kh-stat"><strong class="kh-stat-value" data-count>${esc(s_(a, 'value'))}</strong><span class="kh-stat-label">${esc(s_(a, 'label'))}</span></div>`,

  grid: (a, inner) => {
    const cols = Math.max(1, Math.min(4, parseInt(s_(a, 'columns', '3'), 10) || 3));
    const title = a_(a, 'title') ? `<h3 class="kh-grid-title">${rich(a.title)}</h3>` : '';
    const grid = `<div class="kh-grid kh-cols-${cols}">${inner}</div>`;
    return a_(a, 'boxed', false) ? `<div class="kh-panel">${title}${grid}</div>` : `<div class="kh-grid-wrap">${title}${grid}</div>`;
  },

  card: (a) => {
    const variant = s_(a, 'variant', 'feature');
    const title = rich(s_(a, 'title'));
    const text = a_(a, 'text') ? `<p class="kh-card-text">${rich(a.text)}</p>` : '';
    const url = s_(a, 'url');
    const ic = s_(a, 'icon', 'sparkles');
    let inner: string;
    switch (variant) {
      case 'problem':
        inner = `<span class="kh-num">${esc(s_(a, 'number'))}</span><div><h3 class="kh-card-title">${title}</h3>${text}</div>`;
        break;
      case 'step':
        inner = `<p class="kh-step-label"><span>${esc(s_(a, 'number', '1'))}</span>${esc(s_(a, 'meta2', 'Bước'))}</p><h3 class="kh-card-title">${title}</h3>${text}${a_(a, 'meta') ? `<p class="kh-card-meta">${esc(a.meta)}</p>` : ''}`;
        break;
      case 'image': {
        const img = blockImg(a, { class: 'kh-card-img' });
        inner = (img ? `<div class="kh-card-media">${img}</div>` : '<div class="kh-card-media kh-card-media-empty"></div>') + `<div class="kh-card-body"><h3 class="kh-card-title">${title}</h3>${text}</div>`;
        break;
      }
      case 'link':
        inner = `<span class="kh-icon-box">${icon(ic, '', 20)}</span><div class="kh-card-grow"><h3 class="kh-card-title">${title}</h3>${text}</div>${icon('arrow-up-right', 'kh-card-arrow', 16)}`;
        break;
      case 'choice':
        inner = `<span class="kh-choice-need">${rich(s_(a, 'text'))}</span><strong class="kh-choice-pick">${title}</strong>`;
        break;
      default:
        inner = `<span class="kh-icon-box">${icon(ic, '', 20)}</span><h3 class="kh-card-title">${title}</h3>${text}`;
    }
    const cls = cx('kh-card', 'kh-card-' + variant, s_(a, 'className'));
    return url ? `<a class="${cls} is-link" ${linkAttrs(url)}>${inner}</a>` : `<div class="${cls}">${inner}</div>`;
  },

  about: (a, inner) => {
    const img = blockImg(a, { class: 'kh-about-img', sizes: '(min-width: 1024px) 420px, 90vw' }) || '<img class="kh-about-img" src="/img/hero-placeholder.svg" alt="" width="683" height="1024" loading="lazy">';
    const quote = a_(a, 'quote') ? `<blockquote class="kh-quote"><p>${rich(a.quote)}</p>${a_(a, 'quoteAuthor') ? `<cite>${esc(a.quoteAuthor)}</cite>` : ''}</blockquote>` : '';
    return `<section${wrapId(a)} class="${cx('kh-section', 'kh-bg-' + s_(a, 'bg', 'soft'), s_(a, 'className'))}"><div class="kh-container"><div class="kh-section-box"><div class="kh-about"><div class="kh-about-media">${img}${quote}</div><div class="kh-about-copy">${a_(a, 'eyebrow') ? `<p class="kh-eyebrow">${rich(a.eyebrow)}</p>` : ''}<h2 class="kh-h2">${rich(s_(a, 'title'))}</h2><div class="kh-prose">${inner}</div></div></div></div></div></section>`;
  },

  courses: async (a, _inner, ctx) => {
    const layout = s_(a, 'layout', 'featured');
    const count = parseInt(s_(a, 'count', layout === 'featured' ? '2' : '8'), 10);
    const where: Record<string, unknown> = { status: 'publish' };
    if (layout === 'featured') where.featured = true;
    else if (a_(a, 'onlyOthers', true)) where.featured = false;
    if (a_(a, 'category')) where.categories = { some: { category: { slug: s_(a, 'category') } } };
    const list = await db.course.findMany({ where, orderBy: [{ sortOrder: 'asc' }, { createdAt: 'desc' }], take: count });
    if (!list.length) return '';
    const title = a_(a, 'title') ? `<h3 class="kh-grid-title">${rich(a.title)}</h3>` : '';
    return `${title}<div class="${layout === 'featured' ? 'kh-course-featured' : 'kh-grid kh-cols-4 kh-course-grid'}">${await courseCards(list, ctx.user, ctx.s, layout === 'featured')}</div>`;
  },

  masonry: (a) => {
    const images = (Array.isArray(a.images) ? a.images : []) as A[];
    const out = images.map((img) => blockImg({ imageUrl: img.url, imageAlt: img.alt }, { sizes: '(min-width: 1024px) 340px, 90vw' })).filter(Boolean).map((t) => `<figure class="kh-masonry-item">${t}</figure>`).join('');
    return out ? `<div class="kh-masonry">${out}</div>` : '';
  },

  faq: (_a, inner) => `<div class="kh-faq">${inner}</div>`,

  'faq-item': (a) =>
    a_(a, 'question') ? `<details class="kh-faq-item"><summary><span>${rich(a.question)}</span><i aria-hidden="true">${icon('plus', '', 14)}</i></summary><div class="kh-faq-answer"><p>${rich(s_(a, 'answer'))}</p></div></details>` : '',

  cta: (a, inner, ctx) => {
    let contacts = '';
    if (a_(a, 'showContacts', true)) {
      const items: string[][] = [];
      if (opt(ctx.s, 'phone')) items.push(['phone', 'Hotline / Zalo', opt(ctx.s, 'phone'), 'tel:' + digits(opt(ctx.s, 'phone'))]);
      if (opt(ctx.s, 'email')) items.push(['mail', 'Email', opt(ctx.s, 'email'), 'mailto:' + opt(ctx.s, 'email')]);
      contacts = items.map((it) => `<a class="kh-cta-contact" href="${esc(it[3])}"><span>${icon(it[0], '', 18)}</span><span><small>${esc(it[1])}</small>${esc(it[2])}</span></a>`).join('');
      contacts = contacts ? `<div class="kh-cta-contacts">${contacts}</div>` : '';
    }
    return `<section${wrapId(a)} class="${cx('kh-cta', s_(a, 'className'))}"><div class="kh-container"><div class="kh-cta-box"><div class="kh-cta-copy"><h2 class="kh-h2">${rich(s_(a, 'title'))}</h2>${a_(a, 'text') ? `<p>${rich(a.text)}</p>` : ''}<div class="kh-cta-actions">${inner}</div></div>${contacts}</div></div></section>`;
  },

  'contact-form': (a, _i, ctx) => contactForm(a, ctx.formStatus || '', ctx.path),

  chapter: (a, inner) => {
    const count = (inner.match(/<li class="kh-lesson/g) || []).length;
    const open = !!a_(a, 'open', false) || inner.includes('is-current');
    return `<details class="kh-chapter"${open ? ' open' : ''}><summary><span class="kh-chapter-title">${rich(s_(a, 'title', 'Chương'))}</span><span class="kh-chapter-count">${count ? esc(`${count} bài`) : ''}</span>${icon('chevron-down', 'kh-chapter-caret', 18)}</summary><ol class="kh-lessons">${inner}</ol></details>`;
  },

  lesson: async (a, _inner, ctx) => {
    const title = rich(s_(a, 'title'));
    const time = esc(s_(a, 'duration'));
    if (ctx.course) {
      ctx.lessonN = (ctx.lessonN || 0) + 1;
      const lesson = courseLessons(ctx.course)[ctx.lessonN];
      if (!lesson) return '';
      const row = await lessonRowParts(ctx.course, lesson, ctx.user, ctx.s);
      return `<li class="kh-lesson ${row.cls}${ctx.currentLesson === ctx.lessonN ? ' is-current' : ''}">${icon(row.icon, 'kh-lesson-icon', 16)}<span class="kh-lesson-title"><a href="${esc(row.href)}"${row.title ? ` title="${esc(row.title)}"` : ''}>${title}</a>${row.badge}</span><span class="kh-lesson-time">${time}</span></li>`;
    }
    return `<li class="kh-lesson">${icon(s_(a, 'type') === 'doc' ? 'file-text' : 'lock', 'kh-lesson-icon', 16)}<span class="kh-lesson-title"><span>${title}</span></span><span class="kh-lesson-time">${time}</span></li>`;
  },
};

/* ------------------------------------------------------------ in khối */

const SKIP = /^(woocommerce\/|core\/(shortcode|pattern|post-title|navigation|latest-posts))/;

async function renderBlock(b: Block, ctx: BlockCtx): Promise<string> {
  if (b.name && SKIP.test(b.name)) return '';
  const attrs = (b.attrs || {}) as A;
  // Khối của theme.
  if (b.name?.startsWith('kh/')) {
    const fn = R[b.name.slice(3)];
    if (!fn) return '';
    let inner = '';
    for (const ib of b.inner || []) inner += await renderBlock(ib, ctx);
    return fn(attrs, inner, ctx, b);
  }
  // Khối có sẵn: ghép HTML đã lưu với khối con.
  let out = '';
  let i = 0;
  for (const part of b.content || []) {
    if (part === null) {
      const child = b.inner?.[i++];
      if (child) out += await renderBlock(child, ctx);
    } else {
      out += part;
    }
  }
  return out;
}

export async function renderBlocks(blocks: Block[], ctx: BlockCtx): Promise<string> {
  let out = '';
  for (const b of blocks) out += await renderBlock(b, ctx);
  return out;
}

/** Trang dùng khối "toàn chiều ngang" của theme (không bọc cột chữ). */
export function isLanding(blocks: Block[]): boolean {
  return blocks.some((b) => ['kh/hero', 'kh/section', 'kh/about', 'kh/cta'].includes(b.name || ''));
}
