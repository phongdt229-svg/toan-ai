<?php

namespace Tests\Feature\Auth;

use App\Models\Grade;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    public function test_student_registers_and_lands_on_student_dashboard(): void
    {
        $grade = Grade::where('level', 6)->firstOrFail();

        $response = $this->post(route('register.student'), [
            'name' => 'Nguyễn Văn A',
            'email' => 'hs@example.com',
            'grade_id' => $grade->id,
            'birth_date' => '2013-04-15',
            'address' => 'Quận 3, TP. Hồ Chí Minh',
            'school' => 'THCS Test',
            'math_average_score' => 7.5,
            'tutor_persona' => 'thay',
            'favorite_color' => '#ff0000',
            'interests' => 'bóng đá, vẽ',
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
        ]);

        $response->assertRedirect(route('student.dashboard'));

        $user = User::where('email', 'hs@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::STUDENT));
        $this->assertSame(User::STATUS_ACTIVE, $user->status);

        $profile = $user->studentProfile;
        $this->assertNotNull($profile);
        $this->assertSame(8, strlen($profile->link_code));
        $this->assertSame('2013-04-15', $profile->birth_date->toDateString());
        $this->assertSame('thay', $profile->tutor_persona);
        $this->assertSame(['bóng đá', 'vẽ'], $profile->interests);
        // Không tự đánh giá học lực → suy ra từ điểm TB 7.5 → "Khá" (§34).
        $this->assertSame('good', $profile->self_assessed_level);
    }

    public function test_student_registration_works_without_optional_personalization(): void
    {
        $this->post(route('register.student'), [
            'name' => 'Tối thiểu',
            'email' => 'toithieu@example.com',
            'grade_id' => Grade::where('level', 1)->value('id'),
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
        ])->assertRedirect(route('student.dashboard'));

        $profile = User::where('email', 'toithieu@example.com')->firstOrFail()->studentProfile;

        $this->assertSame('co', $profile->tutor_persona);
        $this->assertNull($profile->self_assessed_level);
        $this->assertNull($profile->interests);
    }

    public function test_teacher_registers_as_pending_and_cannot_enter_portal(): void
    {
        $this->post(route('register.teacher'), [
            'name' => 'Trần Thị B',
            'email' => 'gv@example.com',
            'phone' => '0912345678',
            'school' => 'THCS Test',
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
        ])->assertRedirect(route('account.pending'));

        $teacher = User::where('email', 'gv@example.com')->firstOrFail();
        $this->assertSame(User::STATUS_PENDING, $teacher->status);
        $this->assertTrue($teacher->hasRole(Role::TEACHER));

        // Middleware `active` đẩy tài khoản pending về trang chờ duyệt.
        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertRedirect(route('account.pending'));
    }

    public function test_parent_registers_and_links_child_by_code(): void
    {
        $child = User::factory()->create(['email' => 'con@example.com']);
        $child->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $child->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => 'ABCDEFGH',
        ]);

        $this->post(route('register.parent'), [
            'name' => 'Lê Văn C',
            'email' => 'ph@example.com',
            'phone' => '0987654321',
            'link_code' => 'ABCDEFGH',
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
        ])->assertRedirect(route('parent.dashboard'));

        $parent = User::where('email', 'ph@example.com')->firstOrFail();
        $this->assertTrue($parent->children()->where('users.id', $child->id)->exists());
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'trung@example.com']);

        $this->from(route('register.student'))
            ->post(route('register.student'), [
                'name' => 'Trùng Email',
                'email' => 'trung@example.com',
                'grade_id' => Grade::where('level', 1)->value('id'),
                'password' => 'matkhau123',
                'password_confirmation' => 'matkhau123',
            ])
            ->assertSessionHasErrors('email');
    }
}
