import { execSync } from 'node:child_process';
import { createHmac } from 'node:crypto';
import { resolve } from 'node:path';
import { deflateSync } from 'node:zlib';
import { expect, Page } from '@playwright/test';

export const PASSWORD = process.env.E2E_PASSWORD ?? 'password';
export const ADMIN = 'admin@gmail.com';
export const STUDENT = 'student@gmail.com';

const ROOT = resolve(__dirname, '../..');

/** Chạy một đoạn PHP trong container app (dọn dữ liệu / đọc id). Trả về stdout đã trim. */
export function php(code: string): string {
  const out = execSync(
    `docker compose -f docker-compose.dev.yml exec -T app php artisan tinker --execute=${JSON.stringify(code)}`,
    { cwd: ROOT, encoding: 'utf8' },
  );
  return out.trim().split('\n').pop()!.trim();
}

export async function login(page: Page, email: string, password = PASSWORD) {
  await page.context().clearCookies();
  await page.goto('/dang-nhap');
  await page.locator('form[action$="/dang-nhap"] input[name="email"]').fill(email);
  await page.locator('form[action$="/dang-nhap"] input[name="password"]').fill(password);
  await page.locator('form[action$="/dang-nhap"] button:not([type="button"])').click();
}

/** Hộp thoại xác nhận của app (`data-confirm`), không phải confirm() của trình duyệt. */
export async function confirmOk(page: Page) {
  const ok = page.locator('#app-confirm-ok');
  await expect(ok).toBeVisible();
  await ok.click();
}

// --- TOTP (RFC 6238) — cùng thuật toán với TwoFactorService, viết lại độc lập để kiểm chéo ---
const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function b32decode(text: string): Buffer {
  let bits = '';
  for (const c of text.toUpperCase()) {
    const i = B32.indexOf(c);
    if (i >= 0) bits += i.toString(2).padStart(5, '0');
  }
  const bytes: number[] = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
  return Buffer.from(bytes);
}

export const totpStep = (t = Date.now()) => Math.floor(t / 1000 / 30);

export function totp(secret: string, step = totpStep()): string {
  const msg = Buffer.alloc(8);
  msg.writeBigUInt64BE(BigInt(step));
  const h = createHmac('sha1', b32decode(secret)).update(msg).digest();
  const off = h[19] & 0x0f;
  const n = ((h[off] & 0x7f) << 24) | (h[off + 1] << 16) | (h[off + 2] << 8) | h[off + 3];
  return String(n % 1_000_000).padStart(6, '0');
}

/** Đợi sang bước 30 giây kế tiếp — mỗi mã TOTP chỉ dùng một lần. */
export async function waitForNextStep(after: number) {
  while (totpStep() <= after) await new Promise((r) => setTimeout(r, 1000));
}

// --- PNG hợp lệ 96x96 dựng bằng tay (không cần thư viện ảnh) ---
function crc32(buf: Buffer): number {
  let c = ~0;
  for (const b of buf) {
    c ^= b;
    for (let k = 0; k < 8; k++) c = (c >>> 1) ^ (0xedb88320 & -(c & 1));
  }
  return ~c >>> 0;
}

function chunk(type: string, data: Buffer): Buffer {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body));
  return Buffer.concat([len, body, crc]);
}

export function makePng(size = 96): Buffer {
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(size, 0);
  ihdr.writeUInt32BE(size, 4);
  ihdr[8] = 8; // 8 bit
  ihdr[9] = 2; // RGB
  const row = Buffer.concat([Buffer.from([0]), Buffer.alloc(size * 3, 0x60)]);
  const raw = Buffer.concat(Array.from({ length: size }, () => row));

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', deflateSync(raw)),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}
