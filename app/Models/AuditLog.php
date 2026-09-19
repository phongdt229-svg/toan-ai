<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** Nhãn tiếng Việt cho màn hình quản trị. Hành động mới chưa có nhãn thì hiện mã gốc. */
    public const ACTION_LABELS = [
        'user.suspended' => 'Khoá tài khoản',
        'user.reactivated' => 'Mở khoá tài khoản',
        'teacher.approved' => 'Duyệt giáo viên',
        'teacher.rejected' => 'Từ chối giáo viên',
        'package.created' => 'Tạo gói học',
        'package.updated' => 'Sửa gói học',
        'package.deleted' => 'Xoá gói học',
        'subscription.granted' => 'Cấp gói thủ công',
        'subscription.cancelled' => 'Huỷ đăng ký gói',
        'payment.paid' => 'Thanh toán thành công',
        'lesson.created' => 'Tạo bài học',
        'lesson.updated' => 'Sửa bài học',
        'lesson.deleted' => 'Xoá bài học',
        'question.created' => 'Tạo câu hỏi',
        'question.updated' => 'Sửa câu hỏi',
        'question.deleted' => 'Xoá câu hỏi',
        'question.imported' => 'Nhập câu hỏi từ file',
        'exam.created' => 'Tạo đề kiểm tra',
        'exam.updated' => 'Sửa đề kiểm tra',
        'exam.deleted' => 'Xoá đề kiểm tra',
        'class.created' => 'Tạo lớp',
        'class.student_removed' => 'Xoá học sinh khỏi lớp',
        'assignment.created' => 'Giao bài',
        'assignment.deleted' => 'Xoá bài giao',
        'parent.child_linked' => 'Phụ huynh liên kết con',
        'parent.child_unlinked' => 'Phụ huynh huỷ liên kết',
        'student.parent_revoked' => 'Học sinh gỡ phụ huynh',
        'ai.question_accepted' => 'Duyệt câu hỏi AI soạn',
        'ai.lesson_created' => 'Tạo bài học từ AI',
        'ai.off_topic_suspected' => 'Nghi ngờ AI Tutor lạc đề khỏi Toán học',
    ];

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    /** "Payment #12" — tên ngắn của đối tượng bị tác động. */
    public function subjectLabel(): ?string
    {
        return $this->auditable_type ? class_basename($this->auditable_type).' #'.$this->auditable_id : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
