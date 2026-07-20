<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;
use Throwable;

/**
 * Writes the admin notification log (SPEC §16.3): one row per notification
 * per channel, for every notification the app actually sends. Queued and
 * immediate sends both pass through NotificationSent; Notification::fake()
 * suppresses the event, so faked test sends never log.
 *
 * The notification instance is serialized so the Filament resend action can
 * requeue it verbatim. Serialization is defensive — a notification that
 * cannot be serialized must never break the mail pipeline, it just logs
 * without a resendable payload.
 */
class LogNotificationSent
{
    public function handle(NotificationSent $event): void
    {
        // On-demand (anonymous) notifications have no persistable recipient.
        if (! $event->notifiable instanceof Model) {
            return;
        }

        NotificationLog::query()->create([
            'notifiable_type' => $event->notifiable->getMorphClass(),
            'notifiable_id' => $event->notifiable->getKey(),
            'notification' => $event->notification::class,
            'channel' => $event->channel,
            'payload' => $this->payload($event->notification),
            'sent_at' => now(),
        ]);
    }

    /**
     * @return array{serialized: string|null}
     */
    private function payload(object $notification): array
    {
        try {
            return ['serialized' => base64_encode(serialize($notification))];
        } catch (Throwable) {
            return ['serialized' => null];
        }
    }
}
