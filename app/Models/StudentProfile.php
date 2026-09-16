<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentProfile extends Model
{
    /** Học lực tự đánh giá lúc đăng ký (§33) — thang phân loại theo §34. */
    public const LEVELS = [
        'average' => 'Trung bình',
        'good' => 'Khá',
        'excellent' => 'Giỏi',
    ];

    public const PERSONAS = [
        'co' => 'Cô giáo',
        'thay' => 'Thầy giáo',
    ];

    protected $fillable = [
        'user_id', 'grade_id', 'birth_date', 'address', 'school',
        'self_assessed_level', 'math_average_score', 'tutor_persona',
        'favorite_color', 'interests', 'link_code',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'math_average_score' => 'decimal:2',
            'interests' => 'array',
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

    public function levelLabel(): ?string
    {
        return self::LEVELS[$this->self_assessed_level] ?? null;
    }

    public function personaLabel(): string
    {
        return self::PERSONAS[$this->tutor_persona] ?? self::PERSONAS['co'];
    }

    /**
     * Xếp loại học lực từ điểm trung bình (§34): ≤5 Trung bình · ≤8 Khá · >8 Giỏi.
     */
    public static function classifyLevel(?float $averageScore): ?string
    {
        if ($averageScore === null) {
            return null;
        }

        return match (true) {
            $averageScore <= 5 => 'average',
            $averageScore <= 8 => 'good',
            default => 'excellent',
        };
    }

    /** Mã 8 ký tự, không gồm ký tự dễ nhầm (0/O, 1/I). */
    public static function generateLinkCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
            $code = strtr($code, ['0' => 'X', 'O' => 'Y', '1' => 'Z', 'I' => 'W', 'L' => 'V']);
        } while (static::where('link_code', $code)->exists());

        return $code;
    }
}
