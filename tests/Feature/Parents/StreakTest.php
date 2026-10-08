<?php

namespace Tests\Feature\Parents;

use App\Models\StudentDailyActivity;
use App\Models\User;
use App\Notifications\StudyReminder;
use App\Services\Learning\StreakService;
use Illuminate\Support\Facades\Notification;

/** TA-15: chuỗi ngày học — một ngày tính khi học thực ≥ 10 phút (D-04 tạm chốt). */
class StreakTest extends ParentTestCase
{
    private function studied(User $s, int $daysAgo, int $minutes): void
    {
        StudentDailyActivity::create([
            'user_id' => $s->id, 'activity_date' => today()->subDays($daysAgo)->toDateString(),
            'online_seconds' => $minutes * 60, 'active_seconds' => $minutes * 60,
        ]);
    }

    private function streak(User $s): array
    {
        return app(StreakService::class)->forStudent($s);
    }

    public function test_counts_consecutive_days_including_today(): void
    {
        $s = $this->makeStudent();
        foreach ([0, 1, 2] as $d) {
            $this->studied($s, $d, 15);
        }

        $this->assertSame(3, $this->streak($s)['current']);
        $this->assertTrue($this->streak($s)['today_done']);
    }

    public function test_streak_stays_alive_until_the_day_ends(): void
    {
        $s = $this->makeStudent();
        $this->studied($s, 1, 20);
        $this->studied($s, 2, 20);
        $this->studied($s, 0, 4); // hôm nay mới 4 phút

        $streak = $this->streak($s);
        $this->assertSame(2, $streak['current']);
        $this->assertFalse($streak['today_done']);
        $this->assertSame(6, $streak['minutes_to_keep']);
    }

    public function test_short_days_and_gaps_break_the_chain_but_best_is_kept(): void
    {
        $s = $this->makeStudent();
        foreach ([10, 9, 8, 7] as $d) {
            $this->studied($s, $d, 30); // chuỗi 4 ngày cũ
        }
        $this->studied($s, 2, 5); // dưới 10 phút → không tính
        $this->studied($s, 1, 12);

        $streak = $this->streak($s);
        $this->assertSame(1, $streak['current']);
        $this->assertSame(4, $streak['best']);
    }

    public function test_dashboard_and_parent_page_show_the_streak(): void
    {
        $parent = $this->makeParent();
        $s = $this->makeStudent();
        $this->link($parent, $s);
        $this->studied($s, 1, 20);
        $this->studied($s, 2, 20);

        $this->actingAs($s)->get(route('student.dashboard'))
            ->assertOk()->assertSee('2 ngày liên tiếp')->assertSee('để giữ chuỗi');
        $this->actingAs($parent)->get(route('parent.children.show', $s))
            ->assertOk()->assertSee('Chuỗi ngày học');
    }

    public function test_evening_reminder_mentions_the_streak_at_risk(): void
    {
        Notification::fake();
        $s = $this->makeStudent();
        $this->studied($s, 1, 20);
        $this->studied($s, 2, 20);

        $this->artisan('students:remind-study')->assertSuccessful();

        Notification::assertSentTo($s, StudyReminder::class, fn (StudyReminder $n) => $n->streak === 2
            && str_contains($n->toArray($s)['title'], 'Giữ chuỗi 2 ngày'));
    }
}
