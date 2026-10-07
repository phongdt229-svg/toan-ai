<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\ThemeColor;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Đặc tả module 8: đổi thầy/cô, màu yêu thích, sở thích sau đăng ký + màu nhấn portal học sinh. */
class StudentPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    private function student(array $profile = []): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $user->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => StudentProfile::generateLinkCode(),
        ] + $profile);

        return $user;
    }

    public function test_student_updates_persona_color_and_interests(): void
    {
        $user = $this->student(['tutor_persona' => 'co']);

        $this->actingAs($user)
            ->put(route('student.settings.personalization'), [
                'tutor_persona' => 'thay',
                'favorite_color' => '#16a34a',
                'interests' => 'bóng đá,  vẽ , ',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $profile = $user->studentProfile()->first();
        $this->assertSame('thay', $profile->tutor_persona);
        $this->assertSame('#16a34a', $profile->favorite_color);
        $this->assertSame(['bóng đá', 'vẽ'], $profile->interests);
    }

    public function test_color_must_be_a_hex_value(): void
    {
        // Màu được in vào <style> của layout — chuỗi tự do là cửa chèn CSS.
        $user = $this->student();

        $this->actingAs($user)
            ->put(route('student.settings.personalization'), [
                'tutor_persona' => 'co',
                'favorite_color' => 'red;}body{display:none',
            ])
            ->assertSessionHasErrors('favorite_color');
    }

    public function test_non_student_cannot_use_the_route(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole(Role::TEACHER);

        $this->actingAs($teacher)
            ->put(route('student.settings.personalization'), ['tutor_persona' => 'co'])
            ->assertForbidden();
    }

    public function test_student_portal_uses_favorite_color_as_accent(): void
    {
        $user = $this->student(['favorite_color' => '#16a34a']);
        $accent = ThemeColor::accent('#16a34a');

        $this->actingAs($user)->get(route('student.settings'))
            ->assertOk()
            ->assertSee('--bs-primary: '.$accent['hex'], false)
            ->assertSee('Cá nhân hoá');
    }

    public function test_no_accent_override_without_favorite_color(): void
    {
        $user = $this->student();

        $this->actingAs($user)->get(route('student.settings'))
            ->assertOk()
            ->assertDontSee('--bs-btn-bg:', false);
    }

    public function test_light_colors_are_darkened_until_white_text_is_readable(): void
    {
        foreach (['#ffff00', '#a3e635', '#ffffff', '#2563eb'] as $hex) {
            $accent = ThemeColor::accent($hex);
            $this->assertGreaterThanOrEqual(
                ThemeColor::MIN_CONTRAST,
                ThemeColor::contrastWithWhite(ThemeColor::parse($accent['hex'])),
                "Màu {$hex} → {$accent['hex']} chưa đủ tương phản",
            );
        }

        // Màu đã đủ đậm thì giữ nguyên, không tự ý đổi màu học sinh chọn.
        $this->assertSame('#1e3a8a', ThemeColor::accent('#1e3a8a')['hex']);
        $this->assertNull(ThemeColor::accent('blue'));
    }
}
