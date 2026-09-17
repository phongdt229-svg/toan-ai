<?php

namespace Tests\Feature\AI;

use App\Models\AiGenerationDraft;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;

class ContentGeneratorTest extends AiTestCase
{
    private User $teacher;
    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = $this->makeTeacher();
        $this->topic = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();
    }

    private function requestQuestions(array $extra = []): AiGenerationDraft
    {
        $this->actingAs($this->teacher)->post(route('teacher.ai.questions'), [
            'grade_id' => $this->grade->id,
            'topic_id' => $this->topic->id,
            'count' => 5, 'easy' => 40, 'medium' => 40, 'hard' => 20,
            'types' => ['single_choice'],
            ...$extra,
        ]);

        return AiGenerationDraft::latest('id')->firstOrFail();
    }

    public function test_generating_questions_creates_reviewable_draft_following_ratio(): void
    {
        $draft = $this->requestQuestions();

        // Queue chạy sync trong test → nháp đã sẵn sàng.
        $this->assertSame('ready', $draft->status);
        $this->assertSame(['easy' => 2, 'medium' => 2, 'hard' => 1], $draft->output['plan']);
        $this->assertCount(5, $draft->items());
        $this->assertSame('pending', $draft->items()[0]['status']);

        // Chưa có câu hỏi nào vào ngân hàng — AI không tự xuất bản (§12).
        $this->assertSame(0, Question::where('source', 'ai')->count());

        $this->actingAs($this->teacher)->get(route('teacher.ai.show', $draft))->assertOk()->assertSee('Chấp nhận');
    }

    public function test_structurally_invalid_ai_items_are_dropped(): void
    {
        $this->fake()->push(['questions' => [
            ['type' => 'single_choice', 'difficulty' => 'easy', 'content' => 'Hợp lệ', 'options' => ['a', 'b'], 'correct' => [1]],
            ['type' => 'single_choice', 'difficulty' => 'easy', 'content' => 'Hai đáp án đúng', 'options' => ['a', 'b'], 'correct' => [0, 1]],
            ['type' => 'single_choice', 'difficulty' => 'easy', 'content' => 'Chỉ số sai', 'options' => ['a', 'b'], 'correct' => [5]],
            ['type' => 'essay', 'difficulty' => 'easy', 'content' => 'Loại không được yêu cầu'],
        ]]);

        $draft = $this->requestQuestions();

        $this->assertCount(1, $draft->items());
        $this->assertSame(3, $draft->output['dropped']);
    }

    public function test_accept_creates_published_ai_question_once(): void
    {
        $draft = $this->requestQuestions();

        $this->actingAs($this->teacher)->post(route('teacher.ai.accept', [$draft, 0]))->assertSessionHas('status');

        $q = Question::where('source', 'ai')->with('options')->firstOrFail();
        $this->assertSame('published', $q->status);
        $this->assertSame($this->teacher->id, $q->created_by);
        $this->assertSame($this->topic->id, $q->topic_id);
        $this->assertCount(1, $q->options->where('is_correct', true));
        $this->assertSame('accepted', $draft->fresh()->items()[0]['status']);

        // Bấm lại không tạo trùng.
        $this->actingAs($this->teacher)->post(route('teacher.ai.accept', [$draft, 0]))->assertSessionHas('error');
        $this->assertSame(1, Question::where('source', 'ai')->count());
    }

    public function test_reject_and_regenerate(): void
    {
        $draft = $this->requestQuestions();
        $before = $draft->items()[1]['content'];

        $this->actingAs($this->teacher)->post(route('teacher.ai.reject', [$draft, 0]));
        $this->assertSame('rejected', $draft->fresh()->items()[0]['status']);

        $this->fake()->push(['questions' => [[
            'type' => 'single_choice', 'difficulty' => $draft->items()[1]['difficulty'],
            'content' => 'Câu mới hoàn toàn', 'options' => ['x', 'y'], 'correct' => [0],
        ]]]);
        $this->actingAs($this->teacher)->post(route('teacher.ai.regenerate', [$draft, 1]))->assertSessionHas('status');

        $after = $draft->fresh()->items()[1];
        $this->assertNotSame($before, $after['content']);
        $this->assertSame('pending', $after['status']);
    }

    public function test_edit_prefills_form_and_saving_marks_item_accepted(): void
    {
        $draft = $this->requestQuestions();

        $this->actingAs($this->teacher)
            ->get(route('teacher.ai.edit', [$draft, 0]))
            ->assertRedirect(route('teacher.questions.create'))
            ->assertSessionHasInput('ai_draft_id', $draft->id);

        $item = $draft->items()[0];
        $this->actingAs($this->teacher)->post(route('teacher.questions.store'), [
            'grade_id' => $this->grade->id, 'topic_id' => $this->topic->id, 'type' => 'single_choice',
            'content' => '<p>'.$item['content'].' (đã sửa)</p>', 'difficulty' => $item['difficulty'], 'points' => 1,
            'status' => 'published', 'source' => 'ai', 'ai_draft_id' => $draft->id, 'ai_item_index' => 0,
            'options' => array_map(fn ($o) => ['content' => $o], $item['options']),
            'correct_options' => array_map('strval', $item['correct']),
        ])->assertRedirect(route('teacher.ai.show', $draft));

        $this->assertSame('accepted', $draft->fresh()->items()[0]['status']);
        $this->assertStringContainsString('(đã sửa)', Question::where('source', 'ai')->value('content'));
    }

    public function test_lesson_draft_becomes_unpublished_lesson_with_sanitized_sections(): void
    {
        $this->fake()->push(['sections' => [
            ['type' => 'theory', 'title' => 'Quy tắc', 'content' => '<p>Lý thuyết $a+b$</p><script>alert(1)</script>'],
            ['type' => 'bogus', 'title' => 'Bỏ', 'content' => '<p>x</p>'],
        ]]);

        $this->actingAs($this->teacher)->post(route('teacher.ai.lesson'), [
            'grade_id' => $this->grade->id, 'topic_id' => $this->topic->id,
            'title' => 'Cộng ba phân số', 'difficulty' => 'medium',
        ]);
        $draft = AiGenerationDraft::latest('id')->firstOrFail();

        $this->assertCount(1, $draft->output['sections']);
        $this->assertStringNotContainsString('<script', $draft->output['sections'][0]['content']);

        $this->actingAs($this->teacher)->post(route('teacher.ai.create-lesson', $draft))->assertRedirect();

        $lesson = Lesson::where('title', 'Cộng ba phân số')->firstOrFail();
        $this->assertSame(Lesson::STATUS_DRAFT, $lesson->status, 'AI không được tự xuất bản bài học.');
        $this->assertSame(1, $lesson->sections()->count());

        // Không tạo được lần thứ hai từ cùng bản nháp.
        $this->actingAs($this->teacher)->post(route('teacher.ai.create-lesson', $draft))->assertSessionHas('error');
    }

    public function test_provider_failure_marks_draft_failed(): void
    {
        $this->fake()->failNext();

        $draft = $this->requestQuestions();

        $this->assertSame('failed', $draft->status);
        $this->assertNotEmpty($draft->error);
    }

    public function test_quota_exceeded_creates_no_draft(): void
    {
        config(['ai.daily_limits.teacher' => 0]);

        $this->actingAs($this->teacher)->post(route('teacher.ai.questions'), [
            'grade_id' => $this->grade->id, 'topic_id' => $this->topic->id,
            'count' => 3, 'easy' => 100, 'medium' => 0, 'hard' => 0, 'types' => ['single_choice'],
        ])->assertSessionHas('error');

        $this->assertSame(0, AiGenerationDraft::count());
    }

    public function test_rewrite_returns_sanitized_html(): void
    {
        $this->fake()->push('<p>Dễ hiểu hơn</p><img src=x onerror=alert(1)>');

        $this->actingAs($this->teacher)
            ->postJson(route('teacher.ai.rewrite'), ['content' => '<p>Nội dung gốc</p>', 'mode' => 'simplify'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing(['html' => '<img src=x onerror=alert(1)>']);

        $this->assertStringNotContainsString('onerror', $this->actingAs($this->teacher)
            ->postJson(route('teacher.ai.rewrite'), ['content' => 'x', 'mode' => 'summarize'])->json('data.html') ?? '');
    }

    public function test_drafts_are_private_to_their_author(): void
    {
        $draft = $this->requestQuestions();
        $other = $this->makeTeacher();

        $this->actingAs($other)->get(route('teacher.ai.show', $draft))->assertForbidden();
        $this->actingAs($other)->post(route('teacher.ai.accept', [$draft, 0]))->assertForbidden();
    }

    public function test_topic_must_belong_to_selected_grade(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.ai.questions'), [
            'grade_id' => \App\Models\Grade::where('level', 9)->value('id'), 'topic_id' => $this->topic->id,
            'count' => 3, 'easy' => 100, 'medium' => 0, 'hard' => 0, 'types' => ['single_choice'],
        ])->assertStatus(422);
    }

    public function test_students_cannot_use_teacher_ai(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('teacher.ai.index'))->assertForbidden();
    }

    public function test_admin_usage_page_shows_fake_provider_warning_and_totals(): void
    {
        $this->requestQuestions();

        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.ai-usage.index'))
            ->assertOk()
            ->assertSee('FakeProvider')
            ->assertSee('GV: tạo câu hỏi');
    }
}
