<?php

namespace App\Support;

/**
 * Màu nhấn của portal học sinh theo màu yêu thích (đặc tả module 8).
 *
 * Học sinh chọn màu tự do bằng color picker — màu vàng nhạt hay xanh lá non đặt lên nút chữ trắng là không đọc được.
 * Nên ta tối dần màu cho tới khi chữ trắng trên nền đó đạt tương phản WCAG AA (4.5:1) thay vì tin màu gốc.
 */
class ThemeColor
{
    public const MIN_CONTRAST = 4.5;

    /**
     * @return array{hex: string, rgb: string, hover: string}|null null = màu không hợp lệ → giữ theme mặc định
     */
    public static function accent(?string $hex): ?array
    {
        $rgb = self::parse($hex);
        if ($rgb === null) {
            return null;
        }

        // Mỗi bước tối 5% — tối đa 40 bước là chạm gần đen, chắc chắn đạt.
        for ($i = 0; $i < 40 && self::contrastWithWhite($rgb) < self::MIN_CONTRAST; $i++) {
            $rgb = array_map(fn ($c) => (int) floor($c * 0.95), $rgb);
        }

        $hover = array_map(fn ($c) => (int) floor($c * 0.85), $rgb);

        return [
            'hex' => self::toHex($rgb),
            'rgb' => implode(',', $rgb),
            'hover' => self::toHex($hover),
        ];
    }

    /** @return array{0: int, 1: int, 2: int}|null */
    public static function parse(?string $hex): ?array
    {
        if (! is_string($hex) || ! preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) {
            return null;
        }

        return array_map('hexdec', str_split($m[1], 2));
    }

    /** @param  array{0: int, 1: int, 2: int}  $rgb */
    public static function contrastWithWhite(array $rgb): float
    {
        return 1.05 / (self::luminance($rgb) + 0.05);
    }

    /** @param  array{0: int, 1: int, 2: int}  $rgb */
    private static function luminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** @param  array<int, int>  $rgb */
    private static function toHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...$rgb);
    }
}
