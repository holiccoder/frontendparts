<?php

namespace App\Services\Billing;

use App\Enums\DomesticChannel;
use App\Enums\DomesticTradeStatus;
use Carbon\Carbon;

/**
 * One normalized shape for everything the domestic rails tell us about a
 * trade (SPEC §7.5) — live `query` results and verified notify payloads
 * alike. `eventId` is the provider's notification id (Alipay notify_id /
 * WeChat notification id) and is null for query results; `amount` is a CNY
 * decimal string, already converted from WeChat's fen.
 */
final readonly class DomesticTradeResult
{
    public function __construct(
        public DomesticChannel $channel,
        public string $outTradeNo,
        public ?string $transactionId,
        public DomesticTradeStatus $status,
        public ?string $amount = null,
        public ?string $eventId = null,
        public ?Carbon $paidAt = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->status === DomesticTradeStatus::Paid;
    }
}
