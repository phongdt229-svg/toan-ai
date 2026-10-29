<?php

namespace App\Services\Content;

use App\Models\BlogPost;
use App\Models\Package;
use App\Support\LandingFaq;
use Illuminate\Support\Str;

/**
 * /llms.txt (chuẩn llmstxt.org): bản tóm tắt Markdown cho công cụ AI (ChatGPT, Claude, Perplexity…)
 * đọc nhanh site là gì và trang nào đáng trích dẫn — GEO, cùng vai trò sitemap với công cụ tìm kiếm.
 *
 * Mọi thứ sinh từ dữ liệu THẬT (config hướng dẫn, giá trong bảng packages, bài đã xuất bản, FAQ dùng
 * chung với trang chủ) — cùng luật "chỉ nói thứ sản phẩm có thật" như hero: AI sẽ nhắc lại nguyên văn
 * những gì ở đây cho phụ huynh, viết tay là sớm muộn lệch giá/tính năng.
 */
class LlmsTxtService
{
    /** Bài tin tức mới nhất đưa vào — đủ để AI biết site còn hoạt động, không biến file thành bản sao blog. */
    public const RECENT_POSTS = 20;

    /** Bài học liệt kê tối đa — đủ để AI thấy phạm vi chương trình, phần còn lại có ở sitemap và trang từng lớp. */
    public const MAX_LESSONS = 200;

    public function __construct(private SeoPageService $seo, private PublicCurriculumService $curriculum) {}

