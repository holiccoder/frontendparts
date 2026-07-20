<?php

namespace App\Listeners;

use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;

class SendWelcomeNotification
{
    /**
     * Queue the Day-0 welcome email (SPEC §16.1, §16.4) alongside the
     * framework's VerifyEmail listener on the same Registered event.
     */
    public function handle(Registered $event): void
    {
        $event->user->notify(new WelcomeNotification);
    }
}
