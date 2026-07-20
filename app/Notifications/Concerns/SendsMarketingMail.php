<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Shared convention for marketing mail (SPEC §16.3): every
 * non-transactional email carries a one-click unsubscribe link. The link
 * is a signed route so it works logged-out and cannot be forged for other
 * users; it opts the user out of ALL marketing categories at once.
 */
trait SendsMarketingMail
{
    protected function withUnsubscribeFooter(MailMessage $message, User $notifiable): MailMessage
    {
        return $message
            ->line('---')
            ->line('[Unsubscribe from all marketing emails]('.$this->unsubscribeUrl($notifiable).') · [Manage preferences]('.route('notifications.edit').')');
    }

    private function unsubscribeUrl(User $notifiable): string
    {
        return URL::signedRoute('unsubscribe', ['user' => $notifiable->id]);
    }
}
