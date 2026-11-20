<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReferralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_enabled' => ['nullable', 'boolean'],
            // Trần 50%: mã giảm sâu hơn nữa là cho không gói học, không còn là "quà giới thiệu".
            'new_user_percent' => ['required', 'integer', 'min:0', 'max:50'],
            'referrer_percent' => ['required', 'integer', 'min:0', 'max:50'],
            'voucher_valid_days' => ['required', 'integer', 'min:7', 'max:365'],
            'max_rewards_per_referrer' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'new_user_percent' => 'mức giảm cho bạn mới',
            'referrer_percent' => 'mức giảm cho người giới thiệu',
            'voucher_valid_days' => 'hạn dùng mã',
            'max_rewards_per_referrer' => 'số lần thưởng tối đa',
        ];
    }
}
