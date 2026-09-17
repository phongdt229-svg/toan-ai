<?php

namespace App\Services\Payment;

final class GatewayCheckout
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly string $payUrl,
        public readonly string $requestId,
        public readonly array $raw = [],
    ) {}
}
