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
APP_URL=https://hoctoanonline.com
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
MOMO_IPN_URL=https://hoctoanonline.com/api/v1/payment/momo/ipn
MOMO_RETURN_URL=https://hoctoanonline.com/payment/momo/return

# Thông báo đẩy — sinh một lần bằng `php artisan push:keys`, KHÔNG commit khoá riêng.
# Đổi khoá là mọi thiết bị đã đăng ký phải đăng ký lại.
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=

# Giám sát lỗi — để trống thì tắt. Không bật send_default_pii (dữ liệu trẻ em).
SENTRY_LARAVEL_DSN=
SENTRY_ENVIRONMENT=production

# Bắt buộc admin bật xác thực 2 bước — bật SAU KHI mọi admin đã cài xong (Quản trị → Cài đặt).
ADMIN_REQUIRE_2FA=false

# Số liệu Google Analytics trong Quản trị (tuỳ chọn). Property ID là SỐ; file khoá JSON để ngoài repo, quyền 600.
GA_PROPERTY_ID=
GA_CREDENTIALS_PATH=/etc/toan-ai/ga-service-account.json
LOOKER_STUDIO_EMBED_URL=

TRUSTED_PROXIES=          # đặt khi chạy sau Cloudflare / load balancer
BACKUP_MYSQLDUMP_PATH=/usr/bin/mysqldump
BACKUP_PATH=/var/backups/toan-ai
BACKUP_KEEP_DAYS=14
```

> Hoàn tiền dùng chung khoá MoMo ở trên (`/v2/gateway/api/refund`) — thử trên sandbox trước khi bấm ở production.
> Không commit `.env`. Key MoMo/OpenAI chỉ cấu hình trực tiếp trên server.

### 2.1. Bật VNPAY (song song MoMo)

1. Đăng ký tài khoản **sandbox** tại https://sandbox.vnpayment.vn/devreg/ — VNPAY gửi email gồm **mã website (TMN code)** và **hash secret**.
2. Điền `.env`:
   ```
   PAYMENT_METHODS=momo,vnpay          # thứ tự = thứ tự nút ở trang mua
   VNPAY_TMN_CODE=...
   VNPAY_HASH_SECRET=...
   VNPAY_PAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
   VNPAY_API_URL=https://sandbox.vnpayment.vn/merchant_webapi/api/transaction
   VNPAY_RETURN_URL="${APP_URL}/payment/vnpay/return"
   ```
3. Khai **IPN URL** `https://<domain>/api/v1/payment/vnpay/ipn` trong trang quản trị merchant của VNPAY
   (VNPAY chỉ gọi URL đã khai ở đó; local cần ngrok vì phải là HTTPS public).
4. Chạy `php artisan payments:check-vnpay` — lệnh in các URL cần khai và gọi thử API truy vấn với một mã đơn không tồn tại:
   báo "mã website và hash secret đúng" là xong; 97 = sai hash secret; 02 = sai TMN code. Không tạo giao dịch, không trừ tiền.
5. Thử một đơn thật trên sandbox bằng thẻ test NCB trong tài liệu VNPAY, xem kết quả ở Quản trị → Giao dịch.
6. Lên production: đổi `VNPAY_PAY_URL` / `VNPAY_API_URL` sang domain production VNPAY cấp, key production, chạy lại bước 4.

> Trang mua chỉ hiện nút VNPAY khi `vnpay` có trong `PAYMENT_METHODS`. Gỡ ra thì đơn VNPAY cũ vẫn đối soát / hoàn tiền được.

### 2.2. Bật Stripe (thẻ quốc tế, Apple Pay, Google Pay)

> **Điều kiện pháp lý (D-05):** Stripe không mở tài khoản cho doanh nghiệp / giấy tờ Việt Nam — tài khoản **live** cần pháp nhân
> ở nước Stripe hỗ trợ (vd công ty Mỹ qua Stripe Atlas). **Test mode** dùng được ngay sau khi tạo tài khoản để phát triển.

