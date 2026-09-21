<?php

namespace Tests\Feature\Guides;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nội dung hướng dẫn nằm trong config nên rủi ro lớn nhất là gõ sai tên route trong `links`
 * — bài sẽ nổ lỗi 500 khi người dùng mở. Test dưới đây mở TẤT CẢ các bài để bắt việc đó.
 */
class GuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_article_opens(): void
    {
        $slugs = array_keys(config('guides.articles'));
        $this->assertNotEmpty($slugs);

        foreach ($slugs as $slug) {
            $this->get(route('guides.show', $slug))
                ->assertOk()
                ->assertSee(config("guides.articles.{$slug}.title"));
        }
    }

    public function test_index_lists_articles_grouped_by_audience(): void
    {
        $this->get(route('guides.index'))
            ->assertOk()
            ->assertSee('Hướng dẫn')
            ->assertSee('Học sinh')
            ->assertSee('Giáo viên')
            ->assertSee('Phụ huynh')
            ->assertSee('Dùng AI Tutor cho đúng');
    }

    public function test_search_filters_and_ignores_accents(): void
    {
        // "giao bai" không dấu vẫn phải tìm ra bài "Tạo đề kiểm tra và giao bài".
        $this->get(route('guides.index', ['q' => 'giao bai']))
            ->assertOk()
            ->assertSee('Tạo đề kiểm tra và giao bài')
            ->assertDontSee('Dùng AI Tutor cho đúng');

        $this->get(route('guides.index', ['q' => 'khong-co-gi-khop-dau-ca']))
            ->assertOk()
            ->assertSee('0 bài viết khớp')
            ->assertSee('gửi yêu cầu hỗ trợ');
    }

    public function test_filter_by_audience(): void
    {
        $this->get(route('guides.index', ['doi-tuong' => 'parent']))
            ->assertOk()
            ->assertSee('Phụ huynh: liên kết và theo dõi con')
            // Nhóm chung (tài khoản, gói học, thanh toán) luôn đi kèm mọi vai trò.
            ->assertSee('Gói học và thanh toán MoMo')
            ->assertDontSee('Soạn bài học và chèn công thức toán');
    }

    public function test_unknown_article_returns_404(): void
    {
        $this->get(route('guides.show', 'bai-viet-khong-ton-tai'))->assertNotFound();
    }

    public function test_guide_is_linked_from_footer_and_support_form(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('guides.index'));
        $this->get(route('support.create'))->assertOk()->assertSee(route('guides.index'));
    }
}
