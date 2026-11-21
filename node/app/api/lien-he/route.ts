// Form liên hệ: lưu vào "Liên hệ" + gửi email báo chủ website. Chống spam giống theme.
import { NextResponse } from 'next/server';
import { db } from '@/lib/db';
import { digits } from '@/lib/html';
import { sendMail } from '@/lib/mail';
import { getSettings, opt } from '@/lib/settings';

const recent = new Map<string, number>(); // IP → thời điểm gửi gần nhất

export async function POST(req: Request) {
  const f = await req.formData();
  const str = (k: string) => String(f.get(k) ?? '').trim();
  const site = process.env.SITE_URL || new URL(req.url).origin;
  let back = str('kh_back') || '/';
  if (!back.startsWith('/') || back.startsWith('//')) back = '/';
  const go = (status: string) => NextResponse.redirect(new URL(`${back}${back.includes('?') ? '&' : '?'}kh_form=${status}#kh-form`, site), 303);

  // Máy gửi rác: điền ô ẩn hoặc gửi nhanh hơn 3 giây → giả vờ thành công.
  if (str('kh_website') || Date.now() - Number(str('kh_ts') || 0) < 3000) return go('sent');
  const ip = req.headers.get('x-forwarded-for')?.split(',')[0].trim() || 'local';
  if (Date.now() - (recent.get(ip) || 0) < 30000) return go('wait');

  const name = str('kh_name').slice(0, 80);
  const phone = str('kh_phone');
  const d = digits(phone);
  if (!name || d.length < 9 || d.length > 11) return go('invalid');
  const data = { name, phone, email: str('kh_email').slice(0, 120), topic: str('kh_topic'), message: str('kh_message').slice(0, 2000), source: back };
  await db.contact.create({ data });
  recent.set(ip, Date.now());

  const s = await getSettings();
  await sendMail(
    opt(s, 'notify_email') || opt(s, 'email'),
    `[${opt(s, 'brand_name')}] Liên hệ mới: ${name} – ${phone}`,
    `Họ tên: ${name}\nĐiện thoại: ${phone}\nEmail: ${data.email}\nQuan tâm: ${data.topic}\n\nLời nhắn:\n${data.message}\n\nGửi từ: ${site}${back}\nXem trong quản trị: ${site}/quan-tri/lien-he/`,
  );
  return go('sent');
}
