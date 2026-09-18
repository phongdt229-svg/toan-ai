<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:191'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'email'];
    }

    /**
     * 5 lần/phút theo email + IP — chặn dò xem email nào có tài khoản và chặn spam mail (§29).
     * Broker của Laravel còn chặn gửi lại cùng email trong 60 giây (config/auth.php).
     */
    public function ensureIsNotRateLimited(): void
    {
        $key = Str::transliterate(Str::lower((string) $this->input('email')).'|forgot|'.$this->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Bạn đã thử quá nhiều lần. Vui lòng đợi {$seconds} giây rồi thử lại.",
            ]);
        }

        RateLimiter::hit($key);
    }
}
