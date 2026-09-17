<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementTestAnswer extends Model
{
    protected $fillable = [
        'placement_test_id', 'placement_test_question_id', 'answer', 'is_correct', 'score', 'time_spent_seconds',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'is_correct' => 'boolean',
            'score' => 'decimal:2',
        ];
    }

    public function value(): mixed
    {
        return $this->answer['value'] ?? null;
    }
}
