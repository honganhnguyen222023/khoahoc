// Nhập dữ liệu xuất từ WordPress (data/wordpress-export.json) vào cơ sở dữ liệu Node.
// Chạy: npm run import:wp   (XÓA dữ liệu cũ trong cơ sở dữ liệu Node rồi nhập lại từ đầu)
import { readFileSync } from 'node:fs';
import { PrismaClient } from '@prisma/client';

const file = process.argv[2] || 'data/wordpress-export.json';
const d = JSON.parse(readFileSync(file, 'utf8'));
const db = new PrismaClient();

// Trang của WooCommerce / tài khoản: bản Node có trang riêng nên không nhập.
const SYSTEM_PAGES = new Set(['shop', 'cart', 'checkout', 'my-account', 'tai-khoan']);

const int = (v) => (v === '' || v === null || v === undefined ? null : parseInt(String(v).replace(/\D/g, ''), 10) || null);
const date = (v) => {
  if (!v || String(v).startsWith('0000')) return new Date();
  const dt = new Date(String(v).includes('T') ? v : String(v).replace(' ', 'T') + 'Z');
  return isNaN(dt.getTime()) ? new Date() : dt;
};
const ts = (v) => new Date(Number(v) * 1000);

/** Menu phẳng → cây [{label,url,children}] */
function menuTree(menu) {
  if (!menu) return null;
  const byParent = {};
  for (const it of menu.items) (byParent[it.parent] ||= []).push(it);
  const build = (pid) => (byParent[pid] || []).map((it) => ({ label: it.label, url: it.url, children: build(it.id) }));
  return { name: menu.name, items: build(0) };
}

function role(roles) {
  if (roles.includes('administrator')) return 'admin';
  if (roles.some((r) => ['editor', 'author', 'shop_manager', 'contributor'].includes(r))) return 'staff';
  return 'student';
}

