<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR dạng SVG nhúng thẳng vào trang — không cần ext GD/Imagick, không gọi dịch vụ ngoài
 * (link liên kết chứa mã riêng tư của học sinh, không được gửi ra bên thứ ba để vẽ QR).
 */
final class QrCode
{
    public static function svg(string $text, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        /* Bỏ dòng khai báo XML ở đầu để nhúng inline được trong HTML.
           (Không viết thẻ đó trong comment dạng // — PHP coi nó là thẻ đóng file.) */
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $writer->writeString($text)) ?? '';
    }
}
