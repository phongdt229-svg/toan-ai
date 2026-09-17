<?php

namespace App\Services\Learning;

/**
 * Kết quả chấm một câu. `isCorrect === null` nghĩa là chờ người chấm (tự luận).
 */
final readonly class GradingResult
{
    public function __construct(
        public ?bool $isCorrect,
        public float $score,
        public float $maxScore,
        public bool $needsManualGrading = false,
    ) {}

    public static function correct(float $points): self
    {
        return new self(true, $points, $points);
    }

    public static function wrong(float $points): self
    {
        return new self(false, 0.0, $points);
    }

    /** Điểm từng phần — dùng cho câu nhiều đáp án và điền nhiều chỗ trống. */
    public static function partial(float $score, float $points): self
    {
        return new self($score >= $points, round($score, 2), $points);
    }

    public static function pending(float $points): self
    {
        return new self(null, 0.0, $points, needsManualGrading: true);
    }
}
