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

    /** Hồ sơ Google Doanh nghiệp / Google Maps — chỉ link thật (cùng lưới lọc với footer). */
    public static function mapUrl(): ?string
    {
        $url = config('site.social.google');

        return SocialLink::isUsable($url) ? $url : null;
    }
}
