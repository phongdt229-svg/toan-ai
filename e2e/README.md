# E2E (Playwright)

Kiểm các luồng nhạy cảm bằng trình duyệt thật: 2FA, đăng nhập hộ, ảnh đại diện, hoàn tiền.

```bash
docker compose -f docker-compose.dev.yml up -d   # app + DB local, có seed tài khoản demo
cd e2e && npm install
npx playwright test                              # dùng Chrome cài sẵn trên máy (channel: chrome)
```

- Chạy trên **DB dev** với tài khoản demo — không dùng ở production. Test tự dọn trạng thái 2FA của admin demo.
- `refund.spec.ts` cần `PAYMENT_GATEWAY=fake` (mặc định ở `.env` local) và để lại một đơn đã hoàn trong DB dev.
- `helpers.ts` dùng `docker compose exec app php artisan tinker` để đọc id/dọn dữ liệu; đổi `E2E_BASE_URL` / `E2E_PASSWORD` nếu khác mặc định.
- Không nằm trong CI: cần app + DB dựng sẵn. `php artisan test` vẫn là cổng chính.
