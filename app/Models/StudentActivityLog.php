<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sự kiện giao diện trong lúc học (mở bài, xem phần, rời tab...) — xem migration để biết vì sao chỉ có các loại này. */
class StudentActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    public const TYPES = [
        'lesson_open' => 'Mở bài học',
        'section_view' => 'Xem một phần bài',
        'exercise_start' => 'Bắt đầu làm bài',
        'tab_inactive' => 'Rời tab / thu nhỏ',
        'tab_active' => 'Quay lại tab',
    ];

    protected $fillable = ['user_id', 'event_type', 'path', 'meta', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
