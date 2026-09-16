<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentProfile extends Model
{
    protected $fillable = ['user_id', 'grade_id', 'birth_year', 'link_code'];

    protected function casts(): array
    {
        return ['birth_year' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** Mã 8 ký tự, không gồm ký tự dễ nhầm (0/O, 1/I). */
    public static function generateLinkCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
            $code = strtr($code, ['0' => 'X', 'O' => 'Y', '1' => 'Z', 'I' => 'W', 'L' => 'V']);
        } while (static::where('link_code', $code)->exists());

        return $code;
    }
}
