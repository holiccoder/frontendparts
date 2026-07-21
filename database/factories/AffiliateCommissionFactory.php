<?php

namespace Database\Factories;

use App\Enums\CommissionStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateCommission>
 */
class AffiliateCommissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::factory(),
            'order_id' => Order::factory(),
            'referral_id' => null,
            'amount' => fake()->randomFloat(2, 1, 100),
            'currency' => 'USD',
            'status' => CommissionStatus::Pending,
            'payable_at' => null,
            'voided_reason' => null,
        ];
    }

    public function payable(): static
    {
        return $this->state(fn (): array => [
            'status' => CommissionStatus::Payable,
            'payable_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => CommissionStatus::Paid,
            'payable_at' => now(),
        ]);
    }

    public function voided(string $reason = 'refunded'): static
    {
        return $this->state(fn (): array => [
            'status' => CommissionStatus::Voided,
            'voided_reason' => $reason,
        ]);
    }

    /**
     * Keep the commission graph consistent: a referral belonging to the
     * commission's own affiliate.
     */
    public function withReferral(): static
    {
        return $this->state(fn (array $attributes): array => [
            'referral_id' => AffiliateReferral::factory()->state([
                'affiliate_id' => $attributes['affiliate_id'],
            ]),
        ]);
    }
}
