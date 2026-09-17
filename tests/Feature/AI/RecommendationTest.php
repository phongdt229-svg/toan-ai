<?php

namespace Tests\Feature\AI;

use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Recommendation;
use App\Models\StudentLessonProgress;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Services\Learning\RecommendationService;

class RecommendationTest extends AiTestCase
{
    private Topic $addition;
    private Topic $comparison;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addition = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();     // sort_order 1
        $this->comparison = Topic::where('slug', 'so-sanh-phan-so')->firstOrFail();     // sort_order 2
    }

    private function mastery($student, Topic $topic, int $score, int $attempts = 10): void
    {
        StudentTopicMastery::create([
            'user_id' => $student->id, 'topic_id' => $topic->id, 'mastery_score' => $score,
            'correct_count' => (int) round($attempts * $score / 100),
            'wrong_count' => $attempts - (int) round($attempts * $score / 100),
        ]);
    }

    private function recs($student)
    {
        app(RecommendationService::class)->refresh($student);

        return Recommendation::where('user_id', $student->id)->orderByDesc('priority')->get();
    }

    public function test_weak_topic_gets_unfinished_lesson_then_easy_practice(): void
    {
        $student = $this->makeStudent();
        $this->mastery($student, $this->addition, 30);
        StudentLessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => Lesson::where('slug', 'cong-hai-phan-so-cung-mau-so')->value('id'),
            'status' => 'completed', 'completed_at' => now(),
        ]);

        $recs = $this->recs($student);

        $this->assertSame('review_lesson', $recs[0]->type);
        $this->assertSame(Lesson::where('slug', 'cong-hai-phan-so-khac-mau-so')->value('id'), $recs[0]->target_id);
        $this->assertStringContainsString('30%', $recs[0]->reason);

        $practice = $recs->firstWhere('type', 'practice_topic');
        $this->assertSame('easy', $practice->difficulty, 'Mastery < 40% bắt đầu từ mức Dễ.');
    }

    public function test_prerequisite_topic_is_recommended_before_the_weak_topic(): void
    {
        $student = $this->makeStudent();
        // "So sánh phân số" yếu; chủ đề đứng trước "Phép cộng phân số" có bài và em chưa luyện → kiến thức nền.
        Lesson::where('topic_id', $this->addition->id)->update(['sort_order' => 1]);
        Lesson::create([
            'topic_id' => $this->comparison->id, 'title' => 'So sánh hai phân số', 'slug' => 'so-sanh-hai-phan-so',
            'status' => 'published', 'published_at' => now(),
        ]);
        $this->mastery($student, $this->comparison, 20);

        $recs = $this->recs($student);

        $this->assertSame($this->addition->id, $recs[0]->topic_id);
        $this->assertStringContainsString('kiến thức nền', $recs[0]->reason);
    }

    public function test_medium_mastery_moves_up_to_hard_practice(): void
    {
        $student = $this->makeStudent();
        $this->mastery($student, $this->addition, 65);

        $recs = $this->recs($student);

        $this->assertTrue($recs->contains(fn ($r) => $r->type === 'practice_topic' && $r->difficulty === 'hard'));
    }

    public function test_strong_topic_suggests_untaken_exam_to_recheck(): void
    {
        $student = $this->makeStudent();
        $this->mastery($student, $this->addition, 90);

        $exam = Exam::create(['title' => 'Đề cộng phân số', 'slug' => 'de-cong', 'grade_id' => $this->grade->id, 'status' => 'published']);
        $exam->questions()->attach(Question::where('topic_id', $this->addition->id)->value('id'), ['sort_order' => 1, 'points' => 1]);

        $rec = $this->recs($student)->firstWhere('type', 'take_exam');

        $this->assertNotNull($rec);
        $this->assertSame($exam->id, $rec->target_id);
        $this->assertSame(route('student.exams.show', 'de-cong'), app(RecommendationService::class)->urlFor($rec));
    }

    public function test_new_student_is_pointed_to_first_lesson_of_grade(): void
    {
        $student = $this->makeStudent();

        $recs = $this->recs($student);

        $this->assertCount(1, $recs);
        $this->assertStringStartsWith('Bắt đầu với bài', $recs[0]->reason);
    }

    public function test_recommendations_refresh_automatically_after_practice(): void
    {
        $student = $this->makeStudent();

        // Làm luyện tập sai hết 2 lượt (≥ 5 câu) → chủ đề thành "yếu" → đề xuất xuất hiện.
        foreach ([1, 2] as $round) {
            $this->actingAs($student)->post(route('student.practice.start'), ['topic_id' => $this->addition->id, 'limit' => 10]);
            $this->actingAs($student)->post(route('student.practice.submit'), ['answers' => []]);
        }

        $this->assertTrue(
            Recommendation::where('user_id', $student->id)->where('topic_id', $this->addition->id)->where('type', 'practice_topic')->exists(),
        );
    }

    public function test_completing_recommended_lesson_removes_it(): void
    {
        $student = $this->makeStudent();
        $this->mastery($student, $this->addition, 30);
        $first = $this->recs($student)->firstWhere('type', 'review_lesson');
        $lesson = Lesson::find($first->target_id);

        $this->actingAs($student)->post(route('student.lesson.complete', $lesson));

        $this->assertFalse(
            Recommendation::where('user_id', $student->id)->where('type', 'review_lesson')->where('target_id', $lesson->id)->exists(),
        );
    }

    public function test_dashboard_shows_todays_suggestions(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Gợi ý học hôm nay')
            ->assertSee('Bắt đầu với bài');
    }
}
