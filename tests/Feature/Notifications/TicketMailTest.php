<?php

namespace Tests\Feature\Notifications;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketRepliedNotification;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ticket lifecycle mail (SPEC §13.3, §16.1): created → support inbox,
 * admin reply → user, resolved → user; every mail carries the thread link.
 */
class TicketMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_ticket_notifies_admin()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/dashboard/tickets', [
                'subject' => 'License key question',
                'category' => 'license',
                'body' => 'Can I use a component in client work?',
            ]);

        // On-demand mail routed to the configured support inbox address.
        Notification::assertSentOnDemand(
            TicketCreatedNotification::class,
            fn (TicketCreatedNotification $notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === config('mail.admin.address')
                && $notification->ticket->subject === 'License key question',
        );
    }

    public function test_admin_reply_notifies_user()
    {
        Notification::fake();

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $ticket = SupportTicket::factory()->create(['status' => TicketStatus::Open]);

        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->callAction('reply', data: ['body' => 'Thanks for the report — fix incoming.'])
            ->assertHasNoActionErrors();

        Notification::assertSentTo($ticket->user, TicketRepliedNotification::class);
    }

    public function test_resolved_notifies_user()
    {
        Notification::fake();

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $ticket = SupportTicket::factory()->create(['status' => TicketStatus::Pending]);

        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->callAction('resolve')
            ->assertHasNoActionErrors();

        Notification::assertSentTo($ticket->user, TicketResolvedNotification::class);
    }

    public function test_mails_contain_thread_link()
    {
        $user = User::factory()->create(['name' => 'Thread Tester']);

        $ticket = SupportTicket::factory()->create([
            'user_id' => $user->id,
            'category' => TicketCategory::Billing,
        ]);

        // User-facing mails link the dashboard thread page.
        $userUrl = route('dashboard.tickets.show', $ticket);

        $repliedHtml = (string) (new TicketRepliedNotification($ticket))->toMail($user)->render();
        $this->assertStringContainsString($userUrl, $repliedHtml);

        $resolvedHtml = (string) (new TicketResolvedNotification($ticket))->toMail($user)->render();
        $this->assertStringContainsString($userUrl, $resolvedHtml);

        // The admin alert links the Filament ticket page.
        $adminUrl = SupportTicketResource::getUrl('view', ['record' => $ticket]);

        $createdHtml = (string) (new TicketCreatedNotification($ticket))->toMail(new AnonymousNotifiable)->render();
        $this->assertStringContainsString($adminUrl, $createdHtml);
    }
}
