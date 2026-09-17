<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_SINGLE_CHOICE = 'single_choice';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_FILL_BLANK = 'fill_blank';
    public const TYPE_SHORT_ANSWER = 'short_answer';
    public const TYPE_ESSAY = 'essay';

    /** @var array<string, string> */
    public const TYPES = [
        self::TYPE_MULTIPLE_CHOICE => 'Nhiều đáp án đúng',
        self::TYPE_SINGLE_CHOICE => 'Một đáp án đúng',
        self::TYPE_TRUE_FALSE => 'Đúng / Sai',
        self::TYPE_FILL_BLANK => 'Điền vào chỗ trống',
        self::TYPE_SHORT_ANSWER => 'Trả lời ngắn',
        self::TYPE_ESSAY => 'Tự luận',
    ];

    /** Các loại dùng bảng question_options. */
    public const CHOICE_TYPES = [
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_SINGLE_CHOICE,
    ];

    /** @var array<string, string> */
    public const DIFFICULTIES = [
        'easy' => 'Dễ',
        'medium' => 'Trung bình',
        'hard' => 'Khó',
    ];

    protected $fillable = [
        'grade_id', 'topic_id', 'lesson_id', 'type', 'content', 'explanation',
        'difficulty', 'correct_answer', 'points', 'status', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'correct_answer' => 'array',
            'points' => 'decimal:2',
        ];
    }

    /** Nội dung câu hỏi cũng hiển thị bằng {!! !!} nên phải lọc lúc lưu. */
    protected function content(): Attribute
    {
        return Attribute::set(fn (?string $v) => app(HtmlSanitizer::class)->clean($v));
    }

    protected function explanation(): Attribute
    {
        return Attribute::set(fn (?string $v) => app(HtmlSanitizer::class)->clean($v));
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'question_tags');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function usesOptions(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }

    /** Tự luận phải người chấm, không chấm tự động được. */
    public function needsManualGrading(): bool
    {
        return $this->type === self::TYPE_ESSAY;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function difficultyLabel(): string
    {
        return self::DIFFICULTIES[$this->difficulty] ?? $this->difficulty;
    }
}
