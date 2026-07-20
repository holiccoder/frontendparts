<?php

namespace App\Enums;

/**
 * Presentation-facing license state for the dashboard (SPEC §15.4). Derived
 * from the §7.3 order state machine — unlike OrderStatus it splits Cancelled
 * by whether access is still valid until ends_at, using the same cut-off
 * EntitlementService applies for plan resolution.
 */
enum LicenseState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case PastDue = 'past_due';
    case CancelledValidUntil = 'cancelled_valid_until';
    case Expired = 'expired';
    case Refunded = 'refunded';
}
