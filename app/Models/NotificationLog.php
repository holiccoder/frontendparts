<?php

namespace App\Models;

use Database\Factories\NotificationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * One row per sent notification per channel (SPEC §16.3), written by the
 * LogNotificationSent listener on Laravel's NotificationSent event. The
 * payload holds the exact notification instance, PHP-serialized and
 * base64-encoded, so the Filament resend action can requeue it generically
 * for any notification class.
 */
class NotificationLog extends Model
{
    /** @use HasFactory<NotificationLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'notification',
        'channel',
        'payload',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Human-readable recipient for the admin table — the notifiable's email
     * when it has one (User/Admin do), otherwise a class#id fallback.
     */
    public function recipientLabel(): string
    {
        $notifiable = $this->notifiable;

        if ($notifiable === null) {
            return "{$this->notifiable_type}#{$this->notifiable_id}";
        }

        return (string) ($notifiable->getAttribute('email') ?? $notifiable->getKey());
    }

    /**
     * Requeue the exact notification instance to its original notifiable
     * (SPEC §16.3 resend action).
     */
    public function resend(): void
    {
        $serialized = $this->payload['serialized'] ?? null;

        if (! is_string($serialized) || $serialized === '') {
            throw new RuntimeException('This log entry has no resendable notification payload.');
        }

        $notifiable = $this->notifiable;

        if ($notifiable === null) {
            throw new RuntimeException('The original recipient no longer exists.');
        }

        $notification = unserialize(base64_decode($serialized));

        if (! $notification instanceof Notification) {
            throw new RuntimeException('The stored payload is not a notification.');
        }

        $notifiable->notify($notification);
    }
}
