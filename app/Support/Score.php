<?php

namespace App\Support;

/**
 * Định dạng điểm kiểu Việt: bỏ số 0 thừa, dấu phẩy thập phân.
 * 7.50 → "7,5" · 8.00 → "8" · null → "—"
 *
 * Không dùng Illuminate\Support\Number vì nó cần ext `intl`, máy dev chưa bật.
 */
final class Score
{
    public static function format(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $formatted = number_format((float) $value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
