<?php

namespace App\Http\Controllers\Billing;

use App\Enums\DomesticChannel;
use App\Http\Controllers\Controller;
use App\Models\DomesticEvent;
use App\Services\Billing\DomesticGateway;
use App\Services\Billing\DomesticNotifyHandler;
use App\Services\Billing\InvalidDomesticSignatureException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Domestic async notify endpoints (server-to-server, CSRF-exempt — SPEC
 * §7.5): `/pay/domestic/alipay/notify` and `/pay/domestic/wechat/notify`.
 *
 * Signatures are verified at the DomesticGateway seam (Alipay RSA2 over the
 * form params; WeChat v3 over the Wechatpay-* headers + raw body). Every
 * accepted notification is recorded in `domestic_events`, so the providers'
 * at-least-once delivery stays idempotent on replay — the domestic twin of
 * PaddleWebhookController + paddle_events.
 */
class DomesticNotifyController extends Controller
{
    public function __invoke(Request $request, DomesticGateway $gateway, DomesticNotifyHandler $handler, string $channel): BaseResponse
    {
        $channel = DomesticChannel::tryFrom($channel);

        abort_if($channel === null, 404);

        try {
            $trade = match ($channel) {
                DomesticChannel::Alipay => $gateway->verifyAlipayNotify($request->post()),
                DomesticChannel::Wechat => $gateway->verifyWechatNotify($request->getContent(), $request->headers->all()),
            };
        } catch (InvalidDomesticSignatureException) {
            return response('Invalid signature.', 403);
        }

        if ($trade->eventId === null) {
            return response('Missing event id.', 400);
        }

        $alreadyProcessed = DomesticEvent::query()
            ->where('channel', $channel)
            ->where('event_id', $trade->eventId)
            ->exists();

        // Replayed notification: acknowledge but never re-apply.
        if ($alreadyProcessed) {
            return $this->ack($gateway, $channel);
        }

        // Recording and handling commit together: if handling fails, the
        // event row rolls back too and the provider's retry is processed fresh.
        DB::transaction(function () use ($channel, $trade, $handler): void {
            DomesticEvent::create([
                'channel' => $channel,
                'event_id' => $trade->eventId,
                'event_type' => $trade->status->value,
                'processed_at' => now(),
            ]);

            $handler->handle($trade);
        });

        return $this->ack($gateway, $channel);
    }

    /**
     * The provider-specific acknowledgement: Alipay expects the literal
     * "success" body, WeChat a JSON {"code":"SUCCESS"}.
     */
    private function ack(DomesticGateway $gateway, DomesticChannel $channel): BaseResponse
    {
        $ack = $gateway->successResponse($channel);

        return new Response((string) $ack->getBody(), $ack->getStatusCode(), [
            'Content-Type' => $ack->getHeaderLine('Content-Type') ?: 'text/plain',
        ]);
    }
}
