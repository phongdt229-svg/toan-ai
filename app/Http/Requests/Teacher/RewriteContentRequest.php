<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RewriteContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('ai.generate_content') || $this->user()->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'mode' => ['required', Rule::in(['simplify', 'summarize'])],
        ];
    }
}
