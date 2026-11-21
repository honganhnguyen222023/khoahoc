// Thêm khóa học vào giỏ: /gio-hang/them/?khoa=ID  (&mua=1: chuyển thẳng tới thanh toán)
import { NextResponse } from 'next/server';
import { getCurrentUser } from '@/lib/auth';
import { cartIds, saveCart } from '@/lib/cart';
import { db } from '@/lib/db';
import { hasAccess, learnUrl } from '@/lib/lms';

export async function GET(req: Request) {
  const url = new URL(req.url);
  const site = process.env.SITE_URL || url.origin;
  const id = parseInt(url.searchParams.get('khoa') || '', 10);
  const course = id ? await db.course.findUnique({ where: { id } }) : null;
  if (!course || course.status !== 'publish' || !(course.price && course.price > 0)) return NextResponse.redirect(new URL('/khoa-hoc/', site), 303);
  // Đã sở hữu: vào học luôn.
  if (await hasAccess(course.id, await getCurrentUser())) return NextResponse.redirect(new URL(learnUrl(course), site), 303);
  await saveCart([...(await cartIds()), course.id]);
  const go = url.searchParams.get('mua') ? '/thanh-toan/' : '/gio-hang/?tb=da-them&khoa=' + course.id;
  return NextResponse.redirect(new URL(go, site), 303);
}
