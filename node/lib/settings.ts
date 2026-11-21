// Cài đặt website (giống "Cài đặt website" của theme WordPress), lưu trong bảng Setting.
import { cache } from 'react';
import { db } from './db';
import { digits } from './html';
import fields from './option-fields.json';

export type MenuItem = { label: string; url: string; children: MenuItem[] };
export type Menu = { name: string; items: MenuItem[] } | null;
export type BankAccount = { bank_name: string; account_number: string; account_name: string };

export type Settings = {
  kh: Record<string, string>;
  bacs: { enabled: boolean; instructions: string; accounts: BankAccount[] };
  payments: { vnpay: boolean; momo: boolean };
  menus: { primary: Menu; footer_1: Menu; footer_2: Menu };
};

type FieldDef = { label: string; default: string; type?: string; help?: string; options?: Record<string, string>; min?: number; max?: number };
export const OPTION_GROUPS = (fields as unknown as { groups: Record<string, { label: string; fields: Record<string, FieldDef> }> }).groups;
export const COURSE_FIELDS = (fields as unknown as { courseFields: Record<string, FieldDef> }).courseFields;

export function optionDefaults(): Record<string, string> {
  const out: Record<string, string> = {};
  for (const g of Object.values(OPTION_GROUPS)) for (const [k, f] of Object.entries(g.fields)) out[k] = String(f.default ?? '');
  return out;
}

const DEFAULTS: Omit<Settings, 'kh'> = {
  bacs: { enabled: true, instructions: '', accounts: [] },
  payments: { vnpay: false, momo: false },
  menus: { primary: null, footer_1: null, footer_2: null },
};

/** Đọc toàn bộ cài đặt (1 lần mỗi lượt tải trang). */
export const getSettings = cache(async (): Promise<Settings> => {
  const rows = await db.setting.findMany();
  const raw: Record<string, unknown> = {};
  for (const r of rows) {
    try {
      raw[r.key] = JSON.parse(r.value);
    } catch {
      raw[r.key] = null;
    }
  }
  return {
    kh: { ...optionDefaults(), ...((raw.kh as Record<string, string>) || {}) },
    bacs: { ...DEFAULTS.bacs, ...((raw.bacs as object) || {}) },
    payments: { ...DEFAULTS.payments, ...((raw.payments as object) || {}) },
    menus: { ...DEFAULTS.menus, ...((raw.menus as object) || {}) },
  };
});

export async function saveSetting(key: keyof Settings, value: unknown) {
  const v = JSON.stringify(value);
  await db.setting.upsert({ where: { key }, update: { value: v }, create: { key, value: v } });
}

/** 1 giá trị cài đặt ({year} trong dòng bản quyền tự đổi thành năm nay). */
export function opt(s: Settings, key: string): string {
  const v = String(s.kh[key] ?? '');
  return key === 'copyright' ? v.replace('{year}', String(new Date().getFullYear())) : v;
}

export function zaloUrl(s: Settings): string {
  const z = digits(opt(s, 'zalo') || opt(s, 'phone'));
  return z ? `https://zalo.me/${z}` : '';
}

/** Pha màu với trắng (amount > 0) hoặc đen (amount < 0) – giống kh_mix. */
export function mix(hex: string, amount: number): string {
  const h = String(hex).replace('#', '');
  if (h.length !== 6) return '#' + h;
  const rgb = [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16)).map((c) => {
    const v = amount >= 0 ? c + (255 - c) * amount : c * (1 + amount);
    return Math.max(0, Math.min(255, Math.round(v)));
  });
  return '#' + rgb.map((c) => c.toString(16).padStart(2, '0')).join('');
}

export function cssVars(s: Settings): string {
  const p = opt(s, 'color_primary') || '#157A4F';
  return `:root{--kh-primary:${p};--kh-primary-dark:${mix(p, -0.22)};--kh-primary-soft:${mix(p, 0.9)};--kh-mint:${mix(p, 0.75)};--kh-ink:${opt(s, 'color_ink')};--kh-deep:${opt(s, 'color_deep')};--kh-body:#57586E}`;
}

const RANGES = {
  latin: 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD',
  vietnamese: 'U+0102-0103,U+0110-0111,U+0128-0129,U+0168-0169,U+01A0-01A1,U+01AF-01B0,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+0329,U+1EA0-1EF9,U+20AB',
};

/** @font-face cho font tự lưu (chỉ bộ chữ Việt + Latin). */
export function fontFaces(): string {
  const fonts: [string, string, number[]][] = [
    ['Inter', 'inter', [400, 500, 600]],
    ['Be Vietnam Pro', 'be-vietnam-pro', [600, 700, 800]],
  ];
  let css = '';
  for (const [family, file, weights] of fonts) {
    for (const w of weights) {
      for (const [subset, range] of Object.entries(RANGES)) {
        css += `@font-face{font-family:'${family}';font-style:normal;font-display:swap;font-weight:${w};src:url(/fonts/${file}-${subset}-${w}-normal.woff2) format('woff2');unicode-range:${range}}`;
      }
    }
  }
  return css;
}
