<?php

namespace App\Http\Requests\Auth;

use App\Models\StudentProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StudentRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^0\d{9}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],

            // §33 — dữ liệu đầu vào cho AI cá nhân hóa.
            'birth_date' => ['nullable', 'date', 'before:today', 'after:'.now()->subYears(30)->toDateString()],
            'address' => ['nullable', 'string', 'max:191'],
            'school' => ['nullable', 'string', 'max:191'],
            'self_assessed_level' => ['nullable', Rule::in(array_keys(StudentProfile::LEVELS))],
            'math_average_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            // Form đã pre-check "cô"; không gửi thì service dùng mặc định đó.
            'tutor_persona' => ['nullable', Rule::in(array_keys(StudentProfile::PERSONAS))],
            'favorite_color' => ['nullable', 'string', 'max:20'],
            'interests' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'họ tên',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'password' => 'mật khẩu',
            'grade_id' => 'lớp',
            'birth_date' => 'ngày sinh',
            'address' => 'địa chỉ',
            'school' => 'trường học',
            'self_assessed_level' => 'học lực',
            'math_average_score' => 'điểm trung bình môn Toán',
            'tutor_persona' => 'giáo viên AI',
            'favorite_color' => 'màu yêu thích',
            'interests' => 'sở thích',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.',
        ];
    }
}
