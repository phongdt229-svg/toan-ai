<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const STUDENT = 'student';

    public const TEACHER = 'teacher';

    public const PARENT = 'parent';

    public const ADMIN = 'admin';

    /** Các role người dùng được tự chọn khi đăng ký (§4: không cho tự đăng ký admin). */
    public const SELF_REGISTERABLE = [self::STUDENT, self::TEACHER, self::PARENT];

    protected $fillable = ['name', 'display_name', 'description'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
