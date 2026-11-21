// Nhận form của học viên (đăng nhập, đăng ký, giỏ hàng, đặt hàng…) rồi chuyển trang.
import { NextResponse } from 'next/server';
import * as A from '@/lib/actions';

const ACTIONS: Record<string, (f: FormData) => Promise<unknown>> = {
  'dang-nhap': A.loginAction,
  'dang-ky': A.registerAction,
  'quen-mat-khau': A.lostPasswordAction,
  'dat-lai-mat-khau': A.resetPasswordAction,
  'thong-tin': A.updateAccountAction,
  'bo-khoi-gio': A.cartRemoveAction,
  'dat-mua': A.placeOrderAction,
  'thanh-toan-lai': A.payAgainAction,
};

export async function POST(req: Request, { params }: { params: Promise<{ action: string }> }) {
  const site = process.env.SITE_URL || new URL(req.url).origin;
  // Chống giả mạo yêu cầu: chỉ nhận form gửi từ chính website.
  const origin = req.headers.get('origin');
  if (origin && new URL(origin).host !== new URL(req.url).host && origin !== process.env.SITE_URL) {
    return new Response('Yêu cầu không hợp lệ.', { status: 403 });
  }
  const fn = ACTIONS[(await params).action];
  if (!fn) return new Response('Không rõ thao tác.', { status: 404 });
  try {
    await fn(await req.formData());
    return NextResponse.redirect(new URL('/', site), 303);
  } catch (e) {
    if (e instanceof A.Go) return NextResponse.redirect(new URL(e.url, site), 303);
    throw e;
  }
}
