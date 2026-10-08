<?php

namespace App\Services\Learning;

use App\Models\StudentDailyActivity;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Chuỗi ngày học liên tiếp (TA-15, đặc tả module 9 "Streak — duy trì chuỗi").
 *
 * D-04 (chốt 09/10/2026): một ngày được tính khi học thực ≥ `learning.streak_min_minutes` phút (mặc định 10) — học thực lấy từ
 * thời gian có thao tác (TA-08), nên mở app cho có không giữ được chuỗi.
 * Hôm nay chưa đủ phút thì chuỗi tính tới hôm qua và vẫn "còn sống" tới hết ngày — không phạt em giữa ngày.
 */
class StreakService
{
    /** D-04: số phút học thực tối thiểu để một ngày được tính — đọc từ config/learning.php. */
    public static function minActiveMinutes(): int
    {
        return (int) config('learning.streak_min_minutes', 10);
    }

    /** Đủ xa để kỷ lục có nghĩa mà không phải quét cả lịch sử. */
    private const LOOKBACK_DAYS = 400;

    /**
     * @return array{current: int, best: int, today_done: bool, today_minutes: int, minutes_to_keep: int}
     */
    public function forStudent(User $student, ?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();

        $rows = StudentDailyActivity::where('user_id', $student->id)
            ->whereDate('activity_date', '>=', $today->copy()->subDays(self::LOOKBACK_DAYS))
            ->get(['activity_date', 'active_seconds']);

        $todayMinutes = intdiv((int) ($rows->first(fn ($r) => $r->activity_date->isSameDay($today))?->active_seconds ?? 0), 60);
        $days = $rows->filter(fn ($r) => $r->active_seconds >= self::minActiveMinutes() * 60)
            ->map(fn ($r) => $r->activity_date->toDateString())
            ->flip();

        $todayDone = $days->has($today->toDateString());

        // Chuỗi hiện tại: đếm lùi từ hôm nay (nếu đã đủ) hoặc từ hôm qua.
        $current = 0;
        $cursor = $todayDone ? $today->copy() : $today->copy()->subDay();
        while ($days->has($cursor->toDateString())) {
            $current++;
            $cursor->subDay();
        }

        // Kỷ lục: chuỗi dài nhất trong khoảng đã quét.
        $best = 0;
        $run = 0;
        $previous = null;
        foreach ($days->keys()->sort()->values() as $date) {
            $day = Carbon::parse($date);
            $run = $previous && $previous->copy()->addDay()->isSameDay($day) ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $day;
        }

        return [
            'current' => $current,
            'best' => max($best, $current),
            'today_done' => $todayDone,
            'today_minutes' => $todayMinutes,
            'minutes_to_keep' => $todayDone ? 0 : max(0, self::minActiveMinutes() - $todayMinutes),
        ];
    }
}
