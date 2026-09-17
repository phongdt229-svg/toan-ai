<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sau khi giao chỉ đổi được thông tin mô tả và hạn/lượt làm —
 * đổi nội dung bài khi học sinh đã nộp sẽ làm điểm cũ mất ý nghĩa.
 */
class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('assignment'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Được dời hạn về quá khứ (đóng sớm), nên không ép after:now như lúc tạo.
            'due_at' => ['nullable', 'date'],
            'allow_retry' => ['boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:2', 'max:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['allow_retry' => $this->boolean('allow_retry')]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'tên bài', 'due_at' => 'hạn nộp', 'max_attempts' => 'số lượt làm'];
    }
}
