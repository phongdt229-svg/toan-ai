<?php

namespace App\Http\Requests\Web;

use App\Http\Controllers\Web\CookieConsentController as C;
use Illuminate\Foundation\Http\FormRequest;

class CookieChoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['choice' => ['required', 'in:'.C::ACCEPTED.','.C::REJECTED.','.C::SEEN]];
    }
}
