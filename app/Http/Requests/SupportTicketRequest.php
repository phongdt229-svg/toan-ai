<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use App\Support\MathCaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Người đã đăng nhập không phải gõ lại tên/email.
        if ($user = $this->user()) {
            $this->merge(['name' => $user->name, 'email' => $user->email]);
        }

        // Trình duyệt luôn gửi UTF-8; script/bot có thể gửi byte hỏng làm MySQL từ chối cả câu insert (lỗi 500).
        // Cắt bỏ byte hỏng, giữ lại phần đọc được.
        foreach (['name', 'subject', 'message'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $this->merge([$field => mb_convert_encoding($value, 'UTF-8', 'UTF-8')]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(SupportTicket::TYPE_LABELS))],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191'],
            'subject' => ['required', 'string', 'max:191'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'context_url' => ['nullable', 'string', 'max:500'],
            'captcha' => ['required'],
            // Bẫy bot: ô ẩn, người dùng thật không bao giờ điền.
            'website' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'message.min' => 'Hãy mô tả rõ hơn (ít nhất 20 ký tự) để chúng tôi hỗ trợ đúng việc.',
            'website.prohibited' => 'Yêu cầu không hợp lệ.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => 'loại yêu cầu',
            'name' => 'họ tên',
            'email' => 'email',
            'subject' => 'tiêu đề',
            'message' => 'nội dung',
            'captcha' => 'kết quả phép tính',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty() && $validator->errors()->has('captcha')) {
                return;
            }

            if (! MathCaptcha::check($this->input('captcha'))) {
                $validator->errors()->add('captcha', 'Kết quả phép tính chưa đúng. Thử lại với phép tính mới nhé.');
            }
        }];
    }

    /** Chặn gửi hàng loạt: 5 yêu cầu/giờ cho mỗi IP (và mỗi email). */
    public function ensureIsNotRateLimited(): void
    {
        $key = 'support|'.Str::lower((string) $this->input('email')).'|'.$this->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([
                'message' => "Bạn đã gửi nhiều yêu cầu liên tiếp. Vui lòng đợi khoảng {$minutes} phút rồi gửi tiếp.",
            ]);
        }

        RateLimiter::hit($key, 3600);
    }
}
