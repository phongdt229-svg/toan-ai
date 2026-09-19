<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Dùng chung cho cả 4 portal — hồ sơ chỉ có tên + SĐT, không có gì riêng theo vai trò. */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^0\d{9}$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'họ tên',
            'phone' => 'số điện thoại',
        ];
    }
}
