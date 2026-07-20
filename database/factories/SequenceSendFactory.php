<?php

namespace Database\Factories;

use App\Models\SequenceSend;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SequenceSend>
 */
class SequenceSendFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sequence' => 'b1-free-onboarding',
            'step' => 'day-'.fake()->unique()->numberBetween(1, 30),
            'sent_at' => now(),
        ];
    }
}
