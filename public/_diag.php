<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/*
 * Trang chẩn đoán lỗi 500 ở production — cố ý KHÔNG đi qua Laravel.
 *
 * Tại sao file riêng: khi Laravel hỏng lúc khởi động (provider thiếu class, cache config sai đường dẫn,
 * mất kết nối DB...) thì mọi route đều 500, kể cả trang quản trị, nên công cụ gỡ lỗi phải đứng ngoài.
 *
 * An toàn:
 * - Tắt hẳn (404) khi .env không có DIAG_TOKEN hoặc token ngắn hơn 32 ký tự.
 * - So token bằng hash_equals; không bao giờ in mật khẩu/khoá ra màn hình.
 * - Xong việc thì XOÁ DIAG_TOKEN khỏi .env (hoặc đổi token) — mỗi lần dùng là một cửa đang mở.
 *
 * Dùng: https://<domain>/_diag.php?token=XXX            (chẩn đoán + thử chạy trang chủ)
 *       https://<domain>/_diag.php?token=XXX&path=/dang-nhap&lines=200
 *       https://<domain>/_diag.php?token=XXX&debug=1    (trình duyệt này thấy lỗi chi tiết 2 giờ; &debug=0 tắt)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$base = dirname(__DIR__);

/** Đọc .env tối giản — không dùng được Dotenv vì có thể chính vendor đang hỏng. */
function diag_env(string $file): array
{
    $vars = [];
    if (! is_readable($file)) {
        return $vars;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $vars[$key] = $value;
    }

    return $vars;
}

$env = diag_env($base.'/.env');
$expected = (string) ($env['DIAG_TOKEN'] ?? '');
$given = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_DIAG_TOKEN'] ?? ''));

