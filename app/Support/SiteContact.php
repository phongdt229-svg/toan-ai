<?php

namespace App\Support;

/**
 * Thông tin liên hệ của đơn vị vận hành dạng schema.org — Local SEO.
 *
 * Công cụ tìm kiếm và công cụ AI so khớp NAP (tên – địa chỉ – điện thoại) giữa website, hồ sơ Google Doanh
 * nghiệp và các trang khác: lệch nhau là thực thể bị tách đôi. Vì vậy mọi chỗ hiện liên hệ (JSON-LD, footer,
 * llms.txt) lấy từ ĐÂY, cùng một nguồn `config/site.php`. Thiếu gì thì bỏ trường đó — không đoán, không bịa.
 */
class SiteContact
{
    /**
     * Số điện thoại dạng quốc tế (+84…) cho JSON-LD và link `tel:` — Google khuyên dạng này để nút gọi trên
     * kết quả tìm kiếm chạy được cả khi người xem ở ngoài Việt Nam. Hiển thị cho người đọc vẫn giữ nguyên
     * cách viết trong `.env` (0912 345 678 dễ đọc hơn).
     */
    public static function telephone(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) config('site.hotline'));

        if ($digits === '') {
            return null;
        }

        // 1900/1800 là đầu số tổng đài nội địa, gọi từ nước ngoài không được — giữ nguyên, không gắn +84.
        if (preg_match('/^1[89]00/', $digits)) {
            return $digits;
        }

        return '+84'.preg_replace('/^(84|0)/', '', $digits);
    }

    /** PostalAddress khi có địa chỉ; `addressCountry` cố định VN vì sản phẩm chỉ phục vụ trong nước. */
    public static function postalAddress(): ?array
    {
        if (! config('site.address')) {
            return null;
        }

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => config('site.address'),
            'addressLocality' => config('site.address_locality') ?: null,
            'addressRegion' => config('site.address_region') ?: null,
            'postalCode' => config('site.postal_code') ?: null,
            'addressCountry' => 'VN',
        ]);
    }

    /** Kênh hỗ trợ khách hàng — tiếng Việt, phục vụ Việt Nam. Cần ít nhất email hoặc số điện thoại. */
    public static function contactPoint(): ?array
    {
        $telephone = self::telephone();
        $email = config('site.email');

        if (! $telephone && ! $email) {
            return null;
        }

        return array_filter([
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'telephone' => $telephone,
            'email' => $email ?: null,
            'areaServed' => 'VN',
            'availableLanguage' => ['Vietnamese'],
        ]);
    }

    /**
     * Trang hồ sơ "Đã thông báo Bộ Công Thương" — chỉ nhận link về online.gov.vn: huy hiệu này là cam kết
     * pháp lý, link chép nhầm sang miền khác (hay link mẫu) còn tệ hơn không gắn.
     */
    public static function moitUrl(): ?string
    {
        // Công tắc tắt thì coi như chưa có link: mọi chỗ hiện huy hiệu đều đi qua hàm này.
        if (! config('site.moit_badge')) {
            return null;
        }

        $url = (string) config('site.moit_url');
        $parts = parse_url($url);

        $ok = $parts
            && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && in_array(strtolower($parts['host'] ?? ''), ['online.gov.vn', 'www.online.gov.vn'], true)
            && trim($parts['path'] ?? '', '/') !== '';

        return $ok ? $url : null;
    }

    /**
     * Khối "giới thiệu công ty" ở chân trang — dùng chung cho footer các trang công khai và footer trang chủ V6,
     * để thông tin pháp nhân không lệch nhau giữa hai nơi (Bộ Công Thương đối chiếu từng chữ với hồ sơ).
     *
     * @return array{name: string, registration: ?string, head_office: ?string, office: ?string}|null
     */
    public static function companyDetails(): ?array
    {
        $name = trim((string) config('site.company'));
        $tax = trim((string) config('site.company_tax_code'));
        if ($name === '' || $tax === '') {
            // Chưa khai mã số doanh nghiệp thì không dựng khối pháp nhân nửa vời — footer giữ dòng © như cũ.
            return null;
        }

        $registration = 'Mã số doanh nghiệp: '.$tax;
        $date = trim((string) config('site.company_license_date'));
        $place = trim((string) config('site.company_license_place'));
        if ($date !== '') {
            $registration .= ', cấp lần đầu ngày '.$date;
        }
        if ($place !== '') {
            $registration .= ' tại '.$place;
        }

        $office = trim((string) config('site.office_address'));
        if ($office !== '') {
            // .env có thể đã ghi kèm tên văn phòng ("Tây Nguyên: 42/10 …") → "Văn phòng Tây Nguyên: …".
            $office = str_contains($office, ':') ? 'Văn phòng '.$office : 'Văn phòng: '.$office;
        }

        return [
            'name' => $name,
            'registration' => $registration,
            'head_office' => trim((string) config('site.address')) !== '' ? 'Trụ sở: '.trim((string) config('site.address')) : null,
            'office' => $office !== '' ? $office : null,
        ];
    }

    /** Hồ sơ Google Doanh nghiệp / Google Maps — chỉ link thật (cùng lưới lọc với footer). */
    public static function mapUrl(): ?string
    {
        $url = config('site.social.google');

        return SocialLink::isUsable($url) ? $url : null;
    }
}
