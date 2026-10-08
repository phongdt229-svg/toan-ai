<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/** Ảnh đề bài chụp từ điện thoại (đặc tả module 7). Ảnh được vẽ lại trước khi gửi AI và không lưu lại. */
class AiImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Ảnh điện thoại thường 2–5 MB; server thu nhỏ còn cạnh dài 1600px trước khi gửi đi.
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['image' => 'ảnh đề bài'];
    }
}
