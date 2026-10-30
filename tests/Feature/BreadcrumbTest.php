<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BreadcrumbList (schema.org) — Google hiện "hoctoanonline.com › Tin tức › …" thay cho URL trần.
 * Mỗi trang công khai khai ĐÚNG MỘT lần, và tên từng cấp phải trùng thanh breadcrumb hiện trên trang.
 */
class BreadcrumbTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    public static function pages(): array
    {
        return [
            'gói học' => ['packages.index', ['Trang chủ', 'Gói học']],
            'hướng dẫn' => ['guides.index', ['Trang chủ', 'Hướng dẫn']],
            'tin tức' => ['blog.index', ['Trang chủ', 'Tin tức']],
            'chương trình' => ['curriculum.index', ['Trang chủ', 'Chương trình']],
            'học với AI' => ['ai-learning', ['Trang chủ', 'Học Toán với AI']],
            'hỗ trợ' => ['support.create', ['Trang chủ', 'Hỗ trợ']],
            'điều khoản' => ['legal.terms', ['Trang chủ', 'Điều khoản sử dụng']],
            'bảo mật' => ['legal.privacy', ['Trang chủ', 'Chính sách bảo mật']],
        ];
    }

    /** @dataProvider pages */
    public function test_public_page_declares_one_breadcrumb_matching_what_is_shown(string $route, array $names): void
    {
        $html = $this->get(route($route))->assertOk()->getContent();

        $this->assertSame($names, $this->schemaNames($html));
        $this->assertSame($names, $this->visibleNames($html));
    }

    public function test_blog_category_filter_adds_a_level(): void
    {
        $category = BlogCategory::create(['name' => 'Khuyến mãi', 'slug' => 'khuyen-mai']);

        $html = $this->get(route('blog.index', ['danh-muc' => $category->slug]))->getContent();

        $this->assertSame(['Trang chủ', 'Tin tức', 'Khuyến mãi'], $this->schemaNames($html));
        $this->assertSame(['Trang chủ', 'Tin tức', 'Khuyến mãi'], $this->visibleNames($html));
    }

    public function test_blog_post_shows_the_same_trail_it_declares(): void
    {
        $category = BlogCategory::create(['name' => 'Giới thiệu', 'slug' => 'gioi-thieu']);
        $post = BlogPost::create([
            'blog_category_id' => $category->id, 'title' => 'AI Tutor hoạt động thế nào', 'slug' => 'ai-tutor',
            'content' => '<p>Nội dung</p>', 'status' => 'published', 'published_at' => now(),
        ]);

        $html = $this->get(route('blog.show', $post->slug))->assertOk()->getContent();

        $expected = ['Trang chủ', 'Tin tức', 'Giới thiệu', 'AI Tutor hoạt động thế nào'];
        $this->assertSame($expected, $this->schemaNames($html));
        $this->assertSame($expected, $this->visibleNames($html));
    }

    public function test_every_level_links_to_an_absolute_url(): void
    {
        $html = $this->get(route('packages.index'))->getContent();

        foreach ($this->breadcrumbs($html)[0]['itemListElement'] as $i => $item) {
            $this->assertSame($i + 1, $item['position']);
            $this->assertStringStartsWith(url('/'), $item['item']);
        }
    }

    /** @return list<array> mọi node BreadcrumbList, dù khai riêng hay nằm trong @graph */
    private function breadcrumbs(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return collect($m[1])
            ->flatMap(function ($json) {
                $data = json_decode(trim($json), true) ?? [];

                return $data['@graph'] ?? [$data];
            })
            ->where('@type', 'BreadcrumbList')
            ->values()
            ->all();
    }

    private function schemaNames(string $html): array
    {
        $lists = $this->breadcrumbs($html);
        $this->assertCount(1, $lists, 'Trang phải khai đúng một BreadcrumbList');

        return array_column($lists[0]['itemListElement'], 'name');
    }

    private function visibleNames(string $html): array
    {
        preg_match('#<ol class="breadcrumb[^"]*">(.*?)</ol>#s', $html, $m);
        preg_match_all('#<li class="breadcrumb-item[^"]*"[^>]*>(.*?)</li>#s', $m[1] ?? '', $items);

        return array_map(fn ($item) => html_entity_decode(trim(strip_tags($item))), $items[1]);
    }
}
