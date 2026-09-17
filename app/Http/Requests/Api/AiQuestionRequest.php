<?php

namespace App\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Dùng chung cho các chế độ AI theo câu hỏi. */
class AiQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            // Đáp án cùng định dạng form luyện tập: id lựa chọn, chuỗi, hoặc mảng.
            'answer' => ['nullable', function (string $attr, mixed $value, Closure $fail) {
                if (is_array($value) && count($value) > 20) {
                    $fail('Đáp án không hợp lệ.');
                }
                if (is_string($value) && mb_strlen($value) > 2000) {
                    $fail('Đáp án quá dài.');
                }
            }],
            'answer.*' => ['nullable', 'string', 'max:500'],
            'work' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
