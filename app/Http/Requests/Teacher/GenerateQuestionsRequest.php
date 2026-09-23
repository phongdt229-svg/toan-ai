<?php

namespace App\Http\Requests\Teacher;

use App\Services\AI\ContentGeneratorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateQuestionsRequest extends FormRequest
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
            'count' => ['required', 'integer', 'min:1', 'max:20'],
            'easy' => ['required', 'integer', 'min:0', 'max:100'],
            'medium' => ['required', 'integer', 'min:0', 'max:100'],
            'hard' => ['required', 'integer', 'min:0', 'max:100'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::in(ContentGeneratorService::allowedTypes())],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['topic_id' => 'chủ đề', 'count' => 'số câu', 'types' => 'loại câu hỏi'];
    }
}
