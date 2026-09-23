import { expect, test } from '@playwright/test';
import { STUDENT, confirmOk, login, makePng, php } from './helpers';

test('học sinh tải ảnh đại diện, thấy ở hồ sơ và thanh trên, từ chối file giả, xoá được', async ({ page }) => {
  await login(page, STUDENT);
  await page.goto('/hoc-sinh/cai-dat');

  // File đội lốt ảnh bị từ chối.
  await page.locator('input[name="avatar"]').setInputFiles({ name: 'shell.png', mimeType: 'image/png', buffer: Buffer.from('<?php echo 1;') });
  await page.getByRole('button', { name: 'Tải lên' }).click();
  await expect(page.locator('.text-danger.small').first()).toBeVisible();

  // Ảnh thật: cắt vuông 256px, hiện ở hồ sơ + thanh trên.
  await page.locator('input[name="avatar"]').setInputFiles({ name: 'me.png', mimeType: 'image/png', buffer: makePng(96) });
  await page.getByRole('button', { name: 'Tải lên' }).click();
  await expect(page.getByText('Đã cập nhật ảnh đại diện')).toBeVisible();

  const avatar = page.locator('img[alt="Ảnh đại diện"]');
  await expect(avatar).toBeVisible();
  await expect(page.locator('header img.rounded-circle')).toBeVisible();

  const src = await avatar.getAttribute('src');
  expect(src).toContain('/storage/avatars/');
  const file = await page.request.get(src!);
  expect(file.status()).toBe(200);
  expect(file.headers()['content-type']).toContain('image/jpeg');

  // Xoá: file trên đĩa biến mất, giao diện về biểu tượng mặc định.
  await page.getByRole('button', { name: 'Xoá ảnh' }).click();
  await confirmOk(page);
  await expect(page.getByText('Đã xoá ảnh đại diện')).toBeVisible();
  await expect(page.locator('img[alt="Ảnh đại diện"]')).toHaveCount(0);
  // Không còn phục vụ được nữa (server dev trả 403/404 tuỳ đường tĩnh hay route).
  expect((await page.request.get(src!)).status()).not.toBe(200);

  expect(php("echo App\\Models\\User::where('email','student@gmail.com')->value('avatar') ?? 'null';")).toBe('null');
});
