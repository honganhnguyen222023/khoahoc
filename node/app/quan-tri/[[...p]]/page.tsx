import { notFound, redirect } from 'next/navigation';
import { Html } from '@/components/Html';
import { authPage } from '@/lib/admin/login';
import { pageDashboard, pageOrders, pageStudents } from '@/lib/admin/pages-a';
import { pageAccount, pageContacts, pageCourses, pageSettings } from '@/lib/admin/pages-b';
import { adminShell, type AdminPage, canSection, denied, SECTIONS } from '@/lib/admin/ui';
import { getAdminUser } from '@/lib/auth';
import { isStaff } from '@/lib/lms';
import type { SP } from '@/lib/page';
import { getSettings } from '@/lib/settings';

export const dynamic = 'force-dynamic';

type P = { params: Promise<{ p?: string[] }>; searchParams: Promise<SP> };

export async function generateMetadata({ params }: P) {
  const sec = (await params).p?.[0] || 'tong-quan';
  return { title: sec === 'dang-nhap' ? 'Đăng nhập quản trị' : `${SECTIONS[sec]?.label || 'Quản trị'} · Quản trị` };
}

export default async function Admin({ params, searchParams }: P) {
  const p = (await params).p || [];
  const sp = await searchParams;
  const q: Record<string, string> = Object.fromEntries(Object.entries(sp).map(([k, v]) => [k, Array.isArray(v) ? v[0] || '' : v || '']));
  const s = await getSettings();
  const user = await getAdminUser();
  const section = p[0] || 'tong-quan';
  const id = p[1] || '';

  if (section === 'dang-nhap' || section === 'quen-mat-khau') {
    if (user && isStaff(user)) redirect('/quan-tri/');
    return (
      <div className="qt-auth-body">
        <Html html={authPage(s, section, q)} />
      </div>
    );
  }
  if (!user) redirect(`/quan-tri/dang-nhap/?quay-lai=${encodeURIComponent('/quan-tri/' + p.map((x) => x + '/').join(''))}`);
  if (!isStaff(user)) redirect('/');
  if (!SECTIONS[section]) notFound();

  let page: AdminPage;
  if (!canSection(user, section)) page = denied();
  else if (section === 'tong-quan') page = await pageDashboard(user);
  else if (section === 'don-hang') page = await pageOrders(id, q);
  else if (section === 'hoc-vien') page = await pageStudents(id, q);
  else if (section === 'khoa-hoc') page = await pageCourses(id);
  else if (section === 'lien-he') page = await pageContacts(q);
  else if (section === 'tai-khoan') page = await pageAccount(user);
  else page = await pageSettings(q);

  const flash = q.tb ? { msg: q.tb.slice(0, 600), ok: q.ok !== '0' } : null;
  return <Html html={await adminShell(s, user, section, page, flash)} />;
}
