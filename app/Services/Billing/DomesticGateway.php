<?php

namespace App\Services\Billing;

use App\Enums\DomesticChannel;
use App\Enums\DomesticTradeStatus;
use App\Models\Order;
use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface;
use Yansongda\Artful\Rocket;
use Yansongda\Pay\Exception\DecryptException;
use Yansongda\Pay\Exception\InvalidSignException;
use Yansongda\Pay\Pay;
use Yansongda\Supports\Collection;

/**
 * The single seam over yansongda/pay (SPEC §7.5): every Alipay / WeChat Pay
 * SDK call in the app funnels through here, so tests fake this one class and
 * no other code touches the SDK. Everything crosses the boundary already
 * normalized into DomesticPreOrder / DomesticTradeResult — raw Collections
 * never escape.
 *
 * Refund is exposed for the refund phase (3.7.4) but intentionally not wired
 * into RefundService yet.
 */
class DomesticGateway
{
    /**
     * The SDK keeps its config + event listeners in a static container, so
     * configuration happens exactly once per process.
     */
    private static bool $configured = false;

    /**
     * Create the provider pre-order for a Pending domestic order and return
     * the QR/wake-up payload for the payment page.
     */
    public function scanPreOrder(DomesticChannel $channel, Order $order): DomesticPreOrder
    {
        $this->configure();

        $outTradeNo = $order->out_trade_no ?? self::outTradeNoFor($order);
        $subject = config('app.name')." {$order->plan->value} plan ({$order->billing_period->value})";

        $qrContent = match ($channel) {
            DomesticChannel::Alipay => (string) Pay::alipay()->scan([
                'out_trade_no' => $outTradeNo,
                'total_amount' => (string) $order->amount,
                'subject' => $subject,
            ])->get('qr_code', ''),
            DomesticChannel::Wechat => (string) Pay::wechat()->scan([
                'out_trade_no' => $outTradeNo,
                'description' => $subject,
                'amount' => [
                    'total' => self::fen($order->amount),
                    'currency' => 'CNY',
                ],
            ])->get('code_url', ''),
        };

        return new DomesticPreOrder($channel, $outTradeNo, $qrContent, $qrContent);
    }

    /**
     * Live-query a trade by our out_trade_no (QR-page result polling).
     */
    public function queryTrade(DomesticChannel $channel, string $outTradeNo): DomesticTradeResult
    {
        $this->configure();

        $result = match ($channel) {
            DomesticChannel::Alipay => Pay::alipay()->query(['out_trade_no' => $outTradeNo]),
            DomesticChannel::Wechat => Pay::wechat()->query(['out_trade_no' => $outTradeNo]),
        };

        return $this->normalizeTrade($channel, $this->unwrap($result), eventId: null);
    }

    /**
     * Verify an Alipay async notify (form POST) and return the normalized
     * trade. Alipay notifies the legacy `trade_status_sync` event for scan
     * pre-orders; the sign is RSA2 over the sorted params.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws InvalidDomesticSignatureException When the signature is invalid.
     */
    public function verifyAlipayNotify(array $params): DomesticTradeResult
    {
        $this->configure();

        try {
            $data = Pay::alipay()->callback($params);
        } catch (InvalidSignException|DecryptException $exception) {
            throw new InvalidDomesticSignatureException($exception->getMessage(), previous: $exception);
        }

        $notify = $this->normalizeTrade(DomesticChannel::Alipay, $this->unwrap($data), $params['notify_id'] ?? null);

        // Alipay scan pre-orders notify without a notify_id in some sandbox
        // flows — fall back to a deterministic id so replays stay idempotent.
        return $notify->eventId !== null ? $notify : new DomesticTradeResult(
            $notify->channel,
            $notify->outTradeNo,
            $notify->transactionId,
            $notify->status,
            $notify->amount,
            "{$notify->outTradeNo}:{$notify->status->value}",
            $notify->paidAt,
        );
    }

    /**
     * Verify a WeChat Pay v3 notify (JSON body, Wechatpay-* headers) and
     * return the normalized trade with the resource already decrypted.
     *
     * @param  array<string, mixed>  $headers
     *
     * @throws InvalidDomesticSignatureException When the signature is invalid.
     */
    public function verifyWechatNotify(string $rawBody, array $headers): DomesticTradeResult
    {
        $this->configure();

        try {
            $data = Pay::wechat()->callback(['body' => $rawBody, 'headers' => $headers]);
        } catch (InvalidSignException|DecryptException $exception) {
            throw new InvalidDomesticSignatureException($exception->getMessage(), previous: $exception);
        }

        // The verified notify body with the decrypted trade in `resource`.
        $notify = $this->unwrap($data);

        /** @var array<string, mixed> $resource */
        $resource = $notify->get('resource', []);

        $trade = $this->normalizeTrade(DomesticChannel::Wechat, new Collection($resource), $notify->get('id'));

        return new DomesticTradeResult(
            $trade->channel,
            $trade->outTradeNo,
            $trade->transactionId,
            $trade->status,
            $trade->amount,
            $trade->eventId,
            isset($resource['success_time']) ? Carbon::parse($resource['success_time']) : $trade->paidAt,
        );
    }

