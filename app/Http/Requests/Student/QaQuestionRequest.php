<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class QaQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'title' => ['required', 'string', 'min:10', 'max:191'],
            // Lọc HTML ở QaService lúc lưu, không lọc lúc hiển thị (luật chung của repo).
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return ['topic_id' => 'chủ đề', 'title' => 'tiêu đề', 'body' => 'nội dung'];
    }

    public function messages(): array
    {
        return [
            'title.min' => 'Tiêu đề quá ngắn — viết rõ em đang vướng ở đâu để mọi người trả lời trúng.',
            'body.min' => 'Nội dung quá ngắn — chép đề bài hoặc chỗ em làm tới đâu thì bí.',
        ];
    }
}
