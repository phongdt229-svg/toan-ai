<?php

namespace Tests\Feature\Student;

use App\Support\HtmlSanitizer;
use Tests\TestCase;

/**
 * Hình 3D nhúng trong nội dung là token văn bản `[[hinh:BASE64]]` — phải sống sót qua HtmlSanitizer
 * (lọc lúc LƯU) mà không cần mở thêm thẻ/thuộc tính nào.
 */
class GeometryEmbedTest extends TestCase
{
    public function test_token_hinh_di_qua_sanitizer_nguyen_ven(): void
    {
        $b64 = base64_encode(json_encode(['type' => '3d', 'shape' => 'box', 'dims' => ['a' => 4, 'b' => 3, 'c' => 2]]));
        $html = "<p>Cho hình hộp:</p><p>[[hinh:{$b64}]]</p><p>Tính \$V\$.</p><script>alert(1)</script>";

        $clean = app(HtmlSanitizer::class)->clean($html);

        $this->assertStringContainsString("[[hinh:{$b64}]]", $clean);
        $this->assertStringNotContainsString('<script', $clean);
    }

    public function test_khung_cong_cu_3d_tai_duoc_khong_can_dang_nhap(): void
    {
        $this->get(route('geometry.solid-markup'))
            ->assertOk()
            ->assertSee('data-solid-geometry', false)
            ->assertSee('data-edit-only', false);
    }
}
