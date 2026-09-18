<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

/**
 * Captcha bằng một phép tính đơn giản — hợp với một nền tảng dạy Toán và quan trọng hơn:
 * là chữ thuần nên đọc được bằng trình đọc màn hình, không cần ảnh, không gọi dịch vụ ngoài
 * (CSP đang chặn script/ảnh từ domain khác).
 *
 * Đáp án nằm trong session của người dùng, không nằm trong HTML → bot không đọc được từ form.
 */
class MathCaptcha
{
    private const SESSION_KEY = 'captcha.math';

    /** Câu hỏi sống 15 phút: đủ để điền form, không để bot dùng lại mãi một đáp án. */
    private const TTL_MINUTES = 15;

    /** Sinh câu hỏi mới và ghi đáp án vào session. Trả về chuỗi hiển thị cho người dùng. */
    public static function question(): string
    {
        [$a, $b] = [random_int(2, 9), random_int(2, 9)];

        // Chỉ cộng và nhân với số nhỏ: học sinh lớp 1 cũng làm được, không cản trở người dùng thật.
        if (random_int(0, 1) === 1) {
            $question = "{$a} + {$b}";
            $answer = $a + $b;
        } else {
            $question = "{$a} × {$b}";
            $answer = $a * $b;
        }

        Session::put(self::SESSION_KEY, [
            'answer' => $answer,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ]);

        return $question;
    }

    /** Kiểm tra đáp án. Đúng hay sai đều xoá câu hỏi cũ — mỗi câu chỉ dùng một lần. */
    public static function check(mixed $input): bool
    {
        $stored = Session::pull(self::SESSION_KEY);

        if (! is_array($stored) || ($stored['expires_at'] ?? 0) < now()->getTimestamp()) {
            return false;
        }

        return is_numeric($input) && (int) $input === (int) $stored['answer'];
    }

    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
