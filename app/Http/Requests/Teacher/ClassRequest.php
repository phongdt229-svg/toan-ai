<?php

namespace App\Http\Requests\Teacher;

use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;

class ClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $class = $this->route('class');

        return $class
            ? $this->user()->can('update', $class)
            : $this->user()->can('create', SchoolClass::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'tên lớp', 'grade_id' => 'khối lớp', 'description' => 'mô tả'];
    }
}
