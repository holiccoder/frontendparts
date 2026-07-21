<?php

namespace App\Services\Billing;

use Illuminate\Http\Request;

/**
 * Region/currency routing for checkout (SPEC §7.5): decides whether a buyer
 * sees the CNY domestic QR checkout (Alipay / WeChat Pay) or the USD Paddle
 * checkout.
 *
 * Resolution order:
 *  1. Manual currency switch — the buyer's explicit choice, persisted in the
 *     session by CurrencySwitchController, always wins.
 *  2. Geo-detect heuristic — a Chinese top Accept-Language locale (zh-*)
 *     routes to CNY. Deliberately locale-based, not IP-based: no GeoIP
 *     database dependency, and it matches the buyer's own language choice.
 *  3. Default USD / Paddle.
 *
 * Resolved via the container so tests can bind a fake.
 */
class RegionDetector
{
    /**
     * Session key holding the buyer's manual currency choice.
     */
    public const SESSION_KEY = 'billing.currency';

    public const USD = 'USD';

    public const CNY = 'CNY';

    public function preferredCurrency(Request $request): string
    {
        $override = $request->session()->get(self::SESSION_KEY);

        if (in_array($override, [self::USD, self::CNY], true)) {
            return $override;
        }

        return $this->isChina($request) ? self::CNY : self::USD;
    }

    /**
     * Geo-detect heuristic: the browser's top preferred locale is Chinese.
     */
    public function isChina(Request $request): bool
    {
        $locale = $request->getPreferredLanguage() ?? '';

        return str_starts_with(strtolower($locale), 'zh');
    }
}
