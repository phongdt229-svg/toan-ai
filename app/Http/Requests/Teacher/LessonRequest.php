<?php

namespace App\Http\Requests\Teacher;

use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        return $lesson
            ? $this->user()->can('update', $lesson)
            : $this->user()->can('create', Lesson::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $lessonId = $this->route('lesson')?->id;

        return [
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'title' => ['required', 'string', 'max:191'],
            'slug' => [
                'nullable', 'string', 'max:191', 'alpha_dash',
                Rule::unique('lessons', 'slug')->ignore($lessonId),
            ],
            'summary' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'estimated_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'access_level' => ['required', Rule::in([
                Lesson::ACCESS_FREE, Lesson::ACCESS_PRO, Lesson::ACCESS_PREMIUM,
            ])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'topic_id' => 'chủ đề',
            'title' => 'tiêu đề',
            'slug' => 'đường dẫn',
            'summary' => 'tóm tắt',
            'difficulty' => 'độ khó',
            'estimated_minutes' => 'thời lượng',
            'access_level' => 'gói truy cập',
        ];
    }
}
