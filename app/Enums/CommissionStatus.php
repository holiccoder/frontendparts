<?php

namespace App\Enums;

/**
 * Commission lifecycle (SPEC §17.1, §17.3): created `pending` when the
 * attributed order is paid, flipped to `payable` by the daily command once
 * the refund window + holding period have passed, swept into a payout batch
 * and marked `paid` by the admin, or `voided` when the order is
 * refunded/charged back before payout.
 */
enum CommissionStatus: string
{
    case Pending = 'pending';
    case Payable = 'payable';
    case Paid = 'paid';
    case Voided = 'voided';
}
