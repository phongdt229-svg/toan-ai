<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LegalPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // HtmlSanitizer lọc lúc lưu (model mutator); ở đây chỉ chặn nội dung quá dài.
            'content' => ['required', 'string', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['content' => 'nội dung'];
    }
}
