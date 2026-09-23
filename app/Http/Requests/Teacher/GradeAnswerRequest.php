<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class GradeAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (function () {
            $answer = $this->route('answer')->loadMissing('attempt.exam');

            return $this->user()->can('grade', $answer->attempt->exam);
        })();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['score' => 'điểm', 'feedback' => 'nhận xét'];
    }
}
