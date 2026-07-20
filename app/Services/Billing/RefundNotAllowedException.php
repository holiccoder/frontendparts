<?php

namespace App\Services\Billing;

use RuntimeException;

/**
 * Thrown when a refund is attempted on an order that is not refundable —
 * unpaid/unpaid-linked orders, orders already refunded, or orders past the
 * settings-driven refund window (SPEC §7.3, §8.7).
 */
class RefundNotAllowedException extends RuntimeException {}
