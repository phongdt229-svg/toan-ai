<?php

namespace Tests\Feature\Qa;

use App\Models\Grade;
use App\Models\QaQuestion;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\QaAnswerAccepted;
use App\Notifications\QaAnswerPosted;
use App\Services\Learning\QaService;
use Database\Seeders\CurriculumSkeletonSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Hỏi đáp cho học sinh (đợt 24/09).
 *
 * Người dùng là trẻ em nên phần đáng canh nhất không phải "đăng được câu hỏi"
 * mà là: lọc HTML, tự ẩn khi bị báo, và không ai sửa được bài của người khác.
 */
class QaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, CurriculumSkeletonSeeder::class]);
        Notification::fake();
    }

    private function student(string $name = 'Học sinh'): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $user->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        return $user;
    }

    private function teacher(): User
    {
        $user = User::factory()->create(['name' => 'Cô giáo', 'status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::TEACHER);

        return $user;
    }

    private function topic(): Topic
    {
        return Topic::firstOrFail();
    }

    private function ask(User $user, string $title = 'Em quy đồng mẫu số xong thì cộng sai'): QaQuestion
    {
        return app(QaService::class)->ask($user, $this->topic(), $title, 'Đề bài là 1/2 + 1/3, em làm ra 2/5.');
    }

    // --- Đặt câu hỏi và trả lời ------------------------------------------------------------

    public function test_a_student_can_ask_and_another_can_answer(): void
    {
        $asker = $this->student('Người hỏi');
        $helper = $this->student('Người giúp');

        $this->actingAs($asker)->post(route('student.qa.store'), [
            'topic_id' => $this->topic()->id,
            'title' => 'Em quy đồng mẫu số xong thì cộng sai',
            'body' => 'Đề bài là 1/2 + 1/3, em ra 2/5 mà đáp án là 5/6.',
        ])->assertRedirect();

        $question = QaQuestion::firstOrFail();

        $this->actingAs($helper)->post(route('student.qa.answer', $question), [
            'body' => 'Cộng phân số thì quy đồng rồi chỉ cộng tử, giữ nguyên mẫu nhé.',
        ])->assertSessionHas('status');

        $this->assertSame(1, $question->refresh()->answers_count);
        // Người hỏi phải biết là có người trả lời.
        Notification::assertSentTo($asker, QaAnswerPosted::class);
    }

    public function test_answering_your_own_question_does_not_notify_yourself(): void
    {
        $asker = $this->student();
        $question = $this->ask($asker);

        $this->actingAs($asker)->post(route('student.qa.answer', $question), [
            'body' => 'Em tự nghĩ ra rồi: phải quy đồng trước khi cộng.',
        ]);

        Notification::assertNothingSent();
    }

    public function test_a_question_that_is_too_short_is_refused(): void
    {
        $this->actingAs($this->student())->from(route('student.qa.create'))->post(route('student.qa.store'), [
            'topic_id' => $this->topic()->id,
            'title' => 'Giúp em',
            'body' => 'Khó quá',
        ])->assertSessionHasErrors(['title', 'body']);

        $this->assertDatabaseCount('qa_questions', 0);
    }

    // --- Lọc nội dung ----------------------------------------------------------------------

    public function test_html_is_cleaned_when_saved_not_when_shown(): void
    {
        $student = $this->student();

        $this->actingAs($student)->post(route('student.qa.store'), [
            'topic_id' => $this->topic()->id,
            'title' => 'Câu hỏi có mã độc kèm theo',
            'body' => 'Bài này khó quá <script>alert("xin chao")</script> giúp em với.',
        ]);

        // Luật chung của repo: lọc lúc LƯU, nên trong DB đã sạch.
        $this->assertStringNotContainsString('<script', QaQuestion::firstOrFail()->body);
    }

    // --- Chọn lời giải -----------------------------------------------------------------------

    public function test_the_asker_can_pick_the_best_answer(): void
    {
        $asker = $this->student('Người hỏi');
        $helper = $this->student('Người giúp');
        $question = $this->ask($asker);
        $answer = app(QaService::class)->answer($helper, $question, 'Quy đồng rồi cộng tử số thôi em.');

        $this->actingAs($asker)->post(route('student.qa.accept', [$question, $answer]))
            ->assertSessionHas('status');

        $question->refresh();
        $this->assertSame($answer->id, $question->best_answer_id);
        $this->assertTrue($question->isResolved());
        Notification::assertSentTo($helper, QaAnswerAccepted::class);
    }

    public function test_a_stranger_cannot_pick_the_best_answer(): void
    {
        $asker = $this->student('Người hỏi');
        $stranger = $this->student('Người lạ');
        $question = $this->ask($asker);
        $answer = app(QaService::class)->answer($stranger, $question, 'Câu trả lời của người lạ.');

        $this->actingAs($stranger)->post(route('student.qa.accept', [$question, $answer]))
            ->assertForbidden();

        $this->assertNull($question->refresh()->best_answer_id);
    }

    public function test_a_teacher_can_pick_the_best_answer_too(): void
    {
        $asker = $this->student('Người hỏi');
        $question = $this->ask($asker);
        $answer = app(QaService::class)->answer($this->student('Bạn khác'), $question, 'Cách làm đúng là quy đồng.');

        $this->actingAs($this->teacher())->post(route('student.qa.accept', [$question, $answer]))
            ->assertSessionHas('status');

        $this->assertSame($answer->id, $question->refresh()->best_answer_id);
    }

    // --- Báo xấu và tự ẩn --------------------------------------------------------------------

    public function test_enough_reports_hide_the_content_without_waiting_for_a_grown_up(): void
    {
        $question = $this->ask($this->student('Người viết'));

        for ($i = 1; $i <= QaService::REPORTS_TO_HIDE; $i++) {
            $this->actingAs($this->student("Người báo {$i}"))
                ->post(route('student.qa.report', ['cau-hoi', $question->id]))
                ->assertSessionHas('status');
        }

        // Không để nội dung bẩn nằm chờ suốt đêm tới lúc có người trực.
        $this->assertTrue($question->refresh()->isHidden());
        $this->assertSame(QaService::REPORTS_TO_HIDE, $question->reports_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'qa.auto_hidden']);
    }

    public function test_one_person_cannot_report_the_same_thing_many_times(): void
    {
        $question = $this->ask($this->student('Người viết'));
        $reporter = $this->student('Người báo');

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($reporter)->post(route('student.qa.report', ['cau-hoi', $question->id]));
        }

        // Bấm mười lần mà ẩn được bài của bạn khác thì thành công cụ bắt nạt.
        $this->assertSame(1, $question->refresh()->reports_count);
        $this->assertFalse($question->isHidden());
    }

    public function test_you_cannot_report_your_own_post(): void
    {
        $author = $this->student('Người viết');
        $question = $this->ask($author);

        $this->actingAs($author)->post(route('student.qa.report', ['cau-hoi', $question->id]))
            ->assertForbidden();
    }

    // --- Kiểm duyệt ---------------------------------------------------------------------------

    public function test_a_teacher_can_hide_and_restore(): void
    {
        $question = $this->ask($this->student());
        $teacher = $this->teacher();

        $this->actingAs($teacher)->post(route('student.qa.moderate', ['cau-hoi', $question->id]), ['hide' => 1]);
        $this->assertTrue($question->refresh()->isHidden());
        $this->assertDatabaseHas('audit_logs', ['action' => 'qa.hidden']);

        $this->actingAs($teacher)->post(route('student.qa.moderate', ['cau-hoi', $question->id]), ['hide' => 0]);
        $this->assertFalse($question->refresh()->isHidden());
        $this->assertDatabaseHas('audit_logs', ['action' => 'qa.restored']);
    }

    public function test_a_student_cannot_moderate(): void
    {
        $question = $this->ask($this->student('Người viết'));

        $this->actingAs($this->student('Người khác'))
            ->post(route('student.qa.moderate', ['cau-hoi', $question->id]), ['hide' => 1])
            ->assertForbidden();

        $this->assertFalse($question->refresh()->isHidden());
    }

    public function test_hidden_questions_disappear_from_the_list_but_the_author_still_sees_theirs(): void
    {
        $author = $this->student('Người viết');
        $question = $this->ask($author, 'Câu hỏi sẽ bị ẩn');
        app(QaService::class)->moderate($this->teacher(), $question, true);

        $this->actingAs($this->student('Người khác'))->get(route('student.qa.index'))
            ->assertOk()->assertDontSee('Câu hỏi sẽ bị ẩn');

        $this->actingAs($author)->get(route('student.qa.show', $question))
            ->assertOk()->assertSee('đang ẩn');

        $this->actingAs($this->student('Người khác'))->get(route('student.qa.show', $question))
            ->assertForbidden();
    }

    public function test_nobody_can_answer_a_hidden_question(): void
    {
        $question = $this->ask($this->student());
        app(QaService::class)->moderate($this->teacher(), $question, true);

        $this->actingAs($this->student('Người khác'))
            ->post(route('student.qa.answer', $question), ['body' => 'Câu trả lời cho bài đã bị ẩn.'])
            ->assertForbidden();
    }

    public function test_hiding_an_answer_lowers_the_count_on_the_question(): void
    {
        $question = $this->ask($this->student('Người hỏi'));
        $answer = app(QaService::class)->answer($this->student('Người giúp'), $question, 'Một câu trả lời đầy đủ.');
        $this->assertSame(1, $question->refresh()->answers_count);

        app(QaService::class)->moderate($this->teacher(), $answer, true);

        // Cột đếm sẵn phải khớp lại, nếu không danh sách nói dối.
        $this->assertSame(0, $question->refresh()->answers_count);
    }

    // --- Sửa câu hỏi ---------------------------------------------------------------------------

    public function test_a_question_cannot_be_edited_once_answered(): void
    {
        $asker = $this->student('Người hỏi');
        $question = $this->ask($asker);
        app(QaService::class)->answer($this->student('Người giúp'), $question, 'Câu trả lời đã có ở đây.');

        // Sửa sau khi được trả lời là cách kinh điển để biến câu hỏi hiền thành câu hỏi bẩn.
        $this->expectException(\RuntimeException::class);
        app(QaService::class)->editQuestion($question->refresh(), 'Tiêu đề mới hoàn toàn', 'Nội dung mới hoàn toàn.');
    }

    public function test_guests_cannot_reach_the_q_and_a(): void
    {
        $this->get(route('student.qa.index'))->assertRedirect(route('login'));
    }
}
