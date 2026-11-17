<?php

namespace App\Http\Requests\Admin;

use App\Models\LegalPage;
use Illuminate\Foundation\Http\FormRequest;

/** Tạo / sửa trang chính sách. Không có trường slug — cố ý: slug do hệ thống sinh lúc tạo và không bao giờ đổi. */
class LegalPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $page = $this->route('page');
        // Trang hệ thống được để trống nội dung (= dùng lại bản mặc định trong code).
        $contentRule = $page instanceof LegalPage && $page->is_system ? 'nullable' : 'required';

        return [
            'title' => ['required', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            // HtmlSanitizer lọc lúc lưu (model mutator); ở đây chỉ chặn nội dung quá dài.
            'content' => [$contentRule, 'string', 'max:100000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /** @return array<string, mixed> */
    public function pageData(): array
    {
        return [
            'title' => $this->validated('title'),
            'meta_description' => $this->validated('meta_description'),
            'content' => $this->validated('content'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) ($this->validated('sort_order') ?? 0),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'tiêu đề',
            'meta_description' => 'mô tả',
            'content' => 'nội dung',
            'sort_order' => 'thứ tự',
        ];
    }
}
