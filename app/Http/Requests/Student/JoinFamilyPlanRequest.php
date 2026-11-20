<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class JoinFamilyPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStudent();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['join_code' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9]+$/']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['join_code' => 'mã gia đình'];
    }
}
