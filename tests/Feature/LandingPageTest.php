<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\Content\BlogService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_all_sections(): void
    {
        $this->seed(GradeSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Học Toán thông minh cùng AI')
            ->assertSee('Bắt đầu học miễn phí')
            ->assertSee('Lớp 12')
            ->assertSee('Vui lòng đăng nhập để bắt đầu học.');
    }

    public function test_the_feature_list_keeps_up_with_what_was_actually_shipped(): void
    {
        // Trang chủ lặng lẽ tụt lại sau sản phẩm là chuyện rất dễ xảy ra: tính năng làm xong
        // nhưng người vào xem không biết là có. Test này giữ ba mục mới nhất có mặt ở đó.
        // Mặt còn lại của luật "chỉ quảng cáo thứ có thật" — xem FooterSocialLinksTest.
        $this->get('/')->assertOk()
            ->assertSee('Hỏi đáp')
            ->assertSee('Lộ trình riêng')
            // "Cài như ứng dụng" đổi từ thẻ nhỏ trong lưới tính năng sang section riêng nổi bật
            // (public.partials.pwa) — canh đúng nội dung của section đó, không phải thẻ cũ.
            ->assertSee('Cài đặt ứng dụng')
            ->assertSee('Cài TOÁN AI');
    }

    public function test_pwa_install_button_markup_is_present_and_only_the_homepage_loads_its_script(): void
    {
        // Nút hiện ngay bằng JS (pwa-install.js) trên hầu hết trình duyệt — HTML chỉ cần đủ id/nội
        // dung để JS tìm thấy; d-none trong markup là để không loé ra một nhịp trước khi JS chạy.
        $this->get('/')->assertOk()
            ->assertSee('id="pwa-install-btn"', false)
            ->assertSee('Cài đặt ứng dụng')
            ->assertSee('pwa-install', false);

        // Trang khác không nạp entry riêng này — Vite tách file để không cõng thêm JS vào mọi trang.
        $this->seed(GradeSeeder::class);
        $this->get(route('login'))->assertOk()->assertDontSee('pwa-install', false);
    }

    public function test_news_section_shows_published_posts_but_not_drafts_and_hides_when_empty(): void
    {
        $this->seed([GradeSeeder::class, RolePermissionSeeder::class]);

        // Chưa có bài nào — section ẩn hẳn, không quảng cáo mục trống.
        $this->get('/')->assertOk()->assertDontSee('id="tin-tuc"', false);

        $category = BlogCategory::create(['name' => 'Giới thiệu', 'slug' => 'gioi-thieu']);
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN);
        $blog = app(BlogService::class);

        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài đã xuất bản trên trang chủ', 'content' => 'Nội dung.', 'status' => 'published'], $admin);
        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài nháp không nên lộ', 'content' => 'Nội dung.', 'status' => 'draft'], $admin);

        $this->get('/')->assertOk()
            ->assertSee('id="tin-tuc"', false)
            ->assertSee('Bài đã xuất bản trên trang chủ')
            ->assertDontSee('Bài nháp không nên lộ');
    }

    public function test_news_section_shows_at_most_six_latest_posts(): void
    {
        $this->seed([GradeSeeder::class, RolePermissionSeeder::class]);

        $category = BlogCategory::create(['name' => 'Giới thiệu', 'slug' => 'gioi-thieu']);
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN);
        $blog = app(BlogService::class);

        foreach (range(1, 7) as $i) {
            $blog->create(['blog_category_id' => $category->id, 'title' => "Bài số {$i}", 'content' => 'Nội dung.', 'status' => 'published'], $admin);
        }

        $response = $this->get('/')->assertOk();
        $response->assertDontSee('Bài số 1</h3>', false); // cũ nhất, rớt khỏi top 6 mới nhất
        $response->assertSee('Bài số 7</h3>', false);
    }

    public function test_the_install_button_is_hidden_until_the_browser_says_it_is_installable(): void
    {
        // Nút gửi xuống ở trạng thái `hidden`; install-app.js mới bỏ `hidden` khi trình duyệt
        // bắn beforeinstallprompt. Safari iPhone không bắn nên nút không bao giờ hiện ở đó —
        // hiện nút chết thì bấm vào không có gì xảy ra.
        $this->get('/')->assertOk()
            ->assertSee('data-install-app hidden', false)
            ->assertSee('Cài ứng dụng');
    }

    public function test_the_faq_answers_the_questions_a_paying_parent_asks(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Hỏi thường gặp')
            ->assertSee('Không trả tiền thì dùng được gì?')
            ->assertSee('AI có làm bài hộ con không?');
    }

    public function test_the_faq_structured_data_matches_what_is_on_screen(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Trang có nhiều khối JSON-LD (khối @graph chung của site ở layouts/base đứng trước) — tìm đúng khối FAQ.
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $data = collect($m[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', 'FAQPage');
        $this->assertNotNull($data, 'Thiếu dữ liệu có cấu trúc FAQ cho Google.');

        // Google phạt trang khai một đằng hiện một nẻo — mọi câu khai báo phải có trên màn hình.
        foreach ($data['mainEntity'] as $entry) {
            $this->assertStringContainsString($entry['name'], $html);
            $this->assertNotEmpty($entry['acceptedAnswer']['text']);
        }
    }

    public function test_login_and_register_pages_render(): void
    {
        $this->seed(GradeSeeder::class);

        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk()->assertSee('Bạn là ai?');
        $this->get(route('register.student'))->assertOk();
        $this->get(route('register.teacher'))->assertOk();
        $this->get(route('register.parent'))->assertOk();
    }
}
