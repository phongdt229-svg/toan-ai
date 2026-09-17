<?php

namespace App\Http\Requests\Teacher;

use App\Models\Assignment;
use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $class = SchoolClass::find($this->integer('class_id'));

        // Lớp không tồn tại để rules() báo lỗi validate thay vì 403.
        return $class === null || $this->user()->can('create', [Assignment::class, $class]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(array_keys(Assignment::TYPES))],

            'exam_id' => ['nullable', 'required_if:type,exam', 'integer', 'exists:exams,id'],
            'lesson_id' => ['nullable', 'required_if:type,lesson', 'integer', 'exists:lessons,id'],
            'question_ids' => ['nullable', 'required_if:type,question_set', 'array', 'max:100'],
            'question_ids.*' => ['integer'],

            'assign_to_all' => ['boolean'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer'],

            'due_at' => ['nullable', 'date', 'after:now'],
            'allow_retry' => ['boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:2', 'max:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'assign_to_all' => $this->boolean('assign_to_all'),
            'allow_retry' => $this->boolean('allow_retry'),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'class_id' => 'lớp',
            'title' => 'tên bài',
            'type' => 'loại bài',
            'exam_id' => 'đề kiểm tra',
            'lesson_id' => 'bài học',
            'question_ids' => 'câu hỏi',
            'student_ids' => 'học sinh',
            'due_at' => 'hạn nộp',
            'max_attempts' => 'số lượt làm',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'due_at.after' => 'Hạn nộp phải ở tương lai.',
        ];
    }
}
