<?php

namespace Database\Factories;

use App\Models\Affiliate;
use App\Models\AffiliateReferral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateReferral>
 */
class AffiliateReferralFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::factory(),
            'referred_user_id' => null,
            'clicked_at' => now(),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'landing_url' => null,
            'converted_at' => null,
        ];
    }

    /**
     * The referral linked to a signed-up user (SPEC §17.1 step 3).
     */
    public function converted(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'referred_user_id' => $user?->id ?? User::factory(),
            'converted_at' => now(),
        ]);
    }
}
