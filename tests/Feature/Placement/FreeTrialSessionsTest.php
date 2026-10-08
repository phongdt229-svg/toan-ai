<?php

namespace Tests\Feature\Placement;

use App\Models\LearningPath;
use App\Models\LearningPathItem;
use App\Models\Package;
use App\Models\StudySession;
use App\Models\User;
use App\Services\Learning\LearningPathService;
use App\Services\SubscriptionService;
use Database\Seeders\PackageSeeder;
use Tests\Support\TestPackageSeeder;

/** Bảng giá 08/10/2026: Free học thử 3 buổi lộ trình, sau đó phải mua gói (`path.sessions`). */
class FreeTrialSessionsTest extends PlacementTestCase
{
    private function paths(): LearningPathService
    {
        return app(LearningPathService::class);
    }

    /** Học sinh có lộ trình, buổi đầu đang chờ kiểm tra, và đã học xong $done buổi trước đó. */
    private function studentAtQuiz(int $done): array
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $path = LearningPath::where('user_id', $student->id)->latest('id')->firstOrFail();

        foreach (range(1, $done) as $i) {
            if ($done === 0) {
                break;
            }
            StudySession::create([
                'user_id' => $student->id, 'learning_path_id' => $path->id, 'session_no' => 900 + $i,
                'status' => StudySession::STATUS_DONE, 'completed_at' => now(),
            ]);
        }

        $session = $path->sessions()->where('status', '!=', StudySession::STATUS_DONE)->orderBy('session_no')->firstOrFail();
        LearningPathItem::where('study_session_id', $session->id)->update(['status' => 'done', 'completed_at' => now()]);
        $this->paths()->recalculate($path);

        return [$student, $session->fresh()];
    }

    public function test_real_catalog_is_free_trial_pro_699k_premium_1_2m_yearly(): void
    {
        $this->seed(PackageSeeder::class);

        $active = Package::active()->ordered()->get();
        $this->assertSame(['free', 'pro-nam', 'premium-nam'], $active->pluck('slug')->all());
        $this->assertSame('699000.00', $active[1]->price);
        $this->assertSame('1200000.00', $active[2]->price);
        $this->assertSame([365, 365], [$active[1]->duration_days, $active[2]->duration_days]);
        $this->assertTrue((bool) $active[1]->is_highlighted);

        $this->assertSame(3, $active[0]->features->firstWhere('key', 'path.sessions')->limit_value);
        $this->assertNull($active[1]->features->firstWhere('key', 'path.sessions')->limit_value);
    }

    public function test_free_student_can_finish_the_third_session(): void
    {
        $this->seed(TestPackageSeeder::class);
        [$student, $session] = $this->studentAtQuiz(done: 2);

        $this->assertFalse($this->paths()->isSessionLocked($session, $student));
        $this->actingAs($student)->get(route('student.path.quiz', $session))->assertOk();
    }

    public function test_free_student_is_stopped_at_the_fourth_session(): void
    {
        $this->seed(TestPackageSeeder::class);
        [$student, $session] = $this->studentAtQuiz(done: 3);

        $this->actingAs($student)->get(route('student.path.show'))
            ->assertOk()
            ->assertSee('cần gói học')
            ->assertSee('học xong 3 buổi học thử');

        $this->actingAs($student)->get(route('student.path.quiz', $session))
            ->assertRedirect(route('packages.index'))
            ->assertSessionHas('error');

        // Đề chưa từng được phát (bị khoá ngay từ bước mở đề) nên nộp thẳng cũng bị từ chối.
        $this->actingAs($student)->post(route('student.path.quiz.submit', $session), ['answers' => []])
            ->assertSessionHas('error');

        $this->assertNotSame(StudySession::STATUS_DONE, $session->fresh()->status);
    }

    public function test_paid_student_has_no_session_limit(): void
    {
        $this->seed(TestPackageSeeder::class);
        [$student, $session] = $this->studentAtQuiz(done: 3);

        $subscriptions = app(SubscriptionService::class);
        $subscriptions->activate($subscriptions->createPending($student, Package::where('slug', 'pro-nam')->firstOrFail()));

        $this->assertNull($this->paths()->sessionLimit($student->fresh()));
        $this->actingAs($student)->get(route('student.path.quiz', $session))->assertOk();
    }

    public function test_retaking_placement_does_not_reset_the_free_trial(): void
    {
        $this->seed(TestPackageSeeder::class);
        [$student] = $this->studentAtQuiz(done: 3);

        // Làm lại đầu vào → lộ trình mới, nhưng 3 buổi thử đã dùng vẫn tính.
        $this->completePlacement($student);
        $path = LearningPath::where('user_id', $student->id)->where('status', '!=', 'archived')->latest('id')->firstOrFail();
        $first = $path->sessions()->orderBy('session_no')->firstOrFail();

        $this->assertTrue($this->paths()->isSessionLocked($first, User::find($student->id)));
    }
}