async function main() {
  console.log(`Nhập từ ${file} (xuất lúc ${d.exportedAt}, nguồn ${d.source})`);

  // Xóa sạch (thứ tự theo ràng buộc khóa ngoại).
  for (const m of ['orderNote', 'orderItem', 'order', 'viewRecord', 'lessonOpen', 'progress', 'access', 'session', 'passwordReset', 'courseCategory', 'course', 'category', 'user', 'page', 'contact', 'setting']) {
    await db[m].deleteMany();
  }

  // Cài đặt.
  const bacs = d.bacs || {};
  const settings = {
    kh: { ...d.settings, site_logo: d.site?.logo || '' },
    bacs: {
      enabled: (bacs.settings?.enabled ?? 'yes') !== 'no',
      instructions: bacs.settings?.instructions || '',
      accounts: (bacs.accounts || []).map((a) => ({ bank_name: a.bank_name || '', account_number: a.account_number || '', account_name: a.account_name || '' })),
    },
    payments: { vnpay: false, momo: true },
    menus: { primary: menuTree(d.menus.primary), footer_1: menuTree(d.menus.footer_1), footer_2: menuTree(d.menus.footer_2) },
  };
  for (const [key, value] of Object.entries(settings)) {
    await db.setting.create({ data: { key, value: JSON.stringify(value) } });
  }

  // Danh mục.
  for (const c of d.categories) {
    await db.category.create({ data: { id: c.id, name: c.name, slug: c.slug, description: c.description || '' } });
  }

  // Khóa học (giữ nguyên ID để đơn hàng / quyền học khớp).
  for (const c of d.courses) {
    const m = c.meta;
    await db.course.create({
      data: {
        id: c.id, slug: decodeURIComponent(c.slug), title: c.title, excerpt: c.excerpt || '', status: c.status === 'publish' ? 'publish' : 'draft',
        sortOrder: c.order || 0, price: int(m.price), regularPrice: int(m.regular_price), badge: m.badge || '', icon: m.icon || '',
        metaLine: m.meta_line || '', audience: m.audience || '', benefits: m.benefits || '', video: m.video || '',
        registerUrl: m.register_url || '', detailUrl: m.detail_url || '', duration: m.duration || '', bundle: m.bundle || '',
        featured: m.featured === '1', cover: c.cover || '', thumbnail: c.thumbnail || '', blocks: JSON.stringify(c.blocks),
        createdAt: date(c.date),
        categories: { create: (c.categories || []).map((id) => ({ categoryId: id })) },
      },
    });
  }

  // Trang + bài viết.
  for (const [type, list] of [['page', d.pages], ['post', d.posts]]) {
    for (const p of list) {
      if (type === 'page' && SYSTEM_PAGES.has(p.slug)) continue;
      await db.page.create({
        data: {
          type, slug: decodeURIComponent(p.slug), title: p.title, excerpt: p.excerpt || '', status: p.status === 'publish' ? 'publish' : 'draft',
          isFront: type === 'page' && p.id === d.frontPage, thumbnail: p.thumbnail || '', blocks: JSON.stringify(p.blocks), createdAt: date(p.date),
        },
      });
    }
  }

  // Thành viên + quyền học + tiến độ + lượt xem.
  const courseIds = new Set(d.courses.map((c) => c.id));
  for (const u of d.users) {
    await db.user.create({
      data: {
        id: u.id, login: u.login, email: u.email.toLowerCase(), name: u.displayName || u.login, firstName: u.firstName || '', lastName: u.lastName || '',
        phone: u.phone || '', passwordHash: u.passHash, role: role(u.roles), createdAt: date(u.registered),
      },
    });
    for (const [cid, source] of Object.entries(u.courses || {})) {
      if (!courseIds.has(+cid)) continue;
      const since = u.since?.[cid] ? ts(u.since[cid]) : new Date();
      await db.access.create({ data: { userId: u.id, courseId: +cid, source: String(source), since } });
    }
    for (const [cid, lessons] of Object.entries(u.progress || {})) {
      for (const [n, t] of Object.entries(lessons || {})) await db.progress.create({ data: { userId: u.id, courseId: +cid, lesson: +n, doneAt: ts(t) } });
    }
    for (const [cid, lessons] of Object.entries(u.lessonOpen || {})) {
      for (const [n, t] of Object.entries(lessons || {})) await db.lessonOpen.create({ data: { userId: u.id, courseId: +cid, lesson: +n, openedAt: ts(t) } });
    }
    for (const [kind, map] of [['views', u.views], ['trial', u.trialViews]]) {
      for (const [cid, lessons] of Object.entries(map || {})) {
        for (const [n, r] of Object.entries(lessons || {})) {
          await db.viewRecord.create({ data: { userId: u.id, courseId: +cid, lesson: +n, kind, count: +r.count || 0, last: ts(r.last || 0), grace: !!r.g, tok: r.tok || '' } });
        }
      }
    }
  }

  // Đơn hàng.
  for (const o of d.orders) {
    await db.order.create({
      data: {
        id: o.id, userId: o.customerId || null, status: o.status, total: Math.round(o.total), discount: Math.round(o.discount || 0),
        paymentMethod: o.paymentMethod || 'bacs', paymentTitle: o.paymentTitle || '', firstName: o.firstName || '', lastName: o.lastName || '',
        email: o.email || '', phone: o.phone || '', note: o.note || '', createdAt: date(o.createdAt), paidAt: o.paidAt ? date(o.paidAt) : null,
        items: { create: o.items.map((it) => ({ name: it.name, courseId: courseIds.has(it.courses?.[0]) ? it.courses[0] : null, total: Math.round(it.total) })) },
        notes: { create: o.notes.map((n) => ({ content: n.content, author: n.by || 'system', createdAt: date(n.date) })) },
      },
    });
  }

  for (const c of d.contacts) {
    await db.contact.create({ data: { name: c.name, phone: c.phone, email: c.email, topic: c.topic, message: c.message, source: c.source, status: c.status, createdAt: date(c.date) } });
  }

  const counts = {};
  for (const m of ['setting', 'category', 'course', 'page', 'user', 'access', 'progress', 'viewRecord', 'order', 'contact']) counts[m] = await db[m].count();
  console.log('Đã nhập:', counts);
}

main()
  .catch((e) => { console.error(e); process.exit(1); })
  .finally(() => db.$disconnect());
