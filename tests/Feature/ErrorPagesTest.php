<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Trang lỗi phải hiện được cả khi hệ thống đang hỏng, nên test ở đây kiểm tra
 * cả nội dung lẫn việc view không phụ thuộc vào asset đã build (@vite).
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_url_shows_the_vietnamese_404_page(): void
    {
        $this->get('/duong-dan-khong-ton-tai')
            ->assertNotFound()
            ->assertSee('Không tìm thấy trang')
            ->assertSee('Về trang chủ');
    }

    public function test_forbidden_page_points_the_user_to_logging_in_again(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $this->actingAs($user)->get('/quan-tri')
            ->assertForbidden()
            ->assertSee('Bạn không có quyền vào đây')
            ->assertSee('Đăng nhập lại');
    }

    /** @return list<array{string}> */
    public static function errorViews(): array
    {
        return [['403'], ['404'], ['419'], ['429'], ['500'], ['503']];
    }

    #[DataProvider('errorViews')]
    public function test_error_view_renders_without_build_assets(string $code): void
    {
        $html = View::make("errors.{$code}", [
            'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException((int) $code),
        ])->render();

        $this->assertStringContainsString('<title>', $html);
        $this->assertStringNotContainsString('/build/', $html, "errors/{$code} không được phụ thuộc asset đã build");
        $this->assertStringNotContainsString('csrf-token', $html);
    }

    public function test_maintenance_page_is_reachable_by_the_down_command(): void
    {
        // `php artisan down --render="errors::503"` đăng ký namespace errors:: rồi mới render.
        (new RegisterErrorViewPaths)();
        $this->assertTrue(View::exists('errors::503'));

        $html = View::make('errors::503', ['exception' => null])->render();
        $this->assertStringContainsString('đang được nâng cấp', $html);
        $this->assertStringContainsString('Tải lại trang', $html);
    }
}
