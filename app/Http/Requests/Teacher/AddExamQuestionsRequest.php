<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class AddExamQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('exam'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question_ids' => ['required', 'array', 'min:1', 'max:100'],
            'question_ids.*' => ['integer'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['question_ids' => 'câu hỏi'];
    }
}
