<?php

namespace Tests\Feature\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiUsage;
use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\SchoolClass;
use App\Models\User;

class TutorApiTest extends AiTestCase
{
    // --- Gợi ý -------------------------------------------------------------------

    public function test_hint_returns_reply_and_records_conversation_and_usage(): void
    {
        $student = $this->makeStudent();
        $q = $this->question();

        $this->actingAs($student)
            ->postJson(route('api.ai.hint'), ['question_id' => $q->id, 'work' => 'Em quy đồng rồi…'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['reply_html'], 'meta' => ['usage' => ['used', 'limit', 'remaining']]])
            ->assertJsonPath('meta.usage.used', 1);

        $this->assertDatabaseHas('ai_conversations', ['user_id' => $student->id, 'mode' => 'hint', 'context_id' => $q->id]);
        $this->assertSame(2, AiConversation::first()->messages()->count());
        $this->assertSame(1, (int) AiUsage::where('user_id', $student->id)->value('request_count'));
    }

    public function test_hint_prompt_forbids_final_answer_and_never_contains_it(): void
    {
        $student = $this->makeStudent();
        $q = $this->question(Question::TYPE_SHORT_ANSWER); // đáp án 5/12

        $this->actingAs($student)->postJson(route('api.ai.hint'), ['question_id' => $q->id])->assertOk();

        $prompt = $this->lastPrompt();
        $this->assertStringContainsString('TUYỆT ĐỐI KHÔNG nêu đáp án cuối cùng', $prompt);
        $this->assertStringNotContainsString('Đáp án đúng', $prompt);
        $this->assertStringNotContainsString('5/12', $prompt);
    }

