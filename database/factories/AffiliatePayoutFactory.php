<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliatePayout>
 */
class AffiliatePayoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::factory(),
            'amount' => fake()->randomFloat(2, 50, 500),
            'currency' => 'USD',
            'status' => PayoutStatus::Processing,
            'method' => ['method' => 'paypal', 'email' => fake()->safeEmail()],
            'reference' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => PayoutStatus::Paid,
            'reference' => fake()->bothify('pp-########'),
            'paid_at' => now(),
        ]);
    }
}
