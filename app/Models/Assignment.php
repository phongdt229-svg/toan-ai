<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

    public const TYPE_QUESTION_SET = 'question_set';

    public const TYPE_EXAM = 'exam';

    public const TYPE_LESSON = 'lesson';

    /** @var array<string, string> */
    public const TYPES = [
        self::TYPE_QUESTION_SET => 'Bộ câu hỏi',
        self::TYPE_EXAM => 'Đề kiểm tra',
        self::TYPE_LESSON => 'Học bài',
    ];

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'class_id', 'teacher_id', 'title', 'description', 'type', 'exam_id', 'lesson_id',
        'assign_to_all', 'due_at', 'allow_retry', 'max_attempts', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'assign_to_all' => 'boolean',
            'allow_retry' => 'boolean',
            'max_attempts' => 'integer',
            'due_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected function description(): Attribute
    {
        return Attribute::set(fn (?string $v) => app(HtmlSanitizer::class)->clean($v));
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class)->withTrashed();
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class)->withTrashed();
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'assignment_questions')
            ->withPivot('sort_order', 'points')
            ->orderByPivot('sort_order');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AssignmentStudent::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    /** Số lượt tối đa thực tế: không cho làm lại thì luôn là 1. */
    public function effectiveMaxAttempts(): int
    {
        return $this->allow_retry ? max(1, $this->max_attempts) : 1;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
