// Icon Lucide (inline SVG) – cùng bộ icon với theme WordPress.
import data from './icons-data.json';

const ICONS = data as Record<string, string>;

/** HTML của 1 icon (dùng trong chuỗi HTML). */
export function icon(name: string, cls = '', size = 20): string {
  const inner = ICONS[name];
  if (!inner) return '';
  return `<svg class="${('kh-svg ' + cls).trim()}" xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${inner}</svg>`;
}

export function hasIcon(name: string): boolean {
  return !!ICONS[name];
}

export function iconNames(): string[] {
  return Object.keys(ICONS);
}
