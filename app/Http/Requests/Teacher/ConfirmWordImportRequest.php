<?php

namespace App\Http\Requests\Teacher;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmWordImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Question::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:draft,published'],
        ];
    }
}
