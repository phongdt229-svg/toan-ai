<?php

namespace App\Services\Parenting;

use App\Models\ParentChild;
use App\Models\ParentProfile;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Liên kết phụ huynh ↔ học sinh (§5): bằng mã, link, hoặc QR (QR chỉ là link dạng ảnh).
 *
 * Liên kết cho phụ huynh xem điểm và nhận xét của con — là dữ liệu riêng tư.
 * Vì vậy: mã có throttle ở route, học sinh thấy ai đang liên kết và thu hồi được,
 * và thu hồi thì đổi mã luôn để người đó không tự liên kết lại bằng mã cũ.
 */
class ChildLinkService
{
    /** Link chia sẻ hết hạn sau chừng này ngày. */
    public const SHARE_LINK_DAYS = 7;

    public function __construct(private readonly AuditLogger $audit) {}

    public function linkByCode(User $parent, string $code): User
    {
        $profile = StudentProfile::query()
            ->with('user')
            ->where('link_code', strtoupper(preg_replace('/\s+/', '', $code) ?? ''))
            ->first();

        if (! $profile?->user || ! $profile->user->isStudent()) {
            throw ValidationException::withMessages(['link_code' => 'Mã liên kết không đúng hoặc đã được đổi.']);
        }

        $student = $profile->user;

        DB::transaction(function () use ($parent, $student) {
            $existing = DB::table('parent_children')
                ->where('parent_id', $parent->id)
                ->where('student_id', $student->id)
                ->first();

            if ($existing?->status === ParentChild::STATUS_LINKED) {
                return;
            }

            $values = ['status' => ParentChild::STATUS_LINKED, 'linked_at' => now()];

            $existing
                ? $parent->children()->updateExistingPivot($student->id, $values)
                : $parent->children()->attach($student->id, $values);

            $this->ensureProfile($parent);
            $this->audit->log('parent.child_linked', $student, null, ['parent_id' => $parent->id]);
        });

        return $student;
    }

    /** Phụ huynh tự gỡ liên kết. */
    public function unlink(User $parent, User $student): void
    {
        $parent->children()->updateExistingPivot($student->id, ['status' => ParentChild::STATUS_REVOKED]);
        $this->audit->log('parent.child_unlinked', $student, null, ['parent_id' => $parent->id]);
    }

    /**
     * Học sinh thu hồi quyền xem của một phụ huynh. Đổi mã ngay, nếu không
     * người bị thu hồi chỉ cần nhập lại mã cũ là liên kết lại được.
     */
    public function revokeByStudent(User $student, User $parent): void
    {
        DB::transaction(function () use ($student, $parent) {
            $student->parents()->updateExistingPivot($parent->id, ['status' => ParentChild::STATUS_REVOKED]);
            $this->regenerateCode($student);
            $this->audit->log('student.parent_revoked', $student, null, ['parent_id' => $parent->id]);
        });
    }

    /** Mã mới làm mọi mã cũ và link đã chia sẻ (chứa mã cũ) mất hiệu lực. */
    public function regenerateCode(User $student): string
    {
        $profile = StudentProfile::where('user_id', $student->id)->firstOrFail();
        $profile->update(['link_code' => StudentProfile::generateLinkCode()]);

        return $profile->link_code;
    }

    /** Link ký số, hết hạn sau SHARE_LINK_DAYS ngày — không sửa được tham số trên URL. */
    public function shareUrl(User $student): string
    {
        $code = StudentProfile::where('user_id', $student->id)->value('link_code');

        return URL::temporarySignedRoute(
            'parent.children.accept',
            now()->addDays(self::SHARE_LINK_DAYS),
            ['code' => $code],
        );
    }

    public function ensureProfile(User $parent): ParentProfile
    {
        return ParentProfile::firstOrCreate(['user_id' => $parent->id], ['weekly_report_enabled' => true]);
    }

    /** Học sinh (theo mã) mà link đang trỏ tới — để trang xác nhận hiện tên con. */
    public function studentForCode(string $code): ?User
    {
        return StudentProfile::with('user')->where('link_code', $code)->first()?->user;
    }
}
