<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\PaddleEvent;
use App\Services\Billing\PaddleWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Paddle webhook endpoint (SPEC §7.3). Requests reach here only after the
 * `Paddle-Signature` HMAC has been verified by middleware. Every accepted
 * event is recorded in `paddle_events` — replayed event ids are no-ops, so
 * Paddle's at-least-once delivery stays idempotent.
 */
class PaddleWebhookController extends Controller
{
    public function __invoke(Request $request, PaddleWebhookHandler $handler): Response
    {
        $payload = $request->all();

        $eventId = $payload['event_id'] ?? null;

        if (! is_string($eventId) || $eventId === '') {
            return response('Missing event id.', 400);
        }

        if (PaddleEvent::query()->where('event_id', $eventId)->exists()) {
            return response('Webhook already processed.');
        }

        // Recording and handling commit together: if handling fails, the
        // event row rolls back too and Paddle's retry is processed fresh.
        DB::transaction(function () use ($payload, $eventId, $handler): void {
            PaddleEvent::create([
                'event_id' => $eventId,
                'event_type' => (string) ($payload['event_type'] ?? 'unknown'),
                'processed_at' => now(),
            ]);

            $handler->handle($payload);
        });

        return response('Webhook Handled');
    }
}
