<?php

namespace App\Support;

/**
 * Chuỗi VietQR (chuẩn EMVCo QR của NAPAS) cho chuyển khoản nhanh 24/7 — mọi app ngân hàng Việt Nam quét được,
 * tự điền sẵn ngân hàng, số tài khoản, số tiền và nội dung.
 *
 * Tự dựng thay vì gọi img.vietqr.io: không đưa mã đơn + số tiền của khách ra dịch vụ bên thứ ba,
 * và trang thanh toán không chết theo dịch vụ đó.
 */
final class VietQr
{
    /** Mã định danh dịch vụ NAPAS (GUID) và loại dịch vụ "chuyển nhanh tới tài khoản". */
    private const NAPAS_GUID = 'A000000727';

    private const SERVICE_TO_ACCOUNT = 'QRIBFTTA';

    public static function payload(string $bankBin, string $accountNo, int $amount, string $content): string
    {
        $beneficiary = self::field('00', $bankBin).self::field('01', $accountNo);
        $merchant = self::field('00', self::NAPAS_GUID).self::field('01', $beneficiary).self::field('02', self::SERVICE_TO_ACCOUNT);

        $data = self::field('00', '01')            // phiên bản payload
            .self::field('01', '12')               // QR động: dùng cho một giao dịch, có số tiền
            .self::field('38', $merchant)
            .self::field('53', '704')              // VND
            .self::field('54', (string) $amount)
            .self::field('58', 'VN')
            .self::field('62', self::field('08', self::cleanContent($content)));

        // CRC tính trên toàn chuỗi kể cả "6304" của chính trường CRC (quy định EMVCo).
        $data .= '6304';

        return $data.self::crc16($data);
    }

    /** Nội dung chuyển khoản: app ngân hàng chỉ nhận chữ/số không dấu, tối đa 25 ký tự. */
    public static function cleanContent(string $content): string
    {
        return substr(preg_replace('/[^A-Za-z0-9 ]/', '', $content) ?? '', 0, 25);
    }

    private static function field(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /** CRC-16/CCITT-FALSE (đa thức 0x1021, khởi tạo 0xFFFF) — đúng thuật toán EMVCo yêu cầu. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
