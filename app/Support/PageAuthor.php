<?php

namespace App\Support;

/**
 * Tác giả của các trang giới thiệu (trang chủ, Học Toán với AI, chương trình) — E-E-A-T.
 * Có người thật trong config thì khai Person, không thì Organization; bài tin tức có byline riêng
 * (`blog_posts.byline_name`) nên không đi qua đây.
 */
class PageAuthor
{
    public static function name(): string
    {
        return config('site.editor_name') ?: 'Đội ngũ biên soạn '.config('site.brand');
    }

    public static function title(): ?string
    {
        return config('site.editor_name') ? (config('site.editor_title') ?: null) : null;
    }

    /** Giá trị `author` cho JSON-LD. */
    public static function schema(): array
    {
        if (! config('site.editor_name')) {
            return ['@id' => url('/').'#organization'];
        }

        return array_filter([
            '@type' => 'Person',
            'name' => config('site.editor_name'),
            'jobTitle' => config('site.editor_title') ?: null,
            'worksFor' => ['@id' => url('/').'#organization'],
        ]);
    }
}
