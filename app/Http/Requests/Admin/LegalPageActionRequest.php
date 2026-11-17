<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Khôi phục mặc định / xoá trang chính sách: không có dữ liệu nhập, chỉ kiểm quyền admin. */
class LegalPageActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
