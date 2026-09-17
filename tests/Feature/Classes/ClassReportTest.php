<?php

namespace Tests\Feature\Classes;

use App\Models\AssignmentStudent;
use App\Models\StudentTopicMastery;
use App\Models\Topic;

class ClassReportTest extends ClassroomTestCase
{
    public function test_report_summarises_completion_scores_topics_and_students(): void
    {
        $class = $this->makeClass();
        $good = $this->makeStudent('Giỏi');
        $weak = $this->makeStudent('Yếu');
        $this->enroll($class, $good, $weak);

        $assignment = $this->questionSetAssignment($class, ['title' => 'Bài phân số', 'due_at' => now()->addDay()]);
        AssignmentStudent::where('assignment_id', $assignment->id)->where('student_id', $good->id)
            ->update(['status' => 'completed', 'percent' => 90, 'completed_at' => now()]);

        $topic = Topic::firstOrFail();
        foreach ([[$good, 90], [$weak, 30]] as [$student, $score]) {
            StudentTopicMastery::create([
                'user_id' => $student->id, 'topic_id' => $topic->id, 'correct_count' => 5, 'wrong_count' => 5, 'mastery_score' => $score,
            ]);
        }

        $response = $this->actingAs($this->teacher)->get(route('teacher.reports.index', ['lop' => $class->id]))->assertOk();

        $report = $response->viewData('report');
        $this->assertSame(2, $report['summary']['students']);
        $this->assertSame(50, $report['summary']['completion']);
        $this->assertSame(90, $report['summary']['average']);
        $this->assertSame(['topic' => $topic->name, 'percent' => 60, 'students' => 2, 'weak' => 1], $report['topics'][0]);
        // Học sinh chưa có điểm / điểm thấp xếp trước để giáo viên nhìn thấy ngay.
        $this->assertSame('Giỏi', $report['students']->last()['student']->name);

        $response->assertSee('Bài phân số')->assertSee('1/2 nộp');
    }

    public function test_csv_export_and_access_is_limited_to_own_classes(): void
    {
        $class = $this->makeClass();
        $this->enroll($class, $this->makeStudent('Nguyễn An'));

        $csv = $this->actingAs($this->teacher)->get(route('teacher.reports.export', $class));
        $csv->assertOk();
        $this->assertStringContainsString('Nguyễn An', $csv->streamedContent());

        $other = $this->makeTeacher();
        $this->actingAs($other)->get(route('teacher.reports.export', $class))->assertNotFound();
        $this->actingAs($other)->get(route('teacher.reports.index', ['lop' => $class->id]))->assertNotFound();
        $this->actingAs($other)->get(route('teacher.reports.index'))->assertOk()->assertSee('chưa có lớp');
    }
}
