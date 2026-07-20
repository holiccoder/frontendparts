<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => fake()->sentence(6),
            'category' => fake()->randomElement(TicketCategory::cases()),
            'status' => TicketStatus::Open,
        ];
    }
}
