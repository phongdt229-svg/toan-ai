<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Section phải thuộc đúng bài học trên URL — chặn client gửi id bài khác.
            'section_id' => [
                'required', 'integer',
                Rule::exists('lesson_sections', 'id')
                    ->where('lesson_id', $this->route('lesson')->id),
            ],
            'seconds_spent' => ['nullable', 'integer', 'min:0', 'max:300'],
        ];
    }
}
