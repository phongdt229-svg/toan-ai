<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Services\Admin\AnalyticsService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleAnalyticsSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Database\Seeders\SampleQuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Dữ liệu demo cho dashboard: phải dùng được ngay và câu hỏi sinh tự động phải hợp lệ. */
class SampleAnalyticsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, SampleCurriculumSeeder::class, SampleQuestionSeeder::class]);
    }

    public function test_seeder_fills_dashboard_panels_and_is_idempotent(): void
    {
        $this->seed(SampleAnalyticsSeeder::class);

        $attempts = QuestionAttempt::count();
        $questions = Question::count();

        $this->assertGreaterThan(0, $attempts);
        $this->assertGreaterThanOrEqual(12, StudentTopicMastery::distinct()->count('user_id'));

        Cache::flush();
        $overview = app(AnalyticsService::class)->overview();

        // Panel "Chủ đề học sinh yếu nhất" cần chủ đề có từ 3 học sinh trở lên.
        $this->assertNotEmpty($overview['weak_topics']);
        $this->assertGreaterThanOrEqual(3, $overview['weak_topics'][0]['students']);
        $this->assertGreaterThan(0, $overview['engagement']['active_30d']);
        $this->assertSame(30, count($overview['daily']));
        $this->assertGreaterThan(0, collect($overview['daily'])->sum('active'));

        // Chạy lại không nhân đôi dữ liệu.
        $this->seed(SampleAnalyticsSeeder::class);
        $this->assertSame($attempts, QuestionAttempt::count());
        $this->assertSame($questions, Question::count());
    }

    public function test_generated_questions_always_have_exactly_one_correct_option(): void
    {
        $this->seed(SampleAnalyticsSeeder::class);

        // Chỉ xét câu do seeder tự sinh (câu mẫu viết tay có loại nhiều đáp án đúng).
        $generated = Topic::whereIn('slug', [
            'rut-gon-phan-so', 'phep-tru-phan-so', 'phep-nhan-phan-so',
            'phep-chia-phan-so', 'cong-tru-so-nguyen', 'thu-tu-thuc-hien-phep-tinh',
        ])->pluck('id');

        $questions = Question::with('options')->whereIn('topic_id', $generated)->get();
        $this->assertGreaterThanOrEqual(36, $questions->count());

        foreach ($questions as $question) {
            $correct = $question->options->where('is_correct', true);

            $this->assertCount(1, $correct, "Câu hỏi #{$question->id} phải có đúng 1 đáp án đúng.");
            $this->assertCount(
                $question->options->pluck('content')->unique()->count(),
                $question->options,
                "Câu hỏi #{$question->id} có phương án trùng nhau.",
            );
        }
    }
}
