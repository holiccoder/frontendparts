<?php

namespace App\Http\Controllers\Billing;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\DomesticGateway;
use App\Services\Billing\DomesticNotifyHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * QR-page result polling (SPEC §7.5): returns the order's state so the
 * payment page can flip to the success screen the moment the paid notify
 * lands. While the order is still Pending the poll also live-queries the
 * provider once per call — a paid trade activates the order through the
 * same DomesticNotifyHandler as the notify endpoints, so the flow completes
 * even where notify callbacks cannot reach the app (local dev, sandbox).
 */
class DomesticPaymentStatusController extends Controller
{
    public function __invoke(Request $request, DomesticGateway $gateway, DomesticNotifyHandler $handler, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id && $order->isDomestic(), 404);

        if ($order->status === OrderStatus::Pending
            && $order->out_trade_no !== null
            && $order->domestic_channel !== null) {
            $handler->handle($gateway->queryTrade($order->domestic_channel, $order->out_trade_no));

            $order->refresh();
        }

        return response()->json([
            'status' => $order->status->value,
            'paid' => $order->status === OrderStatus::Active,
        ]);
    }
}
