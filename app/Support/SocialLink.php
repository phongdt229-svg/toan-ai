<?php

namespace App\Support;

/**
 * Chặn link mạng xã hội giả (facebook.com/x, google.com/test…) lọt lên footer.
 * Đây là lưới an toàn khi chép nhầm `.env` của máy dev sang production — link giả nhìn
 * vẫn như link thật nên không ai để ý cho tới khi khách bấm vào.
 */
class SocialLink
{
    private const PLACEHOLDERS = ['x', 'xx', 'xxx', 'test', 'example', 'demo', 'placeholder', 'abc123'];

    public static function isUsable(?string $url): bool
    {
        $parts = parse_url((string) $url);

        if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ! str_contains($parts['host'] ?? '', '.')) {
            return false;
        }

        $last = strtolower(ltrim(basename(rtrim($parts['path'] ?? '', '/')), '@'));

        return ! in_array($last, self::PLACEHOLDERS, true)
            && ! in_array(strtolower($parts['host']), ['example.com', 'www.example.com'], true);
    }
}
