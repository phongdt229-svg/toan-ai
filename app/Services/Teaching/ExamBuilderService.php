<?php

namespace App\Services\Teaching;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Soạn đề (§16): tạo thủ công, chọn từ ngân hàng câu hỏi, hoặc bốc ngẫu nhiên theo tỉ lệ độ khó.
 * Tạo đề bằng AI sẽ gắn vào đây ở Phase 7A.
 */
class ExamBuilderService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $author): Exam
    {
        return Exam::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['title']),
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $author->id,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Exam $exam, array $data): Exam
    {
        $exam->update($data);

        return $exam;
    }

    /**
     * Thêm câu hỏi đã chọn từ ngân hàng. Bỏ qua câu đã có trong đề.
     *
     * @param  array<int, int>  $questionIds
     */
    public function addQuestions(Exam $exam, array $questionIds, User $actor): int
    {
        $this->ensureEditable($exam);

        return DB::transaction(function () use ($exam, $questionIds, $actor) {
            $existing = $this->questionIds($exam);
            $nextOrder = $this->maxSortOrder($exam);

            $questions = $this->usableQuestions($actor)
                ->whereIn('id', $questionIds)
                ->whereNotIn('id', $existing)
                ->get();

            foreach ($questions as $question) {
                $exam->questions()->attach($question->id, [
                    'sort_order' => ++$nextOrder,
                    'points' => $question->points,
                ]);
            }

            $this->recalculateTotals($exam);

            return $questions->count();
        });
    }

    /**
     * Bốc ngẫu nhiên N câu theo tỉ lệ độ khó — ví dụ §12: Dễ 30% / TB 50% / Khó 20%.
     * Nhóm nào thiếu câu thì bù từ nhóm khác để vẫn đủ số lượng nếu ngân hàng cho phép.
     *
     * @param  array<int, int>  $topicIds
     * @param  array{easy: int, medium: int, hard: int}  $ratio  phần trăm, tổng = 100
     */
    public function addRandomQuestions(Exam $exam, array $topicIds, int $count, array $ratio, User $actor): int
    {
        $this->ensureEditable($exam);

        return DB::transaction(function () use ($exam, $topicIds, $count, $ratio, $actor) {
            $existing = $this->questionIds($exam);

            $pool = $this->usableQuestions($actor)
                ->published()
                ->where('grade_id', $exam->grade_id)
                ->when($topicIds, fn ($q) => $q->whereIn('topic_id', $topicIds))
                ->whereNotIn('id', $existing)
                ->get(['id', 'difficulty', 'points'])
                ->shuffle();

            $quotas = $this->quotas($count, $ratio);
            $picked = collect();

            foreach ($quotas as $difficulty => $quota) {
                $picked = $picked->merge($pool->where('difficulty', $difficulty)->take($quota));
            }

            // Bù thiếu từ phần còn lại của pool.
            if ($picked->count() < $count) {
                $picked = $picked->merge(
                    $pool->whereNotIn('id', $picked->pluck('id'))->take($count - $picked->count()),
                );
            }

            $nextOrder = $this->maxSortOrder($exam);

            foreach ($picked as $question) {
                $exam->questions()->attach($question->id, [
                    'sort_order' => ++$nextOrder,
                    'points' => $question->points,
                ]);
            }

            $this->recalculateTotals($exam);

            return $picked->count();
        });
    }

    public function updateQuestion(Exam $exam, Question $question, float $points, int $sortOrder): void
    {
        $this->ensureEditable($exam);

        $exam->questions()->updateExistingPivot($question->id, [
            'points' => $points,
            'sort_order' => $sortOrder,
        ]);

        $this->recalculateTotals($exam);
    }

    public function removeQuestion(Exam $exam, Question $question): void
    {
        $this->ensureEditable($exam);

        $exam->questions()->detach($question->id);
        $this->recalculateTotals($exam);
    }

    public function togglePublish(Exam $exam): Exam
    {
        if (! $exam->isPublished() && count($this->questionIds($exam)) === 0) {
            throw new RuntimeException('Đề chưa có câu hỏi nào, không thể xuất bản.');
        }

        $exam->update([
            'status' => $exam->isPublished() ? Exam::STATUS_DRAFT : Exam::STATUS_PUBLISHED,
        ]);

        return $exam;
    }

    public function recalculateTotals(Exam $exam): void
    {
        $totals = DB::table('exam_questions')
            ->where('exam_id', $exam->id)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(points), 0) as pts')
            ->first();

        $exam->update([
            'total_questions' => (int) $totals->n,
            'total_points' => (float) $totals->pts,
        ]);
    }

    /**
     * Câu được phép đưa vào đề: đã xuất bản (của bất kỳ ai), hoặc nháp của chính người soạn đề.
     */
    private function usableQuestions(User $actor)
    {
        return Question::query()->where(function ($q) use ($actor) {
            $q->where('status', 'published')
                ->orWhere('created_by', $actor->id);
        });
    }

    /**
     * Chia N câu theo tỉ lệ; phần lẻ do làm tròn dồn vào nhóm có tỉ lệ lớn nhất.
     *
     * @param  array{easy: int, medium: int, hard: int}  $ratio
     * @return array{easy: int, medium: int, hard: int}
     */
    public function quotas(int $count, array $ratio): array
    {
        $quotas = [];

        foreach (['easy', 'medium', 'hard'] as $key) {
            $quotas[$key] = (int) floor($count * ($ratio[$key] ?? 0) / 100);
        }

        $remainder = $count - array_sum($quotas);
        if ($remainder > 0) {
            $largest = array_keys($ratio, max($ratio))[0];
            $quotas[$largest] += $remainder;
        }

        return $quotas;
    }

    /**
     * Đọc thẳng bảng pivot: relation `questions()` có orderByPivot, gộp với
     * COUNT/MAX thì MariaDB ở strict mode từ chối câu lệnh.
     *
     * @return array<int, int>
     */
    private function questionIds(Exam $exam): array
    {
        return DB::table('exam_questions')->where('exam_id', $exam->id)->pluck('question_id')->all();
    }

    private function maxSortOrder(Exam $exam): int
    {
        return (int) DB::table('exam_questions')->where('exam_id', $exam->id)->max('sort_order');
    }

    /** Đề đã có người làm thì khoá bộ câu hỏi — đổi câu/điểm lúc này làm sai lệch kết quả đã có. */
    private function ensureEditable(Exam $exam): void
    {
        if ($exam->hasAttempts()) {
            throw new RuntimeException('Đề đã có học sinh làm, không thể thay đổi câu hỏi hoặc điểm.');
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'de-kiem-tra';
        $slug = $base;
        $i = 2;

        while (Exam::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Câu hỏi ứng viên để giáo viên chọn tay, lọc theo lớp/chủ đề/độ khó.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Question>
     */
    public function candidates(Exam $exam, User $actor, array $filters): Collection
    {
        return $this->usableQuestions($actor)
            ->where('grade_id', $exam->grade_id)
            ->whereNotIn('id', $this->questionIds($exam))
            ->when($filters['topic_id'] ?? null, fn ($q, $v) => $q->where('topic_id', $v))
            ->when($filters['difficulty'] ?? null, fn ($q, $v) => $q->where('difficulty', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->with('topic')
            ->latest('id')
            ->limit(50)
            ->get();
    }
}
