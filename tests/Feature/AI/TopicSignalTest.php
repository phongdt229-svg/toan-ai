<?php

namespace Tests\Feature\AI;

use App\Models\AiConversation;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Recommendation;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Models\User;
use App\Services\Learning\RecommendationService;
use App\Services\Learning\TopicSignalService;

/** TA-04 tín hiệu nhiều chiều theo chủ đề · TA-18 "đang học hay chỉ xin đáp án". */
class TopicSignalTest extends AiTestCase
{
    private function attempt(User $s, Question $q, bool $correct, int $seconds = 30, int $minutesAgo = 60): void
    {
        QuestionAttempt::create([
            'user_id' => $s->id, 'question_id' => $q->id, 'topic_id' => $q->topic_id,
            'context' => QuestionAttempt::CONTEXT_PRACTICE, 'difficulty' => $q->difficulty,
            'answer' => ['value' => 'x'], 'is_correct' => $correct, 'score' => $correct ? 1 : 0,
            'time_spent_seconds' => $seconds, 'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function ai(User $s, Question $q, string $mode, int $minutesAgo = 90): void
    {
        $c = AiConversation::create([
            'user_id' => $s->id, 'mode' => $mode, 'context_type' => 'question', 'context_id' => $q->id, 'title' => 'x',
        ]);
        $c->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();
    }

    /** @return array{0: Question, 1: Question} hai câu thuộc hai chủ đề khác nhau */
    private function twoTopics(): array
    {
        $a = Question::published()->where('type', '!=', Question::TYPE_ESSAY)->firstOrFail();
        // Dữ liệu mẫu chỉ có câu của một chủ đề — nhân bản sang chủ đề khác cho đủ hai.
        $b = $a->replicate();
        $b->topic_id = Topic::where('id', '!=', $a->topic_id)->value('id');
        $b->save();

        return [$a, $b];
    }

    public function test_repeated_errors_and_hints_rank_a_topic_above_a_solid_one(): void
    {
        $s = $this->makeStudent();
        [$shaky, $solid] = $this->twoTopics();

        foreach (range(1, 4) as $i) {
            $this->attempt($s, $shaky, false, minutesAgo: 100 - $i);
            $this->attempt($s, $solid, true, minutesAgo: 100 - $i);
        }
        foreach (range(1, 4) as $i) {
            $this->ai($s, $shaky, 'hint');
        }

        $signals = app(TopicSignalService::class)->forStudent($s);

        $this->assertSame($shaky->topic_id, $signals->first()['topic_id'], 'Chủ đề cần ôn nhất đứng đầu.');
        $this->assertGreaterThan($signals->get($solid->topic_id)['need'], $signals->get($shaky->topic_id)['need']);
        $this->assertContains('sai lặp lại 1 câu', $signals->get($shaky->topic_id)['reasons']);
        $this->assertContains('xin gợi ý 4 lần', $signals->get($shaky->topic_id)['reasons']);
    }

    public function test_fixing_own_mistakes_lowers_the_need(): void
    {
        [$q] = $this->twoTopics();

        $stuck = $this->makeStudent();
        $fixer = $this->makeStudent();
        foreach ([[false, 40], [false, 30], [false, 20]] as [$ok, $ago]) {
            $this->attempt($stuck, $q, $ok, minutesAgo: $ago);
        }
        foreach ([[false, 40], [true, 30], [false, 20], [true, 10]] as [$ok, $ago]) {
            $this->attempt($fixer, $q, $ok, minutesAgo: $ago);
        }

        $need = fn (User $u) => app(TopicSignalService::class)->forStudent($u)->get($q->topic_id)['need'];
        $this->assertLessThan($need($stuck), $need($fixer));
    }

    public function test_student_who_mostly_reads_solutions_gets_a_gentle_nudge(): void
    {
        $s = $this->makeStudent();
        [$q] = $this->twoTopics();
        foreach (range(1, 6) as $i) {
            $this->ai($s, $q, 'explain');
        }
        $this->ai($s, $q, 'hint');

        $profile = app(TopicSignalService::class)->helpSeeking($s);
        $this->assertSame('Hay xin lời giải', $profile['label']);
        $this->assertSame(86, $profile['explain_share']);

        $this->actingAs($s)->get(route('student.dashboard'))->assertOk()->assertSee('Thử bấm "Gợi ý" trước');
    }

    public function test_solving_after_a_hint_counts_as_learning(): void
    {
        $s = $this->makeStudent();
        [$q, $q2] = $this->twoTopics();
        $this->ai($s, $q, 'hint', minutesAgo: 50);
        $this->attempt($s, $q, true, minutesAgo: 40);
        $this->ai($s, $q2, 'hint', minutesAgo: 30);
        $this->attempt($s, $q2, true, minutesAgo: 20);
        $this->ai($s, $q2, 'explain', minutesAgo: 10);

        $profile = app(TopicSignalService::class)->helpSeeking($s);
        $this->assertSame(2, $profile['self_solved_after_hint']);
        $this->assertSame('Tự học tốt', $profile['label']);
        $this->assertNull($profile['nudge']);
    }

    public function test_shaky_topic_is_recommended_before_mastery_drops_and_says_why(): void
    {
        $s = $this->makeStudent();
        [$q] = $this->twoTopics();

        // Mastery còn "khá" (chưa yếu) nhưng 6 lượt gần nhất lúc đúng lúc sai + sai lặp lại + xin gợi ý nhiều.
        StudentTopicMastery::create(['user_id' => $s->id, 'topic_id' => $q->topic_id, 'mastery_score' => 70, 'correct_count' => 14, 'wrong_count' => 6]);
        foreach ([false, true, false, true, false, true] as $i => $ok) {
            $this->attempt($s, $q, $ok, minutesAgo: 60 - $i);
        }
        foreach (range(1, 5) as $i) {
            $this->ai($s, $q, 'hint');
        }

        app(RecommendationService::class)->refresh($s);

        $rec = Recommendation::where('user_id', $s->id)->where('target_id', $q->topic_id)->where('reason', 'like', 'Củng cố%')->first();
        $this->assertNotNull($rec);
        $this->assertStringContainsString('vì em', $rec->reason);
    }
}
