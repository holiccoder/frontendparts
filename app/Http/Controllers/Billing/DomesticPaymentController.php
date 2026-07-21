<?php

namespace App\Http\Controllers\Billing;

use App\Enums\DomesticChannel;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\DomesticGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/pay/domestic/{order}` (CSR, noindex — SPEC §15.3): the domestic QR
 * payment page. Creates the provider pre-order at the DomesticGateway seam
 * and renders the QR code — desktop scans it with Alipay/WeChat, mobile gets
 * the app wake-up deep link (SPEC §7.5). The page polls the status endpoint
 * until the paid notify (or the poll's own live query) activates the order.
 */
class DomesticPaymentController extends Controller
{
    public function __invoke(Request $request, DomesticGateway $gateway, Order $order): Response|RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id && $order->isDomestic(), 404);

        // Already paid (notify beat the buyer here) — nothing to scan.
        if ($order->status !== OrderStatus::Pending) {
            return redirect()->route('checkout.success');
        }

        $channel = DomesticChannel::tryFrom((string) $request->query('channel', ''))
            ?? $order->domestic_channel
            ?? DomesticChannel::Alipay;

        // One out_trade_no per Pending order, reused across pre-orders and
        // channels; the rail last scanned is stamped so result polling
        // queries the right provider.
        $order->fill([
            'out_trade_no' => $order->out_trade_no ?? DomesticGateway::outTradeNoFor($order),
            'domestic_channel' => $channel,
        ]);

        if ($order->isDirty()) {
            $order->save();
        }

        $preOrder = $gateway->scanPreOrder($channel, $order);

        return Inertia::render('pay/domestic', [
            'order' => [
                'plan' => $order->plan->value,
                'billing_period' => $order->billing_period->value,
                'amount' => $order->amount,
                'currency' => $order->currency,
            ],
            'channel' => $channel->value,
            'channels' => array_map(
                fn (DomesticChannel $option): string => $option->value,
                DomesticChannel::cases(),
            ),
            'qrContent' => $preOrder->qrContent,
            'wakeUpUrl' => $preOrder->wakeUpUrl,
            'statusUrl' => route('pay.domestic.status', $order),
            'successUrl' => route('checkout.success'),
        ]);
    }
}
