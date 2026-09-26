<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'blog_category_id' => ['required', 'integer', 'exists:blog_categories,id'],
            'title' => ['required', 'string', 'max:191'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            // HtmlSanitizer lọc lúc lưu (model mutator); ở đây chỉ chặn nội dung quá dài.
            'content' => ['required', 'string', 'max:65000'],
            'status' => ['required', Rule::in(array_keys(BlogPost::STATUS_LABELS))],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'blog_category_id' => 'danh mục',
            'title' => 'tiêu đề',
            'excerpt' => 'tóm tắt',
            'content' => 'nội dung',
            'status' => 'trạng thái',
            'cover' => 'ảnh bìa',
        ];
    }
}
