<?php

namespace Tests\Feature\Parents;

use App\Models\Role;
use App\Models\StudySchedule;
use App\Models\User;
use App\Notifications\StudyScheduleChanged;
use App\Services\Auth\AccountDeletionService;
use App\Services\Auth\DataExportService;
use App\Services\Learning\StudyScheduleService;
use Illuminate\Support\Facades\Notification;

/** Lịch học tuần (D-01): học sinh tự đặt, phụ huynh đã liên kết sửa được, bên kia được báo. */
class StudyScheduleTest extends ParentTestCase
{
    /** @return array<string, mixed> Thứ 2 + Thứ 4 lúc 19:30, các ngày khác tắt. */
    private function form(array $override = []): array
    {
        $days = [];
        foreach (array_keys(StudySchedule::WEEKDAYS) as $weekday) {
            $days[$weekday] = ['enabled' => '0', 'start_time' => '19:30', 'duration_minutes' => '45'];
        }
        $days[1]['enabled'] = '1';
        $days[3] = ['enabled' => '1', 'start_time' => '20:00', 'duration_minutes' => '60'];

        return ['days' => array_replace($days, $override)];
    }

    public function test_student_sets_own_weekly_schedule_and_parents_are_told(): void
    {
        Notification::fake();
        $parent = $this->makeParent();
        $student = $this->makeStudent();
        $this->link($parent, $student);

        $this->actingAs($student)->put(route('student.schedule.update'), $this->form())
            ->assertRedirect()->assertSessionHas('status');

        $schedule = StudySchedule::where('student_id', $student->id)->orderBy('weekday')->get();
        $this->assertSame([1, 3], $schedule->pluck('weekday')->all());
        $this->assertSame('20:00', $schedule[1]->startLabel());
        $this->assertSame(60, $schedule[1]->duration_minutes);

        Notification::assertSentTo($parent, StudyScheduleChanged::class);
        Notification::assertNotSentTo($student, StudyScheduleChanged::class);
    }

    public function test_saving_again_replaces_the_whole_week(): void
    {
        $student = $this->makeStudent();
        $this->actingAs($student)->put(route('student.schedule.update'), $this->form());

        // Tắt Thứ 2, bật Chủ nhật.
        $this->actingAs($student)->put(route('student.schedule.update'), $this->form([
            1 => ['enabled' => '0', 'start_time' => '19:30', 'duration_minutes' => '45'],
            7 => ['enabled' => '1', 'start_time' => '09:00', 'duration_minutes' => '90'],
        ]));

        $this->assertSame([3, 7], StudySchedule::where('student_id', $student->id)->orderBy('weekday')->pluck('weekday')->all());
    }

    public function test_linked_parent_edits_child_schedule_and_child_is_told(): void
    {
        Notification::fake();
        $parent = $this->makeParent();
        $student = $this->makeStudent();
        $this->link($parent, $student);

        $this->actingAs($parent)->get(route('parent.children.schedule.edit', $student))->assertOk()->assertSee('Lịch học của');
        $this->actingAs($parent)->put(route('parent.children.schedule.update', $student), $this->form())
            ->assertSessionHas('status');

        $this->assertSame($parent->id, StudySchedule::where('student_id', $student->id)->value('updated_by'));
        Notification::assertSentTo($student, StudyScheduleChanged::class);
    }

    public function test_unlinked_parent_and_teacher_cannot_touch_the_schedule(): void
    {
        $student = $this->makeStudent();
        $stranger = $this->makeParent();

        $this->actingAs($stranger)->get(route('parent.children.schedule.edit', $student))->assertForbidden();
        $this->actingAs($stranger)->put(route('parent.children.schedule.update', $student), $this->form())->assertForbidden();

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole(Role::TEACHER);
        $this->assertFalse($teacher->can('manage-study-schedule', $student));

        $this->assertDatabaseCount('study_schedules', 0);
    }

    public function test_enabled_day_needs_valid_time_and_allowed_duration(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->put(route('student.schedule.update'), $this->form([
            1 => ['enabled' => '1', 'start_time' => '25:99', 'duration_minutes' => '7'],
        ]))->assertSessionHasErrors(['days.1.start_time', 'days.1.duration_minutes']);

        $this->assertDatabaseCount('study_schedules', 0);
    }

    public function test_today_slot_is_shown_to_student_and_parent(): void
    {
        $parent = $this->makeParent();
        $student = $this->makeStudent();
        $this->link($parent, $student);

        StudySchedule::create([
            'student_id' => $student->id, 'weekday' => today()->isoWeekday(), 'start_time' => '18:15', 'duration_minutes' => 45,
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->assertSee('Lịch hôm nay')->assertSee('18:15');
        $this->actingAs($parent)->get(route('parent.children.show', $student))->assertOk()->assertSee('18:15');
    }

    public function test_slot_knows_its_window_on_a_given_day(): void
    {
        $student = $this->makeStudent();
        $slot = StudySchedule::create([
            'student_id' => $student->id, 'weekday' => 2, 'start_time' => '19:30', 'duration_minutes' => 60,
        ]);

        $tuesday = today()->startOfWeek()->addDay();
        $this->assertSame('19:30', $slot->fresh()->startsOn($tuesday)->format('H:i'));
        $this->assertSame('20:30', $slot->fresh()->endsOn($tuesday)->format('H:i'));
        $this->assertSame($slot->id, app(StudyScheduleService::class)->slotOn($student, $tuesday)->id);
    }

    public function test_schedule_is_exported_and_wiped_with_the_account(): void
    {
        $student = $this->makeStudent();
        $this->actingAs($student)->put(route('student.schedule.update'), $this->form());

        $export = app(DataExportService::class)->build($student);
        $this->assertCount(2, $export['learning']['study_schedule']);

        app(AccountDeletionService::class)->request($student);
        User::withTrashed()->whereKey($student->id)
            ->update(['deleted_at' => now()->subDays(AccountDeletionService::GRACE_DAYS + 1)]);
        $this->artisan('accounts:purge')->assertSuccessful();

        $this->assertDatabaseCount('study_schedules', 0);
    }
}
