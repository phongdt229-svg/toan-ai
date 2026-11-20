<?php

namespace App\Http\Requests\Teacher;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportWordQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Question::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            // Chủ đề phải thuộc đúng lớp đã chọn — QuestionImporter kiểm lại lần nữa lúc lưu.
            'topic_id' => ['nullable', 'integer', Rule::exists('topics', 'id')],
            // Đề có hình nặng vài MB; 10MB là đủ mà không để tệp khổng lồ làm treo máy chủ.
            'file' => ['required', 'file', 'mimes:docx', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['file' => 'tệp Word', 'grade_id' => 'lớp', 'topic_id' => 'chủ đề'];
    }
}
