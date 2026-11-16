<?php

namespace App\Services\Content;

use App\Models\LegalPage;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * Trang chính sách: admin soạn toàn văn ở Quản trị → Chính sách, để trống thì dùng bản mặc định trong code
 * (`public/legal/_{slug}-default`). Bản mặc định tự đổi theo cấu hình (email/hotline, IndexNow, Stripe…);
 * bản admin lưu là văn bản CỐ ĐỊNH — bật thêm dịch vụ bên thứ ba thì phải sửa lại ở đây (xem activeServices()).
 */
class LegalPageService
{
    public const PAGES = [
        'terms' => ['title' => 'Điều khoản sử dụng', 'route' => 'legal.terms'],
        'privacy' => ['title' => 'Chính sách bảo mật', 'route' => 'legal.privacy'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function custom(string $slug): ?LegalPage
    {
        return LegalPage::where('slug', $slug)->first();
    }

    public function defaultHtml(string $slug): string
    {
        return view("public.legal._{$slug}-default")->render();
    }

    /** Lưu bản admin soạn. Nội dung rỗng sau khi lọc (chỉ còn thẻ trống) coi như khôi phục mặc định. */
    public function update(string $slug, string $content): ?LegalPage
    {
        $page = LegalPage::firstOrNew(['slug' => $slug]);
        $page->content = $content;

        if (trim(strip_tags((string) $page->content)) === '') {
            $this->reset($slug);

            return null;
        }

        $before = $page->exists ? ['length' => mb_strlen($page->getOriginal('content'))] : null;
        // Lưu lại đúng nội dung cũ thì không đổi ngày cập nhật — ngày đó là cam kết với người đọc.
        $page->save();

        $this->audit->log('legal.updated', $page, $before, ['slug' => $slug, 'length' => mb_strlen($page->content)]);

        return $page;
    }

    public function reset(string $slug): void
    {
        if (LegalPage::where('slug', $slug)->delete()) {
            $this->audit->log('legal.reset', null, ['slug' => $slug], null);
        }
    }

    /** Ngày hiện ở đầu trang: bản admin = lần lưu cuối; bản mặc định = ngày khai trong config/site.php. */
    public function updatedLabel(string $slug, ?LegalPage $custom = null): string
    {
        $custom ??= $this->custom($slug);

        return $custom ? $custom->updated_at->format('d/m/Y') : (string) config('site.legal_updated_at');
    }

    public function lastModified(string $slug): ?Carbon
    {
        return $this->custom($slug)?->updated_at;
    }

    /**
     * Dịch vụ bên thứ ba đang bật — nhắc admin khai đủ trong Chính sách bảo mật khi dùng bản tự soạn
     * (bản mặc định tự thêm/bớt các mục này theo cấu hình).
     *
     * @return list<string>
     */
    public function activeServices(): array
    {
        return array_values(array_filter([
            config('ai.provider') !== 'fake' ? 'Nhà cung cấp mô hình AI (AI Tutor)' : null,
            config('site.google_analytics_id') || config('site.google_tag_manager_id') ? 'Google Analytics / Google Tag Manager' : null,
            config('sentry.dsn') ? 'Sentry (giám sát lỗi)' : null,
            app(IndexNowService::class)->enabled() ? 'IndexNow (báo công cụ tìm kiếm khi đăng bài)' : null,
            in_array('stripe', (array) config('payment.methods'), true) ? 'Stripe (thẻ quốc tế)' : null,
        ]));
    }
}
