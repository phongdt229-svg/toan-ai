<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Hỏi lại mật khẩu: đây là thao tác không lấy lại được, và chặn người khác
            // dùng máy đang mở sẵn để xoá tài khoản hộ.
            'password' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! Hash::check((string) $value, $this->user()->password)) {
                    $fail('Mật khẩu không đúng.');
                }
            }],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['password' => 'mật khẩu', 'reason' => 'lý do'];
    }
}
