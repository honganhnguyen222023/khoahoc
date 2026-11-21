// Đầu trang / chân trang (giống header.php, footer.php của theme).
import type { User } from '@prisma/client';
import { digits, esc, linkAttrs } from './html';
import { icon } from './icons';
import { type MenuItem, opt, type Settings, zaloUrl } from './settings';

function menuHtml(items: MenuItem[] | undefined, cls: string): string {
  const list = items?.length
    ? items
    : [
        { label: 'Trang chủ', url: '/', children: [] },
        { label: 'Khóa học', url: '/khoa-hoc/', children: [] },
        { label: 'Giới thiệu', url: '/gioi-thieu/', children: [] },
        { label: 'Liên hệ', url: '/lien-he/', children: [] },
      ];
  const li = (it: MenuItem): string =>
    `<li class="menu-item${it.children.length ? ' menu-item-has-children' : ''}"><a ${linkAttrs(it.url)}>${esc(it.label)}</a>${it.children.length ? `<ul class="sub-menu">${it.children.map(li).join('')}</ul>` : ''}</li>`;
  return `<ul class="${cls}">${list.map(li).join('')}</ul>`;
}

function logo(s: Settings, withText: 'inline' | 'stack'): string {
  const img = opt(s, 'site_logo');
  if (img) return `<img class="kh-logo-img" src="${esc(img)}" alt="${esc(opt(s, 'brand_name'))}">`;
  if (withText === 'inline') return `<span class="kh-logo-mark">${esc(opt(s, 'brand_initial'))}</span><span class="kh-logo-text">${esc(opt(s, 'brand_name'))}</span>`;
  return `<span class="kh-logo-mark">${esc(opt(s, 'brand_initial'))}</span>`;
}

export function siteHeader(s: Settings, user: User | null, cartCount: number): string {
  const announce = opt(s, 'announce_text')
    ? `<div class="kh-announce">${opt(s, 'announce_url') ? `<a ${linkAttrs(opt(s, 'announce_url'))}>${esc(opt(s, 'announce_text'))} ${icon('arrow-right', '', 14)}</a>` : `<span>${esc(opt(s, 'announce_text'))}</span>`}</div>`
    : '';
  const menu = s.menus.primary?.items;
  const cta = opt(s, 'header_cta_text');
  const zalo = zaloUrl(s);
  const accUrl = user ? '/tai-khoan/khoa-hoc-cua-toi/' : '/tai-khoan/';
  const accLabel = user ? 'Khóa học của tôi' : 'Đăng nhập / Đăng ký';
  return `<a class="kh-skip" href="#main">Chuyển đến nội dung</a>${announce}
<header class="kh-header" data-header><div class="kh-container kh-header-row">
<a class="kh-logo" href="/" rel="home" aria-label="${esc(opt(s, 'brand_name'))}">${logo(s, 'inline')}</a>
<nav class="kh-nav" aria-label="Menu chính">${menuHtml(menu, 'kh-menu')}</nav>
<div class="kh-header-actions">
<a class="kh-cart" href="/gio-hang/" aria-label="Giỏ hàng">${icon('shopping-cart', '', 20)}<span class="kh-cart-count">${cartCount}</span></a>
<a class="kh-cart kh-account${user ? ' is-in' : ''}" href="${accUrl}" aria-label="${esc(accLabel)}" title="${esc(user ? user.name : accLabel)}">${icon('user', '', 20)}</a>
${user ? '' : `<a class="kh-btn kh-btn-outline kh-btn-sm kh-header-cta" href="/tai-khoan/dang-ky/">Đăng ký</a>`}${cta ? `<a class="kh-btn kh-btn-primary kh-btn-sm kh-header-cta" ${linkAttrs(opt(s, 'header_cta_url'))}>${esc(cta)}</a>` : ''}
<button class="kh-burger" type="button" aria-expanded="false" aria-controls="kh-drawer" data-menu-toggle><span class="screen-reader-text">Mở menu</span>${icon('menu', 'kh-burger-open', 24)}${icon('x', 'kh-burger-close', 24)}</button>
</div></div>
<div class="kh-drawer" id="kh-drawer" hidden><nav aria-label="Menu di động">${menuHtml(menu, 'kh-drawer-menu')}</nav>
<div class="kh-drawer-actions">${
    user
      ? `<a class="kh-btn kh-btn-outline" href="/tai-khoan/khoa-hoc-cua-toi/">Khóa học của tôi</a><a class="kh-btn kh-btn-outline" href="/tai-khoan/dang-xuat/">Đăng xuất</a>`
      : `<a class="kh-btn kh-btn-outline" href="/tai-khoan/">Đăng nhập</a><a class="kh-btn kh-btn-outline" href="/tai-khoan/dang-ky/">Đăng ký</a>`
  }${cta ? `<a class="kh-btn kh-btn-primary" ${linkAttrs(opt(s, 'header_cta_url'))}>${esc(cta)}</a>` : ''}${zalo ? `<a class="kh-btn kh-btn-outline" href="${esc(zalo)}" target="_blank" rel="noopener">Nhắn Zalo tư vấn</a>` : ''}</div></div>
</header>`;
}

