<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBankTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Mã giao dịch trong app/sao kê ngân hàng — bắt buộc để sau này đối chiếu được ai đã xác nhận khoản nào.
            'reference' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reference' => 'mã giao dịch ngân hàng'];
    }
}
