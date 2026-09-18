<?php

namespace Tests\Feature\Subscriptions;

use App\Models\AiUsage;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Topic;

class FeatureGatingTest extends SubscriptionTestCase
{
    private function useAi(int $count, $student): void
    {
        AiUsage::create([
            'user_id' => $student->id, 'usage_date' => today(), 'feature' => 'hint', 'request_count' => $count,
        ]);
    }

    private function question(): Question
    {
        return Question::where('type', Question::TYPE_SINGLE_CHOICE)->published()->firstOrFail();
    }

    public function test_ai_daily_quota_comes_from_package_features(): void
    {
        $student = $this->makeStudent();
        $this->useAi(10, $student);

        $this->actingAs($student)
            ->postJson(route('api.ai.hint'), ['question_id' => $this->question()->id])
            ->assertStatus(429);

        $this->subscribe($student, 'pro-thang'); // 50 lượt/ngày

        $this->actingAs($student)
            ->postJson(route('api.ai.hint'), ['question_id' => $this->question()->id])
            ->assertOk()
            ->assertJsonPath('meta.usage.limit', 50);
    }

    public function test_advanced_ai_mode_requires_premium_and_returns_upgrade_offer(): void
    {
        $student = $this->makeStudent();
        $this->subscribe($student, 'pro-thang');

        $this->actingAs($student)
            ->postJson(route('api.ai.similar-exercise'), ['question_id' => $this->question()->id])
            ->assertStatus(402)
            ->assertJsonPath('reason', 'upgrade_required')
            ->assertJsonPath('upgrade.name', 'Premium 1 tháng')
            ->assertJsonPath('upgrade.url', route('packages.index'));

        // Bị khoá trước khi gọi AI → không tốn lượt.
        $this->assertSame(0, (int) AiUsage::where('user_id', $student->id)->sum('request_count'));

        $this->subscribe($student, 'premium-thang');

        $this->actingAs($student)
            ->postJson(route('api.ai.similar-exercise'), ['question_id' => $this->question()->id])
            ->assertOk();
    }

    public function test_free_practice_is_capped_per_day_and_set_shrinks_to_remaining(): void
    {
        $student = $this->makeStudent();
        $topic = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();
        $q = $this->question();

        $attempts = fn (int $n) => collect(range(1, $n))->each(fn ($i) => QuestionAttempt::create([
            'user_id' => $student->id, 'question_id' => $q->id, 'topic_id' => $q->topic_id,
            'context' => QuestionAttempt::CONTEXT_PRACTICE, 'difficulty' => $q->difficulty,
            'answer' => ['value' => null], 'is_correct' => false, 'score' => 0, 'attempt_no' => $i,
        ]));

        $attempts(28);
        $this->actingAs($student)
            ->post(route('student.practice.start'), ['topic_id' => $topic->id, 'limit' => 10])
            ->assertRedirect(route('student.practice.show'));
        $this->assertLessThanOrEqual(2, count(session('practice.current.question_ids')));

        $attempts(2);
        $this->actingAs($student)
            ->post(route('student.practice.start'), ['topic_id' => $topic->id, 'limit' => 10])
            ->assertRedirect(route('packages.index'))
            ->assertSessionHas('error');

        $this->subscribe($student, 'pro-thang');
        $this->actingAs($student)
            ->post(route('student.practice.start'), ['topic_id' => $topic->id, 'limit' => 10])
            ->assertRedirect(route('student.practice.show'));
    }

    public function test_pro_lesson_unlocks_with_pro_subscription(): void
    {
        $student = $this->makeStudent();
        $lesson = Lesson::where('slug', 'cong-hai-phan-so-khac-mau-so')->firstOrFail();
        $lesson->update(['access_level' => Lesson::ACCESS_PRO]);

        $this->actingAs($student)->get(route('student.lesson.show', $lesson))
            ->assertSee('thuộc gói Pro')
            ->assertSee(route('packages.index'));

        $this->subscribe($student, 'pro-thang');

        $this->actingAs($student)->get(route('student.lesson.show', $lesson))
            ->assertOk()
            ->assertDontSee('thuộc gói Pro');
    }

    public function test_free_student_and_parent_see_a_way_to_buy(): void
    {
        $parent = $this->makeParent();
        $student = $this->makeStudent('Bé Na');
        $this->link($parent, $student);

        // Học sinh gói Free: thẻ mời nâng cấp ngay trên trang chủ.
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Em đang dùng gói Free')
            ->assertSee(route('packages.index'));

        // Phụ huynh: nút mua gói cho con ngay trên thẻ của con.
        $this->actingAs($parent)->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('Mua gói cho con');

        $this->subscribe($student, 'pro-thang', $parent);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Em đang dùng gói Free');

        $this->actingAs($parent)->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('Pro 1 tháng')
            ->assertSee('Gia hạn');
    }

    public function test_parent_advanced_report_follows_the_childs_package(): void
    {
        $parent = $this->makeParent();
        $student = $this->makeStudent();
        $this->link($parent, $student);

        $this->actingAs($parent)->get(route('parent.children.show', $student))
            ->assertOk()
            ->assertSee('Báo cáo nâng cao')
            ->assertDontSee('Số câu đã làm 7 ngày qua');

        $this->subscribe($student, 'premium-thang', $parent);

        $this->actingAs($parent)->get(route('parent.children.show', $student))
            ->assertOk()
            ->assertSee('Số câu đã làm 7 ngày qua')
            ->assertDontSee('Biểu đồ học tập từng ngày');
    }
}
