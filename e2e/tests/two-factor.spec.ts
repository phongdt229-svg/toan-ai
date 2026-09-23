import { expect, test } from '@playwright/test';
import { ADMIN, PASSWORD, confirmOk, login, php, totp, totpStep, waitForNextStep } from './helpers';

test.describe.configure({ mode: 'serial' });

const resetAdmin = () => php(
  "App\\Models\\User::where('email','admin@gmail.com')->update(['two_factor_secret'=>null,'two_factor_recovery_codes'=>null,'two_factor_confirmed_at'=>null,'two_factor_last_step'=>null]); echo 'ok';",
);

test.beforeAll(resetAdmin);
test.afterAll(resetAdmin); // dù test hỏng giữa chừng cũng không để admin demo kẹt 2FA

test('admin bật 2FA, đăng nhập bằng mã dự phòng rồi bằng mã TOTP, sai mã bị chặn, tắt được', async ({ page }) => {
  // --- Bật ---
  await login(page, ADMIN);
  await expect(page).toHaveURL(/\/quan-tri/);
  await page.goto('/quan-tri/cai-dat');

  await page.locator('#pw-on').fill(PASSWORD);
  await page.getByRole('button', { name: 'Bắt đầu cài' }).click();

  // QR + khoá nhập tay hiện ra; secret CHƯA được tính là bật.
  await expect(page.locator('svg').first()).toBeVisible();
  const secret = (await page.locator('text=Nhập tay khoá').locator('code').innerText()).trim();
  expect(secret).toMatch(/^[A-Z2-7]{32}$/);

  // Nhập sai trước — không bật.
  await page.locator('input[name="code"]').fill('000000');
  await page.getByRole('button', { name: 'Xác nhận' }).click();
  await expect(page.getByText('Mã không đúng')).toBeVisible();

  // Nhập đúng (mã tính độc lập ở phía test) → bật + hiện mã dự phòng đúng một lần.
  const confirmStep = totpStep();
  await page.locator('input[name="code"]').fill(totp(secret, confirmStep));
  await page.getByRole('button', { name: 'Xác nhận' }).click();
  await expect(page.getByText('Đã bật xác thực 2 bước')).toBeVisible();
  const recovery = (await page.locator('.alert-warning .font-monospace div').allInnerTexts()).map((t) => t.trim());
  expect(recovery).toHaveLength(8);
  expect(recovery[0]).toMatch(/^[A-Z0-9]{5}-[A-Z0-9]{5}$/);

  // Tải lại trang: mã dự phòng và secret không hiện lại.
  await page.reload();
  await expect(page.getByText('Đang bật')).toBeVisible();
  await expect(page.locator('.font-monospace')).toHaveCount(0);

  // --- Đăng nhập: mật khẩu đúng chưa đủ ---
  await login(page, ADMIN);
  await expect(page).toHaveURL(/xac-thuc-2-buoc/);
  await page.goto('/quan-tri'); // thử đi tắt
  await expect(page).toHaveURL(/dang-nhap/);

  // Sai mã.
  await login(page, ADMIN);
  await page.locator('#code').fill('123456');
  await page.getByRole('button', { name: 'Xác nhận' }).click();
  await expect(page.getByText('Mã không đúng hoặc đã dùng rồi')).toBeVisible();

  // Mã dự phòng dùng được một lần.
  await page.locator('#code').fill(recovery[0]);
  await page.getByRole('button', { name: 'Xác nhận' }).click();
  await expect(page).toHaveURL(/\/quan-tri/);

  await login(page, ADMIN);
  await page.locator('#code').fill(recovery[0]);
  await page.getByRole('button', { name: 'Xác nhận' }).click();
  await expect(page.getByText('Mã không đúng hoặc đã dùng rồi')).toBeVisible();

  // Mã TOTP: phải sang bước 30 giây mới (bước lúc xác nhận đã dùng rồi).
  await waitForNextStep(confirmStep);
  await page.locator('#code').fill(totp(secret));
  await page.getByRole('button', { name: 'Xác nhận' }).click();
  await expect(page).toHaveURL(/\/quan-tri/);

  // --- Tắt ---
  await page.goto('/quan-tri/cai-dat');
  await page.locator('#pw-off').fill(PASSWORD);
  await page.getByRole('button', { name: 'Tắt', exact: true }).click();
  await confirmOk(page);
  await expect(page.getByText('Đã tắt xác thực 2 bước')).toBeVisible();

  // Sau khi tắt, đăng nhập lại chỉ cần mật khẩu.
  await login(page, ADMIN);
  await expect(page).toHaveURL(/\/quan-tri/);
});
