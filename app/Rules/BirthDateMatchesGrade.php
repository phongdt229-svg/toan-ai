<?php

namespace App\Rules;

use App\Models\Grade;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ngày sinh phải hợp lý với lớp đã chọn — chặn "sinh 2000 mà học lớp 5" (tuổi 26).
 *
 * Tuổi chuẩn của lớp L là L + 5 (lớp 1 = 6 tuổi). Cho nhích rộng để không chặn oan học sinh học vượt
 * (sớm tối đa 2 năm) hoặc học muộn/lưu ban (muộn tối đa 3 năm): tuổi hợp lệ từ L+3 đến L+8.
 */
class BirthDateMatchesGrade implements DataAwareRule, ValidationRule
{
    public const EARLY_YEARS = 2;

    public const LATE_YEARS = 3;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /** @return array{0: int, 1: int} [tuổi nhỏ nhất, tuổi lớn nhất] cho lớp `level`. */
    public static function ageRange(int $level): array
    {
        return [$level + 5 - self::EARLY_YEARS, $level + 5 + self::LATE_YEARS];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || blank($this->data['grade_id'] ?? null)) {
            return; // thiếu một trong hai thì để các quy tắc khác báo lỗi
        }

        $level = Grade::whereKey($this->data['grade_id'])->value('level');
        if ($level === null) {
            return;
        }

        try {
            $age = Carbon::parse($value)->age;
        } catch (\Throwable) {
            return; // 'date' đã báo lỗi định dạng
        }

        [$min, $max] = self::ageRange((int) $level);
        if ($age < $min || $age > $max) {
            $fail("Ngày sinh không phù hợp với lớp {$level} (thường từ {$min} đến {$max} tuổi). Vui lòng kiểm tra lại ngày sinh hoặc chọn lại lớp.");
        }
    }
}
