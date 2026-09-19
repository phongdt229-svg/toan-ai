<?php

namespace App\Http\Requests;

use App\Support\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['array'],
            // Chỉ nhận đúng các key đã biết — checkbox client gửi gì cũng không ghi được key lạ vào cột JSON.
            'enabled.*' => [Rule::in(array_keys(NotificationType::LABELS))],
        ];
    }
}
