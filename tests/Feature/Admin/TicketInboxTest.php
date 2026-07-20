<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\TicketAuthorType;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Models\Admin;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filament ticket inbox (SPEC §13.3): status/category filters, reply →
 * pending, order context on billing tickets and the resolve action.
 */
class TicketInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_filters_by_status_and_category()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $openBilling = SupportTicket::factory()->create([
            'status' => TicketStatus::Open,
            'category' => TicketCategory::Billing,
        ]);
        $pendingTechnical = SupportTicket::factory()->create([
            'status' => TicketStatus::Pending,
            'category' => TicketCategory::Technical,
        ]);
        $resolvedTakedown = SupportTicket::factory()->create([
            'status' => TicketStatus::Resolved,
            'category' => TicketCategory::Takedown,
        ]);

        Livewire::test(ListSupportTickets::class)
            ->assertCanSeeTableRecords([$openBilling, $pendingTechnical, $resolvedTakedown])
            ->filterTable('status', TicketStatus::Open->value)
            ->assertCanSeeTableRecords([$openBilling])
            ->assertCanNotSeeTableRecords([$pendingTechnical, $resolvedTakedown]);

        Livewire::test(ListSupportTickets::class)
            ->filterTable('status', TicketStatus::Pending->value)
            ->assertCanSeeTableRecords([$pendingTechnical])
            ->assertCanNotSeeTableRecords([$openBilling, $resolvedTakedown]);

        Livewire::test(ListSupportTickets::class)
            ->filterTable('category', TicketCategory::Takedown->value)
            ->assertCanSeeTableRecords([$resolvedTakedown])
            ->assertCanNotSeeTableRecords([$openBilling, $pendingTechnical]);

        // Filters compose: open + billing leaves exactly one record.
        Livewire::test(ListSupportTickets::class)
            ->filterTable('status', TicketStatus::Open->value)
            ->filterTable('category', TicketCategory::Billing->value)
            ->assertCanSeeTableRecords([$openBilling])
            ->assertCanNotSeeTableRecords([$pendingTechnical, $resolvedTakedown]);
    }

    public function test_admin_reply_sets_pending()
    {
        Notification::fake();

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $ticket = SupportTicket::factory()->create(['status' => TicketStatus::Open]);

        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->callAction('reply', data: [
                'body' => 'We are looking into this for you.',
            ])
            ->assertHasNoActionErrors();

        // Admin reply flips the ticket to pending (TicketStatus map).
        $this->assertSame(TicketStatus::Pending, $ticket->refresh()->status);

        $reply = $ticket->messages()->latest('id')->sole();
        $this->assertSame(TicketAuthorType::Admin, $reply->author_type);
        $this->assertSame($admin->id, $reply->author_id);
        $this->assertSame('We are looking into this for you.', $reply->body);

        // A reply on an already-pending ticket keeps it pending.
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->callAction('reply', data: ['body' => 'Any more details?'])
            ->assertHasNoActionErrors();

        $this->assertSame(TicketStatus::Pending, $ticket->refresh()->status);

        // Closed tickets do not offer the reply action.
        $ticket->update(['status' => TicketStatus::Closed]);

        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->assertActionHidden('reply');
    }

    public function test_billing_ticket_shows_order_context()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'amount' => 199,
            'currency' => 'USD',
        ]);

        $billing = SupportTicket::factory()->create([
            'user_id' => $user->id,
            'category' => TicketCategory::Billing,
        ]);

        Livewire::test(ViewSupportTicket::class, ['record' => $billing->id])
            ->assertSee('Order context')
            ->assertSee('Pro')
            ->assertSee('active')
            ->assertSee('199.00 USD');

        // Non-billing tickets hide the section.
        $technical = SupportTicket::factory()->create([
            'user_id' => $user->id,
            'category' => TicketCategory::Technical,
        ]);

        Livewire::test(ViewSupportTicket::class, ['record' => $technical->id])
            ->assertDontSee('Order context');
    }

    public function test_resolve_action()
    {
        Notification::fake();

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $ticket = SupportTicket::factory()->create(['status' => TicketStatus::Open]);

        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->callAction('resolve')
            ->assertHasNoActionErrors();

        $this->assertSame(TicketStatus::Resolved, $ticket->refresh()->status);

        // Resolved (and closed) tickets no longer offer the resolve action.
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->assertActionHidden('resolve');
    }
}
