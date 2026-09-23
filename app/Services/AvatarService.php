<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ảnh đại diện. Không lưu file người dùng gửi lên mà VẼ LẠI thành JPEG vuông 256px:
 * - loại bỏ EXIF (toạ độ GPS, model máy) — người dùng là học sinh, không ai muốn lộ nhà mình qua ảnh;
 * - file không giải mã được thành ảnh thật (webshell đội lốt .png) thì bị từ chối ngay ở bước này;
 * - kích thước cố định nên không phình dung lượng ổ đĩa.
 */
class AvatarService
{
    public const SIZE = 256;

    private const DISK = 'public';

    public function store(User $user, UploadedFile $file): string
    {
        $image = $this->load($file);
        $square = $this->cropSquare($image);

        ob_start();
        imagejpeg($square, null, 85);
        $bytes = (string) ob_get_clean();

        $path = 'avatars/'.$user->id.'-'.Str::lower(Str::random(10)).'.jpg';
        Storage::disk(self::DISK)->put($path, $bytes);

        $old = $user->avatar;
        $user->forceFill(['avatar' => $path])->save();

        $this->delete($old);

        return $path;
    }

    public function remove(User $user): void
    {
        $old = $user->avatar;
        $user->forceFill(['avatar' => null])->save();
        $this->delete($old);
    }

    /** Xoá file ảnh khỏi ổ đĩa (dùng cả khi ẩn danh tài khoản). */
    public function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'avatars/')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** @return \GdImage */
    private function load(UploadedFile $file)
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

    /** Cắt vuông giữa ảnh rồi thu về SIZE×SIZE; nền trắng cho ảnh PNG/WebP có nền trong suốt. */
    private function cropSquare($image)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $side = min($w, $h);

        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $image, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);

        return $out;
    }
}
