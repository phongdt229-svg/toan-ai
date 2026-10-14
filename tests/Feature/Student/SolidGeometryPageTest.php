<?php

namespace Tests\Feature\Student;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Công cụ hình học không gian — nhúng GeoGebra 3D, thuần tự học, không có gì để chấm nên không cần
 * Policy/Form Request. PHPUnit chỉ kiểm được khung trang server render; việc GeoGebra dựng hình đúng
 * số liệu phải kiểm tay (sandbox CI/dev chặn DNS geogebra.org — xem PROJECT_PLAN.md mục công cụ này).
 */
class SolidGeometryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('student.solid-geometry.index'))->assertRedirect(route('login'));
    }

    public function test_student_sees_all_seven_shapes_and_the_canvas(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole(Role::STUDENT);

        $response = $this->actingAs($student)->get(route('student.solid-geometry.index'))->assertOk();

        foreach (['box', 'cube', 'pyramid3', 'pyramid4', 'cylinder', 'cone', 'sphere'] as $shape) {
            $response->assertSee('data-shape="'.$shape.'"', false);
        }

        $response->assertSee('data-solid-canvas', false)
            ->assertSee('data-solid-inputs', false)
            ->assertSee('data-solid-results', false)
            ->assertSee('https://www.geogebra.org/apps/deployggb.js', false);
    }

    public function test_only_students_can_open_the_page(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole(Role::TEACHER);

        $this->actingAs($teacher)->get(route('student.solid-geometry.index'))->assertForbidden();
    }
}
