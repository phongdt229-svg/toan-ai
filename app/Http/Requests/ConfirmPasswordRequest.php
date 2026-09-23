<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/** Thao tác nhạy cảm (bật/tắt 2FA, tải dữ liệu cá nhân): hỏi lại mật khẩu hiện tại. */
class ConfirmPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! Hash::check((string) $value, $this->user()->password)) {
                    $fail('Mật khẩu hiện tại không đúng.');
                }
            }],
        ];
    }
}
