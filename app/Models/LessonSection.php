<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonSection extends Model
{
    /** 9 khối của một lesson theo §8, đúng thứ tự học. */
    public const TYPES = [
        'theory' => 'Lý thuyết',
        'example' => 'Ví dụ',
        'insight' => 'Hiểu bản chất',
        'formula' => 'Công thức / Ghi nhớ',
        'common_mistake' => 'Lỗi thường gặp',
        'quiz' => 'Quiz nhanh',
        'practice' => 'Luyện tập',
        'advanced' => 'Bài nâng cao',
        'test' => 'Kiểm tra',
    ];

    /** Icon Bootstrap Icons cho từng loại section. */
    public const ICONS = [
        'theory' => 'bi-book',
        'example' => 'bi-lightbulb',
        'insight' => 'bi-eye',
        'formula' => 'bi-bookmark-star',
        'common_mistake' => 'bi-exclamation-triangle',
        'quiz' => 'bi-patch-question',
        'practice' => 'bi-pencil-square',
        'advanced' => 'bi-rocket-takeoff',
        'test' => 'bi-clipboard-check',
    ];

    protected $fillable = ['lesson_id', 'type', 'title', 'content', 'sort_order'];

    /**
     * Lọc HTML một lần duy nhất — lúc ghi. View render bằng {!! !!} nên nội dung
     * trong DB phải đã sạch; không dựa vào việc nhớ lọc ở mọi chỗ hiển thị.
     */
    protected function content(): Attribute
    {
        return Attribute::set(fn (?string $value) => app(HtmlSanitizer::class)->clean($value));
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function typeIcon(): string
    {
        return self::ICONS[$this->type] ?? 'bi-file-text';
    }
}
