# Triển khai TOÁN AI lên production

Tài liệu cho người vận hành máy chủ. Môi trường tham chiếu: Ubuntu 22.04+, Nginx, PHP 8.2-FPM, MariaDB 10.4+ (hoặc MySQL 8), Redis 6+.

## 1. Yêu cầu

| Thành phần | Ghi chú |
|---|---|
| PHP 8.2+ | extension: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `curl` |
| Composer 2, Node 20+ | chỉ cần ở bước build |
| MariaDB / MySQL | `utf8mb4`, InnoDB; có `mysqldump` để sao lưu |
| Redis | cache, session, queue (client `predis` đã có trong composer) |
| Supervisor | giữ `queue:work` luôn chạy |
| HTTPS | bắt buộc: service worker (PWA) và MoMo IPN đều cần HTTPS |

## 2. Biến môi trường production (`.env`)

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://toanai.vn
APP_TIMEZONE=Asia/Ho_Chi_Minh

LOG_CHANNEL=stack
LOG_STACK=daily          # xoay vòng log theo ngày
LOG_DAILY_DAYS=14
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=toan_ai
DB_USERNAME=toan_ai
DB_PASSWORD=...

REDIS_CLIENT=predis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp          # email báo cáo tuần, email thanh toán

AI_PROVIDER=openai
OPENAI_API_KEY=...        # chỉ nằm trên server, không bao giờ gửi xuống trình duyệt

PAYMENT_GATEWAY=momo      # "fake" bị vô hiệu ở production dù có đặt nhầm
MOMO_PARTNER_CODE=...
MOMO_ACCESS_KEY=...
MOMO_SECRET_KEY=...
MOMO_ENDPOINT=https://payment.momo.vn
MOMO_IPN_URL=https://toanai.vn/api/v1/payment/momo/ipn
MOMO_RETURN_URL=https://toanai.vn/payment/momo/return

TRUSTED_PROXIES=          # đặt khi chạy sau Cloudflare / load balancer
BACKUP_MYSQLDUMP_PATH=/usr/bin/mysqldump
BACKUP_PATH=/var/backups/toan-ai
BACKUP_KEEP_DAYS=14
```

> Không commit `.env`. Key MoMo/OpenAI chỉ cấu hình trực tiếp trên server.

## 3. Các bước deploy (mỗi lần phát hành)

Dùng sẵn script [deploy/deploy.sh](../deploy/deploy.sh) — nó bật trang bảo trì, làm đủ các bước dưới đây,
và **luôn gỡ bảo trì kể cả khi có bước hỏng giữa chừng** (`trap`):

```bash
cd /var/www/toan-ai
sudo -u www-data bash deploy/deploy.sh
```

Chạy tay thì đúng thứ tự này:

```bash
cd /var/www/toan-ai
php artisan down --render="errors::503" --secret="$(openssl rand -hex 16)" --retry=60

git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force   # đồng bộ permission mới (chạy lại an toàn)
php artisan db:seed --class=PackageSeeder --force          # chỉ tạo gói còn thiếu, không ghi đè giá admin đã sửa

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan queue:restart      # worker nạp code mới sau job hiện tại
php artisan up
```

Lần đầu cài thêm: `php artisan key:generate`, `php artisan db:seed --class=GradeSeeder --force`, tạo tài khoản admin,
`php artisan storage:link`.

### 3.1. Trang bảo trì

`--render="errors::503"` **chụp sẵn** [resources/views/errors/503.blade.php](../resources/views/errors/503.blade.php)
thành HTML tĩnh ngay lúc chạy `down`. Nhờ vậy người dùng vẫn thấy trang bảo trì tử tế kể cả khi
`composer install` / `npm run build` / `migrate` đang chạy dở — nếu không có `--render`, Laravel trả về
trang 503 trắng mặc định. Bỏ `--render` là mất luôn ý nghĩa của bước này.

| Việc | Lệnh |
|---|---|
| Bật bảo trì kèm link xem trước | `php artisan down --render="errors::503" --secret=abc123 --retry=60` |
| Người deploy xem bản thật | mở `https://toanai.vn/abc123` (đặt cookie bỏ qua bảo trì) |
| Bảo trì nhưng cho phép vài IP | `--secret` là cách đơn giản nhất; không cần sửa Nginx |
| Gỡ bảo trì | `php artisan up` |

Thời gian dự kiến hiện trên trang lấy từ biến môi trường `DEPLOY_ETA` (mặc định "15 phút"):

```bash
DEPLOY_ETA="30 phút" php artisan down --render="errors::503"
```

Ràng buộc khi sửa trang này: **không** `@vite`, **không** `csrf_token()`, **không** truy vấn DB,
**không** `route()` — lúc chụp HTML, app có thể đang ở giữa quá trình build. Đường dẫn viết cứng (`/`, `/ho-tro`).
Cùng ràng buộc đó áp cho mọi trang trong `resources/views/errors/` (403, 404, 419, 429, 500, 503) — chúng phải hiện
được đúng lúc hệ thống hỏng; `tests/Feature/ErrorPagesTest.php` kiểm tra điều này.

> Lưu ý với `APP_MAINTENANCE_DRIVER=cache`: file chụp sẵn nằm trong cache chứ không ở `storage/framework/down`.
> Mặc định của repo là `file`, dùng được ngay.

## 4. Queue worker (Supervisor)

`/etc/supervisor/conf.d/toan-ai-worker.conf` — mẫu ở [deploy/supervisor/toan-ai-worker.conf](../deploy/supervisor/toan-ai-worker.conf).

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

