<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    /** Quyền admin do middleware nhóm route `quan-tri` chặn; ở đây chỉ cần chắc chắn có người đăng nhập. */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:191'],
            // Bỏ trống = hoàn toàn bộ phần còn lại. Giới hạn trên do PaymentService kiểm dưới khoá dòng.
            'amount' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => 'lý do hoàn tiền', 'amount' => 'số tiền hoàn'];
    }
}