    public function render(): string
    {
        $brand = config('site.brand');
        $aliases = self::alternateNames();
        $summary = $this->seo->descriptionFor('home')
            ?: 'Học Toán lớp '.config('learning.grade_min').'–'.config('learning.grade_max')
                .' theo đúng chương trình: bài giảng, luyện tập chấm ngay, đề kiểm tra và AI giảng lại từng bước khi làm sai.';

        $lines = [
            '# '.$brand,
            '',
            '> '.$this->oneLine($summary),
            '',
            $brand.($aliases ? ' (còn gọi là '.implode(', ', $aliases).')' : '')
                .' — '.parse_url(url('/'), PHP_URL_HOST).' — là nền tảng học Toán trực tuyến bằng tiếng Việt cho học sinh lớp '
                .config('learning.grade_min').' đến lớp '.config('learning.grade_max').', bám sát chương trình phổ thông Việt Nam. '
                .'Có ba vai trò: học sinh (học bài, luyện tập, làm đề, hỏi AI Tutor), giáo viên (lớp học, giao bài, ngân hàng câu hỏi) '
                .'và phụ huynh (theo dõi tiến độ, nhận báo cáo tuần). AI Tutor gợi ý từng bước chứ không đưa sẵn đáp án, '
                .'và bị khoá trong lúc học sinh làm bài kiểm tra. Bắt đầu miễn phí, không cần thẻ.',
            '',
            '## Trang chính',
            '',
            $this->link('Trang chủ', route('home'), 'giới thiệu sản phẩm, chương trình lớp 1–12, câu hỏi thường gặp'),
            $this->link('Chương trình học', route('curriculum.index'), 'danh sách bài theo lớp, chương, chủ đề'),
            $this->link('Gói học và bảng giá', route('packages.index')),
            $this->link('Trung tâm hướng dẫn', route('guides.index'), 'cách dùng cho học sinh, giáo viên, phụ huynh'),
            $this->link('Đăng ký miễn phí', route('register')),
            $this->link('Hỗ trợ', route('support.create'), 'gửi câu hỏi hoặc báo lỗi'),
            '',
            ...$this->curriculumLines(),
            ...$this->packages(),
            ...$this->guides(),
            ...$this->faq(),
            ...$this->posts(),
            '## Optional',
            '',
            $this->link('Điều khoản sử dụng', route('legal.terms')),
            $this->link('Chính sách bảo mật', route('legal.privacy')),
            $this->link('Sơ đồ trang (sitemap)', route('sitemap')),
            $this->link('RSS tin tức', route('blog.feed')),
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * Bài học theo lớp — chỉ tên + tóm tắt + link trang tổng quan (nội dung bài phải đăng nhập, spec §6).
     *
     * @return list<string>
     */
    private function curriculumLines(): array
    {
        $lessons = $this->curriculum->sitemapLessons()->take(self::MAX_LESSONS);

        if ($lessons->isEmpty()) {
            return [];
        }

        $lines = ['## Chương trình học', '', 'Nội dung bài học cần đăng nhập; trang dưới đây mô tả bài gồm những phần nào.', ''];

        foreach ($lessons->groupBy('grade_slug') as $gradeSlug => $gradeLessons) {
            $course = 'Toán '.mb_strtolower($gradeLessons->first()->grade_name);
            $lines[] = '### '.$course;
            $lines[] = '';
            $lines[] = $this->link('Chương trình '.$course, route('curriculum.grade', $gradeSlug));

            foreach ($gradeLessons as $lesson) {
                $lines[] = $this->link($lesson->title, route('curriculum.lesson', [$gradeSlug, $lesson->slug]), $lesson->summary ?: null);
            }

            $lines[] = '';
        }

        return $lines;
    }

    /** @return list<string> */
    private function packages(): array
    {
        $lines = ['## Gói học', '', 'Giá lấy từ hệ thống tại thời điểm đọc file này; trang Gói học là nguồn cuối cùng.', ''];

        foreach (Package::catalog() as $tier) {
            $prices = $tier['packages']
                ->map(fn (Package $p) => $p->priceLabel().($p->isFree() || ! $p->durationLabel() ? '' : ' / '.$p->durationLabel()))
                ->implode(', ');
            $features = $tier['features']->pluck('label')->filter()->implode('; ');

            $lines[] = '- '.$tier['label'].': '.$prices.($features !== '' ? ' — '.$this->oneLine($features) : '');
        }

        return [...$lines, ''];
    }

    /** @return list<string> */
    private function guides(): array
    {
        $lines = ['## Hướng dẫn sử dụng', ''];
        $audiences = config('guides.audiences', []);

        foreach (collect(config('guides.articles', []))->groupBy('audience', true) as $audience => $articles) {
            $lines[] = '### '.($audiences[$audience]['label'] ?? $audience);
            $lines[] = '';

            foreach ($articles as $slug => $article) {
                $lines[] = $this->link($article['title'], route('guides.show', $slug), $article['summary'] ?? null);
            }

            $lines[] = '';
        }

        return $lines;
    }

    /** @return list<string> */
    private function faq(): array
    {
        $lines = ['## Câu hỏi thường gặp', ''];

        foreach (LandingFaq::items() as [$question, $answer]) {
            $lines[] = '### '.$question;
            $lines[] = '';
            $lines[] = $answer;
            $lines[] = '';
        }

        return $lines;
    }

    /** @return list<string> */
    private function posts(): array
    {
        $posts = BlogPost::published()->latest('published_at')->latest('id')
            ->limit(self::RECENT_POSTS)->get(['title', 'slug', 'excerpt', 'content']);

        if ($posts->isEmpty()) {
            return [];
        }

        $lines = ['## Tin tức và bài viết', ''];

        foreach ($posts as $post) {
            $lines[] = $this->link($post->title, route('blog.show', $post->slug), $post->excerpt ?: Str::limit(strip_tags((string) $post->content), 160));
        }

        return [...$lines, ''];
    }

    /**
     * Tên phụ của sản phẩm, bỏ tên trùng tên chính — dùng chung cho JSON-LD (layouts/base) và file này.
     *
     * @return list<string>
     */
    public static function alternateNames(): array
    {
        $brand = mb_strtolower((string) config('site.brand'));

        return array_values(array_unique(array_filter(
            (array) config('site.alternate_names', []),
            fn ($name) => $name !== '' && mb_strtolower($name) !== $brand,
        )));
    }

    private function link(string $title, string $url, ?string $note = null): string
    {
        // Ngoặc vuông trong tiêu đề sẽ làm vỡ cú pháp link Markdown.
        $title = str_replace(['[', ']'], ['(', ')'], $this->oneLine($title));

        return '- ['.$title.']('.$url.')'.($note ? ': '.$this->oneLine($note) : '');
    }

    /** Nội dung admin gõ có thể xuống dòng — một mục danh sách Markdown phải nằm trên một dòng. */
    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
