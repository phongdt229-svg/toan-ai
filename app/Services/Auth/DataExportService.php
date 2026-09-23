<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Bản sao dữ liệu cá nhân (Chính sách bảo mật §7). Chỉ dữ liệu CỦA CHÍNH người yêu cầu.
 *
 * Cố ý KHÔNG đưa vào: mật khẩu/secret 2FA, phản hồi thô từ cổng thanh toán (`gateway_response`),
 * IP/user agent, và đáp án đúng của câu hỏi. Đáp án học sinh đã chọn thì có.
 *
 * Thêm bảng mới chứa dữ liệu cá nhân → thêm vào đây VÀ vào AccountDeletionService::anonymise().
 */
class DataExportService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $this->audit->log('account.data_exported', $user);

        $mine = fn (string $table, string $column = 'user_id', array $hide = []) => DB::table($table)
            ->where($column, $user->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => collect((array) $row)->except(['id', $column, ...$hide])->all())
            ->all();

        $attemptIds = DB::table('exam_attempts')->where('user_id', $user->id)->pluck('id');
        $conversationIds = DB::table('ai_conversations')->where('user_id', $user->id)->pluck('id');

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles()->pluck('name')->all(),
                'status' => $user->status,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'notification_preferences' => $user->notification_preferences,
            ],
            'profiles' => [
                'student' => $mine('student_profiles')[0] ?? null,
                'teacher' => $mine('teacher_profiles')[0] ?? null,
                'parent' => $mine('parent_profiles')[0] ?? null,
            ],
            'learning' => [
                'lesson_progress' => $mine('student_lesson_progress'),
                'topic_mastery' => $mine('student_topic_mastery'),
                'question_attempts' => $mine('question_attempts'),
                'study_sessions' => $mine('study_sessions'),
                'placement_tests' => $mine('placement_tests'),
                'exam_attempts' => $mine('exam_attempts', 'user_id', ['question_order', 'option_order']),
                'exam_answers' => DB::table('student_answers')->whereIn('exam_attempt_id', $attemptIds)
                    ->get(['exam_attempt_id', 'question_id', 'answer', 'is_correct', 'score', 'max_score', 'time_spent_seconds', 'feedback'])
                    ->map(fn ($r) => (array) $r)->all(),
                'assignment_results' => $mine('assignment_students', 'student_id', ['due_reminded_at']),
                'assignment_submissions' => $mine('assignment_submissions', 'student_id'),
            ],
            'ai' => [
                'conversations' => $mine('ai_conversations'),
                'messages' => DB::table('ai_messages')->whereIn('ai_conversation_id', $conversationIds)->orderBy('id')
                    ->get(['ai_conversation_id', 'role', 'content', 'created_at'])
                    ->map(fn ($r) => (array) $r)->all(),
            ],
            'billing' => [
                'subscriptions' => $mine('subscriptions', 'user_id', ['purchased_by']),
                'payments' => $mine('payments', 'user_id', [
                    'gateway_response', 'pay_url', 'client_ip', 'gateway_request_id', 'flag_reason',
                ]),
            ],
            'support_tickets' => $mine('support_tickets', 'user_id', ['ip_address', 'user_agent', 'admin_note', 'handled_by']),
            'classes' => $mine('class_students', 'student_id'),
            'notifications' => DB::table('notifications')
                ->where('notifiable_type', User::class)->where('notifiable_id', $user->id)
                ->get(['type', 'data', 'read_at', 'created_at'])
                ->map(fn ($r) => ['type' => $r->type, 'data' => json_decode($r->data, true), 'read_at' => $r->read_at, 'created_at' => $r->created_at])
                ->all(),
        ];
    }
}
