<?php

namespace App\Http\Requests\Student;

use App\Models\StudentProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Thầy/cô, màu yêu thích, sở thích — đổi được sau đăng ký (đặc tả module 8). */
class UpdatePersonalizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->studentProfile !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tutor_persona' => ['required', Rule::in(array_keys(StudentProfile::PERSONAS))],
            // Chỉ nhận #rrggbb: giá trị này được in vào <style> của layout, không cho chuỗi tự do.
            'favorite_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'interests' => ['nullable', 'string', 'max:255'],
            'target_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tutor_persona' => 'giáo viên AI',
            'favorite_color' => 'màu yêu thích',
            'interests' => 'sở thích',
            'target_score' => 'điểm mục tiêu',
        ];
    }
}
