<?php

namespace Tests\Feature\Student;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Công cụ vẽ hình học phẳng (2D) tương tác — thuần tự học, không có gì để chấm nên không cần
 * Policy/Form Request. PHPUnit chỉ kiểm được khung trang server render; việc JSXGraph dựng/đo
 * đúng phải kiểm tay bằng Playwright (xem ghi chú trong plane-geometry-widget.js).
 */
class PlaneGeometryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('student.plane-geometry.index'))->assertRedirect(route('login'));
    }

    public function test_student_sees_all_tools_and_the_canvas(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole(Role::STUDENT);

        $response = $this->actingAs($student)->get(route('student.plane-geometry.index'))->assertOk();

        foreach (['point', 'segment', 'triangle', 'circle', 'angle'] as $mode) {
            $response->assertSee('data-mode="'.$mode.'"', false);
        }

        $response->assertSee('data-plane-canvas', false)
            ->assertSee('data-plane-color', false)
            ->assertSee('data-plane-results', false)
            ->assertSee('data-mode-hint', false)
            ->assertSee('data-clear-all', false);
    }

    public function test_only_students_can_open_the_page(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole(Role::TEACHER);

        $this->actingAs($teacher)->get(route('student.plane-geometry.index'))->assertForbidden();
    }
}
