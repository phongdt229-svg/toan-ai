<?php

namespace App\Services\Content;

use App\Models\LegalPage;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * CMS trang chính sách (Quản trị → Chính sách). Admin tạo / sửa trang; SLUG CỐ ĐỊNH từ lúc tạo — sinh từ tiêu đề,
 * không có ô sửa: link đã đăng ký với Bộ Công Thương hay đã chia sẻ không được gãy.
 *
 * Hai trang hệ thống (Điều khoản, Bảo mật) không xoá / tắt được vì đăng ký, cookie, footer trỏ tới chúng. Nội dung
 * NULL = bản mặc định trong code (`public/legal/_{terms,privacy}-default`), tự đổi theo cấu hình (email, IndexNow, Stripe…);
 * bản admin lưu là văn bản CỐ ĐỊNH — bật thêm dịch vụ bên thứ ba thì phải sửa lại (xem activeServices()).
 */
class LegalPageService
{
    /** slug trang hệ thống → partial bản mặc định. */
    public const SYSTEM_DEFAULTS = [
        'dieu-khoan-su-dung' => 'public.legal._terms-default',
        'chinh-sach-bao-mat' => 'public.legal._privacy-default',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function findActive(string $slug): ?LegalPage
    {
        return LegalPage::active()->where('slug', $slug)->first();
    }

    /** Trang đang bật, theo thứ tự — cho footer và mục "Các chính sách khác". Gọi nhiều lần trong một request chỉ tốn một truy vấn. */
    public function footerPages(): Collection
    {
        return once(fn () => LegalPage::active()->ordered()->get(['id', 'slug', 'title', 'sort_order']));
    }

    public function hasDefault(LegalPage $page): bool
    {
        return $page->is_system && isset(self::SYSTEM_DEFAULTS[$page->slug]);
    }

    /** Trang hệ thống chưa soạn → nội dung lấy từ bản mặc định trong code. */
    public function usesDefault(LegalPage $page): bool
    {
        return $this->hasDefault($page) && $page->content === null;
    }

    public function defaultView(LegalPage $page): string
    {
        return self::SYSTEM_DEFAULTS[$page->slug];
    }

    /** HTML để nạp vào ô soạn thảo: bản đã lưu, hoặc bản mặc định đang hiện trên web để admin sửa tiếp. */
    public function editableContent(LegalPage $page): string
    {
        return $this->usesDefault($page) ? view($this->defaultView($page))->render() : (string) $page->content;
    }

    /** @param  array{title: string, meta_description?: ?string, content: string, is_active?: bool, sort_order?: int}  $data */
    public function create(array $data): LegalPage
    {
        $page = new LegalPage($data);
        $page->slug = $this->uniqueSlug($data['title']);
        $page->save();

        $this->audit->log('legal.created', $page, null, ['slug' => $page->slug, 'title' => $page->title]);

        return $page;
    }

    /**
     * Sửa tiêu đề, mô tả, nội dung, hiển thị, thứ tự — KHÔNG BAO GIỜ đổi slug (không có trong $fillable).
     * Trang hệ thống: luôn bật; nội dung trống sau khi lọc = quay về bản mặc định.
     */
    public function update(LegalPage $page, array $data): LegalPage
    {
        $before = $page->only(['title', 'is_active', 'sort_order']);
        $page->fill($data);

        if ($page->is_system) {
            $page->is_active = true;
        }

        if ($this->hasDefault($page) && trim(strip_tags((string) $page->content)) === '') {
            $page->content = null;
        }

        // Lưu lại đúng nội dung cũ thì không đổi ngày cập nhật — ngày đó là cam kết với người đọc.
        $page->save();

        $this->audit->log('legal.updated', $page, $before, $page->only(['title', 'is_active', 'sort_order']) + ['slug' => $page->slug]);

        return $page;
    }

    public function resetToDefault(LegalPage $page): void
    {
        if ($this->hasDefault($page) && $page->content !== null) {
            $page->content = null;
            $page->save();
            $this->audit->log('legal.reset', $page, null, ['slug' => $page->slug]);
        }
    }

    public function delete(LegalPage $page): void
    {
        abort_if($page->is_system, 403, 'Không xoá được trang hệ thống.');

        $snapshot = $page->only(['slug', 'title']);
        $page->delete();

        $this->audit->log('legal.deleted', null, $snapshot, null);
    }

    /** Ngày hiện ở đầu trang: bản mặc định = ngày khai trong config/site.php; còn lại = lần lưu cuối. */
    public function updatedLabel(LegalPage $page): string
    {
        return $this->usesDefault($page) ? (string) config('site.legal_updated_at') : $page->updated_at->format('d/m/Y');
    }

    /** Cho sitemap: bản mặc định theo thời gian sửa file view, còn lại theo lần lưu cuối. */
    public function lastModified(LegalPage $page): ?string
    {
        if ($this->usesDefault($page)) {
            $file = view($this->defaultView($page))->getPath();

            return is_file($file) ? Carbon::createFromTimestamp(filemtime($file))->toAtomString() : null;
        }

        return $page->updated_at?->toAtomString();
    }

    /** Slug xem trước cho form tạo trang. */
    public function previewSlug(string $title): string
    {
        return $this->uniqueSlug($title);
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

    /** Slug không dấu từ tiêu đề, không trùng trang khác và không đè lên route có sẵn (/goi-hoc, /dang-nhap…). */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'trang';
        $slug = $base;

        for ($i = 2; LegalPage::where('slug', $slug)->exists() || $this->isTakenByRoute($slug); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function isTakenByRoute(string $slug): bool
    {
        try {
            return ! app('router')->getRoutes()->match(Request::create('/'.$slug))->isFallback;
        } catch (MethodNotAllowedHttpException) {
            // Có route khác phương thức (vd chỉ POST) — vẫn coi là đã có người dùng đường dẫn này.
            return true;
        } catch (NotFoundHttpException) {
            return false;
        }
    }
}
