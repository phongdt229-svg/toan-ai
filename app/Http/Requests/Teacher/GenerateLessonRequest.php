<?php

namespace App\Http\Requests\Teacher;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('ai.generate_content') || $this->user()->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'title' => ['required', 'string', 'max:191'],
            'difficulty' => ['required', Rule::in(array_keys(Question::DIFFICULTIES))],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['topic_id' => 'chủ đề', 'title' => 'tên bài học'];
    }
}