    public function test_system_prompt_uses_persona_grade_and_interests(): void
    {
        $student = $this->makeStudent(['tutor_persona' => 'thay', 'interests' => ['bóng đá']]);

        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'Phân số là gì?'])->assertOk();

        $system = $this->fake()->lastRequest()->messages[0]['content'];
        $this->assertSame('system', $this->fake()->lastRequest()->messages[0]['role']);
        $this->assertStringContainsString('thầy giáo', $system);
        $this->assertStringContainsString('Lớp 6', $system);
        $this->assertStringContainsString('bóng đá', $system);
        // Tin nhắn học sinh nằm ở role user, không bị nối vào system.
        $this->assertStringNotContainsString('Phân số là gì?', $system);
    }

    // --- Kiểm tra đáp án: đúng/sai lấy từ DB -------------------------------------------

    public function test_check_answer_verdict_comes_from_database_not_ai(): void
    {
        $student = $this->makeStudent();
        $q = $this->question();
        $correctId = $q->options->firstWhere('is_correct', true)->id;

        // AI "nói nhầm" là sai — kết quả vẫn phải là đúng theo DB.
        $this->fake()->push('Em làm sai rồi.');

        $this->actingAs($student)
            ->postJson(route('api.ai.check-answer'), ['question_id' => $q->id, 'answer' => (string) $correctId])
            ->assertOk()
            ->assertJsonPath('data.is_correct', true)
            ->assertJsonPath('data.verdict', 'ĐÚNG');

        $this->assertStringContainsString('Kết quả hệ thống chấm: ĐÚNG', $this->lastPrompt());
    }

    // --- Giải thích / phân tích lỗi ----------------------------------------------------

    public function test_explain_requires_trying_the_question_first(): void
    {
        $student = $this->makeStudent();
        $q = $this->question();

        $this->actingAs($student)
            ->postJson(route('api.ai.explain'), ['question_id' => $q->id])
            ->assertStatus(403)
            ->assertJsonPath('reason', 'blocked');

        $this->assertCount(0, $this->fake()->calls, 'Bị chặn thì không được gọi AI.');

        QuestionAttempt::create(['user_id' => $student->id, 'question_id' => $q->id, 'is_correct' => false]);

        $this->actingAs($student)->postJson(route('api.ai.explain'), ['question_id' => $q->id])->assertOk();
        $this->assertStringContainsString('Đáp án đúng:', $this->lastPrompt());
    }

    public function test_analyze_mistake_returns_structured_diagnosis(): void
    {
        $student = $this->makeStudent();
        $q = $this->question();
        $wrongId = $q->options->firstWhere('is_correct', false)->id;
        QuestionAttempt::create(['user_id' => $student->id, 'question_id' => $q->id, 'is_correct' => false]);

        $this->actingAs($student)
            ->postJson(route('api.ai.analyze-mistake'), ['question_id' => $q->id, 'answer' => (string) $wrongId])
            ->assertOk()
            ->assertJsonStructure(['data' => ['misconception_html', 'knowledge_gap', 'explanation_html', 'hint_html', 'topic']])
            ->assertJsonPath('data.knowledge_gap', 'Quy đồng mẫu số');

        $this->assertTrue($this->fake()->lastRequest()->json);
    }

    public function test_malformed_ai_json_is_a_503_not_a_crash(): void
    {
        $student = $this->makeStudent();
        $q = $this->question();

        $this->fake()->push('xin lỗi tôi không trả JSON');

        $this->actingAs($student)
            ->postJson(route('api.ai.similar-exercise'), ['question_id' => $q->id])
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_similar_exercise_returns_problem_answer_solution(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)
            ->postJson(route('api.ai.similar-exercise'), ['question_id' => $this->question()->id])
            ->assertOk()
            ->assertJsonStructure(['data' => ['problem_html', 'answer_html', 'solution_html']]);
    }

    // --- Chống gian lận -----------------------------------------------------------------

    public function test_all_ai_is_blocked_while_an_exam_is_in_progress(): void
    {
        $student = $this->makeStudent();
        $exam = Exam::create([
            'title' => 'Đề', 'slug' => 'de-ai', 'grade_id' => $this->grade->id, 'status' => 'published',
        ]);
        ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id, 'started_at' => now(),
            'expires_at' => now()->addMinutes(30), 'status' => 'in_progress', 'question_order' => [],
        ]);

        // Kể cả chat tự do — dán đề vào chat là ra đáp án.
        $this->actingAs($student)
            ->postJson(route('api.ai.chat'), ['message' => 'Giải giúp em: 1/2 + 1/3'])
            ->assertStatus(403)
            ->assertJsonPath('reason', 'blocked');

        $this->actingAs($student)
            ->postJson(route('api.ai.hint'), ['question_id' => $this->question()->id])
            ->assertStatus(403);

        $this->assertCount(0, $this->fake()->calls);
    }

    public function test_expired_unsubmitted_exam_does_not_lock_ai_forever(): void
    {
        $student = $this->makeStudent();
        $exam = Exam::create(['title' => 'Đề', 'slug' => 'de-ai-2', 'grade_id' => $this->grade->id, 'status' => 'published']);
        ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id, 'started_at' => now()->subHours(2),
            'expires_at' => now()->subHour(), 'status' => 'in_progress', 'question_order' => [],
        ]);

        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'Chào'])->assertOk();
    }

    public function test_pending_assignment_question_allows_only_hint(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeTeacher();
        $q = $this->question();

        $class = SchoolClass::create(['name' => 'L', 'code' => 'AIAIAI', 'grade_id' => $this->grade->id, 'owner_teacher_id' => $teacher->id]);
        $assignment = Assignment::create([
            'class_id' => $class->id, 'teacher_id' => $teacher->id, 'title' => 'BTVN',
            'type' => 'question_set', 'published_at' => now(),
        ]);
        $assignment->questions()->attach($q->id, ['sort_order' => 1, 'points' => 1]);
        AssignmentStudent::create(['assignment_id' => $assignment->id, 'student_id' => $student->id]);
        QuestionAttempt::create(['user_id' => $student->id, 'question_id' => $q->id]);

        $this->actingAs($student)->postJson(route('api.ai.hint'), ['question_id' => $q->id])->assertOk();

        foreach (['api.ai.explain', 'api.ai.check-answer', 'api.ai.similar-exercise', 'api.ai.analyze-mistake'] as $route) {
            $this->actingAs($student)->postJson(route($route), ['question_id' => $q->id, 'answer' => '1'])
                ->assertStatus(403);
        }

        // Nộp xong thì mở lại.
        AssignmentStudent::query()->update(['status' => 'completed']);
        $this->actingAs($student)->postJson(route('api.ai.explain'), ['question_id' => $q->id])->assertOk();
    }

    public function test_student_cannot_probe_unpublished_teacher_draft_questions(): void
    {
        $student = $this->makeStudent();
        $draft = Question::create([
            'grade_id' => $this->grade->id, 'type' => 'short_answer', 'content' => '<p>Câu nháp bí mật</p>',
            'status' => 'draft', 'correct_answer' => ['accepted' => ['42']],
        ]);

        $this->actingAs($student)->postJson(route('api.ai.hint'), ['question_id' => $draft->id])->assertNotFound();
    }

    // --- Quota, lỗi, giới hạn tốc độ -----------------------------------------------------

    public function test_daily_quota_blocks_before_calling_provider(): void
    {
        config(['ai.daily_limits.student.free' => 2]);
        $student = $this->makeStudent();

        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'a'])->assertOk();
        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'b'])->assertOk()
            ->assertJsonPath('meta.usage.remaining', 0);

        $this->actingAs($student)
            ->postJson(route('api.ai.chat'), ['message' => 'c'])
            ->assertStatus(429)
            ->assertJsonPath('reason', 'quota_exceeded');

        $this->assertCount(2, $this->fake()->calls);

        // Sang ngày mới thì có lượt lại.
        $this->travel(1)->days();
        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'd'])->assertOk();
    }

    public function test_provider_failure_is_logged_but_not_charged_to_student(): void
    {
        $student = $this->makeStudent();
        $this->fake()->failNext();

        $this->actingAs($student)
            ->postJson(route('api.ai.chat'), ['message' => 'Chào cô'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI Tutor đang bận, bạn thử lại sau ít phút nhé.');

        $usage = AiUsage::where('user_id', $student->id)->firstOrFail();
        $this->assertSame(0, $usage->request_count);
        $this->assertSame(1, $usage->failed_count);
    }

    public function test_per_minute_rate_limit(): void
    {
        config(['ai.daily_limits.student.free' => 100]);
        $student = $this->makeStudent();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => "q{$i}"])->assertOk();
        }

        $this->actingAs($student)
            ->postJson(route('api.ai.chat'), ['message' => 'quá nhanh'])
            ->assertStatus(429)
            ->assertJsonPath('reason', 'rate_limited');
    }

    // --- Chat & an toàn hiển thị ---------------------------------------------------------

    public function test_chat_continues_conversation_with_history(): void
    {
        $student = $this->makeStudent();

        $first = $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'Câu hỏi thứ nhất'])->json('data.conversation_id');

        $this->actingAs($student)
            ->postJson(route('api.ai.chat'), ['message' => 'Câu hỏi thứ hai', 'conversation_id' => $first])
            ->assertJsonPath('data.conversation_id', $first);

        $this->assertStringContainsString('Câu hỏi thứ nhất', $this->lastPrompt());
        $this->assertSame(4, AiConversation::find($first)->messages()->count());
    }

    public function test_cannot_continue_or_read_someone_elses_conversation(): void
    {
        $owner = $this->makeStudent();
        $id = $this->actingAs($owner)->postJson(route('api.ai.chat'), ['message' => 'riêng tư'])->json('data.conversation_id');

        $intruder = $this->makeStudent();
        $this->actingAs($intruder)->postJson(route('api.ai.chat'), ['message' => 'x', 'conversation_id' => $id])->assertNotFound();
        $this->actingAs($intruder)->getJson(route('api.ai.conversation', $id))->assertNotFound();
    }

    public function test_ai_output_html_is_escaped(): void
    {
        $student = $this->makeStudent();
        $this->fake()->push('<script>alert(1)</script> **đậm** $\\frac{1}{2}$');

        $html = $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'x'])->json('data.reply_html');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<strong>đậm</strong>', $html);
        $this->assertStringContainsString('\frac{1}{2}', $html);
    }

    public function test_off_topic_message_without_proper_refusal_is_flagged_for_review(): void
    {
        $student = $this->makeStudent();
        $this->fake()->push('Bóng đá là môn thể thao vua, đội bạn thích là đội nào?');

        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'Đội bóng đá yêu thích của cô là gì?'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.off_topic_suspected']);
        $meta = AiMessage::where('role', 'assistant')->latest('id')->value('meta');
        $this->assertSame('bóng đá', $meta['scope_flag']['keyword']);
    }

    public function test_off_topic_message_with_proper_refusal_is_not_flagged(): void
    {
        $student = $this->makeStudent();
        $this->fake()->push('Câu hỏi này không thuộc phạm vi Toán học, em quay lại bài học nhé.');

        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'Đội bóng đá yêu thích của cô là gì?'])->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'ai.off_topic_suspected']);
    }

    public function test_ordinary_math_message_is_never_flagged(): void
    {
        $student = $this->makeStudent();
        $this->fake()->push('Phân số là...');

        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'Phân số là gì?'])->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'ai.off_topic_suspected']);
    }

    public function test_admin_ai_usage_page_shows_off_topic_warning(): void
    {
        $student = $this->makeStudent();
        $this->fake()->push('Bóng đá là môn thể thao vua.');
        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'Đội bóng đá yêu thích của cô là gì?']);

        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.ai-usage.index'))
            ->assertOk()
            ->assertSee('lượt chat trong 30 ngày có tin nhắn ngoài lề');
    }

    public function test_guests_and_parents_cannot_use_ai(): void
    {
        $this->postJson(route('api.ai.chat'), ['message' => 'x'])->assertUnauthorized();

        $parent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $parent->assignRole('parent');

        $this->actingAs($parent)->postJson(route('api.ai.chat'), ['message' => 'x'])->assertStatus(403);
    }

    public function test_student_ai_page_and_widget_render(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('student.ai.index'))->assertOk()->assertSee('lượt còn lại hôm nay');
        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->assertSee('id="ai-tutor"', false);
    }
}