export function siteFooter(s: Settings): string {
  const socials: [string, string][] = [['facebook', opt(s, 'facebook')], ['tiktok', opt(s, 'tiktok')], ['youtube', opt(s, 'youtube')], ['zalo', zaloUrl(s)]];
  const cols = (['footer_1', 'footer_2'] as const)
    .map((k) => s.menus[k])
    .filter(Boolean)
    .map((m) => `<nav class="kh-footer-col" aria-label="${esc(m!.name)}"><h2 class="kh-footer-title">${esc(m!.name.replace(/^Chân trang\s*–\s*/u, ''))}</h2>${menuHtml(m!.items, 'kh-footer-menu')}</nav>`)
    .join('');
  const contact: string[] = [];
  if (opt(s, 'phone')) contact.push(`<li><span class="kh-icon-box kh-icon-sm">${icon('phone', '', 16)}</span><span><small>Hotline / Zalo</small><a href="tel:${esc(digits(opt(s, 'phone')))}">${esc(opt(s, 'phone'))}</a></span></li>`);
  if (opt(s, 'email')) contact.push(`<li><span class="kh-icon-box kh-icon-sm">${icon('mail', '', 16)}</span><span><small>Email</small><a href="mailto:${esc(opt(s, 'email'))}">${esc(opt(s, 'email'))}</a></span></li>`);
  if (opt(s, 'address')) contact.push(`<li><span class="kh-icon-box kh-icon-sm">${icon('map-pin', '', 16)}</span><span><small>Địa chỉ</small>${esc(opt(s, 'address'))}</span></li>`);
  const zalo = zaloUrl(s);
  const float =
    opt(s, 'float_contact') === '1' && (zalo || opt(s, 'phone'))
      ? `<div class="kh-float" aria-label="Liên hệ nhanh">${zalo ? `<a class="kh-float-zalo" href="${esc(zalo)}" target="_blank" rel="noopener" aria-label="Nhắn Zalo"><span>Zalo</span></a>` : ''}${opt(s, 'phone') ? `<a class="kh-float-call" href="tel:${esc(digits(opt(s, 'phone')))}" aria-label="Gọi hotline">${icon('phone', '', 22)}</a>` : ''}</div>`
      : '';
  return `<footer class="kh-footer"><div class="kh-container"><div class="kh-footer-grid">
<div class="kh-footer-brand"><a class="kh-logo" href="/">${logo(s, 'stack')}<span><strong class="kh-logo-text">${esc(opt(s, 'brand_name'))}</strong><small>${esc(opt(s, 'brand_tagline'))}</small></span></a>
${opt(s, 'footer_about') ? `<p>${esc(opt(s, 'footer_about'))}</p>` : ''}<div class="kh-socials">${socials.filter(([, u]) => u).map(([n, u]) => `<a href="${esc(u)}" target="_blank" rel="noopener" aria-label="${n[0].toUpperCase() + n.slice(1)}">${icon(n, '', 18)}</a>`).join('')}</div></div>
${cols}
<div class="kh-footer-col"><h2 class="kh-footer-title">Liên hệ</h2><ul class="kh-footer-contact">${contact.join('')}</ul>${zalo ? `<a class="kh-btn kh-btn-primary kh-btn-sm" href="${esc(zalo)}" target="_blank" rel="noopener">Nhắn Zalo tư vấn ${icon('arrow-right', '', 16)}</a>` : ''}</div>
</div><div class="kh-footer-bottom"><p>${esc(opt(s, 'copyright'))}</p><p>${esc(opt(s, 'footer_note'))}</p></div></div></footer>${float}`;
}
