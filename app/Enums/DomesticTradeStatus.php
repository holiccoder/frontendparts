<?php

namespace App\Enums;

/**
 * Normalized trade state across the two domestic rails (SPEC §7.5).
 * Alipay's TRADE_SUCCESS / TRADE_FINISHED and WeChat's SUCCESS map to Paid;
 * waiting states (WAIT_BUYER_PAY / NOTPAY / USERPAYING) map to Waiting;
 * closed/expired/failed states map to Closed.
 */
enum DomesticTradeStatus: string
{
    case Paid = 'paid';
    case Waiting = 'waiting';
    case Closed = 'closed';
}
