<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Response;

/**
 * sitemap.xml cho các trang công khai.
 *
 * Chỉ liệt kê trang khách vãng lai xem được — KHÔNG đưa trang sau đăng nhập vào đây
 * (chúng đã gắn noindex ở layouts.app, đưa vào sitemap là mâu thuẫn tín hiệu).
 * Sinh trực tiếp, không cache file: số URL còn nhỏ và phần hướng dẫn nằm trong config.
 *
 * Bài viết có ảnh bìa khai thêm thẻ <image:image> (namespace Google Image Sitemap) để Google
 * biết ảnh nào thuộc trang nào mà không phải tự dò trong HTML — index ảnh nhanh hơn.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('packages.index'), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => route('guides.index'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('support.create'), 'priority' => '0.5', 'changefreq' => 'yearly'],
            ['loc' => route('register'), 'priority' => '0.7', 'changefreq' => 'yearly'],
            ['loc' => route('legal.terms'), 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['loc' => route('legal.privacy'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        foreach (array_keys(config('guides.articles', [])) as $slug) {
            $urls[] = ['loc' => route('guides.show', $slug), 'priority' => '0.6', 'changefreq' => 'monthly'];
        }

        $urls[] = ['loc' => route('blog.index'), 'priority' => '0.6', 'changefreq' => 'weekly'];

        foreach (BlogPost::published()->latest('published_at')->latest('id')->get(['slug', 'title', 'cover_path']) as $post) {
            $urls[] = [
                'loc' => route('blog.show', $post->slug),
                'priority' => '0.5',
                'changefreq' => 'monthly',
                'image' => $post->coverUrl() ? ['loc' => $post->coverUrl(), 'title' => $post->title] : null,
            ];
        }

        $lastmod = now()->toAtomString();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url>'."\n"
                .'    <loc>'.e($url['loc']).'</loc>'."\n"
                .'    <lastmod>'.$lastmod.'</lastmod>'."\n"
                .'    <changefreq>'.$url['changefreq'].'</changefreq>'."\n"
                .'    <priority>'.$url['priority'].'</priority>'."\n";

            if (! empty($url['image'])) {
                $xml .= '    <image:image>'."\n"
                    .'      <image:loc>'.e($url['image']['loc']).'</image:loc>'."\n"
                    .'      <image:title>'.e($url['image']['title']).'</image:title>'."\n"
                    .'    </image:image>'."\n";
            }

            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
