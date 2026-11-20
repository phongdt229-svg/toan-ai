<?php

namespace App\Services\Learning;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Thi thử vào 10 / THPT. Làm bài, bấm giờ, chấm điểm vẫn là ExamService — ở đây chỉ thêm phần
 * phụ huynh và học sinh lớp cuối cấp cần: điểm thang 10, đứng thứ mấy giữa các thí sinh, yếu chủ đề nào.
 */
class MockExamService
{
    public function __construct(private readonly ExamService $exams) {}

    /**
     * Đề thi thử đang mở, kỳ thi hợp với lớp của em lên trước.
     *
     * @return Collection<int, Exam>
     */
    public function openFor(User $student): Collection
    {
        $preferred = $this->preferredTrack($student->loadMissing('studentProfile.grade')->studentProfile);

        return Exam::query()
            ->mock()
            ->published()
            ->open()
            ->with('grade')
            ->withCount(['attempts as my_attempts_count' => fn ($q) => $q->where('user_id', $student->id)])
            ->withMax(['attempts as my_best_score' => fn ($q) => $q->where('user_id', $student->id)], 'score')
            ->latest()
            ->get()
            ->sortBy(fn (Exam $e) => $e->mock_track === $preferred ? 0 : 1)
            ->values();
    }

    /** Lớp 9 → "vào 10", lớp 12 → "THPT"; lớp khác chưa có kỳ thi gợi ý. */
    public function preferredTrack(?StudentProfile $profile): ?string
    {
        $level = $profile?->grade?->level;

        return array_search($level, Exam::MOCK_TRACK_GRADE, true) ?: null;
    }

    /** Quy điểm về thang 10, làm tròn 0,25 như cách chấm đề thi thật. */
    public function scoreOutOfTen(float $score, float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round($score / $total * 10 * 4) / 4;
    }

    /**
     * Vị trí của lượt này giữa mọi thí sinh đã nộp đề (mỗi thí sinh tính lượt điểm cao nhất).
     *
     * @return array{score10: float, rank: int, candidates: int, beat_percent: int, average10: float}
     */
    public function standing(ExamAttempt $attempt): array
    {
        $exam = $attempt->exam;
        $total = (float) ($attempt->total_points ?: $exam->total_points);

        $best = DB::table('exam_attempts')
            ->where('exam_id', $exam->id)
            ->whereIn('status', [ExamAttempt::STATUS_SUBMITTED, ExamAttempt::STATUS_GRADED])
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(score) as best')
            ->pluck('best', 'user_id')
            ->map(fn ($v) => (float) $v);

        // So bằng lượt TỐT NHẤT của em — xem lại một lượt cũ điểm thấp không làm em tụt hạng.
        $mine = max((float) $attempt->score, $best->get($attempt->user_id, 0.0));
        $others = $best->except($attempt->user_id);

        $candidates = $best->count();
        $beaten = $others->filter(fn ($v) => $v < $mine)->count();

        return [
            'score10' => $this->scoreOutOfTen((float) $attempt->score, $total),
            'rank' => $others->filter(fn ($v) => $v > $mine)->count() + 1,
            'candidates' => $candidates,
            // Một mình em làm đề thì chưa có ai để so.
            'beat_percent' => $others->isEmpty() ? 0 : (int) round($beaten / $others->count() * 100),
            'average10' => $candidates > 0 ? $this->scoreOutOfTen($best->avg(), $total) : 0.0,
        ];
    }

    /**
     * Chủ đề làm kém nhất (dưới 70%), tối đa $limit — để gợi ý ôn trước kỳ thi.
     *
     * @return array<int, array{topic: string, percent: int}>
     */
    public function weakTopics(ExamAttempt $attempt, int $limit = 3): array
    {
        return collect($this->exams->breakdownByTopic($attempt))
            ->filter(fn ($row) => $row['percent'] < 70)
            ->sortBy('percent')
            ->take($limit)
            ->values()
            ->all();
    }
}
