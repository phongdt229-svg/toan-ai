import { expect, test } from '@playwright/test';
import { ADMIN, STUDENT, confirmOk, login } from './helpers';

/** Cần PAYMENT_GATEWAY=fake (mặc định ở .env local): học sinh mua gói qua trang giả lập MoMo, admin hoàn tiền. */
test('mua gói → hoàn một phần (gói còn) → hoàn phần còn lại (gói bị thu hồi)', async ({ page }) => {
  // --- Học sinh mua Pro tháng ---
  await login(page, STUDENT);
  await page.goto('/goi-hoc/pro-thang/mua');
  await page.locator('form[action$="/goi-hoc/pro-thang/mua"] button').last().click();

  await expect(page).toHaveURL(/thanh-toan\/.+\/gia-lap/);
  const orderCode = decodeURIComponent(page.url().split('/thanh-toan/')[1].split('/')[0]);
  await page.getByRole('button', { name: 'Xác nhận thanh toán' }).click();
  await expect(page.getByRole('heading', { name: 'Thanh toán thành công' })).toBeVisible({ timeout: 20_000 });

  // --- Admin hoàn một phần ---
  await login(page, ADMIN);
  await page.goto(`/quan-tri/giao-dich/${orderCode}`);
  await expect(page.locator('h2 code')).toHaveText(orderCode);
  await expect(page.locator('.badge', { hasText: 'Thành công' })).toBeVisible();

  const form = page.locator('form[action$="/hoan-tien"]');
  await form.locator('input[name="amount"]').fill('30000');
  await form.locator('input[name="reason"]').fill('E2E hoàn một phần');
  await form.getByRole('button', { name: 'Hoàn tiền' }).click();
  await confirmOk(page);

  await expect(page.getByText('hoàn một phần, gói được giữ nguyên')).toBeVisible();
  await expect(page.locator('.badge', { hasText: 'Thành công' })).toBeVisible(); // đơn vẫn paid
  await expect(page.getByText('−30.000₫')).toBeVisible();

  // Số tiền vượt phần còn lại: trình duyệt chặn trước (max), và server cũng chặn khi bị qua mặt.
  const amount = form.locator('input[name="amount"]');
  await amount.fill('99999999');
  expect(await amount.evaluate((el: HTMLInputElement) => el.validity.rangeOverflow)).toBe(true);
  await amount.evaluate((el: HTMLInputElement) => el.removeAttribute('max'));
  await form.locator('input[name="reason"]').fill('quá tay');
  await form.getByRole('button', { name: 'Hoàn tiền' }).click();
  await confirmOk(page);
  await expect(page.locator('.alert-danger').first()).toContainText('Số tiền hoàn phải từ 1₫ đến 69.000₫');

  // --- Hoàn phần còn lại (để trống số tiền) ---
  await page.goto(`/quan-tri/giao-dich/${orderCode}`);
  const form2 = page.locator('form[action$="/hoan-tien"]');
  await form2.locator('input[name="reason"]').fill('E2E hoàn nốt');
  await form2.getByRole('button', { name: 'Hoàn tiền' }).click();
  await confirmOk(page);

  await expect(page.getByText('Đã hoàn toàn bộ tiền và thu hồi gói')).toBeVisible();
  await expect(page.locator('.badge', { hasText: 'Đã hoàn tiền' })).toBeVisible();
  await expect(page.locator('form[action$="/hoan-tien"]')).toHaveCount(0); // hết tiền để hoàn

  // --- Học sinh thấy đơn đã hoàn ---
  await login(page, STUDENT);
  await page.goto(`/thanh-toan/${orderCode}`);
  await expect(page.getByText('Đơn đã được hoàn tiền')).toBeVisible();
});
