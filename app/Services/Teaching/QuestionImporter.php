<?php

namespace App\Services\Teaching;

use App\Models\Grade;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Nhập câu hỏi hàng loạt từ CSV — cách duy nhất thực tế để đưa nội dung
 * 12 lớp vào hệ thống (rủi ro đã ghi ở PROJECT_PLAN §12).
 *
 * Cột: type, difficulty, points, content, explanation, topic_id,
 *      option_1..option_6, correct, accepted
 *
 * `correct`:
 *   - trắc nghiệm: số thứ tự lựa chọn đúng, vd "1" hoặc "1,3"
 *   - đúng/sai: "true" / "false"
 *   - điền chỗ trống: các chỗ trống cách nhau bằng ";", đáp án thay thế bằng "|"
 *     vd "1/2|0,5 ; 6"
 * `accepted`: dùng cho trả lời ngắn, các đáp án cách nhau bằng "|"
 */
class QuestionImporter
{
    private const MAX_OPTIONS = 6;

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function import(UploadedFile $file, Grade $grade, User $author, string $status = 'draft'): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Không đọc được tệp.']];
        }

        // Excel ghi CSV kèm BOM; không bỏ thì tên cột đầu tiên có ký tự rác.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Tệp rỗng.']];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        // Cache chủ đề hợp lệ của lớp này để không query mỗi dòng.
        $validTopicIds = Topic::query()
            ->whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $grade->id))
            ->pluck('id')
            ->flip();

        while (($raw = fgetcsv($handle)) !== false) {
            $line++;

            if (count(array_filter($raw, 'filled')) === 0) {
                continue;
            }

            $row = $this->mapRow($header, $raw);

            try {
                $this->importRow($row, $grade, $author, $status, $validTopicIds);
                $imported++;
            } catch (\InvalidArgumentException $e) {
                $skipped++;
                // Chỉ giữ 20 lỗi đầu, đủ để sửa file mà không làm ngập màn hình.
                if (count($errors) < 20) {
                    $errors[] = "Dòng {$line}: {$e->getMessage()}";
                }
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string|null>  $raw
     * @return array<string, string>
     */
    private function mapRow(array $header, array $raw): array
    {
        $row = [];

        foreach ($header as $i => $key) {
            $row[$key] = trim((string) ($raw[$i] ?? ''));
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @param  \Illuminate\Support\Collection<int, int>  $validTopicIds
     */
    private function importRow(array $row, Grade $grade, User $author, string $status, $validTopicIds): void
    {
        $type = $row['type'] ?? '';

        if (! array_key_exists($type, Question::TYPES)) {
            throw new \InvalidArgumentException("loại câu hỏi không hợp lệ ('{$type}').");
        }

        if (blank($row['content'] ?? null)) {
            throw new \InvalidArgumentException('thiếu nội dung câu hỏi.');
        }

        $topicId = filled($row['topic_id'] ?? null) ? (int) $row['topic_id'] : null;

        if ($topicId !== null && ! $validTopicIds->has($topicId)) {
            throw new \InvalidArgumentException("chủ đề {$topicId} không thuộc {$grade->name}.");
        }

        $difficulty = $row['difficulty'] ?? 'medium';
        if (! array_key_exists($difficulty, Question::DIFFICULTIES)) {
            $difficulty = 'medium';
        }

        DB::transaction(function () use ($row, $type, $grade, $topicId, $difficulty, $author, $status) {
            $question = Question::create([
                'grade_id' => $grade->id,
                'topic_id' => $topicId,
                'type' => $type,
                'content' => $row['content'],
                'explanation' => $row['explanation'] ?: null,
                'difficulty' => $difficulty,
                'points' => is_numeric($row['points'] ?? null) ? (float) $row['points'] : 1,
                'status' => $status,
                'source' => 'manual',
                'correct_answer' => $this->correctAnswerFor($type, $row),
                'created_by' => $author->id,
            ]);

            if (in_array($type, Question::CHOICE_TYPES, true)) {
                $this->createOptions($question, $row);
            }
        });
    }

    /**
     * @param  array<string, string>  $row
     */
    private function correctAnswerFor(string $type, array $row): ?array
    {
        $correct = $row['correct'] ?? '';

        return match ($type) {
            Question::TYPE_TRUE_FALSE => [
                'value' => in_array(strtolower($correct), ['true', '1', 'đúng', 'dung'], true),
            ],

            Question::TYPE_FILL_BLANK => [
                'blanks' => collect(explode(';', $correct))
                    ->map(fn ($blank) => collect(explode('|', $blank))
                        ->map(fn ($v) => trim($v))->filter()->values()->all())
                    ->filter(fn ($alts) => count($alts) > 0)
                    ->values()
                    ->all(),
            ],

            Question::TYPE_SHORT_ANSWER => [
                'accepted' => collect(explode('|', $row['accepted'] ?: $correct))
                    ->map(fn ($v) => trim($v))->filter()->values()->all(),
            ],

            default => null,
        };
    }

    /** @param  array<string, string>  $row */
    private function createOptions(Question $question, array $row): void
    {
        $correctIndexes = collect(explode(',', $row['correct'] ?? ''))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->all();

        $created = 0;

        for ($i = 1; $i <= self::MAX_OPTIONS; $i++) {
            $content = $row["option_{$i}"] ?? '';

            if (blank($content)) {
                continue;
            }

            $question->options()->create([
                'content' => $content,
                'is_correct' => in_array($i, $correctIndexes, true),
                'sort_order' => $i,
            ]);
            $created++;
        }

        if ($created < 2) {
            throw new \InvalidArgumentException('câu trắc nghiệm cần ít nhất 2 lựa chọn.');
        }

        if ($question->options()->where('is_correct', true)->count() === 0) {
            throw new \InvalidArgumentException('không có đáp án đúng nào được đánh dấu.');
        }
    }
}
