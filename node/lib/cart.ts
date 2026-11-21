// Giỏ hàng: danh sách mã khóa học lưu trong cookie "kh_cart".
import { cookies } from 'next/headers';
import { db } from './db';

export const CART_COOKIE = 'kh_cart';

export async function cartIds(): Promise<number[]> {
  const raw = (await cookies()).get(CART_COOKIE)?.value || '';
  return [...new Set(raw.split(',').map((x) => parseInt(x, 10)).filter((x) => x > 0))];
}

/** Khóa học trong giỏ (chỉ khóa đang bán, có học phí). */
export async function cartCourses() {
  const ids = await cartIds();
  if (!ids.length) return [];
  const list = await db.course.findMany({ where: { id: { in: ids }, status: 'publish', price: { gt: 0 } } });
  return ids.map((id) => list.find((c) => c.id === id)).filter((c): c is NonNullable<typeof c> => !!c);
}

export async function saveCart(ids: number[]) {
  (await cookies()).set(CART_COOKIE, [...new Set(ids)].join(','), { path: '/', sameSite: 'lax', maxAge: 60 * 60 * 24 * 30 });
}
