// Đăng nhập: phiên làm việc lưu trong cơ sở dữ liệu, mã phiên nằm trong cookie (httpOnly).
import { cache } from 'react';
import { cookies } from 'next/headers';
import { randomBytes } from 'node:crypto';
import type { User } from '@prisma/client';
import { db } from './db';
import { hashPassword, verifyPassword } from './passwords';

export const SESSION_COOKIE = 'kh_session';
const DAY = 24 * 60 * 60 * 1000;

export const getCurrentUser = cache(async (): Promise<User | null> => {
  const id = (await cookies()).get(SESSION_COOKIE)?.value;
  if (!id) return null;
  const s = await db.session.findUnique({ where: { id }, include: { user: true } });
  if (!s || s.expiresAt.getTime() < Date.now()) return null;
  return s.user;
});

/** Tạo phiên đăng nhập + đặt cookie (gọi trong Server Action / Route Handler). */
export async function startSession(userId: number, remember: boolean) {
  const id = randomBytes(32).toString('hex');
  const ms = remember ? 30 * DAY : 2 * DAY;
  await db.session.create({ data: { id, userId, expiresAt: new Date(Date.now() + ms) } });
  (await cookies()).set(SESSION_COOKIE, id, {
    httpOnly: true,
    sameSite: 'lax',
    secure: (process.env.SITE_URL || '').startsWith('https://'),
    path: '/',
    ...(remember ? { maxAge: ms / 1000 } : {}),
  });
}

export async function endSession() {
  const jar = await cookies();
  const id = jar.get(SESSION_COOKIE)?.value;
  if (id) await db.session.deleteMany({ where: { id } });
  jar.delete(SESSION_COOKIE);
}

/** Đăng nhập bằng email hoặc tên đăng nhập. */
export async function authenticate(login: string, password: string): Promise<User | null> {
  const key = login.trim().toLowerCase();
  if (!key || !password) return null;
  const user = await db.user.findFirst({ where: { OR: [{ email: key }, { login: login.trim() }] } });
  if (!user) return null;
  const { ok, needsRehash } = await verifyPassword(password, user.passwordHash);
  if (!ok) return null;
  if (needsRehash) await db.user.update({ where: { id: user.id }, data: { passwordHash: await hashPassword(password) } });
  return user;
}
