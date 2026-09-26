<?php

namespace Tests\Feature\Blog;

use App\Models\AuditLog;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Blog / Tin tức (kế hoạch 26/09): bài giới thiệu + khuyến mãi do admin viết, có danh mục.
 */
class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole('admin');

        return $user;
    }

    private function student(): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole('student');

        return $user;
    }

    private function category(): BlogCategory
    {
        return BlogCategory::create(['name' => 'Khuyến mãi', 'slug' => 'khuyen-mai']);
    }

    // --- Quyền: chỉ admin viết -------------------------------------------------------------

    public function test_only_admin_can_write_posts(): void
    {
        $category = $this->category();

        $this->actingAs($this->student())->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài của học sinh', 'content' => 'Nội dung dài đủ ký tự.',
            'status' => 'draft',
        ])->assertForbidden();

        $this->assertSame(0, BlogPost::count());
    }

    public function test_guest_cannot_reach_admin_screens(): void
    {
        $this->get(route('admin.blog.index'))->assertRedirect(route('login'));
    }

    // --- Tạo / sửa bài -----------------------------------------------------------------------

    public function test_admin_creates_a_published_post_with_unique_slug_and_sanitized_content(): void
    {
        $category = $this->category();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id,
            'title' => 'Ra mắt tính năng mới',
            'excerpt' => 'Tóm tắt ngắn',
            'content' => '<p>Xin chào</p><script>alert(1)</script>',
            'status' => 'published',
        ]);

        $post = BlogPost::firstOrFail();
        $response->assertRedirect(route('admin.blog.edit', $post));

        $this->assertSame('ra-mat-tinh-nang-moi', $post->slug);
        $this->assertStringNotContainsString('<script>', $post->content);
        $this->assertStringContainsString('Xin chào', $post->content);
        $this->assertSame($admin->id, $post->created_by);
        $this->assertNotNull($post->published_at);
        $this->assertTrue(AuditLog::where('action', 'blog.created')->exists());

        // Trùng tiêu đề lần hai vẫn ra slug khác nhau.
        $this->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Ra mắt tính năng mới',
            'content' => 'Bài thứ hai cùng tên.', 'status' => 'draft',
        ]);
        $this->assertSame('ra-mat-tinh-nang-moi-2', BlogPost::where('title', 'Ra mắt tính năng mới')->latest('id')->first()->slug);
    }

    public function test_editing_without_changing_status_does_not_reset_published_at(): void
    {
        $category = $this->category();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài đã xuất bản',
            'content' => 'Nội dung gốc.', 'status' => 'published',
        ]);
        $post = BlogPost::firstOrFail();
        $originalPublishedAt = $post->published_at;

        $this->travel(2)->days();
        $this->put(route('admin.blog.update', $post), [
            'blog_category_id' => $category->id, 'title' => 'Bài đã xuất bản',
            'content' => 'Nội dung sửa lại.', 'status' => 'published',
        ]);

        $this->assertTrue($post->refresh()->published_at->equalTo($originalPublishedAt));

        // Gỡ xuất bản rồi xuất bản lại → published_at đổi mới.
        $this->put(route('admin.blog.update', $post), [
            'blog_category_id' => $category->id, 'title' => 'Bài đã xuất bản',
            'content' => 'Nội dung.', 'status' => 'draft',
        ]);
        $this->assertNull($post->refresh()->published_at);

        $this->put(route('admin.blog.update', $post), [
            'blog_category_id' => $category->id, 'title' => 'Bài đã xuất bản',
            'content' => 'Nội dung.', 'status' => 'published',
        ]);
        $this->assertFalse($post->refresh()->published_at->equalTo($originalPublishedAt));
    }

    // --- Ảnh bìa -----------------------------------------------------------------------------

    public function test_cover_image_is_replaced_and_old_file_is_deleted(): void
    {
        $category = $this->category();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài có ảnh', 'content' => 'Nội dung.',
            'status' => 'draft', 'cover' => UploadedFile::fake()->image('a.jpg', 800, 450),
        ]);
        $post = BlogPost::firstOrFail();
        $firstCover = $post->cover_path;
        Storage::disk('public')->assertExists($firstCover);

        $this->put(route('admin.blog.update', $post), [
            'blog_category_id' => $category->id, 'title' => 'Bài có ảnh', 'content' => 'Nội dung.',
            'status' => 'draft', 'cover' => UploadedFile::fake()->image('b.jpg', 800, 450),
        ]);

        Storage::disk('public')->assertMissing($firstCover);
        Storage::disk('public')->assertExists($post->refresh()->cover_path);
    }

    public function test_non_image_cover_is_rejected(): void
    {
        $category = $this->category();

        $this->actingAs($this->admin())->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài', 'content' => 'Nội dung.',
            'status' => 'draft', 'cover' => UploadedFile::fake()->create('shell.jpg', 10, 'application/x-php'),
        ])->assertSessionHasErrors('cover');

        $this->assertSame(0, BlogPost::count());
    }

    public function test_deleting_a_post_removes_its_cover_file(): void
    {
        $category = $this->category();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài sẽ xoá', 'content' => 'Nội dung.',
            'status' => 'draft', 'cover' => UploadedFile::fake()->image('a.jpg', 800, 450),
        ]);
        $post = BlogPost::firstOrFail();
        $cover = $post->cover_path;

        $this->delete(route('admin.blog.destroy', $post));

        Storage::disk('public')->assertMissing($cover);
        $this->assertSame(0, BlogPost::count());
        $this->assertTrue(AuditLog::where('action', 'blog.deleted')->exists());
    }

    // --- Danh mục ----------------------------------------------------------------------------

    public function test_admin_manages_categories_and_cannot_delete_one_with_posts(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog-categories.store'), ['name' => 'Sự kiện'])
            ->assertSessionHas('status');
        $category = BlogCategory::firstOrFail();
        $this->assertSame('su-kien', $category->slug);

        $this->put(route('admin.blog-categories.update', $category), ['name' => 'Sự kiện đặc biệt']);
        $this->assertSame('Sự kiện đặc biệt', $category->refresh()->name);

        $this->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài', 'content' => 'Nội dung.', 'status' => 'draft',
        ]);

        $this->delete(route('admin.blog-categories.destroy', $category))->assertSessionHas('error');
        $this->assertDatabaseHas('blog_categories', ['id' => $category->id]);
    }

    // --- Trang công khai -----------------------------------------------------------------------

    public function test_public_page_only_shows_published_posts(): void
    {
        $category = $this->category();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài đã xuất bản', 'content' => 'Nội dung.', 'status' => 'published',
        ]);
        $this->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài nháp', 'content' => 'Nội dung.', 'status' => 'draft',
        ]);

        auth()->logout();

        $this->get(route('blog.index'))->assertOk()->assertSee('Bài đã xuất bản')->assertDontSee('Bài nháp');

        $published = BlogPost::where('title', 'Bài đã xuất bản')->firstOrFail();
        $draft = BlogPost::where('title', 'Bài nháp')->firstOrFail();

        $this->get(route('blog.show', $published))->assertOk()->assertSee('Bài đã xuất bản');
        $this->get(route('blog.show', $draft))->assertNotFound();
    }

    public function test_public_page_filters_by_category(): void
    {
        // Tên bài cố ý không trùng cụm từ nào trong phụ đề tĩnh của trang ("Bài giới thiệu sản
        // phẩm và tin khuyến mãi...") — nếu không assertDontSee ăn nhầm chính phụ đề đó.
        $intro = BlogCategory::create(['name' => 'Giới thiệu', 'slug' => 'gioi-thieu']);
        $promo = $this->category();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'blog_category_id' => $intro->id, 'title' => 'Chào mừng đến với TOÁN AI', 'content' => 'Nội dung.', 'status' => 'published',
        ]);
        $this->post(route('admin.blog.store'), [
            'blog_category_id' => $promo->id, 'title' => 'Ưu đãi tháng chín', 'content' => 'Nội dung.', 'status' => 'published',
        ]);

        auth()->logout();

        $this->get(route('blog.index', ['danh-muc' => 'khuyen-mai']))
            ->assertOk()->assertSee('Ưu đãi tháng chín')->assertDontSee('Chào mừng đến với TOÁN AI');
    }

    public function test_published_posts_are_listed_in_the_sitemap(): void
    {
        $category = $this->category();
        $this->actingAs($this->admin())->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài sitemap', 'content' => 'Nội dung.', 'status' => 'published',
        ]);
        $post = BlogPost::firstOrFail();

        $this->get('/sitemap.xml')->assertOk()->assertSee(route('blog.show', $post), false);
    }

    public function test_admin_index_shows_charts_and_summary(): void
    {
        $category = $this->category();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài A', 'content' => 'Nội dung.', 'status' => 'published',
        ]);
        $this->post(route('admin.blog.store'), [
            'blog_category_id' => $category->id, 'title' => 'Bài B', 'content' => 'Nội dung.', 'status' => 'draft',
        ]);

        $this->get(route('admin.blog.index'))->assertOk()
            ->assertSee('blog-daily', false)
            ->assertSee('count-bars', false)
            ->assertSeeText('Đã xuất bản')
            ->assertSeeText('Bản nháp');
    }
}
