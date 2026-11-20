<?php

namespace Tests\Feature\Student;

use App\Models\Grade;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Công cụ hình học gắn vào trang Học của lớp phù hợp, không còn ở menu trái (yêu cầu 19/11). */
class GeometryToolsPlacementTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole(Role::STUDENT);
    }

    private function learnPage(int $level)
    {
        return $this->actingAs($this->student)
            ->get(route('student.learn.index', ['grade' => Grade::where('level', $level)->value('slug')]))
            ->assertOk();
    }

    public function test_tools_follow_the_grade(): void
    {
        $plane = route('student.plane-geometry.index');
        $solid = route('student.solid-geometry.index');

        $this->learnPage(3)->assertDontSee('data-geometry-tools', false);
        $this->learnPage(7)->assertSee($plane, false)->assertDontSee($solid, false);
        $this->learnPage(9)->assertSee($plane, false)->assertSee($solid, false);
        $this->learnPage(10)->assertSee($plane, false)->assertDontSee($solid, false);
        $this->learnPage(12)->assertDontSee($plane, false)->assertSee($solid, false);
    }

    public function test_tools_are_no_longer_in_the_sidebar(): void
    {
        $labels = array_column(config('navigation.student'), 'route');

        $this->assertNotContains('student.plane-geometry.index', $labels);
        $this->assertNotContains('student.solid-geometry.index', $labels);

        // Trang công cụ vẫn mở được và có đường quay lại chương trình học.
        $this->actingAs($this->student)->get(route('student.solid-geometry.index', ['grade' => 'lop-12']))
            ->assertOk()->assertSee(route('student.learn.index', ['grade' => 'lop-12']), false);
    }
}
