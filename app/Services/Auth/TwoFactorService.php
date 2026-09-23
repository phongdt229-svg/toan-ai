<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Xác thực 2 bước bằng TOTP (RFC 6238: HMAC-SHA1, 6 số, chu kỳ 30 giây) — tương thích Google
 * Authenticator, Authy, 1Password… Tự cài đặt vì thuật toán chỉ ~30 dòng, khỏi thêm phụ thuộc.
 *
 * Mọi thay đổi bật/tắt đều ghi audit log: đây là chốt chặn bảo vệ tài khoản quản trị.
 */
class TwoFactorService
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(private readonly AuditLogger $audit) {}

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && $user->two_factor_secret !== null;
    }

    /** Sinh secret mới, CHƯA bật — người dùng phải nhập đúng một mã để xác nhận (confirm). */
    public function beginSetup(User $user): string
    {
        $secret = $this->base32Encode(random_bytes(20));

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        return $secret;
    }

    public function secretOf(User $user): ?string
    {
        return $user->two_factor_secret ? decrypt($user->two_factor_secret) : null;
    }

    public function otpAuthUrl(User $user, string $secret): string
    {
        $issuer = config('site.brand', config('app.name'));

        return 'otpauth://totp/'.rawurlencode("{$issuer}:{$user->email}")
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&digits='.self::DIGITS.'&period='.self::PERIOD;
    }

    public function qrSvg(string $url): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($url);
    }

    /**
     * Xác nhận mã đầu tiên → bật thật, trả về mã dự phòng (hiện ĐÚNG MỘT LẦN).
     *
     * @return list<string>|null null nếu mã sai
     */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || ! $this->verifyCode($user, $code)) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(array_map(fn ($c) => Hash::make($c), $codes)),
        ])->save();

        $this->audit->log('auth.2fa_enabled', $user);

        return $codes;
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => encrypt(array_map(fn ($c) => Hash::make($c), $codes))])->save();
        $this->audit->log('auth.2fa_recovery_regenerated', $user);

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        $this->audit->log('auth.2fa_disabled', $user);
    }

    /** Mã TOTP hoặc mã dự phòng (dùng xong là mất). */
    public function verifyChallenge(User $user, string $input): bool
    {
        $input = trim($input);

        if (preg_match('/^\d{6}$/', str_replace(' ', '', $input))) {
            return $this->verifyCode($user, str_replace(' ', '', $input));
        }

        return $this->consumeRecoveryCode($user, $input);
    }

    /** Cho lệch ±1 chu kỳ (đồng hồ điện thoại lệch); mỗi bước chỉ được dùng một lần. */
    private function verifyCode(User $user, string $code): bool
    {
        $secret = $this->secretOf($user);

        if ($secret === null || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $now = intdiv(time(), self::PERIOD);

        foreach ([0, -1, 1] as $offset) {
            $step = $now + $offset;

            if (hash_equals($this->totp($secret, $step), $code)) {
                if ($user->two_factor_last_step !== null && $step <= (int) $user->two_factor_last_step) {
                    return false;
                }

                $user->forceFill(['two_factor_last_step' => $step])->save();

                return true;
            }
        }

        return false;
    }

    private function consumeRecoveryCode(User $user, string $input): bool
    {
        if ($user->two_factor_recovery_codes === null) {
            return false;
        }

        $hashes = decrypt($user->two_factor_recovery_codes);
        $normalized = Str::upper(trim($input));

        foreach ($hashes as $i => $hash) {
            if (Hash::check($normalized, $hash)) {
                unset($hashes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => encrypt(array_values($hashes))])->save();
                $this->audit->log('auth.2fa_recovery_used', $user);

                return true;
            }
        }

        return false;
    }

    /** @return list<string> dạng XXXXX-XXXXX, bỏ ký tự dễ nhầm (0/O, 1/I). */
    private function generateRecoveryCodes(): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return array_map(function () use ($alphabet) {
            $raw = '';
            for ($i = 0; $i < 10; $i++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            return substr($raw, 0, 5).'-'.substr($raw, 5);
        }, range(1, 8));
    }

    private function totp(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Công khai để test tính mã đúng chuẩn RFC. */
    public function codeAt(string $secret, int $timestamp): string
    {
        return $this->totp($secret, intdiv($timestamp, self::PERIOD));
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private function base32Decode(string $text): string
    {
        $bits = '';
        foreach (str_split(strtoupper($text)) as $char) {
            $pos = strpos(self::BASE32, $char);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