1. Stripe Dashboard → Developers → API keys: lấy **Secret key** (`sk_test_...` khi thử, `sk_live_...` khi chạy thật).
2. Developers → Webhooks → Add endpoint `https://<domain>/api/v1/payment/stripe/webhook`, chọn sự kiện
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`,
   `checkout.session.expired` → copy **Signing secret** (`whsec_...`).
3. `.env`:
   ```
   PAYMENT_METHODS=momo,vnpay,stripe
   STRIPE_SECRET_KEY=sk_test_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```
   Local muốn gọi Stripe test thật trong khi các cổng khác giả lập: thêm `stripe` vào `PAYMENT_LIVE_GATEWAYS`;
   nhận webhook ở local bằng Stripe CLI: `stripe listen --forward-to localhost:8000/api/v1/payment/stripe/webhook`
   (CLI in ra `whsec_...` riêng cho phiên đó).
4. `php artisan payments:check-stripe` — in URL webhook, gọi thử API đọc số dư; cảnh báo nếu dùng key live ngoài production.
5. Thử bằng thẻ test `4242 4242 4242 4242` (hạn bất kỳ trong tương lai, CVC bất kỳ), xem kết quả ở Quản trị → Giao dịch.

> Thu bằng **VND** (D-06) — Stripe coi VND là đơn vị không thập phân, giá gửi đi đúng bằng giá trong bảng `packages`.
> Bật Stripe thì Chính sách bảo mật tự khai Stripe là bên xử lý thanh toán (điều kiện theo `PAYMENT_METHODS`) — nhớ sửa `SITE_LEGAL_UPDATED_AT`.

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

> ⚠️ `public/storage` phải là **symlink** tới `storage/app/public`, không được là thư mục thật. Laravel luôn lưu ảnh
> upload (ảnh bìa tin tức, ảnh đại diện) vào `storage/app/public/...`; nếu `public/storage` là thư mục thật (vd copy
> tay lúc cài trên aaPanel) thì ảnh mới lưu xong nhưng link `/storage/...` báo 404. Kiểm tra: `ls -ld public/storage`
> phải bắt đầu bằng `l`. Sửa: gộp file cũ (`cp -an public/storage/. storage/app/public/`), `mv public/storage public/storage.bak`,
> `php artisan storage:link`. Symlink tạo trong Docker trỏ `/var/www/html/...` nên không dùng được ngoài container.

### 3.0. Tốc độ (PageSpeed)

Hai việc ở **web server** (không nằm trong code) ảnh hưởng điểm di động nhiều nhất — nginx/aaPanel không đọc `.htaccess`:

- Bật nén **gzip/brotli** cho `text/css`, `application/javascript`, `application/json`, `image/svg+xml`.
  CSS chính 361 KB còn ~55 KB khi nén; bỏ nén là mất điểm LCP ngay.
- Cache dài cho file có hash trong `/build/` (đổi nội dung là đổi tên nên an toàn):

```nginx
location /build/ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

Code đã làm: font Google tải không chặn hiển thị; KaTeX (~75 KB nén) chỉ tải khi trang có công thức; Three.js/JSXGraph/MathLive
chỉ tải khi mở hình/công thức.

### 3.1. Trang bảo trì

Có hai cách bật: **trang quản trị** (không cần SSH) và **dòng lệnh** (dùng trong script deploy).

#### Bật / tắt từ trang quản trị

Quản trị → **Bảo trì** (`/quan-tri/bao-tri`): nhập thời gian dự kiến (hiện nguyên văn cho người dùng),
tích ô xác nhận, bấm **Bật chế độ bảo trì**. Tắt bằng nút **Tắt bảo trì, mở lại site**. Cả hai đều ghi audit log
(`maintenance.enabled` / `maintenance.disabled`).

Người bật được cấp sẵn cookie bỏ qua nên **không tự khoá mình ra ngoài** — trình duyệt đó vẫn dùng site
bình thường trong 12 giờ. Trang còn hiện "link xem site thật" để gửi cho người cần kiểm tra bản mới.

Nếu mất cookie (đổi máy, quá 12 giờ): `/dang-nhap` và `/quan-tri/bao-tri` **cố ý không bị chặn**
(khai trong `bootstrap/app.php`), nên vẫn đăng nhập rồi tắt được từ xa. Danh sách không chặn còn có `/up`
để health check của load balancer không tưởng là server chết và khởi động lại nó.

#### Bật / tắt bằng dòng lệnh

`--render="errors::503"` **chụp sẵn** [resources/views/errors/503.blade.php](../resources/views/errors/503.blade.php)
thành HTML tĩnh ngay lúc chạy `down`. Nhờ vậy người dùng vẫn thấy trang bảo trì tử tế kể cả khi
`composer install` / `npm run build` / `migrate` đang chạy dở — nếu không có `--render`, Laravel trả về
trang 503 trắng mặc định. Bỏ `--render` là mất luôn ý nghĩa của bước này.

| Việc | Lệnh |
|---|---|
| Bật bảo trì kèm link xem trước | `php artisan down --render="errors::503" --secret=abc123 --retry=60` |
| Người deploy xem bản thật | mở `https://hoctoanonline.com/abc123` (đặt cookie bỏ qua bảo trì) |
| Bảo trì nhưng cho phép vài IP | `--secret` là cách đơn giản nhất; không cần sửa Nginx |
| Gỡ bảo trì | `php artisan up` |

