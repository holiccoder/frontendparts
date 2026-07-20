<?php

namespace Database\Factories;

use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => User::factory(),
            'notification' => WelcomeNotification::class,
            'channel' => fake()->randomElement(['mail', 'database']),
            'payload' => ['serialized' => base64_encode(serialize(new WelcomeNotification))],
            'sent_at' => now(),
        ];
    }
}
