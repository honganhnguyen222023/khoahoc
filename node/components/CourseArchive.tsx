// Danh sách khóa học: bộ lọc bên trái, lưới bên phải (giống archive-khoa_hoc.php).
import type { Prisma } from '@prisma/client';
import { Html } from '@/components/Html';
import { getCurrentUser } from '@/lib/auth';
import { db } from '@/lib/db';
import { esc } from '@/lib/html';
import { icon } from '@/lib/icons';
import { q, qList, type SP } from '@/lib/page';
import { getSettings } from '@/lib/settings';
import { courseCards, pageHeader } from '@/lib/templates';

const COMBO = 'combo';
const PER_PAGE = 12;
export const SORTS: Record<string, string> = { '': 'Nổi bật', moi: 'Mới nhất', cu: 'Cũ nhất', az: 'Tên A – Z', za: 'Tên Z – A' };
const ORDERS: Record<string, Prisma.CourseOrderByWithRelationInput[]> = {
  '': [{ sortOrder: 'asc' }, { createdAt: 'desc' }],
  moi: [{ createdAt: 'desc' }],
  cu: [{ createdAt: 'asc' }],
  az: [{ title: 'asc' }],
  za: [{ title: 'desc' }],
};

export async function CourseArchive({ sp, termSlug }: { sp: SP; termSlug?: string }) {
  const s = await getSettings();
  const user = await getCurrentUser();
  const term = termSlug ? await db.category.findUnique({ where: { slug: termSlug } }) : null;
  const title = term ? term.name : 'Tất cả khóa học';
  const desc = term?.description || 'Thanh toán một lần, học trọn đời trên điện thoại hoặc máy tính.';

  const loai = qList(sp, 'loai').filter((x) => x === 'le' || x === 'combo');
  const chuDe = qList(sp, 'chu_de');
  const sort = SORTS[q(sp, 'sap_xep')] !== undefined ? q(sp, 'sap_xep') : '';
  if (term) (term.slug === COMBO ? loai : chuDe).push(term.slug === COMBO ? 'combo' : term.slug);
  const fl = { loai: [...new Set(loai)], chuDe: [...new Set(chuDe)] };

  const and: Prisma.CourseWhereInput[] = [{ status: 'publish' }];
  if (fl.loai.length === 1) {
    const inCombo = { categories: { some: { category: { slug: COMBO } } } };
    and.push(fl.loai[0] === 'combo' ? inCombo : { NOT: inCombo });
  }
  if (fl.chuDe.length) and.push({ categories: { some: { category: { slug: { in: fl.chuDe } } } } });
  const where = { AND: and };
  const page = Math.max(1, parseInt(q(sp, 'trang'), 10) || 1);
  const [total, list] = await Promise.all([
    db.course.count({ where }),
    db.course.findMany({ where, orderBy: ORDERS[sort], skip: (page - 1) * PER_PAGE, take: PER_PAGE }),
  ]);
  const pages = Math.ceil(total / PER_PAGE);

  // Danh mục có khóa học (để hiện trong bộ lọc).
  const terms = await db.category.findMany({ where: { courses: { some: { course: { status: 'publish' } } } }, orderBy: { name: 'asc' } });
  const hasCombo = terms.some((t) => t.slug === COMBO);
  const topics = terms.filter((t) => t.slug !== COMBO);
  const active = fl.loai.length + fl.chuDe.length;
  const check = (name: string, value: string, label: string, on: boolean) =>
    `<label class="kh-check"><input type="checkbox" name="${name}[]" value="${esc(value)}"${on ? ' checked' : ''}><span>${esc(label)}</span></label>`;

  let html = pageHeader(title, desc, term ? [['Khóa học', '/khoa-hoc/'], [title]] : [[title]]);
  html += `<div class="kh-container kh-page"><form class="kh-catalog" method="get" action="/khoa-hoc/" data-autosubmit>
<aside class="kh-catalog-side"><details class="kh-filterbox" data-filterbox><summary class="kh-filterbox-toggle">${icon('sliders-horizontal', '', 18)} Bộ lọc${active ? `<span class="kh-filterbox-count">${active}</span>` : ''}</summary>
<div class="kh-filterbox-body"><p class="kh-filterbox-title">Lọc khóa học</p>
${hasCombo ? `<fieldset class="kh-filter-group"><legend>Thể loại</legend>${check('loai', 'le', 'Khóa học lẻ', fl.loai.includes('le'))}${check('loai', 'combo', 'Combo', fl.loai.includes('combo'))}</fieldset>` : ''}
${topics.length ? `<fieldset class="kh-filter-group"><legend>Chủ đề</legend>${topics.map((t) => check('chu_de', t.slug, t.name, fl.chuDe.includes(t.slug))).join('')}</fieldset>` : ''}
<button type="submit" class="kh-btn kh-btn-primary kh-btn-block kh-filter-submit">Lọc</button>
${active || sort ? `<a class="kh-filter-reset" href="/khoa-hoc/">${icon('x', '', 14)} Xóa tất cả bộ lọc</a>` : ''}</div></details></aside>
<div class="kh-catalog-main"><div class="kh-catalog-bar"><p class="kh-catalog-count" role="status">${total} khóa học</p>
<label class="kh-sort"><span>Sắp xếp</span><select name="sap_xep">${Object.entries(SORTS).map(([k, l]) => `<option value="${k}"${k === sort ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></label></div>`;
  if (list.length) {
    html += `<div class="kh-grid kh-cols-3 kh-course-grid">${await courseCards(list, user, s)}</div>`;
    if (pages > 1) {
      const link = (n: number) => {
        const u = new URLSearchParams();
        fl.loai.forEach((v) => u.append('loai[]', v));
        fl.chuDe.forEach((v) => u.append('chu_de[]', v));
        if (sort) u.set('sap_xep', sort);
        if (n > 1) u.set('trang', String(n));
        const qs = u.toString();
        return (term ? `/danh-muc-khoa-hoc/${term.slug}/` : '/khoa-hoc/') + (qs ? '?' + qs : '');
      };
      html += '<nav class="kh-pagination" aria-label="Phân trang">';
      if (page > 1) html += `<a class="prev page-numbers" href="${esc(link(page - 1))}">‹</a>`;
      for (let n = 1; n <= pages; n++) html += n === page ? `<span aria-current="page" class="page-numbers current">${n}</span>` : `<a class="page-numbers" href="${esc(link(n))}">${n}</a>`;
      if (page < pages) html += `<a class="next page-numbers" href="${esc(link(page + 1))}">›</a>`;
      html += '</nav>';
    }
  } else {
    html += `<div class="kh-empty-state">${icon('book-open', '', 32)}<p>Không có khóa học nào khớp bộ lọc.</p><a class="kh-btn kh-btn-outline" href="/khoa-hoc/">Xem tất cả khóa học</a></div>`;
  }
  html += '</div></form></div>';
  return <Html html={html} />;
}
