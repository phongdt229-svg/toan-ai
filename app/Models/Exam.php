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

class Exam extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /** @var array<string, string> */
    public const TYPES = [
        'practice' => 'Ôn tập',
        'quiz' => 'Kiểm tra nhanh',
        'test' => 'Kiểm tra',
    ];

    /** @var array<string, string> */
    public const DIFFICULTIES = [
        'easy' => 'Dễ',
        'medium' => 'Trung bình',
        'hard' => 'Khó',
        'mixed' => 'Hỗn hợp',
    ];

    protected $fillable = [
        'title', 'slug', 'description', 'grade_id', 'type', 'duration_minutes',
        'total_questions', 'total_points', 'difficulty', 'access_level', 'max_attempts',
        'shuffle_questions', 'shuffle_options', 'show_answers_after_submit',
        'available_from', 'available_to', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'total_questions' => 'integer',
            'total_points' => 'decimal:2',
            'max_attempts' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'show_answers_after_submit' => 'boolean',
            'available_from' => 'datetime',
            'available_to' => 'datetime',
        ];
    }

    protected function description(): Attribute
    {
        return Attribute::set(fn (?string $v) => app(HtmlSanitizer::class)->clean($v));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('sort_order', 'points')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /** Đề đang trong khung giờ mở. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('available_to')->orWhere('available_to', '>=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isOpen(): bool
    {
        return ($this->available_from === null || $this->available_from->isPast())
            && ($this->available_to === null || $this->available_to->isFuture());
    }

    /**
     * Đáp án chỉ được lộ khi giáo viên cho phép VÀ đề đã đóng (nếu có hạn đóng) —
     * tránh học sinh làm trước chuyền đáp án cho người làm sau.
     */
    public function answersRevealable(): bool
    {
        return $this->show_answers_after_submit
            && ($this->available_to === null || $this->available_to->isPast());
    }

    /** Đề đã có người làm thì không được đổi bộ câu hỏi, nếu không điểm cũ mất ý nghĩa. */
    public function hasAttempts(): bool
    {
        return $this->attempts()->exists();
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
