<?php

namespace App\Support;

/**
 * Thời lượng kiểu phụ huynh đọc được: 45 phút · 2h05 · 12h30 (§14).
 */
final class Duration
{
    public static function human(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);

        if ($minutes < 60) {
            return $minutes.' phút';
        }

        return intdiv($minutes, 60).'h'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
