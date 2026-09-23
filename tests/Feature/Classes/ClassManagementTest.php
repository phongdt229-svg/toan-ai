<?php

namespace Tests\Feature\Classes;

use App\Models\AssignmentStudent;
use App\Models\SchoolClass;
use App\Services\Teaching\ClassService;

class ClassManagementTest extends ClassroomTestCase
{
    public function test_teacher_creates_class_with_join_code_and_is_owner(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.classes.store'), ['name' => '6A1', 'grade_id' => $this->grade->id])
            ->assertRedirect();

        $class = SchoolClass::firstOrFail();

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $class->code);
        $this->assertDoesNotMatchRegularExpression('/[01OIL]/', $class->code);
        $this->assertTrue($class->hasTeacher($this->teacher));
        $this->assertTrue($class->isOwnedBy($this->teacher));
    }

    public function test_student_joins_with_code_case_and_space_insensitive(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();

        $messy = ' '.strtolower(substr($class->code, 0, 3)).' '.strtolower(substr($class->code, 3)).' ';

        $this->actingAs($student)
            ->post(route('student.classes.join'), ['code' => $messy])
            ->assertRedirect(route('student.classes.index'));

        $this->assertTrue($class->hasActiveStudent($student));
    }

    public function test_wrong_or_archived_code_is_rejected(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('student.classes.join'), ['code' => 'ZZZZZZ'])
            ->assertSessionHasErrors('code');

        $class->update(['status' => SchoolClass::STATUS_ARCHIVED]);

        $this->actingAs($student)->post(route('student.classes.join'), ['code' => $class->code])
            ->assertSessionHasErrors('code');
    }

    public function test_join_code_attempts_are_rate_limited(): void
    {
        $student = $this->makeStudent();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($student)->post(route('student.classes.join'), ['code' => "BAD{$i}XX"]);
        }

        $this->actingAs($student)
            ->post(route('student.classes.join'), ['code' => 'BADXXX'])
            ->assertStatus(429);
    }

    public function test_regenerated_code_invalidates_old_one(): void
    {
        $class = $this->makeClass();
        $old = $class->code;

        $this->actingAs($this->teacher)->post(route('teacher.classes.code', $class));

        $this->assertNotSame($old, $class->fresh()->code);
        $this->actingAs($this->makeStudent())
            ->post(route('student.classes.join'), ['code' => $old])
            ->assertSessionHasErrors('code');
    }

    public function test_teacher_adds_and_removes_student_keeping_history(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();

        $this->actingAs($this->teacher)
            ->post(route('teacher.classes.students.add', $class), ['email' => $student->email])
            ->assertSessionHas('status');

        $assignment = $this->questionSetAssignment($class);

        $this->actingAs($this->teacher)
            ->delete(route('teacher.classes.students.remove', [$class, $student]))
            ->assertSessionHas('status');

        $this->assertFalse($class->hasActiveStudent($student));
        // Rời lớp không xoá bài đã giao / điểm.
        $this->assertTrue(AssignmentStudent::where('assignment_id', $assignment->id)->where('student_id', $student->id)->exists());
    }

    public function test_add_by_email_rejects_non_students(): void
    {
        $class = $this->makeClass();
        $otherTeacher = $this->makeTeacher();

        $this->actingAs($this->teacher)
            ->post(route('teacher.classes.students.add', $class), ['email' => $otherTeacher->email])
            ->assertSessionHasErrors('email');
    }

    public function test_student_joining_later_receives_open_class_wide_assignments(): void
    {
        $class = $this->makeClass();
        $this->enroll($class, $this->makeStudent());

        $wholeClass = $this->questionSetAssignment($class, ['title' => 'Cả lớp']);
        $closed = $this->questionSetAssignment($class, ['title' => 'Đã đóng']);
        $closed->update(['status' => 'closed']);

        $latecomer = $this->makeStudent();
        $this->actingAs($latecomer)->post(route('student.classes.join'), ['code' => $class->code]);

        $this->assertTrue(AssignmentStudent::where('assignment_id', $wholeClass->id)->where('student_id', $latecomer->id)->exists());
        $this->assertFalse(AssignmentStudent::where('assignment_id', $closed->id)->where('student_id', $latecomer->id)->exists());
    }

    public function test_rejoining_after_removal_restores_membership(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        app(ClassService::class)->removeStudent($class, $student);

        $this->actingAs($student)->post(route('student.classes.join'), ['code' => $class->code]);

        $this->assertTrue($class->hasActiveStudent($student));
    }

    public function test_other_teacher_cannot_see_or_manage_class(): void
    {
        $class = $this->makeClass();
        $other = $this->makeTeacher();

        $this->actingAs($other)->get(route('teacher.classes.show', $class))->assertForbidden();
        $this->actingAs($other)
            ->post(route('teacher.classes.students.add', $class), ['email' => 'x@example.com'])
            ->assertForbidden();
    }

    public function test_assistant_manages_students_but_not_class_settings(): void
    {
        $class = $this->makeClass();
        $assistant = $this->makeTeacher();

        $this->actingAs($this->teacher)
            ->post(route('teacher.classes.assistants.add', $class), ['email' => $assistant->email])
            ->assertSessionHas('status');

        $this->actingAs($assistant)->get(route('teacher.classes.show', $class))->assertOk();
        $this->actingAs($assistant)
            ->post(route('teacher.classes.students.add', $class), ['email' => $this->makeStudent()->email])
            ->assertSessionHas('status');

        $this->actingAs($assistant)->post(route('teacher.classes.code', $class))->assertForbidden();
        $this->actingAs($assistant)->post(route('teacher.classes.archive', $class))->assertForbidden();
    }
}
