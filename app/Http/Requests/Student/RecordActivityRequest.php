<?php

namespace App\Http\Requests\Student;

use App\Models\StudentActivityLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Heartbeat + sự kiện giao diện từ trang học của học sinh (resources/js/activity-tracker.js). */
class RecordActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStudent();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
            'events' => ['nullable', 'array', 'max:20'],
            'events.*.type' => ['required', Rule::in(array_keys(StudentActivityLog::TYPES))],
            'events.*.path' => ['nullable', 'string', 'max:191'],
            'events.*.meta' => ['nullable', 'array', 'max:10'],
        ];
    }
}
