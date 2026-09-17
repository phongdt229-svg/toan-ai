<?php

namespace App\Http\Requests\Student;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SaveExamAnswerRequest extends FormRequest
{
    /**
     * Chỉ kiểm tra quyền sở hữu. Lượt đã nộp / hết giờ do ExamService trả 409
     * kèm link kết quả, để client chuyển trang thay vì hiện lỗi 403 khó hiểu.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('attempt'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer'],
            // Giá trị có thể là chuỗi (tự luận, trả lời ngắn) hoặc mảng (nhiều lựa chọn, nhiều chỗ trống).
            'value' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && mb_strlen($value) > 20000) {
                    $fail('Câu trả lời quá dài.');
                }
                if (is_array($value) && count($value) > 20) {
                    $fail('Câu trả lời không hợp lệ.');
                }
            }],
            'value.*' => ['nullable', 'string', 'max:2000'],
            'time_spent' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ];
    }
}
