<?php

namespace App\Services\Billing;

use Laravel\Paddle\Cashier;

/**
 * Thin gateway over Paddle's REST API for the calls the app issues directly
 * (everything funnels through here so tests can `Http::fake()` a single
 * boundary). Customer creation and checkout-session building stay with
 * Cashier's Billable API, which itself rides on the same HTTP client.
 */
class PaddleGateway
{
    /**
     * Refund a Paddle transaction in full by creating a refund adjustment
     * covering every line item (SPEC §7.3).
     *
     * @return array<string, mixed> The created Paddle adjustment payload.
     */
    public function refund(string $transactionId, string $reason): array
    {
        $transaction = Cashier::api('GET', "transactions/{$transactionId}")['data'];

        $items = collect($transaction['details']['line_items'] ?? [])
            ->map(fn (array $lineItem): array => [
                'item_id' => $lineItem['id'],
                'type' => 'full',
            ])
            ->values()
            ->all();

        return Cashier::api('POST', 'adjustments', [
            'action' => 'refund',
            'transaction_id' => $transactionId,
            'reason' => $reason,
            'items' => $items,
        ])['data'];
    }

    /**
     * Cancel a Paddle subscription at the end of its current billing period
     * (SPEC §16.2 B7) — the user keeps access until ends_at, mirroring the
     * `subscription.canceled` webhook path.
     *
     * @return array<string, mixed> The updated Paddle subscription payload.
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        return Cashier::api('POST', "subscriptions/{$subscriptionId}/cancel", [
            'effective_from' => 'next_billing_period',
        ])['data'];
    }
}
