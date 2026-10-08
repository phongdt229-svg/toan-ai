<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Giải mã ảnh người dùng gửi lên thành GdImage. Dùng chung cho ảnh đại diện và ảnh đề bài:
 * file không giải mã được thành ảnh thật (webshell đội lốt .png) bị từ chối ngay ở đây.
 */
class ImageLoader
{
    public static function fromUpload(UploadedFile $file): \GdImage
    {
        $info = @getimagesize($file->getRealPath());
        $loader = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            default => null,
        };

        $image = ($loader && function_exists($loader)) ? @$loader($file->getRealPath()) : false;

        if (! $image) {
            throw new RuntimeException('Không đọc được ảnh này. Hãy dùng file JPG, PNG hoặc WebP hợp lệ.');
        }

        return $image;
    }

    /**
     * Thu nhỏ cho cạnh dài ≤ $maxSide (không phóng to), nền trắng cho ảnh trong suốt, xuất JPEG.
     * Vẽ lại = mất toàn bộ EXIF (vị trí GPS, máy chụp).
     */
    public static function toJpeg(\GdImage $image, int $maxSide, int $quality = 85): string
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($out, null, $quality);

        return (string) ob_get_clean();
    }
}
