<?php

namespace App\Http\Requests\ParentPortal;

use Illuminate\Foundation\Http\FormRequest;

class LinkChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('child.link');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['link_code' => ['required', 'string', 'max:16']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['link_code' => 'mã liên kết'];
    }
}
