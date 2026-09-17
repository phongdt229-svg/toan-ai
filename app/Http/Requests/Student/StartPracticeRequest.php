<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPracticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'limit' => ['nullable', 'integer', 'min:5', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'topic_id' => 'chủ đề',
            'difficulty' => 'độ khó',
            'limit' => 'số câu',
        ];
    }
}
