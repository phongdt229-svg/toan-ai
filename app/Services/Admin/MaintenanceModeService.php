<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bật / tắt chế độ bảo trì từ trang quản trị.
 *
 * Vẫn gọi đúng `artisan down`/`artisan up` chứ không tự ghi file: lệnh này còn tạo
 * `storage/framework/maintenance.php` — file mà `public/index.php` nạp TRƯỚC cả Composer,
 * nhờ vậy trang bảo trì hiện được ngay cả khi vendor/ đang bị thay giữa chừng.
 *
 * `--render` chụp sẵn view 503 thành HTML tĩnh ngay lúc bật. Bỏ tuỳ chọn này là người dùng
 * thấy trang 503 trắng mặc định của Laravel.
 */
class MaintenanceModeService
{
    /** Cookie do Laravel cấp để người bật bảo trì vẫn vào được site (hạn 12 giờ). */
    public const BYPASS_COOKIE = 'laravel_maintenance';

    public function __construct(private readonly AuditLogger $audit) {}

    public function active(): bool
    {
        return app()->isDownForMaintenance();
    }

    /**
     * Thông tin lần bật hiện tại, hoặc null khi site đang chạy bình thường.
     *
     * @return array{secret: ?string, eta: ?string, enabled_at: ?Carbon, enabled_by: ?string}|null
     */
    public function status(): ?array
    {
        if (! $this->active()) {
            return null;
        }

        $data = app()->maintenanceMode()->data();

        return [
            'secret' => $data['secret'] ?? null,
            'eta' => $data['eta'] ?? null,
            'enabled_at' => isset($data['enabled_at']) ? Carbon::parse($data['enabled_at']) : null,
            'enabled_by' => $data['enabled_by'] ?? null,
        ];
    }

    /**
     * Bật bảo trì. Trả về "mã bỏ qua" — mở `https://tên-miền/<mã>` một lần là trình duyệt đó
     * xem được site thật trong khi mọi người khác vẫn thấy trang bảo trì.
     */
    public function enable(User $admin, ?string $eta = null): string
    {
        $eta = $eta ?: config('site.maintenance_eta');
        $secret = Str::random(32);

        // Chia sẻ cho view 503 TRƯỚC khi gọi down: lệnh down render view ngay trong tiến trình này.
        View::share('maintenanceEta', $eta);

        $exit = Artisan::call('down', [
            '--render' => 'errors::503',
            '--secret' => $secret,
            '--retry' => 60,
        ]);

        if ($exit !== 0 || ! $this->active()) {
            throw new RuntimeException('Không bật được chế độ bảo trì. Kiểm tra quyền ghi thư mục storage/framework.');
        }

        // Ghi thêm dữ liệu của riêng mình vào payload để trang quản trị hiện được
        // "bật lúc nào, ai bật, dự kiến bao lâu". Đi qua driver nên chạy đúng với cả driver cache.
        $mode = app()->maintenanceMode();
        $mode->activate($mode->data() + [
            'eta' => $eta,
            'enabled_at' => now()->toIso8601String(),
            'enabled_by' => $admin->name,
        ]);

        $this->audit->log('maintenance.enabled', null, null, ['eta' => $eta, 'by' => $admin->name]);

        return $secret;
    }

    public function disable(User $admin): void
    {
        if (Artisan::call('up') !== 0) {
            throw new RuntimeException('Không tắt được chế độ bảo trì. Xoá tay file storage/framework/down trên máy chủ.');
        }

        $this->audit->log('maintenance.disabled', null, null, ['by' => $admin->name]);
    }
}
