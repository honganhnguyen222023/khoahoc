// Tiện ích cho trang: đọc tham số URL, in HTML.
export type SP = Record<string, string | string[] | undefined>;

/** Lấy 1 giá trị ?key= (chấp nhận cả key[] như form PHP). */
export function q(sp: SP, key: string): string {
  const v = sp[key] ?? sp[key + '[]'];
  return Array.isArray(v) ? v[0] ?? '' : v ?? '';
}

/** Lấy danh sách ?key[]=a&key[]=b. */
export function qList(sp: SP, key: string): string[] {
  const v = sp[key + '[]'] ?? sp[key];
  return (Array.isArray(v) ? v : v ? [v] : []).map(String).filter(Boolean);
}
