<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('test')->user_id === $this->user()->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers' => ['nullable', 'array', 'max:20'],
            'time_spent' => ['nullable', 'array', 'max:20'],
            'time_spent.*' => ['nullable', 'integer', 'min:0', 'max:1800'],
        ];
    }
}
