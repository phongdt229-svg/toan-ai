<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('session')->user_id === $this->user()->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers' => ['nullable', 'array', 'max:20'],
            // Giây làm từng câu do trình duyệt đo — chỉ dùng làm tín hiệu tốc độ, không ảnh hưởng điểm.
            'time_spent' => ['nullable', 'array', 'max:20'],
            'time_spent.*' => ['nullable', 'integer', 'min:0', 'max:900'],
        ];
    }
}