Thời gian dự kiến hiện trên trang lấy từ `config('site.maintenance_eta')` (env `DEPLOY_ETA`, mặc định "15 phút").
**Config đang cache thì phải `php artisan config:clear` trước** mới nhận giá trị mới — `deploy.sh` đã làm sẵn:

```bash
DEPLOY_ETA="30 phút" php artisan config:clear && php artisan down --render="errors::503"
```

Bật từ trang quản trị thì không vướng chuyện này: giá trị người bật nhập được truyền thẳng vào view lúc chụp HTML.

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
| `subscriptions:remind-expiring` | 08:00 | nhắc gia hạn gói sắp hết hạn (7/3/1 ngày) |
| `assignments:remind-due` | 18:00 | nhắc học sinh bài giao hết hạn trong 24 giờ tới |
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

- `https://hoctoanonline.com/sitemap.xml` sinh động từ [SitemapController](../app/Http/Controllers/Web/SitemapController.php)
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

## 6.2. GEO — để công cụ AI tìm thấy và trích dẫn

- `/llms.txt` ([LlmsTxtService](../app/Services/Content/LlmsTxtService.php)): tóm tắt Markdown cho ChatGPT, Claude,
  Perplexity… sinh từ dữ liệu thật (hướng dẫn, giá trong DB, FAQ trang chủ, bài đã đăng). Không cần cấu hình.
- `.env`: `SITE_BRAND="TOÁN AI"` (tên chính — hiện mặc định "Math AI"), `SITE_ALTERNATE_NAMES` (tên phụ, phân tách
  dấu phẩy, mặc định `TOÁN AI,Học Toán Online`), `SITE_SOCIAL_*` link trang thật (thành `sameAs` trong JSON-LD),
  `SITE_EMAIL` email thật của miền đang chạy.
- **IndexNow** (báo Bing ngay khi đăng/sửa/xoá bài — ChatGPT Search dựa trên chỉ mục Bing): đặt
  `INDEXNOW_KEY=<chuỗi 8–128 ký tự a-z A-Z 0-9 ->`, ví dụ sinh bằng `php -r "echo bin2hex(random_bytes(16));"`.
  Kiểm tra `https://hoctoanonline.com/indexnow-key.txt` trả đúng chuỗi đó. Cần queue worker đang chạy (mục 4).
- **Cloudflare** → Security → Bots: để **tắt** "Block AI bots", "AI Labyrinth" và "Managed robots.txt" — bật là bot AI
  thật bị chặn dù trang vẫn mở bình thường bằng trình duyệt.
- Đăng ký [Bing Webmaster Tools](https://www.bing.com/webmasters) (nhập từ Search Console được) và gửi sitemap.

## 6.3. Local SEO — tìm kiếm tại Việt Nam

Sản phẩm học trực tuyến, không có cửa hàng → hồ sơ Google là **doanh nghiệp khu vực phục vụ**.

- **Google Doanh nghiệp** (business.google.com): danh mục "Dịch vụ giáo dục" / "Trung tâm dạy kèm", khu vực phục vụ
  "Việt Nam", **ẩn địa chỉ** nếu không đón khách tại chỗ. Website trỏ về trang chủ. Dán link hồ sơ vào
  `SITE_SOCIAL_GOOGLE` → thành `sameAs` + `hasMap` trong JSON-LD và link ở footer.
- **NAP thống nhất**: `SITE_COMPANY`, `SITE_HOTLINE`, `SITE_ADDRESS` (+ `SITE_ADDRESS_LOCALITY`, `SITE_ADDRESS_REGION`,
  `SITE_POSTAL_CODE`) phải viết **y hệt** hồ sơ Google, Zalo OA, Facebook. Footer, JSON-LD (`PostalAddress`,
  `ContactPoint`) và `/llms.txt` đều đọc từ [SiteContact](../app/Support/SiteContact.php) nên chỉ sửa một chỗ.
  Hồ sơ ẩn địa chỉ thì để trống cả 4 ô địa chỉ — địa chỉ trên web mà Google không có là lệch NAP.
- Search Console → Cài đặt: kiểm tra quốc gia mục tiêu không bị đặt sai (site dùng `lang="vi"`, `og:locale=vi_VN`,
  `areaServed=VN` sẵn — không cần `hreflang` vì chỉ có một ngôn ngữ).
- Kiểm tra: dán trang chủ vào [Rich Results Test](https://search.google.com/test/rich-results) — Organization phải có
  `address`/`contactPoint` đúng như đã khai.

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
