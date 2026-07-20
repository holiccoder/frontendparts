<?php

namespace Database\Factories;

use App\Enums\TicketAuthorType;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketMessage>
 */
class SupportTicketMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => SupportTicket::factory(),
            'author_type' => TicketAuthorType::User,
            'author_id' => null,
            'body' => fake()->paragraph(),
            'attachments' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'author_type' => TicketAuthorType::Admin,
        ]);
    }
}
