<?php

namespace App\Services\Learning;

use App\Models\StudentActivityLog;
use App\Models\StudentDailyActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Thời gian học thật (đặc tả "Logic": effective_study_time = tổng thời gian có tương tác hợp lệ).
 *
 * Trình duyệt gửi heartbeat mỗi 30 giây khi tab đang hiển thị, kèm cờ "có tương tác trong 60 giây qua"
 * (cuộn, gõ, bấm). Server cộng thời gian theo KHOẢNG CÁCH giữa hai heartbeat, chặn trên một nhịp —
 * nên mở nhiều tab hay gửi dồn cũng không cộng nhanh hơn đồng hồ thật.
 */
class ActivityService
{
    public const HEARTBEAT_SECONDS = 30;

    /** Mỗi heartbeat cộng tối đa chừng này giây — mất mạng 10 phút rồi quay lại không được tính 10 phút. */
    public const MAX_CREDIT_SECONDS = 35;

    /** Heartbeat sát nhau hơn mức này bị bỏ qua (tab thứ hai, script gửi dồn). */
    public const MIN_GAP_SECONDS = 5;

    /** Mức tập trung = học thực / online. */
    public const FOCUS_HIGH = 60;

    public const FOCUS_LOW = 30;

    public function heartbeat(User $student, bool $active, ?Carbon $at = null): StudentDailyActivity
    {
        $at ??= now();

        return DB::transaction(function () use ($student, $active, $at) {
            $row = StudentDailyActivity::where('user_id', $student->id)
                ->whereDate('activity_date', $at->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $row) {
                // Heartbeat đầu tiên trong ngày: chưa biết học sinh đã ở đây bao lâu → chỉ ghi mốc, không cộng.
                return StudentDailyActivity::create([
                    'user_id' => $student->id,
                    'activity_date' => $at->toDateString(),
                    'first_seen_at' => $at,
                    'last_seen_at' => $at,
                    'last_active_at' => $active ? $at : null,
                ]);
            }

            $elapsed = (int) $row->last_seen_at->diffInSeconds($at, false);

            if ($elapsed < self::MIN_GAP_SECONDS) {
                return $row;
            }

            $credit = min($elapsed, self::MAX_CREDIT_SECONDS);

            $row->online_seconds += $credit;
            if ($active) {
                $row->active_seconds += $credit;
                $row->last_active_at = $at;
            }
            $row->last_seen_at = $at;
            $row->save();

            return $row;
        });
    }

    /**
     * @param  array<int, array{type: string, path?: ?string, meta?: ?array}>  $events
     */
    public function recordEvents(User $student, array $events, ?Carbon $at = null): int
    {
        $at ??= now();

        $rows = collect($events)
            ->filter(fn ($e) => isset(StudentActivityLog::TYPES[$e['type'] ?? '']))
            ->take(20)
            ->map(fn ($e) => [
                'user_id' => $student->id,
                'event_type' => $e['type'],
                'path' => isset($e['path']) ? Str::limit((string) $e['path'], 191, '') : null,
                'meta' => isset($e['meta']) ? json_encode(array_slice((array) $e['meta'], 0, 10)) : null,
                'occurred_at' => $at,
            ])
            ->values();

        if ($rows->isNotEmpty()) {
            StudentActivityLog::insert($rows->all());
        }

        return $rows->count();
    }

    public function today(User $student): ?StudentDailyActivity
    {
        return StudentDailyActivity::where('user_id', $student->id)->whereDate('activity_date', today())->first();
    }

    /**
     * N ngày gần nhất, ngày không học vẫn có dòng (0 phút) để phụ huynh thấy ngày trống.
     *
     * @return Collection<int, array{date: Carbon, online: int, active: int, focus: ?string}>
     */
    public function lastDays(User $student, int $days = 7): Collection
    {
        $from = today()->subDays($days - 1);

        $rows = StudentDailyActivity::where('user_id', $student->id)
            ->whereDate('activity_date', '>=', $from)
            ->get()
            ->keyBy(fn ($r) => $r->activity_date->toDateString());

        return collect(range(0, $days - 1))->map(function ($i) use ($from, $rows) {
            $date = $from->copy()->addDays($i);
            $row = $rows->get($date->toDateString());

            return [
                'date' => $date,
                'online' => $row?->onlineMinutes() ?? 0,
                'active' => $row?->activeMinutes() ?? 0,
                'focus' => $row ? $this->focusLevel($row->online_seconds, $row->active_seconds) : null,
            ];
        });
    }

    /** high / medium / low — null khi online quá ít để kết luận (dưới 5 phút). */
    public function focusLevel(int $onlineSeconds, int $activeSeconds): ?string
    {
        if ($onlineSeconds < 300) {
            return null;
        }

        $percent = $activeSeconds / $onlineSeconds * 100;

        return match (true) {
            $percent >= self::FOCUS_HIGH => 'high',
            $percent >= self::FOCUS_LOW => 'medium',
            default => 'low',
        };
    }

    public static function focusLabel(?string $level): string
    {
        return ['high' => 'Tập trung tốt', 'medium' => 'Tập trung vừa', 'low' => 'Tập trung thấp'][$level] ?? '—';
    }
}
