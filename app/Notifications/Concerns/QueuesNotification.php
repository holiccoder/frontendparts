<?php

namespace App\Notifications\Concerns;

use Illuminate\Bus\Queueable;

/**
 * Shared convention for app transactional mail (SPEC §16.1, §16.3): every
 * mail notification is queued and delivered through the mail + database
 * channels so the Filament bell and the email share one system. Classes
 * using this trait must still `implements ShouldQueue` themselves.
 */
trait QueuesNotification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
