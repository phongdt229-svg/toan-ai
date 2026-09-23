<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('exam'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'points' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
