// Đăng nhập: phiên làm việc lưu trong cơ sở dữ liệu, mã phiên nằm trong cookie (httpOnly).
// Website (học viên) và trang quản trị dùng 2 cookie riêng, nên trên cùng 1 trình duyệt có thể
// vừa đăng nhập học viên ở website, vừa đăng nhập quản trị ở /quan-tri/ mà không đè lên nhau.
import { cache } from 'react';
import { cookies, headers } from 'next/headers';
import { randomBytes } from 'node:crypto';
import type { User } from '@prisma/client';
import { db } from './db';
import { hashPassword, verifyPassword } from './passwords';
import { getSettings, opt } from './settings';

export type SessionKind = 'site' | 'admin';
export const SESSION_COOKIE = 'kh_session';
export const ADMIN_COOKIE = 'kh_admin';
const COOKIE: Record<SessionKind, string> = { site: SESSION_COOKIE, admin: ADMIN_COOKIE };
const DAY = 24 * 60 * 60 * 1000;

async function userFrom(kind: SessionKind): Promise<User | null> {
  const id = (await cookies()).get(COOKIE[kind])?.value;
  if (!id) return null;
  const s = await db.session.findUnique({ where: { id }, include: { user: true } });
  if (!s || s.kind !== kind || s.expiresAt.getTime() < Date.now()) return null;
  return s.user;
}

/** Người đang đăng nhập ở website. */
export const getCurrentUser = cache(() => userFrom('site'));

/** Người đang đăng nhập ở trang quản trị (chỉ trả về nếu là quản trị viên / nhân viên). */
export const getAdminUser = cache(async (): Promise<User | null> => {
  const u = await userFrom('admin');
  return u && (u.role === 'admin' || u.role === 'staff') ? u : null;
});

/** Tên trình duyệt / thiết bị gọn từ User-Agent (hiện trong quản trị). */
function deviceName(ua: string): string {
  const os = /iPhone/.test(ua) ? 'iPhone' : /iPad/.test(ua) ? 'iPad' : /Android/.test(ua) ? 'Android' : /Windows/.test(ua) ? 'Windows' : /Mac OS X|Macintosh/.test(ua) ? 'Mac' : /Linux/.test(ua) ? 'Linux' : 'Thiết bị khác';
  const br = /Edg\//.test(ua) ? 'Edge' : /OPR\//.test(ua) ? 'Opera' : /CocCoc|coc_coc/i.test(ua) ? 'Cốc Cốc' : /Zalo/i.test(ua) ? 'Zalo' : /FBAN|FBAV/.test(ua) ? 'Facebook' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : '';
  return br ? `${br} · ${os}` : os;
}

/** Số thiết bị học viên được đăng nhập cùng lúc (0 = không giới hạn). */
export async function maxDevices(): Promise<number> {
  const n = parseInt(opt(await getSettings(), 'max_devices'), 10);
  return Number.isFinite(n) && n >= 0 ? n : 2;
}

/** Tạo phiên đăng nhập + đặt cookie (gọi trong Server Action / Route Handler). */
export async function startSession(user: Pick<User, 'id' | 'role'>, remember: boolean, kind: SessionKind = 'site') {
  const jar = await cookies();
  // Phiên cũ trên chính trình duyệt này: bỏ đi (không tính là thêm 1 thiết bị).
  const old = jar.get(COOKIE[kind])?.value;
  if (old) await db.session.deleteMany({ where: { id: old } });
  await db.session.deleteMany({ where: { userId: user.id, expiresAt: { lt: new Date() } } });

  // Học viên: giới hạn số thiết bị. Thiết bị thứ (giới hạn + 1) đăng nhập → thiết bị cũ nhất bị đăng xuất.
  if (kind === 'site' && user.role === 'student') {
    const max = await maxDevices();
    if (max > 0) {
      const live = await db.session.findMany({ where: { userId: user.id, kind: 'site' }, orderBy: { createdAt: 'desc' }, select: { id: true } });
      const drop = live.slice(max - 1).map((x) => x.id);
      if (drop.length) await db.session.deleteMany({ where: { id: { in: drop } } });
    }
  }

  const id = randomBytes(32).toString('hex');
  const ms = remember ? 30 * DAY : 2 * DAY;
  const device = deviceName((await headers()).get('user-agent') || '').slice(0, 60);
  await db.session.create({ data: { id, userId: user.id, kind, device, expiresAt: new Date(Date.now() + ms) } });
  jar.set(COOKIE[kind], id, {
    httpOnly: true,
    sameSite: 'lax',
    secure: (process.env.SITE_URL || '').startsWith('https://'),
    path: '/',
    ...(remember ? { maxAge: ms / 1000 } : {}),
  });
}

export async function endSession(kind: SessionKind = 'site') {
  const jar = await cookies();
  const id = jar.get(COOKIE[kind])?.value;
  if (id) await db.session.deleteMany({ where: { id } });
  jar.delete(COOKIE[kind]);
}

/** Đăng xuất mọi thiết bị khác của người dùng (giữ lại phiên đang dùng). */
export async function endOtherSessions(userId: number, kind?: SessionKind) {
  const keep = kind ? (await cookies()).get(COOKIE[kind])?.value : undefined;
  await db.session.deleteMany({ where: { userId, ...(keep ? { id: { not: keep } } : {}) } });
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
