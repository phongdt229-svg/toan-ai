<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Nội dung lesson do giáo viên soạn được render bằng {!! !!}, nên bắt buộc
 * phải lọc trước khi LƯU (không lọc lúc hiển thị — lọc một lần, ở cửa vào).
 *
 * Cú pháp KaTeX ($...$, $$...$$) là text thuần nên đi qua purifier nguyên vẹn.
 */
class HtmlSanitizer
{
    private const ALLOWED = 'p,br,strong,b,em,i,u,s,sub,sup,'
        .'h2,h3,h4,ul,ol,li,blockquote,hr,'
        .'table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],'
        .'a[href|title|rel],img[src|alt|width|height],'
        .'code,pre,span[class],div[class]';

    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.AutoParagraph', false);
        // Chặn javascript:, data: trong href/src.
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        $config->set('Attr.AllowedRel', ['nofollow', 'noopener', 'noreferrer']);
        $config->set('Cache.SerializerPath', storage_path('app/purifier'));

        if (! is_dir(storage_path('app/purifier'))) {
            mkdir(storage_path('app/purifier'), 0775, true);
        }

        $this->purifier = new HTMLPurifier($config);
    }

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return $this->purifier->purify($html);
    }
}
