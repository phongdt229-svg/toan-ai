<?php

namespace App\Http\Requests\Teacher;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exam = $this->route('exam');

        return $exam
            ? $this->user()->can('update', $exam)
            : $this->user()->can('create', Exam::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $exam = $this->route('exam');

        return [
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Đổi lớp khi đề đã có câu hỏi sẽ làm bộ câu hỏi lệch lớp — khoá lại.
            'grade_id' => [
                'required', 'integer', 'exists:grades,id',
                function (string $attr, mixed $value, \Closure $fail) use ($exam) {
                    if ($exam && $exam->total_questions > 0 && (int) $value !== $exam->grade_id) {
                        $fail('Không thể đổi lớp khi đề đã có câu hỏi.');
                    }
                },
            ],
            'type' => ['required', Rule::in(array_keys(Exam::TYPES))],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
            'difficulty' => ['required', Rule::in(array_keys(Exam::DIFFICULTIES))],
            'access_level' => ['required', Rule::in(['free', 'pro', 'premium'])],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'show_answers_after_submit' => ['boolean'],
            'available_from' => ['nullable', 'date'],
            'available_to' => ['nullable', 'date', 'after:available_from'],
        ];
    }

    /** Checkbox không tick thì không gửi lên — phải ép về false, không thì giữ giá trị cũ. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'shuffle_questions' => $this->boolean('shuffle_questions'),
            'shuffle_options' => $this->boolean('shuffle_options'),
            'show_answers_after_submit' => $this->boolean('show_answers_after_submit'),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'tên đề',
            'grade_id' => 'lớp',
            'duration_minutes' => 'thời gian làm bài',
            'max_attempts' => 'số lượt làm',
            'available_from' => 'thời điểm mở',
            'available_to' => 'thời điểm đóng',
        ];
    }
}
