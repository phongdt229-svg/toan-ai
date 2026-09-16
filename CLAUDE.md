# TOÁN AI — Hướng dẫn làm việc trong repo

Nền tảng học Toán lớp 1–12 (Laravel 12 + Blade + Bootstrap 5 + MariaDB).

## Đọc trước khi code

1. [TOAN_AI_SPEC.md](TOAN_AI_SPEC.md) — yêu cầu sản phẩm (nguồn sự thật về **làm gì**)
2. [PROJECT_PLAN.md](PROJECT_PLAN.md) — kiến trúc, schema, API, RBAC matrix, roadmap (**làm thế nào**)

Đang ở **Phase 2 (xong)**. Phase kế tiếp: Phase 3 — Question Bank & Luyện tập.

## Nguyên tắc không được phá

- Controller mỏng; business logic ở `app/Services/`.
- Mọi action ghi dữ liệu: **Form Request** + **Policy/Gate**. Không ngoại lệ.
- Không hard-code giá, quota, quyền — lấy từ DB (`packages`, `package_features`, `permissions`).
- Frontend không quyết định số tiền và không quyết định quyền truy cập nội dung.
- AI không tự publish nội dung — output vào bảng draft, người duyệt mới vào bảng thật.
- Mobile-first: viết CSS cho mobile trước, `@include media-breakpoint-up(lg)` để mở rộng.
- `Model::shouldBeStrict()` đang bật ở local → lazy loading sẽ **nổ exception**. Luôn eager load.
- Nội dung HTML do người dùng soạn phải qua `HtmlSanitizer` **lúc lưu**, không lọc lúc hiển thị.

## Lệnh hay dùng

```bash
php artisan migrate:fresh --seed   # dựng lại DB + seed roles/permissions/grades/demo users
php artisan test                   # chạy trên DB toan_ai_test (MariaDB)
npm run dev                        # Vite dev server
npm run build                      # build assets
php artisan serve                  # http://localhost:8000
```

MariaDB của XAMPP phải đang chạy. DB dev: `toan_ai`, DB test: `toan_ai_test`.

## Tài khoản demo (chỉ local, seed bởi `DemoUserSeeder`)

| Role | Email | Mật khẩu |
|---|---|---|
| Admin | `admin@toan-ai.local` | `password` |
| Giáo viên (đã duyệt) | `teacher@toan-ai.local` | `password` |
| Giáo viên (chờ duyệt) | `teacher-pending@toan-ai.local` | `password` |
| Học sinh | `student@toan-ai.local` | `password` |
| Phụ huynh | `parent@toan-ai.local` | `password` |

## Quy ước

- Route web dùng tiếng Việt không dấu: `/dang-nhap`, `/hoc-sinh`, `/quan-tri`.
- Route name theo portal: `student.*`, `teacher.*`, `parent.*`, `admin.*`.
- API luôn dưới `/api/v1/` (prefix đặt ở `bootstrap/app.php`).
- Menu từng portal khai báo ở [config/navigation.php](config/navigation.php), `route => null` = chưa làm.
- Comment trong code viết tiếng Việt, giải thích **tại sao**, không mô tả lại code.

## Trước khi commit

- [ ] `php artisan test` xanh
- [ ] Không commit `.env`, không commit key MoMo/OpenAI
- [ ] Cập nhật checklist phase tương ứng trong `PROJECT_PLAN.md`
