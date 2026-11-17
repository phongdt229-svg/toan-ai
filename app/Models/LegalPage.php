<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Trang chính sách (CMS) — xem LegalPageService. `slug` không có trong $fillable: chỉ service gán lúc TẠO,
 * form sửa không đổi được (link đã đăng ký Bộ Công Thương / đã chia sẻ không được gãy).
 */
class LegalPage extends Model
{
    protected $fillable = ['title', 'meta_description', 'content', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Lọc lúc lưu như Lesson/Blog; riêng trang này cho thêm mailto:/tel: vì mục Liên hệ cần. */
    protected function content(): Attribute
    {
        return Attribute::set(fn (?string $value) => (new HtmlSanitizer(['mailto', 'tel']))->clean($value));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function url(): string
    {
        return url('/'.$this->slug);
    }
}