if (strlen($expected) < 32 || ! hash_equals($expected, $given)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

// &debug=1 → chỉ trình duyệt này thấy trang lỗi chi tiết của Laravel (App\Support\DiagnosticDebug), 2 giờ.
// &debug=0 → tắt. Khách khác vẫn thấy trang 500 bình thường dù APP_DEBUG=false.
$debugNote = '';
if (isset($_GET['debug'])) {
    $on = $_GET['debug'] === '1';
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    setcookie('diag_debug', $on ? $expected : '', [
        'expires' => $on ? time() + 7200 : time() - 3600,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    $debugNote = $on
        ? "DEBUG RIÊNG: ĐÃ BẬT cho trình duyệt này trong 2 giờ — mở trang lỗi để xem chi tiết. Tắt: &debug=0\n"
        : "DEBUG RIÊNG: đã tắt.\n";
}

/** Che giá trị nhạy cảm có thể nằm trong log (mật khẩu, token, khoá). */
function diag_redact(string $text): string
{
    return preg_replace(
        '/((?:password|passwd|secret|token|api[_-]?key|authorization|dsn)["\']?\s*[:=]>?\s*["\']?)[^"\'\s,}&]+/i',
        '$1***',
        $text
    );
}

function diag_section(string $title): void
{
    echo "\n==== {$title} ".str_repeat('=', max(0, 60 - strlen($title)))."\n";
}

function diag_line(bool $ok, string $text): void
{
    echo ($ok ? '[ OK ] ' : '[LỖI] ').$text."\n";
}

date_default_timezone_set('Asia/Ho_Chi_Minh');
echo 'Chẩn đoán lúc '.date('Y-m-d H:i:s T')."\n".$debugNote;
if (is_file($base.'/bootstrap/cache/config.php')) {
    echo "Lưu ý: đang dùng config:cache — sửa .env (APP_DEBUG, DIAG_TOKEN...) phải chạy php artisan config:clear mới ăn.\n";
}

// ---- 1. PHP --------------------------------------------------------------------------------------
diag_section('PHP');
diag_line(version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP '.PHP_VERSION.' (Laravel 12 cần >= 8.2) · SAPI '.PHP_SAPI);
foreach (['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'bcmath', 'gd', 'curl'] as $ext) {
    diag_line(extension_loaded($ext), "extension {$ext}");
}
echo 'memory_limit='.ini_get('memory_limit').' · opcache='.(function_exists('opcache_get_status') && @opcache_get_status(false) ? 'bật' : 'tắt')."\n";

// ---- 2. File & thư mục ---------------------------------------------------------------------------
diag_section('File & quyền ghi');
diag_line(is_readable($base.'/.env'), '.env đọc được');
diag_line(is_file($base.'/vendor/autoload.php'), 'vendor/autoload.php (đã composer install)');
diag_line(is_file(__DIR__.'/build/manifest.json'), 'public/build/manifest.json (đã npm run build)');
foreach (['storage/logs', 'storage/framework/views', 'storage/framework/cache', 'storage/framework/sessions', 'bootstrap/cache'] as $dir) {
    diag_line(is_dir($base.'/'.$dir) && is_writable($base.'/'.$dir), "ghi được {$dir}");
}
if (is_file($base.'/storage/framework/maintenance.php')) {
    diag_line(false, 'Đang ở chế độ bảo trì (storage/framework/maintenance.php) → chạy php artisan up');
}
echo 'APP_ENV='.($env['APP_ENV'] ?? '?').' · APP_DEBUG='.($env['APP_DEBUG'] ?? '?')
    .' · APP_KEY '.(! empty($env['APP_KEY']) ? 'có' : 'TRỐNG').' · SESSION_DRIVER='.($env['SESSION_DRIVER'] ?? '?')
    .' · CACHE_STORE='.($env['CACHE_STORE'] ?? '?')."\n";
if (empty($env['APP_KEY'])) {
    diag_line(false, 'APP_KEY trống → php artisan key:generate');
}

// ---- 3. Cache khởi động (bootstrap/cache) ---------------------------------------------------------
diag_section('bootstrap/cache');
$autoload = is_file($base.'/vendor/autoload.php');
if ($autoload) {
    require_once $base.'/vendor/autoload.php';
}
foreach (glob($base.'/bootstrap/cache/*.php') ?: [] as $file) {
    $name = basename($file);
    echo "- {$name} (".date('Y-m-d H:i', filemtime($file)).")\n";
    $source = (string) file_get_contents($file);

    // Cache chép từ máy Windows sang chứa đường dẫn C:\... → Laravel đọc view/storage sai chỗ.
    if (preg_match('/[A-Z]:\\\\\\\\(?:xampp|Users)/i', $source)) {
        diag_line(false, "  {$name} chứa đường dẫn Windows (cache từ máy local) → xoá file này rồi php artisan optimize");
    }

    // packages.php/services.php liệt kê provider: một provider của gói dev (Pail, Sail, Collision)
    // mà server cài --no-dev là mọi request đều chết.
    if ($autoload && in_array($name, ['packages.php', 'services.php'], true)) {
        preg_match_all("/'([A-Za-z0-9_\\\\]+\\\\[A-Za-z0-9_]*ServiceProvider)'/", $source, $m);
        $missing = [];
        foreach (array_unique($m[1]) as $class) {
            $class = str_replace('\\\\', '\\', $class);
            if (! class_exists($class)) {
                $missing[] = $class;
            }
        }
        diag_line($missing === [], $missing === []
            ? "  {$name}: mọi provider đều có trong vendor"
            : "  {$name}: THIẾU class ".implode(', ', $missing).' → rm bootstrap/cache/packages.php bootstrap/cache/services.php');
    }
}

// ---- 4. Database ---------------------------------------------------------------------------------
diag_section('Database');
$pdo = null;
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $env['DB_HOST'] ?? '127.0.0.1',
        $env['DB_PORT'] ?? '3306',
        $env['DB_DATABASE'] ?? ''
    );
    $pdo = new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    diag_line(true, 'Kết nối '.($env['DB_DATABASE'] ?? '?').'@'.($env['DB_HOST'] ?? '?').' · '.$pdo->query('SELECT VERSION()')->fetchColumn());
} catch (Throwable $e) {
    diag_line(false, 'Không kết nối được DB: '.diag_redact($e->getMessage()));
}

if ($pdo) {
    try {
        $ran = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = array_map(fn ($f) => basename($f, '.php'), glob($base.'/database/migrations/*.php') ?: []);
        $pending = array_values(array_diff($files, $ran));
        diag_line($pending === [], $pending === []
            ? 'Không còn migration chưa chạy'
            : count($pending).' migration CHƯA chạy → php artisan migrate --force (hoặc file deploy/sql):'."\n       ".implode("\n       ", $pending));
    } catch (Throwable $e) {
        diag_line(false, 'Đọc bảng migrations lỗi: '.diag_redact($e->getMessage()));
    }
    foreach (['users', 'sessions', 'cache', 'jobs', 'legal_pages', 'packages'] as $table) {
        $exists = (bool) $pdo->query('SHOW TABLES LIKE '.$pdo->quote($table))->fetchColumn();
        diag_line($exists, "bảng {$table}");
    }
}

// ---- 5. Log Laravel ------------------------------------------------------------------------------
diag_section('Log mới nhất');
$lines = max(20, min(400, (int) ($_GET['lines'] ?? 80)));
$logs = glob($base.'/storage/logs/*.log') ?: [];
usort($logs, fn ($a, $b) => filemtime($b) <=> filemtime($a));
if ($logs === []) {
    echo "(chưa có file log — có thể storage/logs không ghi được)\n";
} else {
    $log = $logs[0];
    echo basename($log).' ('.date('Y-m-d H:i:s', filemtime($log)).', '.round(filesize($log) / 1024)." KB) — {$lines} dòng cuối:\n\n";
    // Đọc từ cuối file: log production có thể vài trăm MB, không nạp cả file vào bộ nhớ.
    $fh = fopen($log, 'rb');
    fseek($fh, 0, SEEK_END);
    $size = ftell($fh);
    $chunk = min($size, 256 * 1024);
    fseek($fh, -$chunk, SEEK_END);
    $tail = array_slice(explode("\n", (string) fread($fh, $chunk)), -$lines);
    fclose($fh);
    echo diag_redact(implode("\n", $tail))."\n";
}

// ---- 6. Chạy thử Laravel -------------------------------------------------------------------------
// Để cuối: nếu Laravel chết bằng fatal error thì các phần trên đã in ra rồi.
diag_section('Chạy thử Laravel');
$path = (string) ($_GET['path'] ?? '/');
if ($path === '' || $path[0] !== '/') {
    $path = '/';
}
echo "GET {$path}\n";

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo "\n[LỖI] FATAL: ".diag_redact($error['message'])."\n       tại {$error['file']}:{$error['line']}\n";
    }
});

