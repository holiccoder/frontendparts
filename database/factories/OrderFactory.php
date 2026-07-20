<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $period = fake()->randomElement(BillingPeriod::cases());
        $startsAt = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'user_id' => User::factory(),
            'plan' => fake()->randomElement(OrderPlan::cases()),
            'status' => fake()->randomElement(OrderStatus::cases()),
            'billing_period' => $period,
            'amount' => fake()->randomFloat(2, 0, 300),
            'currency' => 'USD',
            'starts_at' => $startsAt,
            'ends_at' => match ($period) {
                BillingPeriod::Monthly => (clone $startsAt)->modify('+1 month'),
                BillingPeriod::Quarterly => (clone $startsAt)->modify('+3 months'),
                BillingPeriod::Yearly => (clone $startsAt)->modify('+1 year'),
                BillingPeriod::Lifetime => null,
            },
            'cancelled_at' => null,
        ];
    }
}
