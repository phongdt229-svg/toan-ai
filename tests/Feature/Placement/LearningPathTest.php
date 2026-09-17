<?php

namespace Tests\Feature\Placement;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\LearningPath;
use App\Models\LearningPathItem;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\StudentLessonProgress;
use App\Models\StudentTopicMastery;
use App\Models\StudySession;
use App\Models\Topic;
use App\Models\User;
use App\Services\Learning\LearningPathService;
use App\Services\Learning\ProgressService;

class LearningPathTest extends PlacementTestCase
{
    private function paths(): LearningPathService
    {
        return app(LearningPathService::class);
    }

    private function pathFor(User $student): LearningPath
    {
        return LearningPath::where('user_id', $student->id)->where('status', '!=', 'archived')->latest('id')->firstOrFail();
    }

    private function topic(string $slug): Topic
    {
        return Topic::where('slug', $slug)->firstOrFail();
    }

    // --- Sinh lộ trình §35 ------------------------------------------------------------------

    public function test_path_has_four_stages_in_order_with_first_in_progress(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);

        $stages = $this->pathFor($student)->stages()->get();

        $this->assertSame(['foundation', 'consolidation', 'advanced', 'exam_practice'], $stages->pluck('stage')->all());
        $this->assertSame('in_progress', $stages[0]->status);
        $this->assertSame(['locked', 'locked', 'locked'], $stages->slice(1)->pluck('status')->values()->all());
    }

    public function test_weak_topic_goes_to_foundation_with_its_prerequisite_first(): void
    {
        $student = $this->makeStudent();
        // Chỉ "So sánh phân số" yếu → nền tảng phải có cả "Phép cộng phân số" (đứng trước trong chương) và đứng trước.
        StudentTopicMastery::create([
            'user_id' => $student->id, 'topic_id' => $this->topic('so-sanh-phan-so')->id,
            'mastery_score' => 20, 'correct_count' => 2, 'wrong_count' => 8,
        ]);

        $path = $this->paths()->generate($student->load('studentProfile'));
        $topicOrder = $path->stages()->where('stage', 'foundation')->first()->items()->pluck('topic_id')->unique()->values()->all();

        $this->assertSame([$this->topic('phep-cong-phan-so')->id, $this->topic('so-sanh-phan-so')->id], $topicOrder);
    }

    public function test_excellent_student_skips_easy_practice_in_foundation(): void
    {
        $student = $this->makeStudent();
        $test = $this->completePlacement($student, perfect: true);
        $this->assertSame('excellent', $test->level_result);

        $difficulties = $this->pathFor($student)->stages()->where('stage', 'foundation')->first()
            ->items()->where('item_type', 'practice')->pluck('difficulty')->unique()->all();

        $this->assertSame(['medium'], array_values($difficulties));
    }

    public function test_items_are_chunked_into_sessions_of_three(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $path = $this->pathFor($student);

        $pending = $path->items()->where('learning_path_items.status', 'pending')->count();

        $this->assertSame((int) ceil($pending / 3), $path->total_sessions);
        $this->assertTrue($path->sessions()->withCount('items')->get()->every(fn ($s) => $s->items_count <= 3));
    }

    public function test_lesson_learned_before_path_counts_as_done_and_is_not_scheduled(): void
    {
        $student = $this->makeStudent();
        $lesson = Lesson::where('slug', 'cong-hai-phan-so-cung-mau-so')->firstOrFail();
        app(ProgressService::class)->completeLesson($student, $lesson);

        $this->completePlacement($student);

        $item = $this->pathFor($student)->items()->where('item_type', 'lesson')->where('target_id', $lesson->id)->first();
        $this->assertSame('done', $item->status);
        $this->assertNull($item->study_session_id);
    }

    // --- Tự điều chỉnh qua event -------------------------------------------------------------

    public function test_completing_a_lesson_marks_item_and_raises_progress(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $path = $this->pathFor($student);
        $item = $path->items()->where('item_type', 'lesson')->where('learning_path_items.status', 'pending')->first();

        $this->actingAs($student)->post(route('student.lesson.complete', Lesson::find($item->target_id)));

        $this->assertSame('done', $item->fresh()->status);
        $this->assertGreaterThan(0, $path->fresh()->progress_percent);
    }

    public function test_practicing_enough_questions_after_item_created_completes_practice_item(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $item = $this->pathFor($student)->items()->where('item_type', 'practice')->whereNull('difficulty')->orWhere(function ($q) {
            $q->where('item_type', 'practice');
        })->where('learning_path_items.status', 'pending')->first();

        // Luyện đúng chủ đề + độ khó của mục cho tới khi đủ số câu.
        for ($round = 0; $round < 3 && $item->fresh()->status === 'pending'; $round++) {
            $this->actingAs($student)->post(route('student.practice.start'), array_filter([
                'topic_id' => $item->topic_id, 'difficulty' => $item->difficulty, 'limit' => 5,
            ]));
            $this->actingAs($student)->post(route('student.practice.submit'), ['answers' => []]);
        }

        $this->assertSame('done', $item->fresh()->status);
    }

    public function test_finishing_exam_completes_exam_item(): void
    {
        $exam = Exam::create([
            'title' => 'Đề lộ trình', 'slug' => 'de-lo-trinh', 'grade_id' => $this->grade->id, 'status' => 'published',
            'duration_minutes' => 10, 'max_attempts' => 2, 'shuffle_questions' => false, 'shuffle_options' => false,
        ]);
        $exam->questions()->attach(Question::published()->value('id'), ['sort_order' => 1, 'points' => 1]);

        $student = $this->makeStudent();
        $this->completePlacement($student);
        $item = $this->pathFor($student)->items()->where('item_type', 'exam')->firstOrFail();

        $this->actingAs($student)->post(route('student.exams.start', $exam));
        $this->actingAs($student)->post(route('student.exams.submit', ExamAttempt::firstOrFail()));

        $this->assertSame('done', $item->fresh()->status);
    }

    public function test_topic_that_drops_after_being_completed_gets_a_review_item(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $path = $this->pathFor($student);
        $topicId = $this->topic('phep-cong-phan-so')->id;

        // Giả lập: đã học xong mọi mục kế hoạch của chủ đề, rồi làm sai nhiều.
        LearningPathItem::whereIn('id', $path->items()->where('learning_path_items.topic_id', $topicId)->pluck('learning_path_items.id'))
            ->update(['status' => 'done']);
        StudentTopicMastery::updateOrCreate(
            ['user_id' => $student->id, 'topic_id' => $topicId],
            ['mastery_score' => 10, 'correct_count' => 1, 'wrong_count' => 9],
        );

        $this->paths()->syncMastery($student);
        $this->paths()->syncMastery($student); // gọi lại không chèn trùng

        $reviews = $path->items()->where('origin', 'review')->where('learning_path_items.topic_id', $topicId)->get();
        $this->assertCount(1, $reviews);
        $this->assertNotNull($reviews[0]->study_session_id);
    }

    // --- Buổi học & kiểm tra cuối buổi §36–37 ---------------------------------------------------

    private function finishSessionItems(StudySession $session): void
    {
        LearningPathItem::where('study_session_id', $session->id)->update(['status' => 'done', 'completed_at' => now()]);
        $this->paths()->recalculate($session->path()->first());
    }

    public function test_session_moves_to_quiz_then_passing_completes_it(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $path = $this->pathFor($student);
        $session = $path->sessions()->first();

        $this->finishSessionItems($session);
        $this->assertSame(StudySession::STATUS_QUIZ_PENDING, $session->fresh()->status);

        $this->actingAs($student)->get(route('student.path.quiz', $session))->assertOk()->assertSee('Kiểm tra cuối buổi');

        $questions = Question::whereIn('id', $session->fresh()->quiz_question_ids)->with('options')->get();
        $answers = $questions->mapWithKeys(fn ($q) => [$q->id => match ($q->type) {
            'single_choice' => (string) $q->options->firstWhere('is_correct', true)->id,
            'multiple_choice' => $q->options->where('is_correct', true)->pluck('id')->map(fn ($v) => (string) $v)->all(),
            'true_false' => $q->correct_answer['value'] ? '1' : '0',
            'fill_blank' => collect($q->correct_answer['blanks'])->map(fn ($b) => $b[0])->all(),
            'short_answer' => $q->correct_answer['accepted'][0],
        }])->all();

        $this->actingAs($student)
            ->post(route('student.path.quiz.submit', $session), ['answers' => $answers])
            ->assertRedirect(route('student.path.show'))
            ->assertSessionHas('status');

        $session->refresh();
        $this->assertSame(StudySession::STATUS_DONE, $session->status);
        $this->assertSame(100, $session->quiz_percent);
        $this->assertSame(1, $path->fresh()->completed_sessions);
        $this->assertSame(0, $path->items()->where('origin', 'review')->count());
    }

    public function test_failing_quiz_adds_review_to_next_session(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $path = $this->pathFor($student);
        $session = $path->sessions()->first();

        $this->finishSessionItems($session);
        $this->paths()->quizQuestions($session->fresh());

        $result = $this->paths()->submitQuiz($session->fresh(), []);

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['review_topics']);
        $this->assertSame(StudySession::STATUS_DONE, $session->fresh()->status, 'Chưa đạt vẫn được sang buổi mới.');

        $review = $path->items()->where('origin', 'review')->first();
        $this->assertNotNull($review);
        $this->assertGreaterThan($session->session_no, StudySession::find($review->study_session_id)->session_no);
    }

    public function test_quiz_cannot_be_submitted_twice(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $session = $this->pathFor($student)->sessions()->first();
        $this->finishSessionItems($session);
        $this->paths()->quizQuestions($session->fresh());
        $this->paths()->submitQuiz($session->fresh(), []);

        $this->actingAs($student)
            ->post(route('student.path.quiz.submit', $session), ['answers' => []])
            ->assertSessionHas('error');
    }

    public function test_other_student_cannot_open_session_quiz(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $session = $this->pathFor($student)->sessions()->first();

        $this->actingAs($this->makeStudent())->get(route('student.path.quiz', $session))->assertForbidden();
    }

    // --- Làm lại đầu vào, hoàn thành, hiển thị -------------------------------------------------

    public function test_retaking_placement_archives_old_path(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $old = $this->pathFor($student);

        $this->completePlacement($student, perfect: true);

        $this->assertSame('archived', $old->fresh()->status);
        $this->assertSame(1, LearningPath::where('user_id', $student->id)->where('status', 'active')->count());
    }

    public function test_path_completes_when_every_item_and_session_is_done(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $path = $this->pathFor($student);

        LearningPathItem::whereIn('id', $path->items()->pluck('learning_path_items.id'))->update(['status' => 'done']);
        StudySession::where('learning_path_id', $path->id)->update(['status' => 'done']);
        $this->paths()->recalculate($path);

        $path->refresh();
        $this->assertSame(LearningPath::STATUS_COMPLETED, $path->status);
        $this->assertSame(100, $path->progress_percent);
        $this->assertSame(0, $path->remainingSessions());
    }

    public function test_path_page_dashboard_and_parent_report_show_progress(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);

        $this->actingAs($student)->get(route('student.path.show'))
            ->assertOk()
            ->assertSee('Ôn lại nền tảng')
            ->assertSee('Luyện đề')
            ->assertSee('Buổi 1 — học hôm nay');

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Hoàn thành lộ trình')
            ->assertSee('Buổi còn lại')
            ->assertSee('Gợi ý học hôm nay · Buổi 1');

        $parent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $parent->assignRole('parent');
        app(\App\Services\Parenting\ChildLinkService::class)->linkByCode($parent, $student->studentProfile()->value('link_code'));

        $this->actingAs($parent)->get(route('parent.children.show', $student))
            ->assertOk()
            ->assertSee('Lộ trình học')
            ->assertSee('Kiểm tra đầu vào:');
    }

    public function test_student_without_path_is_sent_to_placement(): void
    {
        $this->actingAs($this->makeStudent())
            ->get(route('student.path.show'))
            ->assertRedirect(route('student.placement.intro'));
    }
}
