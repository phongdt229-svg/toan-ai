<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Ghi tiến độ hướng dẫn (OB-03): tour đã xem / bỏ qua ở bước mấy, ẩn danh sách "Bắt đầu". */
class RecordOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Chỉ nhận khoá có thật trong config — không cho client ghi khoá tuỳ ý vào bảng.
        $keys = collect(array_keys(config('onboarding.tours', [])))->map(fn ($r) => "tour:{$r}")->push('checklist:hidden')->all();

        return [
            'key' => ['required', 'string', Rule::in($keys)],
            'status' => ['required', Rule::in(['completed', 'dismissed', 'hidden'])],
            'step' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }
}
