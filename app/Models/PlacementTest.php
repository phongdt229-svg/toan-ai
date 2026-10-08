<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class PlacementTest extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_GRADED = 'graded';

    /** Ân hạn cho độ trễ mạng — cùng giá trị với đề kiểm tra. */
    public const GRACE_SECONDS = ExamAttempt::GRACE_SECONDS;

    protected $fillable = [
        'user_id', 'grade_id', 'status', 'started_at', 'expires_at', 'submitted_at', 'auto_submitted',
        'total_questions', 'correct_count', 'score', 'level_result', 'understanding_percent',
        'avg_seconds_per_question', 'weak_topics', 'analysis',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'auto_submitted' => 'boolean',
            'score' => 'decimal:2',
            'weak_topics' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PlacementTestQuestion::class)->orderBy('sort_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PlacementTestAnswer::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isPastDeadline(): bool
    {
        return now()->greaterThan($this->expires_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    public function remainingSeconds(): int
    {
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    public function levelLabel(): ?string
    {
        return StudentProfile::LEVELS[$this->level_result] ?? null;
    }

    /** "Mức độ hiểu" §34, diễn giải cho học sinh/phụ huynh. */
    public function understandingLabel(): ?string
    {
        return match (true) {
            $this->understanding_percent === null => null,
            $this->understanding_percent < 50 => 'Cần củng cố nền tảng',
            $this->understanding_percent < 80 => 'Hiểu cơ bản',
            default => 'Hiểu sâu',
        };
    }

    /** "Tốc độ làm bài" §34. */
    public function speedLabel(): ?string
    {
        return match (true) {
            $this->avg_seconds_per_question === null => null,
            $this->avg_seconds_per_question < 45 => 'Nhanh',
            $this->avg_seconds_per_question < 120 => 'Vừa phải',
            default => 'Chậm',
        };
    }

    /** Điểm TB tự khai lệch điểm bài đầu vào từ mức này (thang 10) → nói rõ, và gợi ý làm lại nếu bài thấp hơn hẳn. */
    public const SELF_REPORT_GAP = 3;

    /**
     * Năng lực theo TỪNG chủ đề của bài (TA-05) — tính từ bản chụp câu hỏi + câu trả lời, nên bài cũ cũng có.
     * Cần nạp sẵn `questions.topic` và `answers`.
     *
     * @return Collection<int, array{topic_id: int, name: string, correct: int, total: int, percent: int}>
     */
    public function topicScores(): Collection
    {
        $answers = $this->answers->keyBy('placement_test_question_id');

        return $this->questions
            ->filter(fn ($q) => $q->topic_id)
            ->groupBy('topic_id')
            ->map(fn ($qs, $topicId) => [
                'topic_id' => (int) $topicId,
                'name' => $qs->first()->topic?->name ?? 'Chủ đề',
                'correct' => $c = $qs->filter(fn ($q) => $answers->get($q->id)?->is_correct)->count(),
                'total' => $qs->count(),
                'percent' => (int) round($c / $qs->count() * 100),
            ])
            ->sortBy('percent')
            ->values();
    }

    /**
     * Điểm TB tự khai chỉ là tín hiệu phụ (đặc tả Logic §1). null = không lệch đáng kể / không khai.
     *
     * @return array{self: float, test: float, gap: float, suggest_retake: bool}|null
     */
    public function selfReportGap(?float $selfReported): ?array
    {
        if ($selfReported === null || $this->score === null) {
            return null;
        }

        $gap = round($selfReported - (float) $this->score, 1);

        return abs($gap) >= self::SELF_REPORT_GAP
            ? ['self' => $selfReported, 'test' => (float) $this->score, 'gap' => $gap, 'suggest_retake' => $gap > 0]
            : null;
    }
}
