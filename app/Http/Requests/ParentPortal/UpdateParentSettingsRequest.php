<?php

namespace App\Http\Requests\ParentPortal;

use Illuminate\Foundation\Http\FormRequest;

/** Cài đặt báo cáo của phụ huynh: báo cáo tuần, thông báo bắt đầu/xong buổi, báo cáo cuối ngày. */
class UpdateParentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isParent();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'weekly_report_enabled' => ['nullable', 'boolean'],
            'session_events_enabled' => ['nullable', 'boolean'],
            'daily_report_enabled' => ['nullable', 'boolean'],
        ];
    }
}
