<?php

namespace Database\Factories;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Affiliate>
 */
class AffiliateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => Affiliate::generateCode(),
            'status' => AffiliateStatus::Active,
            'payout_method' => null,
            'terms_accepted_at' => now(),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => AffiliateStatus::Suspended,
        ]);
    }
}
