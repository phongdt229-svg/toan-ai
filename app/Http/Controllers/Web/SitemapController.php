<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * sitemap.xml cho các trang công khai.
 *
 * Chỉ liệt kê trang khách vãng lai xem được — KHÔNG đưa trang sau đăng nhập vào đây
 * (chúng đã gắn noindex ở layouts.app, đưa vào sitemap là mâu thuẫn tín hiệu).
 * Sinh trực tiếp, không cache file: số URL còn nhỏ và phần hướng dẫn nằm trong config.
 *
 * Bài viết có ảnh bìa khai thêm thẻ <image:image> (namespace Google Image Sitemap) để Google
 * biết ảnh nào thuộc trang nào mà không phải tự dò trong HTML — index ảnh nhanh hơn.
 *
 * <lastmod> lấy NGÀY THẬT thay vì luôn là "vừa xong": bài viết dùng `updated_at` (có sẵn, chính
 * xác tuyệt đối), trang tĩnh dùng thời gian sửa file Blade/config lần cuối (`filemtime`) — gần
 * đúng nhưng còn thật hơn nhiều so với báo "mới cập nhật" ở MỌI request. Không có ngày thật thì
 * bỏ hẳn thẻ này — sitemap thiếu lastmod vẫn hợp lệ, còn hơn đoán bừa (Google mất tin tưởng vào
 * cả sitemap nếu ngày lúc nào cũng là "bây giờ", dù nội dung tĩnh không đổi gì).
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => $this->viewMtime('public.landing')],
            ['loc' => route('packages.index'), 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $this->viewMtime('public.packages.index')],
            ['loc' => route('guides.index'), 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => $this->viewMtime('public.guides.index')],
            ['loc' => route('support.create'), 'priority' => '0.5', 'changefreq' => 'yearly', 'lastmod' => $this->viewMtime('public.support.create')],
            ['loc' => route('register'), 'priority' => '0.7', 'changefreq' => 'yearly', 'lastmod' => $this->viewMtime('auth.register-choose')],
            ['loc' => route('legal.terms'), 'priority' => '0.3', 'changefreq' => 'yearly', 'lastmod' => $this->viewMtime('public.legal.terms')],
            ['loc' => route('legal.privacy'), 'priority' => '0.3', 'changefreq' => 'yearly', 'lastmod' => $this->viewMtime('public.legal.privacy')],
        ];

        // Nội dung mọi bài hướng dẫn nằm chung 1 file config — cùng chung một lastmod hợp lý.
        $guidesLastmod = $this->fileMtime(config_path('guides.php'));

        foreach (array_keys(config('guides.articles', [])) as $slug) {
            $urls[] = ['loc' => route('guides.show', $slug), 'priority' => '0.6', 'changefreq' => 'monthly', 'lastmod' => $guidesLastmod];
        }

        $posts = BlogPost::published()->latest('published_at')->latest('id')->get(['slug', 'title', 'cover_path', 'updated_at']);

        $urls[] = [
            'loc' => route('blog.index'),
            'priority' => '0.6',
            'changefreq' => 'weekly',
            // Trang danh sách "mới" nhất khi có bài mới nhất — không có bài nào thì dùng ngày sửa view.
            'lastmod' => $posts->max('updated_at')?->toAtomString() ?? $this->viewMtime('public.blog.index'),
        ];

        foreach ($posts as $post) {
            $urls[] = [
                'loc' => route('blog.show', $post->slug),
                'priority' => '0.5',
                'changefreq' => 'monthly',
                'lastmod' => $post->updated_at->toAtomString(),
                'image' => $post->coverUrl() ? ['loc' => $post->coverUrl(), 'title' => $post->title] : null,
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url>'."\n"
                .'    <loc>'.e($url['loc']).'</loc>'."\n";

            if (! empty($url['lastmod'])) {
                $xml .= '    <lastmod>'.$url['lastmod'].'</lastmod>'."\n";
            }

            $xml .= '    <changefreq>'.$url['changefreq'].'</changefreq>'."\n"
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

    /** Ngày sửa cuối của một file Blade, dạng chuỗi cho <lastmod> — null nếu không tìm được file. */
    private function viewMtime(string $view): ?string
    {
        try {
            return $this->fileMtime(view($view)->getPath());
        } catch (\Throwable) {
            return null;
        }
    }

    private function fileMtime(string $path): ?string
    {
        $mtime = @filemtime($path);

        return $mtime ? Carbon::createFromTimestamp($mtime)->toAtomString() : null;
    }

    /**
     * sitemap-news.xml — Google News chỉ chấp nhận URL đã xuất bản trong 48 giờ gần đây (yêu cầu
     * bắt buộc, khác sitemap.xml thường không giới hạn thời gian); URL cũ hơn phải RỚT khỏi
     * sitemap này dù bài vẫn còn trên site. Có sitemap đúng chuẩn không đồng nghĩa được Google
     * News nhận — còn phải đăng ký & duyệt qua Google Publisher Center (xem PROJECT_PLAN.md).
     */
    public function news(): Response
    {
        $publication = config('site.brand', config('app.name'));

        $posts = BlogPost::published()
            ->where('published_at', '>=', now()->subHours(48))
            ->latest('published_at')->latest('id')
            ->get(['slug', 'title', 'published_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">'."\n";

        foreach ($posts as $post) {
            $xml .= '  <url>'."\n"
                .'    <loc>'.e(route('blog.show', $post->slug)).'</loc>'."\n"
                .'    <news:news>'."\n"
                .'      <news:publication>'."\n"
                .'        <news:name>'.e($publication).'</news:name>'."\n"
                .'        <news:language>vi</news:language>'."\n"
                .'      </news:publication>'."\n"
                .'      <news:publication_date>'.$post->published_at->toIso8601String().'</news:publication_date>'."\n"
                .'      <news:title>'.e($post->title).'</news:title>'."\n"
                .'    </news:news>'."\n"
                .'  </url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
