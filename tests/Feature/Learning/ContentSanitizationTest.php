<?php

namespace Tests\Feature\Learning;

use App\Models\LessonSection;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, SampleCurriculumSeeder::class]);
    }

    public function test_script_tags_are_stripped_on_save(): void
    {
        $section = LessonSection::first();

        $section->update([
            'content' => '<p>An toàn</p><script>alert(1)</script><img src="x" onerror="alert(2)">',
        ]);

        $stored = $section->fresh()->content;

        $this->assertStringContainsString('An toàn', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
    }

    public function test_javascript_href_is_removed(): void
    {
        $section = LessonSection::first();
        $section->update(['content' => '<p><a href="javascript:alert(1)">bấm</a></p>']);

        $this->assertStringNotContainsString('javascript:', $section->fresh()->content);
    }

    public function test_katex_syntax_survives_sanitizing(): void
    {
        $section = LessonSection::first();
        $section->update(['content' => '<p>$$\frac{1}{2} + \frac{1}{3} = \frac{5}{6}$$</p>']);

        $stored = $section->fresh()->content;

        $this->assertStringContainsString('\frac{1}{2}', $stored);
        $this->assertStringContainsString('$$', $stored);
    }
}
