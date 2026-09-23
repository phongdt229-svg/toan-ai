<?php

namespace App\Http\Requests\Admin;

use App\Models\Voucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Voucher::normalizeCode($this->input('code')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        /** @var Voucher|null $voucher */
        $voucher = $this->route('voucher');

        return [
            // alpha_dash: mã nằm trên URL/QR và người dùng gõ tay — tránh khoảng trắng, dấu tiếng Việt.
            'code' => ['required', 'string', 'alpha_dash', 'min:3', 'max:32',
                Rule::unique('vouchers', 'code')->ignore($voucher?->id)],
            'description' => ['nullable', 'string', 'max:191'],
            'type' => ['required', Rule::in(array_keys(Voucher::TYPE_LABELS))],
            'value' => ['required', 'numeric', 'min:0.01'],
            'max_discount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_uses_per_user' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['boolean'],
            'packages' => ['array'],
            'packages.*' => ['integer', 'exists:packages,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('type') === Voucher::TYPE_PERCENT && (float) $this->input('value') > 100) {
                $validator->errors()->add('value', 'Giảm theo % thì giá trị không quá 100.');
            }

            // Trần giảm chỉ có nghĩa với loại %; đặt cho loại "số tiền" là thừa và gây hiểu nhầm.
            if ($this->input('type') === Voucher::TYPE_FIXED && $this->filled('max_discount')) {
                $validator->errors()->add('max_discount', 'Mã giảm số tiền cố định không cần trần giảm.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'code' => 'mã',
            'type' => 'loại giảm',
            'value' => 'giá trị',
            'max_discount' => 'giảm tối đa',
            'min_order_amount' => 'đơn tối thiểu',
            'starts_at' => 'ngày bắt đầu',
            'ends_at' => 'ngày kết thúc',
            'max_uses' => 'tổng lượt',
            'max_uses_per_user' => 'lượt mỗi người',
        ];
    }
}
