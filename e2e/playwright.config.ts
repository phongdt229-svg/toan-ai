import { defineConfig } from '@playwright/test';

/**
 * E2E chạy trên app local (docker compose -f docker-compose.dev.yml up) với tài khoản demo.
 * Dùng Chrome cài sẵn trên máy (channel: 'chrome') để khỏi tải trình duyệt riêng.
 * Chạy: cd e2e && npm install && npx playwright test
 */
export default defineConfig({
  testDir: './tests',
  timeout: 90_000,
  workers: 1, // các luồng dùng chung tài khoản demo — chạy tuần tự
  fullyParallel: false,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
    channel: 'chrome',
    headless: true,
    locale: 'vi-VN',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
