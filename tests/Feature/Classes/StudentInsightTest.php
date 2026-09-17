<?php

namespace Tests\Feature\Classes;

use App\Models\AssignmentStudent;
use App\Models\StudentTopicMastery;
use App\Models\TeacherComment;
use App\Models\Topic;
use App\Models\User;
use App\Services\Teaching\StudentInsightService;

class StudentInsightTest extends ClassroomTestCase
{
    /**
     * Gắn trực tiếp điểm cho một học sinh qua nhiều bài giao — kiểm tra logic lọc,
     * không cần làm bài thật từng lượt.
     *
     * @param  array<int, int|null>  $percents  null = chưa làm
     */
    private function seedHistory(User $student, $class, array $percents, bool $overdue = false): void
    {
        foreach ($percents as $i => $percent) {
            $assignment = $this->questionSetAssignment($class, [
                'title' => "Bài {$i}",
                'assign_to_all' => false,
                'student_ids' => [$student->id],
                'due_at' => now()->addDay(),
            ]);

            if ($overdue && $percent === null) {
                $assignment->update(['due_at' => now()->subDay()]);
            }

            if ($percent !== null) {
                AssignmentStudent::where('assignment_id', $assignment->id)->update([
                    'status' => 'completed',
                    'percent' => $percent,
                    'completed_at' => now()->addMinutes($i), // thứ tự thời gian = thứ tự mảng
                ]);
            }
        }
    }

    private function insightFor(User $student): array
    {
        return app(StudentInsightService::class)->studentsFor($this->teacher)->firstWhere('student.id', $student->id);
    }

    public function test_low_score_flag(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);

        $this->seedHistory($student, $class, [40, 45]);

        $insight = $this->insightFor($student);
        $this->assertSame(43, $insight['avg_percent']);
        $this->assertContains('low_score', $insight['flags']);
        $this->assertContains('needs_support', $insight['flags']);
    }

    public function test_not_done_counts_only_overdue_unsubmitted(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);

        // 1 quá hạn chưa làm, 1 chưa đến hạn chưa làm.
        $this->seedHistory($student, $class, [null], overdue: true);
        $this->seedHistory($student, $class, [null], overdue: false);

        $insight = $this->insightFor($student);
        $this->assertSame(1, $insight['overdue']);
        $this->assertContains('not_done', $insight['flags']);
        // Chỉ 1 bài quá hạn — chưa tới ngưỡng "cần hỗ trợ".
        $this->assertNotContains('needs_support', $insight['flags']);
    }

    public function test_two_overdue_means_needs_support(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);

        $this->seedHistory($student, $class, [null, null], overdue: true);

        $this->assertContains('needs_support', $this->insightFor($student)['flags']);
    }

    public function test_weak_topics_mean_needs_support(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);

        foreach (Topic::limit(2)->get() as $topic) {
            StudentTopicMastery::create([
                'user_id' => $student->id, 'topic_id' => $topic->id,
                'correct_count' => 1, 'wrong_count' => 9, 'mastery_score' => 10,
            ]);
        }

        $insight = $this->insightFor($student);
        $this->assertSame(2, $insight['weak_topics']);
        $this->assertContains('needs_support', $insight['flags']);
    }

    public function test_improving_requires_enough_history_and_real_gain(): void
    {
        $class = $this->makeClass();
        $improver = $this->makeStudent();
        $flat = $this->makeStudent();
        $tooFew = $this->makeStudent();
        $this->enroll($class, $improver, $flat, $tooFew);

        $this->seedHistory($improver, $class, [50, 55, 70, 75, 80]); // trước 52.5 → gần đây 75
        $this->seedHistory($flat, $class, [70, 72, 71, 73]);
        $this->seedHistory($tooFew, $class, [20, 90, 95]);           // chỉ 3 bài

        $this->assertContains('improving', $this->insightFor($improver)['flags']);
        $this->assertNotContains('improving', $this->insightFor($flat)['flags']);
        $this->assertNull($this->insightFor($tooFew)['trend']);
    }

    public function test_filter_page_and_dashboard_numbers(): void
    {
        $class = $this->makeClass();
        $weak = $this->makeStudent('Em Cần Giúp');
        $good = $this->makeStudent('Em Học Tốt');
        $this->enroll($class, $weak, $good);

        $this->seedHistory($weak, $class, [30]);
        $this->seedHistory($good, $class, [90]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.students.index', ['filter' => 'needs_support']))
            ->assertOk()
            ->assertSee('Em Cần Giúp')
            ->assertDontSee('Em Học Tốt');

        $stats = app(StudentInsightService::class)->dashboardStats($this->teacher);
        $this->assertSame(1, $stats['classes']);
        $this->assertSame(2, $stats['students']);
        $this->assertSame(2, $stats['open_assignments']);
        $this->assertSame(60, $stats['average_score']);
        $this->assertSame(1, $stats['students_needing_help']);

        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()->assertSee('Em Cần Giúp');
    }

    public function test_insights_only_include_this_teachers_classes(): void
    {
        $mine = $this->makeClass();
        $theirs = $this->makeClass($this->makeTeacher());
        $student = $this->makeStudent();
        $this->enroll($mine, $student);
        $this->enroll($theirs, $student);

        // Bài điểm thấp ở lớp người khác không được làm học sinh bị gắn cờ ở lớp mình.
        $otherAssignment = app(\App\Services\Teaching\AssignmentService::class)->create($theirs, $theirs->owner, [
            'title' => 'Lớp khác', 'type' => 'question_set', 'question_ids' => $this->autoGradableQuestionIds(),
            'assign_to_all' => true, 'allow_retry' => false,
        ]);
        AssignmentStudent::where('assignment_id', $otherAssignment->id)->update(['status' => 'completed', 'percent' => 5]);

        $insight = $this->insightFor($student);
        $this->assertNull($insight['avg_percent']);
        $this->assertNotContains('low_score', $insight['flags']);
    }

    // --- Nhận xét ---------------------------------------------------------------

    public function test_teacher_comments_on_own_student(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);

        $this->actingAs($this->teacher)
            ->post(route('teacher.students.comments.store', $student), [
                'content' => 'Em cần luyện thêm quy đồng.',
                'class_id' => $class->id,
                'visible_to_parent' => '1',
            ])
            ->assertSessionHas('status');

        $comment = TeacherComment::firstOrFail();
        $this->assertTrue($comment->visible_to_parent);

        $this->actingAs($this->teacher)
            ->get(route('teacher.students.show', $student))
            ->assertOk()
            ->assertSee('Em cần luyện thêm quy đồng.');
    }

    public function test_private_comment_flag_is_stored(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);

        $this->actingAs($this->teacher)->post(route('teacher.students.comments.store', $student), [
            'content' => 'Ghi chú nội bộ',
        ]);

        $this->assertFalse(TeacherComment::firstOrFail()->visible_to_parent);
    }

    public function test_teacher_cannot_view_or_comment_on_students_outside_their_classes(): void
    {
        $stranger = $this->makeStudent();

        $this->actingAs($this->teacher)->get(route('teacher.students.show', $stranger))->assertForbidden();
        $this->actingAs($this->teacher)
            ->post(route('teacher.students.comments.store', $stranger), ['content' => 'x'])
            ->assertForbidden();
    }

    public function test_only_author_can_delete_comment(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);

        $comment = TeacherComment::create([
            'teacher_id' => $this->teacher->id, 'student_id' => $student->id, 'content' => 'x',
        ]);

        $this->actingAs($this->makeTeacher())
            ->delete(route('teacher.students.comments.destroy', $comment))
            ->assertForbidden();
    }
}
