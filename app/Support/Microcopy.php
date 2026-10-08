<?php

namespace App\Support;

use App\Models\StudentProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Lời chào / câu động viên theo học sinh (TA-21, đặc tả module 8 "microcopy phù hợp phong cách").
 *
 * Quy tắc, không gọi AI: rẻ, đoán trước được, và không bao giờ nói điều gì sai về bài học.
 * Sở thích khớp từ khoá thì dùng câu riêng; không khớp thì câu chung — không bịa sở thích cho em.
 */
class Microcopy
{
    /**
     * từ khoá CÓ DẤU (chữ thường) => câu động viên. Khớp có dấu, vì bỏ dấu thì "vệ sinh" thành "ve sinh"
     * và khớp nhầm "vẽ"; chỉ khi em gõ hoàn toàn không dấu ("bong da") mới so bản không dấu.
     */
    public const INTEREST_LINES = [
        'bóng đá' => 'Mỗi bài làm đúng là một bàn thắng ⚽ — vào sân thôi!',
        'game' => 'Hôm nay qua thêm vài màn Toán nhé 🎮 — mỗi câu đúng là một lần lên cấp.',
        'vẽ' => 'Toán cũng có hình và màu như tranh vẽ 🎨 — mình phác vài nét hôm nay nhé.',
        'nhạc' => 'Giữ nhịp đều như một bản nhạc 🎵 — mỗi ngày một chút là thuộc bài.',
        'đọc sách' => 'Mỗi bài Toán là một chương truyện nhỏ 📚 — đọc tiếp chương hôm nay nhé.',
        'bơi' => 'Như tập bơi, đều đặn mới nhanh 🏊 — làm vài vòng Toán hôm nay nào.',
        'nấu ăn' => 'Toán như nấu ăn — đúng công thức là ra món ngon 🍳.',
        'cầu lông' => 'Phản xạ nhanh như đánh cầu 🏸 — thử vài câu tính nhẩm nhé.',
        'robot' => 'Kỹ sư robot nào cũng giỏi Toán 🤖 — nạp thêm năng lượng hôm nay nhé.',
        'lập trình' => 'Lập trình viên giỏi đều nghĩ như dân Toán 💻 — giải vài bài khởi động nào.',
    ];

    public static function greeting(?Carbon $now = null): string
    {
        $hour = ($now ?? now())->hour;
        $part = match (true) {
            $hour < 11 => 'Chào buổi sáng',
            $hour < 14 => 'Chào buổi trưa',
            $hour < 18 => 'Chào buổi chiều',
            default => 'Chào buổi tối',
        };

        return $part;
    }

    /** Câu động viên dưới lời chào — theo sở thích nếu khớp, theo thầy/cô nếu không. */
    public static function encouragement(?StudentProfile $profile): string
    {
        foreach ($profile?->interests ?? [] as $interest) {
            $text = Str::of($interest)->lower()->squish()->value();
            $typedWithoutAccents = Str::ascii($text) === $text;

            foreach (self::INTEREST_LINES as $keyword => $line) {
                $needle = $typedWithoutAccents ? Str::ascii($keyword) : $keyword;

                if (preg_match('/(^|\s)'.preg_quote($needle, '/').'(\s|$)/u', $text)) {
                    return $line;
                }
            }
        }

        $who = $profile?->tutor_persona === 'thay' ? 'Thầy' : 'Cô';

        return "{$who} AI luôn sẵn sàng gợi ý khi em vướng — nhưng em tự làm trước nhé!";
    }
}
