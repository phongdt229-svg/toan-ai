<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class AddStudentToClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageStudents', $this->route('class'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'email học sinh'];
    }
}
