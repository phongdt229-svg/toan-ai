<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Không kiểm unique ở đây: EmailChangeService kiểm lại cả lúc yêu cầu lẫn lúc xác nhận,
            // vì giữa hai bước có thể có người khác vừa đăng ký địa chỉ đó.
            'email' => ['required', 'string', 'email', 'max:191'],
            // Hỏi lại mật khẩu: đổi email là đổi luôn đường đăng nhập và đường lấy lại mật khẩu —
            // không để người mượn được máy đang mở sẵn làm việc này.
            'current_password' => ['required', 'current_password'],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'email mới', 'current_password' => 'mật khẩu hiện tại'];
    }

    public function messages(): array
    {
        return ['current_password.current_password' => 'Mật khẩu không đúng.'];
    }
}
