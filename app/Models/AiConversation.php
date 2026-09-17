<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    public const MODES = [
        'chat' => 'Hỏi đáp',
        'hint' => 'Gợi ý',
        'explain' => 'Giải thích',
        'check_answer' => 'Kiểm tra đáp án',
        'similar_exercise' => 'Bài tương tự',
        'analyze_mistake' => 'Phân tích lỗi',
    ];

    protected $fillable = ['user_id', 'mode', 'context_type', 'context_id', 'title', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode] ?? $this->mode;
    }
}
