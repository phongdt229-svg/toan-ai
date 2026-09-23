<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** Mã TOTP 6 số (xác nhận lúc cài đặt) hoặc mã dự phòng (lúc đăng nhập, dài hơn). */
class TwoFactorCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['code' => $this->routeIs('admin.two-factor.confirm') ? ['required', 'digits:6'] : ['required', 'string', 'max:32']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['code' => 'mã xác thực'];
    }
}
