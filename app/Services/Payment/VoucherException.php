<?php

namespace App\Services\Payment;

use RuntimeException;

/** Mã giảm giá không dùng được. Message viết cho người dùng đọc, nói rõ lý do. */
class VoucherException extends RuntimeException {}
