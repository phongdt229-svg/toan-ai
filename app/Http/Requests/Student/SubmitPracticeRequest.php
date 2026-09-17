<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPracticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    /**
     * Không validate đáp án theo câu hỏi ở đây — bộ câu hỏi nằm trong session,
     * GradingService chỉ chấm những câu thuộc bộ đó nên dữ liệu thừa bị bỏ qua.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['nullable', 'array', 'max:50'],
            'time_spent' => ['nullable', 'array', 'max:50'],
            'time_spent.*' => ['nullable', 'integer', 'min:0', 'max:1800'],
        ];
    }
}
