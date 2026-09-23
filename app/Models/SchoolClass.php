<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Lớp học. Không đặt tên model là Class vì đó là từ khoá PHP.
 */
class SchoolClass extends Model
{
    use SoftDeletes;

    protected $table = 'classes';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = ['name', 'code', 'grade_id', 'description', 'owner_teacher_id', 'status'];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_teacher_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_classes', 'class_id', 'teacher_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** Toàn bộ học sinh từng ở lớp, kể cả đã rời. Thường dùng activeStudents(). */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_students', 'class_id', 'student_id')
            ->withPivot('status', 'joined_at')
            ->withTimestamps();
    }

    public function activeStudents(): BelongsToMany
    {
        return $this->students()->wherePivot('status', 'active');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'class_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /** Lớp mà giáo viên này dạy (chủ nhiệm hoặc giáo viên phụ). */
    public function scopeTaughtBy(Builder $query, User $teacher): Builder
    {
        return $query->whereHas('teachers', fn ($q) => $q->where('users.id', $teacher->id));
    }

    public function hasTeacher(User $user): bool
    {
        return $this->teachers()->where('users.id', $user->id)->exists();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_teacher_id === $user->id;
    }

    public function hasActiveStudent(User $user): bool
    {
        return $this->activeStudents()->where('users.id', $user->id)->exists();
    }

    /** 6 ký tự, bỏ ký tự dễ đọc nhầm khi giáo viên đọc mã cho cả lớp (0/O, 1/I/L). */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public static function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/\s+/', '', $code) ?? '');
    }
}
