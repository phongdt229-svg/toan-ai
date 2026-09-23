<?php

namespace Tests\Feature\Parents;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\AssignmentSubmission;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\SchoolClass;
use App\Models\StudentLessonProgress;
use App\Models\StudentTopicMastery;
use App\Models\TeacherComment;
use App\Models\Topic;
use App\Models\User;
use App\Services\Learning\StudentReportService;
use App\Support\Duration;

class ParentReportTest extends ParentTestCase
{
    private function teacher(): User
    {
        $t = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $t->assignRole('teacher');

        return $t;
    }

    public function test_curriculum_percent_counts_published_lessons_of_childs_grade(): void
    {
        $child = $this->makeStudent();
        $service = app(StudentReportService::class);

        // Mẫu có 2 bài đã xuất bản cho lớp 6.
        $this->assertSame(0, $service->curriculumPercent($child));

        StudentLessonProgress::create([
            'user_id' => $child->id,
            'lesson_id' => Lesson::where('slug', 'cong-hai-phan-so-cung-mau-so')->value('id'),
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        $this->assertSame(50, $service->curriculumPercent($child));
    }

    public function test_curriculum_percent_is_null_without_grade_content(): void
    {
        $child = $this->makeStudent();
        $child->studentProfile->update(['grade_id' => Grade::where('level', 12)->value('id')]);

        $this->assertNull(app(StudentReportService::class)->curriculumPercent($child->fresh()));
    }

    public function test_average_score_out_of_10_uses_graded_exams_and_assignments_only(): void
    {
        $child = $this->makeStudent();
        $teacher = $this->teacher();
        $service = app(StudentReportService::class);

        $this->assertNull($service->averageScoreOutOf10($child));

        $exam = Exam::create([
            'title' => 'Đề', 'slug' => 'de-'.uniqid(), 'grade_id' => $this->grade->id,
            'status' => 'published', 'created_by' => $teacher->id,
        ]);
        // 8/10 = 80%
        ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $child->id, 'started_at' => now(), 'expires_at' => now(),
            'submitted_at' => now(), 'status' => 'graded', 'score' => 8, 'total_points' => 10, 'question_order' => [],
        ]);
        // Lượt chờ chấm không tính.
        ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $child->id, 'started_at' => now(), 'expires_at' => now(),
            'submitted_at' => now(), 'status' => 'submitted', 'score' => 0, 'total_points' => 10, 'question_order' => [],
        ]);

        $class = SchoolClass::create([
            'name' => 'L', 'code' => 'ABCDEF', 'grade_id' => $this->grade->id, 'owner_teacher_id' => $teacher->id,
        ]);
        $assignment = Assignment::create([
            'class_id' => $class->id, 'teacher_id' => $teacher->id, 'title' => 'BT', 'type' => 'question_set',
            'published_at' => now(),
        ]);
        // 6/10 = 60%
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id, 'student_id' => $child->id, 'score' => 6, 'max_score' => 10,
            'submitted_at' => now(),
        ]);

        $this->assertSame(7.0, $service->averageScoreOutOf10($child));
    }

    public function test_study_time_sums_lessons_and_questions(): void
    {
        $child = $this->makeStudent();

        StudentLessonProgress::create([
            'user_id' => $child->id, 'lesson_id' => Lesson::value('id'), 'time_spent_seconds' => 3600,
        ]);
        QuestionAttempt::create([
            'user_id' => $child->id, 'question_id' => Question::value('id'), 'time_spent_seconds' => 1800,
        ]);

        $seconds = app(StudentReportService::class)->studySeconds($child);

        $this->assertSame(5400, $seconds);
        $this->assertSame('1h30', Duration::human($seconds));
        $this->assertSame('45 phút', Duration::human(2700));
    }

    public function test_daily_activity_covers_every_day_including_empty_ones(): void
    {
        $child = $this->makeStudent();
        $questionId = Question::value('id');

        $this->travel(-2)->days();
        QuestionAttempt::create(['user_id' => $child->id, 'question_id' => $questionId, 'is_correct' => true]);
        QuestionAttempt::create(['user_id' => $child->id, 'question_id' => $questionId, 'is_correct' => false]);
        $this->travelBack();

        $activity = app(StudentReportService::class)->dailyActivity($child, 7);

        $this->assertCount(7, $activity);
        $this->assertSame(now()->toDateString(), end($activity)['date']);
        $twoDaysAgo = collect($activity)->firstWhere('date', now()->subDays(2)->toDateString());
        $this->assertSame(2, $twoDaysAgo['answered']);
        $this->assertSame(1, $twoDaysAgo['correct']);
        $this->assertSame(0, collect($activity)->firstWhere('date', now()->toDateString())['answered']);
    }

    public function test_recommendation_points_to_unfinished_lesson_of_weak_topic(): void
    {
        $child = $this->makeStudent();
        $topic = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();

        StudentTopicMastery::create([
            'user_id' => $child->id, 'topic_id' => $topic->id,
            'correct_count' => 2, 'wrong_count' => 8, 'mastery_score' => 20,
        ]);
        // Bài đầu đã học → gợi ý bài tiếp theo chưa học.
        StudentLessonProgress::create([
            'user_id' => $child->id,
            'lesson_id' => Lesson::where('slug', 'cong-hai-phan-so-cung-mau-so')->value('id'),
            'status' => 'completed', 'completed_at' => now(),
        ]);

        $report = app(StudentReportService::class)->summary($child);
        $lessonRec = $report['recommendations']->firstWhere('type', 'review_lesson');

        $this->assertSame('Phép cộng phân số', $lessonRec->topic->name);
        $this->assertSame(Lesson::where('slug', 'cong-hai-phan-so-khac-mau-so')->value('id'), $lessonRec->target_id);
    }

    public function test_report_page_shows_only_parent_visible_comments(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $this->link($parent, $child);
        $teacher = $this->teacher();

        TeacherComment::create(['teacher_id' => $teacher->id, 'student_id' => $child->id,
            'content' => 'Con tiến bộ rõ ở phần quy đồng.', 'visible_to_parent' => true]);
        TeacherComment::create(['teacher_id' => $teacher->id, 'student_id' => $child->id,
            'content' => 'Ghi chú nội bộ giáo viên', 'visible_to_parent' => false]);

        $this->actingAs($parent)
            ->get(route('parent.children.show', $child))
            ->assertOk()
            ->assertSee('Con tiến bộ rõ ở phần quy đồng.')
            ->assertDontSee('Ghi chú nội bộ giáo viên');
    }

    public function test_dashboard_warns_about_overdue_assignments(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent('Lê Bé');
        $this->link($parent, $child);
        $teacher = $this->teacher();

        $class = SchoolClass::create([
            'name' => 'L', 'code' => 'QWERTY', 'grade_id' => $this->grade->id, 'owner_teacher_id' => $teacher->id,
        ]);
        $assignment = Assignment::create([
            'class_id' => $class->id, 'teacher_id' => $teacher->id, 'title' => 'Quá hạn', 'type' => 'question_set',
            'due_at' => now()->subDay(), 'published_at' => now()->subDays(3),
        ]);
        AssignmentStudent::create(['assignment_id' => $assignment->id, 'student_id' => $child->id]);

        $this->actingAs($parent)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('Lê Bé')
            ->assertSee('1 bài quá hạn chưa nộp');
    }

    public function test_settings_toggle_weekly_report(): void
    {
        $parent = $this->makeParent();

        $this->actingAs($parent)->put(route('parent.settings.update'), []);
        $this->assertFalse($parent->parentProfile()->value('weekly_report_enabled'));

        $this->actingAs($parent)->put(route('parent.settings.update'), ['weekly_report_enabled' => '1']);
        $this->assertTrue((bool) $parent->parentProfile()->value('weekly_report_enabled'));
    }
}
