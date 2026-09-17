<?php

namespace App\Services\Payment;

use RuntimeException;

/** Lỗi hiển thị được cho người dùng (message tiếng Việt, không lộ chi tiết kỹ thuật). */
class PaymentException extends RuntimeException {}
