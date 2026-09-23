<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('work', $this->route('assignment'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers' => ['nullable', 'array', 'max:100'],
            'time_spent' => ['nullable', 'array'],
            'time_spent.*' => ['nullable', 'integer', 'min:0', 'max:1800'],
        ];
    }
}
