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
- Mã giảm giá: số tiền giảm chỉ tính ở `VoucherService`, client chỉ gửi chuỗi mã (session giữ MÃ, không giữ SỐ TIỀN).
  Lượt dùng đếm từ bảng `voucher_redemptions` (giữ chỗ lúc tạo đơn → `redeemed_at` khi trả xong → `released_at` khi đơn hỏng),
  không dùng cột đếm sẵn. Mã giảm 100% không đi qua cổng — `PaymentService::settleFreeOrder()`.
- Header bảo mật do `SecurityHeaders` gửi; CSP chưa chặn script inline — đừng thêm nguồn script ngoài (CDN) mà không cập nhật CSP.
- Sửa `public/sw.js` → tăng `VERSION` để trình duyệt bỏ cache cũ. Không cho service worker cache API, làm bài, thanh toán.
- Số liệu dashboard admin cache 10 phút (`AnalyticsService`) — test/thao tác cần số mới thì gọi `forget()`.
- Kích hoạt gói chỉ qua `SubscriptionService::activate()` (idempotent, cộng nối) — không `update(['status' => 'active'])` tay.
- Form công khai (hỗ trợ, báo lỗi nội dung) phải có `MathCaptcha` + honeypot + throttle — xem `SupportTicketRequest`.
- Nội dung Trung tâm hướng dẫn nằm ở [config/guides.php](config/guides.php) (không có bảng DB). Thêm bài = thêm một phần tử;
  `links` chỉ ghi **tên route** (`'packages.index'`, hoặc `'guides.show:slug'` để trỏ bài khác) vì config nạp trước route.
  `GuideTest` mở tất cả các bài nên gõ sai tên route sẽ bị test bắt ngay.
- Quên mật khẩu: mọi logic ở `PasswordResetService`; giữ nguyên tắc "thông báo giống nhau dù email có tồn tại hay không".
  Local `MAIL_MAILER=log` → link đặt lại nằm trong `storage/logs/laravel.log`.
- Câu hỏi trong kiểm tra đầu vào là **bản chụp**; chấm qua `PlacementTestQuestion::toQuestion()` để dùng lại `GradingService`.
- Xác thực email: `User` implement `MustVerifyEmail`, mail gửi qua `VerifyEmailLink` (link ký, hạn 60 phút).
  Chưa xác thực **vẫn học được**, chỉ chặn mua gói (middleware `verified` ở `packages.checkout`/`packages.pay`).
  Thêm middleware `verified` vào chỗ khác phải cân nhắc: khoá quá tay là người dùng mới không dùng được gì.
- Xoá tài khoản: chỉ qua `AccountDeletionService` (soft delete → 30 ngày → `anonymise()`), không `User::delete()` tay.
  Thêm bảng mới chứa dữ liệu cá nhân thì **phải bổ sung vào `anonymise()`**, nếu không là vi phạm Chính sách bảo mật.
  Lệnh dọn: `accounts:purge` (đã đặt lịch 03:00 hằng ngày).
- Trang lỗi + trang bảo trì (`resources/views/errors/`) không được dùng `@vite`, `csrf_token()`, DB hay `route()` —
  chúng phải hiện được đúng lúc hệ thống hỏng hoặc đang deploy. `ErrorPagesTest` canh điều này.
- Bảo trì bật/tắt ở Quản trị → Bảo trì (`MaintenanceModeService`, vẫn gọi `artisan down/up` chứ không tự ghi file:
  lệnh này còn tạo `storage/framework/maintenance.php` mà `public/index.php` nạp trước cả Composer).
  `bootstrap/app.php` cố ý **không chặn** `up`, `dang-nhap`, `quan-tri/bao-tri` — đường cứu hộ khi admin mất
  cookie bỏ qua. Thêm route vào danh sách này phải cân nhắc: mỗi mục là một cửa còn mở lúc site đang đóng.
- Thêm trang công khai mới → thêm vào `SitemapController`; trang sau đăng nhập thì thôi (đã `noindex` ở `layouts/app`).
  Thẻ OG/Twitter đặt sẵn ở `layouts/base`, trang nào cần preview riêng thì khai `@section('og_title'/'og_description'/'og_image')`.
- CI chạy `php artisan test` trên mỗi push/PR vào `main` ([.github/workflows/ci.yml](.github/workflows/ci.yml)).
  Chưa bật job Pint vì code cũ còn 66 file lệch chuẩn — dọn một lượt rồi mới thêm.

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
| Admin | `admin@gmail.com` | `password` |
| Giáo viên (đã duyệt) | `teacher@gmail.com` | `password` |
| Giáo viên (chờ duyệt) | `teacher-pending@gmail.com` | `password` |
| Học sinh | `student@gmail.com` | `password` |
| Phụ huynh | `parent@gmail.com` | `password` |

Ở `APP_ENV=local`, màn hình đăng nhập liệt kê các tài khoản này (bấm để điền, bấm đúp để đăng nhập) — danh sách lấy từ
`DemoUserSeeder::ACCOUNTS`, mật khẩu từ `config('app.demo_password')` (env `DEMO_PASSWORD`). Môi trường khác không hiện.

Lớp mẫu `6A1 — Toán`, mã tham gia **`TOAN6A`**, đã có học sinh demo và 3 bài giao (bộ câu hỏi, học bài, đề).

`SampleAnalyticsSeeder` (local) thêm 22 học sinh `hs01@gmail.com` … `hs22@gmail.com` (cùng mật khẩu demo),
8 chủ đề Lớp 6 kèm câu hỏi tự sinh, lịch sử luyện tập 30 ngày và kết quả bài giao — để dashboard quản trị
và báo cáo lớp của giáo viên có số liệu. Mastery **không** ghi thẳng mà tính lại qua `MasteryService`.

`SampleTeachersSeeder` (local) thêm 12 giáo viên `gv01@gmail.com` … `gv12@gmail.com` (cùng mật khẩu demo),
đã duyệt, có hồ sơ trường/môn đầy đủ — để danh sách giáo viên ở trang quản trị có số liệu thật.

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
