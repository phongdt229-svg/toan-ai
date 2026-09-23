<?php

namespace Database\Seeders;

use App\Models\AiConversation;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Question;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Learning\ExamService;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu demo cho các bảng đang trống: thanh toán/gói, lượt làm đề, hội thoại AI, yêu cầu
 * hỗ trợ — để dashboard quản trị và báo cáo có số liệu thật thay vì toàn số 0.
 *
 * Đi qua đúng service nghiệp vụ (SubscriptionService, ExamService) rồi mới lùi ngày tháng
 * lại cho trải đều theo thời gian — không tự ý update(['status'=>...]) tay (§29). Riêng
 * Payment/AiConversation/SupportTicket là bảng nhật ký thuần, tạo thẳng bằng Eloquent.
 *
 * Chỉ chạy ở local/testing (xem DatabaseSeeder). Có guard theo từng phần để chạy lại
 * (php artisan db:seed --class=SampleReportsSeeder) không nhân đôi dữ liệu.
 */
class SampleReportsSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20260920);

        $students = User::whereHas('roles', fn ($q) => $q->where('name', 'student'))
            ->whereIn('email', $this->studentEmails())
            ->orderBy('id')
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $this->seedSubscriptionsAndPayments($students);
        $this->seedExamAttempts($students);
        $this->seedAiConversations($students);
        $this->seedSupportTickets($students);
    }

    /** @return list<string> */
    private function studentEmails(): array
    {
        $emails = ['student@gmail.com'];

        for ($i = 1; $i <= 22; $i++) {
            $emails[] = sprintf('hs%02d@gmail.com', $i);
        }

        return $emails;
    }

    /**
     * Gói + thanh toán trải đều 6 tháng gần đây. 2/3 số học sinh có mua ít nhất 1 lần,
     * số còn lại vẫn ở Free — để báo cáo có cả hai nhóm, không phải ai cũng trả phí.
     *
     * @param  Collection<int, User>  $students
     */
    private function seedSubscriptionsAndPayments($students): void
    {
        if (Payment::count() > 0) {
            return;
        }

        $service = app(SubscriptionService::class);
        $packages = Package::whereIn('slug', ['pro-thang', 'premium-thang'])->get()->keyBy('slug');
        $slugs = array_keys($packages->all());
        $orderSeq = 1;

        foreach ($students as $index => $student) {
            if ($index % 4 === 3) {
                continue;
            }

            // Gói tháng gia hạn nhiều lần trong 6 tháng là bình thường — không phải bịa cho đủ số.
            foreach (range(0, mt_rand(3, 6) - 1) as $cycle) {
                $package = $packages[$slugs[mt_rand(0, count($slugs) - 1)]];
                $paidAt = now()->subDays(mt_rand(1, 180))->setTime(mt_rand(8, 21), mt_rand(0, 59));

                $subscription = $service->createPending($student, $package);
                $service->activate($subscription);

                // Kích hoạt thật luôn tính từ "bây giờ" (nối gói đang có) — lùi lại cho một
                // bức tranh 6 tháng, không phải tất cả dồn về hôm nay.
                DB::table('subscriptions')->where('id', $subscription->id)->update([
                    'starts_at' => $paidAt,
                    'ends_at' => $paidAt->copy()->addDays((int) $package->duration_days),
                    'activated_at' => $paidAt,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]);

                Payment::create([
                    'order_code' => sprintf('SEEDPAY%06d', $orderSeq++),
                    'user_id' => $student->id,
                    'package_id' => $package->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $package->price,
                    'currency' => 'VND',
                    'method' => 'momo',
                    'status' => Payment::STATUS_PAID,
                    'paid_at' => $paidAt,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]);
            }
        }
    }

    /**
     * Lượt làm đề — đi qua ExamService thật (start/saveAnswer/submit) nên chấm điểm,
     * question_order, mastery... đều đúng như học sinh làm thật. Câu tự luận/điền khuyết
     * bỏ trống, giống việc học sinh thật hay bỏ qua câu khó khi không đủ giờ.
     *
     * @param  Collection<int, User>  $students
     */
    private function seedExamAttempts($students): void
    {
        if (ExamAttempt::count() > 0) {
            return;
        }

        $exam = Exam::where('status', 'published')->first();

        if (! $exam) {
            return;
        }

        $service = app(ExamService::class);
        $questions = $exam->questions()->with('options')->get()->keyBy('id');

        foreach ($students as $index => $student) {
            $ability = 0.5 + ($index % 5) * 0.1;

            foreach (range(1, mt_rand(2, min(3, $exam->max_attempts))) as $n) {
                try {
                    $attempt = $service->start($student, $exam);
                } catch (\Throwable) {
                    break; // hết lượt hoặc không đủ điều kiện — dừng học sinh này
                }

                foreach ($attempt->question_order as $questionId) {
                    $question = $questions->get($questionId);
                    $value = $question ? $this->plausibleAnswer($question, $ability) : null;

                    if ($value !== null) {
                        $service->saveAnswer($attempt, $questionId, $value);
                    }
                }

                $service->submit($attempt);

                $startedAt = now()->subDays(mt_rand(0, 45))->setTime(mt_rand(7, 21), mt_rand(0, 59));
                DB::table('exam_attempts')->where('id', $attempt->id)->update([
                    'started_at' => $startedAt,
                    'submitted_at' => $startedAt->copy()->addMinutes(mt_rand(5, max(5, $exam->duration_minutes - 2))),
                    'expires_at' => $startedAt->copy()->addMinutes($exam->duration_minutes),
                    'created_at' => $startedAt,
                    'updated_at' => $startedAt,
                ]);
            }
        }
    }

    /** Giá trị hợp lệ cho ExamService::saveAnswer() theo đúng/sai giả lập; null = bỏ qua câu này. */
    private function plausibleAnswer(Question $question, float $ability): mixed
    {
        $correct = (mt_rand(1, 100) / 100) <= $ability;

        return match ($question->type) {
            Question::TYPE_SINGLE_CHOICE => $question->options->firstWhere('is_correct', $correct)?->id,
            Question::TYPE_MULTIPLE_CHOICE => $correct
                ? $question->options->where('is_correct', true)->pluck('id')->all()
                : $question->options->where('is_correct', false)->pluck('id')->take(1)->all(),
            Question::TYPE_TRUE_FALSE => $correct
                ? ($question->correct_answer['value'] ?? true)
                : ! ($question->correct_answer['value'] ?? true),
            // fill_blank / short_answer / essay: không đoán mò nội dung tự luận.
            default => null,
        };
    }

    /**
     * Hội thoại AI Tutor — kèm tổng hợp vào ai_usage cho khớp (trang AI usage đọc từ đây,
     * không đếm lại từ ai_conversations).
     *
     * @param  Collection<int, User>  $students
     */
    private function seedAiConversations($students): void
    {
        if (AiConversation::count() > 0) {
            return;
        }

        $modes = ['chat', 'hint', 'explain', 'check_answer', 'similar_exercise', 'analyze_mistake'];
        $questions = [
            'Phân số là gì ạ?', 'Làm sao quy đồng mẫu số vậy cô?', 'Em không hiểu cộng số nguyên âm.',
            'Sao nhân hai số âm lại ra số dương?', 'Cô giải thích lại thứ tự thực hiện phép tính giúp em.',
            'Bài này em làm sai ở đâu vậy ạ?',
        ];
        $replies = [
            'Phân số biểu diễn một phần của tổng thể, gồm tử số trên và mẫu số dưới...',
            'Để quy đồng mẫu số, em tìm bội chung nhỏ nhất của hai mẫu rồi nhân cả tử và mẫu...',
            'Số nguyên âm nhỏ hơn 0, nằm bên trái số 0 trên trục số...',
            'Nhân hai số cùng dấu (âm với âm) luôn ra số dương, quy tắc dấu em nhớ nhé...',
            'Nhân chia làm trước, cộng trừ làm sau — trong ngoặc thì làm trong ngoặc trước tiên...',
        ];

        /** @var array<string, array{request_count: int, tokens_in: int, tokens_out: int, cost_estimate: float}> */
        $usage = [];

        foreach ($students as $student) {
            foreach (range(1, mt_rand(2, 6)) as $n) {
                $mode = $modes[mt_rand(0, count($modes) - 1)];
                $at = now()->subDays(mt_rand(0, 30))->setTime(mt_rand(7, 22), mt_rand(0, 59));
                $tokensIn = mt_rand(60, 220);
                $tokensOut = mt_rand(80, 320);

                $conversation = AiConversation::create([
                    'user_id' => $student->id,
                    'mode' => $mode,
                    'context_type' => 'free',
                    'title' => mb_substr($questions[array_rand($questions)], 0, 80),
                    'last_message_at' => $at,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                $conversation->messages()->create([
                    'role' => 'user', 'content' => $questions[array_rand($questions)],
                    'created_at' => $at, 'updated_at' => $at,
                ]);
                $conversation->messages()->create([
                    'role' => 'assistant', 'content' => $replies[array_rand($replies)], 'model' => 'fake',
                    'tokens_in' => $tokensIn, 'tokens_out' => $tokensOut, 'latency_ms' => mt_rand(300, 1500),
                    'created_at' => $at, 'updated_at' => $at,
                ]);

                $key = "{$student->id}|{$at->toDateString()}|{$mode}";
                $usage[$key] ??= ['user_id' => $student->id, 'usage_date' => $at->toDateString(), 'feature' => $mode, 'request_count' => 0, 'tokens_in' => 0, 'tokens_out' => 0, 'cost_estimate' => 0];
                $usage[$key]['request_count']++;
                $usage[$key]['tokens_in'] += $tokensIn;
                $usage[$key]['tokens_out'] += $tokensOut;
                // FakeProvider không tính phí thật — giữ 0 cho đúng bản chất, khỏi làm sai lệch chi phí ước tính.
            }
        }

        $now = now();
        foreach (array_chunk(array_values($usage), 200) as $chunk) {
            DB::table('ai_usage')->insert(array_map(fn ($row) => [...$row, 'failed_count' => 0, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }
    }

    /**
     * Yêu cầu hỗ trợ — trộn khách chưa đăng nhập (user_id null) và tài khoản thật,
     * đủ 4 trạng thái để trang Quản trị → Hỗ trợ có cái để lọc.
     *
     * @param  Collection<int, User>  $students
     */
    private function seedSupportTickets($students): void
    {
        if (SupportTicket::count() > 0) {
            return;
        }

        $admin = User::where('email', 'admin@gmail.com')->first();
        $types = [SupportTicket::TYPE_SUPPORT, SupportTicket::TYPE_CONTENT_ERROR, SupportTicket::TYPE_PAYMENT, SupportTicket::TYPE_OTHER];
        $statuses = [SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS, SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED];
        $subjects = [
            'Không đăng nhập được', 'Câu hỏi hình như sai đáp án', 'Thanh toán bị trừ tiền nhưng chưa lên gói',
            'Chữ công thức Toán hiển thị lỗi trên điện thoại', 'Góp ý giao diện trang chủ',
            'Không nhận được email đặt lại mật khẩu', 'Bài giao không hiện trong danh sách', 'Xin hướng dẫn liên kết tài khoản phụ huynh',
        ];

        for ($i = 0; $i < 100; $i++) {
            $student = $students[$i % $students->count()];
            $status = $statuses[mt_rand(0, 3)];
            $at = now()->subDays(mt_rand(0, 60))->setTime(mt_rand(7, 22), mt_rand(0, 59));
            $handled = in_array($status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true);
            $isGuest = mt_rand(1, 100) <= 30;

            SupportTicket::create([
                'code' => SupportTicket::generateCode(),
                'type' => $types[mt_rand(0, 3)],
                'user_id' => $isGuest ? null : $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'subject' => $subjects[array_rand($subjects)],
                'message' => 'Nội dung yêu cầu hỗ trợ demo #'.($i + 1).' — dữ liệu mẫu cho báo cáo.',
                'status' => $status,
                'admin_note' => $handled ? 'Đã xử lý, phản hồi qua email.' : null,
                'handled_by' => $handled ? $admin?->id : null,
                'handled_at' => $handled ? $at->copy()->addHours(mt_rand(1, 48)) : null,
                'ip_address' => '127.0.0.1',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }
}
