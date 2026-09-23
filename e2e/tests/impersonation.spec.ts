import { expect, test } from '@playwright/test';
import { ADMIN, PASSWORD, STUDENT, confirmOk, login, php } from './helpers';

test('admin đăng nhập hộ học sinh: có dải cảnh báo, bị chặn việc nhạy cảm, thoát về đúng admin', async ({ page }) => {
  const studentId = php("echo App\\Models\\User::where('email','student@gmail.com')->value('id');");

  await login(page, ADMIN);
  await page.goto(`/quan-tri/nguoi-dung/${studentId}`);
  await page.getByRole('button', { name: 'Đăng nhập hộ' }).click();
  await confirmOk(page);

  // Đang là học sinh, dải đỏ hiện rõ.
  await expect(page).toHaveURL(/\/hoc-sinh/);
  const banner = page.locator('[role="alert"].bg-danger');
  await expect(banner).toContainText('Đang đăng nhập hộ');

  // Đổi mật khẩu bị chặn (kể cả khi biết mật khẩu hiện tại).
  await page.goto('/hoc-sinh/cai-dat');
  await page.locator('#current_password').fill(PASSWORD);
  await page.locator('#password').fill('Newpass123');
  await page.locator('#password_confirmation').fill('Newpass123');
  await page.getByRole('button', { name: 'Đổi mật khẩu' }).click();
  await expect(page.getByText('Đang đăng nhập hộ — không được thực hiện thao tác này')).toBeVisible();

  // Vào cổng quản trị bằng quyền học sinh: bị từ chối.
  const res = await page.goto('/quan-tri');
  expect(res?.status()).toBe(403);

  // Thoát → về trang người dùng, lại là admin, dải đỏ biến mất.
  await page.goto('/hoc-sinh');
  await banner.getByRole('button', { name: 'Thoát chế độ này' }).click();
  await expect(page).toHaveURL(new RegExp(`/quan-tri/nguoi-dung/${studentId}`));
  await expect(page.locator('[role="alert"].bg-danger')).toHaveCount(0);

  // Mật khẩu học sinh vẫn là mật khẩu cũ.
  await login(page, STUDENT);
  await expect(page).toHaveURL(/\/hoc-sinh/);
});
