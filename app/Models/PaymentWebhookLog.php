<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookLog extends Model
{
    public const RESULT_PROCESSED = 'processed';

    public const RESULT_DUPLICATE = 'duplicate';

    public const RESULT_INVALID_SIGNATURE = 'invalid_signature';

    public const RESULT_NOT_FOUND = 'not_found';

    public const RESULT_AMOUNT_MISMATCH = 'amount_mismatch';

    public const RESULT_PAYMENT_FAILED = 'payment_failed';

    public const RESULT_PENDING = 'still_pending';

    public const RESULT_ERROR = 'error';

    protected $fillable = [
        'provider', 'order_code', 'signature_valid', 'payload', 'headers',
        'result', 'message', 'ip_address', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'headers' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
