# TOÁN AI — Hướng dẫn làm việc trong repo

Nền tảng học Toán lớp 1–12 (Laravel 12 + Blade + Bootstrap 5 + MariaDB).

## Đọc trước khi code

1. [TOAN_AI_SPEC.md](TOAN_AI_SPEC.md) — yêu cầu sản phẩm (nguồn sự thật về **làm gì**)
2. [PROJECT_PLAN.md](PROJECT_PLAN.md) — kiến trúc, schema, API, RBAC matrix, roadmap (**làm thế nào**)

Roadmap **Phase 0–10 đã xong**. Triển khai production: [docs/DEPLOY.md](docs/DEPLOY.md).

## Nguyên tắc không được phá

- Controller mỏng; business logic ở `app/Services/`.
- Mọi action ghi dữ liệu: **Form Request** + **Policy/Gate**. Không ngoại lệ.
- Không hard-code giá, quota, quyền — lấy từ DB (`packages`, `package_features`, `permissions`).
- Frontend không quyết định số tiền và không quyết định quyền truy cập nội dung.
- AI không tự publish nội dung — output vào bảng draft, người duyệt mới vào bảng thật.
- Mobile-first: viết CSS cho mobile trước, `@include media-breakpoint-up(lg)` để mở rộng.
- `Model::shouldBeStrict()` đang bật ở local → lazy loading sẽ **nổ exception**. Luôn eager load.
- Nội dung HTML do người dùng soạn phải qua `HtmlSanitizer` **lúc lưu**, không lọc lúc hiển thị.
- Đáp án đúng chỉ đọc từ DB; bộ câu hỏi phát cho học sinh phải giữ phía server (session/DB), không tin id client gửi.
- Thời gian làm bài do server quyết (`expires_at`). Client chỉ hiển thị.
- Relation có `orderByPivot` đừng gọi `count()`/`max()` trực tiếp — MariaDB strict mode từ chối; query thẳng bảng pivot.
- Cột thời gian NOT NULL thứ hai trong một bảng dùng `dateTime`, không dùng `timestamp` (MariaDB 10.4).
- Module khác cần phản ứng khi học sinh làm đề/học xong bài → nghe event `ExamAttemptFinished` / `LessonCompleted`, đừng gọi thẳng từ `ExamService`/`ProgressService`.
- Relation có `withTrashed` (vd `AssignmentStudent::assignment`) — khi thống kê nhớ thêm `whereNull('deleted_at')`.
- Mailable: không đặt thuộc tính `$from`, `$to`, `$subject` — trùng thuộc tính có sẵn của Laravel.
- Không viết `?>` trong comment `//` của file PHP — PHP coi đó là thẻ đóng.
- AI: mọi lời gọi đi qua `AiProviderInterface`; test dùng `FakeProvider` (`push()`, `failNext()`, `lastRequest()`), không gọi API thật.
- AI không bao giờ quyết định đúng/sai hay tự xuất bản nội dung. Output AI hiển thị qua `AiText::toHtml` (escape).
- `.env`: `AI_PROVIDER=fake` khi dev; chạy thật cần `AI_PROVIDER=openai` + `OPENAI_API_KEY`.
- Lộ trình học (`LearningPathService`) tự cập nhật qua listener `SyncLearningPath` — đừng đánh dấu mục lộ trình bằng tay trong controller.
- Gói học: hỏi `SubscriptionService::limit()/allows()` hoặc `AccessControlService`, đừng tự query `subscriptions`.
  Thêm khoá tính năng mới → thêm vào `PackageFeature::KEYS` + `KEY_TYPES` **và** chỗ kiểm tra trong code.
- Thanh toán: chỉ `PaymentService` đổi trạng thái đơn. Không bao giờ kích hoạt gói từ return URL / query string / dữ liệu client.
  Local dùng `PAYMENT_GATEWAY=fake` → nút "Xác nhận thanh toán" ở trang giả lập; chạy MoMo sandbox cần key + URL IPN public (ngrok).
- Header bảo mật do `SecurityHeaders` gửi; CSP chưa chặn script inline — đừng thêm nguồn script ngoài (CDN) mà không cập nhật CSP.
- Sửa `public/sw.js` → tăng `VERSION` để trình duyệt bỏ cache cũ. Không cho service worker cache API, làm bài, thanh toán.
- Số liệu dashboard admin cache 10 phút (`AnalyticsService`) — test/thao tác cần số mới thì gọi `forget()`.
- Kích hoạt gói chỉ qua `SubscriptionService::activate()` (idempotent, cộng nối) — không `update(['status' => 'active'])` tay.
- Câu hỏi trong kiểm tra đầu vào là **bản chụp**; chấm qua `PlacementTestQuestion::toQuestion()` để dùng lại `GradingService`.

## Lệnh hay dùng

```bash
php artisan migrate:fresh --seed   # dựng lại DB + seed roles/permissions/grades/demo users
php artisan test                   # chạy trên DB toan_ai_test (MariaDB)
npm run dev                        # Vite dev server
npm run build                      # build assets
php artisan serve                  # http://localhost:8000
php artisan schedule:work          # chạy lịch định kỳ ở local (tự nộp bài hết giờ, hết hạn gói)
php artisan queue:work             # bắt buộc cho AI soạn bài của giáo viên, email báo cáo tuần, email thanh toán
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

Ở `APP_ENV=local`, màn hình đăng nhập liệt kê các tài khoản này (bấm để điền, bấm đúp để đăng nhập) — danh sách lấy từ
`DemoUserSeeder::ACCOUNTS`, mật khẩu từ `config('app.demo_password')` (env `DEMO_PASSWORD`). Môi trường khác không hiện.

Lớp mẫu `6A1 — Toán`, mã tham gia **`TOAN6A`**, đã có học sinh demo và 3 bài giao (bộ câu hỏi, học bài, đề).

`SampleAnalyticsSeeder` (local) thêm 12 học sinh `hs01@toan-ai.local` … `hs12@toan-ai.local` (cùng mật khẩu demo),
8 chủ đề Lớp 6 kèm câu hỏi tự sinh, lịch sử luyện tập 30 ngày và kết quả bài giao — để dashboard quản trị
và báo cáo lớp của giáo viên có số liệu. Mastery **không** ghi thẳng mà tính lại qua `MasteryService`.

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
