// Cổng thanh toán: VNPay (v2.1.0), MoMo (v2), mã QR chuyển khoản (VietQR).
import { createHmac } from 'node:crypto';
import type { Order } from '@prisma/client';
import type { BankAccount } from './settings';

const site = () => (process.env.SITE_URL || 'http://localhost:3000').replace(/\/$/, '');

/* ================================================================ VNPay */

export function vnpayReady(): boolean {
  return !!(process.env.VNPAY_TMN_CODE && process.env.VNPAY_HASH_SECRET);
}

/** Mã hóa giống urlencode của PHP (VNPay ký theo kiểu này). */
function phpEncode(v: string): string {
  return encodeURIComponent(v).replace(/%20/g, '+').replace(/[!'()*~]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
}

function vnpSign(params: Record<string, string>): string {
  const data = Object.keys(params).sort().map((k) => `${phpEncode(k)}=${phpEncode(params[k])}`).join('&');
  return createHmac('sha512', process.env.VNPAY_HASH_SECRET || '').update(Buffer.from(data, 'utf-8')).digest('hex');
}

function vnTime(d: Date): string {
  const t = new Date(d.getTime() + 7 * 3600 * 1000); // giờ Việt Nam
  const p = (n: number) => String(n).padStart(2, '0');
  return `${t.getUTCFullYear()}${p(t.getUTCMonth() + 1)}${p(t.getUTCDate())}${p(t.getUTCHours())}${p(t.getUTCMinutes())}${p(t.getUTCSeconds())}`;
}

/** Link sang trang thanh toán VNPay. */
export function vnpayUrl(order: Order, ip: string, ref: string): string {
  const now = new Date();
  const params: Record<string, string> = {
    vnp_Version: '2.1.0',
    vnp_Command: 'pay',
    vnp_TmnCode: process.env.VNPAY_TMN_CODE || '',
    vnp_Amount: String(order.total * 100),
    vnp_CurrCode: 'VND',
    vnp_TxnRef: ref,
    vnp_OrderInfo: `Thanh toan don hang ${order.id}`,
    vnp_OrderType: 'other',
    vnp_Locale: 'vn',
    vnp_ReturnUrl: `${site()}/api/thanh-toan/vnpay/`,
    vnp_IpAddr: ip || '127.0.0.1',
    vnp_CreateDate: vnTime(now),
    vnp_ExpireDate: vnTime(new Date(now.getTime() + 15 * 60 * 1000)),
  };
  const qs = Object.keys(params).sort().map((k) => `${phpEncode(k)}=${phpEncode(params[k])}`).join('&');
  return `${process.env.VNPAY_URL || 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'}?${qs}&vnp_SecureHash=${vnpSign(params)}`;
}

/** Kiểm tra dữ liệu VNPay gửi về. */
export function vnpayVerify(query: URLSearchParams): { valid: boolean; paid: boolean; ref: string; amount: number; transNo: string } {
  const params: Record<string, string> = {};
  query.forEach((v, k) => {
    if (k.startsWith('vnp_') && k !== 'vnp_SecureHash' && k !== 'vnp_SecureHashType') params[k] = v;
  });
  const valid = !!process.env.VNPAY_HASH_SECRET && vnpSign(params) === (query.get('vnp_SecureHash') || '').toLowerCase();
  return {
    valid,
    paid: params.vnp_ResponseCode === '00' && params.vnp_TransactionStatus === '00',
    ref: params.vnp_TxnRef || '',
    amount: Math.round(Number(params.vnp_Amount || 0) / 100),
    transNo: params.vnp_TransactionNo || '',
  };
}

/* ================================================================= MoMo */

export function momoReady(): boolean {
  return !!(process.env.MOMO_PARTNER_CODE && process.env.MOMO_ACCESS_KEY && process.env.MOMO_SECRET_KEY);
}

function momoSign(raw: string): string {
  return createHmac('sha256', process.env.MOMO_SECRET_KEY || '').update(raw).digest('hex');
}

/** Tạo giao dịch MoMo, trả về link thanh toán. */
export async function momoCreate(order: Order, ref: string): Promise<{ ok: boolean; payUrl?: string; message: string }> {
  const b = {
    partnerCode: process.env.MOMO_PARTNER_CODE || '',
    accessKey: process.env.MOMO_ACCESS_KEY || '',
    requestId: ref,
    amount: String(order.total),
    orderId: ref,
    orderInfo: `Thanh toan don hang ${order.id}`,
    redirectUrl: `${site()}/api/thanh-toan/momo/`,
    ipnUrl: `${site()}/api/thanh-toan/momo/`,
    requestType: 'captureWallet',
    extraData: '',
  };
  const raw = `accessKey=${b.accessKey}&amount=${b.amount}&extraData=${b.extraData}&ipnUrl=${b.ipnUrl}&orderId=${b.orderId}&orderInfo=${b.orderInfo}&partnerCode=${b.partnerCode}&redirectUrl=${b.redirectUrl}&requestId=${b.requestId}&requestType=${b.requestType}`;
  try {
    const res = await fetch(process.env.MOMO_ENDPOINT || 'https://test-payment.momo.vn/v2/gateway/api/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...b, partnerName: 'KhoaHoc', storeId: 'KhoaHoc', lang: 'vi', autoCapture: true, signature: momoSign(raw) }),
      signal: AbortSignal.timeout(20000),
    });
    const data = (await res.json()) as { resultCode?: number; payUrl?: string; message?: string };
    return data.resultCode === 0 && data.payUrl ? { ok: true, payUrl: data.payUrl, message: 'ok' } : { ok: false, message: data.message || 'MoMo từ chối giao dịch.' };
  } catch (e) {
    return { ok: false, message: 'Không kết nối được MoMo: ' + String(e).slice(0, 120) };
  }
}

const MOMO_RESULT_KEYS = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'];

/** Kiểm tra dữ liệu MoMo gửi về (trang quay lại hoặc IPN). */
export function momoVerify(p: Record<string, string>): { valid: boolean; paid: boolean; ref: string; amount: number; transId: string } {
  const v: Record<string, string> = { ...p, accessKey: process.env.MOMO_ACCESS_KEY || '' };
  const raw = MOMO_RESULT_KEYS.map((k) => `${k}=${v[k] ?? ''}`).join('&');
  return {
    valid: !!process.env.MOMO_SECRET_KEY && momoSign(raw) === p.signature,
    paid: String(p.resultCode) === '0',
    ref: p.orderId || '',
    amount: Number(p.amount || 0),
    transId: String(p.transId || ''),
  };
}

/* ============================================================ VietQR */

const BANK_BIN: Record<string, string> = {
  vietcombank: '970436', vcb: '970436', vietinbank: '970415', bidv: '970418', agribank: '970405', techcombank: '970407', tcb: '970407',
  mbbank: '970422', mb: '970422', acb: '970416', vpbank: '970432', tpbank: '970423', sacombank: '970403', hdbank: '970437', vib: '970441',
  shb: '970443', ocb: '970448', msb: '970426', seabank: '970440', eximbank: '970431', lpbank: '970449', namabank: '970428', bacabank: '970409',
};

/** Ảnh mã QR chuyển khoản (quét bằng app ngân hàng: điền sẵn số tiền + nội dung). */
export function vietQr(acc: BankAccount, amount: number, info: string): string {
  const key = acc.bank_name.toLowerCase().replace(/[^a-z]/g, '').replace(/^ngânhàng|^nganhang/, '');
  const bin = BANK_BIN[key] || Object.entries(BANK_BIN).find(([k]) => key.includes(k))?.[1];
  const no = acc.account_number.replace(/\s/g, '');
  if (!bin || !no) return '';
  return `https://img.vietqr.io/image/${bin}-${encodeURIComponent(no)}-compact2.png?amount=${amount}&addInfo=${encodeURIComponent(info)}&accountName=${encodeURIComponent(acc.account_name)}`;
}
