<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationDraft extends Model
{
    public const TYPE_QUESTIONS = 'questions';

    public const TYPE_LESSON = 'lesson';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['user_id', 'type', 'status', 'input', 'output', 'error'];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /** @return array<int, array<string, mixed>> */
    public function items(): array
    {
        return $this->output['items'] ?? [];
    }
}
