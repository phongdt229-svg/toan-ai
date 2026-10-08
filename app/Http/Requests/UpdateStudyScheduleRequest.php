<?php

namespace App\Http\Requests;

use App\Models\StudySchedule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lịch học tuần (D-01) — dùng chung cho học sinh tự sửa và phụ huynh sửa cho con.
 * Form gửi `days[1..7][enabled|start_time|duration_minutes]`; ngày không bật = không học.
 */
class UpdateStudyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-study-schedule', $this->student());
    }

    /** Học sinh được sửa lịch: route `{student}` của phụ huynh, hoặc chính người đang đăng nhập. */
    public function student(): User
    {
        $student = $this->route('student');

        return $student instanceof User ? $student : $this->user();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'days' => ['nullable', 'array'],
            'days.*' => ['array'],
            'days.*.enabled' => ['nullable', 'boolean'],
            'days.*.start_time' => ['nullable', 'required_if_accepted:days.*.enabled', 'date_format:H:i'],
            'days.*.duration_minutes' => ['nullable', 'required_if_accepted:days.*.enabled', Rule::in(StudySchedule::DURATIONS)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'days.*.start_time' => 'giờ bắt đầu',
            'days.*.duration_minutes' => 'thời lượng',
        ];
    }

    /**
     * Chỉ các ngày được bật, key là thứ trong tuần hợp lệ (1–7).
     *
     * @return array<int, array{start_time: string, duration_minutes: int}>
     */
    public function slots(): array
    {
        return collect($this->validated('days') ?? [])
            ->filter(fn ($day, $weekday) => isset(StudySchedule::WEEKDAYS[(int) $weekday]) && filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->map(fn ($day) => ['start_time' => $day['start_time'], 'duration_minutes' => (int) $day['duration_minutes']])
            ->mapWithKeys(fn ($slot, $weekday) => [(int) $weekday => $slot])
            ->all();
    }
}
