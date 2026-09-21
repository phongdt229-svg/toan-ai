<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Bật chế độ bảo trì — dùng chung cho cả bật và tắt. */
class MaintenanceModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        // Tắt bảo trì thì không gửi gì cả, nên chỉ ràng buộc khi có dữ liệu.
        if ($this->isMethod('DELETE')) {
            return [];
        }

        return [
            // Hiện nguyên văn trên trang bảo trì nên để người bật tự viết ("30 phút", "sau 21h").
            'eta' => ['nullable', 'string', 'max:50'],
            // Chặn bấm nhầm: bật bảo trì là cả trang ngừng phục vụ.
            'confirm' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Hãy tích vào ô xác nhận trước khi bật chế độ bảo trì.',
        ];
    }

    public function attributes(): array
    {
        return ['eta' => 'thời gian dự kiến'];
    }
}
