<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BlogPost extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Bản nháp',
        self::STATUS_PUBLISHED => 'Đã xuất bản',
    ];

    protected $fillable = [
        'blog_category_id', 'title', 'slug', 'excerpt', 'content',
        'cover_path', 'status', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Lọc HTML một lần duy nhất — lúc ghi (đúng luật chung, xem LessonSection).
     * View render bằng {!! !!} nên nội dung trong DB phải đã sạch sẵn.
     */
    protected function content(): Attribute
    {
        return Attribute::set(fn (?string $value) => app(HtmlSanitizer::class)->clean($value));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
