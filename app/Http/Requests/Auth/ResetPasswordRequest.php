<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            // Yêu cầu mật khẩu giống lúc đăng ký: tối thiểu 8 ký tự, có chữ và số.
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => 'email',
            'password' => 'mật khẩu mới',
        ];
    }
}
