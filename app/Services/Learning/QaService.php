<?php

namespace App\Services\Learning;

use App\Models\QaAnswer;
use App\Models\QaQuestion;
use App\Models\QaReport;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\QaAnswerAccepted;
use App\Notifications\QaAnswerPosted;
use App\Services\AuditLogger;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hỏi đáp cho học sinh (PROJECT_PLAN.md §10, đợt 24/09).
 *
 * Mọi luật an toàn nằm ở đây, không rải trong controller: lọc HTML lúc lưu, tự ẩn khi bị
 * báo nhiều, và chặn sửa sau khi đã có người trả lời.
 */
class QaService
{
    /** Đủ số lượt báo này thì nội dung tự ẩn, chờ người lớn xem lại. */
    public const REPORTS_TO_HIDE = 3;

    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly AuditLogger $audit,
    ) {}

    public function ask(User $user, Topic $topic, string $title, string $body): QaQuestion
    {
        return QaQuestion::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'title' => trim($title),
            'body' => $this->sanitizer->clean($body),
        ]);
    }

    /** @throws RuntimeException */
    public function editQuestion(QaQuestion $question, string $title, string $body): void
    {
        // Sửa câu hỏi sau khi đã có người trả lời là cách kinh điển để biến câu hỏi hiền
        // thành câu hỏi bẩn, và làm các câu trả lời bên dưới thành vô nghĩa.
        if ($question->answers_count > 0) {
            throw new RuntimeException('Câu hỏi đã có người trả lời nên không sửa được nữa.');
        }

        $question->update(['title' => trim($title), 'body' => $this->sanitizer->clean($body)]);
    }

    public function answer(User $user, QaQuestion $question, string $body): QaAnswer
    {
        if ($question->isHidden()) {
            throw new RuntimeException('Câu hỏi này đang bị ẩn.');
        }

        $answer = DB::transaction(function () use ($user, $question, $body) {
            $answer = QaAnswer::create([
                'question_id' => $question->id,
                'user_id' => $user->id,
                'body' => $this->sanitizer->clean($body),
            ]);

            $this->syncAnswerCount($question);

            return $answer;
        });

        // Người hỏi tự trả lời thêm ý thì không cần tự báo cho mình.
        if ($question->user_id !== $user->id) {
            $question->user->notify(new QaAnswerPosted($question, $user));
        }

        return $answer;
    }

    /** Người hỏi hoặc giáo viên chọn câu trả lời đúng. */
    public function acceptAnswer(QaQuestion $question, QaAnswer $answer): void
    {
        if ($answer->question_id !== $question->id) {
            throw new RuntimeException('Câu trả lời không thuộc câu hỏi này.');
        }

        $question->update(['best_answer_id' => $answer->id, 'status' => QaQuestion::STATUS_RESOLVED]);

        if ($answer->user_id !== $question->user_id) {
            $answer->user->notify(new QaAnswerAccepted($question));
        }
    }

    /**
     * Báo xấu. Trả về true nếu lượt báo này khiến nội dung bị ẩn.
     *
     * Không để nội dung bẩn nằm chờ tới lúc có người trực: đủ ngưỡng là ẩn ngay,
     * giáo viên/quản trị hiện lại sau nếu báo nhầm.
     */
    public function report(User $user, Model $target, ?string $reason = null): bool
    {
        $already = QaReport::where('reportable_type', $target::class)
            ->where('reportable_id', $target->getKey())
            ->where('user_id', $user->id)
            ->exists();

        if ($already) {
            return $target->status === QaQuestion::STATUS_HIDDEN || $target->status === QaAnswer::STATUS_HIDDEN;
        }

        QaReport::create([
            'reportable_type' => $target::class,
            'reportable_id' => $target->getKey(),
            'user_id' => $user->id,
            'reason' => $reason ? mb_substr(trim($reason), 0, 191) : null,
        ]);

        $count = $target->reports()->count();
        $target->forceFill(['reports_count' => $count])->save();

        if ($count < self::REPORTS_TO_HIDE || $this->isHidden($target)) {
            return false;
        }

        $this->setHidden($target, true);
        $this->audit->log('qa.auto_hidden', $target, null, ['reports' => $count]);

        return true;
    }

    /** Giáo viên / quản trị ẩn hoặc hiện lại. */
    public function moderate(User $moderator, Model $target, bool $hidden): void
    {
        $this->setHidden($target, $hidden);

        $this->audit->log($hidden ? 'qa.hidden' : 'qa.restored', $target, null, ['by' => $moderator->id]);
    }

    private function isHidden(Model $target): bool
    {
        return $target->status === ($target instanceof QaQuestion
            ? QaQuestion::STATUS_HIDDEN
            : QaAnswer::STATUS_HIDDEN);
    }

    private function setHidden(Model $target, bool $hidden): void
    {
        if ($target instanceof QaQuestion) {
            // Hiện lại thì về "chờ trả lời" hoặc "đã có lời giải" tuỳ đã chọn đáp án chưa.
            $target->forceFill(['status' => $hidden
                ? QaQuestion::STATUS_HIDDEN
                : ($target->best_answer_id ? QaQuestion::STATUS_RESOLVED : QaQuestion::STATUS_OPEN)])->save();

            return;
        }

        $target->forceFill(['status' => $hidden ? QaAnswer::STATUS_HIDDEN : QaAnswer::STATUS_VISIBLE])->save();
        $this->syncAnswerCount($target->question);
    }

    /** Số câu trả lời hiển thị — cột đếm sẵn phải khớp lại sau mỗi lần ẩn/hiện. */
    private function syncAnswerCount(QaQuestion $question): void
    {
        $question->forceFill(['answers_count' => $question->answers()->visible()->count()])->save();
    }
}
