<?php

namespace App\Models;

use App\Models\Concerns\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
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

    /** Con của phụ huynh này. */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_children', 'parent_id', 'student_id')
            ->withPivot('status', 'linked_at')
            ->withTimestamps();
    }

    /** Phụ huynh của học sinh này. */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_children', 'student_id', 'parent_id')
            ->withPivot('status', 'linked_at')
            ->withTimestamps();
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
