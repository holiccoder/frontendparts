<?php

namespace App\Services\Billing;

use App\Enums\DomesticChannel;

/**
 * A domestic pre-order (SPEC §7.5): the Alipay `qr_code` / WeChat `code_url`
 * returned by the provider's precreate call. The QR page encodes `qrContent`
 * as a QR image for desktop scan-to-pay and links `wakeUpUrl` as the mobile
 * app wake-up deep link (today both are the same provider URL — Alipay's
 * qr.alipay.com link wakes the app, WeChat's weixin:// link wakes WeChat).
 */
final readonly class DomesticPreOrder
{
    public function __construct(
        public DomesticChannel $channel,
        public string $outTradeNo,
        public string $qrContent,
        public string $wakeUpUrl,
    ) {}
}
