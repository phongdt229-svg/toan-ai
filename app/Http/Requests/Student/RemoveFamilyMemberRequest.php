<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class RemoveFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-family-member', [$this->route('subscription'), $this->route('member')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
