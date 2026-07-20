<?php

namespace App\Notifications;

use App\Models\Component;
use App\Notifications\Concerns\QueuesNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use QueuesNotification;

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Welcome to FrontendParts')
            ->greeting("Hi {$notifiable->name},")
            ->line('Thanks for creating your FrontendParts account — your library of production-ready React & Vue components is ready to explore.');

        $components = $this->featuredComponents();

        if ($components->count() === 3) {
            $message->line('Here are three components to get you started:');

            foreach ($components as $component) {
                $message->line("- [{$component->name}]({$component->publicUrl()})");
            }
        }

        return $message->action('Browse the catalog', route('components.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Welcome to FrontendParts')
            ->body('Your account is ready — browse the catalog to grab your first components.')
            ->icon('heroicon-o-sparkles')
            ->getDatabaseMessage();
    }

    /**
     * The three newest published components (SPEC §16.2 Day-0 welcome). The
     * email fails soft to the plain catalog CTA when fewer than three exist.
     *
     * @return Collection<int, Component>
     */
    protected function featuredComponents(): Collection
    {
        return Component::published()->latest()->limit(3)->get();
    }
}
