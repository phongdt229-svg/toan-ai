<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Một khung giờ học cố định trong tuần của học sinh (D-01). */
class StudySchedule extends Model
{
    public const WEEKDAYS = [
        1 => 'Thứ 2',
        2 => 'Thứ 3',
        3 => 'Thứ 4',
        4 => 'Thứ 5',
        5 => 'Thứ 6',
        6 => 'Thứ 7',
        7 => 'Chủ nhật',
    ];

    public const DURATIONS = [30, 45, 60, 90, 120];

    protected $fillable = ['student_id', 'weekday', 'start_time', 'duration_minutes', 'updated_by'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function weekdayLabel(): string
    {
        return self::WEEKDAYS[$this->weekday] ?? '';
    }

    /** "19:30" — cột TIME của MariaDB trả về "19:30:00". */
    public function startLabel(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    /** Mốc bắt đầu của khung này vào ngày $date (theo múi giờ ứng dụng). */
    public function startsOn(Carbon $date): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', $this->startLabel()));

        return $date->copy()->setTime($h, $m);
    }

    public function endsOn(Carbon $date): Carbon
    {
        return $this->startsOn($date)->addMinutes($this->duration_minutes);
    }
}
