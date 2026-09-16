<?php

namespace App\Http\Requests\Teacher;

use App\Models\LessonSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lesson'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(LessonSection::TYPES))],
            'title' => ['nullable', 'string', 'max:191'],
            // HtmlSanitizer lọc lúc lưu; ở đây chỉ chặn nội dung quá dài.
            'content' => ['required', 'string', 'max:65000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => 'loại nội dung',
            'title' => 'tiêu đề',
            'content' => 'nội dung',
        ];
    }
}
