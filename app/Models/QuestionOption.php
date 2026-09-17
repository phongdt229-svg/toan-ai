<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $fillable = ['question_id', 'content', 'is_correct', 'sort_order'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    protected function content(): Attribute
    {
        return Attribute::set(fn (?string $v) => app(HtmlSanitizer::class)->clean($v));
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
