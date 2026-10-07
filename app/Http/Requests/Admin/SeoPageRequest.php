<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeoPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'route_name' => ['required', 'string', Rule::in(array_keys(config('seo_pages')))],
            'title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'tiêu đề',
            'meta_description' => 'mô tả',
            'meta_keywords' => 'từ khoá',
        ];
    }
}
