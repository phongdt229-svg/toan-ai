<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Role;
use App\Models\StudentDailyActivity;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Learning\StreakService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D-02 phạm vi lớp nhận học sinh · D-04 ngưỡng streak — cả hai đổi qua config/learning.php. */
class GradeRangeAndStreakConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    private function register(int $level)
    {
        return $this->post(route('register.student'), [
            'name' => 'Học Sinh', 'email' => "hs{$level}@example.com",
            'grade_id' => Grade::where('level', $level)->value('id'),
            'password' => 'matkhau123', 'password_confirmation' => 'matkhau123',
        ]);
    }

    public function test_default_range_is_grades_one_to_twelve(): void
    {
        $this->assertSame(12, Grade::active()->count());
        $this->get('/')->assertOk()->assertSee('lớp 1 → 12')->assertSee('Tiểu học');
    }

    public function test_narrowing_to_six_to_twelve_hides_primary_everywhere(): void
    {
        config(['learning.grade_min' => 6]);

        $this->assertSame(7, Grade::active()->count());
        $this->assertSame('6 → 12', Grade::rangeLabel());

        $this->get('/')->assertOk()
            ->assertSee('lớp 6 → 12')
            ->assertSee('Từ lớp 6 đến lớp 12')
            ->assertDontSee('Tiểu học')
            ->assertDontSee('lớp 1 → 12');

        $this->get(route('register.student'))->assertOk()->assertDontSee('>Lớp 1<', false);

        $this->register(3)->assertSessionHasErrors('grade_id');
        $this->register(7)->assertSessionHasNoErrors();
    }

    public function test_streak_threshold_comes_from_config(): void
    {
        $s = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $s->assignRole(Role::STUDENT);
        StudentProfile::create(['user_id' => $s->id, 'grade_id' => Grade::value('id'), 'link_code' => StudentProfile::generateLinkCode()]);
        StudentDailyActivity::create(['user_id' => $s->id, 'activity_date' => today()->toDateString(), 'online_seconds' => 900, 'active_seconds' => 900]);

        $this->assertSame(1, app(StreakService::class)->forStudent($s)['current']); // 15 phút ≥ 10

        config(['learning.streak_min_minutes' => 20]);
        $streak = app(StreakService::class)->forStudent($s);
        $this->assertSame(0, $streak['current']);
        $this->assertSame(5, $streak['minutes_to_keep']);
    }
}
