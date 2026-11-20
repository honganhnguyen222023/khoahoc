// Dữ liệu hiển thị của 1 khóa học (giống kh_course() trong theme).
import type { Course, User } from '@prisma/client';
import { esc, price } from './html';
import { accessInfo, continueUrl, courseUrl, durationLabel, hasAccess, isStaff } from './lms';
import { zaloUrl, type Settings } from './settings';

export type CourseView = Course & {
  priceHtml: string;
  regularHtml: string;
  saveHtml: string;
  detail: string;
  iconName: string;
  benefitList: string[];
  owned: boolean;
  expired: boolean;
  access: Awaited<ReturnType<typeof accessInfo>>;
  durationTxt: string;
  register: string;
  cartUrl: string;
  ctaText: string;
};

/** Link nút "Đăng ký": link riêng → mua ngay (có học phí) → Zalo → trang Liên hệ. */
export function registerUrl(c: Course, s: Settings): string {
  if (c.registerUrl) return c.registerUrl;
  if ((c.price ?? 0) > 0) return `/thanh-toan/?mua=${c.id}`;
  return zaloUrl(s) || '/lien-he/';
}

export async function courseView(c: Course, user: User | null, s: Settings): Promise<CourseView> {
  const p = c.price ?? 0;
  const r = c.regularPrice ?? 0;
  const owned = await hasAccess(c.id, user);
  const access = user ? await accessInfo(c.id, user.id) : null;
  const expired = !owned && !!access && !access.active;
  return {
    ...c,
    priceHtml: price(p),
    regularHtml: r > p ? price(r) : '',
    saveHtml: r > p && p > 0 ? '−' + price(r - p) : '',
    detail: c.detailUrl || courseUrl(c),
    iconName: c.icon || 'book-open',
    benefitList: String(c.benefits || '').split('\n').map((x) => x.trim()).filter(Boolean),
    owned,
    expired,
    access,
    durationTxt: durationLabel(c, s),
    register: owned && user ? await continueUrl(c, isStaff(user) && !access ? 0 : user.id) : registerUrl(c, s),
    cartUrl: (c.price ?? 0) > 0 && !c.registerUrl ? `/gio-hang/?them=${c.id}` : '',
    ctaText: owned ? 'Vào học' : expired ? 'Mua lại' : 'Đăng ký',
  };
}

/** Lợi ích: thoát HTML rồi **in đậm**. */
export function benefit(line: string): string {
  return esc(line).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}