    /**
     * Refund a paid domestic order in full via the provider API (SPEC §7.5 —
     * wired into RefundService by the refund phase, kept at the gateway seam).
     *
     * @return array<string, mixed> The provider's refund response payload.
     */
    public function refund(DomesticChannel $channel, Order $order, string $reason, string $outRefundNo): array
    {
        $this->configure();

        $result = match ($channel) {
            DomesticChannel::Alipay => Pay::alipay()->refund([
                'out_trade_no' => $order->out_trade_no,
                'refund_amount' => (string) $order->amount,
                'refund_reason' => $reason,
            ]),
            DomesticChannel::Wechat => Pay::wechat()->refund([
                'out_trade_no' => $order->out_trade_no,
                'out_refund_no' => $outRefundNo,
                'reason' => $reason,
                'amount' => [
                    'refund' => self::fen($order->amount),
                    'total' => self::fen($order->amount),
                    'currency' => 'CNY',
                ],
            ]),
        };

        return $this->unwrap($result)->all();
    }

    /**
     * The provider-specific acknowledgement body for a processed notify
     * (Alipay expects the literal "success", WeChat a JSON code).
     */
    public function successResponse(DomesticChannel $channel): ResponseInterface
    {
        $this->configure();

        return match ($channel) {
            DomesticChannel::Alipay => Pay::alipay()->success(),
            DomesticChannel::Wechat => Pay::wechat()->success(),
        };
    }

    /**
     * Our pre-order reference (both rails' `out_trade_no`): unique per order
     * attempt, ASCII, ≤ 64 chars to fit Alipay's limit.
     */
    public static function outTradeNoFor(Order $order): string
    {
        return sprintf('fp%d%s', $order->id, bin2hex(random_bytes(6)));
    }

    /**
     * CNY decimal string → fen integer for WeChat payloads.
     */
    private static function fen(string $amount): int
    {
        return (int) bcmul($amount, '100', 0);
    }

    private function configure(): void
    {
        if (self::$configured) {
            return;
        }

        Pay::config(config('pay'));

        self::$configured = true;
    }

    /**
     * The SDK returns a Rocket from some pipelines and a Collection from
     * others — both carry the payload; normalize to a Collection.
     */
    private function unwrap(mixed $result): Collection
    {
        if ($result instanceof Rocket) {
            $destination = $result->getDestination();

            return $destination instanceof Collection ? $destination : new Collection((array) $destination);
        }

        return $result instanceof Collection ? $result : new Collection((array) $result);
    }

    /**
     * Map a raw provider payload (query response or verified notify) onto
     * the shared trade shape.
     *
     * @param  Collection<string, mixed>  $data
     */
    private function normalizeTrade(DomesticChannel $channel, Collection $data, ?string $eventId): DomesticTradeResult
    {
        return match ($channel) {
            DomesticChannel::Alipay => new DomesticTradeResult(
                $channel,
                (string) $data->get('out_trade_no', ''),
                $data->get('trade_no'),
                match ($data->get('trade_status')) {
                    'TRADE_SUCCESS', 'TRADE_FINISHED' => DomesticTradeStatus::Paid,
                    'TRADE_CLOSED' => DomesticTradeStatus::Closed,
                    default => DomesticTradeStatus::Waiting,
                },
                $data->get('total_amount') !== null ? (string) $data->get('total_amount') : null,
                $eventId,
                // Alipay timestamps are China Standard Time.
                isset($data['gmt_payment']) ? Carbon::parse($data->get('gmt_payment'), 'Asia/Shanghai') : null,
            ),
            DomesticChannel::Wechat => new DomesticTradeResult(
                $channel,
                (string) $data->get('out_trade_no', ''),
                $data->get('transaction_id'),
                match ($data->get('trade_state')) {
                    'SUCCESS' => DomesticTradeStatus::Paid,
                    'CLOSED', 'REVOKED', 'PAYERROR' => DomesticTradeStatus::Closed,
                    default => DomesticTradeStatus::Waiting,
                },
                isset($data['amount']['total']) ? bcdiv((string) $data->get('amount.total'), '100', 2) : null,
                $eventId,
                isset($data['success_time']) ? Carbon::parse($data->get('success_time')) : null,
            ),
        };
    }
}