if (! $autoload) {
    diag_line(false, 'Bỏ qua: chưa có vendor/autoload.php');
    exit;
}

/** In exception kèm chuỗi getPrevious() và vài khung trace đầu. */
function diag_exception(Throwable $e): void
{
    do {
        echo "\n".get_class($e).': '.diag_redact($e->getMessage())."\n  tại ".$e->getFile().':'.$e->getLine()."\n";
        foreach (array_slice($e->getTrace(), 0, 12) as $i => $frame) {
            echo sprintf("  #%d %s%s%s() %s\n", $i, $frame['class'] ?? '', $frame['type'] ?? '', $frame['function'] ?? '',
                isset($frame['file']) ? $frame['file'].':'.($frame['line'] ?? '?') : '');
        }
    } while ($e = $e->getPrevious());
}

// Chỉ dọn bộ đệm do mình mở: PHP có thể bật sẵn output_buffering, dọn tới 0 là mất cả báo cáo ở trên.
$obLevel = ob_get_level();
$obClean = function () use ($obLevel) {
    while (ob_get_level() > $obLevel) {
        ob_end_clean();
    }
};

try {
    ob_start();
    $app = require $base.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);

    // Khởi động riêng trước: lỗi lúc nạp provider/config ném thẳng ra đây. Nếu để handle() làm thì
    // Laravel cố vẽ trang 500 trên một app nửa vời, hỏng tiếp ("Target class [view]") và che lỗi gốc.
    try {
        $kernel->bootstrap();
    } catch (Throwable $e) {
        $obClean();
        diag_line(false, 'Laravel hỏng ngay lúc khởi động (provider / config / .env):');
        diag_exception($e);
        exit;
    }

    $request = Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $obClean();

    $status = $response->getStatusCode();
    diag_line($status < 500, "HTTP {$status}");

    // Handler của Laravel đã đổi exception thành trang 500 — lấy lại exception gốc để xem nguyên nhân.
    $exception = $response->exception ?? null;
    if ($status >= 500 && $exception instanceof Throwable) {
        diag_exception($exception);
    }
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    $obClean();
    diag_line(false, 'Laravel lỗi khi xử lý request:');
    diag_exception($e);
}
