<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    public const TYPE_SUPPORT = 'support';

    public const TYPE_CONTENT_ERROR = 'content_error';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_OTHER = 'other';

    public const TYPE_LABELS = [
        self::TYPE_SUPPORT => 'Yêu cầu hỗ trợ',
        self::TYPE_CONTENT_ERROR => 'Báo lỗi nội dung',
        self::TYPE_PAYMENT => 'Vấn đề thanh toán',
        self::TYPE_OTHER => 'Góp ý khác',
    ];

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_LABELS = [
        self::STATUS_NEW => 'Mới',
        self::STATUS_IN_PROGRESS => 'Đang xử lý',
        self::STATUS_RESOLVED => 'Đã xử lý',
        self::STATUS_CLOSED => 'Đã đóng',
    ];

    protected $fillable = [
        'code', 'type', 'user_id', 'name', 'email', 'subject', 'message',
        'context_url', 'related_type', 'related_id',
        'status', 'admin_note', 'handled_by', 'handled_at', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_NEW, self::STATUS_IN_PROGRESS]);
    }

    /** Mã người gửi đọc qua điện thoại được: HT-A1B2C3. */
    public static function generateCode(): string
    {
        do {
            $code = 'HT-'.strtoupper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_IN_PROGRESS], true);
    }
}
