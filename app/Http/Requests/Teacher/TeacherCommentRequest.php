<?php

namespace App\Http\Requests\Teacher;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class TeacherCommentRequest extends FormRequest
{
    /** Chỉ nhận xét học sinh đang học lớp mình dạy. */
    public function authorize(): bool
    {
        /** @var User $student */
        $student = $this->route('student');

        return $this->user()->hasPermission('comment.create') && $this->user()->teachesStudent($student);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'visible_to_parent' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['visible_to_parent' => $this->boolean('visible_to_parent')]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['content' => 'nội dung nhận xét'];
    }
}
