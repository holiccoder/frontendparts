<?php

namespace Tests\Feature\Support;

use App\Enums\TicketAuthorType;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * User-side ticketing (SPEC §13.3, §15.4): create with category, threaded
 * replies with private-disk attachments, own-tickets-only visibility,
 * NFR-10 creation rate limit and the TicketStatus transition map.
 */
class TicketUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_ticket_with_category()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/dashboard/tickets', [
                'subject' => 'Cannot download my purchase',
                'category' => 'billing',
                'body' => 'The download button spins forever.',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->sole();

        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame(TicketCategory::Billing, $ticket->category);
        $this->assertSame(TicketStatus::Open, $ticket->status);

        // The opening body becomes the first user-authored thread message.
        $message = $ticket->messages()->sole();
        $this->assertSame(TicketAuthorType::User, $message->author_type);
        $this->assertSame($user->id, $message->author_id);
        $this->assertSame('The download button spins forever.', $message->body);

        // The thread page renders with the message.
        $this->actingAs($user)
            ->get("/dashboard/tickets/{$ticket->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/tickets/show')
                ->where('ticket.subject', 'Cannot download my purchase')
                ->where('ticket.category', 'billing')
                ->where('ticket.status', 'open')
                ->has('messages', 1)
                ->where('messages.0.author_type', 'user')
                ->where('messages.0.body', 'The download button spins forever.')
            );

        // Category validation rejects unknown values.
        $this->actingAs($user)
            ->post('/dashboard/tickets', [
                'subject' => 'Bad category',
                'category' => 'nonsense',
                'body' => 'Body',
            ])
            ->assertSessionHasErrors('category');
    }

    public function test_create_form_preselects_category_from_query()
    {
        Notification::fake();

        $user = User::factory()->create();

        // Deep link from the copyright page (SPEC §9, §15.7): the takedown
        // category arrives preselected.
        $this->actingAs($user)
            ->get('/dashboard/tickets/new?category=takedown')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/tickets/new')
                ->where('presetCategory', 'takedown')
            );

        // Unknown values fall back to no preselection instead of erroring.
        $this->actingAs($user)
            ->get('/dashboard/tickets/new?category=nonsense')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('presetCategory', null)
            );

        // No query at all keeps the blank default.
        $this->actingAs($user)
            ->get('/dashboard/tickets/new')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('presetCategory', null)
            );
    }

    public function test_threaded_reply_appends()
    {
        Notification::fake();
        Storage::fake('local');

        $user = User::factory()->create();

        $ticket = SupportTicket::factory()->create([
            'user_id' => $user->id,
            'status' => TicketStatus::Pending,
        ]);

        $this->actingAs($user)
            ->post("/dashboard/tickets/{$ticket->id}/messages", [
                'body' => 'Here is the screenshot you asked for.',
                'attachments' => [UploadedFile::fake()->image('screenshot.png')],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $message = $ticket->messages()->latest('id')->sole();

        $this->assertSame(TicketAuthorType::User, $message->author_type);
        $this->assertSame('Here is the screenshot you asked for.', $message->body);

        // Attachment stored on the private disk, tracked in the JSON column.
        $this->assertCount(1, $message->attachments);
        $this->assertSame('screenshot.png', $message->attachments[0]['name']);
        Storage::disk('local')->assertExists($message->attachments[0]['path']);

        // User reply on a pending ticket re-opens it (TicketStatus map).
        $this->assertSame(TicketStatus::Open, $ticket->refresh()->status);
    }

    public function test_only_own_tickets_visible()
    {
        Notification::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();

        $own = SupportTicket::factory()->create(['user_id' => $user->id]);
        $foreign = SupportTicket::factory()->create(['user_id' => $other->id]);

        $this->get('/dashboard/tickets')->assertRedirect('/login');

        $this->actingAs($user)
            ->get('/dashboard/tickets')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/tickets/index')
                ->has('tickets', 1)
                ->where('tickets.0.id', $own->id)
            );

        $this->actingAs($user)->get("/dashboard/tickets/{$foreign->id}")->assertForbidden();

        $this->actingAs($user)
            ->post("/dashboard/tickets/{$foreign->id}/messages", ['body' => 'Not my ticket'])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch("/dashboard/tickets/{$foreign->id}", ['status' => 'closed'])
            ->assertForbidden();
    }

    public function test_create_rate_limited()
    {
        Notification::fake();

        $user = User::factory()->create();

        // NFR-10: ticket creation is throttled at 5 per minute per user.
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)
                ->post('/dashboard/tickets', [
                    'subject' => "Ticket {$i}",
                    'category' => 'other',
                    'body' => 'Body',
                ])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->post('/dashboard/tickets', [
                'subject' => 'One too many',
                'category' => 'other',
                'body' => 'Body',
            ])
            ->assertStatus(429);

        $this->assertSame(5, $user->tickets()->count());
    }

    public function test_status_flow_transitions_valid_only()
    {
        Notification::fake();

        // The transition map itself (SPEC §13.3 edges — see TicketStatus).
        $this->assertTrue(TicketStatus::Open->canTransitionTo(TicketStatus::Pending));
        $this->assertTrue(TicketStatus::Open->canTransitionTo(TicketStatus::Resolved));
        $this->assertTrue(TicketStatus::Open->canTransitionTo(TicketStatus::Closed));
        $this->assertTrue(TicketStatus::Pending->canTransitionTo(TicketStatus::Open));
        $this->assertTrue(TicketStatus::Resolved->canTransitionTo(TicketStatus::Open));
        $this->assertTrue(TicketStatus::Resolved->canTransitionTo(TicketStatus::Pending));
        $this->assertTrue(TicketStatus::Resolved->canTransitionTo(TicketStatus::Closed));
        $this->assertFalse(TicketStatus::Open->canTransitionTo(TicketStatus::Open));
        $this->assertFalse(TicketStatus::Closed->canTransitionTo(TicketStatus::Open));
        $this->assertFalse(TicketStatus::Closed->canTransitionTo(TicketStatus::Pending));

        $user = User::factory()->create();

        $ticket = SupportTicket::factory()->create([
            'user_id' => $user->id,
            'status' => TicketStatus::Open,
        ]);

        // Users cannot set admin-only states — only `closed` is accepted.
        $this->actingAs($user)
            ->patch("/dashboard/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertSessionHasErrors('status');

        $this->actingAs($user)
            ->patch("/dashboard/tickets/{$ticket->id}", ['status' => 'pending'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TicketStatus::Open, $ticket->refresh()->status);

        // Closing an open ticket is a valid transition.
        $this->actingAs($user)
            ->patch("/dashboard/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertSessionHasNoErrors();

        $this->assertSame(TicketStatus::Closed, $ticket->refresh()->status);

        // Closed is terminal: re-closing and replying are both rejected.
        $this->actingAs($user)
            ->patch("/dashboard/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        $this->actingAs($user)
            ->post("/dashboard/tickets/{$ticket->id}/messages", ['body' => 'Ping?'])
            ->assertSessionHasErrors('body');

        // A user reply on a resolved ticket re-opens it.
        $resolved = SupportTicket::factory()->create([
            'user_id' => $user->id,
            'status' => TicketStatus::Resolved,
        ]);

        $this->actingAs($user)
            ->post("/dashboard/tickets/{$resolved->id}/messages", ['body' => 'Still broken.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(TicketStatus::Open, $resolved->refresh()->status);
    }
}
