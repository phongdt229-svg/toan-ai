<?php

namespace Tests\Feature\Placement;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\LearningPath;
use App\Models\LearningPathItem;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\StudentTopicMastery;
use App\Models\StudySession;
use App\Models\Topic;
use App\Models\User;
use App\Services\Learning\LearningPathService;
use App\Services\Learning\ProgressService;
use App\Services\Parenting\ChildLinkService;

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

        $answers = $this->correctAnswers($session);

        $this->actingAs($student)
            ->post(route('student.path.quiz.submit', $session), ['answers' => $answers])
            ->assertRedirect(route('student.path.show'))
            ->assertSessionHas('status');

        $session->refresh();
        $this->assertSame(StudySession::STATUS_DONE, $session->status);
        $this->assertSame(100, $session->quiz_percent);
        $this->assertSame(1, $path->fresh()->completed_sessions);
        // Đạt hết thì không ôn lại chính các chủ đề vừa kiểm tra (mục chống quên / lỗi cũ là chuyện khác — xem test 20/60/20).
        $quizTopics = Question::whereIn('id', $session->quiz_question_ids)->pluck('topic_id');
        $this->assertSame(0, $path->items()->where('origin', 'review')->whereIn('learning_path_items.topic_id', $quizTopics)->count());
    }

    // --- Cấu trúc buổi 20% ôn dễ quên · 60% mới · 20% lỗi gần đây (đặc tả Logic §5) -----------------

    /** Chủ đề thuộc lộ trình nhưng không nằm trong bài kiểm tra cuối buổi đầu tiên. */
    private function otherPathTopicId(User $student, StudySession $session): int
    {
        $quizTopics = Question::whereIn('id', $session->quiz_question_ids)->pluck('topic_id');
        $id = $this->pathFor($student)->items()->whereNotNull('learning_path_items.topic_id')
            ->whereNotIn('learning_path_items.topic_id', $quizTopics)->value('learning_path_items.topic_id');
        $this->assertNotNull($id, 'Dữ liệu mẫu phải có chủ đề ngoài bài kiểm tra.');

        return (int) $id;
    }

    public function test_good_quiz_still_schedules_review_of_a_topic_not_practiced_for_a_week(): void
    {
        $student = $this->makeStudent();
        $session = $this->sessionAtQuiz($student);
        $topicId = $this->otherPathTopicId($student, $session);

        StudentTopicMastery::updateOrCreate(['user_id' => $student->id, 'topic_id' => $topicId], [
            'mastery_score' => 90, 'correct_count' => 9, 'wrong_count' => 1,
            'last_practiced_at' => now()->subDays(LearningPathService::FORGET_AFTER_DAYS + 3),
        ]);

        $this->paths()->submitQuiz($session, $this->correctAnswers($session));

        $item = $this->pathFor($student)->items()->where('origin', 'review')->where('learning_path_items.topic_id', $topicId)->first();
        $this->assertNotNull($item);
        $this->assertStringStartsWith('Ôn lại kẻo quên', $item->title);
        $this->assertSame($session->session_no + 1, StudySession::find($item->study_session_id)->session_no);
    }

    public function test_good_quiz_reinforces_the_most_frequent_recent_mistake(): void
    {
        $student = $this->makeStudent();
        $session = $this->sessionAtQuiz($student);
        $topicId = $this->otherPathTopicId($student, $session);
        $questionId = Question::where('topic_id', $topicId)->value('id') ?? Question::value('id');

        foreach (range(1, 6) as $i) {
            QuestionAttempt::create([
                'user_id' => $student->id, 'question_id' => $questionId, 'topic_id' => $topicId,
                'context' => QuestionAttempt::CONTEXT_PRACTICE, 'difficulty' => 'easy',
                'answer' => ['value' => 'x'], 'is_correct' => false, 'score' => 0,
            ]);
        }

        $this->paths()->submitQuiz($session, $this->correctAnswers($session));

        $item = $this->pathFor($student)->items()->where('origin', 'review')->where('learning_path_items.topic_id', $topicId)->first();
        $this->assertNotNull($item);
        $this->assertStringStartsWith('Củng cố lỗi sai', $item->title);
    }

    public function test_spaced_review_never_adds_more_than_two_items_to_a_session(): void
    {
        $student = $this->makeStudent();
        $session = $this->sessionAtQuiz($student);

        $this->paths()->submitQuiz($session, array_slice($this->correctAnswers($session), 0, 3, true));

        $next = $this->pathFor($student)->sessions()->where('session_no', $session->session_no + 1)->first();
        $this->assertLessThanOrEqual(2, $next->items()->where('origin', 'review')->count());
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

    /** @return array<int, mixed> đáp án đúng cho bộ câu cuối buổi, giữ thứ tự câu */
    private function correctAnswers(StudySession $session): array
    {
        $ids = $session->fresh()->quiz_question_ids;
        $questions = Question::whereIn('id', $ids)->with('options')->get()->sortBy(fn ($q) => array_search($q->id, $ids));

        return $questions->mapWithKeys(fn ($q) => [$q->id => match ($q->type) {
            'single_choice' => (string) $q->options->firstWhere('is_correct', true)->id,
            'multiple_choice' => $q->options->where('is_correct', true)->pluck('id')->map(fn ($v) => (string) $v)->all(),
            'true_false' => $q->correct_answer['value'] ? '1' : '0',
            'fill_blank' => collect($q->correct_answer['blanks'])->map(fn ($b) => $b[0])->all(),
            'short_answer' => $q->correct_answer['accepted'][0],
        }])->all();
    }

    private function sessionAtQuiz(User $student): StudySession
    {
        $this->completePlacement($student);
        $session = $this->pathFor($student)->sessions()->orderBy('session_no')->first();
        $this->finishSessionItems($session);
        $this->paths()->quizQuestions($session->fresh());

        return $session->fresh();
    }

    public function test_quiz_score_maps_to_next_step_rule(): void
    {
        $this->assertSame(LearningPathService::NEXT_NEW, $this->paths()->nextStepFor(80));
        $this->assertSame(LearningPathService::NEXT_REVIEW_SHARE, $this->paths()->nextStepFor(79));
        $this->assertSame(LearningPathService::NEXT_REVIEW_SHARE, $this->paths()->nextStepFor(50));
        $this->assertSame(LearningPathService::NEXT_REVIEW_FIRST, $this->paths()->nextStepFor(49));
    }

    public function test_mid_score_learns_new_lessons_with_a_review_share(): void
    {
        $student = $this->makeStudent();
        $session = $this->sessionAtQuiz($student);
        $this->assertCount(5, $session->quiz_question_ids);

        // Đúng 3/5 câu → khoảng 50–79% (điểm từng câu khác nhau) → nhánh "bài mới + ~30% ôn".
        $answers = array_slice($this->correctAnswers($session), 0, 3, true);
        $result = $this->paths()->submitQuiz($session, $answers);

        $this->assertGreaterThanOrEqual(LearningPathService::QUIZ_PASS_PERCENT, $result['percent']);
        $this->assertLessThan(LearningPathService::QUIZ_ADVANCE_PERCENT, $result['percent']);
        $this->assertSame(LearningPathService::NEXT_REVIEW_SHARE, $result['next']);

        $path = $this->pathFor($student);
        $reviews = $path->items()->where('origin', 'review')->get();
        $this->assertCount(LearningPathService::reviewItemsPerSession(), $reviews);

        // Mục ôn nằm chung buổi kế tiếp với bài mới, không chèn buổi riêng.
        $next = StudySession::find($reviews[0]->study_session_id);
        $this->assertSame($session->session_no + 1, $next->session_no);
        $this->assertTrue($next->items()->where('origin', 'plan')->exists());
    }

    public function test_low_score_inserts_a_review_session_before_new_lessons(): void
    {
        $student = $this->makeStudent();
        $session = $this->sessionAtQuiz($student);
        $path = $this->pathFor($student);
        $planned = $path->sessions()->where('session_no', $session->session_no + 1)->first();

        $result = $this->paths()->submitQuiz($session, []);

        $this->assertSame(LearningPathService::NEXT_REVIEW_FIRST, $result['next']);

        $reviewSession = $path->sessions()->where('session_no', $session->session_no + 1)->first();
        $this->assertNotSame($planned?->id, $reviewSession->id);
        $this->assertTrue($reviewSession->items()->get()->every(fn ($i) => $i->origin === 'review'));
        $this->assertSame($reviewSession->id, $this->paths()->currentSession($path)->id, 'Buổi hiện tại phải là buổi ôn.');

        if ($planned) {
            $this->assertSame($session->session_no + 2, $planned->fresh()->session_no, 'Bài mới lùi lại một buổi.');
        }
    }

    // --- Kiểm tra cuối buổi 15 phút (đặc tả module 5) ---------------------------------------------

    public function test_opening_quiz_starts_a_fifteen_minute_server_timer(): void
    {
        $student = $this->makeStudent();
        $this->completePlacement($student);
        $session = $this->pathFor($student)->sessions()->first();
        $this->finishSessionItems($session);

        $this->actingAs($student)->get(route('student.path.quiz', $session))->assertOk()->assertSee('quiz-timer', false);
        $expires = $session->fresh()->quiz_expires_at;
        $this->assertEqualsWithDelta(now()->addMinutes(15)->timestamp, $expires->timestamp, 5);

        // Tải lại trang không được đặt lại đồng hồ.
        $this->travel(5)->minutes();
        $this->actingAs($student)->get(route('student.path.quiz', $session))->assertOk();
        $this->assertEquals($expires, $session->fresh()->quiz_expires_at);
    }

    public function test_answers_sent_after_the_deadline_are_not_graded(): void
    {
        $student = $this->makeStudent();
        $session = $this->sessionAtQuiz($student);
        $answers = $this->correctAnswers($session);

        $this->travel(16)->minutes();

        $this->actingAs($student)
            ->post(route('student.path.quiz.submit', $session), ['answers' => $answers])
            ->assertRedirect(route('student.path.show'))
            ->assertSessionHas('error');

        $session->refresh();
        $this->assertSame(StudySession::STATUS_DONE, $session->status);
        $this->assertSame(0, $session->quiz_percent);
        $this->assertTrue($session->quiz_auto_submitted);
    }

    public function test_submitting_within_grace_period_is_still_graded(): void
    {
        $student = $this->makeStudent();
        $session = $this->sessionAtQuiz($student);
        $answers = $this->correctAnswers($session);

        // Đồng hồ trình duyệt tự nộp lúc 00:00, request tới server trễ vài giây.
        $this->travel(15 * 60 + 10)->seconds();

        $this->actingAs($student)->post(route('student.path.quiz.submit', $session), ['answers' => $answers]);

        $this->assertSame(100, $session->fresh()->quiz_percent);
        $this->assertFalse($session->fresh()->quiz_auto_submitted);
    }

    public function test_abandoned_quiz_is_closed_by_the_finalize_command(): void
    {
        $student = $this->makeStudent();
        $session = $this->sessionAtQuiz($student);

        $this->travel(20)->minutes();
        $this->artisan('exams:finalize-expired')->assertSuccessful();

        $session->refresh();
        $this->assertSame(StudySession::STATUS_DONE, $session->status);
        $this->assertTrue($session->quiz_auto_submitted);
        $this->assertEquals($session->quiz_expires_at, $session->quiz_submitted_at);
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
        app(ChildLinkService::class)->linkByCode($parent, $student->studentProfile()->value('link_code'));

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
