<?php

namespace Tests\Feature\Security;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_throttled(): void
    {
        // Registration is limited to 10 requests per minute per IP.
        // Use invalid data so no user is created / logged in, keeping the
        // throttle key stable (guest IP) across all attempts.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post('/register', [
                'name' => "User {$attempt}",
                'email' => "user{$attempt}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'mismatch',
            ])->assertSessionHasErrors();
        }

        $this->post('/register', [
            'name' => 'Blocked user',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertTooManyRequests();
    }

    public function test_password_reset_is_throttled(): void
    {
        // Password reset link requests are limited to 5 per minute per IP.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/forgot-password', [
                'email' => "reset{$attempt}@example.com",
            ])->assertRedirect();
        }

        $this->post('/forgot-password', [
            'email' => 'blocked@example.com',
        ])->assertTooManyRequests();
    }

    public function test_ticket_creation_throttled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/dashboard/tickets', [
                'subject' => "Ticket {$attempt}",
                'category' => 'billing',
                'body' => 'I need help with my invoice.',
            ])->assertRedirect();
        }

        $this->post('/dashboard/tickets', [
            'subject' => 'Blocked ticket',
            'category' => 'billing',
            'body' => 'This should be blocked.',
        ])->assertTooManyRequests();
    }

    public function test_ticket_messages_throttled(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post("/dashboard/tickets/{$ticket->id}/messages", [
                'body' => "Message {$attempt}",
            ])->assertRedirect();
        }

        $this->post("/dashboard/tickets/{$ticket->id}/messages", [
            'body' => 'This should be blocked.',
        ])->assertTooManyRequests();
    }
}
