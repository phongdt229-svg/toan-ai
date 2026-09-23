<?php

namespace App\Models;

use App\Models\Concerns\HasRoles;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailLink;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'avatar',
        'last_login_at',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pending_email_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    /** URL ảnh đại diện, null nếu chưa có — view tự hiện biểu tượng mặc định. */
    public function avatarUrl(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    /** Loại thông báo (key = FQCN class, xem App\Support\NotificationType) đã bị người này tắt. */
    public function hasMutedNotification(string $type): bool
    {
        return in_array($type, $this->notification_preferences ?? [], true);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(StudentLessonProgress::class);
    }

    /** Lớp giáo viên này dạy (chủ nhiệm hoặc phụ). */
    public function teachingClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'teacher_classes', 'teacher_id', 'class_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** Lớp học sinh này đang học. */
    public function joinedClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_students', 'student_id', 'class_id')
            ->withPivot('status', 'joined_at')
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    public function assignmentRecords(): HasMany
    {
        return $this->hasMany(AssignmentStudent::class, 'student_id');
    }

    /** Giáo viên và học sinh có chung ít nhất một lớp đang hoạt động không. */
    public function teachesStudent(User $student): bool
    {
        return SchoolClass::query()
            ->taughtBy($this)
            ->whereHas('activeStudents', fn ($q) => $q->where('users.id', $student->id))
            ->exists();
    }

    /** Con của phụ huynh này. */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_children', 'parent_id', 'student_id')
            ->withPivot('status', 'linked_at')
            ->withTimestamps();
    }

    /** Con đang liên kết (bỏ các liên kết đã bị thu hồi). */
    public function linkedChildren(): BelongsToMany
    {
        return $this->children()->wherePivot('status', ParentChild::STATUS_LINKED);
    }

    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    /** Chặn phụ huynh xem báo cáo của học sinh không phải con mình (đoán id trên URL). */
    public function isParentOf(User $student): bool
    {
        return $this->linkedChildren()->where('users.id', $student->id)->exists();
    }

    /** Phụ huynh của học sinh này. */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_children', 'student_id', 'parent_id')
            ->withPivot('status', 'linked_at')
            ->withTimestamps();
    }

    public function linkedParents(): BelongsToMany
    {
        return $this->parents()->wherePivot('status', ParentChild::STATUS_LINKED);
    }

    /** Mail xác thực email bản tiếng Việt. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailLink);
    }

    /** Trang Cài đặt của đúng portal — mỗi role một route riêng. */
    public function settingsRoute(): string
    {
        return match (true) {
            $this->isAdmin() => 'admin.settings',
            $this->isTeacher() => 'teacher.settings',
            $this->isParent() => 'parent.settings',
            default => 'student.settings',
        };
    }

    /** Mail đặt lại mật khẩu bản tiếng Việt thay cho mail mặc định của Laravel. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }

    /** Gói học của học sinh này (kể cả do phụ huynh mua). */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Route mặc định sau khi đăng nhập, theo role. */
    public function homeRoute(): string
    {
        return match (true) {
            $this->isAdmin() => route('admin.dashboard'),
            $this->isTeacher() => route('teacher.dashboard'),
            $this->isParent() => route('parent.dashboard'),
            default => route('student.dashboard'),
        };
    }
}