Worker xử lý: AI soạn bài cho giáo viên, email báo cáo tuần, email thanh toán thành công.

## 5. Scheduler (cron)

```cron
* * * * * cd /var/www/toan-ai && php artisan schedule:run >> /dev/null 2>&1
```

Lịch đã khai báo trong `routes/console.php` (`php artisan schedule:list`):

| Lệnh | Tần suất | Việc |
|---|---|---|
| `exams:finalize-expired` | mỗi phút | tự nộp bài kiểm tra / đầu vào quá giờ |
| `payments:expire-pending` | 5 phút | đối soát MoMo rồi huỷ đơn quá hạn |
| `subscriptions:expire` | 00:05 | gói hết hạn → `expired`, đăng ký chờ > 24h → huỷ |
| `backup:database` | 02:00 | sao lưu DB `.sql.gz`, xoá bản cũ hơn `BACKUP_KEEP_DAYS` |
| `accounts:purge` | 03:00 | ẩn danh vĩnh viễn tài khoản đã yêu cầu xoá quá 30 ngày (cam kết trong Chính sách bảo mật) |
| `reports:weekly-parents` | CN 19:00 | email báo cáo tuần cho phụ huynh |
| `queue:prune-failed`, `sanctum:prune-expired` | tuần / ngày | dọn bảng |

Windows (XAMPP): tạo Task Scheduler chạy `php artisan schedule:run` mỗi phút, và một task "At startup" chạy `php artisan queue:work`.

## 6. Nginx + HTTPS

Mẫu: [deploy/nginx/toan-ai.conf](../deploy/nginx/toan-ai.conf). Điểm quan trọng:
- Chuyển hết HTTP → HTTPS; chứng chỉ Let's Encrypt (`certbot --nginx`).
- `/build/assets/*` cache 1 năm (tên file có hash); `/sw.js` và `/manifest.webmanifest` **không cache** để bản mới tới người dùng ngay.
- Header bảo mật (HSTS, X-Frame-Options, CSP cơ bản…) do middleware `SecurityHeaders` của app gửi — không cần lặp ở Nginx.
- MoMo gọi IPN từ internet: không chặn `POST /api/v1/payment/momo/ipn` bằng firewall/basic auth.

## 6.1. SEO & chia sẻ mạng xã hội

- `https://toanai.vn/sitemap.xml` sinh động từ [SitemapController](../app/Http/Controllers/Web/SitemapController.php)
  — chỉ gồm trang công khai (trang chủ, gói học, hướng dẫn và từng bài hướng dẫn, hỗ trợ, đăng ký, 2 trang pháp lý).
  Thêm trang công khai mới thì thêm vào đây, nếu không Google không biết tới nó.
- [public/robots.txt](../public/robots.txt) chặn các tiền tố sau đăng nhập và trỏ tới sitemap bằng **URL tuyệt đối**
  (chuẩn robots.txt không nhận đường dẫn tương đối) — **đổi tên miền thì phải sửa dòng `Sitemap:` này**.
- Thẻ Open Graph / Twitter Card nằm ở [layouts/base.blade.php](../resources/views/layouts/base.blade.php),
  mặc định lấy `@section('title')` + `@section('meta_description')` của chính trang đó.
  Trang muốn preview riêng thì khai báo `@section('og_title')` / `og_description` / `og_image` / `og_type`.
- Ảnh preview mặc định: `public/og-cover.png` (1200×630). Thay ảnh khác thì **ghi đè đúng file này**
  rồi xoá cache preview ở [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) —
  Zalo và Facebook giữ ảnh cũ khá lâu.
- Trang sau đăng nhập gắn `noindex,nofollow` (đặt ở `layouts/app.blade.php`), nên không bao giờ đưa chúng vào sitemap.

Sau khi lên production, kiểm tra nhanh: dán link trang chủ vào một cuộc trò chuyện Zalo và xem preview có đúng ảnh + tiêu đề không.

## 7. Sao lưu & khôi phục

```bash
php artisan backup:database                 # chạy tay
gunzip -c /var/backups/toan-ai/toan_ai-YYYYMMDD-HHMMSS.sql.gz | mysql -u root -p toan_ai
```

Thư mục sao lưu nằm trên cùng máy → **đồng bộ ra ngoài** (rclone/S3/NAS) hằng ngày. Thử khôi phục vào DB phụ ít nhất mỗi tháng một lần.

## 8. Checklist trước khi mở cho người dùng

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `php artisan about` không cảnh báo
- [ ] HTTPS hoạt động, `http://` tự chuyển `https://`, cookie `Secure`
- [ ] `php artisan test` xanh trên máy build
- [ ] `PAYMENT_GATEWAY=momo`, key production, **IPN URL public** và MoMo gọi được (xem log tại Quản trị → Giao dịch)
- [ ] Mua thử 1 gói giá thấp bằng tài khoản thật, kiểm tra gói kích hoạt + email
- [ ] `AI_PROVIDER=openai`, key đúng; Quản trị → AI usage có số liệu sau vài lượt hỏi
- [ ] Supervisor `RUNNING`, cron chạy (`schedule:list` có "Next Due" hợp lý)
- [ ] Bản sao lưu đầu tiên được tạo và đã thử khôi phục
- [ ] Đổi mật khẩu các tài khoản demo hoặc không chạy `DemoUserSeeder` ở production
- [ ] Giá các gói trong Quản trị → Gói học đã đúng giá bán thật
